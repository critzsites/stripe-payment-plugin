/**
 * Stripe Payment Plugin JavaScript
 */

(function($) {
    'use strict';
    
    let stripe;
    let elements;
    let cardElement;
    let paymentIntentClientSecret;
    
    // Initialize Stripe when DOM is ready
    $(document).ready(function() {
        if (typeof stripePayment === 'undefined') {
            console.error('Stripe Payment: Configuration not loaded');
            return;
        }
        
        // Initialize Stripe
        stripe = Stripe(stripePayment.publishableKey);
        elements = stripe.elements();
        
        // Create card element
        const style = {
            base: {
                fontSize: '15px',
                color: '#32325d',
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                '::placeholder': {
                    color: '#aab7c4',
                },
            },
            invalid: {
                color: '#e25950',
                iconColor: '#e25950',
            },
        };
        
        cardElement = elements.create('card', {
            style: style,
            hidePostalCode: true // We collect postal code separately in billing address
        });
        
        cardElement.mount('#card-element');
        
        // Handle real-time validation errors
        cardElement.on('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
        
        // Handle form submission
        $('#stripe-payment-form').on('submit', handleFormSubmit);
    });
    
    /**
     * Handle form submission
     */
    async function handleFormSubmit(event) {
        event.preventDefault();
        
        const form = $('#stripe-payment-form');
        const submitButton = $('#submit-button');
        const buttonText = submitButton.find('.button-text');
        const buttonSpinner = submitButton.find('.button-spinner');
        const paymentMessage = $('#payment-message');
        
        // Hide previous messages
        paymentMessage.hide().removeClass('success error');
        
        // Validate form
        if (!validateForm()) {
            return;
        }
        
        // Disable form and show loading state
        form.addClass('processing');
        submitButton.prop('disabled', true);
        buttonText.text('Processing...');
        buttonSpinner.show();
        
        try {
            // Check if subscription mode
            const isSubscription = $('#is-subscription').val() === '1';
            
            if (isSubscription) {
                // Handle subscription
                await handleSubscriptionPayment();
            } else {
                // Handle one-time payment
                await handleOneTimePayment();
            }
        } catch (error) {
            console.error('Payment error:', error);
            showMessage(
                error.responseJSON?.data?.message || error.message || 'An error occurred. Please try again.',
                'error'
            );
            form.removeClass('processing');
            submitButton.prop('disabled', false);
            buttonText.text('Process Payment');
            buttonSpinner.hide();
        }
    }
    
    /**
     * Handle one-time payment
     */
    async function handleOneTimePayment() {
        const form = $('#stripe-payment-form');
        const submitButton = $('#submit-button');
        const buttonText = submitButton.find('.button-text');
        const buttonSpinner = submitButton.find('.button-spinner');
        
        // Get amount and currency
        const amount = parseInt($('#payment-amount').val());
        const currency = $('#payment-currency').val();
        
        // Get customer details
        const fullName = $('#full-name').val();
        const email = $('#email').val();
        const phone = $('#phone').val();
        
        // Get billing address details
        const addressLine1 = $('#address-line1').val();
        const addressLine2 = $('#address-line2').val();
        const city = $('#city').val();
        const state = $('#state').val();
        const postalCode = $('#postal-code').val();
        const country = $('#country').val();
        
        // Create payment intent with customer details
        const response = await $.ajax({
            url: stripePayment.ajaxUrl,
            type: 'POST',
            data: {
                action: 'stripe_create_payment_intent',
                amount: amount,
                currency: currency,
                customer_email: email,
                customer_name: fullName,
                customer_phone: phone,
                address_line1: addressLine1,
                address_line2: addressLine2,
                address_city: city,
                address_state: state,
                address_postal_code: postalCode,
                address_country: country,
                nonce: stripePayment.nonce
            }
        });
            
            if (response.success && response.data.clientSecret) {
                paymentIntentClientSecret = response.data.clientSecret;
                
                // Build address object
                const address = {
                    line1: addressLine1,
                    city: city,
                    state: state,
                    postal_code: postalCode,
                    country: country
                };
                
                // Add line2 if provided
                if (addressLine2) {
                    address.line2 = addressLine2;
                }
                
                // Confirm payment with Stripe
                const {error, paymentIntent} = await stripe.confirmCardPayment(
                    paymentIntentClientSecret,
                    {
                        payment_method: {
                            card: cardElement,
                            billing_details: {
                                name: fullName,
                                email: email,
                                phone: phone,
                                address: address
                            }
                        }
                    }
                );
                
                if (error) {
                    // Show error message
                    showMessage(error.message, 'error');
                    form.removeClass('processing');
                    submitButton.prop('disabled', false);
                    buttonText.text('Process Payment');
                    buttonSpinner.hide();
                } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                    // Payment succeeded
                    await confirmPayment(paymentIntent.id);
                    
                    // Reset form
                    form[0].reset();
                    cardElement.clear();
                    
                    // Reset form state
                    form.removeClass('processing');
                    submitButton.prop('disabled', false);
                    buttonText.text('Process Payment');
                    buttonSpinner.hide();
                    
                    // Check if thank you page is set, then redirect
                    if (stripePayment.thankYouPage && stripePayment.thankYouPage.trim() !== '') {
                        // Redirect to thank you page after a short delay
                        showMessage('Payment successful! Redirecting...', 'success');
                        setTimeout(function() {
                            window.location.href = stripePayment.thankYouPage;
                        }, 1500);
                    } else {
                        // Show success message on same page
                        showMessage('Payment successful! Thank you for your purchase.', 'success');
                    }
                }
            } else {
                throw new Error(response.data?.message || 'Failed to create payment intent');
            }
    }
    
    /**
     * Handle subscription payment
     */
    async function handleSubscriptionPayment() {
        const form = $('#stripe-payment-form');
        const submitButton = $('#submit-button');
        const buttonText = submitButton.find('.button-text');
        const buttonSpinner = submitButton.find('.button-spinner');
        
        // Get subscription details
        const trialAmount = parseInt($('#trial-amount').val());
        const trialDays = parseInt($('#trial-days').val());
        const annualAmount = parseInt($('#annual-amount').val());
        const noTrial = $('#no-trial').val() === '1';
        const currency = $('#payment-currency').val();
        const priceId = $('#stripe-price-id').val();
        
        // Create subscription setup
        const response = await $.ajax({
            url: stripePayment.ajaxUrl,
            type: 'POST',
            data: {
                action: 'stripe_create_subscription',
                trial_amount: trialAmount,
                trial_days: trialDays,
                annual_amount: annualAmount,
                currency: currency,
                price_id: priceId,
                nonce: stripePayment.nonce
            }
        });
        
        if (response.success && response.data.setupIntentClientSecret) {
            const setupIntentClientSecret = response.data.setupIntentClientSecret;
            const priceId = response.data.priceId;
            
            // Get customer details
            const fullName = $('#full-name').val();
            const email = $('#email').val();
            const phone = $('#phone').val();
            
            // Get billing address details
            const addressLine1 = $('#address-line1').val();
            const addressLine2 = $('#address-line2').val();
            const city = $('#city').val();
            const state = $('#state').val();
            const postalCode = $('#postal-code').val();
            const country = $('#country').val();
            
            // Build address object
            const address = {
                line1: addressLine1,
                city: city,
                state: state,
                postal_code: postalCode,
                country: country
            };
            
            if (addressLine2) {
                address.line2 = addressLine2;
            }
            
            // Confirm setup intent
            const {error: setupError, setupIntent} = await stripe.confirmCardSetup(
                setupIntentClientSecret,
                {
                    payment_method: {
                        card: cardElement,
                        billing_details: {
                            name: fullName,
                            email: email,
                            phone: phone,
                            address: address
                        }
                    }
                }
            );
            
            if (setupError) {
                showMessage(setupError.message, 'error');
                form.removeClass('processing');
                submitButton.prop('disabled', false);
                buttonText.text('Process Payment');
                buttonSpinner.hide();
                return;
            }
            
            // Create subscription
            const subscriptionResponse = await $.ajax({
                url: stripePayment.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'stripe_confirm_subscription',
                    payment_method_id: setupIntent.payment_method,
                    price_id: priceId,
                    trial_amount: trialAmount,
                    trial_days: trialDays,
                    no_trial: noTrial ? '1' : '0',
                    currency: currency,
                    customer_email: email,
                    customer_name: fullName,
                    customer_phone: phone,
                    address_line1: addressLine1,
                    address_line2: addressLine2,
                    address_city: city,
                    address_state: state,
                    address_postal_code: postalCode,
                    address_country: country,
                    nonce: stripePayment.nonce
                }
            });
            
            if (subscriptionResponse.success) {
                // If the backend created an invoice payment intent (no-trial immediate charge),
                // confirm it client-side for SCA.
                const invoicePiClientSecret = subscriptionResponse.data?.invoicePaymentIntentClientSecret;
                if (invoicePiClientSecret) {
                    const { error: invoiceError, paymentIntent: invoicePI } = await stripe.confirmCardPayment(
                        invoicePiClientSecret,
                        { payment_method: setupIntent.payment_method }
                    );
                    
                    if (invoiceError) {
                        showMessage(invoiceError.message || 'Payment confirmation failed. Please try again.', 'error');
                        form.removeClass('processing');
                        submitButton.prop('disabled', false);
                        buttonText.text('Process Payment');
                        buttonSpinner.hide();
                        return;
                    }
                    
                    if (invoicePI && invoicePI.status !== 'succeeded' && invoicePI.status !== 'processing') {
                        showMessage('Payment was not completed. Please try again.', 'error');
                        form.removeClass('processing');
                        submitButton.prop('disabled', false);
                        buttonText.text('Process Payment');
                        buttonSpinner.hide();
                        return;
                    }
                }
                
                // Subscription created successfully
                showMessage('Subscription created successfully! Thank you for your purchase.', 'success');
                
                // Reset form
                form[0].reset();
                cardElement.clear();
                
                // Reset form state
                form.removeClass('processing');
                submitButton.prop('disabled', false);
                buttonText.text('Process Payment');
                buttonSpinner.hide();
                
                // Check if thank you page is set, then redirect
                if (stripePayment.thankYouPage && stripePayment.thankYouPage.trim() !== '') {
                    setTimeout(function() {
                        window.location.href = stripePayment.thankYouPage;
                    }, 1500);
                }
            } else {
                throw new Error(subscriptionResponse.data?.message || 'Failed to create subscription');
            }
        } else {
            throw new Error(response.data?.message || 'Failed to create subscription setup');
        }
    }
    
    /**
     * Validate form fields
     */
    function validateForm() {
        const fullName = $('#full-name').val().trim();
        const email = $('#email').val().trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const phone = $('#phone').val().trim();
        const addressLine1 = $('#address-line1').val().trim();
        const city = $('#city').val().trim();
        const state = $('#state').val().trim();
        const postalCode = $('#postal-code').val().trim();
        const country = $('#country').val();
        
        if (!fullName) {
            showMessage('Please enter your full name.', 'error');
            $('#full-name').focus();
            return false;
        }
        
        if (!email) {
            showMessage('Please enter your email address.', 'error');
            $('#email').focus();
            return false;
        }
        
        if (!emailRegex.test(email)) {
            showMessage('Please enter a valid email address.', 'error');
            $('#email').focus();
            return false;
        }
        
        if (!phone) {
            showMessage('Please enter your phone number.', 'error');
            $('#phone').focus();
            return false;
        }
        
        if (!addressLine1) {
            showMessage('Please enter your street address.', 'error');
            $('#address-line1').focus();
            return false;
        }
        
        if (!city) {
            showMessage('Please enter your city.', 'error');
            $('#city').focus();
            return false;
        }
        
        if (!state) {
            showMessage('Please enter your state/province.', 'error');
            $('#state').focus();
            return false;
        }
        
        if (!postalCode) {
            showMessage('Please enter your ZIP/postal code.', 'error');
            $('#postal-code').focus();
            return false;
        }
        
        if (!country) {
            showMessage('Please select your country.', 'error');
            $('#country').focus();
            return false;
        }
        
        return true;
    }
    
    /**
     * Confirm payment on server
     */
    async function confirmPayment(paymentIntentId) {
        try {
            await $.ajax({
                url: stripePayment.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'stripe_confirm_payment',
                    payment_intent_id: paymentIntentId,
                    nonce: stripePayment.nonce
                }
            });
        } catch (error) {
            console.error('Error confirming payment:', error);
        }
    }
    
    /**
     * Show message to user
     */
    function showMessage(message, type) {
        const paymentMessage = $('#payment-message');
        paymentMessage
            .removeClass('success error')
            .addClass(type)
            .text(message)
            .fadeIn();
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: paymentMessage.offset().top - 100
        }, 300);
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                paymentMessage.fadeOut();
            }, 5000);
        }
    }
    
})(jQuery);

