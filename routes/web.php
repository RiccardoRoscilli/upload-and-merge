<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfMergeController;

Route::get('/', [PdfMergeController::class, 'index'])->name('merge.index');
Route::post('/upload', [PdfMergeController::class, 'upload'])->name('merge.upload');
