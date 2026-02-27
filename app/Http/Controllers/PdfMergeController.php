<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use ZipArchive;

class PdfMergeController extends Controller
{
    public function index()
    {
        return view('merge');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|mimes:zip,pdf|max:51200', // Max 50MB per file, accetta ZIP e PDF
        ]);

        try {
            $uploadedFiles = $request->file('files');
            $allPdfs = [];

            // Crea directory temporanea
            $tempDir = storage_path('app/temp/' . uniqid());
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Ordina i file per numero nel nome
            $sortedFiles = collect($uploadedFiles)->sortBy(function($file) {
                $filename = $file->getClientOriginalName();
                // Estrai il numero dal nome (es: "ITAS 1.zip" -> 1, "IT 11.zip" -> 11, "file 5.pdf" -> 5)
                if (preg_match('/(\d+)/', $filename, $matches)) {
                    return (int)$matches[1];
                }
                // Se non c'è numero, metti alla fine
                return 9999;
            })->values()->all();

            // Processa ogni file nell'ordine corretto
            foreach ($sortedFiles as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                
                if ($extension === 'zip') {
                    // Processa ZIP
                    $zipPath = $file->getRealPath();
                    $extractDir = $tempDir . '/' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                    // Estrai ZIP
                    $zip = new ZipArchive;
                    if ($zip->open($zipPath) === TRUE) {
                        $zip->extractTo($extractDir);
                        $zip->close();

                        // Trova tutti i PDF nella directory estratta
                        $pdfs = $this->findPdfs($extractDir);
                        $allPdfs = array_merge($allPdfs, $pdfs);
                    } else {
                        throw new \Exception("Impossibile aprire il file ZIP: " . $file->getClientOriginalName());
                    }
                } elseif ($extension === 'pdf') {
                    // Processa PDF diretto
                    $pdfPath = $tempDir . '/' . $file->getClientOriginalName();
                    $file->move($tempDir, $file->getClientOriginalName());
                    $allPdfs[] = $pdfPath;
                }
            }

            // Ordina i PDF per nome
            sort($allPdfs);

            if (empty($allPdfs)) {
                throw new \Exception("Nessun file PDF trovato");
            }

            // Unisci i PDF
            $mergedPdfPath = $this->mergePdfs($allPdfs);

            // Pulisci directory temporanea
            $this->deleteDirectory($tempDir);

            // Restituisci il PDF unito
            return response()->download($mergedPdfPath, 'merged_' . date('Y-m-d_H-i-s') . '.pdf')->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function findPdfs($directory)
    {
        $pdfs = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                $pdfs[] = $file->getPathname();
            }
        }

        return $pdfs;
    }

    private function mergePdfs($pdfFiles)
    {
        $pdf = new Fpdi();

        foreach ($pdfFiles as $file) {
            try {
                $pageCount = $pdf->setSourceFile($file);

                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);

                    // Aggiungi pagina con le dimensioni originali
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            } catch (\Exception $e) {
                // Salta i PDF corrotti
                continue;
            }
        }

        // Salva il PDF unito
        $outputPath = storage_path('app/temp/merged_' . uniqid() . '.pdf');
        $pdf->Output('F', $outputPath);

        return $outputPath;
    }

    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
