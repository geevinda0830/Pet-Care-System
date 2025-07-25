/**
 * Main JavaScript file for Pet Care & Sitting System
 * COMPLETE FIX - Completely disabled validation for login forms
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
    
    // Enhanced Password Validation Function (for registration only)
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
    
    // ROBUST login page detection
    function isLoginPage() {
        // Multiple ways to detect login page
        return window.location.pathname.includes('login.php') || 
               document.querySelector('form[action*="login"]') !== null ||
               document.querySelector('.auth-form-container h2')?.textContent.toLowerCase().includes('sign in') ||
               document.querySelector('form .btn')?.textContent.toLowerCase().includes('sign in') ||
               document.querySelector('button[type="submit"]')?.textContent.toLowerCase().includes('sign in') ||
               document.querySelector('input[type="submit"]')?.value.toLowerCase().includes('sign in') ||
               document.querySelector('.auth-footer')?.textContent.includes("Don't have an account?") ||
               document.querySelector('a[href*="register"]')?.textContent.toLowerCase().includes('create account');
    }
    
    // Determine if we're on registration page
    function isRegistrationPage() {
        return window.location.pathname.includes('register.php') || 
               document.querySelector('form[action*="register"]') !== null ||
               document.querySelector('.auth-form-container h2')?.textContent.toLowerCase().includes('sign up') ||
               document.querySelector('form .btn')?.textContent.toLowerCase().includes('sign up') ||
               document.querySelector('#confirm_password') !== null;
    }
    
    // Add validation feedback - only for registration
    function addValidationFeedback(input, message, isValid) {
        // NEVER add validation feedback on login pages
        if (isLoginPage()) {
            return;
        }
        
        // Remove existing feedback
        const inputContainer = input.closest('.form-group-modern') || input.parentNode;
        const existingFeedback = inputContainer.querySelector('.validation-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        // Add new feedback if message exists
        if (message) {
            const feedback = document.createElement('div');
            feedback.className = `validation-feedback ${isValid ? 'valid-feedback' : 'invalid-feedback'}`;
            feedback.style.display = 'block';
            feedback.style.fontSize = '0.8rem';
            feedback.style.marginTop = '0.25rem';
            feedback.style.padding = '0';
            feedback.style.position = 'static';
            feedback.style.width = '100%';
            feedback.textContent = message;
            
            // Insert after the input group
            const inputGroup = input.closest('.input-group-modern') || input;
            inputGroup.parentNode.appendChild(feedback);
        }
        
        // Update input styling
        input.classList.remove('is-valid', 'is-invalid');
        if (message) {
            input.classList.add(isValid ? 'is-valid' : 'is-invalid');
        }
    }
    
    // Clear all validation
    function clearAllValidation(input) {
        const inputContainer = input.closest('.form-group-modern') || input.parentNode;
        const feedback = inputContainer.querySelector('.validation-feedback');
        if (feedback) {
            feedback.remove();
        }
        input.classList.remove('is-valid', 'is-invalid');
    }
    
    // COMPLETELY DISABLE validation for login pages
    if (isLoginPage()) {
        console.log('Login page detected - disabling all validation');
        
        // Remove any existing validation messages
        document.querySelectorAll('.validation-feedback').forEach(el => el.remove());
        document.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
            el.classList.remove('is-valid', 'is-invalid');
        });
        
        // Add event listeners to prevent validation
        document.querySelectorAll('input').forEach(function(input) {
            // Clear validation on any input event
            input.addEventListener('input', function() {
                clearAllValidation(this);
            });
            
            input.addEventListener('blur', function() {
                clearAllValidation(this);
            });
            
            input.addEventListener('focus', function() {
                clearAllValidation(this);
            });
            
            input.addEventListener('keyup', function() {
                clearAllValidation(this);
            });
        });
        
    } else if (isRegistrationPage()) {
        console.log('Registration page detected - enabling full validation');
        
        // Email validation for registration
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
                    clearAllValidation(this);
                }
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') || this.classList.contains('is-valid')) {
                    clearAllValidation(this);
                }
            });
        });
        
        // Password validation for registration
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        passwordInputs.forEach(function(input) {
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
                    clearAllValidation(this);
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
                    clearAllValidation(this);
                }
            });
        }
    }
    
    // Password toggle functionality (works on all pages)
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
    
    // Form submission validation
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            let isFormValid = true;
            
            if (isLoginPage()) {
                // MINIMAL validation for login - only check if fields have values
                const requiredFields = form.querySelectorAll('input[required]');
                requiredFields.forEach(function(field) {
                    const value = field.value.trim();
                    if (!value) {
                        // Show simple error without validation styling
                        alert('Please fill in all required fields');
                        field.focus();
                        isFormValid = false;
                        return false;
                    }
                });
            } else {
                // Full validation for registration forms
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
                
                const passwordFields = form.querySelectorAll('input[type="password"]');
                passwordFields.forEach(function(field) {
                    if (field.id === 'confirm_password') return;
                    
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
                
                const confirmPasswordField = form.querySelector('#confirm_password');
                if (confirmPasswordField) {
                    const password = form.querySelector('#password').value;
                    const confirmPassword = confirmPasswordField.value;
                    
                    if (password !== confirmPassword) {
                        addValidationFeedback(confirmPasswordField, 'Passwords do not match', false);
                        isFormValid = false;
                    }
                }
            }
            
            if (!isFormValid) {
                event.preventDefault();
                event.stopPropagation();
                
                if (!isLoginPage()) {
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                }
            }
        });
    });
    
    // User type selector functionality (for registration)
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
        
        const checkedInput = document.querySelector('input[name="user_type"]:checked');
        if (checkedInput && checkedInput.value === 'pet_sitter' && petSitterNotice) {
            petSitterNotice.style.display = 'block';
        }
    }
    
    // Prevent any accidental validation on login pages
    if (isLoginPage()) {
        // Override any validation that might be triggered
        setTimeout(function() {
            document.querySelectorAll('.validation-feedback').forEach(el => el.remove());
            document.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
                el.classList.remove('is-valid', 'is-invalid');
            });
        }, 100);
        
        // Continue clearing every 500ms to be safe
        setInterval(function() {
            document.querySelectorAll('.validation-feedback').forEach(el => el.remove());
            document.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
                el.classList.remove('is-valid', 'is-invalid');
            });
        }, 500);
    }
});