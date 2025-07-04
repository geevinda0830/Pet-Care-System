/**
 * Enhanced Cart Management System
 * Handles all cart operations with improved error handling and user feedback
 */

// Global cart manager object
window.CartManager = {
    
    /**
     * Initialize cart functionality
     */
    init: function() {
        this.updateCartCount();
        this.bindEvents();
        console.log('CartManager initialized');
    },

    /**
     * Check if user is logged in
     */
    isUserLoggedIn: function() {
        return window.userLoggedIn === true;
    },

    /**
     * Add item to cart - Universal function for all pages
     * @param {number} productId - The product ID to add
     * @param {number} quantity - The quantity to add (optional, defaults to 1)
     * @param {HTMLElement} buttonElement - The button that was clicked (optional)
     */
    addToCart: function(productId, quantity = 1, buttonElement = null) {
        console.log('CartManager.addToCart called:', { productId, quantity, buttonElement });
        
        // Check if user is logged in
        if (!this.isUserLoggedIn()) {
            this.showMessage('Please login to add items to cart', 'error');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
            return;
        }

        // Validate inputs
        if (!productId || productId <= 0) {
            this.showMessage('Invalid product selected', 'error');
            return;
        }

        // Get quantity from input if it exists and no quantity provided
        if (quantity === 1) {
            const quantityInput = document.getElementById('quantity');
            if (quantityInput) {
                quantity = parseInt(quantityInput.value) || 1;
            }
        }

        // Ensure minimum quantity
        quantity = Math.max(1, parseInt(quantity));

        // Handle button loading state
        let originalButtonHTML = '';
        if (buttonElement) {
            originalButtonHTML = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            buttonElement.disabled = true;
        } else {
            // Try to find the button by event target
            if (window.event && window.event.target) {
                buttonElement = window.event.target.closest('button') || window.event.target;
                if (buttonElement && buttonElement.tagName === 'BUTTON') {
                    originalButtonHTML = buttonElement.innerHTML;
                    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                    buttonElement.disabled = true;
                }
            }
        }

        // Reset button function
        const resetButton = () => {
            if (buttonElement && originalButtonHTML) {
                buttonElement.innerHTML = originalButtonHTML;
                buttonElement.disabled = false;
            }
        };

        // Create form data
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('quantity', quantity);

        // Send request to cart_process.php
        fetch('cart_process.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed response:', data);
                
                if (data.success) {
                    this.showMessage(data.message || 'Product added to cart successfully!', 'success');
                    this.updateCartCount();
                    
                    // Trigger custom event for other scripts
                    this.triggerCartUpdate(data);
                    
                } else {
                    this.showMessage(data.message || 'Failed to add product to cart', 'error');
                    
                    // Handle redirect if needed
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    }
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Raw text:', text);
                this.showMessage('Server response error. Please try again.', 'error');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            this.showMessage('Network error. Please check your connection and try again.', 'error');
        })
        .finally(() => {
            resetButton();
        });
    },

    /**
     * Remove item from cart
     * @param {number} cartItemId - The cart item ID to remove
     * @param {HTMLElement} buttonElement - The button that was clicked (optional)
     */
    removeFromCart: function(cartItemId, buttonElement = null) {
        if (!this.isUserLoggedIn()) {
            this.showMessage('Please login to manage your cart', 'error');
            return;
        }

        if (!cartItemId || cartItemId <= 0) {
            this.showMessage('Invalid item selected', 'error');
            return;
        }

        // Handle button loading state
        let originalButtonHTML = '';
        if (buttonElement) {
            originalButtonHTML = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            buttonElement.disabled = true;
        }

        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('cart_item_id', cartItemId);

        fetch('cart_process.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showMessage(data.message || 'Item removed from cart', 'success');
                this.updateCartCount();
                
                // Remove the item from the page
                const itemElement = buttonElement ? buttonElement.closest('.cart-item, .cart-item-modern') : null;
                if (itemElement) {
                    itemElement.style.transition = 'opacity 0.3s ease';
                    itemElement.style.opacity = '0';
                    setTimeout(() => {
                        itemElement.remove();
                        this.checkEmptyCart();
                    }, 300);
                } else {
                    // Reload the page if we can't find the item element
                    setTimeout(() => location.reload(), 1000);
                }
                
                this.triggerCartUpdate(data);
                
            } else {
                this.showMessage(data.message || 'Failed to remove item', 'error');
            }
        })
        .catch(error => {
            console.error('Remove from cart error:', error);
            this.showMessage('Error removing item. Please try again.', 'error');
        })
        .finally(() => {
            if (buttonElement && originalButtonHTML) {
                buttonElement.innerHTML = originalButtonHTML;
                buttonElement.disabled = false;
            }
        });
    },

    /**
     * Update item quantity in cart
     * @param {number} cartItemId - The cart item ID
     * @param {number} quantity - New quantity
     * @param {HTMLElement} inputElement - The input element (optional)
     */
    updateQuantity: function(cartItemId, quantity, inputElement = null) {
        if (!this.isUserLoggedIn()) {
            this.showMessage('Please login to manage your cart', 'error');
            return;
        }

        quantity = Math.max(1, parseInt(quantity));

        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('cart_item_id', cartItemId);
        formData.append('quantity', quantity);

        fetch('cart_process.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateCartCount();
                this.triggerCartUpdate(data);
                
                // Update the item total if element exists
                const itemElement = inputElement ? inputElement.closest('.cart-item, .cart-item-modern') : null;
                if (itemElement) {
                    const totalElement = itemElement.querySelector('.item-total, .total-price');
                    if (totalElement && data.item_total) {
                        totalElement.textContent = '₱' + parseFloat(data.item_total).toFixed(2);
                    }
                }
                
            } else {
                this.showMessage(data.message || 'Failed to update quantity', 'error');
                // Reset input to original value if available
                if (inputElement && data.original_quantity) {
                    inputElement.value = data.original_quantity;
                }
            }
        })
        .catch(error => {
            console.error('Update quantity error:', error);
            this.showMessage('Error updating quantity. Please try again.', 'error');
        });
    },

    /**
     * Clear entire cart
     */
    clearCart: function() {
        if (!this.isUserLoggedIn()) {
            this.showMessage('Please login to manage your cart', 'error');
            return;
        }

        if (!confirm('Are you sure you want to clear all items from your cart?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'clear');

        fetch('cart_process.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showMessage(data.message || 'Cart cleared successfully', 'success');
                this.updateCartCount();
                setTimeout(() => location.reload(), 1000);
            } else {
                this.showMessage(data.message || 'Failed to clear cart', 'error');
            }
        })
        .catch(error => {
            console.error('Clear cart error:', error);
            this.showMessage('Error clearing cart. Please try again.', 'error');
        });
    },

    /**
     * Update cart count display
     */
    updateCartCount: function() {
        if (!this.isUserLoggedIn()) {
            return;
        }

        fetch('get_cart_count.php', {
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && typeof data.count !== 'undefined') {
                this.updateCartCountElements(data.count);
            }
        })
        .catch(error => {
            console.error('Error updating cart count:', error);
        });
    },

    /**
     * Update all cart count elements on the page
     * @param {number} count - Cart item count
     */
    updateCartCountElements: function(count) {
        const cartCountSelectors = [
            '.cart-count',
            '#cart-count',
            '.cart-badge',
            '[data-cart-count]',
            '.badge-cart'
        ];

        cartCountSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                element.textContent = count;
                element.style.display = count > 0 ? 'inline' : 'none';
                
                // Add animation for count change
                element.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 200);
            });
        });
    },

    /**
     * Enhanced message display function
     * @param {string} message - Message to display
     * @param {string} type - Message type (success, error, info)
     */
    showMessage: function(message, type = 'info') {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.cart-message, .message');
        existingMessages.forEach(msg => msg.remove());

        // Create new message element
        const messageElement = document.createElement('div');
        messageElement.className = `message cart-message ${type}`;
        
        const iconClass = type === 'success' ? 'fa-check-circle' : 
                         type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
        
        messageElement.innerHTML = `
            <i class="fas ${iconClass}"></i>
            <span>${message}</span>
            <button class="message-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        // Add styles if not already present
        if (!document.querySelector('#cart-message-styles')) {
            const styles = document.createElement('style');
            styles.id = 'cart-message-styles';
            styles.textContent = `
                .message {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 25px;
                    border-radius: 8px;
                    color: white;
                    font-weight: 600;
                    z-index: 1000;
                    transform: translateX(400px);
                    transition: transform 0.3s ease;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    max-width: 400px;
                }
                .message.show { transform: translateX(0); }
                .message.success { background: #10b981; }
                .message.error { background: #ef4444; }
                .message.info { background: #3b82f6; }
                .message-close {
                    background: none;
                    border: none;
                    color: white;
                    cursor: pointer;
                    font-size: 14px;
                    margin-left: auto;
                    opacity: 0.8;
                }
                .message-close:hover { opacity: 1; }
            `;
            document.head.appendChild(styles);
        }

        // Add to page
        document.body.appendChild(messageElement);

        // Show message with animation
        setTimeout(() => {
            messageElement.classList.add('show');
        }, 100);

        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (messageElement.parentNode) {
                messageElement.classList.remove('show');
                setTimeout(() => {
                    if (messageElement.parentNode) {
                        messageElement.remove();
                    }
                }, 300);
            }
        }, 5000);
    },

    /**
     * Check if cart is empty and show appropriate message
     */
    checkEmptyCart: function() {
        const cartItems = document.querySelectorAll('.cart-item, .cart-item-modern');
        if (cartItems.length === 0) {
            const cartContainer = document.querySelector('.cart-items, .cart-container');
            if (cartContainer) {
                cartContainer.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your cart is empty</h3>
                        <p>Add some products to get started!</p>
                        <a href="shop.php" class="btn btn-primary">Continue Shopping</a>
                    </div>
                `;
            }
        }
    },

    /**
     * Trigger custom cart update event
     * @param {object} data - Response data from server
     */
    triggerCartUpdate: function(data) {
        const event = new CustomEvent('cartUpdated', {
            detail: data
        });
        document.dispatchEvent(event);
    },

    /**
     * Bind event listeners
     */
    bindEvents: function() {
        // Bind quantity input changes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('quantity-input')) {
                const cartItemId = e.target.dataset.cartItemId;
                const quantity = e.target.value;
                if (cartItemId) {
                    this.updateQuantity(cartItemId, quantity, e.target);
                }
            }
        });

        // Bind remove buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-remove-item') || 
                e.target.closest('.btn-remove-item')) {
                e.preventDefault();
                const button = e.target.classList.contains('btn-remove-item') ? 
                              e.target : e.target.closest('.btn-remove-item');
                const cartItemId = button.dataset.cartItemId;
                if (cartItemId) {
                    this.removeFromCart(cartItemId, button);
                }
            }
        });

        // Listen for cart update events
        document.addEventListener('cartUpdated', (e) => {
            console.log('Cart updated:', e.detail);
        });
    }
};

// Global functions for backward compatibility
window.addToCart = function(productId, buttonElement) {
    return CartManager.addToCart(productId, 1, buttonElement);
};

window.removeFromCart = function(cartItemId, buttonElement) {
    return CartManager.removeFromCart(cartItemId, buttonElement);
};

window.updateCartQuantity = function(cartItemId, quantity, inputElement) {
    return CartManager.updateQuantity(cartItemId, quantity, inputElement);
};

window.clearCart = function() {
    return CartManager.clearCart();
};

window.updateCartCount = function() {
    return CartManager.updateCartCount();
};

// Initialize cart manager when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    CartManager.init();
});

// Initialize cart manager immediately if DOM is already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        CartManager.init();
    });
} else {
    CartManager.init();
}