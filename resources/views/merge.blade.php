<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Upload & Merge PDF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 800px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        #dropzone {
            border: 3px dashed #dee2e6;
            border-radius: 10px;
            padding: 60px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        #dropzone:hover, #dropzone.dragover {
            border-color: #667eea;
            background: #e7f1ff;
        }
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            background: #f8f9fa;
            cursor: move;
        }
        .file-item:hover {
            background: #e9ecef;
        }
        .file-item.sortable-ghost {
            opacity: 0.4;
            background: #667eea;
        }
        .file-item.sortable-drag {
            opacity: 1;
            background: #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .drag-handle {
            cursor: grab;
            color: #6c757d;
            margin-right: 10px;
        }
        .drag-handle:active {
            cursor: grabbing;
        }
        .progress {
            height: 30px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-body p-5">
                <h1 class="text-center mb-4">
                    <i class="fas fa-file-pdf text-danger"></i> Upload & Merge PDF
                </h1>
                <p class="text-center text-muted mb-4">
                    Carica file ZIP contenenti PDF. Verranno estratti e uniti in un unico documento.
                </p>

                <form id="uploadForm">
                    @csrf
                    
                    <!-- Dropzone -->
                    <div id="dropzone" class="mb-4">
                        <i class="fas fa-cloud-upload-alt fa-4x text-muted mb-3"></i>
                        <h5>Trascina i file ZIP qui</h5>
                        <p class="text-muted mb-3">oppure</p>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-folder-open"></i> Seleziona File
                        </button>
                        <input type="file" id="fileInput" name="files[]" multiple accept=".zip" style="display: none;">
                        <p class="text-muted mt-3 mb-0"><small>Formati supportati: ZIP (max 50MB per file)</small></p>
                    </div>

                    <!-- Lista File -->
                    <div id="fileList" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">File Selezionati (<span id="fileCount">0</span>)</h6>
                            <small class="text-muted"><i class="fas fa-arrows-alt"></i> Trascina per riordinare</small>
                        </div>
                        <div id="fileListContent"></div>
                    </div>

                    <!-- Pulsanti -->
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" id="mergeBtn" class="btn btn-success flex-grow-1" disabled>
                            <i class="fas fa-compress-arrows-alt"></i> Unisci PDF
                        </button>
                        <button type="button" id="clearBtn" class="btn btn-secondary" disabled>
                            <i class="fas fa-times"></i> Cancella
                        </button>
                    </div>
                </form>

                <!-- Progress -->
                <div id="progressContainer" class="mt-4" style="display: none;">
                    <div class="progress">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">
                            <span id="progressText">0%</span>
                        </div>
                    </div>
                    <p class="text-center text-muted mt-2 mb-0" id="statusText">Elaborazione in corso...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        let selectedFiles = [];
        let sortable = null;

        // Dropzone events
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('fileInput');

        dropzone.addEventListener('click', (e) => {
            if (e.target.tagName !== 'BUTTON') {
                fileInput.click();
            }
        });

        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('dragover');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            for (let file of files) {
                if (file.name.endsWith('.zip')) {
                    selectedFiles.push(file);
                }
            }
            updateFileList();
        }

        function updateFileList() {
            const fileList = document.getElementById('fileList');
            const fileListContent = document.getElementById('fileListContent');
            const fileCount = document.getElementById('fileCount');
            const mergeBtn = document.getElementById('mergeBtn');
            const clearBtn = document.getElementById('clearBtn');

            if (selectedFiles.length === 0) {
                fileList.style.display = 'none';
                mergeBtn.disabled = true;
                clearBtn.disabled = true;
                return;
            }

            fileList.style.display = 'block';
            fileCount.textContent = selectedFiles.length;
            mergeBtn.disabled = false;
            clearBtn.disabled = false;

            let html = '';
            selectedFiles.forEach((file, index) => {
                const size = (file.size / 1024 / 1024).toFixed(2);
                html += `
                    <div class="file-item">
                        <div>
                            <i class="fas fa-file-archive text-warning me-2"></i>
                            <strong>${file.name}</strong>
                            <small class="text-muted ms-2">(${size} MB)</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeFile(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            });

            fileListContent.innerHTML = html;
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
        }

        document.getElementById('clearBtn').addEventListener('click', () => {
            selectedFiles = [];
            fileInput.value = '';
            updateFileList();
        });

        document.getElementById('uploadForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            if (selectedFiles.length === 0) {
                Swal.fire('Attenzione', 'Seleziona almeno un file ZIP', 'warning');
                return;
            }

            const formData = new FormData();
            selectedFiles.forEach(file => {
                formData.append('files[]', file);
            });

            // Mostra progress
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('mergeBtn').disabled = true;
            document.getElementById('clearBtn').disabled = true;

            try {
                const response = await fetch('{{ route("merge.upload") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Errore durante l\'elaborazione');
                }

                // Download del PDF
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'merged_' + new Date().toISOString().slice(0,19).replace(/:/g,'-') + '.pdf';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);

                Swal.fire('Successo!', 'PDF unito e scaricato con successo', 'success');

                // Reset
                selectedFiles = [];
                fileInput.value = '';
                updateFileList();

            } catch (error) {
                Swal.fire('Errore', error.message, 'error');
            } finally {
                document.getElementById('progressContainer').style.display = 'none';
                document.getElementById('mergeBtn').disabled = false;
                document.getElementById('clearBtn').disabled = false;
            }
        });
    </script>
</body>
</html>
