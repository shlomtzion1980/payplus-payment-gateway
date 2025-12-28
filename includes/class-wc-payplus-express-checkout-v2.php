<?php
/**
 * PayPlus Express Checkout V2
 * Modern implementation for Apple Pay and Google Pay
 * Supports both Classic Checkout and Block-based Checkout
 *
 * @package PayPlus
 * @version 2.0.0
 */

defined('ABSPATH') || exit;

class WC_PayPlus_Express_Checkout_V2 {

    /**
     * Singleton instance
     *
     * @var WC_PayPlus_Express_Checkout_V2
     */
    private static $instance = null;

    /**
     * Gateway settings
     *
     * @var object
     */
    private $settings;

    /**
     * Apple Pay enabled
     *
     * @var bool
     */
    private $apple_pay_enabled = false;

    /**
     * Google Pay enabled
     *
     * @var bool
     */
    private $google_pay_enabled = false;

    /**
     * API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * Secret Key
     *
     * @var string
     */
    private $secret_key;

    /**
     * Test mode
     *
     * @var bool
     */
    private $test_mode = false;

    /**
     * Merchant identifier for Apple Pay
     *
     * @var string
     */
    private $apple_merchant_id;

    /**
     * Google Pay merchant ID
     *
     * @var string
     */
    private $google_merchant_id;

    /**
     * Display locations
     *
     * @var array
     */
    private $display_locations = [];

    /**
     * Get singleton instance
     *
     * @return WC_PayPlus_Express_Checkout_V2
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_settings();
        
        if (!$this->is_available()) {
            return;
        }

        $this->init_hooks();
    }

    /**
     * Load settings
     */
    private function load_settings() {
        $settings = get_option('woocommerce_payplus-payment-gateway_settings', []);
        $this->settings = (object) $settings;

        // Test mode
        $this->test_mode = isset($settings['api_test_mode']) && $settings['api_test_mode'] === 'yes';

        // API credentials
        $this->api_key = $this->test_mode 
            ? ($settings['dev_api_key'] ?? '') 
            : ($settings['api_key'] ?? '');
        
        $this->secret_key = $this->test_mode 
            ? ($settings['dev_secret_key'] ?? '') 
            : ($settings['secret_key'] ?? '');

        // Express checkout settings
        $this->apple_pay_enabled = isset($settings['express_apple_pay_enabled']) 
            && $settings['express_apple_pay_enabled'] === 'yes';
        
        $this->google_pay_enabled = isset($settings['express_google_pay_enabled']) 
            && $settings['express_google_pay_enabled'] === 'yes';

        // Merchant identifiers
        $this->apple_merchant_id = $settings['apple_merchant_identifier'] ?? '';
        $this->google_merchant_id = $settings['google_merchant_id'] ?? '';

        // Display locations
        $locations = $settings['express_display_locations'] ?? ['product', 'cart', 'checkout'];
        $this->display_locations = is_array($locations) ? $locations : explode(',', $locations);
        
        // Debug logging
        $this->log('Express Checkout V2 - Settings Loaded', [
            'apple_pay_enabled' => $this->apple_pay_enabled,
            'google_pay_enabled' => $this->google_pay_enabled,
            'api_key_set' => !empty($this->api_key),
            'secret_key_set' => !empty($this->secret_key),
            'display_locations' => $this->display_locations,
            'test_mode' => $this->test_mode
        ]);
    }

    /**
     * Check if express checkout is available
     *
     * @return bool
     */
    public function is_available() {
        if (!class_exists('WooCommerce')) {
            $this->log('Express Checkout V2 - Not Available', ['reason' => 'WooCommerce not active']);
            return false;
        }

        if (empty($this->api_key) || empty($this->secret_key)) {
            $this->log('Express Checkout V2 - Not Available', [
                'reason' => 'Missing API credentials',
                'api_key' => !empty($this->api_key),
                'secret_key' => !empty($this->secret_key)
            ]);
            return false;
        }

        $available = $this->apple_pay_enabled || $this->google_pay_enabled;
        
        $this->log('Express Checkout V2 - Availability Check', [
            'available' => $available,
            'apple_pay_enabled' => $this->apple_pay_enabled,
            'google_pay_enabled' => $this->google_pay_enabled
        ]);
        
        return $available;
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        $this->log('Express Checkout V2 - Initializing Hooks', [
            'display_locations' => $this->display_locations
        ]);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Display buttons on different locations
        $this->add_button_display_hooks();

        // AJAX handlers
        add_action('wp_ajax_payplus_express_create_order', [$this, 'ajax_create_order']);
        add_action('wp_ajax_nopriv_payplus_express_create_order', [$this, 'ajax_create_order']);
        
        add_action('wp_ajax_payplus_express_update_shipping', [$this, 'ajax_update_shipping']);
        add_action('wp_ajax_nopriv_payplus_express_update_shipping', [$this, 'ajax_update_shipping']);
        
        add_action('wp_ajax_payplus_express_process_payment', [$this, 'ajax_process_payment']);
        add_action('wp_ajax_nopriv_payplus_express_process_payment', [$this, 'ajax_process_payment']);

        // Apple Pay domain verification
        add_action('init', [$this, 'handle_apple_pay_domain_verification']);
    }

    /**
     * Add hooks to display buttons in different locations
     */
    private function add_button_display_hooks() {
        $this->log('Express Checkout V2 - Adding Display Hooks', [
            'display_locations' => $this->display_locations
        ]);
        
        // Product page
        if (in_array('product', $this->display_locations, true)) {
            add_action('woocommerce_after_add_to_cart_button', [$this, 'display_express_buttons'], 10);
            $this->log('Express Checkout V2 - Added hook: woocommerce_after_add_to_cart_button');
        }

        // Cart page
        if (in_array('cart', $this->display_locations, true)) {
            add_action('woocommerce_proceed_to_checkout', [$this, 'display_express_buttons'], 5);
            $this->log('Express Checkout V2 - Added hook: woocommerce_proceed_to_checkout');
        }

        // Checkout page - Classic ONLY for now (blocks needs different approach)
        if (in_array('checkout', $this->display_locations, true)) {
            add_action('woocommerce_before_checkout_form', [$this, 'display_express_buttons'], 5);
            $this->log('Express Checkout V2 - Added hook: woocommerce_before_checkout_form (classic only)');
            
            // TODO: Blocks checkout needs proper React integration, not HTML injection
            // For now, we'll focus on classic checkout working perfectly
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        $should_display = $this->should_display_buttons();
        $has_checkout_block = has_block('woocommerce/checkout');
        $has_cart_block = has_block('woocommerce/cart');
        
        $this->log('Express Checkout V2 - Enqueue Scripts Check', [
            'should_display' => $should_display,
            'has_checkout_block' => $has_checkout_block,
            'has_cart_block' => $has_cart_block,
            'is_checkout' => is_checkout(),
            'is_cart' => is_cart(),
            'is_product' => is_product()
        ]);
        
        // Check if we should display on current page or in blocks context
        if (!$should_display && !$has_checkout_block && !$has_cart_block) {
            $this->log('Express Checkout V2 - Scripts NOT Enqueued', ['reason' => 'Conditions not met']);
            return;
        }

        $this->log('Express Checkout V2 - Enqueuing Scripts');

        // Enqueue styles
        wp_enqueue_style(
            'payplus-express-checkout',
            PAYPLUS_PLUGIN_URL . 'assets/css/express-checkout-v2.css',
            [],
            PAYPLUS_VERSION
        );

        // Enqueue main script (required for both classic and blocks)
        wp_enqueue_script(
            'payplus-express-checkout',
            PAYPLUS_PLUGIN_URL . 'assets/js/express-checkout-v2.js',
            ['jquery'],
            PAYPLUS_VERSION,
            true
        );

        // Apple Pay JS (required for Apple Pay)
        if ($this->apple_pay_enabled) {
            wp_enqueue_script(
                'apple-pay-sdk',
                'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js',
                [],
                null,
                true
            );
        }

        // Google Pay JS (required for Google Pay)
        if ($this->google_pay_enabled) {
            wp_enqueue_script(
                'google-pay-sdk',
                'https://pay.google.com/gp/p/js/pay.js',
                [],
                null,
                true
            );
        }

        // Localize script with data (for both classic and blocks)
        wp_localize_script('payplus-express-checkout', 'payplus_express_params', $this->get_javascript_params());
        
        // Expose the params globally for blocks script
        wp_localize_script('payplus-express-checkout', 'wc_payplus_express_checkout_v2_blocks_params', array_merge(
            $this->get_javascript_params(),
            [
                'shouldShowExpressCheckoutButton' => in_array('checkout', $this->display_locations, true),
                'isBlocksCheckout' => $has_checkout_block,
            ]
        ));
        
        // Expose PayPlusExpressCheckout class globally for blocks to use
        wp_add_inline_script('payplus-express-checkout', '
            // Make PayPlusExpressCheckout available globally
            window.PayPlusExpressCheckout = window.PayPlusExpressCheckout || {};
        ', 'after');
    }

    /**
     * Check if buttons should be displayed on current page
     *
     * @return bool
     */
    private function should_display_buttons() {
        // Product page
        if (is_product() && in_array('product', $this->display_locations, true)) {
            return true;
        }

        // Cart page
        if (is_cart() && in_array('cart', $this->display_locations, true)) {
            return true;
        }

        // Checkout page (Classic or Blocks)
        if (is_checkout() && !is_order_received_page() && in_array('checkout', $this->display_locations, true)) {
            return true;
        }

        // Also check if we're in a WooCommerce block context
        if (in_array('checkout', $this->display_locations, true) && 
            (did_action('woocommerce_blocks_checkout_before_order_summary') || 
             doing_action('woocommerce_blocks_checkout_before_order_summary'))) {
            return true;
        }

        return false;
    }

    /**
     * Display express checkout buttons
     */
    public function display_express_buttons() {
        if (!$this->should_display_buttons()) {
            $this->log('Express Checkout V2 - Buttons NOT Displayed', ['reason' => 'should_display_buttons returned false']);
            return;
        }

        $context = $this->get_current_context();
        
        $this->log('Express Checkout V2 - Displaying Buttons', [
            'context' => $context,
            'apple_pay_enabled' => $this->apple_pay_enabled,
            'google_pay_enabled' => $this->google_pay_enabled
        ]);
        
        ?>
        <div class="payplus-express-checkout-container" data-context="<?php echo esc_attr($context); ?>">
            <div class="payplus-express-separator">
                <span><?php esc_html_e('Or pay with', 'payplus-payment-gateway'); ?></span>
            </div>
            
            <div class="payplus-express-buttons">
                <?php if ($this->apple_pay_enabled) : ?>
                    <div id="payplus-apple-pay-button" class="payplus-express-button payplus-apple-pay-button"></div>
                <?php endif; ?>
                
                <?php if ($this->google_pay_enabled) : ?>
                    <div id="payplus-google-pay-button" class="payplus-express-button payplus-google-pay-button"></div>
                <?php endif; ?>
            </div>

            <div class="payplus-express-loading" style="display: none;">
                <span class="spinner"></span>
                <span class="text"><?php esc_html_e('Processing...', 'payplus-payment-gateway'); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Get current page context
     *
     * @return string
     */
    private function get_current_context() {
        if (is_product()) {
            return 'product';
        } elseif (is_cart()) {
            return 'cart';
        } elseif (is_checkout()) {
            return 'checkout';
        }
        return 'unknown';
    }

    /**
     * AJAX: Create order data for express checkout
     */
    public function ajax_create_order() {
        check_ajax_referer('payplus_express_checkout', 'nonce');

        try {
            $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'cart';
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

            // Get order data based on context
            if ($context === 'product' && $product_id > 0) {
                $order_data = $this->get_product_order_data($product_id, $quantity);
            } else {
                $order_data = $this->get_cart_order_data();
            }

            wp_send_json_success($order_data);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Get order data for product page
     *
     * @param int $product_id
     * @param int $quantity
     * @return array
     */
    private function get_product_order_data($product_id, $quantity = 1) {
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_purchasable()) {
            throw new Exception(__('Product is not available.', 'payplus-payment-gateway'));
        }

        $total = $product->get_price() * $quantity;
        $tax = 0;

        if (wc_tax_enabled()) {
            $tax_rates = WC_Tax::get_rates($product->get_tax_class());
            $taxes = WC_Tax::calc_tax($total, $tax_rates);
            $tax = array_sum($taxes);
        }

        return [
            'total' => [
                'label' => get_bloginfo('name'),
                'amount' => number_format($total + $tax, 2, '.', ''),
                'type' => 'final'
            ],
            'displayItems' => [
                [
                    'label' => $product->get_name() . ' x ' . $quantity,
                    'amount' => number_format($total, 2, '.', '')
                ]
            ],
            'shippingRequired' => !$product->is_virtual(),
            'requestShipping' => !$product->is_virtual(),
            'requestBilling' => true,
            'requestEmail' => true,
            'requestPhone' => true
        ];
    }

    /**
     * Get order data from cart
     *
     * @return array
     */
    private function get_cart_order_data() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            throw new Exception(__('Cart is empty.', 'payplus-payment-gateway'));
        }

        WC()->cart->calculate_totals();

        $display_items = [];
        
        // Add cart items
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $display_items[] = [
                'label' => $product->get_name() . ' x ' . $cart_item['quantity'],
                'amount' => number_format($cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'], 2, '.', '')
            ];
        }

        // Check if shipping is required
        $shipping_required = WC()->cart->needs_shipping();

        // Add shipping if calculated
        if ($shipping_required && WC()->cart->get_shipping_total() > 0) {
            $display_items[] = [
                'label' => __('Shipping', 'payplus-payment-gateway'),
                'amount' => number_format(WC()->cart->get_shipping_total() + WC()->cart->get_shipping_tax(), 2, '.', '')
            ];
        }

        // Add fees
        foreach (WC()->cart->get_fees() as $fee) {
            $display_items[] = [
                'label' => $fee->name,
                'amount' => number_format($fee->total + $fee->tax, 2, '.', '')
            ];
        }

        $total = WC()->cart->get_total('');

        return [
            'total' => [
                'label' => get_bloginfo('name'),
                'amount' => number_format($total, 2, '.', ''),
                'type' => 'final'
            ],
            'displayItems' => $display_items,
            'shippingRequired' => $shipping_required,
            'requestShipping' => $shipping_required,
            'requestBilling' => true,
            'requestEmail' => true,
            'requestPhone' => true
        ];
    }

    /**
     * AJAX: Update shipping method
     */
    public function ajax_update_shipping() {
        check_ajax_referer('payplus_express_checkout', 'nonce');

        try {
            $shipping_address = isset($_POST['shipping_address']) ? $_POST['shipping_address'] : [];
            
            if (empty($shipping_address)) {
                throw new Exception(__('Shipping address is required.', 'payplus-payment-gateway'));
            }

            // Calculate shipping
            $shipping_options = $this->calculate_shipping($shipping_address);

            wp_send_json_success(['shippingOptions' => $shipping_options]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Calculate shipping options
     *
     * @param array $address
     * @return array
     */
    private function calculate_shipping($address) {
        $packages = WC()->cart->get_shipping_packages();
        $package = reset($packages);

        // Set customer location
        WC()->customer->set_shipping_country($address['countryCode'] ?? '');
        WC()->customer->set_shipping_state($address['administrativeArea'] ?? '');
        WC()->customer->set_shipping_postcode($address['postalCode'] ?? '');
        WC()->customer->set_shipping_city($address['locality'] ?? '');

        // Calculate shipping
        WC()->cart->calculate_shipping();
        
        $shipping_options = [];
        $available_methods = WC()->shipping()->calculate_shipping_for_package($package);

        if (!empty($available_methods['rates'])) {
            foreach ($available_methods['rates'] as $rate) {
                $shipping_options[] = [
                    'id' => $rate->get_id(),
                    'label' => $rate->get_label(),
                    'amount' => number_format($rate->get_cost() + $rate->get_shipping_tax(), 2, '.', ''),
                    'detail' => $rate->get_method_title()
                ];
            }
        } else {
            // No shipping methods available
            $shipping_options[] = [
                'id' => 'no_shipping',
                'label' => __('No shipping available', 'payplus-payment-gateway'),
                'amount' => '0.00',
                'detail' => ''
            ];
        }

        return $shipping_options;
    }

    /**
     * AJAX: Process payment
     */
    public function ajax_process_payment() {
        check_ajax_referer('payplus_express_checkout', 'nonce');

        try {
            $payment_data = isset($_POST['payment_data']) ? $_POST['payment_data'] : [];
            $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'cart';
            
            if (empty($payment_data)) {
                throw new Exception(__('Payment data is required.', 'payplus-payment-gateway'));
            }

            // Create WooCommerce order
            $order_id = $this->create_woocommerce_order($payment_data, $context);
            
            if (!$order_id) {
                throw new Exception(__('Failed to create order.', 'payplus-payment-gateway'));
            }

            // Process payment with PayPlus
            $result = $this->process_payplus_payment($order_id, $payment_data);

            wp_send_json_success([
                'order_id' => $order_id,
                'redirect_url' => $this->get_return_url($order_id),
                'result' => $result
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Create WooCommerce order from payment data
     *
     * @param array $payment_data
     * @param string $context
     * @return int Order ID
     */
    private function create_woocommerce_order($payment_data, $context) {
        $order = wc_create_order();
        
        if (!$order) {
            throw new Exception(__('Failed to create order.', 'payplus-payment-gateway'));
        }

        // Add items to order
        if ($context === 'product') {
            // Handle product page purchase
            $product_id = isset($payment_data['product_id']) ? absint($payment_data['product_id']) : 0;
            $quantity = isset($payment_data['quantity']) ? absint($payment_data['quantity']) : 1;
            
            $product = wc_get_product($product_id);
            if ($product) {
                $order->add_product($product, $quantity);
            }
        } else {
            // Add cart items to order
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $order->add_product(
                    $cart_item['data'],
                    $cart_item['quantity'],
                    [
                        'subtotal' => $cart_item['line_subtotal'],
                        'total' => $cart_item['line_total'],
                        'subtotal_tax' => $cart_item['line_subtotal_tax'],
                        'total_tax' => $cart_item['line_tax']
                    ]
                );
            }
        }

        // Set billing address
        if (isset($payment_data['billingContact'])) {
            $billing = $payment_data['billingContact'];
            $order->set_billing_first_name($billing['givenName'] ?? '');
            $order->set_billing_last_name($billing['familyName'] ?? '');
            $order->set_billing_email($billing['emailAddress'] ?? '');
            $order->set_billing_phone($billing['phoneNumber'] ?? '');
            
            if (isset($billing['addressLines'])) {
                $order->set_billing_address_1($billing['addressLines'][0] ?? '');
                $order->set_billing_address_2($billing['addressLines'][1] ?? '');
            }
            
            $order->set_billing_city($billing['locality'] ?? '');
            $order->set_billing_state($billing['administrativeArea'] ?? '');
            $order->set_billing_postcode($billing['postalCode'] ?? '');
            $order->set_billing_country($billing['countryCode'] ?? '');
        }

        // Set shipping address
        if (isset($payment_data['shippingContact'])) {
            $shipping = $payment_data['shippingContact'];
            $order->set_shipping_first_name($shipping['givenName'] ?? '');
            $order->set_shipping_last_name($shipping['familyName'] ?? '');
            
            if (isset($shipping['addressLines'])) {
                $order->set_shipping_address_1($shipping['addressLines'][0] ?? '');
                $order->set_shipping_address_2($shipping['addressLines'][1] ?? '');
            }
            
            $order->set_shipping_city($shipping['locality'] ?? '');
            $order->set_shipping_state($shipping['administrativeArea'] ?? '');
            $order->set_shipping_postcode($shipping['postalCode'] ?? '');
            $order->set_shipping_country($shipping['countryCode'] ?? '');
        }

        // Add shipping
        if (isset($payment_data['shippingMethod'])) {
            $shipping_method = $payment_data['shippingMethod'];
            $item = new WC_Order_Item_Shipping();
            $item->set_method_title($shipping_method['label'] ?? '');
            $item->set_method_id($shipping_method['id'] ?? '');
            $item->set_total($shipping_method['amount'] ?? 0);
            $order->add_item($item);
        }

        // Set payment method
        $order->set_payment_method('payplus-payment-gateway');
        $order->set_payment_method_title(__('Express Checkout', 'payplus-payment-gateway'));

        // Add express checkout metadata
        $order->update_meta_data('_payplus_express_checkout', 'yes');
        $order->update_meta_data('_payplus_express_type', $payment_data['paymentMethod'] ?? 'unknown');

        // Calculate totals
        $order->calculate_totals();
        $order->save();

        // Empty cart if from cart/checkout
        if (in_array($context, ['cart', 'checkout'], true)) {
            WC()->cart->empty_cart();
        }

        return $order->get_id();
    }

    /**
     * Process payment with PayPlus
     *
     * @param int $order_id
     * @param array $payment_data
     * @return array
     */
    private function process_payplus_payment($order_id, $payment_data) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            throw new Exception(__('Order not found.', 'payplus-payment-gateway'));
        }

        // Build PayPlus payment request
        $payment_request = [
            'payment_page_uid' => '', // Will be generated by PayPlus
            'amount' => $order->get_total(),
            'currency_code' => $order->get_currency(),
            'customer' => [
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone()
            ],
            'payment_method' => [
                'type' => $payment_data['paymentMethod'] ?? 'apple_pay',
                'token' => $payment_data['token'] ?? ''
            ],
            'more_info' => $order_id,
            'success_url' => $this->get_return_url($order_id),
            'failure_url' => wc_get_checkout_url()
        ];

        // Call PayPlus API
        $response = $this->call_payplus_api('/v1/payments/charge', $payment_request);

        if ($response && isset($response['status']) && $response['status'] === 'success') {
            // Payment successful
            $order->payment_complete($response['transaction_uid'] ?? '');
            $order->add_order_note(
                sprintf(
                    __('PayPlus Express Checkout payment completed. Transaction ID: %s', 'payplus-payment-gateway'),
                    $response['transaction_uid'] ?? ''
                )
            );

            return [
                'status' => 'success',
                'transaction_id' => $response['transaction_uid'] ?? ''
            ];
        } else {
            // Payment failed
            $error_message = $response['message'] ?? __('Payment failed', 'payplus-payment-gateway');
            $order->update_status('failed', $error_message);
            throw new Exception($error_message);
        }
    }

    /**
     * Call PayPlus API
     *
     * @param string $endpoint
     * @param array $data
     * @return array|false
     */
    private function call_payplus_api($endpoint, $data) {
        $api_url = $this->test_mode 
            ? 'https://restapidev.payplus.co.il'
            : 'https://restapi.payplus.co.il';

        $url = $api_url . $endpoint;

        $args = [
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => wp_json_encode([
                    'api_key' => $this->api_key,
                    'secret_key' => $this->secret_key
                ])
            ],
            'method' => 'POST',
            'timeout' => 45
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Get return URL after successful payment
     *
     * @param int $order_id
     * @return string
     */
    private function get_return_url($order_id) {
        $order = wc_get_order($order_id);
        return $order ? $order->get_checkout_order_received_url() : wc_get_page_permalink('checkout');
    }

    /**
     * Handle Apple Pay domain verification
     */
    public function handle_apple_pay_domain_verification() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
        
        if (strpos($request_uri, '.well-known/apple-developer-merchantid-domain-association') !== false) {
            $file = PAYPLUS_PLUGIN_DIR . '/apple-developer-merchantid-domain-association';
            
            if (file_exists($file)) {
                header('Content-Type: text/plain');
                readfile($file);
                exit;
            }
        }
    }
    
    /**
     * Check if Apple Pay V2 is enabled
     *
     * @return bool
     */
    public function is_apple_pay_v2_enabled() {
        return $this->apple_pay_enabled;
    }

    /**
     * Check if Google Pay V2 is enabled
     *
     * @return bool
     */
    public function is_google_pay_v2_enabled() {
        return $this->google_pay_enabled;
    }

    /**
     * Get JavaScript params for localization
     *
     * @return array
     */
    public function get_javascript_params() {
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('payplus_express_checkout'),
            'apple_pay_enabled' => $this->apple_pay_enabled,
            'google_pay_enabled' => $this->google_pay_enabled,
            'apple_merchant_id' => $this->apple_merchant_id,
            'google_merchant_id' => $this->google_merchant_id,
            'google_gateway' => 'payplus',
            'google_gateway_merchant_id' => $this->api_key,
            'test_mode' => $this->test_mode,
            'environment' => $this->test_mode ? 'TEST' : 'PRODUCTION',
            'country_code' => substr(get_option('woocommerce_default_country'), 0, 2),
            'currency_code' => get_woocommerce_currency(),
            'store_name' => get_bloginfo('name'),
            'button_type' => $this->settings->express_button_type ?? 'buy',
            'button_color' => $this->settings->express_button_color ?? 'black',
            'button_height' => $this->settings->express_button_height ?? '48',
            'i18n' => [
                'error_generic' => __('An error occurred. Please try again.', 'payplus-payment-gateway'),
                'error_shipping' => __('Unable to calculate shipping. Please use regular checkout.', 'payplus-payment-gateway'),
                'error_payment' => __('Payment failed. Please try again.', 'payplus-payment-gateway'),
            ]
        ];
    }
    
    /**
     * Log debug information
     *
     * @param string $message
     * @param array $data
     */
    private function log($message, $data = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_message = $message;
        if (!empty($data)) {
            $log_message .= ' | Data: ' . wp_json_encode($data);
        }
        
        error_log('PayPlus Express V2: ' . $log_message);
    }
}

// Initialize
WC_PayPlus_Express_Checkout_V2::get_instance();

