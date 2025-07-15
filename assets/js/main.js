/**
 * Main JavaScript file for Pet Care & Sitting System
 * Fixed version with complete validation and password toggle functionality
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
    
    // Enhanced Email Validation Function
    function validateEmail(email) {
        const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        return emailRegex.test(email);
    }
    
    // Enhanced Password Validation Function
    function validatePassword(password) {
        const minLength = password.length >= 6;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
        
        return {
            isValid: minLength && (hasUpper || hasLower) && (hasNumber || hasSpecial),
            minLength: minLength,
            hasUpper: hasUpper,
            hasLower: hasLower,
            hasNumber: hasNumber,
            hasSpecial: hasSpecial
        };
    }
    
    // Add validation feedback elements
    function addValidationFeedback(input, message, isValid) {
        // Remove existing feedback
        const existingFeedback = input.parentNode.querySelector('.validation-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        // Add new feedback if message exists
        if (message) {
            const feedback = document.createElement('div');
            feedback.className = `validation-feedback ${isValid ? 'valid-feedback' : 'invalid-feedback'}`;
            feedback.style.display = 'block';
            feedback.textContent = message;
            input.parentNode.appendChild(feedback);
        }
        
        // Update input styling
        input.classList.remove('is-valid', 'is-invalid');
        if (message) {
            input.classList.add(isValid ? 'is-valid' : 'is-invalid');
        }
    }
    
    // Real-time email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(function(input) {
        input.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email) {
                if (validateEmail(email)) {
                    addValidationFeedback(this, 'Valid email address', true);
                } else {
                    addValidationFeedback(this, 'Please enter a valid email address', false);
                }
            } else {
                addValidationFeedback(this, null, false);
            }
        });
        
        input.addEventListener('input', function() {
            // Clear validation on input to allow user to type
            if (this.classList.contains('is-invalid') || this.classList.contains('is-valid')) {
                const feedback = this.parentNode.querySelector('.validation-feedback');
                if (feedback) feedback.remove();
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
    });
    
    // Real-time password validation
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(function(input) {
        // Skip confirm password inputs
        if (input.id === 'confirm_password') return;
        
        input.addEventListener('input', function() {
            const password = this.value;
            if (password) {
                const validation = validatePassword(password);
                let message = '';
                
                if (!validation.isValid) {
                    const requirements = [];
                    if (!validation.minLength) requirements.push('at least 6 characters');
                    if (!validation.hasUpper && !validation.hasLower) requirements.push('at least one letter');
                    if (!validation.hasNumber && !validation.hasSpecial) requirements.push('at least one number or special character');
                    
                    message = 'Password must contain: ' + requirements.join(', ');
                    addValidationFeedback(this, message, false);
                } else {
                    addValidationFeedback(this, 'Strong password!', true);
                }
            } else {
                addValidationFeedback(this, null, false);
            }
        });
    });
    
    // Confirm password validation
    const confirmPasswordInput = document.getElementById('confirm_password');
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword) {
                if (password === confirmPassword) {
                    addValidationFeedback(this, 'Passwords match!', true);
                } else {
                    addValidationFeedback(this, 'Passwords do not match', false);
                }
            } else {
                addValidationFeedback(this, null, false);
            }
        });
    }
    
    // Fixed Password toggle functionality
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetSelector = this.getAttribute('data-toggle');
            const target = document.querySelector(targetSelector);
            const icon = this.querySelector('i');
            
            if (target) {
                if (target.type === 'password') {
                    target.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                    this.setAttribute('title', 'Hide password');
                } else {
                    target.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    this.setAttribute('title', 'Show password');
                }
            }
        });
    });
    
    // Enhanced form validation
    const forms = document.querySelectorAll('.needs-validation, form');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            let isFormValid = true;
            
            // Validate email fields
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(function(field) {
                const email = field.value.trim();
                if (email && !validateEmail(email)) {
                    addValidationFeedback(field, 'Please enter a valid email address', false);
                    isFormValid = false;
                } else if (!email && field.hasAttribute('required')) {
                    addValidationFeedback(field, 'Email is required', false);
                    isFormValid = false;
                }
            });
            
            // Validate password fields
            const passwordFields = form.querySelectorAll('input[type="password"]');
            passwordFields.forEach(function(field) {
                if (field.id === 'confirm_password') return; // Skip confirm password here
                
                const password = field.value;
                if (password) {
                    const validation = validatePassword(password);
                    if (!validation.isValid) {
                        addValidationFeedback(field, 'Password does not meet requirements', false);
                        isFormValid = false;
                    }
                } else if (field.hasAttribute('required')) {
                    addValidationFeedback(field, 'Password is required', false);
                    isFormValid = false;
                }
            });
            
            // Validate confirm password
            const confirmPasswordField = form.querySelector('#confirm_password');
            if (confirmPasswordField) {
                const password = form.querySelector('#password').value;
                const confirmPassword = confirmPasswordField.value;
                
                if (password !== confirmPassword) {
                    addValidationFeedback(confirmPasswordField, 'Passwords do not match', false);
                    isFormValid = false;
                }
            }
            
            // Check HTML5 validation
            if (!form.checkValidity()) {
                isFormValid = false;
            }
            
            if (!isFormValid) {
                event.preventDefault();
                event.stopPropagation();
                
                // Focus on first invalid field
                const firstInvalid = form.querySelector('.is-invalid, :invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // User type selector functionality (for registration page)
    const userTypeInputs = document.querySelectorAll('input[name="user_type"]');
    const petSitterNotice = document.getElementById('pet-sitter-notice');
    
    if (userTypeInputs.length > 0) {
        userTypeInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (petSitterNotice) {
                    if (this.value === 'pet_sitter') {
                        petSitterNotice.style.display = 'block';
                    } else {
                        petSitterNotice.style.display = 'none';
                    }
                }
            });
        });
        
        // Initialize notice visibility
        const checkedInput = document.querySelector('input[name="user_type"]:checked');
        if (checkedInput && checkedInput.value === 'pet_sitter' && petSitterNotice) {
            petSitterNotice.style.display = 'block';
        }
    }
});