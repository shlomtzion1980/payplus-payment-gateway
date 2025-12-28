<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined('ABSPATH') || exit;

/**
 * WC_PayPlus_Express_Checkout_V2_Blocks_Support class.
 *
 * @extends AbstractPaymentMethodType
 */
final class WC_PayPlus_Express_Checkout_V2_Blocks_Support extends AbstractPaymentMethodType {
	
	/**
	 * Payment method name
	 *
	 * @var string
	 */
	protected $name = 'payplus-express-checkout-v2';
	
	/**
	 * Express Checkout configuration
	 *
	 * @var WC_PayPlus_Express_Checkout_V2
	 */
	private $express_checkout_configuration;
	
	/**
	 * Constructor
	 *
	 * @param WC_PayPlus_Express_Checkout_V2 $express_checkout_configuration
	 */
	public function __construct($express_checkout_configuration = null) {
		error_log('PayPlus Express V2 Blocks: Constructor called');
		$this->express_checkout_configuration = $express_checkout_configuration 
			?: WC_PayPlus_Express_Checkout_V2::get_instance();
	}
	
	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		error_log('PayPlus Express V2 Blocks: Initialize called');
		$this->settings = get_option('woocommerce_payplus-payment-gateway_settings', []);
	}
	
	/**
	 * Returns if this payment method should be active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		$gateway_settings = $this->settings;
		
		// Check if PayPlus gateway is enabled
		if (empty($gateway_settings['enabled']) || 'yes' !== $gateway_settings['enabled']) {
			error_log('PayPlus Express V2 Blocks: Gateway not enabled');
			return false;
		}
		
		// Check if Express Checkout V2 is enabled (Apple Pay or Google Pay)
		$apple_pay_enabled = !empty($gateway_settings['express_apple_pay_enabled']) 
			&& 'yes' === $gateway_settings['express_apple_pay_enabled'];
		$google_pay_enabled = !empty($gateway_settings['express_google_pay_enabled']) 
			&& 'yes' === $gateway_settings['express_google_pay_enabled'];
		
		$is_active = $apple_pay_enabled || $google_pay_enabled;
		
		error_log('PayPlus Express V2 Blocks: is_active = ' . ($is_active ? 'true' : 'false') . ' | Apple Pay: ' . ($apple_pay_enabled ? 'yes' : 'no') . ' | Google Pay: ' . ($google_pay_enabled ? 'yes' : 'no'));
		
		return $is_active;
	}
	
	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		error_log('PayPlus Express V2 Blocks: get_payment_method_script_handles called');
		
		// Enqueue Apple Pay SDK if enabled
		if ($this->express_checkout_configuration->is_apple_pay_v2_enabled()) {
			wp_register_script(
				'apple-pay-sdk',
				'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js',
				[],
				null,
				true
			);
			error_log('PayPlus Express V2 Blocks: Apple Pay SDK registered');
		}
		
		// Enqueue Google Pay SDK if enabled
		if ($this->express_checkout_configuration->is_google_pay_v2_enabled()) {
			wp_register_script(
				'google-pay-sdk',
				'https://pay.google.com/gp/p/js/pay.js',
				[],
				null,
				true
			);
			error_log('PayPlus Express V2 Blocks: Google Pay SDK registered');
		}
		
		$this->register_blocks_script_handles();
		
		error_log('PayPlus Express V2 Blocks: Returning script handles');
		return ['payplus-express-checkout-v2-blocks'];
	}
	
	/**
	 * Registers the blocks JS scripts.
	 */
	private function register_blocks_script_handles() {
		$version = PAYPLUS_VERSION;
		$dependencies = ['wp-element', 'wp-i18n', 'wc-blocks-registry'];
		
		// Add SDK dependencies if enabled
		if ($this->express_checkout_configuration->is_apple_pay_v2_enabled()) {
			$dependencies[] = 'apple-pay-sdk';
		}
		if ($this->express_checkout_configuration->is_google_pay_v2_enabled()) {
			$dependencies[] = 'google-pay-sdk';
		}
		
		$script_url = PAYPLUS_PLUGIN_URL . 'assets/js/express-checkout-v2-blocks.js';
		error_log('PayPlus Express V2 Blocks: Registering script at: ' . $script_url);
		error_log('PayPlus Express V2 Blocks: Dependencies: ' . wp_json_encode($dependencies));
		
		wp_register_script(
			'payplus-express-checkout-v2-blocks',
			$script_url,
			$dependencies,
			$version,
			true
		);
		
		// Localize the script with configuration data
		// This is crucial - the JS looks for this variable
		$config_data = $this->get_payment_method_data();
		wp_localize_script(
			'payplus-express-checkout-v2-blocks',
			'wc_payplus_express_checkout_v2_blocks_params',
			$config_data
		);
		
		error_log('PayPlus Express V2 Blocks: Localized script with data: ' . wp_json_encode($config_data));
		
		wp_set_script_translations(
			'payplus-express-checkout-v2-blocks',
			'payplus-payment-gateway'
		);
		
		error_log('PayPlus Express V2 Blocks: Script registered');
	}
	
	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		error_log('PayPlus Express V2 Blocks: get_payment_method_data called');
		
		$data = array_merge(
			$this->express_checkout_configuration->get_javascript_params(),
			[
				'shouldShowExpressCheckoutButton' => $this->should_show_express_checkout_button(),
				'isBlocksCheckout' => true,
			]
		);
		
		error_log('PayPlus Express V2 Blocks: Returning payment method data: ' . wp_json_encode($data));
		
		return $data;
	}
	
	/**
	 * Returns true if the Express Checkout should be shown.
	 *
	 * @return boolean
	 */
	private function should_show_express_checkout_button() {
		// Check if checkout is in display locations
		$display_locations = $this->settings['express_display_locations'] ?? [];
		
		if (is_string($display_locations)) {
			$display_locations = array_map('trim', explode(',', $display_locations));
		}
		
		return in_array('checkout', $display_locations, true);
	}
	
	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		// Express Checkout doesn't support these features directly
		// as it's processed by Apple Pay / Google Pay
		return [];
	}
}

