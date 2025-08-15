<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .barcode-preview {
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px 0;
            text-align: center;
        }
        .barcode-preview img {
            max-width: 200px;
            height: auto;
        }
        .loading {
            display: none;
        }
        .preview-container {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h2 class="text-center mb-0">Barcode Generator</h2>
                    </div>
                    <div class="card-body">
                        <form id="barcodeGeneratorForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="quantity" class="form-label">Quantity</label>
                                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" max="100" value="5" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="prefix" class="form-label">Prefix (Optional)</label>
                                        <input type="text" class="form-control" id="prefix" name="prefix" placeholder="e.g., PROD">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="label" class="form-label">Label Text</label>
                                <input type="text" class="form-control" id="label" name="label" value="Product Barcode" placeholder="Label for barcodes">
                            </div>
                            
                            <div class="mb-3">
                                <label for="format" class="form-label">Export Format</label>
                                <select class="form-select" id="format" name="format" required>
                                    <option value="pdf">PDF Document</option>
                                    <option value="word">Word Document (.docx)</option>
                                    <option value="excel">Excel Spreadsheet (.xlsx)</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-outline-primary" id="previewBtn">Preview</button>
                                <button type="submit" class="btn btn-primary">Generate & Download</button>
                            </div>
                        </form>
                        
                        <div class="loading mt-3">
                            <div class="d-flex justify-content-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                        
                        <div id="previewContainer" class="mt-4" style="display: none;">
                            <h5>Preview:</h5>
                            <div class="preview-container" id="previewContent"></div>
                        </div>
                        
                        <div id="alertContainer" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class BarcodeGenerator {
            constructor() {
                this.form = document.getElementById('barcodeGeneratorForm');
                this.previewBtn = document.getElementById('previewBtn');
                this.loading = document.querySelector('.loading');
                this.previewContainer = document.getElementById('previewContainer');
                this.previewContent = document.getElementById('previewContent');
                this.alertContainer = document.getElementById('alertContainer');
                
                this.init();
            }
            
            init() {
                this.form.addEventListener('submit', (e) => this.handleGenerate(e));
                this.previewBtn.addEventListener('click', () => this.handlePreview());
            }
            
            showLoading(show = true) {
                this.loading.style.display = show ? 'block' : 'none';
            }
            
            showAlert(message, type = 'info') {
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                this.alertContainer.innerHTML = alertHtml;
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    const alert = this.alertContainer.querySelector('.alert');
                    if (alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            }
            
            async handlePreview() {
                const formData = new FormData(this.form);
                formData.append('action', 'preview');
                
                this.showLoading(true);
                
                try {
                    const response = await fetch('barcode_generator.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.displayPreview(result.data);
                        this.showAlert('Preview generated successfully!', 'success');
                    } else {
                        this.showAlert(result.message, 'danger');
                    }
                } catch (error) {
                    console.error('Preview error:', error);
                    this.showAlert('Error generating preview. Please try again.', 'danger');
                } finally {
                    this.showLoading(false);
                }
            }
            
            displayPreview(barcodes) {
                let html = '';
                barcodes.forEach((barcode, index) => {
                    html += `
                        <div class="barcode-preview">
                            <img src="${barcode.image}" alt="Barcode ${barcode.barcode}">
                            <div class="mt-2">
                                <strong>${barcode.barcode}</strong><br>
                                <small>${barcode.label}</small>
                            </div>
                        </div>
                    `;
                });
                
                this.previewContent.innerHTML = html;
                this.previewContainer.style.display = 'block';
            }
            
            async handleGenerate(e) {
                e.preventDefault();
                
                const formData = new FormData(this.form);
                formData.append('action', 'generate');
                
                this.showLoading(true);
                
                try {
                    const response = await fetch('barcode_generator.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.showAlert(`Successfully generated ${result.data.quantity} barcodes in ${result.data.format} format!`, 'success');
                        
                        // Trigger download
                        const downloadLink = document.createElement('a');
                        downloadLink.href = result.data.download_url;
                        downloadLink.download = result.data.filename;
                        document.body.appendChild(downloadLink);
                        downloadLink.click();
                        document.body.removeChild(downloadLink);
                    } else {
                        this.showAlert(result.message, 'danger');
                    }
                } catch (error) {
                    console.error('Generation error:', error);
                    this.showAlert('Error generating barcodes. Please try again.', 'danger');
                } finally {
                    this.showLoading(false);
                }
            }
        }
        
        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            new BarcodeGenerator();
        });
    </script>
</body>
</html>

