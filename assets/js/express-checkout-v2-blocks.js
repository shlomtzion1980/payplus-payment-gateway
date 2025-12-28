/**
 * PayPlus Express Checkout V2 - Blocks Integration
 * Registers with WooCommerce Blocks to show express checkout buttons
 * 
 * @package PayPlus
 * @version 2.0.0
 */

(function() {
    'use strict';
    
    const { registerExpressPaymentMethod } = window.wc.wcBlocksRegistry;
    const { createElement } = window.wp.element;
    const { __ } = window.wp.i18n;
    
    if (typeof registerExpressPaymentMethod === 'undefined') {
        console.warn('PayPlus Express V2 Blocks: WooCommerce Blocks not available');
        return;
    }
    
    // Get settings from PHP
    const settings = window.payplus_express_params || {};
    
    console.log('PayPlus Express V2 Blocks: Initializing...', settings);
    
    /**
     * PayPlus Express Checkout Payment Method for Blocks
     */
    const PayPlusExpressCheckoutMethod = {
        name: 'payplus-express-checkout',
        
        content: createElement('div', {
            className: 'payplus-express-checkout-container',
            'data-context': 'checkout',
            style: { padding: '20px', background: '#f9f9f9', borderRadius: '8px', textAlign: 'center' }
        }, [
            createElement('div', {
                key: 'separator',
                className: 'payplus-express-separator',
                style: { 
                    display: 'flex', 
                    alignItems: 'center', 
                    marginBottom: '15px',
                    color: '#666'
                }
            }, [
                createElement('span', { 
                    key: 'line1',
                    style: { flex: 1, borderBottom: '1px solid #ddd' } 
                }, ''),
                createElement('span', { 
                    key: 'text',
                    style: { padding: '0 15px' } 
                }, __('Or pay with', 'payplus-payment-gateway')),
                createElement('span', { 
                    key: 'line2',
                    style: { flex: 1, borderBottom: '1px solid #ddd' } 
                }, '')
            ]),
            createElement('div', {
                key: 'buttons',
                className: 'payplus-express-buttons',
                style: { 
                    display: 'flex',
                    flexDirection: 'column',
                    gap: '12px',
                    alignItems: 'center',
                    maxWidth: '400px',
                    margin: '0 auto'
                }
            }, [
                settings.google_pay_enabled && createElement('div', {
                    key: 'google-pay',
                    id: 'payplus-google-pay-button',
                    className: 'payplus-express-button payplus-google-pay-button',
                    style: { width: '100%', minHeight: '48px', background: '#fff', borderRadius: '4px' }
                }),
                settings.apple_pay_enabled && createElement('div', {
                    key: 'apple-pay',
                    id: 'payplus-apple-pay-button',
                    className: 'payplus-express-button payplus-apple-pay-button',
                    style: { 
                        width: '100%', 
                        minHeight: '48px',
                        WebkitAppearance: '-apple-pay-button',
                        applePayButtonType: settings.button_type || 'buy',
                        applePayButtonStyle: settings.button_color || 'black'
                    }
                })
            ].filter(Boolean))
        ]),
        
        edit: createElement('div', {
            style: { padding: '20px', textAlign: 'center', border: '2px dashed #ccc', borderRadius: '4px' }
        }, [
            createElement('strong', { key: 'title' }, 'PayPlus Express Checkout'),
            createElement('br', { key: 'br' }),
            createElement('span', { key: 'desc', style: { color: '#666' } }, 
                __('Apple Pay / Google Pay buttons will appear here', 'payplus-payment-gateway'))
        ]),
        
        canMakePayment: () => {
            // Always return true if either payment method is enabled
            // The actual availability will be checked by the button initialization
            const available = settings.apple_pay_enabled || settings.google_pay_enabled;
            console.log('PayPlus Express V2 Blocks: canMakePayment =', available);
            return available;
        },
        
        paymentMethodId: 'payplus-payment-gateway',
        
        supports: {
            features: ['products']
        }
    };
    
    // Register the express payment method
    registerExpressPaymentMethod(PayPlusExpressCheckoutMethod);
    
    console.log('PayPlus Express V2 Blocks: Successfully registered!');
    
    // Wait for DOM and initialize payment buttons
    setTimeout(() => {
        console.log('PayPlus Express V2 Blocks: Attempting to initialize buttons...');
        if (typeof window.PayPlusExpressCheckout === 'function') {
            try {
                new window.PayPlusExpressCheckout();
                console.log('PayPlus Express V2 Blocks: Buttons initialized!');
            } catch (error) {
                console.error('PayPlus Express V2 Blocks: Init error', error);
            }
        } else {
            console.warn('PayPlus Express V2 Blocks: PayPlusExpressCheckout class not found yet, will try again...');
            // Try again after a longer delay
            setTimeout(() => {
                if (typeof window.PayPlusExpressCheckout === 'function') {
                    try {
                        new window.PayPlusExpressCheckout();
                        console.log('PayPlus Express V2 Blocks: Buttons initialized (delayed)!');
                    } catch (error) {
                        console.error('PayPlus Express V2 Blocks: Delayed init error', error);
                    }
                }
            }, 1000);
        }
    }, 500);
    
})();

