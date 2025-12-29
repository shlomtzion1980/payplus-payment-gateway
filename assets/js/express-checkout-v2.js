/**
 * PayPlus Express Checkout V2
 * JavaScript for Apple Pay and Google Pay integration
 * Uses the ORIGINAL working implementation from front.js
 * 
 * @package PayPlus
 * @version 2.0.0
 */

(function($) {
    'use strict';

    class PayPlusExpressCheckout {
        constructor() {
            this.params = window.payplus_express_params || {};
            this.applePaySession = null;
            this.context = 'cart';
            
            // Variables from original implementation
            this.currentShippingPrice = 0;
            this.currentShippingTax = 0;
            this.currentShippingIdentifier = null;
            this.currentShippingArrayPayPlus = null;
            this.globalPriceProductsWithoutTax = 0;
            this.globalPriceProductsWithTax = 0;
            this.globalTaxForProducts = 0;
            this.globalDiscount = 0;
            this.appleTotalPrice = 0;
            this.globalPayingVat = true;
            this.ArrayCheckoutItemsApplePay = [];
            this.applePayConfig = null;
            
            this.init();
        }

        init() {
            if (!this.params.ajax_url) {
                console.error('PayPlus Express V2: No AJAX URL');
                return;
            }

            this.context = $('.payplus-express-checkout-container').data('context') || 'cart';
            console.log('PayPlus Express V2: Initializing', this.context);
            
            // Initialize Apple Pay (uses original implementation)
            if (this.params.apple_pay_enabled) {
                this.initApplePay();
            }

            // Initialize Google Pay iframe (uses original implementation)
            if (this.params.google_pay_enabled) {
                this.initGooglePayIframe();
            }
        }

        // ==================== Apple Pay (Original Implementation) ====================

        initApplePay() {
            if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
                $('#payplus-apple-pay-button').hide();
                console.log('PayPlus Express V2: Apple Pay not available');
                return;
            }

            const $button = $('#payplus-apple-pay-button');
            
            if ($button.length === 0) {
                console.log('PayPlus Express V2: Apple Pay button not found');
                return;
            }

            // Set Apple Pay config (from original)
            this.setApplePayConfig();

            // Set Apple Pay button styling
            $button.css({
                '-webkit-appearance': '-apple-pay-button',
                '-apple-pay-button-type': this.params.button_type || 'buy',
                '-apple-pay-button-style': this.params.button_color || 'black',
                'height': (this.params.button_height || '48') + 'px',
                'cursor': 'pointer',
                'display': 'inline-block'
            });

            // Bind click event
            $button.on('click', (e) => {
                e.preventDefault();
                this.startApplePay();
            });

            $button.show();
            console.log('PayPlus Express V2: Apple Pay initialized');
        }

        setApplePayConfig() {
            // Original Apple Pay configuration
            this.applePayConfig = {
                countryCode: this.params.country_code || 'IL',
                currencyCode: this.params.currency_code || 'ILS',
                displayName: this.params.store_name || 'Items',
                supportedNetworks: ['visa', 'masterCard', 'amex'],
                merchantCapabilities: ['supports3DS'],
                requiredShippingContactFields: ['postalAddress', 'phone', 'email'],
                total: { label: 'Total', amount: 1, type: 'final' },
                lineItems: [],
                shippingMethods: [],
            };
        }

        async startApplePay() {
            try {
                console.log('PayPlus Express V2: Starting Apple Pay');
                this.showLoading();
                
                // Get cart data (from original implementation)
                const cartData = await this.getCartData();
                console.log('PayPlus Express V2: Cart data', cartData);
                
                // Update Apple Pay config with cart data
                this.updateApplePayConfig(
                    cartData.shippingMethods,
                    cartData.shippingMethodsPayPlus,
                    cartData.items,
                    cartData.priceProductsWithoutTax,
                    cartData.priceProductsWithTax,
                    cartData.taxProducts,
                    cartData.discount,
                    cartData.totalPrice
                );

                // Create Apple Pay session (version 10)
                const session = new ApplePaySession(10, this.applePayConfig);
                this.applePaySession = session;

                // Event: Merchant validation (using original AJAX endpoint)
                session.onvalidatemerchant = async (event) => {
                    await this.onValidateMerchant(event, session);
                };

                // Event: Shipping contact selected
                session.onshippingcontactselected = async (event) => {
                    await this.onApplePayShippingContactSelected(event, session);
                };

                // Event: Shipping method selected
                session.onshippingmethodselected = async (event) => {
                    await this.onApplePayShippingMethodSelected(event, session);
                };

                // Event: Payment authorized (using original process-payment-oneclick)
                session.onpaymentauthorized = async (event) => {
                    await this.onApplePayPaymentAuthorized(event, session);
                };

                // Event: Cancel
                session.oncancel = () => {
                    this.hideLoading();
                    console.log('PayPlus Express V2: Apple Pay cancelled');
                };

                // Start session
                session.begin();
                console.log('PayPlus Express V2: Apple Pay session started');
                
            } catch (error) {
                console.error('PayPlus Express V2: Apple Pay error:', error);
                this.showError(error.message || (this.params.i18n && this.params.i18n.error_generic));
                this.hideLoading();
            }
        }

        async onValidateMerchant(event, session) {
            // Original implementation using PayPlus API
            try {
                console.log('PayPlus Express V2: Validating merchant');
                const additionalData = {
                    urlValidation: event.validationURL,
                };
                
                const response = await $.ajax({
                    type: 'post',
                    dataType: 'json',
                    url: this.params.ajax_url,
                    data: {
                        action: 'apple-onvalidate-merchant',
                        obj: additionalData,
                        _ajax_nonce: this.params.nonce
                    }
                });
                
                if (response && response.status && response.payment_response) {
                    console.log('PayPlus Express V2: Merchant validated');
                    session.completeMerchantValidation(response.payment_response);
                } else {
                    console.error('PayPlus Express V2: Merchant validation failed', response);
                    session.abort();
                    this.hideLoading();
                }
            } catch (error) {
                console.error('PayPlus Express V2: Merchant validation error:', error);
                session.abort();
                this.hideLoading();
            }
        }

        async onApplePayShippingContactSelected(event, session) {
            try {
                const shippingContact = event.shippingContact;
                console.log('PayPlus Express V2: Shipping contact selected', shippingContact);
                
                // Update paying VAT status (original implementation)
                const contact = {
                    country_ISO: shippingContact.countryCode || ''
                };
                await this.updatePayingVat(contact);
                
                // Update totals based on VAT status
                this.updateApplePayConfig(
                    this.currentShippingArrayPayPlus.all,
                    this.currentShippingArrayPayPlus.all,
                    this.ArrayCheckoutItemsApplePay,
                    this.globalPriceProductsWithoutTax,
                    this.globalPriceProductsWithTax,
                    this.globalTaxForProducts,
                    this.globalDiscount,
                    this.appleTotalPrice
                );

                const update = {
                    newTotal: this.applePayConfig.total,
                    newLineItems: this.applePayConfig.lineItems,
                    newShippingMethods: this.applePayConfig.shippingMethods
                };

                session.completeShippingContactSelection(update);
            } catch (error) {
                console.error('PayPlus Express V2: Shipping contact error:', error);
                session.completeShippingContactSelection({
                    errors: [new ApplePayError('shippingContactInvalid', 'postalAddress', error.message)]
                });
            }
        }

        async onApplePayShippingMethodSelected(event, session) {
            console.log('PayPlus Express V2: Shipping method selected', event.shippingMethod);
            const shippingMethod = event.shippingMethod;
            
            // Update current shipping price
            this.currentShippingPrice = parseFloat(shippingMethod.amount) || 0;
            this.currentShippingIdentifier = shippingMethod.identifier;
            
            // Recalculate totals
            this.updateApplePayConfig(
                this.currentShippingArrayPayPlus.all,
                this.currentShippingArrayPayPlus.all,
                this.ArrayCheckoutItemsApplePay,
                this.globalPriceProductsWithoutTax,
                this.globalPriceProductsWithTax,
                this.globalTaxForProducts,
                this.globalDiscount,
                this.appleTotalPrice
            );

            const update = {
                newTotal: this.applePayConfig.total,
                newLineItems: this.applePayConfig.lineItems
            };

            session.completeShippingMethodSelection(update);
        }

        async onApplePayPaymentAuthorized(event, session) {
            try {
                console.log('PayPlus Express V2: Payment authorized', event.payment);
                const payment = event.payment;
                
                // Build payment data for original endpoint
                const additionalData = {
                    method: 'apple-pay',
                    token: payment.token.paymentData,
                    shipping: this.currentShippingIdentifier || 'shipping--1',
                    paying_vat: this.globalPayingVat,
                    contact: {
                        customer_name: payment.shippingContact.givenName + ' ' + payment.shippingContact.familyName,
                        email: payment.shippingContact.emailAddress,
                        phone: payment.shippingContact.phoneNumber || '',
                        address: payment.shippingContact.addressLines ? payment.shippingContact.addressLines.join(', ') : '',
                        city: payment.shippingContact.locality || '',
                        country_ISO: payment.shippingContact.countryCode || ''
                    }
                };
                
                // Process payment using original endpoint
                const response = await $.ajax({
                    type: 'post',
                    dataType: 'json',
                    url: this.params.ajax_url,
                    data: {
                        action: 'process-payment-oneclick',
                        obj: additionalData,
                        _ajax_nonce: this.params.nonce
                    }
                });

                if (response && response.status === true) {
                    console.log('PayPlus Express V2: Payment successful');
                    session.completePayment({
                        status: ApplePaySession.STATUS_SUCCESS
                    });

                    // Redirect to success page
                    setTimeout(() => {
                        window.location.href = response.link;
                    }, 500);
                } else {
                    console.error('PayPlus Express V2: Payment failed', response);
                    session.completePayment({
                        status: ApplePaySession.STATUS_FAILURE
                    });
                    this.showError(response.payment_response?.data?.message || 'Payment failed');
                    this.hideLoading();
                }
            } catch (error) {
                console.error('PayPlus Express V2: Payment authorization error:', error);
                session.completePayment({
                    status: ApplePaySession.STATUS_FAILURE
                });
                this.showError(error.message || 'Payment error');
                this.hideLoading();
            }
        }

        // ==================== Google Pay Iframe (Original Implementation) ====================

        initGooglePayIframe() {
            console.log('PayPlus Express V2: Initializing Google Pay iframe');
            
            const $container = $('#payplus-google-pay-button');
            if ($container.length === 0) {
                console.log('PayPlus Express V2: Google Pay container not found');
                return;
            }

            // Setup iframe for Google Pay
            const timestamp = Date.now();
            const siteUrlEncoded = btoa(window.location.origin);
            const iframeUrl = this.params.google_pay_iframe_url + '?var=' + timestamp + '&wb=' + siteUrlEncoded;
            
            const iframe = $('<iframe>', {
                src: iframeUrl,
                allow: 'payment *',
                sandbox: 'allow-forms allow-scripts allow-same-origin allow-popups',
                allowpaymentrequest: 'true',
                style: 'width: 100%; height: 50px; border: none; display: block;',
                'aria-label': 'Google Pay',
                title: 'Google Pay'
            });

            $container.empty().append(iframe).show();
            console.log('PayPlus Express V2: Google Pay iframe created', iframeUrl);

            // Setup postMessage listener for iframe communication
            this.setupGooglePayIframeListener();
        }

        setupGooglePayIframeListener() {
            console.log('PayPlus Express V2: Setting up Google Pay iframe listener');
            
            window.addEventListener('message', async (event) => {
                console.log('PayPlus Express V2: Message from iframe', event.origin, event.data);
                
                const paymentData = event.data;
                const iframeUrl = this.params.google_pay_iframe_url;
                
                if (!iframeUrl) {
                    console.error('PayPlus Express V2: No iframe URL configured');
                    return;
                }

                // Verify origin is from PayPlus
                const isPayPlusOrigin = event.origin.includes('payplus.co.il');
                if (!isPayPlusOrigin) {
                    console.log('PayPlus Express V2: Origin mismatch, ignoring');
                    return;
                }

                const messageType = paymentData.oneClickCheckoutGooglePay || paymentData.type;
                console.log('PayPlus Express V2: Message type:', messageType);

                if (messageType === 'ProcessPayment') {
                    await this.processGooglePayPayment(paymentData);
                } else if (messageType === 'getCurrentPrice') {
                    await this.sendCartDataToIframe();
                } else if (messageType === 'getPayingVat') {
                    await this.sendPayingVatToIframe(paymentData);
                }
            });
        }

        async sendCartDataToIframe() {
            console.log('PayPlus Express V2: Sending cart data to iframe');
            try {
                const cartData = await this.getCartData();
                
                const finalCartData = {
                    startProcess: true,
                    host: window.location.hostname,
                    totalPriceWithoutTax: cartData.priceProductsWithoutTax.toFixed(2),
                    taxProductsAmount: cartData.taxProducts.toFixed(2),
                    currencyCode: cartData.currencyCode,
                    shipping: {
                        all: cartData.shippingMethods
                    },
                    products: cartData.items,
                    discount: cartData.discount.toFixed(2),
                    totalPriceWithTax: cartData.priceProductsWithTax.toFixed(2),
                    paying_vat: this.globalPayingVat
                };

                console.log('PayPlus Express V2: Posting cart data to iframe', finalCartData);
                
                // Send data to iframe
                const iframe = $('#payplus-google-pay-button iframe')[0];
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.postMessage(finalCartData, '*');
                }
            } catch (error) {
                console.error('PayPlus Express V2: Error sending cart data:', error);
            }
        }

        async sendPayingVatToIframe(paymentData) {
            console.log('PayPlus Express V2: Updating paying VAT status');
            const contact = {
                country_ISO: paymentData.data?.country || ''
            };
            await this.updatePayingVat(contact);
            
            // Send updated status to iframe
            const iframe = $('#payplus-google-pay-button iframe')[0];
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage({ paying_vat: this.globalPayingVat }, '*');
            }
        }

        async processGooglePayPayment(paymentData) {
            console.log('PayPlus Express V2: Processing Google Pay payment');
            this.showLoading();
            
            try {
                const gpayData = paymentData.data.paymentData;
                const token = gpayData?.paymentMethodData?.tokenizationData?.token;
                
                if (!token) {
                    throw new Error('No token received from Google Pay');
                }

                const additionalData = {
                    page_checkout: true,
                    method: 'google-pay',
                    token: token,
                    cardInfo: {
                        info: gpayData?.paymentMethodData?.info || {}
                    },
                    shipping: gpayData?.shippingOptionData?.id || 'shipping--1',
                    paying_vat: this.globalPayingVat,
                    contact: {
                        customer_name: gpayData.shippingAddress?.name || '',
                        email: gpayData.email || '',
                        phone: gpayData.shippingAddress?.phoneNumber || '',
                        address: gpayData.shippingAddress?.address1 || '',
                        city: gpayData.shippingAddress?.locality || '',
                        country_ISO: gpayData.shippingAddress?.countryCode || ''
                    }
                };

                console.log('PayPlus Express V2: Sending payment data to AJAX', additionalData);

                const response = await $.ajax({
                    type: 'post',
                    dataType: 'json',
                    url: this.params.ajax_url,
                    data: {
                        action: 'process-payment-oneclick',
                        obj: additionalData,
                        _ajax_nonce: this.params.nonce
                    }
                });

                if (response.status === true) {
                    console.log('PayPlus Express V2: Payment successful, redirecting');
                    const iframe = $('#payplus-google-pay-button iframe')[0];
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage('PAYMENT_SUCCESS', '*');
                    }
                    setTimeout(() => {
                        window.location.href = response.link;
                    }, 500);
                } else {
                    console.error('PayPlus Express V2: Payment failed', response);
                    const iframe = $('#payplus-google-pay-button iframe')[0];
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage('PAYMENT_ERROR', '*');
                    }
                    this.showError(response.payment_response?.data?.message || 'Payment failed');
                    this.hideLoading();
                }
            } catch (error) {
                console.error('PayPlus Express V2: Payment error:', error);
                const iframe = $('#payplus-google-pay-button iframe')[0];
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.postMessage('PAYMENT_ERROR', '*');
                }
                this.showError(error.message || 'Payment error');
                this.hideLoading();
            }
        }

        // ==================== Helper Methods (Original Implementation) ====================

        async getCartData() {
            console.log('PayPlus Express V2: Getting cart data');
            
            const response = await $.ajax({
                type: 'post',
                dataType: 'json',
                url: this.params.ajax_url,
                data: {
                    action: 'payplus-get-total-cart',
                    _ajax_nonce: this.params.nonce
                }
            });
            
            if (!response || !response.status) {
                throw new Error('Failed to get cart data');
            }
            
            const data = response.data;
            
            // Store values for later use
            this.globalPriceProductsWithoutTax = parseFloat(data.priceProductsWithoutTax);
            this.globalPriceProductsWithTax = parseFloat(data.priceProductsWithTax);
            this.globalTaxForProducts = parseFloat(data.taxProducts);
            this.globalDiscount = parseFloat(data.discount);
            this.appleTotalPrice = parseFloat(data.totalPrice);
            this.ArrayCheckoutItemsApplePay = data.items;
            this.currentShippingArrayPayPlus = { all: data.shippingMethods };
            
            console.log('PayPlus Express V2: Cart data received', data);
            return data;
        }

        updateApplePayConfig(
            formattedShippingArray,
            formattedShippingArrayPayPlus,
            items,
            priceProductsWithoutTax,
            priceProductsWithTax,
            taxProducts,
            discount,
            totalPrice
        ) {
            console.log('PayPlus Express V2: Updating Apple Pay config');
            
            // Build line items
            const lineItems = [];
            
            // Add product items
            if (items && items.length > 0) {
                items.forEach(item => {
                    lineItems.push({
                        label: item.name,
                        amount: this.globalPayingVat ? item.quantity_price_including_vat.toFixed(2) : item.quantity_price.toFixed(2),
                        type: 'final'
                    });
                });
            }
            
            // Add tax
            if (this.globalPayingVat && taxProducts > 0) {
                lineItems.push({
                    label: 'Tax',
                    amount: taxProducts.toFixed(2),
                    type: 'final'
                });
            }
            
            // Add discount
            if (discount > 0) {
                lineItems.push({
                    label: 'Discount',
                    amount: '-' + discount.toFixed(2),
                    type: 'final'
                });
            }
            
            // Add shipping
            if (this.currentShippingPrice > 0) {
                lineItems.push({
                    label: 'Shipping',
                    amount: this.currentShippingPrice.toFixed(2),
                    type: 'final'
                });
            }
            
            // Build shipping methods
            const shippingMethods = [];
            if (formattedShippingArray && formattedShippingArray.length > 0) {
                formattedShippingArray.forEach(method => {
                    shippingMethods.push({
                        label: method.title,
                        amount: parseFloat(method.cost_with_tax || method.cost_without_tax || 0).toFixed(2),
                        identifier: 'shipping-' + method.id,
                        detail: ''
                    });
                });
            }
            
            // Calculate total
            let totalAmount = this.globalPayingVat ? priceProductsWithTax : priceProductsWithoutTax;
            totalAmount += this.currentShippingPrice;
            totalAmount -= discount;
            
            // Update config
            this.applePayConfig.lineItems = lineItems;
            this.applePayConfig.shippingMethods = shippingMethods;
            this.applePayConfig.total = {
                label: this.params.store_name || 'Total',
                amount: totalAmount.toFixed(2),
                type: 'final'
            };
            
            console.log('PayPlus Express V2: Apple Pay config updated', this.applePayConfig);
        }

        async updatePayingVat(contact) {
            console.log('PayPlus Express V2: Updating paying VAT', contact);
            
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: 'post',
                    dataType: 'json',
                    url: this.params.ajax_url,
                    data: {
                        action: 'check-customer-vat-oc',
                        obj: contact,
                        _ajax_nonce: this.params.nonce
                    },
                    success: (response) => {
                        this.globalPayingVat = response.paying_vat;
                        console.log('PayPlus Express V2: Paying VAT updated', this.globalPayingVat);
                        resolve();
                    },
                    error: (jqXHR, textStatus, errorThrow) => {
                        console.error('PayPlus Express V2: VAT check error:', textStatus, errorThrow);
                        reject(jqXHR);
                    }
                });
            });
        }

        showLoading() {
            console.log('PayPlus Express V2: Showing loading');
            $('.payplus-express-buttons').css('opacity', '0.5');
            $('.payplus-express-buttons button').prop('disabled', true);
        }

        hideLoading() {
            console.log('PayPlus Express V2: Hiding loading');
            $('.payplus-express-buttons').css('opacity', '1');
            $('.payplus-express-buttons button').prop('disabled', false);
        }

        showError(message) {
            console.error('PayPlus Express V2: Error:', message);
            
            // Try WooCommerce notice system first
            if (typeof wc_add_notice !== 'undefined') {
                wc_add_notice(message, 'error');
            } else {
                // Fallback to alert
                alert(message);
            }
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        console.log('PayPlus Express V2: Document ready');
        if ($('.payplus-express-checkout-container').length > 0) {
            console.log('PayPlus Express V2: Container found, initializing');
            new PayPlusExpressCheckout();
        } else {
            console.log('PayPlus Express V2: Container not found');
        }
    });

    // Re-initialize on AJAX complete (for dynamic content)
    $(document).ajaxComplete(function(event, xhr, settings) {
        // Only reinitialize on WooCommerce AJAX updates
        if (settings.url && settings.url.indexOf('wc-ajax') !== -1) {
            if ($('.payplus-express-checkout-container').length > 0 && !$('.payplus-express-checkout-container').data('initialized')) {
                console.log('PayPlus Express V2: Re-initializing after AJAX');
                $('.payplus-express-checkout-container').data('initialized', true);
                new PayPlusExpressCheckout();
            }
        }
    });
    
    // Expose class globally for WooCommerce Blocks (future use)
    window.PayPlusExpressCheckout = PayPlusExpressCheckout;

})(jQuery);

