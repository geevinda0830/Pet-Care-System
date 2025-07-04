/**
 * Complete Add to Cart Functionality
 * This script should be included in all pages that need cart functionality
 */

// Global cart functions
window.CartManager = {
    
    /**
     * Add item to cart - Universal function for all pages
     * @param {number} productId - The product ID to add
     * @param {number} quantity - The quantity to add (optional, defaults to 1)
     * @param {HTMLElement} buttonElement - The button that was clicked (optional)
     */
    addToCart: function(productId, quantity = 1, buttonElement = null) {
        // Check if user is logged in (check both possible session variables)
        const isLoggedIn = document.body.dataset.userLoggedIn === 'true';
        
        if (!isLoggedIn) {
            this.showMessage('Please login to add items to cart', 'error');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
            return;
        }

        // Get quantity from input if it exists and no quantity provided
        if (quantity === 1) {
            const quantityInput = document.getElementById('quantity');
            if (quantityInput) {
                quantity = parseInt(quantityInput.value) || 1;
            }
        }

        // Handle button loading state
        let originalButtonHTML = '';
        if (buttonElement) {
            originalButtonHTML = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            buttonElement.disabled = true;
        } else {
            // Try to find the button by event target
            if (event && event.target) {
                buttonElement = event.target.closest('button') || event.target;
                if (buttonElement) {
                    originalButtonHTML = buttonElement.innerHTML;
                    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                    buttonElement.disabled = true;
                }
            }
        }

        // Create form data
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('quantity', quantity);

        // Send request to cart_process.php
        fetch('cart_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                this.showMessage(data.message || 'Product added to cart successfully!', 'success');
                
                // Update cart count in header if element exists
                this.updateCartCount(data.cart_count);
                
                // Update cart total if on cart page
                if (data.cart_total !== undefined) {
                    this.updateCartTotal(data.cart_total);
                }
                
                // Trigger custom event for other components
                window.dispatchEvent(new CustomEvent('cartUpdated', { 
                    detail: { 
                        action: 'add', 
                        productId: productId, 
                        quantity: quantity,
                        cartCount: data.cart_count,
                        cartTotal: data.cart_total
                    } 
                }));
                
            } else {
                this.showMessage(data.message || 'Failed to add product to cart', 'error');
            }
        })
        .catch(error => {
            console.error('Add to cart error:', error);
            this.showMessage('An error occurred while adding the product to cart. Please try again.', 'error');
        })
        .finally(() => {
            // Restore button state
            if (buttonElement && originalButtonHTML) {
                buttonElement.innerHTML = originalButtonHTML;
                buttonElement.disabled = false;
            }
        });
    },

    /**
     * Remove item from cart
     * @param {number} cartItemId - The cart item ID to remove
     */
    removeFromCart: function(cartItemId) {
        if (!confirm('Are you sure you want to remove this item from your cart?')) {
            return;
        }

        // Create form data
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('cart_item_id', cartItemId);

        // Send request
        fetch('cart_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showMessage(data.message || 'Item removed from cart', 'success');
                
                // Reload page if on cart page, otherwise update count
                if (window.location.pathname.includes('cart.php')) {
                    location.reload();
                } else {
                    this.updateCartCount(data.cart_count);
                }
                
                // Trigger custom event
                window.dispatchEvent(new CustomEvent('cartUpdated', { 
                    detail: { 
                        action: 'remove', 
                        cartItemId: cartItemId,
                        cartCount: data.cart_count 
                    } 
                }));
                
            } else {
                this.showMessage(data.message || 'Failed to remove item from cart', 'error');
            }
        })
        .catch(error => {
            console.error('Remove from cart error:', error);
            this.showMessage('An error occurred while removing the item. Please try again.', 'error');
        });
    },

    /**
     * Update cart item quantity
     * @param {number} cartItemId - The cart item ID
     * @param {number} newQuantity - The new quantity
     */
    updateQuantity: function(cartItemId, newQuantity) {
        if (newQuantity < 1) {
            this.removeFromCart(cartItemId);
            return;
        }

        // Create form data
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('cart_item_id', cartItemId);
        formData.append('quantity', newQuantity);

        // Send request
        fetch('cart_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update cart count and total
                this.updateCartCount(data.cart_count);
                this.updateCartTotal(data.cart_total);
                
                // Trigger custom event
                window.dispatchEvent(new CustomEvent('cartUpdated', { 
                    detail: { 
                        action: 'update', 
                        cartItemId: cartItemId,
                        quantity: newQuantity,
                        cartCount: data.cart_count,
                        cartTotal: data.cart_total
                    } 
                }));
                
            } else {
                this.showMessage(data.message || 'Failed to update quantity', 'error');
                // Reload page to reset quantity
                location.reload();
            }
        })
        .catch(error => {
            console.error('Update quantity error:', error);
            this.showMessage('An error occurred while updating quantity', 'error');
            location.reload();
        });
    },

    /**
     * Update cart count display
     * @param {number} count - New cart count
     */
    updateCartCount: function(count) {
        const cartCountElements = document.querySelectorAll('.cart-count, [data-cart-count]');
        cartCountElements.forEach(element => {
            element.textContent = count || '0';
        });
    },

    /**
     * Update cart total display
     * @param {number} total - New cart total
     */
    updateCartTotal: function(total) {
        const cartTotalElements = document.querySelectorAll('.cart-total, [data-cart-total]');
        cartTotalElements.forEach(element => {
            element.textContent = `Rs.${parseFloat(total || 0).toFixed(2)}`;
        });
    },

    /**
     * Show message to user
     * @param {string} message - Message to show
     * @param {string} type - Type of message ('success', 'error', 'warning', 'info')
     */
    showMessage: function(message, type = 'info') {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.cart-message-toast');
        existingMessages.forEach(msg => msg.remove());

        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `cart-message-toast`;
        
        // Set styles based on type
        const styles = {
            success: { bg: '#d4edda', color: '#155724', border: '#c3e6cb', icon: 'check-circle' },
            error: { bg: '#f8d7da', color: '#721c24', border: '#f5c6cb', icon: 'exclamation-circle' },
            warning: { bg: '#fff3cd', color: '#856404', border: '#ffeaa7', icon: 'exclamation-triangle' },
            info: { bg: '#d1ecf1', color: '#0c5460', border: '#bee5eb', icon: 'info-circle' }
        };
        
        const style = styles[type] || styles.info;
        
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 320px;
            max-width: 500px;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: ${style.bg};
            color: ${style.color};
            border: 1px solid ${style.border};
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.4;
            animation: slideInRight 0.3s ease-out;
        `;
        
        messageDiv.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-${style.icon}" style="flex-shrink: 0;"></i>
                <span style="flex: 1;">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; color: inherit; cursor: pointer; font-size: 16px; padding: 0; margin-left: 10px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        // Add animation styles
        if (!document.querySelector('#cart-message-styles')) {
            const styleSheet = document.createElement('style');
            styleSheet.id = 'cart-message-styles';
            styleSheet.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(styleSheet);
        }

        // Add to page
        document.body.appendChild(messageDiv);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.remove();
                    }
                }, 300);
            }
        }, 5000);
    }
};

// Backward compatibility - Global functions
function addToCart(productId, quantity, buttonElement) {
    return CartManager.addToCart(productId, quantity, buttonElement);
}

function removeFromCart(cartItemId) {
    return CartManager.removeFromCart(cartItemId);
}

function updateCartQuantity(cartItemId, newQuantity) {
    return CartManager.updateQuantity(cartItemId, newQuantity);
}

function showMessage(message, type) {
    return CartManager.showMessage(message, type);
}

// Quantity control functions for product details page
function decreaseQty() {
    const input = document.getElementById('quantity');
    if (input) {
        const current = parseInt(input.value) || 1;
        if (current > 1) {
            input.value = current - 1;
        }
    }
}

function increaseQty() {
    const input = document.getElementById('quantity');
    if (input) {
        const max = parseInt(input.getAttribute('max')) || 999;
        const current = parseInt(input.value) || 1;
        if (current < max) {
            input.value = current + 1;
        }
    }
}

// Quantity input validation
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity');
    if (quantityInput) {
        quantityInput.addEventListener('change', function() {
            const value = parseInt(this.value) || 1;
            const max = parseInt(this.getAttribute('max')) || 999;
            const min = parseInt(this.getAttribute('min')) || 1;
            
            if (value > max) this.value = max;
            if (value < min) this.value = min;
        });
    }
    
    // Add change quantity buttons functionality for cart page
    const quantityButtons = document.querySelectorAll('.quantity-btn');
    quantityButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.closest('.quantity-controls').querySelector('.quantity-input');
            const cartItemId = input.dataset.cartItemId;
            const change = parseInt(this.dataset.change);
            const currentValue = parseInt(input.value) || 1;
            const newValue = Math.max(1, currentValue + change);
            const maxValue = parseInt(input.getAttribute('max')) || 999;
            
            if (newValue <= maxValue) {
                input.value = newValue;
                CartManager.updateQuantity(cartItemId, newValue);
            }
        });
    });
});

// Initialize cart functionality when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in and store in dataset for easy access
    const userLoggedIn = document.querySelector('meta[name="user-logged-in"]');
    if (userLoggedIn) {
        document.body.dataset.userLoggedIn = userLoggedIn.content;
    }
    
    console.log('Cart Manager initialized successfully');
});