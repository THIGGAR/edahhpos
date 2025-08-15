<?php
session_start();

require_once 'db_connect.php';
require_once 'functions.php';

// Check authentication
if (!validateUserSession() || !hasRole('cashier')) {
    header("Location: login.php");
    exit;
}

// Handle date range form
$start_date = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d', strtotime('-7 days'));
$end_date = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d');

$report = getSalesReport($start_date, $end_date);

// Handle CSV download
if (isset($_GET['download'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . $start_date . '_to_' . $end_date . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Orders', 'Total Sales (MWK)', 'Payment Method']);
    
    foreach ($report as $row) {
        fputcsv($output, [
            $row['sale_date'],
            $row['orders_count'],
            number_format($row['total_sales'], 2),
            ucfirst(str_replace('_', ' ', $row['payment_method']))
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report | Auntie Eddah POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo">
            <i class="fas fa-cash-register"></i> 
            Cashier Panel
        </div>
        <div class="user-profile">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Cashier'); ?></span>
        </div>
    </header>

    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="pending_orders.php">
                <i class="fas fa-clock"></i>
                <span>Pending Orders</span>
            </a>
            <a href="completed_orders.php">
                <i class="fas fa-check-circle"></i>
                <span>Completed Orders</span>
            </a>
            <a href="scan_barcode.php">
                <i class="fas fa-barcode"></i>
                <span>Scan Barcode</span>
            </a>
            <a href="sales_report.php" class="active">
                <i class="fas fa-chart-bar"></i>
                <span>Sales Report</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="content">
            <section class="section">
                <h2><i class="fas fa-chart-bar"></i> Sales Report</h2>
                
                <form method="GET" style="margin-bottom: 20px;">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&download=1" class="btn btn-success">
                            <i class="fas fa-download"></i> Download CSV
                        </a>
                    </div>
                </form>

                <?php if (empty($report)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 4rem; color: var(--secondary-color); margin-bottom: 20px;"></i>
                        <h3>No Sales Data</h3>
                        <p>No sales recorded for the selected period.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Orders</th>
                                    <th>Total Sales (MWK)</th>
                                    <th>Payment Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report as $row): ?>
                                    <tr>
                                        <td><?php echo $row['sale_date']; ?></td>
                                        <td><?php echo $row['orders_count']; ?></td>
                                        <td><?php echo number_format($row['total_sales'], 2); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <p><strong>Total Sales: MWK<?php 
                            $total_sales = array_sum(array_column($report, 'total_sales'));
                            echo number_format($total_sales, 2); 
                        ?></strong></p>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <footer>
            &copy; <?php echo date('Y'); ?> Auntie Eddah POS
        </footer>
    </main>
</body>
</html>
?>```javascript
// Integrated Cashier Dashboard JavaScript
class CashierDashboard {
    constructor() {
        this.currentSection = 'dashboard';
        this.cart = [];
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupKeyboardShortcuts();
        this.startAutoRefresh();
        this.focusBarcodeInput();
    }

    bindEvents() {
        // Navigation events
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (item.getAttribute('href')?.startsWith('#')) {
                    e.preventDefault();
                    const section = item.getAttribute('href').substring(1);
                    this.showSection(section);
                }
            });
        });

        // Barcode input events
        const barcodeInput = document.getElementById('barcode-input');
        if (barcodeInput) {
            barcodeInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.addToCart();
                }
            });

            barcodeInput.addEventListener('input', (e) => {
                this.debounce(() => this.previewProduct(e.target.value), 300);
            });
        }

        // Cart action buttons
        const addToCartBtn = document.getElementById('add-to-cart-btn');
        if (addToCartBtn) {
            addToCartBtn.addEventListener('click', () => this.addToCart());
        }

        const clearCartBtn = document.getElementById('clear-cart-btn');
        if (clearCartBtn) {
            clearCartBtn.addEventListener('click', () => this.clearCart());
        }

        const processPaymentBtn = document.getElementById('process-payment-btn');
        if (processPaymentBtn) {
            processPaymentBtn.addEventListener('click', () => this.processPayment());
        }

        // Payment method selection
        const paymentMethodSelect = document.getElementById('payment-method');
        if (paymentMethodSelect) {
            paymentMethodSelect.addEventListener('change', (e) => {
                const processBtn = document.getElementById('process-payment-btn');
                if (processBtn) {
                    processBtn.disabled = !e.target.value || this.getCartItemCount() === 0;
                }
            });
        }

        // Cart item controls (delegated event listener for dynamic content)
        document.getElementById('cart-items-list')?.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-item')) {
                const index = parseInt(e.target.closest('.remove-item').dataset.index);
                this.removeCartItem(index);
            } else if (e.target.classList.contains('increase-qty')) {
                const index = parseInt(e.target.closest('.increase-qty').dataset.index);
                this.updateQuantity(index, 1);
            } else if (e.target.classList.contains('decrease-qty')) {
                const index = parseInt(e.target.closest('.decrease-qty').dataset.index);
                this.updateQuantity(index, -1);
            }
        });

        // Sales report form submission
        const salesReportForm = document.getElementById('sales-report-form');
        if (salesReportForm) {
            salesReportForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                await this.loadSalesReport(startDate, endDate);
            });
        }

        // Download CSV button
        const downloadCsvBtn = document.getElementById('download-csv-btn');
        if (downloadCsvBtn) {
            downloadCsvBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                window.location.href = `?start_date=${startDate}&end_date=${endDate}&download=1`;
            });
        }

        // Confirm correction forms for pending orders (delegated event listener)
        document.getElementById('pending-orders-table')?.addEventListener('submit', async (e) => {
            if (e.target.classList.contains('confirm-correction-form')) {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);
                formData.append('ajax', 'confirm_correction');

                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.status === 'success') {
                        this.showToast(data.message, 'success');
                        const orderRow = form.closest('tr');
                        if (orderRow) {
                            orderRow.remove();
                            this.refreshPendingOrdersData();
                        }
                        this.refreshStats();
                    } else {
                        this.showToast(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error confirming correction:', error);
                    this.showToast('Error confirming correction', 'error');
                }
            }
        });
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + N: New Sale
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                this.showSection('new-sale');
            }

            // Ctrl/Cmd + R: Refresh Stats
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.refreshStats();
            }

            // Escape: Back to dashboard
            if (e.key === 'Escape' && this.currentSection !== 'dashboard') {
                this.showSection('dashboard');
            }
        });
    }

    showSection(sectionName) {
        // Hide all sections
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });

        // Show target section
        const targetSection = document.getElementById(`${sectionName}-section`);
        if (targetSection) {
            targetSection.classList.add('active');
            this.currentSection = sectionName;
        }

        // Update navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });

        const activeNavItem = document.querySelector(`.nav-item[href="#${sectionName}"]`) || 
                             document.querySelector(`.nav-item[onclick*="${sectionName}"]`);
        if (activeNavItem) {
            activeNavItem.classList.add('active');
        }

        // Load content dynamically based on section
        if (sectionName === 'new-sale') {
            this.focusBarcodeInput();
        } else if (sectionName === 'pending-orders') {
            this.loadPendingOrders();
        } else if (sectionName === 'completed-orders') {
            this.loadCompletedOrders();
        } else if (sectionName === 'sales-report') {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            this.loadSalesReport(startDate, endDate);
        }
    }

    focusBarcodeInput() {
        setTimeout(() => {
            const barcodeInput = document.getElementById('barcode-input');
            if (barcodeInput && this.currentSection === 'new-sale') {
                barcodeInput.focus();
            }
        }, 100);
    }

    async previewProduct(barcode) {
        if (!barcode || barcode.length < 3) {
            this.hideProductPreview();
            return;
        }

        try {
            const response = await fetch(`?ajax=product_details&barcode=${encodeURIComponent(barcode)}`);
            const data = await response.json();

            if (data.status === 'success') {
                this.showProductPreview(data.data);
            } else {
                this.hideProductPreview();
            }
        } catch (error) {
            console.error('Error fetching product details:', error);
            this.hideProductPreview();
        }
    }

    showProductPreview(product) {
        const preview = document.getElementById('product-preview');
        const nameEl = document.getElementById('product-name');
        const priceEl = document.getElementById('product-price');
        const categoryEl = document.getElementById('product-category');

        if (preview && nameEl && priceEl && categoryEl) {
            nameEl.textContent = product.name;
            priceEl.textContent = `MWK${parseFloat(product.price).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            categoryEl.textContent = product.category;
            preview.style.display = 'block';
        }
    }

    hideProductPreview() {
        const preview = document.getElementById('product-preview');
        if (preview) {
            preview.style.display = 'none';
        }
    }

    async addToCart() {
        const barcodeInput = document.getElementById('barcode-input');
        const barcode = barcodeInput?.value.trim();

        if (!barcode) {
            this.showToast('Please enter a barcode', 'warning');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('ajax', 'add_to_cart');
            formData.append('csrf_token', this.csrfToken);
            formData.append('barcode', barcode);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                this.showToast(data.message, 'success');
                this.updateCartDisplay();
                this.updateCartTotal(data.cart_total);
                this.updateCartBadge(data.cart_count);
                barcodeInput.value = '';
                this.hideProductPreview();
                this.focusBarcodeInput();
            } else {
                this.showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            this.showToast('Error adding product to cart', 'error');
        }
    }

    async removeCartItem(index) {
        try {
            const formData = new FormData();
            formData.append('ajax', 'cart_operation');
            formData.append('operation', 'remove_item');
            formData.append('item_index', index);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                this.showToast(data.message, 'success');
                this.updateCartDisplay();
                this.updateCartTotal(data.cart_total);
                this.updateCartBadge(data.cart_count);
            } else {
                this.showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error removing cart item:', error);
            this.showToast('Error removing item from cart', 'error');
        }
    }

    async updateQuantity(index, change) {
        const cartItem = document.querySelector(`.cart-item[data-index="${index}"]`);
        const quantityEl = cartItem?.querySelector('.quantity');
        const currentQty = parseInt(quantityEl?.textContent || '0');
        const newQty = currentQty + change;

        if (newQty < 1) {
            this.removeCartItem(index);
            return;
        }

        try {
            const formData = new FormData();
            formData.append('ajax', 'cart_operation');
            formData.append('operation', 'update_quantity');
            formData.append('item_index', index);
            formData.append('quantity', newQty);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                this.updateCartDisplay();
                this.updateCartTotal(data.cart_total);
            } else {
                this.showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error updating quantity:', error);
            this.showToast('Error updating quantity', 'error');
        }
    }

    async clearCart() {
        if (!confirm('Are you sure you want to clear the cart?')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('ajax', 'cart_operation');
            formData.append('operation', 'clear');

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                this.showToast(data.message, 'success');
                this.updateCartDisplay();
                this.updateCartTotal(data.cart_total);
                this.updateCartBadge(data.cart_count);
            } else {
                this.showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error clearing cart:', error);
            this.showToast('Error clearing cart', 'error');
        }
    }

    async processPayment() {
        const paymentMethod = document.getElementById('payment-method')?.value;
        
        if (!paymentMethod) {
            this.showToast('Please select a payment method', 'warning');
            return;
        }

        if (this.getCartItemCount() === 0) {
            this.showToast('Cart is empty', 'warning');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('ajax', 'process_payment');
            formData.append('payment_method', paymentMethod);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                this.showToast(data.message, 'success');
                this.updateCartDisplay();
                this.updateCartTotal('0.00');
                this.updateCartBadge(0);
                
                // Reset payment method
                document.getElementById('payment-method').value = '';
                document.getElementById('process-payment-btn').disabled = true;
                
                // Refresh stats
                this.refreshStats();
                
                // Show success modal or redirect
                if (data.pending) {
                    setTimeout(() => {
                        this.showSection('dashboard');
                    }, 2000);
                } else {
                    setTimeout(() => {
                        this.showSection('dashboard');
                    }, 2000);
                }
            } else {
                this.showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error processing payment:', error);
            this.showToast('Error processing payment', 'error');
        }
    }

    async updateCartDisplay() {
        try {
            const response = await fetch('?ajax=cart_data');
            const data = await response.json();

            const cartItemsList = document.getElementById('cart-items-list');
            if (cartItemsList) {
                cartItemsList.innerHTML = '';
                if (data.cart && data.cart.length > 0) {
                    data.cart.forEach((item, index) => {
                        const li = document.createElement('li');
                        li.className = 'cart-item';
                        li.dataset.index = index;
                        li.innerHTML = `
                            <div class="item-details">
                                <span class="item-name">${this.escapeHtml(item.name)}</span>
                                <span class="item-price">MWK${parseFloat(item.price).toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                            </div>
                            <div class="item-controls">
                                <button class="btn btn-sm btn-secondary decrease-qty" data-index="${index}">-</button>
                                <span class="quantity">${item.quantity}</span>
                                <button class="btn btn-sm btn-secondary increase-qty" data-index="${index}">+</button>
                                <button class="btn btn-sm btn-danger remove-item" data-index="${index}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `;
                        cartItemsList.appendChild(li);
                    });
                } else {
                    cartItemsList.innerHTML = '<li class="empty-cart-message">Your cart is empty. Scan a product to add.</li>';
                }
                this.updateCartTotal(data.cart_total);
                this.updateCartBadge(data.cart_count);
            }
        } catch (error) {
            console.error('Error updating cart display:', error);
            this.showToast('Error updating cart display', 'error');
        }
    }

    updateCartTotal(total) {
        const cartTotalEl = document.getElementById('cart-total');
        if (cartTotalEl) {
            cartTotalEl.textContent = `MWK${total}`;
        }
    }

    updateCartBadge(count) {
        const badge = document.getElementById('cart-badge');
        if (count > 0) {
            if (badge) {
                badge.textContent = count;
                badge.style.display = 'inline';
            } else {
                const newSaleNav = document.querySelector('.nav-item[href="#new-sale"]');
                if (newSaleNav) {
                    const badgeEl = document.createElement('span');
                    badgeEl.className = 'badge';
                    badgeEl.id = 'cart-badge';
                    badgeEl.textContent = count;
                    newSaleNav.appendChild(badgeEl);
                }
            }
        } else {
            if (badge) {
                badge.style.display = 'none';
            }
        }
    }

    getCartItemCount() {
        return document.querySelectorAll('.cart-item').length;
    }

    async refreshStats() {
        try {
            const response = await fetch('?ajax=stats');
            const data = await response.json();

            if (data.status === 'success') {
                const stats = data.stats;
                
                const ordersToday = document.getElementById('orders-today');
                const pendingPayments = document.getElementById('pending-payments');
                const transactionsCount = document.getElementById('transactions-count');
                const totalSales = document.getElementById('total-sales');
                const lastUpdated = document.getElementById('last-updated');

                if (ordersToday) ordersToday.textContent = stats.orders_today;
                if (pendingPayments) pendingPayments.textContent = stats.pending_payments;
                if (transactionsCount) transactionsCount.textContent = stats.transactions_count;
                if (totalSales) totalSales.textContent = `MWK${parseFloat(stats.total_sales_today).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
                if (lastUpdated) lastUpdated.textContent = new Date().toLocaleString();

                this.showToast('Stats updated successfully', 'success');
            }
        } catch (error) {
            console.error('Error refreshing stats:', error);
            this.showToast('Error refreshing stats', 'error');
        }
    }

    async loadPendingOrders() {
        try {
            const response = await fetch('?ajax=pending_orders_data');
            const data = await response.json();

            const pendingOrdersSection = document.getElementById('pending-orders-section');
            if (pendingOrdersSection) {
                const tableContainer = pendingOrdersSection.querySelector('.table-container');
                const emptyMessageDiv = pendingOrdersSection.querySelector('div[style*="text-align: center;"]');
                const pendingCountEl = document.getElementById('pending-count');
                const totalPendingAmountEl = document.getElementById('total-pending-amount');

                if (data.status === 'success' && data.data.length > 0) {
                    if (emptyMessageDiv) emptyMessageDiv.style.display = 'none';
                    if (tableContainer) tableContainer.style.display = 'block';

                    let tableHtml = `
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    let totalPending = 0;
                    data.data.forEach(order => {
                        const icons = {
                            'cash': 'fas fa-money-bill-wave',
                            'mpamba': 'fas fa-mobile-alt',
                            'airtel_money': 'fas fa-mobile-alt',
                            'card': 'fas fa-credit-card'
                        };
                        const icon = icons[order.payment_method] || 'fas fa-question';
                        const customerName = this.escapeHtml((order.first_name || '') + ' ' + (order.last_name || ''));
                        const createdAtDate = new Date(order.created_at);
                        const formattedDate = createdAtDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                        const formattedTime = createdAtDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                        totalPending += parseFloat(order.total);

                        tableHtml += `
                            <tr data-order-id="${order.order_id}">
                                <td><strong>#${order.order_id}</strong></td>
                                <td>${customerName}</td>
                                <td><strong>MWK${parseFloat(order.total).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
                                <td>
                                    <span class="payment-method ${order.payment_method}">
                                        <i class="${icon}"></i>
                                        ${this.capitalizeFirstLetter(order.payment_method.replace('_', ' '))}
                                    </span>
                                </td>
                                <td>
                                    ${formattedDate}<br>
                                    <small>${formattedTime}</small>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <form class="confirm-correction-form" data-order-id="${order.order_id}">
                                            <input type="hidden" name="order_id" value="${order.order_id}">
                                            <input type="hidden" name="csrf_token" value="${this.csrfToken}">
                                            <input type="hidden" name="ajax" value="confirm_correction">
                                            <button type="submit" name="confirm_correction" class="btn btn-success btn-sm" data-order-id="${order.order_id}">
                                                <i class="fas fa-check"></i> Confirm Correction
                                            </button>
                                        </form>
                                        <a href="order_details.php?id=${order.order_id}" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    tableHtml += `
                            </tbody>
                        </table>
                    `;
                    if (tableContainer) {
                        tableContainer.innerHTML = tableHtml;
                    }
                    if (pendingCountEl) pendingCountEl.textContent = data.data.length;
                    if (totalPendingAmountEl) totalPendingAmountEl.textContent = `MWK${totalPending.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
                } else {
                    if (emptyMessageDiv) emptyMessageDiv.style.display = 'block';
                    if (tableContainer) tableContainer.style.display = 'none';
                    if (pendingCountEl) pendingCountEl.textContent = 0;
                    if (totalPendingAmountEl) totalPendingAmountEl.textContent = 'MWK0.00';
                }
            }
        } catch (error) {
            console.error('Error loading pending orders:', error);
            this.showToast('Error loading pending orders', 'error');
        }
    }

    async refreshPendingOrdersData() {
        await this.loadPendingOrders();
    }

    async loadCompletedOrders() {
        try {
            const response = await fetch('?ajax=completed_orders_data');
            const data = await response.json();

            const completedOrdersSection = document.getElementById('completed-orders-section');
            if (completedOrdersSection) {
                const tableContainer = completedOrdersSection.querySelector('.table-container');
                const emptyMessageDiv = completedOrdersSection.querySelector('div[style*="text-align: center;"]');
                const completedCountEl = document.getElementById('completed-count');
                const totalCompletedAmountEl = document.getElementById('total-completed-amount');

                if (data.status === 'success' && data.data.length > 0) {
                    if (emptyMessageDiv) emptyMessageDiv.style.display = 'none';
                    if (tableContainer) tableContainer.style.display = 'block';

                    let tableHtml = `
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Completed At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    let totalCompleted = 0;
                    data.data.forEach(order => {
                        const icons = {
                            'cash': 'fas fa-money-bill-wave',
                            'mpamba': 'fas fa-mobile-alt',
                            'airtel_money': 'fas fa-mobile-alt',
                            'card': 'fas fa-credit-card'
                        };
                        const icon = icons[order.payment_method] || 'fas fa-question';
                        const customerName = this.escapeHtml((order.first_name || '') + ' ' + (order.last_name || ''));
                        const updatedAtDate = new Date(order.updated_at);
                        const formattedDate = updatedAtDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                        const formattedTime = updatedAtDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                        totalCompleted += parseFloat(order.total);

                        tableHtml += `
                            <tr data-order-id="${order.order_id}">
                                <td><strong>#${order.order_id}</strong></td>
                                <td>${customerName}</td>
                                <td><strong class="order-amount">MWK${parseFloat(order.total).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
                                <td>
                                    <span class="payment-method ${order.payment_method}">
                                        <i class="${icon}"></i>
                                        ${this.capitalizeFirstLetter(order.payment_method.replace('_', ' '))}
                                    </span>
                                </td>
                                <td>
                                    ${formattedDate}<br>
                                    <small>${formattedTime}</small>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="order_details.php?id=${order.order_id}" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <button class="btn btn-primary btn-sm" data-action="print-receipt" data-order-id="${order.order_id}">
                                            <i class="fas fa-print"></i> Print
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    tableHtml += `
                            </tbody>
                        </table>
                    `;
                    if (tableContainer) {
                        tableContainer.innerHTML = tableHtml;
                    }
                    if (completedCountEl) completedCountEl.textContent = data.data.length;
                    if (totalCompletedAmountEl) totalCompletedAmountEl.textContent = `MWK${totalCompleted.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
                } else {
                    if (emptyMessageDiv) emptyMessageDiv.style.display = 'block';
                    if (tableContainer) tableContainer.style.display = 'none';
                    if (completedCountEl) completedCountEl.textContent = 0;
                    if (totalCompletedAmountEl) totalCompletedAmountEl.textContent = 'MWK0.00';
                }
            }
        } catch (error) {
            console.error('Error loading completed orders:', error);
            this.showToast('Error loading completed orders', 'error');
        }
    }

    async loadSalesReport(startDate, endDate) {
        try {
            const response = await fetch(`?ajax=sales_report_data&start_date=${startDate}&end_date=${endDate}`);
            const data = await response.json();

            const salesReportContent = document.getElementById('sales-report-content');
            if (salesReportContent) {
                if (data.status === 'success' && data.data.length > 0) {
                    // Aggregate data by date for the chart
                    const aggregatedData = {};
                    data.data.forEach(row => {
                        if (!aggregatedData[row.sale_date]) {
                            aggregatedData[row.sale_date] = {
                                orders_count: 0,
                                total_sales: 0
                            };
                        }
                        aggregatedData[row.sale_date].orders_count += parseInt(row.orders_count);
                        aggregatedData[row.sale_date].total_sales += parseFloat(row.total_sales);
                    });

                    // Prepare chart data
                    const dates = Object.keys(aggregatedData).sort((a, b) => new Date(a) - new Date(b));
                    const salesData = dates.map(date => aggregatedData[date].total_sales);

                    // Render chart
                    const ctx = document.getElementById('salesChart')?.getContext('2d');
                    if (ctx) {
                        if (this.salesChart) {
                            this.salesChart.destroy();
                        }
                        this.salesChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: dates,
                                datasets: [{
                                    label: 'Total Sales (MWK)',
                                    data: salesData,
                                    backgroundColor: '#007bff',
                                    borderColor: '#0056b3',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Total Sales (MWK)'
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Date'
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'top'
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return `MWK${context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // Render table
                    let tableHtml = `
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Orders</th>
                                        <th>Total Sales (MWK)</th>
                                        <th>Payment Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    let totalSales = 0;
                    data.data.forEach(row => {
                        totalSales += parseFloat(row.total_sales);
                        tableHtml += `
                            <tr>
                                <td>${row.sale_date}</td>
                                <td>${row.orders_count}</td>
                                <td>${parseFloat(row.total_sales).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                <td>${this.capitalizeFirstLetter(row.payment_method.replace('_', ' '))}</td>
                            </tr>
                        `;
                    });
                    tableHtml += `
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top: 20px; text-align: center;">
                            <p><strong>Total Sales: MWK${totalSales.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></p>
                        </div>
                    `;
                    salesReportContent.innerHTML = `
                        <div class="chart-container" style="margin-bottom: 30px;">
                            <canvas id="salesChart"></canvas>
                        </div>
                        ${tableHtml}
                    `;
                } else {
                    salesReportContent.innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-inbox" style="font-size: 4rem; color: var(--secondary-color); margin-bottom: 20px;"></i>
                            <h3>No Sales Data</h3>
                            <p>No sales recorded for the selected period.</p>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading sales report:', error);
            this.showToast('Error loading sales report', 'error');
        }
    }

    startAutoRefresh() {
        setInterval(() => {
            this.refreshStats();
            if (this.currentSection === 'pending-orders') {
                this.loadPendingOrders();
            }
            if (this.currentSection === 'completed-orders') {
                this.loadCompletedOrders();
            }
        }, 30000);
    }

    showToast(message, type = 'info') {
        const notificationContainer = document.getElementById('notification-container');
        if (!notificationContainer) return;

        const toast = document.createElement('div');
        toast.className = `notification ${type} animate__animated animate__fadeInDown`;
        toast.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
        notificationContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('animate__fadeOutUp');
            toast.addEventListener('animationend', () => toast.remove());
        }, 5000);
    }

    debounce(func, delay) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), delay);
        };
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new CashierDashboard();
    const urlParams = new URLSearchParams(window.location.search);
    const section = urlParams.get('section') || 'dashboard';
    window.dashboard.showSection(section);
});
```