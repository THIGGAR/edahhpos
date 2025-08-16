<?php
session_start();

require_once 'db_connect.php';
require_once 'functions.php';

// Ensure user_email and user_name are set
if (!isset($_SESSION['user_email'])) {
    $_SESSION['user_email'] = 'cashier@example.com';
}
if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'Cashier';
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCsrfToken();
}

// Determine which section to display
$section = filter_input(INPUT_GET, 'section', FILTER_SANITIZE_STRING) ?? 'dashboard';

// Handle AJAX requests
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    if ($_POST['ajax'] === 'store_cart_data') {
        $csrf_token = sanitizeInput($_POST['csrf_token'] ?? '');
        $cart_data = json_decode($_POST['cart_data'] ?? '{}', true);
        $transaction_ref = sanitizeInput($_POST['transaction_ref'] ?? '');

        if (!validateCsrfToken($csrf_token)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid security token'
            ]);
            exit;
        }

        if (empty($cart_data) || empty($transaction_ref)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid cart data or transaction reference'
            ]);
            exit;
        }

        $_SESSION['cart_data'] = $cart_data;
        $_SESSION['transaction_ref'] = $transaction_ref;

        echo json_encode([
            'status' => 'success',
            'message' => 'Cart data stored in session'
        ]);
        exit;
    } elseif ($_POST['ajax'] === 'add_to_cart') {
        $csrf_token = sanitizeInput($_POST['csrf_token'] ?? '');
        $barcode = sanitizeInput($_POST['barcode'] ?? '');

        if (!validateCsrfToken($csrf_token)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid security token. Please try again.',
                'message_type' => 'danger'
            ]);
            exit;
        }

        if (empty($barcode)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Please enter a barcode',
                'message_type' => 'danger'
            ]);
            exit;
        }

        $products = getProductByBarcode($barcode);

        if (!empty($products)) {
            $product = $products[0];

            // Check if product already in cart
            $item_index = -1;
            foreach ($_SESSION['cart'] as $index => $item) {
                if ($item['product_id'] == $product['product_id']) {
                    $item_index = $index;
                    break;
                }
            }

            if ($item_index >= 0) {
                // Update existing item quantity
                $_SESSION['cart'][$item_index]['quantity'] += 1;
            } else {
                // Add new item to cart
                $_SESSION['cart'][] = [
                    'product_id' => $product['product_id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => 1,
                    'barcodes' => $barcode
                ];
            }

            // Calculate cart total for response
            $cart_total = 0;
            foreach ($_SESSION['cart'] as $item) {
                $cart_total += $item['price'] * $item['quantity'];
            }

            echo json_encode([
                'status' => 'success',
                'message' => "{$product['name']} (MWK" . number_format($product['price'], 2) . ") added to cart",
                'message_type' => 'success',
                'cart_total' => number_format($cart_total, 2),
                'cart_count' => count($_SESSION['cart'])
            ]);
            exit;
        }

        echo json_encode([
            'status' => 'error',
            'message' => "Product not found for barcode: " . htmlspecialchars($barcode),
            'message_type' => 'danger'
        ]);
        exit;
    } elseif ($_POST['ajax'] === 'cart_operation') {
        $operation = sanitizeInput($_POST['operation'] ?? '');

        if ($operation === 'clear') {
            $_SESSION['cart'] = [];
            unset($_SESSION['cart_data'], $_SESSION['transaction_ref']);
            echo json_encode([
                'status' => 'success',
                'message' => 'Cart cleared successfully',
                'cart_total' => '0.00',
                'cart_count' => 0
            ]);
            exit;
        }

        if ($operation === 'remove_item') {
            $item_index = intval($_POST['item_index'] ?? -1);
            if (isset($_SESSION['cart'][$item_index])) {
                $item_name = $_SESSION['cart'][$item_index]['name'];
                unset($_SESSION['cart'][$item_index]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);

                $cart_total = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $cart_total += $item['price'] * $item['quantity'];
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => "Removed {$item_name} from cart",
                    'cart_total' => number_format($cart_total, 2),
                    'cart_count' => count($_SESSION['cart'])
                ]);
                exit;
            }
        }

        if ($operation === 'update_quantity') {
            $item_index = intval($_POST['item_index'] ?? -1);
            $new_quantity = intval($_POST['quantity'] ?? 0);

            if (isset($_SESSION['cart'][$item_index]) && $new_quantity > 0) {
                $_SESSION['cart'][$item_index]['quantity'] = $new_quantity;

                $cart_total = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $cart_total += $item['price'] * $item['quantity'];
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Quantity updated',
                    'cart_total' => number_format($cart_total, 2)
                ]);
                exit;
            }
        }

        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid operation'
        ]);
        exit;
    } elseif ($_POST['ajax'] === 'process_payment') {
        $payment_method = sanitizeInput($_POST['payment_method'] ?? '');
        $tx_ref = sanitizeInput($_POST['tx_ref'] ?? '');
        $csrf_token = sanitizeInput($_POST['csrf_token'] ?? '');

        if (!validateCsrfToken($csrf_token)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid security token'
            ]);
            exit;
        }

        if (empty($_SESSION['cart'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Cart is empty'
            ]);
            exit;
        }

        if (empty($payment_method)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Please select a payment method'
            ]);
            exit;
        }

        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        $order_id = createOrder($_SESSION['user_id'], $_SESSION['cart'], $payment_method, $total);

        if ($order_id) {
            if ($payment_method === 'cash') {
                $payment_result = confirmPayment($order_id);
                if ($payment_result['success']) {
                    $_SESSION['cart'] = [];
                    unset($_SESSION['cart_data'], $_SESSION['transaction_ref']);
                    echo json_encode([
                        'status' => 'success',
                        'message' => "Order #$order_id completed successfully. Total: MWK" . number_format($total, 2),
                        'order_id' => $order_id,
                        'total' => number_format($total, 2),
                        'redirect' => 'dashboard.php?section=completed-orders'
                    ]);
                    exit;
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Failed to process payment: {$payment_result['error']}"
                    ]);
                    exit;
                }
            } elseif ($payment_method === 'Mobile Transfer') {
                if ($tx_ref) {
                    // Verify payment
                    $payment_status = verifyPaychanguPayment($tx_ref);
                    if ($payment_status['status'] === 'success' && $payment_status['data']['status'] === 'success') {
                        // Update orders table
                        updateOrderStatus($order_id, 'completed');

                        // Insert into payments table
                        insertPaymentRecord(
                            $order_id,
                            $tx_ref,
                            $_SESSION['user_email'],
                            $_SESSION['user_name'],
                            '', // Last name not available
                            $total,
                            'success',
                            $payment_method
                        );

                        $_SESSION['cart'] = [];
                        unset($_SESSION['cart_data'], $_SESSION['transaction_ref']);
                        echo json_encode([
                            'status' => 'success',
                            'message' => "Order #$order_id completed successfully. Total: MWK" . number_format($total, 2),
                            'order_id' => $order_id,
                            'total' => number_format($total, 2),
                            'redirect' => 'dashboard.php?section=completed-orders'
                        ]);
                        exit;
                    } else {
                        echo json_encode([
                            'status' => 'error',
                            'message' => "Payment verification failed: " . ($payment_status['message'] ?? 'Unknown error')
                        ]);
                        exit;
                    }
                } else {
                    // Initiate mobile payment
                    $tx_ref = 'PA' . $order_id . time();
                    $_SESSION['pending_order_id'] = $order_id;
                    echo json_encode([
                        'status' => 'pending',
                        'message' => "Initiating mobile payment for Order #$order_id",
                        'order_id' => $order_id,
                        'total' => number_format($total, 2),
                        'tx_ref' => $tx_ref,
                        'pending' => true
                    ]);
                    exit;
                }
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to create order. Please try again.'
            ]);
            exit;
        }
    } elseif ($_POST['ajax'] === 'confirm_correction') {
        $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        $csrf_token = sanitizeInput($_POST['csrf_token'] ?? '');

        $response = [
            'status' => 'error',
            'message' => 'Failed to confirm correction.',
            'message_type' => 'danger',
            'order_id' => $order_id,
            'order_total' => 0
        ];

        if (!validateCsrfToken($csrf_token)) {
            $response['message'] = "Invalid security token.";
            echo json_encode($response);
            exit;
        }

        if ($order_id) {
            $order = getOrderById($order_id);
            if ($order && confirmOrderCorrection($order_id)) {
                logActivity($_SESSION['user_id'], "Confirmed correction for order #$order_id", 'order_correction');
                $response = [
                    'status' => 'success',
                    'message' => "Order #$order_id correction confirmed successfully.",
                    'message_type' => 'success',
                    'order_id' => $order_id,
                    'order_total' => $order['total']
                ];
            } else {
                $response['message'] = "Failed to confirm correction for order #$order_id.";
            }
        }
        echo json_encode($response);
        exit;
    }
}

// Handle GET AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    if ($_GET['ajax'] === 'stats') {
        $stats = getDashboardStats();
        echo json_encode([
            'status' => 'success',
            'stats' => $stats,
            'message' => 'Stats retrieved successfully'
        ]);
        exit;
    } elseif ($_GET['ajax'] === 'product_details') {
        $barcode = sanitizeInput($_GET['barcode'] ?? '');
        $products = getProductByBarcode($barcode);
        echo json_encode([
            'status' => !empty($products) ? 'success' : 'error',
            'data' => !empty($products) ? [
                'product_id' => $products[0]['product_id'],
                'name' => $products[0]['name'],
                'price' => $products[0]['price'],
                'category' => $products[0]['category']
            ] : [],
            'message' => !empty($products) ? 'Product found' : "Product not found for barcode: $barcode"
        ]);
        exit;
    } elseif ($_GET['ajax'] === 'completed_orders_data') {
        $completed_orders = getCompletedOrders();
        echo json_encode(['status' => 'success', 'data' => $completed_orders]);
        exit;
    } elseif ($_GET['ajax'] === 'pending_orders_data') {
        $pending_orders = getCustomerPendingOrders();
        echo json_encode(['status' => 'success', 'data' => $pending_orders]);
        exit;
    } elseif ($_GET['ajax'] === 'sales_report_data') {
        $start_date = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d', strtotime('-7 days'));
        $end_date = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d');
        $report = getSalesReport($start_date, $end_date);
        echo json_encode(['status' => 'success', 'data' => $report]);
        exit;
    } elseif ($_GET['ajax'] === 'cart_data') {
        $cart_total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $cart_total += $item['price'] * $item['quantity'];
        }
        echo json_encode([
            'status' => 'success',
            'cart' => $_SESSION['cart'],
            'cart_total' => number_format($cart_total, 2),
            'cart_count' => count($_SESSION['cart'])
        ]);
        exit;
    }
}

// Function to verify Paychangu payment
function verifyPaychanguPayment($tx_ref) {
    $secret_key = 'SEC-S1l5Jkcc9FSgJao8tlm2kcFwbb9xi13u';
    $url = "https://api.paychangu.com/verify-payment/{$tx_ref}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $secret_key
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        return json_decode($response, true);
    } else {
        return [
            'status' => 'error',
            'message' => $error ?: 'Failed to verify payment w...(truncated 45962 characters)...="item-details">
                                <span class="item-name">${item.name}</span>
                                <span class="item-price">MWK${Number(item.price).toFixed(2)}</span>
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
                        cartList.appendChild(li);
                    });
                }
                document.getElementById('cart-count').textContent = data.cart_count;
                document.getElementById('cart-total').textContent = `MWK${data.cart_total}`;
                document.getElementById('process-payment-btn').disabled = !this.validatePaymentForm();
            }
        } catch (error) {
            console.error('Error refreshing cart:', error);
            this.showToast('Error refreshing cart', 'error');
        }
    }

    async refreshStats() {
        try {
            const response = await fetch('?ajax=stats');
            const data = await response.json();
            if (data.status === 'success') {
                document.getElementById('orders-today').textContent = data.stats.orders_today;
                document.getElementById('pending-payments').textContent = data.stats.pending_payments;
                document.getElementById('transactions-count').textContent = data.stats.transactions_count;
                document.getElementById('total-sales').textContent = `MWK${Number(data.stats.total_sales_today).toFixed(2)}`;
            } else {
                this.showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error refreshing stats:', error);
            this.showToast('Error refreshing stats', 'error');
        }
    }

    async refreshPendingOrdersData() {
        try {
            const response = await fetch('?ajax=pending_orders_data');
            const data = await response.json();
            if (data.status === 'success') {
                const tableBody = document.querySelector('#pending-orders-table tbody');
                tableBody.innerHTML = '';
                if (data.data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No pending orders.</td></tr>';
                } else {
                    data.data.forEach(order => {
                        const tr = document.createElement('tr');
                        tr.dataset.orderId = order.order_id;
                        tr.innerHTML = `
                            <td><strong>#${order.order_id}</strong></td>
                            <td>${order.first_name || ''} ${order.last_name || ''}</td>
                            <td><strong>MWK${Number(order.total).toFixed(2)}</strong></td>
                            <td>
                                <span class="payment-method ${order.payment_method}">
                                    <i class="${this.getPaymentIcon(order.payment_method)}"></i>
                                    ${this.capitalizeWords(order.payment_method.replace('_', ' '))}
                                </span>
                            </td>
                            <td>
                                ${new Date(order.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}<br>
                                <small>${new Date(order.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</small>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <form class="confirm-correction-form" data-order-id="${order.order_id}">
                                        <input type="hidden" name="order_id" value="${order.order_id}">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
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
                        `;
                        tableBody.appendChild(tr);
                    });
                }
                document.getElementById('pending-count').textContent = data.data.length;
                const totalPending = data.data.reduce((sum, order) => sum + parseFloat(order.total), 0);
                document.getElementById('total-pending-amount').textContent = `MWK${totalPending.toFixed(2)}`;
            }
        } catch (error) {
            console.error('Error refreshing pending orders:', error);
            this.showToast('Error refreshing pending orders', 'error');
        }
    }

    async loadSalesReport(startDate, endDate) {
        try {
            const response = await fetch(`?ajax=sales_report_data&start_date=${startDate}&end_date=${endDate}`);
            const data = await response.json();
            const content = document.getElementById('sales-report-content');
            if (data.status === 'success' && data.data.length > 0) {
                content.innerHTML = `
                    <div class="table-container">
                        <table id="sales-report-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Order Count</th>
                                    <th>Total Sales</th>
                                    <th>Payment Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.data.map(report => `
                                    <tr>
                                        <td>${new Date(report.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                                        <td>${report.order_count}</td>
                                        <td>MWK${Number(report.total_sales).toFixed(2)}</td>
                                        <td>${this.capitalizeWords(report.payment_method.replace('_', ' '))}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 4rem; color: var(--secondary-color); margin-bottom: 20px;"></i>
                        <h3>No Sales Data</h3>
                        <p>No sales data available for the selected date range.</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading sales report:', error);
            this.showToast('Error loading sales report', 'error');
        }
    }

    showSection(sectionId) {
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        document.getElementById(`${sectionId}-section`).classList.add('active');
        document.querySelector(`.nav-link[href="#${sectionId}"]`).classList.add('active');
        if (sectionId === 'pending-orders') {
            this.refreshPendingOrdersData();
        } else if (sectionId === 'completed-orders') {
            this.refreshCompletedOrdersData();
        } else if (sectionId === 'sales-report') {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            this.loadSalesReport(startDate, endDate);
        }
    }

    async refreshCompletedOrdersData() {
        try {
            const response = await fetch('?ajax=completed_orders_data');
            const data = await response.json();
            if (data.status === 'success') {
                const tableBody = document.querySelector('#completed-orders-table tbody');
                tableBody.innerHTML = '';
                if (data.data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No completed orders.</td></tr>';
                } else {
                    data.data.forEach(order => {
                        const tr = document.createElement('tr');
                        tr.dataset.orderId = order.order_id;
                        tr.innerHTML = `
                            <td><strong>#${order.order_id}</strong></td>
                            <td>${order.first_name || ''} ${order.last_name || ''}</td>
                            <td><strong class="order-amount">MWK${Number(order.total).toFixed(2)}</strong></td>
                            <td>
                                <span class="payment-method ${order.payment_method}">
                                    <i class="${this.getPaymentIcon(order.payment_method)}"></i>
                                    ${this.capitalizeWords(order.payment_method.replace('_', ' '))}
                                </span>
                            </td>
                            <td>
                                ${new Date(order.updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}<br>
                                <small>${new Date(order.updated_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</small>
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
                        `;
                        tableBody.appendChild(tr);
                    });
                }
                document.getElementById('completed-count').textContent = data.data.length;
                const totalCompleted = data.data.reduce((sum, order) => sum + parseFloat(order.total), 0);
                document.getElementById('total-completed-amount').textContent = totalCompleted.toFixed(2);
            }
        } catch (error) {
            console.error('Error refreshing completed orders:', error);
            this.showToast('Error refreshing completed orders', 'error');
        }
    }

    getPaymentIcon(paymentMethod) {
        const icons = {
            'cash': 'fas fa-money-bill-wave',
            'mpamba': 'fas fa-mobile-alt',
            'airtel_money': 'fas fa-mobile-alt',
            'card': 'fas fa-credit-card',
            'Mobile Transfer': 'fas fa-mobile-alt'
        };
        return icons[paymentMethod] || 'fas fa-question';
    }

    capitalizeWords(str) {
        return str.replace(/\b\w/g, c => c.toUpperCase());
    }

    showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.getElementById('notification-container').appendChild(toast);
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new CashierDashboard();
    const urlParams = new URLSearchParams(window.location.search);
    const section = urlParams.get('section') || 'dashboard';
    dashboard.showSection(section);
    const salesReportForm = document.getElementById('sales-report-form');
    if (salesReportForm) {
        salesReportForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            await dashboard.loadSalesReport(startDate, endDate);
        });
    }
    const downloadCsvBtn = document.getElementById('download-csv-btn');
    if (downloadCsvBtn) {
        downloadCsvBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            window.location.href = `?ajax=sales_report_data&start_date=${startDate}&end_date=${endDate}&download=1`;
        });
    }
    if (section === 'sales-report') {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        dashboard.loadSalesReport(startDate, endDate);
    }
    document.querySelectorAll('.confirm-correction-form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('ajax', 'confirm_correction');
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.status === 'success') {
                    dashboard.showToast(data.message, 'success');
                    const orderRow = form.closest('tr');
                    if (orderRow) {
                        orderRow.remove();
                        const pendingCountEl = document.getElementById('pending-count');
                        if (pendingCountEl) {
                            pendingCountEl.textContent = parseInt(pendingCountEl.textContent) - 1;
                        }
                        dashboard.refreshPendingOrdersData();
                    }
                    dashboard.refreshStats();
                    dashboard.showSection('completed-orders'); // Switch to completed orders section
                } else {
                    dashboard.showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Error confirming correction:', error);
                dashboard.showToast('Error confirming correction', 'error');
            }
        });
    });
});
</script>
</body>
</html>
<?php
$conn->close();
?>