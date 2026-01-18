(function() {
    'use strict';

    // Wait for WooCommerce Blocks to be available
    if (typeof window.wc === 'undefined' || typeof window.wc.wcBlocksRegistry === 'undefined') {
        return;
    }

    const { registerExpressPaymentMethod } = window.wc.wcBlocksRegistry;
    const { getPaymentMethodData } = window.wc.wcSettings;

    // Helper to get React dynamically
    function getReact() {
        if (typeof window.wp !== 'undefined' && window.wp.element && window.wp.element.createElement) {
            return window.wp.element;
        }
        if (typeof window.React !== 'undefined' && window.React.createElement) {
            return window.React;
        }
        return null;
    }

    function initExpressPayment() {
        // Verify React is available before proceeding
        const ReactCheck = getReact();
        
        if (!ReactCheck || !ReactCheck.createElement) {
            // React not available, try again later (max 10 attempts = 1 second)
            if (typeof initExpressPayment.attempts === 'undefined') {
                initExpressPayment.attempts = 0;
            }
            initExpressPayment.attempts++;
            if (initExpressPayment.attempts < 10) {
                setTimeout(initExpressPayment, 100);
            }
            return;
        }
        
        // Get express checkout data from the main payment method
        const payplusData = getPaymentMethodData('payplus-payment-gateway');
        
        if (!payplusData || !payplusData.express_data || !payplusData.express_data.isExpressCheckoutEnabled) {
            return;
        }

        const expressData = payplusData.express_data;
        const { 
            isGoogleEnabled, 
            isAppleEnabled, 
            googlePayIframeUrl,
            shippingPrice,
            currencyCode,
            shippingWoo,
            globalShipping,
            globalShippingPriceTax,
            globalShippingWithoutTax,
            requirePhone,
            phonePlaceholder,
            ajaxUrl,
            frontNonce
        } = expressData;
        
        // Function to send cart data to Google Pay iframe (for blocks checkout)
        function sendCartDataToGooglePayIframe(iframe, startProcess = false) {
            if (!iframe || !iframe.contentWindow) {
                return;
            }
            
            // Get cart data via AJAX (same endpoint as classic checkout)
            if (typeof jQuery !== 'undefined' && ajaxUrl && frontNonce) {
                jQuery.ajax({
                    type: 'post',
                    dataType: 'json',
                    url: ajaxUrl,
                    data: {
                        action: 'payplus-get-total-cart',
                        _ajax_nonce: frontNonce
                    },
                    success: function(response) {
                        if (response && response.error === false) {
                            // Check if cart has products
                            if (!response.products || response.products.length === 0 || !response.total_without_tax || response.total_without_tax == 0) {
                                const errorDiv = document.getElementById('error-api-payplus');
                                if (errorDiv) {
                                    errorDiv.innerHTML = '<p>Error: Empty shopping cart</p>';
                                }
                                return;
                            }
                            
                            // Format products for Google Pay (same as front.js)
                            const formattedProducts = (response.products || []).map(item => ({
                                type: 'LINE_ITEM',
                                label: item.title || 'Product',
                                status: 'FINAL',
                                price: ((item.priceProductWithoutTax || 0) * (item.quantity || 1)).toString()
                            }));
                            
                            // Calculate tax from products
                            const resultTaxGlobal = (response.products || []).reduce(function(acc, obj) {
                                return acc + ((obj.priceProductWithTax || 0) - (obj.priceProductWithoutTax || 0)) * (obj.quantity || 1);
                            }, 0);
                            
                            // Determine which shipping to use based on shippingWoo setting
                            let shippingData;
                            if (shippingWoo === "false") {
                                // Use global shipping (overrides WooCommerce shipping)
                                shippingData = {
                                    all: [{
                                        id: 0,
                                        title: "Shipping Delivery",
                                        cost_without_tax: (globalShippingWithoutTax || globalShipping || 0).toString(),
                                        cost_with_tax: (globalShippingPriceTax || globalShipping || 0).toString()
                                    }]
                                };
                            } else {
                                // Use WooCommerce shipping
                                shippingData = shippingPrice ? JSON.parse(shippingPrice) : { all: [] };
                            }
                            
                            // Prepare cart data for iframe (same format as classic checkout)
                            const cartData = {
                                startProcess: startProcess, // Only true when iframe requests it
                                host: window.location.host,
                                totalPriceWithoutTax: parseFloat(response.total_without_tax || 0),
                                taxProductsAmount: parseFloat(response.taxGlobal || resultTaxGlobal || 0),
                                currencyCode: currencyCode || 'ILS',
                                shipping: shippingData,
                                products: formattedProducts,
                                discount: parseFloat(response.discountPrice || 0)
                            };
                            
                            // Send to iframe
                            iframe.contentWindow.postMessage(cartData, '*');
                        }
                    },
                    error: function() {
                        console.error('PayPlus: Failed to get cart data for Google Pay iframe');
                    }
                });
            }
        }
        
        // Handle Google Pay payment processing (same as classic checkout)
        function handleGooglePayPayment(paymentData, iframe) {
            if (typeof jQuery === 'undefined' || !ajaxUrl || !frontNonce) {
                return;
            }
            
            const actualPaymentData = paymentData.data?.paymentData || paymentData;
            const phoneNumberField = document.getElementById('phone-number');
            let phoneNumber = '';
            
            if (phoneNumberField && phoneNumberField.required) {
                phoneNumber = phoneNumberField.value || '';
                if (!phoneNumber) {
                    const errorDiv = document.getElementById('error-api-payplus');
                    if (errorDiv) {
                        errorDiv.innerHTML = '<p>' + (expressData.requirePhoneText || 'Phone number is required.') + '</p>';
                    }
                    return;
                }
            }
            
            const additionalData = {
                product_id: '', // Empty for checkout
                page_checkout: true,
                method: 'google-pay',
                quantity: 1,
                token: actualPaymentData?.paymentMethodData?.tokenizationData?.token,
                cardInfo: {
                    info: actualPaymentData?.paymentMethodData?.info || {}
                },
                shipping: actualPaymentData?.shippingOptionData?.id || 'shipping--1',
                paying_vat: true, // TODO: Get from globalPayingVat if available
                contact: {
                    customer_name: actualPaymentData.shippingAddress?.name || '',
                    email: actualPaymentData.email || '',
                    phone: phoneNumber,
                    address: actualPaymentData.shippingAddress?.address1 || '',
                    city: actualPaymentData.shippingAddress?.locality || '',
                    country_ISO: actualPaymentData.shippingAddress?.countryCode || ''
                }
            };
            
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: ajaxUrl,
                data: {
                    action: 'process-payment-oneclick',
                    obj: additionalData,
                    _ajax_nonce: frontNonce
                },
                success: function(response) {
                    if (response.status === true) {
                        iframe.contentWindow.postMessage('PAYMENT_SUCCESS', '*');
                        setTimeout(() => {
                            window.location.href = response.link;
                        }, 500);
                    } else {
                        const errorDiv = document.getElementById('error-api-payplus');
                        if (errorDiv) {
                            errorDiv.innerHTML = '<p>' + (response.payment_response?.data?.message || 'Payment failed') + '</p>';
                        }
                        iframe.contentWindow.postMessage('PAYMENT_ERROR', '*');
                    }
                },
                error: function(jqXHR, textStatus, errorThrow) {
                    const errorDiv = document.getElementById('error-api-payplus');
                    if (errorDiv) {
                        errorDiv.innerHTML = '<p>Error: ' + textStatus + ' - ' + errorThrow + '</p>';
                    }
                    iframe.contentWindow.postMessage('PAYMENT_ERROR', '*');
                }
            });
        }
        
        // Handle Google Pay VAT check request
        function handleGooglePayVatCheck(paymentData, iframe) {
            // TODO: Implement VAT check if needed
            // For now, just respond with default
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage({
                    paying_vat_check: true
                }, '*');
            }
        }
        
        // Load Apple Pay script if needed
        if (payplusData.importApplePayScript && !document.querySelector('script[src*="script.js"]')) {
            const script = document.createElement('script');
            script.src = payplusData.importApplePayScript;
            script.async = true;
            document.head.appendChild(script);
        }

        // Helper function to check if running in Facebook app
        function isFacebookApp() {
            const ua = navigator.userAgent || navigator.vendor || window.opera;
            return (ua.indexOf("FBAN") > -1) || (ua.indexOf("FBAV") > -1);
        }

        // Helper function to check if Apple Pay is available
        function isApplePayAvailable() {
            return window.ApplePaySession && ApplePaySession.canMakePayments();
        }

        const { createElement, useEffect, useRef } = ReactCheck;

        // Create Express Payment Content Component
        const ExpressPaymentContent = (props) => {
            const { buttonAttributes } = props;
            const iframeRef = useRef(null);
            
            // Get button styles from props or use defaults
            const height = buttonAttributes?.height || '48';
            const borderRadius = buttonAttributes?.borderRadius || '4';
            
            // Listen for messages from Google Pay iframe (same as classic checkout)
            useEffect(() => {
                if (!isGoogleEnabled) {
                    return;
                }
                
                const handleMessage = async (event) => {
                    const paymentData = event.data;
                    const iframe = iframeRef.current;
                    
                    if (!iframe || !iframe.contentWindow) {
                        return;
                    }
                    
                    // Check if message is from Google Pay iframe domain
                    if (!googlePayIframeUrl) {
                        return;
                    }
                    
                    try {
                        const iframeDomain = new URL(googlePayIframeUrl).origin;
                        if (event.origin !== iframeDomain) {
                            return;
                        }
                    } catch (e) {
                        // Invalid URL, skip
                        return;
                    }
                    
                    // Handle getCurrentPrice request (iframe wants cart data)
                    if (paymentData && paymentData.oneClickCheckoutGooglePay === 'getCurrentPrice') {
                        sendCartDataToGooglePayIframe(iframe, true); // true = startProcess
                    }
                    
                    // Handle ProcessPayment (payment completed in iframe)
                    if (paymentData && paymentData.oneClickCheckoutGooglePay === 'ProcessPayment') {
                        handleGooglePayPayment(paymentData, iframe);
                    }
                    
                    // Handle getPayingVat request
                    if (paymentData && paymentData.oneClickCheckoutGooglePay === 'getPayingVat') {
                        handleGooglePayVatCheck(paymentData, iframe);
                    }
                };
                
                window.addEventListener('message', handleMessage);
                
                return () => {
                    window.removeEventListener('message', handleMessage);
                };
            }, []);
            
            // Create hidden input fields that Google Pay iframe expects (same as classic checkout)
            const hiddenFields = [];
            
            // Product fields (empty for checkout, but iframe expects them)
            hiddenFields.push(
                createElement('input', {
                    key: 'payplus_pricewt_product',
                    type: 'hidden',
                    id: 'payplus_pricewt_product',
                    value: ''
                }),
                createElement('input', {
                    key: 'payplus_pricewithouttax_product',
                    type: 'hidden',
                    id: 'payplus_pricewithouttax_product',
                    value: ''
                }),
                createElement('input', {
                    key: 'payplus_product_name',
                    type: 'hidden',
                    id: 'payplus_product_name',
                    value: ''
                })
            );
            
            // Shipping and currency fields
            if (shippingPrice) {
                hiddenFields.push(
                    createElement('input', {
                        key: 'payplus_shipping',
                        type: 'hidden',
                        id: 'payplus_shipping',
                        value: shippingPrice
                    })
                );
            }
            
            hiddenFields.push(
                createElement('input', {
                    key: 'payplus_currency_code',
                    type: 'hidden',
                    id: 'payplus_currency_code',
                    value: currencyCode || ''
                }),
                createElement('input', {
                    key: 'payplus_shipping_woo',
                    type: 'hidden',
                    id: 'payplus_shipping_woo',
                    value: shippingWoo || 'true'
                })
            );
            
            // Global shipping fields (if not using WooCommerce shipping)
            if (shippingWoo === "false") {
                hiddenFields.push(
                    createElement('input', {
                        key: 'payplus_price_shipping',
                        type: 'hidden',
                        id: 'payplus_price_shipping',
                        value: globalShipping || ''
                    }),
                    createElement('input', {
                        key: 'payplus_pricewt_shipping',
                        type: 'hidden',
                        id: 'payplus_pricewt_shipping',
                        value: globalShippingPriceTax || ''
                    }),
                    createElement('input', {
                        key: 'payplus_pricewithouttax_shipping',
                        type: 'hidden',
                        id: 'payplus_pricewithouttax_shipping',
                        value: globalShippingWithoutTax || ''
                    })
                );
            }
            
            // Phone number field (if required)
            if (requirePhone) {
                hiddenFields.push(
                    createElement('input', {
                        key: 'phone-number',
                        type: 'text',
                        id: 'phone-number',
                        name: 'phone-number',
                        placeholder: phonePlaceholder || 'Phone number here:',
                        required: true,
                        style: { display: 'none' }
                    })
                );
            }
            
            const containerStyle = {
                display: 'flex',
                flexDirection: 'column',
                gap: '10px',
                marginTop: '15px',
                marginBottom: '15px',
                alignItems: 'center',
                width: '100%'
            };

            const titleContainerStyle = {
                display: 'flex',
                alignItems: 'center',
                width: '100%',
                marginBottom: '10px'
            };

            const lineStyle = {
                flexGrow: 1,
                height: '1px',
                backgroundColor: '#ccc'
            };

            const titleStyle = {
                margin: '0 15px',
                fontSize: '18px',
                fontWeight: 'bold'
            };

            const buttonsContainerStyle = {
                width: '100%',
                display: 'flex',
                flexDirection: 'column',
                gap: '10px'
            };

            const removeGooglePay = isFacebookApp();
            const isAppleAvailable = isApplePayAvailable();
            
            const buttons = [];

            // Google Pay iframe
            if (isGoogleEnabled && !removeGooglePay && googlePayIframeUrl) {
                const date = new Date();
                const currentTimestamp = Math.floor(date.getTime() / 1000);
                const bi = btoa(window.location.origin);
                const iframeSrc = `${googlePayIframeUrl}?var=${currentTimestamp}&wb=${bi}`;
                
                buttons.push(
                    createElement('div', {
                        key: 'google-pay-container',
                        className: 'express-checkout-buttons',
                        style: { width: '100%', marginBottom: '10px' }
                    },
                        createElement('iframe', {
                            key: 'google-pay-iframe',
                            ref: iframeRef,
                            id: 'googlePayButton',
                            src: iframeSrc,
                            'data-product-id': '', // Empty for checkout (not product page)
                            style: { 
                                width: '100%', 
                                height: `${height}px`, 
                                display: 'block', 
                                border: '0',
                                borderRadius: `${borderRadius}px`
                            },
                            frameBorder: '0',
                            allow: 'payment *',
                            sandbox: 'allow-forms allow-scripts allow-same-origin allow-popups allow-top-navigation',
                            allowpaymentrequest: true,
                            loading: 'lazy'
                        })
                    )
                );
            }

            // Apple Pay button
            if (isAppleEnabled && isAppleAvailable) {
                buttons.push(
                    createElement('button', {
                        key: 'apple-pay-button',
                        id: 'applePayButton',
                        lang: 'en',
                        onClick: (e) => {
                            e.preventDefault();
                            if (window.handleApplePayClick) {
                                window.handleApplePayClick(e);
                            }
                        },
                        className: 'apple-pay-button apple-pay-button-with-text apple-pay-button-black-with-text',
                        style: { 
                            padding: '18px', 
                            width: '100%', 
                            display: 'block',
                            height: `${height}px`,
                            borderRadius: `${borderRadius}px`
                        }
                    })
                );
            }

            // Don't render if no buttons are available
            if (buttons.length === 0) {
                return null;
            }

            return createElement('div', { style: containerStyle },
                // Hidden input fields for Google Pay iframe
                ...hiddenFields,
                createElement('div', { style: buttonsContainerStyle }, ...buttons),
                createElement('div', { key: 'error-api-payplus', id: 'error-api-payplus' })
            );
        };

        // Create Express Payment Edit Component
        const ExpressPaymentEdit = (props) => {
            const { PaymentMethodLabel } = props.components;
            return createElement(PaymentMethodLabel, { 
                text: 'Express Checkout (Apple Pay / Google Pay)' 
            });
        };

        // Express Payment Method Configuration
        const ExpressPaymentMethod = {
            name: 'payplus-express-checkout',
            title: 'Express Checkout',
            description: 'Pay quickly with Apple Pay or Google Pay',
            gatewayId: 'payplus-payment-gateway',
            paymentMethodId: 'payplus-express-checkout',
            
            // content must be a React element (created with createElement), not a function
            content: createElement(ExpressPaymentContent),
            edit: createElement(ExpressPaymentEdit),
            
            label: 'Express Checkout',
            ariaLabel: 'Express Checkout',
            
            canMakePayment: (cartData) => {
                // Don't show for subscription orders
                if (payplusData.isSubscriptionOrder) {
                    return false;
                }
                
                const removeGooglePay = isFacebookApp();
                const isAppleAvailable = isApplePayAvailable();
                
                return (isGoogleEnabled && !removeGooglePay) || (isAppleEnabled && isAppleAvailable);
            },
            
            supports: {
                features: ['products'],
            },
        };

        // Register the express payment method
        registerExpressPaymentMethod(ExpressPaymentMethod);
    }

    // Initialize - will retry if React not available
    initExpressPayment();
})();
