<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Pet Care System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .payment-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            min-height: calc(100vh - 40px);
        }

        .payment-form-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            height: fit-content;
        }

        .order-summary-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            height: fit-content;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .form-header h1 {
            color: #2d3748;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .form-header p {
            color: #718096;
            font-size: 1.1rem;
        }

        .progress-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }

        .progress-bar::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: #e2e8f0;
            z-index: 1;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            width: 66.66%;
            height: 3px;
            background: #667eea;
            z-index: 2;
            transition: width 0.3s ease;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 3;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }

        .step-circle.active {
            background: #667eea;
            color: white;
        }

        .step-circle.completed {
            background: #48bb78;
            color: white;
        }

        .step-circle.inactive {
            background: #e2e8f0;
            color: #a0aec0;
        }

        .step-label {
            font-size: 0.9rem;
            color: #4a5568;
            font-weight: 500;
        }

        .payment-methods {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.5rem;
            color: #2d3748;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .payment-option {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            background: white;
        }

        .payment-option:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
        }

        .payment-option.selected {
            border-color: #667eea;
            background: #f7fafc;
            transform: translateY(-2px);
        }

        .payment-option input[type="radio"] {
            display: none;
        }

        .payment-option i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .payment-option label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            cursor: pointer;
        }

        .card-details {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .card-details.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
            font-weight: 600;
        }

        .form-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input.error {
            border-color: #e53e3e;
            background: #fed7d7;
        }

        .form-input.success {
            border-color: #48bb78;
            background: #f0fff4;
        }

        .input-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .card-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .card-preview::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="3" fill="rgba(255,255,255,0.1)"/></svg>');
            animation: float 20s infinite linear;
        }

        .card-number {
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 3px;
            margin-bottom: 20px;
            font-family: 'Courier New', monospace;
        }

        .card-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-holder {
            font-size: 1rem;
            opacity: 0.9;
        }

        .card-expiry {
            font-size: 1rem;
            opacity: 0.9;
        }

        .card-type {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2rem;
        }

        .error-message {
            color: #e53e3e;
            font-size: 0.9rem;
            margin-top: 5px;
            display: none;
        }

        .success-message {
            color: #48bb78;
            font-size: 0.9rem;
            margin-top: 5px;
            display: none;
        }

        .security-info {
            background: #f7fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }

        .security-info h4 {
            color: #2d3748;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .security-info p {
            color: #4a5568;
            font-size: 0.9rem;
        }

        .pay-button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .pay-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .pay-button:active {
            transform: translateY(0);
        }

        .pay-button.loading {
            pointer-events: none;
        }

        .pay-button .spinner {
            display: none;
            animation: spin 1s linear infinite;
        }

        .pay-button.loading .spinner {
            display: inline-block;
        }

        .pay-button.loading .button-text {
            display: none;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .order-summary h3 {
            color: #2d3748;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-info h4 {
            color: #2d3748;
            margin-bottom: 5px;
        }

        .item-info p {
            color: #718096;
            font-size: 0.9rem;
        }

        .item-price {
            font-weight: 600;
            color: #2d3748;
            font-size: 1.1rem;
        }

        .order-total {
            border-top: 2px solid #e2e8f0;
            padding-top: 20px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-total h3 {
            color: #2d3748;
            font-size: 1.5rem;
            margin: 0;
        }

        .total-amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
        }

        .trust-badges {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }

        .trust-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #718096;
            font-size: 0.9rem;
        }

        .trust-badge i {
            color: #48bb78;
        }

        @media (max-width: 768px) {
            .payment-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .payment-form-section,
            .order-summary-section {
                padding: 20px;
            }
            
            .form-header h1 {
                font-size: 2rem;
            }
            
            .payment-options {
                grid-template-columns: 1fr;
            }
            
            .input-group {
                grid-template-columns: 1fr;
            }
        }

        @keyframes float {
            0% { transform: translateX(0px) rotate(0deg); }
            33% { transform: translateX(30px) rotate(120deg); }
            66% { transform: translateX(-20px) rotate(240deg); }
            100% { transform: translateX(0px) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-form-section">
            <div class="form-header">
                <h1><i class="fas fa-paw"></i> Pet Care Payment</h1>
                <p>Secure payment for your pet care services</p>
            </div>

            <div class="progress-bar">
                <div class="progress-step">
                    <div class="step-circle completed">1</div>
                    <div class="step-label">Service</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle completed">2</div>
                    <div class="step-label">Details</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle active">3</div>
                    <div class="step-label">Payment</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle inactive">4</div>
                    <div class="step-label">Confirmation</div>
                </div>
            </div>

            <form id="paymentForm">
                <div class="payment-methods">
                    <h3 class="section-title">Choose Payment Method</h3>
                    <div class="payment-options">
                        <div class="payment-option selected" data-method="credit_card">
                            <input type="radio" id="credit_card" name="payment_method" value="credit_card" checked>
                            <label for="credit_card">
                                <i class="fas fa-credit-card"></i>
                                Credit Card
                            </label>
                        </div>
                        <div class="payment-option" data-method="paypal">
                            <input type="radio" id="paypal" name="payment_method" value="paypal">
                            <label for="paypal">
                                <i class="fab fa-paypal"></i>
                                PayPal
                            </label>
                        </div>
                        <div class="payment-option" data-method="bank_transfer">
                            <input type="radio" id="bank_transfer" name="payment_method" value="bank_transfer">
                            <label for="bank_transfer">
                                <i class="fas fa-university"></i>
                                Bank Transfer
                            </label>
                        </div>
                    </div>
                </div>

                <div id="credit_card_details" class="card-details active">
                    <div class="card-preview">
                        <div class="card-type">
                            <i class="fab fa-cc-visa"></i>
                        </div>
                        <div class="card-number" id="preview-number">•••• •••• •••• ••••</div>
                        <div class="card-info">
                            <div class="card-holder" id="preview-holder">CARDHOLDER NAME</div>
                            <div class="card-expiry" id="preview-expiry">MM/YY</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="card_holder">Cardholder Name</label>
                        <input type="text" id="card_holder" name="card_holder" class="form-input" placeholder="John Doe">
                        <div class="error-message" id="card_holder_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <input type="text" id="card_number" name="card_number" class="form-input" placeholder="1234 5678 9012 3456" maxlength="19">
                        <div class="error-message" id="card_number_error"></div>
                    </div>

                    <div class="input-group">
                        <div class="form-group">
                            <label for="expiry_date">Expiry Date</label>
                            <input type="text" id="expiry_date" name="expiry_date" class="form-input" placeholder="MM/YY" maxlength="5">
                            <div class="error-message" id="expiry_error"></div>
                        </div>
                        <div class="form-group">
                            <label for="cvv">CVV</label>
                            <input type="text" id="cvv" name="cvv" class="form-input" placeholder="123" maxlength="4">
                            <div class="error-message" id="cvv_error"></div>
                        </div>
                    </div>

                    <div class="security-info">
                        <h4><i class="fas fa-shield-alt"></i> Secure Payment</h4>
                        <p>Your payment information is encrypted and secure. We use industry-standard SSL encryption to protect your data.</p>
                    </div>
                </div>

                <div id="paypal_details" class="card-details">
                    <div class="security-info">
                        <h4><i class="fab fa-paypal"></i> PayPal Payment</h4>
                        <p>You will be redirected to PayPal to complete your payment securely.</p>
                    </div>
                </div>

                <div id="bank_transfer_details" class="card-details">
                    <div class="security-info">
                        <h4><i class="fas fa-university"></i> Bank Transfer</h4>
                        <p>Bank transfer instructions will be provided after clicking "Pay Now".</p>
                    </div>
                </div>

                <button type="submit" class="pay-button" id="payButton">
                    <span class="button-text">
                        <i class="fas fa-lock"></i> Pay Now - $149.99
                    </span>
                    <span class="spinner">
                        <i class="fas fa-spinner"></i>
                    </span>
                </button>

                <div class="trust-badges">
                    <div class="trust-badge">
                        <i class="fas fa-shield-alt"></i>
                        SSL Secured
                    </div>
                    <div class="trust-badge">
                        <i class="fas fa-lock"></i>
                        256-bit Encryption
                    </div>
                    <div class="trust-badge">
                        <i class="fas fa-check-circle"></i>
                        PCI Compliant
                    </div>
                </div>
            </form>
        </div>

        <div class="order-summary-section">
            <div class="order-summary">
                <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                
                <div class="order-item">
                    <div class="item-info">
                        <h4>Pet Sitting Service</h4>
                        <p>Golden Retriever - Max</p>
                        <p>March 15-17, 2024 (3 days)</p>
                    </div>
                    <div class="item-price">$120.00</div>
                </div>

                <div class="order-item">
                    <div class="item-info">
                        <h4>Additional Services</h4>
                        <p>Dog Walking & Feeding</p>
                    </div>
                    <div class="item-price">$25.00</div>
                </div>

                <div class="order-item">
                    <div class="item-info">
                        <h4>Service Fee</h4>
                        <p>Platform fee</p>
                    </div>
                    <div class="item-price">$4.99</div>
                </div>

                <div class="order-total">
                    <h3>Total</h3>
                    <div class="total-amount">$149.99</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentOptions = document.querySelectorAll('.payment-option');
            const cardDetails = document.querySelectorAll('.card-details');
            const form = document.getElementById('paymentForm');
            const payButton = document.getElementById('payButton');
            
            // Card input elements
            const cardNumberInput = document.getElementById('card_number');
            const cardHolderInput = document.getElementById('card_holder');
            const expiryInput = document.getElementById('expiry_date');
            const cvvInput = document.getElementById('cvv');
            
            // Preview elements
            const previewNumber = document.getElementById('preview-number');
            const previewHolder = document.getElementById('preview-holder');
            const previewExpiry = document.getElementById('preview-expiry');

            // Payment method selection
            paymentOptions.forEach(option => {
                option.addEventListener('click', function() {
                    paymentOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    const method = this.dataset.method;
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    
                    cardDetails.forEach(detail => {
                        detail.classList.remove('active');
                    });
                    
                    document.getElementById(method + '_details').classList.add('active');
                });
            });

            // Card number formatting and validation
            cardNumberInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
                
                if (formattedValue.length > 19) {
                    formattedValue = formattedValue.substr(0, 19);
                }
                
                e.target.value = formattedValue;
                
                // Update preview
                previewNumber.textContent = formattedValue.padEnd(19, '•').replace(/(.{4})/g, '$1 ');
                
                // Card type detection
                const cardType = document.querySelector('.card-type i');
                if (value.startsWith('4')) {
                    cardType.className = 'fab fa-cc-visa';
                } else if (value.startsWith('5')) {
                    cardType.className = 'fab fa-cc-mastercard';
                } else if (value.startsWith('3')) {
                    cardType.className = 'fab fa-cc-amex';
                } else {
                    cardType.className = 'fas fa-credit-card';
                }
                
                // Validation
                validateCardNumber(value);
            });

            // Cardholder name
            cardHolderInput.addEventListener('input', function(e) {
                const value = e.target.value.toUpperCase();
                previewHolder.textContent = value || 'CARDHOLDER NAME';
                validateCardHolder(value);
            });

            // Expiry date formatting
            expiryInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                e.target.value = value;
                previewExpiry.textContent = value || 'MM/YY';
                validateExpiry(value);
            });

            // CVV validation
            cvvInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '');
                validateCVV(e.target.value);
            });

            // Validation functions
            function validateCardNumber(number) {
                const input = document.getElementById('card_number');
                const error = document.getElementById('card_number_error');
                
                if (number.length === 0) {
                    showError(input, error, 'Card number is required');
                    return false;
                } else if (number.length < 16) {
                    showError(input, error, 'Card number must be 16 digits');
                    return false;
                } else if (!luhnCheck(number)) {
                    showError(input, error, 'Invalid card number');
                    return false;
                } else {
                    showSuccess(input, error);
                    return true;
                }
            }

            function validateCardHolder(name) {
                const input = document.getElementById('card_holder');
                const error = document.getElementById('card_holder_error');
                
                if (name.length === 0) {
                    showError(input, error, 'Cardholder name is required');
                    return false;
                } else if (name.length < 2) {
                    showError(input, error, 'Name must be at least 2 characters');
                    return false;
                } else {
                    showSuccess(input, error);
                    return true;
                }
            }

            function validateExpiry(expiry) {
                const input = document.getElementById('expiry_date');
                const error = document.getElementById('expiry_error');
                
                if (expiry.length === 0) {
                    showError(input, error, 'Expiry date is required');
                    return false;
                } else if (expiry.length < 5) {
                    showError(input, error, 'Invalid expiry date format');
                    return false;
                } else {
                    const [month, year] = expiry.split('/');
                    const now = new Date();
                    const currentYear = now.getFullYear() % 100;
                    const currentMonth = now.getMonth() + 1;
                    
                    if (parseInt(month) < 1 || parseInt(month) > 12) {
                        showError(input, error, 'Invalid month');
                        return false;
                    }
                    
                    if (parseInt(year) < currentYear || (parseInt(year) === currentYear && parseInt(month) < currentMonth)) {
                        showError(input, error, 'Card has expired');
                        return false;
                    }
                    
                    showSuccess(input, error);
                    return true;
                }
            }

            function validateCVV(cvv) {
                const input = document.getElementById('cvv');
                const error = document.getElementById('cvv_error');
                
                if (cvv.length === 0) {
                    showError(input, error, 'CVV is required');
                    return false;
                } else if (cvv.length < 3) {
                    showError(input, error, 'CVV must be 3-4 digits');
                    return false;
                } else {
                    showSuccess(input, error);
                    return true;
                }
            }

            function showError(input, errorElement, message) {
                input.classList.add('error');
                input.classList.remove('success');
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }

            function showSuccess(input, errorElement) {
                input.classList.remove('error');
                input.classList.add('success');
                errorElement.style.display = 'none';
            }

            // Luhn algorithm for card validation
            function luhnCheck(num) {
                let sum = 0;
                let shouldDouble = false;
                
                for (let i = num.length - 1; i >= 0; i--) {
                    let digit = parseInt(num.charAt(i));
                    
                    if (shouldDouble) {
                        digit *= 2;
                        if (digit > 9) {
                            digit -= 9;
                        }
                    }
                    
                    sum += digit;
                    shouldDouble = !shouldDouble;
                }
                
                return (sum % 10) === 0;
            }

            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
                
                if (selectedMethod === 'credit_card') {
                    const cardNumber = cardNumberInput.value.replace(/\s/g, '');
                    const cardHolder = cardHolderInput.value;
                    const expiry = expiryInput.value;
                    const cvv = cvvInput.value;
                    
                    if (!validateCardNumber(cardNumber) || !validateCardHolder(cardHolder) || 
                        !validateExpiry(expiry) || !validateCVV(cvv)) {
                        return;
                    }
                }
                
                // Show loading state
                payButton.classList.add('loading');
                payButton.disabled = true;
                
                // Simulate payment processing
                setTimeout(() => {
                    // Update progress bar
                    const progressBar = document.querySelector('.progress-bar::after');
                    const activeStep = document.querySelector('.step-circle.active');
                    const nextStep = document.querySelector('.step-circle.inactive');
                    
                    activeStep.classList.remove('active');
                    activeStep.classList.add('completed');
                    nextStep.classList.remove('inactive');
                    nextStep.classList.add('active');
                    
                    // Show success message
                    alert('Payment successful! Your booking has been confirmed.');
                    
                    // Reset button
                    payButton.classList.remove('loading');
                    payButton.disabled = false;
                }, 3000);
            });
        });
    </script>
</body>
</html>