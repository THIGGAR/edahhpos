
class EnhancedShopManagerDashboard {
    constructor() {
        this.currentSection = 'dashboard';
        this.isLoading = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupMobileMenu();
        this.setupSearch();
        this.setupKeyboardNavigation();
        this.setupAutoRefresh();
        this.loadInitialData();
        
        const initialHash = window.location.hash.substring(1);
        if (initialHash && document.getElementById(initialHash)) {
            this.showSection(initialHash);
        } else {
            this.showSection('dashboard');
        }
    }

    setupEventListeners() {
        document.querySelectorAll('.sidebar-nav a, .nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                if (href && href.startsWith('#') || link.dataset.section) {
                    e.preventDefault();
                    const section = link.dataset.section || href.replace('#', '');
                    this.showSection(section);
                }
            });
        });

        document.getElementById('refreshDashboard')?.addEventListener('click', () => {
            this.refreshDashboard();
        });

        document.getElementById('addUserBtn')?.addEventListener('click', (e) => {
            e.target.disabled = true;
            this.openAddUserModal();
            setTimeout(() => { e.target.disabled = false; }, 1000);
        });

        document.getElementById('newQuotationBtn')?.addEventListener('click', (e) => {
            e.target.disabled = true;
            this.resetQuotationForm();
            setTimeout(() => { e.target.disabled = false; }, 1000);
        });

        document.getElementById('addQuotationItem')?.addEventListener('click', (e) => {
            e.target.disabled = true;
            this.addQuotationItem();
            setTimeout(() => { e.target.disabled = false; }, 1000);
        });

        document.getElementById('resetQuotationForm')?.addEventListener('click', (e) => {
            e.target.disabled = true;
            this.resetQuotationForm();
            setTimeout(() => { e.target.disabled = false; }, 1000);
        });

        document.getElementById('addUserForm')?.addEventListener('submit', (e) => {
            this.handleAddUserSubmission(e);
        });

        document.getElementById('editUserForm')?.addEventListener('submit', (e) => {
            this.handleEditUserSubmission(e);
        });

        document.getElementById('quotationForm')?.addEventListener('submit', (e) => {
            this.handleQuotationSubmission(e);
        });

        document.getElementById('generateSalesReportBtn')?.addEventListener('click', (e) => {
            e.target.disabled = true;
            this.generateSalesReport();
            setTimeout(() => { e.target.disabled = false; }, 1000);
        });

        document.getElementById('generateInventoryReportBtn')?.addEventListener('click', (e) => {
            e.target.disabled = true;
            this.generateInventoryReport();
            setTimeout(() => { e.target.disabled = false; }, 1000);
        });

        document.getElementById('profileSettingsForm')?.addEventListener('submit', (e) => {
            this.handleProfileSettingsSubmission(e);
        });

        document.getElementById('notificationSettingsForm')?.addEventListener('submit', (e) => {
            this.handleNotificationSettingsSubmission(e);
        });

        window.addEventListener('resize', () => {
            this.handleResize();
        });

        window.addEventListener('hashchange', () => {
            const section = window.location.hash.substring(1);
            if (section && document.getElementById(section)) {
                this.showSection(section);
            }
        });

        this.setupQuotationItemListeners();
    }

    setupQuotationItemListeners() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('.remove-item')) {
                e.preventDefault();
                const button = e.target.closest('.remove-item');
                button.disabled = true;
                this.removeQuotationItem(e.target.closest('.quotation-item'));
                setTimeout(() => { button.disabled = false; }, 1000);
            }
        });
    }

    setupMobileMenu() {
        const mobileToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        if (mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });

            overlay?.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    mobileToggle.focus();
                }
            });
        }
    }

    setupSearch() {
        const userSearch = document.getElementById('userSearch');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');

        if (userSearch) {
            userSearch.addEventListener('input', this.debounce(() => {
                this.filterUsers();
            }, 300));
        }

        if (roleFilter) {
            roleFilter.addEventListener('change', () => {
                this.filterUsers();
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                this.filterUsers();
            });
        }
    }

    setupKeyboardNavigation() {
        document.querySelectorAll('.sidebar-nav a, .nav-link').forEach((link, index) => {
            link.setAttribute('tabindex', '0');
            link.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    link.click();
                }
            });
        });
    }

    setupAutoRefresh() {
        setInterval(() => {
            if (this.currentSection === 'dashboard') {
                this.refreshDashboard(true);
            }
        }, 300000);
    }

    openAddUserModal() {
        const modal = document.getElementById('addUserModal');
        if (modal) {
            modal.style.display = 'block';
            document.getElementById('addUserForm').reset();
            this.showToast('Add new user form opened', 'info');
        } else {
            this.showToast('Error opening add user form', 'error');
        }
    }

    async filterUsers() {
        const search = document.getElementById('userSearch')?.value || '';
        const role = document.getElementById('roleFilter')?.value || '';
        const status = document.getElementById('statusFilter')?.value || '';
        
        try {
            this.showLoading(true);
            const response = await fetch(`get_users.php?search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}&status=${encodeURIComponent(status)}`, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await response.json();
            if (response.ok && data.success) {
                const tbody = document.getElementById('usersTable');
                if (tbody) {
                    tbody.innerHTML = data.users.map(user => `
                        <tr data-user-id="${user.user_id}">
                            <td>${this.escapeHTML(user.first_name + ' ' + user.last_name)}</td>
                            <td>${this.escapeHTML(user.email)}</td>
                            <td><span class="role-badge badge-${user.role.replace('_manager', '')}">${this.capitalize(user.role.replace('_', ' '))}</span></td>
                            <td>${user.last_active ? this.formatDate(user.last_active) : 'Never'}</td>
                            <td>
                                <span class="status-badge ${user.is_active ? 'badge-active' : 'badge-inactive'}">
                                    ${user.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-edit" onclick="openEditUserModal(${user.user_id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                ${user.role !== 'shop_manager' ? `
                                    <form method="POST" action="archive_user.php" class="archive-form" style="display:inline;">
                                        <input type="hidden" name="user_id" value="${user.user_id}">
                                        <input type="hidden" name="action" value="${user.is_active ? 'archive' : 'activate'}">
                                        <button type="submit" class="btn btn-sm ${user.is_active ? 'btn-warning' : 'btn-success'}">
                                            ${user.is_active ? '<i class="fas fa-archive"></i> Archive' : '<i class="fas fa-check-circle"></i> Activate'}
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-danger btn-delete" onclick="window.dashboard.deleteUser(${user.user_id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                ` : ''}
                            </td>
                        </tr>
                    `).join('');
                    this.showToast('Users list updated', 'success');
                }
            } else {
                throw new Error(data.message || 'Failed to load users');
            }
        } catch (error) {
            console.error('Error filtering users:', error.message);
            this.showToast('Error loading users', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    async handleAddUserSubmission(e) {
        e.preventDefault();
        if (this.isLoading) return;

        const formData = new FormData(e.target);
        const submitButton = e.target.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            this.showLoading(true);
            this.showToast('Creating user...', 'info');
            const response = await fetch('add_user.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (response.ok && data.success) {
                this.showToast(data.message, 'success');
                this.closeModal('addUserModal');
                this.filterUsers();
            } else {
                throw new Error(data.message || 'Failed to create user');
            }
        } catch (error) {
            console.error('Add user error:', error.message);
            this.showToast(error.message || 'Error creating user', 'error');
        } finally {
            this.showLoading(false);
            if (submitButton) submitButton.disabled = false;
        }
    }

    async handleEditUserSubmission(e) {
        e.preventDefault();
        if (this.isLoading) return;

        const form = e.target;
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            this.showLoading(true);
            this.showToast('Updating user...', 'info');
            const response = await fetch('edit_user.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (response.ok && data.success) {
                this.showToast(data.message || 'User updated successfully', 'success');
                this.closeModal('editUserModal');
                this.filterUsers();
            } else {
                throw new Error(data.message || 'Failed to update user');
            }
        } catch (error) {
            console.error('Edit user error:', error.message);
            this.showToast(error.message || 'Error updating user', 'error');
        } finally {
            this.showLoading(false);
            if (submitButton) submitButton.disabled = false;
        }
    }

    async deleteUser(userId) {
        if (this.isLoading) return;
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;

        try {
            this.showLoading(true);
            this.showToast('Deleting user...', 'info');
            const response = await fetch('delete_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${encodeURIComponent(userId)}`
            });
            const data = await response.json();
            if (response.ok && data.success) {
                this.showToast(data.message || 'User deleted successfully', 'success');
                this.filterUsers();
            } else {
                throw new Error(data.message || 'Failed to delete user');
            }
        } catch (error) {
            console.error('Delete user error:', error.message);
            this.showToast(error.message || 'Error deleting user', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    async archiveUser(userId, action) {
        if (this.isLoading) return;

        try {
            this.showLoading(true);
            this.showToast(`${action === 'archive' ? 'Archiving' : 'Activating'} user...`, 'info');
            const response = await fetch('archive_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${encodeURIComponent(userId)}&action=${encodeURIComponent(action)}`
            });
            const data = await response.json();
            if (response.ok && data.success) {
                this.showToast(data.message || `User ${action}d successfully`, 'success');
                this.filterUsers();
            } else {
                throw new Error(data.message || `Failed to ${action} user`);
            }
        } catch (error) {
            console.error(`${action} user error:`, error.message);
            this.showToast(error.message || `Error ${action}ing user`, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }

    showSection(sectionId) {
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });

        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.classList.add('active');
        }

        document.querySelectorAll('.sidebar-nav a, .nav-link').forEach(link => {
            link.classList.remove('active');
            link.setAttribute('aria-current', 'false');
        });

        const activeLink = document.querySelector(`[data-section="${sectionId}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
            activeLink.setAttribute('aria-current', 'page');
        }

        this.currentSection = sectionId;
        this.loadSectionData(sectionId);

        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            if (sidebar && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        }

        document.title = `${this.getSectionTitle(sectionId)} | Shop Manager Dashboard`;
        window.history.pushState(null, null, `#${sectionId}`);
    }

    getSectionTitle(sectionId) {
        const titles = {
            'dashboard': 'Dashboard',
            'users': 'User Management',
            'send-quotation': 'Send Quotation',
            'sales-reports': 'Sales Reports',
            'inventory-reports': 'Inventory Reports',
            'settings': 'Settings'
        };
        return titles[sectionId] || 'Dashboard';
    }

    loadSectionData(sectionId) {
        switch (sectionId) {
            case 'dashboard':
                this.loadDashboardData();
                break;
            case 'users':
                this.loadUsersData();
                break;
            case 'send-quotation':
                this.loadQuotationData();
                break;
            default:
                break;
        }
    }

    loadInitialData() {
        this.loadDashboardData();
    }

    loadDashboardData() {
        const todaysSalesElement = document.getElementById('todaysSales');
        const activeUsersElement = document.getElementById('activeUsers');
        const pendingOrdersElement = document.getElementById('pendingOrders');
        const totalProductsElement = document.getElementById('totalProducts');

        if (todaysSalesElement) {
            this.animateCounter('todaysSales', this.extractNumber(todaysSalesElement.textContent), 'MWK ');
        }
        if (activeUsersElement) {
            this.animateCounter('activeUsers', this.extractNumber(activeUsersElement.textContent));
        }
        if (pendingOrdersElement) {
            this.animateCounter('pendingOrders', this.extractNumber(pendingOrdersElement.textContent));
        }
        if (totalProductsElement) {
            this.animateCounter('totalProducts', this.extractNumber(totalProductsElement.textContent));
        }
    }

    extractNumber(text) {
        const match = text.match(/[\d,]+\.?\d*/);
        return match ? parseFloat(match[0].replace(/,/g, '')) : 0;
    }

    animateCounter(elementId, targetValue, prefix = '') {
        const element = document.getElementById(elementId);
        if (!element) return;

        const startValue = 0;
        const duration = 1000;
        const startTime = performance.now();

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const currentValue = Math.floor(startValue + (targetValue - startValue) * progress);

            if (prefix === 'MWK ') {
                element.textContent = `${prefix}${this.formatNumber(currentValue)}`;
            } else {
                element.textContent = `${prefix}${currentValue}`;
            }

            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };

        requestAnimationFrame(animate);
    }

    formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }

    loadUsersData() {
        this.filterUsers();
    }

    loadQuotationData() {
        this.loadSuppliers();
        this.loadProducts();
        this.loadRecentQuotations();
    }

    async loadSuppliers() {
        try {
            const response = await fetch('get_suppliers.php', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await response.json();
            if (response.ok && data.success) {
                const supplierSelect = document.getElementById('supplierSelect');
                if (supplierSelect) {
                    supplierSelect.innerHTML = '<option value="">Select Supplier</option>' +
                        data.suppliers.map(supplier => 
                            `<option value="${supplier.id}">${supplier.name}</option>`
                        ).join('');
                }
            } else {
                throw new Error(data.message || 'Failed to load suppliers');
            }
        } catch (error) {
            console.error('Error loading suppliers:', error.message);
            this.showToast('Error loading suppliers. Please try again.', 'error');
        }
    }

    async loadProducts() {
        try {
            const response = await fetch('get_products.php', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await response.json();
            if (response.ok && data.success) {
                const productSelects = document.querySelectorAll('select[name="product_id[]"]');
                productSelects.forEach(select => {
                    select.innerHTML = '<option value="">Select Product</option>' +
                        data.products.map(product => 
                            `<option value="${product.id}">${product.name}</option>`
                        ).join('');
                });
            } else {
                throw new Error(data.message || 'Failed to load products');
            }
        } catch (error) {
            console.error('Error loading products:', error.message);
            this.showToast('Error loading products. Please try again.', 'error');
        }
    }

    async loadRecentQuotations() {
        try {
            const response = await fetch('get_recent_quotations.php', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await response.json();
            if (response.ok && data.success) {
                const tbody = document.querySelector('#recentQuotations tbody');
                if (tbody) {
                    tbody.innerHTML = data.quotations.map(quotation => `
                        <tr>
                            <td>${this.formatDate(quotation.created_at)}</td>
                            <td>${this.escapeHTML(quotation.supplier_name)}</td>
                            <td>${quotation.items_count}</td>
                            <td>MWK ${this.formatNumber(quotation.total)}</td>
                            <td><span class="status-badge badge-${quotation.status}">${this.capitalize(quotation.status)}</span></td>
                            <td>
                                <button class="btn btn-sm btn-view" onclick="window.dashboard.viewQuotation(${quotation.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                }
            } else {
                throw new Error(data.message || 'Failed to load recent quotations');
            }
        } catch (error) {
            console.error('Error loading recent quotations:', error.message);
            this.showToast('Error loading recent quotations. Please try again.', 'error');
        }
    }

    async handleQuotationSubmission(e) {
        e.preventDefault();
        if (this.isLoading) return;

        const formData = new FormData(e.target);
        const submitButton = e.target.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        const supplierId = formData.get('supplier_id');
        const productIds = formData.getAll('product_id[]').filter(id => id);
        const quantities = formData.getAll('quantity[]').filter(qty => qty > 0);
        
        if (!supplierId) {
            this.showToast('Please select a supplier', 'error');
            if (submitButton) submitButton.disabled = false;
            return;
        }
        
        if (productIds.length === 0 || quantities.length === 0) {
            this.showToast('Please add at least one product with quantity', 'error');
            if (submitButton) submitButton.disabled = false;
            return;
        }
        
        try {
            this.showLoading(true);
            this.showToast('Sending quotation...', 'info');
            console.log('Sending quotation request:', Object.fromEntries(formData));

            const response = await fetch('send_quotation.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                this.showToast(data.message || 'Quotation sent successfully!', 'success');
                this.resetQuotationForm();
                this.loadRecentQuotations();
            } else {
                throw new Error(data.message || 'Failed to send quotation');
            }
        } catch (error) {
            console.error('Quotation submission error:', error.message);
            this.showToast(error.message || 'Error sending quotation. Please try again.', 'error');
        } finally {
            this.showLoading(false);
            if (submitButton) submitButton.disabled = false;
        }
    }

    viewQuotation(quotationId) {
        this.showToast(`Viewing quotation ${quotationId}`, 'info');
        console.log('Viewing quotation:', quotationId);
    }

    async generateSalesReport() {
        const dateFrom = document.getElementById('salesDateFrom')?.value;
        const dateTo = document.getElementById('salesDateTo')?.value;
        
        if (!dateFrom || !dateTo) {
            this.showToast('Please select both from and to dates', 'error');
            return;
        }
        
        try {
            this.showLoading(true);
            this.showToast('Generating sales report...', 'info');
            console.log('Sending sales report request:', { dateFrom, dateTo });

            const response = await fetch('generate_sales_report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}`
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                const tbody = document.querySelector('#salesReportTable tbody');
                if (tbody && data.sales) {
                    tbody.innerHTML = data.sales.map(sale => `
                        <tr>
                            <td>${this.formatDate(sale.date)}</td>
                            <td>${sale.product_name}</td>
                            <td>${sale.quantity}</td>
                            <td>MWK ${this.formatNumber(sale.unit_price)}</td>
                            <td>MWK ${this.formatNumber(sale.total)}</td>
                            <td>${sale.payment_method}</td>
                        </tr>
                    `).join('');
                }
                this.showToast(data.message || 'Sales report generated successfully!', 'success');
            } else {
                throw new Error(data.message || 'Failed to generate sales report');
            }
        } catch (error) {
            console.error('Sales report error:', error.message);
            this.showToast(error.message || 'Error generating sales report. Please try again.', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    async generateInventoryReport() {
        const category = document.getElementById('inventoryCategoryFilter')?.value || '';
        const threshold = document.getElementById('lowStockThreshold')?.value || '';
        
        try {
            this.showLoading(true);
            this.showToast('Generating inventory report...', 'info');
            console.log('Sending inventory report request:', { category, threshold });

            const response = await fetch('generate_inventory_report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `category=${encodeURIComponent(category)}&threshold=${encodeURIComponent(threshold)}`
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                const tbody = document.querySelector('#inventoryReportTable tbody');
                if (tbody && data.items) {
                    tbody.innerHTML = data.items.map(item => `
                        <tr>
                            <td>${item.id}</td>
                            <td>${item.name}</td>
                            <td>${item.category}</td>
                            <td>MWK ${this.formatNumber(item.price)}</td>
                            <td>${item.quantity}</td>
                            <td><span class="status-badge badge-${item.quantity <= threshold ? 'warning' : 'active'}">${item.quantity <= threshold ? 'Low Stock' : 'In Stock'}</span></td>
                            <td>${this.formatDate(item.last_updated)}</td>
                        </tr>
                    `).join('');
                }
                this.showToast(data.message || 'Inventory report generated successfully!', 'success');
            } else {
                throw new Error(data.message || 'Failed to generate inventory report');
            }
        } catch (error) {
            console.error('Inventory report error:', error.message);
            this.showToast(error.message || 'Error generating inventory report. Please try again.', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    async handleProfileSettingsSubmission(e) {
        e.preventDefault();
        if (this.isLoading) return;
        
        const formData = new FormData(e.target);
        const submitButton = e.target.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            this.showLoading(true);
            this.showToast('Updating profile...', 'info');
            console.log('Sending profile update request:', Object.fromEntries(formData));

            const response = await fetch('update_profile.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                this.showToast(data.message || 'Profile updated successfully!', 'success');
            } else {
                throw new Error(data.message || 'Failed to update profile');
            }
        } catch (error) {
            console.error('Profile update error:', error.message);
            this.showToast(error.message || 'Error updating profile. Please try again.', 'error');
        } finally {
            this.showLoading(false);
            if (submitButton) submitButton.disabled = false;
        }
    }

    async handleNotificationSettingsSubmission(e) {
        e.preventDefault();
        if (this.isLoading) return;
        
        const formData = new FormData(e.target);
        const submitButton = e.target.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            this.showLoading(true);
            this.showToast('Updating notification settings...', 'info');
            console.log('Sending notification settings request:', Object.fromEntries(formData));

            const response = await fetch('update_notification_settings.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                this.showToast(data.message || 'Notification settings updated successfully!', 'success');
            } else {
                throw new Error(data.message || 'Failed to update notification settings');
            }
        } catch (error) {
            console.error('Notification settings update error:', error.message);
            this.showToast(error.message || 'Error updating settings. Please try again.', 'error');
        } finally {
            this.showLoading(false);
            if (submitButton) submitButton.disabled = false;
        }
    }

    showLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            if (show) {
                overlay.classList.add('active');
            } else {
                overlay.classList.remove('active');
            }
        }
        this.isLoading = show;
    }

    showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${this.getToastIcon(type)}"></i>
            </div>
            <div class="toast-content">${message}</div>
            <button class="toast-close" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
        
        toast.querySelector('.toast-close').addEventListener('click', () => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        });
    }

    getToastIcon(type) {
        const icons = {
            'success': 'check-circle',
            'error': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    escapeHTML(str) {
        return str.replace(/[&<>"']/g, match => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[match]);
    }

    handleResize() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        if (window.innerWidth > 768) {
            if (sidebar) sidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new EnhancedShopManagerDashboard();
});

window.dashboard = null;
