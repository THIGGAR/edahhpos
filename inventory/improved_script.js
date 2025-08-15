(function() {
    'use strict';
    
    class InventoryDashboard {
        constructor() {
            this.currentSection = 'dashboard';
            this.currentMode = 'new_product';
            this.products = [];
            this.categories = [];
            this.isLoading = false;
            this.baseUrl = ".";
            this.currentPage = 1;
            this.itemsPerPage = 10;
            this.searchTimeout = null;

            this.init();
        }

        init() {
            console.log('Initializing InventoryDashboard');
            this.setupEventListeners();
            this.setupModeButtons();
            this.loadInitialData();
            this.setupFormValidation();
            this.switchMode('new_product');
        }

        setupEventListeners() {
            console.log('Setting up general event listeners');

            // Sidebar toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleSidebar();
                });
            } else {
                console.warn('Sidebar toggle element not found');
            }

            // Navigation links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const section = link.dataset.section;
                    if (section) {
                        this.showSection(section);
                    }
                });
            });

            // View all products button
            const viewAllProducts = document.getElementById('viewAllProducts');
            if (viewAllProducts) {
                viewAllProducts.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.showSection('inventory');
                });
            }

            // Notification bell
            const notificationBell = document.getElementById('notificationBell');
            if (notificationBell) {
                notificationBell.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.showNotifications();
                });
            }

            // User profile
            const userProfile = document.getElementById('userProfile');
            if (userProfile) {
                userProfile.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.showUserProfile();
                });
            }

            // Form submissions - Fixed to handle different form types properly
            const forms = {
                'newProductForm': 'new_product',
                'existingCategoryForm': 'existing_product',
                'updateStockForm': 'update_stock'
            };
            Object.keys(forms).forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.addEventListener('submit', e => this.handleFormSubmission(e, forms[formId]));
                } else {
                    console.warn(`Form ${formId} not found`);
                }
            });

            // Category selection for stock update
            const stockCategory = document.getElementById('stockCategory');
            if (stockCategory) {
                stockCategory.addEventListener('change', e => this.loadProducts(e.target.value));
            }

            // Barcode input handling
            ['newProductBarcode', 'existingProductBarcode', 'stockBarcode'].forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    input.addEventListener('input', e => {
                        e.target.value = e.target.value.replace(/[^0-9]/g, '');
                        this.handleBarcodeInput(e.target.value, id);
                    });
                } else {
                    console.warn(`Barcode input ${id} not found`);
                }
            });

            // Barcode generator form
            const barcodeGeneratorForm = document.getElementById('barcodeGeneratorForm');
            if (barcodeGeneratorForm) {
                barcodeGeneratorForm.addEventListener('submit', (e) => {
                    this.handleBarcodeGeneration(e);
                });
            }

            // Preview barcodes button
            const previewBarcodesBtn = document.getElementById('previewBarcodesBtn');
            if (previewBarcodesBtn) {
                previewBarcodesBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.previewBarcodes();
                });
            }

            // Reports form
            const reportsForm = document.getElementById('reportsForm');
            if (reportsForm) {
                reportsForm.addEventListener('submit', (e) => {
                    this.handleReportGeneration(e);
                });
            }

            // Report type change
            const reportType = document.getElementById('reportType');
            if (reportType) {
                reportType.addEventListener('change', (e) => {
                    this.handleReportTypeChange(e.target.value);
                });
            }

            // Inventory filters
            const applyFilters = document.getElementById('applyFilters');
            if (applyFilters) {
                applyFilters.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.currentPage = 1;
                    this.applyInventoryFilters();
                });
            }

            const clearFilters = document.getElementById('clearFilters');
            if (clearFilters) {
                clearFilters.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.currentPage = 1;
                    this.clearInventoryFilters();
                });
            }

            const refreshInventory = document.getElementById('refreshInventory');
            if (refreshInventory) {
                refreshInventory.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.currentPage = 1;
                    this.loadInventoryData();
                });
            }

            // Search input with debounce
            const inventorySearch = document.getElementById('inventorySearch');
            if (inventorySearch) {
                inventorySearch.addEventListener('input', (e) => {
                    this.debounceSearch(e.target.value);
                });
            }

            // Clear search button
            const clearSearch = document.getElementById('clearSearch');
            if (clearSearch) {
                clearSearch.addEventListener('click', (e) => {
                    e.preventDefault();
                    const searchInput = document.getElementById('inventorySearch');
                    if (searchInput) {
                        searchInput.value = '';
                        this.debounceSearch('');
                    }
                });
            }

            // Pagination controls
            const prevPage = document.getElementById('prevPage');
            const nextPage = document.getElementById('nextPage');
            if (prevPage) {
                prevPage.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (this.currentPage > 1) {
                        this.currentPage--;
                        this.applyInventoryFilters();
                    }
                });
            }
            if (nextPage) {
                nextPage.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.currentPage++;
                    this.applyInventoryFilters();
                });
            }

            // Global search
            const globalSearch = document.getElementById('globalSearch');
            if (globalSearch) {
                globalSearch.addEventListener('input', (e) => {
                    this.handleGlobalSearch(e.target.value);
                });
            }

            // Handle keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                this.handleKeyboardShortcuts(e);
            });
        }

        setupModeButtons() {
            console.log('Setting up mode buttons');
            document.querySelectorAll('.mode-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const mode = btn.dataset.mode;
                    if (mode) {
                        this.switchMode(mode);
                    }
                });
            });
        }

        switchMode(mode) {
            console.log(`Switching to mode: ${mode}`);
            this.currentMode = mode;
            
            document.querySelectorAll('.mode-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.mode === mode);
            });
            
            document.querySelectorAll('.form-section').forEach(form => {
                form.classList.toggle('active', form.dataset.mode === mode);
            });
            
            if (mode === 'update_stock' || mode === 'existing_category') {
                this.loadCategories();
            }
        }

        showSection(section) {
            console.log(`Showing section: ${section}`);
            this.currentSection = section;
            
            // Update navigation active state
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.toggle('active', link.dataset.section === section);
            });
            
            // Update content sections
            document.querySelectorAll('.content-section').forEach(sec => {
                sec.classList.toggle('active', sec.id === `${section}-section`);
            });
            
            // Update page title
            const pageTitle = document.querySelector('.page-title');
            if (pageTitle) {
                pageTitle.textContent = this.getSectionTitle(section);
            }
            
            // Load section-specific data
            switch (section) {
                case 'inventory':
                    this.loadInventoryData();
                    break;
                case 'barcode-generator':
                    this.loadBarcodeGenerator();
                    break;
                case 'reports':
                    this.loadReports();
                    break;
                case 'register-goods':
                    this.loadCategories();
                    break;
            }
        }

        getSectionTitle(section) {
            const titles = {
                'dashboard': 'Dashboard',
                'register-goods': 'Register Goods',
                'inventory': 'Inventory Management',
                'barcode-generator': 'Barcode Generator',
                'reports': 'Reports',
                'settings': 'Settings'
            };
            return titles[section] || 'Dashboard';
        }

        async loadInitialData() {
            console.log('Loading initial data');
            try {
                await this.loadCategories();
                await this.loadDashboardStats();
            } catch (error) {
                console.error('Error loading initial data:', error);
                this.showToast('Error loading initial data. Please check your server connection.', 'error');
            }
        }

        async loadCategories() {
            try {
                const response = await fetch(`api_handler.php?action=get_categories`, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                
                if (result.success) {
                    this.categories = result.data;
                    this.populateCategorySelects();
                } else {
                    console.error('Failed to load categories:', result.message);
                    this.showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Error loading categories:', error);
                this.showToast('Failed to load categories. Please check your server.', 'error');
            }
        }

        populateCategorySelects() {
            const selects = ['stockCategory', 'categoryFilter', 'existingCategory'];
            selects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    select.innerHTML = selectId === 'categoryFilter' 
                        ? '<option value="">All Categories</option>'
                        : '<option value="" disabled selected>Select a category</option>';
                    this.categories.forEach(category => {
                        const option = document.createElement('option');
                        option.value = category.name;
                        option.textContent = category.name;
                        select.appendChild(option);
                    });
                }
            });
        }

        async loadProducts(category = '') {
            try {
                const url = category 
                    ? `api_handler.php?action=get_products_by_category&category=${encodeURIComponent(category)}`
                    : `api_handler.php?action=get_products`;
                const response = await fetch(url, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                
                if (result.success) {
                    this.products = result.data;
                    this.populateProductSelects();
                } else {
                    console.error('Failed to load products:', result.message);
                    this.showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Error loading products:', error);
                this.showToast('Failed to load products. Please check your server.', 'error');
            }
        }

        populateProductSelects() {
            const select = document.getElementById('stockProduct');
            if (select) {
                select.innerHTML = '<option value="" disabled selected>Select a product</option>';
                this.products.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.product_id;
                    option.textContent = `${product.name} (${product.barcodes})`;
                    select.appendChild(option);
                });
            }
        }

        setupFormValidation() {
            console.log('Setting up form validation');
            const forms = ['newProductForm', 'existingCategoryForm', 'updateStockForm'];
            forms.forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.querySelectorAll('input[required], select[required]').forEach(input => {
                        input.addEventListener('input', () => this.validateField(input));
                        input.addEventListener('blur', () => this.validateField(input));
                    });
                    form.addEventListener('submit', (e) => {
                        if (!this.validateForm(form)) {
                            e.preventDefault();
                            this.showToast('Please fill in all required fields correctly.', 'error');
                        }
                    });
                }
            });
        }

        validateField(input) {
            const errorElement = document.getElementById(`${input.id}Error`);
            if (!errorElement) return true;

            if (!input.value.trim()) {
                errorElement.textContent = 'This field is required';
                errorElement.classList.add('show');
                return false;
            }

            if (input.type === 'number' && input.name === 'price' && input.value <= 0) {
                errorElement.textContent = 'Price must be greater than 0';
                errorElement.classList.add('show');
                return false;
            }

            if (input.type === 'number' && input.name === 'quantity' && input.value < 1) {
                errorElement.textContent = 'Quantity must be at least 1';
                errorElement.classList.add('show');
                return false;
            }

            if (input.name === 'barcode' && !/^\d{8,}$/.test(input.value)) {
                errorElement.textContent = 'Barcode must be at least 8 digits';
                errorElement.classList.add('show');
                return false;
            }

            errorElement.classList.remove('show');
            return true;
        }

        validateForm(form) {
            let isValid = true;
            form.querySelectorAll('input[required], select[required]').forEach(input => {
                if (!this.validateField(input)) {
                    isValid = false;
                }
            });
            return isValid;
        }

        // FIXED: Corrected form submission handling with proper checkbox handling
        async handleFormSubmission(e, formType) {
            e.preventDefault();
            const form = e.target;
            if (!this.validateForm(form)) return;

            const formData = new FormData(form);
            
            // FIXED: Explicitly handle checkboxes for promotion and customer visibility
            const isPromotionChecked = form.querySelector('input[name="is_promotion"]')?.checked || false;
            const isCustomerVisibleChecked = form.querySelector('input[name="customer_visible"]')?.checked || false;
            
            // Set explicit values for checkboxes
            formData.set('is_promotion', isPromotionChecked ? '1' : '0');
            formData.set('customer_visible', isCustomerVisibleChecked ? '1' : '0');
            
            this.setLoading(true);
            
            try {
                let endpoint = '';
                
                // Set the correct registration mode based on form type
                switch (formType) {
                    case 'new_product':
                        endpoint = `register_goods.php`;
                        formData.set('registration_mode', 'new_product');
                        break;
                    case 'existing_product':
                        endpoint = `register_goods.php`;
                        formData.set('registration_mode', 'new_product'); // Still uses new_product mode
                        break;
                    case 'update_stock':
                        endpoint = `register_goods.php`;
                        formData.set('registration_mode', 'update_stock');
                        break;
                    default:
                        throw new Error('Unknown form type');
                }

                console.log('Submitting form:', formType, 'to endpoint:', endpoint);
                console.log('Form data entries:');
                for (let [key, value] of formData.entries()) {
                    console.log(key, ':', value);
                }

                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('Server response:', result);
                
                if (result.success) {
                    this.showToast(result.message, 'success');
                    form.reset();
                    this.loadDashboardStats();
                    this.loadCategories();
                    if (this.currentSection === 'inventory') {
                        this.loadInventoryData();
                    }
                } else {
                    this.showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Form submission error:', error);
                this.showToast('Error submitting form. Please check your server.', 'error');
            } finally {
                this.setLoading(false);
            }
        }

        async handleBarcodeInput(barcode, inputId) {
            if (barcode.length >= 8) {
                try {
                    const response = await fetch(`check_barcode.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `barcode=${encodeURIComponent(barcode)}`
                    });
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const result = await response.json();
                    
                    const errorElement = document.getElementById(`${inputId}Error`);
                    if (errorElement) {
                        if (result.exists) {
                            errorElement.textContent = 'Barcode already exists!';
                            errorElement.classList.add('show');
                            errorElement.style.color = 'var(--danger-color)';
                        } else {
                            errorElement.textContent = 'Barcode available';
                            errorElement.classList.add('show');
                            errorElement.style.color = 'var(--success-color)';
                        }
                    }
                } catch (error) {
                    console.error('Error checking barcode:', error);
                    this.showToast('Error checking barcode. Please check your server.', 'error');
                }
            }
        }

        async handleBarcodeGeneration(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'generate');

            this.setLoading(true);
            try {
                const response = await fetch(`barcode_generator.php`, {
                    method: 'POST',
                    body: formData
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                
                if (result.success) {
                    this.showToast(result.message, 'success');
                    this.displayBarcodeResult(result.data);
                } else {
                    this.showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Barcode generation error:', error);
                this.showToast('Error during barcode generation. Please check your server.', 'error');
            } finally {
                this.setLoading(false);
            }
        }

        displayBarcodeResult(data) {
            const previewContainer = document.getElementById('barcodePreviewContainer');
            if (!previewContainer) return;

            previewContainer.innerHTML = `
                <div class="barcode-result">
                    <h4>Barcodes Generated Successfully!</h4>
                    <div class="result-info">
                        <p><strong>Quantity:</strong> ${data.quantity}</p>
                        <p><strong>Format:</strong> ${data.format ? data.format.toUpperCase() : 'PDF'}</p>
                        <p><strong>File Size:</strong> ${data.file_size || 'N/A'}</p>
                        <p><strong>Filename:</strong> ${data.filename || 'barcodes.pdf'}</p>
                    </div>
                    <div class="download-section">
                        <a href="${data.download_url}" class="btn btn-primary" download>
                            <i class="fas fa-download"></i> Download ${data.format ? data.format.toUpperCase() : 'PDF'} File
                        </a>
                    </div>
                </div>
            `;
            previewContainer.style.display = 'block';
        }

        async previewBarcodes() {
            const form = document.getElementById('barcodeGeneratorForm');
            if (!form) return;

            const formData = new FormData(form);
            formData.append('action', 'preview');

            this.setLoading(true);
            try {
                const response = await fetch(`barcode_generator.php`, {
                    method: 'POST',
                    body: formData
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                
                if (result.success) {
                    this.displayBarcodePreview(result.data.previews || []);
                } else {
                    this.showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Barcode preview error:', error);
                this.showToast('Error during barcode preview. Please check your server.', 'error');
            } finally {
                this.setLoading(false);
            }
        }

        displayBarcodePreview(previews) {
            const previewContainer = document.getElementById('barcodePreviewContainer');
            if (!previewContainer) return;

            if (!previews || previews.length === 0) {
                previewContainer.innerHTML = '<p>No preview available</p>';
                previewContainer.style.display = 'block';
                return;
            }

            previewContainer.innerHTML = previews.map(preview => `
                <div class="barcode-preview-item">
                    <div class="barcode-label">${this.escapeHtml(preview.label || '')}</div>
                    <div class="barcode-image">${preview.image || ''}</div>
                    <div class="barcode-text">${this.escapeHtml(preview.barcode || '')}</div>
                </div>
            `).join('');
            
            previewContainer.style.display = 'block';
            this.showToast('Preview generated successfully', 'success');
        }

        loadBarcodeGenerator() {
            const previewContainer = document.getElementById('barcodePreviewContainer');
            if (previewContainer) previewContainer.style.display = 'none';
        }

        async handleReportGeneration(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);

            this.setLoading(true);
            try {
                const response = await fetch(`generate_reports.php`, {
                    method: 'POST',
                    body: formData
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                
                if (result.success) {
                    this.showToast(result.message, 'success');
                    if (result.data && result.data.report_url) {
                        window.open(`${result.data.report_url}`, '_blank');
                    }
                } else {
                    this.showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Report generation error:', error);
                this.showToast('Error during report generation. Please check your server.', 'error');
            } finally {
                this.setLoading(false);
            }
        }

        handleReportTypeChange(reportType) {
            const customDateRange = document.getElementById('customDateRange');
            if (customDateRange) {
                customDateRange.style.display = reportType === 'custom' ? 'flex' : 'none';
            }
        }

        loadReports() {
            console.log('Loading reports section');
            const reportType = document.getElementById('reportType');
            if (reportType) {
                this.handleReportTypeChange(reportType.value);
            }
        }

        // FIXED: Enhanced editProduct function with better error handling and proper endpoint
        async editProduct(productId) {
            console.log('Edit product called with ID:', productId);
            
            if (!productId) {
                this.showToast('Invalid product ID', 'error');
                return;
            }

            try {
                this.setLoading(true);
                
                // FIXED: Use the correct endpoint for getting product details
                const response = await fetch(`fixed_get_product_details.php?id=${productId}`, {
                    method: 'GET',
                    headers: { 
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache'
                    }
                });
                
                console.log('Product details response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('Product details result:', result);
                
                if (result.success && result.data) {
                    this.showEditModal(result.data);
                } else {
                    this.showToast(result.message || 'Failed to load product details', 'error');
                }
            } catch (error) {
                console.error('Error loading product for edit:', error);
                this.showToast('Error loading product details. Please check your server.', 'error');
            } finally {
                this.setLoading(false);
            }
        }

        showEditModal(product) {
            console.log('Showing edit modal for product:', product);
            
            // Remove any existing modal first
            const existingModal = document.getElementById('editProductModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Create modal HTML with proper promotion handling
            const modalHTML = `
                <div id="editProductModal" class="modal-overlay">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Product</h3>
                            <button type="button" class="modal-close" onclick="this.closest('.modal-overlay').remove()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form id="editProductForm" class="modal-body">
                            <input type="hidden" name="product_id" value="${product.product_id}">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="editProductName">Product Name</label>
                                    <input type="text" id="editProductName" name="name" value="${this.escapeHtml(product.name)}" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="editProductCategory">Category</label>
                                    <select id="editProductCategory" name="category" required>
                                        <option value="">Select a category</option>
                                        ${this.categories.map(cat => 
                                            `<option value="${this.escapeHtml(cat.name)}" ${cat.name === product.category ? 'selected' : ''}>${this.escapeHtml(cat.name)}</option>`
                                        ).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="editProductPrice">Price</label>
                                    <input type="number" id="editProductPrice" name="price" value="${product.price}" step="0.01" min="0" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="editProductBarcode">Barcode</label>
                                    <input type="text" id="editProductBarcode" name="barcode" value="${this.escapeHtml(product.barcodes)}" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="editStockAdjustment">Stock Adjustment</label>
                                    <input type="number" id="editStockAdjustment" name="stock_adjustment" value="0" placeholder="Enter +/- amount">
                                    <small>Current stock: ${product.stock_quantity}</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="editExpiryDate">Expiry Date</label>
                                    <input type="date" id="editExpiryDate" name="expiry_date" value="${product.expiry_date || ''}">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_promotion" id="editIsPromotion" ${product.is_promotion ? 'checked' : ''}> 
                                    <span class="checkmark"></span>
                                    Enable Promotion
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="customer_visible" id="editCustomerVisible" ${product.show_on_customer_dashboard ? 'checked' : ''}> 
                                    <span class="checkmark"></span>
                                    Show on Customer Dashboard
                                </label>
                            </div>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Product</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Add form submission handler
            const editForm = document.getElementById('editProductForm');
            if (editForm) {
                editForm.addEventListener('submit', (e) => this.handleEditSubmission(e));
            }
        }

        // FIXED: Enhanced edit submission with proper checkbox handling and FormData
        async handleEditSubmission(e) {
            e.preventDefault();
            const form = e.target;
            
            console.log('Edit form submission started');
            
            // Use FormData for edit submission to match PHP expectations
            const formData = new FormData(form);
            
            // FIXED: Explicitly handle checkboxes for promotion and customer visibility
            const isPromotionChecked = form.querySelector('input[name="is_promotion"]')?.checked || false;
            const isCustomerVisibleChecked = form.querySelector('input[name="customer_visible"]')?.checked || false;
            
            // Set explicit values for checkboxes
            formData.set('is_promotion', isPromotionChecked ? '1' : '0');
            formData.set('customer_visible', isCustomerVisibleChecked ? '1' : '0');
            
            console.log('Edit form submission data:');
            for (let [key, value] of formData.entries()) {
                console.log(key, ':', value);
            }
            
            this.setLoading(true);
            try {
                const response = await fetch(`edit_product.php`, {
                    method: 'POST',
                    body: formData // Send as FormData, not JSON
                });
                
                console.log('Edit response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('Edit response:', result);
                
                if (result.success) {
                    this.showToast(result.message, 'success');
                    // Close modal
                    const modal = document.getElementById('editProductModal');
                    if (modal) modal.remove();
                    // Refresh inventory
                    this.loadInventoryData();
                } else {
                    this.showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Error updating product:', error);
                this.showToast('Error updating product. Please check your server.', 'error');
            } finally {
                this.setLoading(false);
            }
        }

        // FIXED: Enhanced deleteProduct function with better error handling
        async deleteProduct(productId) {
            console.log('Delete product called with ID:', productId);
            
            if (!productId) {
                this.showToast('Invalid product ID', 'error');
                return;
            }

            if (!confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                return;
            }

            try {
                this.setLoading(true);
                
                // Send POST request with JSON body as expected by delete_product.php
                const response = await fetch(`delete_product.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        product_id: parseInt(productId)
                    })
                });
                
                console.log('Delete response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('Delete response:', result);
                
                if (result.success) {
                    this.showToast(result.message, 'success');
                    this.loadInventoryData();
                } else {
                    this.showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Error deleting product:', error);
                this.showToast('Error deleting product. Please check your server.', 'error');
            } finally {
                this.setLoading(false);
            }
        }

        // FIXED: Enhanced toggleVisibility function with better error handling and proper endpoint
        async toggleVisibility(productId, newVisibility) {
            console.log('Toggle visibility called with ID:', productId, 'new visibility:', newVisibility);
            
            if (!productId) {
                this.showToast('Invalid product ID', 'error');
                return;
            }

            try {
                this.setLoading(true);
                
                // FIXED: Use the correct endpoint and proper form data
                const response = await fetch(`fixed_toggle_visibility.php`, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: `product_id=${encodeURIComponent(productId)}&visibility=${encodeURIComponent(newVisibility)}`
                });
                
                console.log('Toggle visibility response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('Toggle visibility response:', result);
                
                if (result.success) {
                    this.showToast(result.message, 'success');
                    this.loadInventoryData();
                } else {
                    this.showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Error toggling visibility:', error);
                this.showToast('Error updating visibility. Please check your server.', 'error');
            } finally {
                this.setLoading(false);
            }
        }

        async applyInventoryFilters() {
            const category = document.getElementById('categoryFilter')?.value || '';
            const visibility = document.getElementById('visibilityFilter')?.value || '';
            const promotion = document.getElementById('promotionFilter')?.value || '';
            const search = document.getElementById('inventorySearch')?.value || '';
            
            await this.loadInventoryData({
                category,
                visibility,
                promotion,
                search
            });
        }

        clearInventoryFilters() {
            const filters = ['categoryFilter', 'visibilityFilter', 'promotionFilter', 'inventorySearch'];
            filters.forEach(filterId => {
                const element = document.getElementById(filterId);
                if (element) element.value = '';
            });
            this.currentPage = 1;
            this.loadInventoryData();
        }

        debounceSearch(searchTerm) {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.currentPage = 1;
                this.applyInventoryFilters();
            }, 500);
        }

        async loadInventoryData(filters = {}) {
            try {
                this.setInventoryLoading(true);
                
                const formData = new FormData();
                Object.keys(filters).forEach(key => {
                    formData.append(key, filters[key] || '');
                });
                formData.append('page', this.currentPage);
                formData.append('limit', this.itemsPerPage);
                
                const response = await fetch(`api_handler.php?action=get_products`, {
                    method: 'POST',
                    body: formData
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    this.displayInventoryData(result.data.products || []);
                    this.updateInventoryStats(result.data.total || 0);
                    this.updatePagination(result.data.total || 0);
                } else {
                    this.showToast(result.message, 'error');
                    this.displayInventoryData([]);
                }
            } catch (error) {
                console.error('Error loading inventory:', error);
                this.showToast('Error loading inventory. Please check your server.', 'error');
                this.displayInventoryData([]);
            } finally {
                this.setInventoryLoading(false);
            }
        }

        displayInventoryData(products) {
            const tbody = document.getElementById('inventoryTableBody');
            const noResults = document.getElementById('noResultsMessage');
            
            if (!tbody) return;
            
            if (products.length === 0) {
                tbody.innerHTML = '';
                if (noResults) noResults.style.display = 'block';
                return;
            }
            
            if (noResults) noResults.style.display = 'none';
            
            tbody.innerHTML = products.map(product => `
                <tr data-product-id="${product.product_id}">
                    <td class="image-cell">
                        <img src="${this.escapeHtml(product.image || `uploads/placeholder.jpg`)}" 
                             alt="Product Image" 
                             class="product-image"
                             onerror="this.src='uploads/placeholder.jpg'">
                    </td>
                    <td class="product-name">
                        <div class="name-container">
                            <span class="name">${this.escapeHtml(product.name)}</span>
                            ${product.description ? `<small class="description">${this.escapeHtml(product.description)}</small>` : ''}
                        </div>
                    </td>
                    <td>
                        <span class="category-badge">${this.escapeHtml(product.category || 'N/A')}</span>
                    </td>
                    <td>
                        <code class="barcode">${this.escapeHtml(product.barcodes)}</code>
                    </td>
                    <td class="price-cell">
                        <span class="price">$${parseFloat(product.price || 0).toFixed(2)}</span>
                    </td>
                    <td class="stock-cell">
                        <span class="stock-quantity ${(product.stock_quantity || 0) <= 10 ? 'low-stock' : ''}">${product.stock_quantity || 0}</span>
                    </td>
                    <td class="promotion-cell">
                        <span class="status-badge ${product.is_promotion ? 'promotion-active' : 'promotion-inactive'}">
                            <i class="fas ${product.is_promotion ? 'fa-tag' : 'fa-times'}"></i>
                            ${product.is_promotion ? 'Active' : 'None'}
                        </span>
                    </td>
                    <td class="visibility-cell">
                        <span class="status-badge ${product.show_on_customer_dashboard ? 'visible' : 'hidden'}">
                            <i class="fas ${product.show_on_customer_dashboard ? 'fa-eye' : 'fa-eye-slash'}"></i>
                            ${product.show_on_customer_dashboard ? 'Visible' : 'Hidden'}
                        </span>
                    </td>
                    <td class="actions-cell">
                        <div class="action-buttons">
                            <button class="action-btn edit" onclick="window.dashboard.editProduct(${product.product_id})" title="Edit Product">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn toggle" onclick="window.dashboard.toggleVisibility(${product.product_id}, ${product.show_on_customer_dashboard ? 0 : 1})" title="Toggle Visibility">
                                <i class="fas ${product.show_on_customer_dashboard ? 'fa-eye-slash' : 'fa-eye'}"></i>
                            </button>
                            <button class="action-btn delete" onclick="window.dashboard.deleteProduct(${product.product_id})" title="Delete Product">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        updateInventoryStats(total) {
            const totalCount = document.getElementById('totalProductsCount');
            const filteredCount = document.getElementById('filteredProductsCount');
            
            if (totalCount) totalCount.textContent = total;
            if (filteredCount) filteredCount.textContent = total;
        }

        updatePagination(total) {
            const pagination = document.getElementById('inventoryPagination');
            const paginationStart = document.getElementById('paginationStart');
            const paginationEnd = document.getElementById('paginationEnd');
            const paginationTotal = document.getElementById('paginationTotal');
            const prevPage = document.getElementById('prevPage');
            const nextPage = document.getElementById('nextPage');
            const pageNumbers = document.getElementById('pageNumbers');
            
            if (!pagination) return;
            
            const totalPages = Math.ceil(total / this.itemsPerPage);
            const start = (this.currentPage - 1) * this.itemsPerPage + 1;
            const end = Math.min(this.currentPage * this.itemsPerPage, total);
            
            if (paginationStart) paginationStart.textContent = start;
            if (paginationEnd) paginationEnd.textContent = end;
            if (paginationTotal) paginationTotal.textContent = total;
            
            if (prevPage) {
                prevPage.disabled = this.currentPage <= 1;
            }
            if (nextPage) {
                nextPage.disabled = this.currentPage >= totalPages;
            }
            
            if (pageNumbers) {
                pageNumbers.innerHTML = '';
                for (let i = Math.max(1, this.currentPage - 2); i <= Math.min(totalPages, this.currentPage + 2); i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = `page-number ${i === this.currentPage ? 'active' : ''}`;
                    pageBtn.textContent = i;
                    pageBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.currentPage = i;
                        this.applyInventoryFilters();
                    });
                    pageNumbers.appendChild(pageBtn);
                }
            }
            
            pagination.style.display = total > 0 ? 'flex' : 'none';
        }

        setInventoryLoading(isLoading) {
            const indicator = document.getElementById('inventoryLoadingIndicator');
            if (indicator) {
                indicator.style.display = isLoading ? 'flex' : 'none';
            }
        }

        sortInventoryTable(column) {
            this.showToast(`Sorting by ${column} not implemented yet`, 'info');
            // Add sorting logic if needed
        }

        loadDashboardStats() {
            console.log('Dashboard stats loaded from server');
            // Stats are loaded server-side in PHP, but could fetch dynamically if needed
        }

        setLoading(isLoading) {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = isLoading ? 'flex' : 'none';
            }
        }

        showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer') || this.createToastContainer();
            
            const toast = document.createElement('div');
            toast.classList.add('toast', type);
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${this.getToastIcon(type)}"></i>
                    <span>${this.escapeHtml(message)}</span>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            toastContainer.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 300);
            }, 5000);
        }

        createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
            return container;
        }

        getToastIcon(type) {
            const icons = {
                'success': 'fa-check-circle',
                'error': 'fa-exclamation-circle',
                'warning': 'fa-exclamation-triangle',
                'info': 'fa-info-circle'
            };
            return icons[type] || icons.info;
        }

        showNotifications() {
            this.showToast('Notifications feature coming soon', 'info');
        }

        showUserProfile() {
            this.showToast('User profile feature coming soon', 'info');
        }

        toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebar && mainContent) {
                sidebar.classList.toggle('collapsed');
                if (sidebar.classList.contains('collapsed')) {
                    mainContent.classList.add('expanded');
                } else {
                    mainContent.classList.remove('expanded');
                }
            }
        }

        handleGlobalSearch(searchTerm) {
            if (this.currentSection === 'inventory') {
                const inventorySearch = document.getElementById('inventorySearch');
                if (inventorySearch) {
                    inventorySearch.value = searchTerm;
                    this.debounceSearch(searchTerm);
                }
            } else {
                this.showToast(`Global search for "${searchTerm}" - feature coming soon`, 'info');
            }
        }

        handleKeyboardShortcuts(e) {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.getElementById('globalSearch') || document.getElementById('inventorySearch');
                if (searchInput) {
                    searchInput.focus();
                }
            }
            
            // Escape to close modals/overlays
            if (e.key === 'Escape') {
                const loadingOverlay = document.getElementById('loadingOverlay');
                if (loadingOverlay && loadingOverlay.style.display === 'flex') {
                    this.setLoading(false);
                }
                
                // Close edit modal
                const editModal = document.getElementById('editProductModal');
                if (editModal) {
                    editModal.remove();
                }
            }
        }

        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.toString().replace(/[&<>"']/g, m => map[m]) : '';
        }
    }

    // Initialize dashboard when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        console.log('DOM fully loaded, creating InventoryDashboard');
        window.dashboard = new InventoryDashboard();
    });

    // Handle page visibility changes
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && window.dashboard) {
            // Refresh data when page becomes visible
            if (window.dashboard.currentSection === 'dashboard') {
                window.dashboard.loadDashboardStats();
            } else if (window.dashboard.currentSection === 'inventory') {
                window.dashboard.loadInventoryData();
            }
        }
    });

})();

// Add modal styles to the page
const modalStyles = `
<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    backdrop-filter: blur(4px);
}

.modal-content {
    background: var(--white, #ffffff);
    border-radius: var(--border-radius-2xl, 16px);
    box-shadow: var(--shadow-2xl, 0 25px 50px -12px rgba(0, 0, 0, 0.25));
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--spacing-6, 24px);
    border-bottom: 1px solid var(--gray-200, #e5e7eb);
}

.modal-header h3 {
    margin: 0;
    font-size: var(--font-size-xl, 20px);
    font-weight: 600;
    color: var(--text-color, #1f2937);
}

.modal-close {
    background: none;
    border: none;
    font-size: var(--font-size-lg, 18px);
    color: var(--gray-500, #6b7280);
    cursor: pointer;
    padding: var(--spacing-2, 8px);
    border-radius: var(--border-radius, 6px);
    transition: var(--transition-base, all 0.15s ease);
}

.modal-close:hover {
    background: var(--gray-100, #f3f4f6);
    color: var(--gray-700, #374151);
}

.modal-body {
    padding: var(--spacing-6, 24px);
}

.modal-footer {
    display: flex;
    gap: var(--spacing-3, 12px);
    justify-content: flex-end;
    padding-top: var(--spacing-4, 16px);
    border-top: 1px solid var(--gray-200, #e5e7eb);
    margin-top: var(--spacing-6, 24px);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-4, 16px);
    margin-bottom: var(--spacing-4, 16px);
}

.form-group {
    margin-bottom: var(--spacing-4, 16px);
}

.form-group label {
    display: block;
    margin-bottom: var(--spacing-2, 8px);
    font-weight: 500;
    color: var(--text-color, #1f2937);
}

.form-group input,
.form-group select {
    width: 100%;
    padding: var(--spacing-3, 12px);
    border: 1px solid var(--gray-300, #d1d5db);
    border-radius: var(--border-radius, 6px);
    font-size: var(--font-size-sm, 14px);
    transition: var(--transition-base, all 0.15s ease);
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary-color, #3b82f6);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: var(--spacing-2, 8px);
    cursor: pointer;
    font-weight: 500;
}

.checkbox-label input[type="checkbox"] {
    margin: 0;
    width: 18px;
    height: 18px;
    accent-color: var(--primary-color, #3b82f6);
}

.checkmark {
    font-size: var(--font-size-sm, 14px);
    color: var(--text-color, #1f2937);
}

.btn {
    padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
    border: none;
    border-radius: var(--border-radius, 6px);
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition-base, all 0.15s ease);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-2, 8px);
}

.btn-primary {
    background: var(--primary-color, #3b82f6);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark, #2563eb);
}

.btn-secondary {
    background: var(--gray-200, #e5e7eb);
    color: var(--gray-700, #374151);
}

.btn-secondary:hover {
    background: var(--gray-300, #d1d5db);
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 20px;
    }
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', modalStyles);

