/**
 * PayPlus Express Checkout V2 - Blocks Integration
 * Renders Apple Pay and Google Pay buttons in WooCommerce Blocks checkout
 * 
 * This script DOES NOT use registerExpressPaymentMethod because ECE buttons
 * are not payment methods themselves - they are part of the checkout experience.
 * 
 * @package PayPlus
 * @version 2.0.0
 */

(function($) {
	'use strict';
	
	console.log('PayPlus Express V2 Blocks: Script loaded');
	
	// Get configuration from localized data (use both possible names)
	const config = window.payplus_express_params || window.wc_payplus_express_checkout_v2_blocks_params || {};
	console.log('PayPlus Express V2 Blocks: Config:', config);
	
	// If config is empty, wait for it to be available
	if (!config.ajax_url) {
		console.log('PayPlus Express V2 Blocks: Config not ready, will retry');
	}
	
	/**
	 * Wait for the blocks checkout to be ready, then inject our buttons
	 */
	function initExpressCheckoutForBlocks() {
		console.log('PayPlus Express V2 Blocks: Initializing for blocks');
		
		// Look for the checkout form container
		const checkoutForm = document.querySelector('.wc-block-checkout__form');
		
		if (!checkoutForm) {
			console.log('PayPlus Express V2 Blocks: Checkout form not found, retrying...');
			setTimeout(initExpressCheckoutForBlocks, 500);
			return;
		}
		
		console.log('PayPlus Express V2 Blocks: Checkout form found');
		
		// Check if we should show express checkout
		if (!config.apple_pay_enabled && !config.google_pay_enabled) {
			console.log('PayPlus Express V2 Blocks: No express payment methods enabled');
			return;
		}
		
		// Check if buttons already exist (to avoid duplicates)
		if (document.getElementById('payplus-apple-pay-button') || document.getElementById('payplus-google-pay-button')) {
			console.log('PayPlus Express V2 Blocks: Buttons already exist');
			return;
		}
		
		// Create the container for express checkout buttons with actual button elements
		const wrapper = document.createElement('div');
		wrapper.id = 'payplus-express-checkout-v2-wrapper';
		wrapper.style.marginBottom = '20px';
		
		// Build the button HTML
		let buttonsHtml = '<div class="payplus-express-buttons">';
		
		if (config.apple_pay_enabled) {
			buttonsHtml += '<div id="payplus-apple-pay-button" class="payplus-express-button payplus-apple-pay-button"></div>';
		}
		
		if (config.google_pay_enabled) {
			buttonsHtml += '<div id="payplus-google-pay-button" class="payplus-express-button payplus-google-pay-button"></div>';
		}
		
		buttonsHtml += '</div>';
		
		wrapper.innerHTML = `
			<div class="payplus-express-checkout-container" data-context="checkout">
				<div class="payplus-express-separator">
					<span>Or pay with</span>
				</div>
				${buttonsHtml}
				<div class="payplus-express-loading" style="display: none;">
					<span class="spinner"></span>
					<span class="text">Processing...</span>
				</div>
			</div>
		`;
		
		// Insert the wrapper at the beginning of the checkout form
		checkoutForm.insertBefore(wrapper, checkoutForm.firstChild);
		
		console.log('PayPlus Express V2 Blocks: Container injected into DOM');
		
		// Initialize the PayPlusExpressCheckout class
		if (window.PayPlusExpressCheckout) {
			console.log('PayPlus Express V2 Blocks: Initializing PayPlusExpressCheckout');
			window.payplusExpressCheckout = new window.PayPlusExpressCheckout();
		} else {
			console.error('PayPlus Express V2 Blocks: PayPlusExpressCheckout class not found');
		}
	}
	
	// Wait for DOM to be ready, then initialize
	$(document).ready(function() {
		console.log('PayPlus Express V2 Blocks: DOM ready');
		
		// Start initialization after a short delay to ensure blocks are rendered
		setTimeout(initExpressCheckoutForBlocks, 1000);
		
		// Also observe for dynamic changes in case blocks re-render
		const observer = new MutationObserver(function(mutations) {
			// Check if the checkout form is present but our buttons are not
			const checkoutForm = document.querySelector('.wc-block-checkout__form');
			const ourButtons = document.querySelector('#payplus-apple-pay-button, #payplus-google-pay-button');
			
			if (checkoutForm && !ourButtons) {
				console.log('PayPlus Express V2 Blocks: Blocks re-rendered, re-initializing');
				initExpressCheckoutForBlocks();
			}
		});
		
		// Observe the entire document for changes
		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
		
		console.log('PayPlus Express V2 Blocks: Observer started');
	});
	
})(jQuery);
