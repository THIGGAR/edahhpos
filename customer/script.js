// Modern POS System JavaScript - Enhanced Performance & Features
document.addEventListener('DOMContentLoaded', () => {
    // Performance optimization: Use requestAnimationFrame for smooth animations
    let animationFrame;
    
    // Debounce function for performance optimization
    function debounce(func, wait) {
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

    // Throttle function for scroll events
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    }

    // Initialize theme system
    initializeTheme();
    
    // Initialize sidebar functionality
    initializeSidebar();
    
    // Initialize search functionality
    initializeSearch();
    
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize modal system
    initializeModals();
    
    // Initialize product filtering and sorting
    initializeProductFilters();
    
    // Initialize cart functionality
    initializeCart();
    
    // Initialize animations and interactions
    initializeAnimations();
    
    // Initialize performance monitoring
    initializePerformanceMonitoring();

    // Theme System
    function initializeTheme() {
        const themeToggle = document.createElement('button');
        themeToggle.className = 'theme-toggle';
        themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        themeToggle.setAttribute('aria-label', 'Toggle dark mode');
        themeToggle.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            backdrop-filter: var(--backdrop-blur);
            color: var(--text-color);
        `;
        
        document.body.appendChild(themeToggle);
        
        // Load saved theme
        const savedTheme = localStorage.getItem('pos-theme') || 'light';
        if (savedTheme === 'dark') {
            document.body.classList.add('dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark');
            const isDark = document.body.classList.contains('dark');
            themeToggle.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            localStorage.setItem('pos-theme', isDark ? 'dark' : 'light');
            
            // Animate theme transition
            themeToggle.style.transform = 'rotate(360deg) scale(1.2)';
            setTimeout(() => {
                themeToggle.style.transform = 'rotate(0deg) scale(1)';
            }, 300);
        });
    }

    // Sidebar System
    function initializeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const mainContent = document.querySelector('.main-content');
        
        if (mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                mobileToggle.classList.toggle('active');
                
                // Add overlay for mobile
                if (sidebar.classList.contains('open')) {
                    const overlay = document.createElement('div');
                    overlay.className = 'sidebar-overlay';
                    overlay.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.5);
                        z-index: 998;
                        backdrop-filter: blur(5px);
                    `;
                    document.body.appendChild(overlay);
                    
                    overlay.addEventListener('click', () => {
                        sidebar.classList.remove('open');
                        mobileToggle.classList.remove('active');
                        overlay.remove();
                    });
                } else {
                    const overlay = document.querySelector('.sidebar-overlay');
                    if (overlay) overlay.remove();
                }
            });
        }
        
        // Add active state management for sidebar links
        const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                // Add ripple effect
                createRippleEffect(e, link);
                
                // Close mobile sidebar
                if (window.innerWidth <= 768) {
                    setTimeout(() => {
                        sidebar.classList.remove('open');
                        mobileToggle.classList.remove('active');
                        const overlay = document.querySelector('.sidebar-overlay');
                        if (overlay) overlay.remove();
                    }, 200);
                }
            });
        });
    }

    // Enhanced Search System
    function initializeSearch() {
        const searchForm = document.querySelector('.search-bar form');
        const searchInput = searchForm?.querySelector('input[name="search"]');
        
        if (searchForm && searchInput) {
            // Add search suggestions
            const suggestionsContainer = document.createElement('div');
            suggestionsContainer.className = 'search-suggestions';
            suggestionsContainer.style.cssText = `
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--glass-bg);
                backdrop-filter: var(--backdrop-blur);
                border: 1px solid var(--glass-border);
                border-radius: var(--border-radius);
                max-height: 200px;
                overflow-y: auto;
                z-index: 1000;
                display: none;
            `;
            searchForm.style.position = 'relative';
            searchForm.appendChild(suggestionsContainer);
            
            // Debounced search suggestions
            const debouncedSearch = debounce((query) => {
                if (query.length > 2) {
                    fetchSearchSuggestions(query, suggestionsContainer);
                } else {
                    suggestionsContainer.style.display = 'none';
                }
            }, 300);
            
            searchInput.addEventListener('input', (e) => {
                debouncedSearch(e.target.value);
            });
            
            searchInput.addEventListener('focus', () => {
                if (searchInput.value.length > 2) {
                    suggestionsContainer.style.display = 'block';
                }
            });
            
            document.addEventListener('click', (e) => {
                if (!searchForm.contains(e.target)) {
                    suggestionsContainer.style.display = 'none';
                }
            });
            
            // Form validation with enhanced UX
            searchForm.addEventListener('submit', (e) => {
                const query = searchInput.value.trim();
                if (!query) {
                    e.preventDefault();
                    showToast('Please enter a search query.', 'error');
                    searchInput.focus();
                    searchInput.style.animation = 'shake 0.5s ease-in-out';
                    setTimeout(() => {
                        searchInput.style.animation = '';
                    }, 500);
                }
            });
        }
    }

    // Search suggestions fetcher
    function fetchSearchSuggestions(query, container) {
        // Simulate API call - replace with actual endpoint
        const suggestions = [
            'Electronics', 'Clothing', 'Books', 'Home & Garden', 
            'Sports', 'Beauty', 'Automotive', 'Toys'
        ].filter(item => item.toLowerCase().includes(query.toLowerCase()));
        
        if (suggestions.length > 0) {
            container.innerHTML = suggestions.map(suggestion => 
                `<div class="suggestion-item" style="padding: 10px; cursor: pointer; transition: var(--transition);">${suggestion}</div>`
            ).join('');
            container.style.display = 'block';
            
            // Add click handlers
            container.querySelectorAll('.suggestion-item').forEach(item => {
                item.addEventListener('click', () => {
                    document.querySelector('.search-bar input').value = item.textContent;
                    container.style.display = 'none';
                });
                
                item.addEventListener('mouseenter', () => {
                    item.style.background = 'var(--primary-color)';
                    item.style.color = 'var(--white)';
                });
                
                item.addEventListener('mouseleave', () => {
                    item.style.background = 'transparent';
                    item.style.color = 'var(--text-color)';
                });
            });
        } else {
            container.style.display = 'none';
        }
    }

    // Enhanced Form Validation
    function initializeFormValidation() {
        // Quantity input validation with real-time feedback
        const quantityInputs = document.querySelectorAll('input[name="quantity"]');
        quantityInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                const value = parseInt(e.target.value);
                if (value < 0) {
                    e.target.value = 0;
                    showToast('Quantity cannot be negative.', 'error');
                } else if (value > 100) {
                    e.target.value = 100;
                    showToast('Maximum quantity is 100.', 'warning');
                }
                
                // Add visual feedback
                if (value > 0) {
                    e.target.style.borderColor = 'var(--success-color)';
                } else {
                    e.target.style.borderColor = 'var(--border-color)';
                }
            });
            
            // Add increment/decrement buttons
            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'display: flex; align-items: center; gap: 5px;';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            
            const decrementBtn = document.createElement('button');
            decrementBtn.innerHTML = '<i class="fas fa-minus"></i>';
            decrementBtn.type = 'button';
            decrementBtn.className = 'quantity-btn';
            decrementBtn.style.cssText = `
                background: var(--secondary-color);
                color: white;
                border: none;
                border-radius: 50%;
                width: 30px;
                height: 30px;
                cursor: pointer;
                transition: var(--transition);
            `;
            
            const incrementBtn = decrementBtn.cloneNode(true);
            incrementBtn.innerHTML = '<i class="fas fa-plus"></i>';
            incrementBtn.style.background = 'var(--primary-color)';
            
            wrapper.insertBefore(decrementBtn, input);
            wrapper.appendChild(incrementBtn);
            
            decrementBtn.addEventListener('click', () => {
                const currentValue = parseInt(input.value) || 0;
                if (currentValue > 0) {
                    input.value = currentValue - 1;
                    input.dispatchEvent(new Event('input'));
                }
            });
            
            incrementBtn.addEventListener('click', () => {
                const currentValue = parseInt(input.value) || 0;
                if (currentValue < 100) {
                    input.value = currentValue + 1;
                    input.dispatchEvent(new Event('input'));
                }
            });
        });
        
        // Profile form validation
        const profileForm = document.querySelector('.profile-section form');
        if (profileForm) {
            const inputs = profileForm.querySelectorAll('input[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', validateInput);
                input.addEventListener('input', clearValidationError);
            });
        }
    }

    function validateInput(e) {
        const input = e.target;
        const value = input.value.trim();
        
        if (!value && input.required) {
            showInputError(input, 'This field is required');
        } else if (input.type === 'email' && value && !isValidEmail(value)) {
            showInputError(input, 'Please enter a valid email address');
        } else if (input.type === 'tel' && value && !isValidPhone(value)) {
            showInputError(input, 'Please enter a valid phone number');
        } else {
            clearInputError(input);
        }
    }

    function showInputError(input, message) {
        clearInputError(input);
        input.style.borderColor = 'var(--danger-color)';
        const errorDiv = document.createElement('div');
        errorDiv.className = 'input-error';
        errorDiv.textContent = message;
        errorDiv.style.cssText = `
            color: var(--danger-color);
            font-size: 0.8rem;
            margin-top: 5px;
            animation: fadeInUp 0.3s ease;
        `;
        input.parentNode.appendChild(errorDiv);
    }

    function clearInputError(input) {
        input.style.borderColor = 'var(--border-color)';
        const errorDiv = input.parentNode.querySelector('.input-error');
        if (errorDiv) errorDiv.remove();
    }

    function clearValidationError(e) {
        clearInputError(e.target);
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isValidPhone(phone) {
        return /^[\+]?[1-9][\d]{0,15}$/.test(phone.replace(/\s/g, ''));
    }

    // Enhanced Modal System
    function initializeModals() {
        const viewDetailsButtons = document.querySelectorAll('.view-details');
        const modal = document.getElementById('order-details-modal');
        const modalContent = document.getElementById('order-details-content');
        const closeModal = document.querySelector('.modal .close');

        viewDetailsButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                createRippleEffect(e, button);
                const orderId = button.getAttribute('data-order-id');
                fetchOrderDetails(orderId);
            });
        });

        if (closeModal) {
            closeModal.addEventListener('click', () => {
                closeModalWithAnimation(modal);
            });
        }

        // Enhanced modal backdrop click
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModalWithAnimation(modal);
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal && modal.style.display === 'block') {
                closeModalWithAnimation(modal);
            }
        });
    }

    function closeModalWithAnimation(modal) {
        modal.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => {
            modal.style.display = 'none';
            modal.style.animation = '';
        }, 300);
    }

    function fetchOrderDetails(orderId) {
        // Show loading state
        const modal = document.getElementById('order-details-modal');
        const modalContent = document.getElementById('order-details-content');
        
        modalContent.innerHTML = `
            <div class="loading-spinner" style="text-align: center; padding: 40px;">
                <div style="
                    width: 40px;
                    height: 40px;
                    border: 4px solid var(--border-color);
                    border-top: 4px solid var(--primary-color);
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 20px;
                "></div>
                <p>Loading order details...</p>
            </div>
        `;
        modal.style.display = 'block';
        
        fetch(`fetch_order_details.php?order_id=${orderId}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.error) {
                showToast(data.error, 'error');
                closeModalWithAnimation(modal);
                return;
            }
            
            modalContent.innerHTML = `
                <div class="order-details-header" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--border-color);">
                    <h2 style="color: var(--primary-color); margin-bottom: 10px;">Order Details</h2>
                    <div class="order-meta" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div><strong>Order ID:</strong> ${data.order_id}</div>
                        <div><strong>Transaction Ref:</strong> ${data.transaction_ref}</div>
                        <div><strong>Total:</strong> <span style="color: var(--primary-color); font-size: 1.2em;">K${parseFloat(data.amount).toFixed(2)}</span></div>
                        <div><strong>Status:</strong> <span class="status-badge">${data.status}</span></div>
                        <div><strong>Date:</strong> ${new Date(data.created_at).toLocaleDateString()}</div>
                    </div>
                </div>
                <div class="order-items">
                    <h3 style="margin-bottom: 15px; color: var(--dark-color);">Items Ordered:</h3>
                    <div class="items-grid" style="display: grid; gap: 10px;">
                        ${data.items.map(item => `
                            <div class="item-row" style="
                                display: flex;
                                align-items: center;
                                gap: 15px;
                                padding: 15px;
                                background: var(--glass-bg);
                                border-radius: var(--border-radius);
                                backdrop-filter: var(--backdrop-blur);
                                transition: var(--transition);
                            ">
                                <img src="${item.image || 'https://via.placeholder.com/60'}" 
                                     alt="${item.name}" 
                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 5px 0; color: var(--dark-color);">${item.name}</h4>
                                    <p style="margin: 0; color: var(--secondary-color);">Quantity: ${item.quantity}</p>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: bold; color: var(--primary-color);">K${parseFloat(item.price * item.quantity).toFixed(2)}</div>
                                    <div style="font-size: 0.9em; color: var(--secondary-color);">K${parseFloat(item.price).toFixed(2)} each</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            // Add hover effects to item rows
            modalContent.querySelectorAll('.item-row').forEach(row => {
                row.addEventListener('mouseenter', () => {
                    row.style.transform = 'translateX(5px)';
                    row.style.boxShadow = 'var(--shadow)';
                });
                row.addEventListener('mouseleave', () => {
                    row.style.transform = 'translateX(0)';
                    row.style.boxShadow = 'none';
                });
            });
        })
        .catch(error => {
            console.error('Error fetching order details:', error);
            showToast('Failed to load order details. Please try again.', 'error');
            closeModalWithAnimation(modal);
        });
    }

    // Enhanced Product Filtering and Sorting
    function initializeProductFilters() {
        // Category filtering with animation
        const categoryFilters = document.querySelectorAll('.category-filter');
        categoryFilters.forEach(button => {
            button.addEventListener('click', (e) => {
                createRippleEffect(e, button);
                
                categoryFilters.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                
                const category = button.dataset.category;
                filterProducts(category);
            });
        });

        // Enhanced sorting with animation
        const sortSelect = document.getElementById('sortProducts');
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                const value = sortSelect.value;
                sortProducts(value);
            });
        }
    }

    function filterProducts(category) {
        const productCards = document.querySelectorAll('.product-card');
        
        productCards.forEach((card, index) => {
            const shouldShow = category === 'all' || card.dataset.category === category;
            
            if (shouldShow) {
                card.style.animation = `fadeInUp 0.5s ease ${index * 0.1}s both`;
                card.style.display = 'block';
            } else {
                card.style.animation = 'fadeOut 0.3s ease both';
                setTimeout(() => {
                    card.style.display = 'none';
                }, 300);
            }
        });
    }

    function sortProducts(sortType) {
        const container = document.querySelector('.products-grid');
        if (!container) return;
        
        const cards = Array.from(container.querySelectorAll('.product-card'));
        
        // Add loading state
        container.style.opacity = '0.7';
        container.style.pointerEvents = 'none';
        
        setTimeout(() => {
            cards.sort((a, b) => {
                const nameA = a.querySelector('h3').textContent.toLowerCase();
                const nameB = b.querySelector('h3').textContent.toLowerCase();
                const priceA = parseFloat(a.querySelector('.price').textContent.replace('K', ''));
                const priceB = parseFloat(b.querySelector('.price').textContent.replace('K', ''));
                
                switch (sortType) {
                    case 'name_asc': return nameA.localeCompare(nameB);
                    case 'name_desc': return nameB.localeCompare(nameA);
                    case 'price_asc': return priceA - priceB;
                    case 'price_desc': return priceB - priceA;
                    default: return 0;
                }
            });
            
            // Re-append sorted cards with stagger animation
            cards.forEach((card, index) => {
                card.style.animation = `fadeInUp 0.4s ease ${index * 0.05}s both`;
                container.appendChild(card);
            });
            
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
        }, 200);
    }

    // Enhanced Cart Functionality
    function initializeCart() {
        // Add to cart buttons with enhanced feedback
        const addToCartButtons = document.querySelectorAll('.add-to-cart');
        addToCartButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                createRippleEffect(e, button);
                
                // Visual feedback
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                button.disabled = true;
                
                setTimeout(() => {
                    button.innerHTML = '<i class="fas fa-check"></i> Added!';
                    button.style.background = 'var(--success-color)';
                    
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.style.background = '';
                        button.disabled = false;
                    }, 1500);
                }, 500);
            });
        });
        
        // Buy now buttons with enhanced animation
        const buyNowButtons = document.querySelectorAll('.buy-now');
        buyNowButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                createRippleEffect(e, button);
                
                // Add loading animation
                button.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    button.style.transform = 'scale(1)';
                }, 150);
            });
        });
    }

    // Animation and Interaction System
    function initializeAnimations() {
        // Intersection Observer for scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.6s ease both';
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        // Observe elements for scroll animations
        document.querySelectorAll('.product-card, .promotion-card, .summary-card, section').forEach(el => {
            observer.observe(el);
        });
        
        // Parallax effect for background images
        const parallaxElements = document.querySelectorAll('[class*="::after"]');
        const handleParallax = throttle(() => {
            const scrolled = window.pageYOffset;
            parallaxElements.forEach(el => {
                const rate = scrolled * -0.5;
                el.style.transform = `translateY(${rate}px)`;
            });
        }, 16);
        
        window.addEventListener('scroll', handleParallax);
        
        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; transform: scale(1); }
                to { opacity: 0; transform: scale(0.9); }
            }
            
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
            
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            }
        `;
        document.head.appendChild(style);
    }

    // Ripple Effect Function
    function createRippleEffect(event, element) {
        const circle = document.createElement('span');
        const diameter = Math.max(element.clientWidth, element.clientHeight);
        const radius = diameter / 2;
        
        const rect = element.getBoundingClientRect();
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - rect.left - radius}px`;
        circle.style.top = `${event.clientY - rect.top - radius}px`;
        circle.classList.add('ripple');
        
        const ripple = element.getElementsByClassName('ripple')[0];
        if (ripple) ripple.remove();
        
        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(circle);
        
        setTimeout(() => circle.remove(), 600);
    }

    // Enhanced Toast System
    function showToast(message, type = 'success', duration = 3000) {
        const toast = document.getElementById('toast') || createToastContainer();
        
        const toastElement = document.createElement('div');
        toastElement.className = `toast-item toast-${type}`;
        toastElement.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-${getToastIcon(type)}"></i>
                <span>${message}</span>
                <button class="toast-close" style="
                    background: none;
                    border: none;
                    color: inherit;
                    cursor: pointer;
                    font-size: 1.2em;
                    margin-left: auto;
                ">&times;</button>
            </div>
        `;
        
        toastElement.style.cssText = `
            background: linear-gradient(135deg, var(--${type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'success'}-color), ${type === 'error' ? '#c82333' : type === 'warning' ? '#e6b800' : '#218838'});
            color: ${type === 'warning' ? 'var(--dark-color)' : 'var(--white)'};
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 10px;
            box-shadow: var(--shadow);
            backdrop-filter: var(--backdrop-blur);
            animation: slideInRight 0.5s ease;
            max-width: 350px;
            word-wrap: break-word;
        `;
        
        toast.appendChild(toastElement);
        
        const closeBtn = toastElement.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => removeToast(toastElement));
        
        setTimeout(() => removeToast(toastElement), duration);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            display: flex;
            flex-direction: column;
        `;
        document.body.appendChild(container);
        return container;
    }

    function removeToast(toastElement) {
        toastElement.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (toastElement.parentNode) {
                toastElement.parentNode.removeChild(toastElement);
            }
        }, 300);
    }

    function getToastIcon(type) {
        switch (type) {
            case 'success': return 'check-circle';
            case 'error': return 'exclamation-circle';
            case 'warning': return 'exclamation-triangle';
            case 'info': return 'info-circle';
            default: return 'check-circle';
        }
    }

    // Performance Monitoring
    function initializePerformanceMonitoring() {
        // Monitor page load performance
        window.addEventListener('load', () => {
            const perfData = performance.getEntriesByType('navigation')[0];
            const loadTime = perfData.loadEventEnd - perfData.loadEventStart;
            
            if (loadTime > 3000) {
                console.warn('Page load time is slow:', loadTime + 'ms');
            }
        });
        
        // Monitor memory usage (if available)
        if ('memory' in performance) {
            setInterval(() => {
                const memory = performance.memory;
                if (memory.usedJSHeapSize > memory.jsHeapSizeLimit * 0.9) {
                    console.warn('High memory usage detected');
                }
            }, 30000);
        }
        
        // Lazy loading for images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            imageObserver.unobserve(img);
                        }
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    // Add slide-out animation for toasts
    const slideOutStyle = document.createElement('style');
    slideOutStyle.textContent = `
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(slideOutStyle);

    // Global error handler
    window.addEventListener('error', (e) => {
        console.error('Global error:', e.error);
        showToast('An unexpected error occurred. Please refresh the page.', 'error');
    });

    // Service worker registration for PWA capabilities
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(err => {
            console.log('Service worker registration failed:', err);
        });
    }
});

