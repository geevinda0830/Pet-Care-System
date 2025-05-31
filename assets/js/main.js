/**
 * Main JavaScript file for Pet Care & Sitting System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Form validation
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Password toggle visibility
    const togglePassword = document.querySelectorAll('.toggle-password');
    if (togglePassword) {
        togglePassword.forEach(function(button) {
            button.addEventListener('click', function() {
                const passwordField = document.querySelector(this.getAttribute('data-toggle'));
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                
                // Toggle icon
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    }
    
    // Quantity increment/decrement for product cart
    const quantityBtns = document.querySelectorAll('.quantity-btn');
    if (quantityBtns) {
        quantityBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const action = this.getAttribute('data-action');
                const input = this.closest('.quantity-control').querySelector('input');
                let value = parseInt(input.value);
                
                if (action === 'increase') {
                    value++;
                } else if (action === 'decrease' && value > 1) {
                    value--;
                }
                
                input.value = value;
                
                // If there's a form, trigger the update quantity event
                const form = this.closest('form');
                if (form && form.classList.contains('update-cart-form')) {
                    const updateBtn = form.querySelector('.update-cart-btn');
                    if (updateBtn) {
                        updateBtn.click();
                    }
                }
            });
        });
    }
    
    // Handle the rating selection for reviews
    const ratingStars = document.querySelectorAll('.rating-select i');
    if (ratingStars) {
        const ratingInput = document.getElementById('rating-input');
        
        ratingStars.forEach(function(star, index) {
            star.addEventListener('mouseover', function() {
                // Reset all stars
                ratingStars.forEach(s => s.className = 'far fa-star');
                
                // Fill stars up to current
                for (let i = 0; i <= index; i++) {
                    ratingStars[i].className = 'fas fa-star';
                }
            });
            
            star.addEventListener('click', function() {
                // Set the rating value
                if (ratingInput) {
                    ratingInput.value = index + 1;
                }
                
                // Keep the stars filled
                for (let i = 0; i <= index; i++) {
                    ratingStars[i].className = 'fas fa-star';
                }
                
                for (let i = index + 1; i < ratingStars.length; i++) {
                    ratingStars[i].className = 'far fa-star';
                }
            });
        });
        
        // Reset stars when moving out if no rating selected
        document.querySelector('.rating-select').addEventListener('mouseleave', function() {
            if (ratingInput && ratingInput.value) {
                const rating = parseInt(ratingInput.value);
                
                ratingStars.forEach(function(star, index) {
                    if (index < rating) {
                        star.className = 'fas fa-star';
                    } else {
                        star.className = 'far fa-star';
                    }
                });
            } else {
                ratingStars.forEach(s => s.className = 'far fa-star');
            }
        });
    }
    
    // Date range picker initialization for booking form
    const checkInDate = document.getElementById('checkInDate');
    const checkOutDate = document.getElementById('checkOutDate');
    
    if (checkInDate && checkOutDate) {
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        checkInDate.setAttribute('min', today);
        
        // Update checkout min date when checkin changes
        checkInDate.addEventListener('change', function() {
            checkOutDate.setAttribute('min', this.value);
            
            // If checkout date is before new checkin date, reset it
            if (checkOutDate.value && checkOutDate.value < this.value) {
                checkOutDate.value = this.value;
            }
        });
    }
    
    // Initialize Google Maps for location selection
    const mapContainer = document.getElementById('map-container');
    if (mapContainer && typeof google !== 'undefined') {
        const defaultLocation = { lat: 6.9271, lng: 79.8612 }; // Default to Colombo, Sri Lanka
        
        const map = new google.maps.Map(mapContainer, {
            center: defaultLocation,
            zoom: 12
        });
        
        const marker = new google.maps.Marker({
            position: defaultLocation,
            map: map,
            draggable: true
        });
        
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        
        // Update coordinates when marker is dragged
        google.maps.event.addListener(marker, 'dragend', function() {
            const position = marker.getPosition();
            if (latInput && lngInput) {
                latInput.value = position.lat();
                lngInput.value = position.lng();
            }
        });
        
        // Initialize with existing coordinates if available
        if (latInput && lngInput && latInput.value && lngInput.value) {
            const position = {
                lat: parseFloat(latInput.value),
                lng: parseFloat(lngInput.value)
            };
            
            marker.setPosition(position);
            map.setCenter(position);
        }
    }
});

/**
 * Add to cart function
 * @param {number} productId - The ID of the product to add to cart
 */
function addToCart(productId) {
    // Create form data
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', 1); // Default quantity
    formData.append('action', 'add');
    
    // Send AJAX request
    fetch('cart_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count
            document.querySelector('.cart-count').textContent = data.cart_count;
            
            // Show success message
            const toast = document.createElement('div');
            toast.className = 'position-fixed bottom-0 end-0 p-3';
            toast.style.zIndex = '11';
            toast.innerHTML = `
                <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check-circle me-2"></i> ${data.message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            document.body.appendChild(toast);
            
            const toastEl = toast.querySelector('.toast');
            const bsToast = new bootstrap.Toast(toastEl);
            bsToast.show();
            
            // Remove toast after it's hidden
            toastEl.addEventListener('hidden.bs.toast', function() {
                document.body.removeChild(toast);
            });
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

/**
 * Remove item from cart
 * @param {number} cartItemId - The ID of the cart item to remove
 */
function removeFromCart(cartItemId) {
    if (confirm('Are you sure you want to remove this item from your cart?')) {
        // Create form data
        const formData = new FormData();
        formData.append('cart_item_id', cartItemId);
        formData.append('action', 'remove');
        
        // Send AJAX request
        fetch('cart_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page to update cart
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}