<?php

/**
 * Plugin Name: PayPlus Payment Gateway
 * Description: Accept credit/debit card payments or other methods such as bit, Apple Pay, Google Pay in one page. Create digitally signed invoices & much more.
 * Plugin URI: https://www.payplus.co.il/wordpress
 * Version: 7.1.5
 * Tested up to: 6.6.2
 * Requires Plugins: woocommerce
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: PayPlus LTD
 * Author URI: https://www.payplus.co.il/
 * License: GPLv2 or later
 * Text Domain: payplus-payment-gateway
 */

defined('ABSPATH') or die('Hey, You can\'t access this file!'); // Exit if accessed directly
define('PAYPLUS_PLUGIN_URL', plugins_url('/', __FILE__));
define('PAYPLUS_PLUGIN_URL_ASSETS_IMAGES', PAYPLUS_PLUGIN_URL . "assets/images/");
define('PAYPLUS_PLUGIN_DIR', dirname(__FILE__));
define('PAYPLUS_VERSION', '7.1.5');
define('PAYPLUS_VERSION_DB', 'payplus_3_1');
define('PAYPLUS_TABLE_PROCESS', 'payplus_payment_process');
class WC_PayPlus
{
    protected static $instance = null;
    public $notices = [];
    private $payplus_payment_gateway_settings;
    public $applePaySettings;
    public $isApplePayGateWayEnabled;
    public $isApplePayExpressEnabled;
    public $invoice_api = null;
    public $isAutoPPCC;
    private $_wpnonce;
    public $importApplePayScript;
    public $hostedFieldsOptions;

    /**
     * The main PayPlus gateway instance. Use get_main_payplus_gateway() to access it.
     *
     * @var null|WC_PayPlus_Gateway
     */
    protected $payplus_gateway = null;

    /**
     *
     */
    private function __construct()
    {
        //ACTION
        $this->payplus_payment_gateway_settings = (object) get_option('woocommerce_payplus-payment-gateway_settings');
        $this->hostedFieldsOptions = get_option('woocommerce_payplus-payment-gateway-hostedfields_settings');
        $this->applePaySettings = get_option('woocommerce_payplus-payment-gateway-applepay_settings');
        $this->isApplePayGateWayEnabled = boolval(isset($this->applePaySettings['enabled']) && $this->applePaySettings['enabled'] === "yes");
        $this->isApplePayExpressEnabled = boolval(property_exists($this->payplus_payment_gateway_settings, 'enable_apple_pay') && $this->payplus_payment_gateway_settings->enable_apple_pay === 'yes');
        $this->isAutoPPCC = boolval(property_exists($this->payplus_payment_gateway_settings, 'auto_load_payplus_cc_method') && $this->payplus_payment_gateway_settings->auto_load_payplus_cc_method === 'yes');
        $this->importApplePayScript = boolval(property_exists($this->payplus_payment_gateway_settings, 'import_applepay_script') && $this->payplus_payment_gateway_settings->import_applepay_script === 'yes');

        add_action('admin_init', [$this, 'check_environment']);
        add_action('admin_notices', [$this, 'admin_notices'], 15);
        add_action('plugins_loaded', [$this, 'init']);
        add_action('manage_product_posts_custom_column', [$this, 'payplus_custom_column_product'], 10, 2);
        add_action('woocommerce_email_before_order_table', [$this, 'payplus_add_content_specific_email'], 20, 4);
        add_action('wp_head', [$this, 'payplus_no_index_page_error']);
        add_action('woocommerce_api_payplus_gateway', [$this, 'ipn_response']);
        add_action('wp_ajax_make-hosted-payment', [$this, 'hostedPayment']);
        add_action('wp_ajax_update-hosted-payment', [$this, 'updateHostedPayment']);
        add_action('woocommerce_applied_coupon', [$this, 'catch_coupon_code_on_checkout'], 10, 1);
        add_action('woocommerce_removed_coupon', [$this, 'catch_remove_coupon_code_on_checkout'], 10, 1);

        add_action('woocommerce_add_to_cart', [$this, 'sync_cart_to_existing_order'], 10, 6);
        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'sync_order_after_cart_quantity_update'], 10, 4);
        add_action('woocommerce_cart_item_removed', [$this, 'remove_cart_item_from_order'], 10, 2);

        //end custom hook

        add_action('woocommerce_before_checkout_form', [$this, 'msg_checkout_code']);

        //FILTER
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
        add_filter('woocommerce_available_payment_gateways', [$this, 'payplus_applepay_disable_manager']);
        if (isset($this->payplus_payment_gateway_settings->payplus_cron_service) && boolval($this->payplus_payment_gateway_settings->payplus_cron_service === 'yes')) {
            $this->payPlusCronActivate();
            add_action('payplus_hourly_cron_job', [$this, 'getPayplusCron']);
        } else {
            $this->payPlusCronDeactivate();
        }
    }

    public function sync_cart_to_existing_order($cart_item_key, $product_id, $quantity, $variation_id, $variations, $cart_item_data)
    {
        $payload = json_decode(WC()->session->get('hostedPayload'), true);
        $order_id = WC()->session->get('order_awaiting_payment');
        $order = wc_get_order($order_id);
        // Clear the order's existing items before syncing the new ones
        if (!$order) {
            return;
        }
        foreach ($order->get_items() as $item_id => $item) {
            $productId = $item->get_product_id(); // Get the product ID
            if ($productId === $product_id) {
                $order->remove_item($item_id);
            }
        }
        // Check if the order exists and the cart is not empty
        if ($order && WC()->cart) {
            // Add the current product to the existing order
            $order->add_product(wc_get_product($product_id), $quantity, array(
                'variation_id' => $variation_id,
                'variation'    => $variations,
            ));

            // Recalculate order totals
            $order->calculate_totals();

            // Save the order
            $order->save();
        }

        $totalAmount = 0;
        foreach ($payload['items'] as $key => $item) {
            $totalAmount += $item['price'] * $item['quantity'];
        }

        $payload['amount'] = number_format($totalAmount, 2, '.', '');
        $payload = wp_json_encode($payload);
        WC()->session->set('hostedPayload', $payload);
        $hostedResponse = $this->createUpdateHostedPaymentPageLink($payload);
    }


    /**
     * Sync the cart item changes to an existing order after quantity update
     *
     * @param string $cart_item_key The cart item key.
     * @param int    $quantity The new quantity of the item.
     * @param int    $old_quantity The old quantity of the item.
     * @param WC_Cart $cart The cart object.
     */
    public function sync_order_after_cart_quantity_update($cart_item_key, $quantity, $old_quantity, $cart)
    {
        // Get the cart item data
        $cart_item = $cart->get_cart_item($cart_item_key);

        $product_id = $cart_item['product_id']; // Get product ID
        $variation_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : 0; // Check for variation ID
        $variations = !empty($cart_item['variation']) ? $cart_item['variation'] : array(); // Variation details
        $cart_item_data = $cart_item['data']; // Get other cart item data

        // Now call your sync function
        $this->sync_cart_to_existing_order($cart_item_key, $product_id, $quantity, $variation_id, $variations, $cart_item_data);
    }


    function remove_cart_item_from_order($cart_item_key, $cart_item)
    {
        $payload = json_decode(WC()->session->get('hostedPayload'), true);
        $order_id = WC()->session->get('order_awaiting_payment');
        $order = wc_get_order($order_id);

        // Check if the order exists
        if (!$order) {
            error_log('Order does not exist: ' . $order_id); // Log error
            return; // Exit if the order doesn't exist
        }

        // Get the product ID of the removed cart item
        $product_id = $cart_item->removed_cart_contents[$cart_item_key]['product_id'];
        $order_items = $order->get_items();

        // Loop through order items and remove the product if it exists
        $item_removed = false;
        foreach ($order_items as $order_item_id => $order_item) {
            if ($order_item->get_product_id() == $product_id) {
                // Remove the item from the order
                $order->remove_item($order_item_id);
                $item_removed = true; // Track if an item was removed
                break; // Exit loop after removing
            }
        }

        // Check if an item was removed
        if ($item_removed) {
            // Recalculate order totals after removal
            $order->calculate_totals();
            $order->save();
        } else {
            error_log('No matching product found in order for product ID: ' . $product_id); // Log if no match was found
        }

        $totalAmount = 0;
        foreach ($payload['items'] as $key => $item) {
            $totalAmount += $item['price'] * $item['quantity'];
        }

        $payload['amount'] = number_format($totalAmount, 2, '.', '');
        $payload = wp_json_encode($payload);
        WC()->session->set('hostedPayload', $payload);
        $hostedResponse = $this->createUpdateHostedPaymentPageLink($payload);
    }

    public function getHostedDataFromOrder($order)
    {
        // Initialize the result array
        $order_data = array(
            'items'    => array(),
            'shipping' => array(),
            'coupons'  => array(),
        );

        // Get all items (products) in the order
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $order_data['items'][] = array(
                'name'        => $item->get_name(),
                'quantity'    => $item->get_quantity(),
                'total'       => $item->get_total(),
                'subtotal'    => $item->get_subtotal(),
                'tax'         => $item->get_total_tax(),
                'product_id'  => $product ? $product->get_id() : null,
                'sku'         => $product ? $product->get_sku() : null,
            );
        }

        // Get shipping data
        foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
            $order_data['shipping'][] = array(
                'method_title' => $shipping_item->get_name(),
                'cost'         => $shipping_item->get_total(),
                'tax'          => $shipping_item->get_taxes(),
            );
        }

        // Get coupon data using $order->get_coupon_codes()
        $applied_coupons = $order->get_coupon_codes();

        foreach ($applied_coupons as $coupon_code) {
            // Load the WC_Coupon object
            $coupon = new WC_Coupon($coupon_code);

            // Get the discount amount and any other details as needed
            $coupon_discount = $order->get_discount_total(); // Total discount applied
            $coupon_discount_tax = $order->get_discount_tax(); // Total discount tax

            $order_data['coupons'][] = array(
                'code'         => $coupon_code,                // Coupon code
                'discount'     => $coupon_discount,            // Discount amount
                'discount_tax' => $coupon_discount_tax,        // Discount tax
                'coupon_type'  => $coupon->get_discount_type(), // Coupon type (fixed_cart, percent, etc.)
            );
        }

        // Output the final array (for debugging or further use)
        return $order_data;
    }

    public function catch_remove_coupon_code_on_checkout($coupon_code)
    {
        $payload = json_decode(WC()->session->get('hostedPayload'), true);
        $order_id = WC()->session->get('order_awaiting_payment');
        $order = wc_get_order($order_id);

        if (! $order) {
            return; // Exit if the order doesn't exist
        }

        // Check if the coupon was applied to the order
        if (in_array($coupon_code, $order->get_coupon_codes())) {
            // Remove the coupon from the order
            $order->remove_coupon($coupon_code);

            // Recalculate totals after removing the coupon
            $order->calculate_totals();

            // Save the updated order
            $order->save();
        }

        $order = wc_get_order($order_id);

        $orderData = $this->getHostedDataFromOrder($order);

        $totalAmount = 0;
        foreach ($payload['items'] as $key => $item) {
            if ($item['name'] === "coupon_discount") {
                /// $totalAmount += -floatval($payload['items'][$key]['price']);
                unset($payload['items'][$key]);
            } else {
                $totalAmount += $item['price'] * $item['quantity'];
            }
        }

        $payload['amount'] = number_format($totalAmount, 2, '.', '');
        $payload = wp_json_encode($payload);
        WC()->session->set('hostedPayload', $payload);
        $hostedResponse = $this->createUpdateHostedPaymentPageLink($payload);
    }

    public function catch_coupon_code_on_checkout($coupon_code)
    {
        $payload = json_decode(WC()->session->get('hostedPayload'), true);
        $order_id = WC()->session->get('order_awaiting_payment');
        // Assuming you have the $order object
        $order = wc_get_order($order_id);

        $coupon = new WC_Coupon($coupon_code);

        // Get the discount amount or coupon value (for fixed discount coupons)
        $coupon_value = $coupon->get_amount();

        if (!$order) {
            return; // If the order doesn't exist, return early
        }

        // Load the WooCommerce coupon object using the coupon code
        if (!$coupon->get_id()) {
            return; // If the coupon doesn't exist, return early
        }

        // Add the coupon to the order (apply the coupon)
        $order->apply_coupon($coupon);

        // Recalculate totals to include the discount from the coupon
        $order->calculate_totals();

        // Save the updated order
        $order->save();

        $orderData = $this->getHostedDataFromOrder($order);


        $totalAmount = 0;
        $noCoupon = false;

        foreach ($payload['items'] as $key => $item) {
            if ($item['name'] === "coupon_discount") {
                $payload['items'][$key]['price'] = -floatval($coupon_value);
                $totalAmount += -floatval($coupon_value) * $item['quantity'];
                $noCoupon = true;
            } else {
                $totalAmount += $item['price'] * $item['quantity'];
            }
        }


        if (floatval($coupon_value) > 0 && !$noCoupon) {
            $item['name'] = "coupon_discount";
            $item['quantity'] = 1;
            $item['price'] = -floatval($coupon_value);
            $item['vat_type'] =  0;
            $totalAmount += -floatval($coupon_value);
            $payload['items'][] = $item;
        }

        $payload['amount'] = number_format($totalAmount, 2, '.', '');
        $payload = wp_json_encode($payload);
        WC()->session->set('hostedPayload', $payload);
        $hostedResponse = $this->createUpdateHostedPaymentPageLink($payload);
    }

    public function hostedPayment()
    {
        check_ajax_referer('frontNonce', '_ajax_nonce');
        $order_id = intval($_POST['order_id']);
        $this->payplus_gateway = $this->get_main_payplus_gateway();
        $order = wc_get_order($order_id);
        $linkRedirect = html_entity_decode(esc_url($this->payplus_gateway->get_return_url($order)));
        $metaData['payplus_page_request_uid'] = $_POST['page_request_uid'];
        WC_PayPlus_Meta_Data::update_meta($order, $metaData);
        $PayPlusAdminPayments = new WC_PayPlus_Admin_Payments;
        $_wpnonce = wp_create_nonce('_wp_payplusIpn');
        $PayPlusAdminPayments->payplusIpn($order_id, $_wpnonce, true);
        WC()->session->__unset('hostedPayload');
        WC()->session->__unset('page_request_uid');
        WC()->session->__unset('order_awaiting_payment');
    }

    function createUpdateHostedPaymentPageLink($payload)
    {
        $options = get_option('woocommerce_payplus-payment-gateway_settings');
        $testMode = boolval($options['api_test_mode'] === 'yes');
        $apiUrl = $testMode ? 'https://restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink' : 'https://restapi.payplus.co.il/api/v1.0/PaymentPages/generateLink';
        $apiKey = $testMode ? $options['dev_api_key'] : $options['api_key'];
        $secretKey = $testMode ? $options['dev_secret_key'] : $options['secret_key'];

        $auth = json_encode([
            'api_key' => $apiKey,
            'secret_key' => $secretKey
        ]);
        $requestHeaders = [];
        $requestHeaders[] = 'Content-Type:application/json';
        $requestHeaders[] = 'Authorization: ' . $auth;


        $pageRequestUid = WC()->session->get('page_request_uid');

        if ($pageRequestUid) {
            $apiUrl = str_replace("/generateLink", "/Update/$pageRequestUid", $apiUrl);
        }

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_POST, true);

        $hostedResponse = curl_exec($ch);

        curl_close($ch);

        $hostedResponseArray = json_decode($hostedResponse, true);

        if (isset($hostedResponseArray['data']['page_request_uid'])) {
            $pageRequestUid = $hostedResponseArray['data']['page_request_uid'];
            WC()->session->set('page_request_uid', $pageRequestUid);
        }

        WC()->session->set('hostedResponse', $hostedResponse);

        return $hostedResponse;
    }

    public function updateHostedPayment()
    {
        check_ajax_referer('frontNonce', '_ajax_nonce');
        $payload = json_decode(WC()->session->get('hostedPayload'), true);
        $order_id = $payload['more_info'];
        $order = wc_get_order($order_id);

        $totalAmount = 0;
        foreach ($payload['items'] as $key => $item) {

            if ($item['name'] === "Shipping") {
                $payload['items'][$key]['price'] = floatval($_POST['totalShipping']);
                $totalAmount += floatval($_POST['totalShipping']) * $item['quantity'];
            } else {
                $totalAmount += $item['price'] * $item['quantity'];
            }
        }

        $payload['amount'] = number_format($totalAmount, 2, '.', '');

        if (! $order) {
            return;
        }

        // Remove existing shipping items
        foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
            $order->remove_item($item_id);
        }
        // Get the shipping method object by its ID

        // Create a new shipping item for the order
        $item = new WC_Order_Item_Shipping();
        $item->set_method_title('Shipping');  // Set the shipping method title (name)
        $item->set_method_id('shipping_total');        // Set the shipping method ID
        $item->set_total(floatval($_POST['totalShipping']));          // Set the shipping cost

        // Add the shipping item to the order
        $order->add_item($item);

        // Calculate totals and save the order
        $order->calculate_totals();
        $order->save();

        $payload = wp_json_encode($payload);

        WC()->session->set('hostedPayload', $payload);
        $hostedResponse = $this->createUpdateHostedPaymentPageLink($payload);
        wp_die($payload);
    }

    public function payPlusCronDeactivate()
    {
        $timestamp = wp_next_scheduled('payplus_hourly_cron_job');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'payplus_hourly_cron_job');
        }
    }

    public function payPlusCronActivate()
    {
        if (!wp_next_scheduled('payplus_hourly_cron_job')) {
            wp_schedule_event(current_time('timestamp'), 'hourly', 'payplus_hourly_cron_job');
        }
    }

    public function getPayplusCron()
    {
        $current_time = current_time('Y-m-d H:i:s');

        // Extract the current hour and minute
        $current_hour = gmdate('H', strtotime($current_time));
        $current_minute = gmdate('i', strtotime($current_time));

        $args = array(
            'status' => 'pending',
            'date_created' => $current_time,
            'return' => 'ids', // Just return IDs to save memory
        );
        $this->payplus_gateway = $this->get_main_payplus_gateway();

        $orders = array_reverse(wc_get_orders($args));
        $this->payplus_gateway->payplus_add_log_all('payplus-cron-log', 'getPayplusCron process started: ' . wp_json_encode($orders), 'default');
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            $hour = $order->get_date_created()->date('H');
            $min = $order->get_date_created()->date('i');
            $calc = $current_minute - $min;
            if ($current_hour >= $hour - 2) {
                $paymentPageUid = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_page_request_uid') !== "" ? WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_page_request_uid') : false;
                if ($paymentPageUid) {
                    $this->payplus_gateway->payplus_add_log_all('payplus-cron-log', "$order_id: created in the last two hours: current time: $current_hour:$current_minute created at: $hour:$min diff calc (minutes): $calc\n");
                    $PayPlusAdminPayments = new WC_PayPlus_Admin_Payments;
                    $_wpnonce = wp_create_nonce('_wp_payplusIpn');
                    $PayPlusAdminPayments->payplusIpn($order_id, $_wpnonce);
                }
            }
        }
    }
    /**
     * Returns the main PayPlus payment gateway class instance.
     *
     * @return new WC_PayPlus_Gateway
     */
    public function get_main_payplus_gateway()
    {
        if (!is_null($this->payplus_gateway)) {
            return $this->payplus_gateway;
        }
        $this->payplus_gateway = new WC_PayPlus_Gateway();
        return $this->payplus_gateway;
    }


    /**
     * @return void
     */
    public function msg_checkout_code()
    {
        $this->payplus_gateway = $this->get_main_payplus_gateway();
        $woocommerce_price_num_decimal = get_option('woocommerce_price_num_decimals');
        if ($this->payplus_gateway->api_test_mode) {
            echo '<div
    style="background: #d23d3d; border-right: 8px #b33434 solid; border-radius: 4px; color: #FFF; padding: 5px;margin: 5px 0px">
    ' . esc_html__('Sandbox mode is active and real transaction cannot be processed. Please make sure to move production when
    finishing testing', 'payplus-payment-gateway') . '</div>';
        }

        if ($woocommerce_price_num_decimal > 2 || $woocommerce_price_num_decimal == 1 || $woocommerce_price_num_decimal < 0) {
            echo '<div style="background: #d23d3d; border-right: 8px #b33434 solid; border-radius: 4px; color: #FFF; padding: 5px;margin: 5px 0px">'
                . esc_html__('Please change the "Number of decimal digits" to 2 or 0 in your WooCommerce settings>General>Currency
    settings', 'payplus-payment-gateway') . '</div>';
        }
    }

    /**
     * @return void
     */
    public function ipn_response()
    {
        if (!wp_verify_nonce(sanitize_key($this->_wpnonce), '_wp_payplus')) {
            wp_die('Not allowed! - ipn_response');
        }
        global $wpdb;
        $this->payplus_gateway = $this->get_main_payplus_gateway();
        $REQUEST = $this->payplus_gateway->arr_clean($_REQUEST);
        $tblname = $wpdb->prefix . 'payplus_payment_process';
        $tblname = esc_sql($tblname);
        $indexRow = 0;
        if (!empty($REQUEST['more_info'])) {
            $status_code = isset($_REQUEST['status_code']) ? sanitize_text_field(wp_unslash($_REQUEST['status_code'])) : '';
            if ($status_code !== '000') {
                $this->payplus_gateway->store_payment_ip();
            }
            $order_id = isset($_REQUEST['more_info']) ? sanitize_text_field(wp_unslash($_REQUEST['more_info'])) : '';
            $result = $wpdb->get_results($wpdb->prepare(
                "SELECT id as rowId, count(*) as rowCount, count_process FROM {$wpdb->prefix}payplus_payment_process WHERE order_id = %d AND ( status_code = %d )",
                $order_id,
                $status_code
            ));
            $result = $result[0] ?? null;
            if (!$result->rowCount) {
                $wpdb->insert(
                    $tblname,
                    array(
                        'order_id' => $order_id,
                        'function_begin' => 'ipn_response',
                        'status_code' => $status_code,
                        'count_process' => 1,
                    ),
                    array('%d', '%s', '%d', '%d')  // Data types for each column: order_id (integer), function_begin (string), status_code (integer), count_process (integer)
                );

                if ($wpdb->last_error) {
                    payplus_Add_log_payplus($wpdb->last_error);
                }

                $data = [
                    'transaction_uid' => isset($_REQUEST['transaction_uid']) ? sanitize_text_field(wp_unslash($_REQUEST['transaction_uid'])) : null,
                    'page_request_uid' => isset($_REQUEST['page_request_uid']) ? sanitize_text_field(wp_unslash($_REQUEST['page_request_uid'])) : null,
                    'voucher_id' => isset($_REQUEST['voucher_num']) ? sanitize_text_field(wp_unslash($_REQUEST['voucher_num'])) : null,
                    'token_uid' => isset($_REQUEST['token_uid']) ? sanitize_text_field(wp_unslash($_REQUEST['token_uid'])) : null,
                    'type' => isset($_REQUEST['type']) ? sanitize_text_field(wp_unslash($_REQUEST['type'])) : null,
                    'order_id' => isset($_REQUEST['more_info']) ? sanitize_text_field(wp_unslash($_REQUEST['more_info'])) : null,
                    'status_code' => isset($_REQUEST['status_code']) ? intval($_REQUEST['status_code']) : null,
                    'number' => isset($_REQUEST['number']) ? sanitize_text_field(wp_unslash($_REQUEST['number'])) : null,
                    'expiry_year' => isset($_REQUEST['expiry_year']) ? sanitize_text_field(wp_unslash($_REQUEST['expiry_year'])) : null,
                    'expiry_month' => isset($_REQUEST['expiry_month']) ? sanitize_text_field(wp_unslash($_REQUEST['expiry_month'])) : null,
                    'four_digits' => isset($_REQUEST['four_digits']) ? sanitize_text_field(wp_unslash($_REQUEST['four_digits'])) : null,
                    'brand_id' => isset($_REQUEST['brand_id']) ? sanitize_text_field(wp_unslash($_REQUEST['brand_id'])) : null,
                ];

                $order = $this->payplus_gateway->validateOrder($data);

                $linkRedirect = html_entity_decode(esc_url($this->payplus_gateway->get_return_url($order)));

                if (isset($REQUEST['paymentPayPlusDashboard']) && !empty($REQUEST['paymentPayPlusDashboard'])) {
                    $order_id = $REQUEST['more_info'];
                    $order = wc_get_order($order_id);
                    $paymentPayPlusDashboard = $REQUEST['paymentPayPlusDashboard'];
                    if ($paymentPayPlusDashboard === $this->payplus_gateway->payplus_generate_key_dashboard) {
                        $order->set_payment_method('payplus-payment-gateway');
                        $order->set_payment_method_title('Pay with Debit or Credit Card');
                        $linkRedirect = esc_url(get_admin_url()) . "post.php?post=" . $order_id . "&action=edit";
                    }
                }
                WC()->session->__unset('save_payment_method');
                wp_redirect($linkRedirect);
            } else {
                $countProcess = intval($result->count_process);
                $rowId = intval($result->rowId);
                $wpdb->update(
                    $tblname,
                    array(
                        'count_process' => $countProcess + 1,
                    ),
                    array(
                        'id' => $rowId,
                    ),
                    array('%d'),
                    array('%d')
                );
                if ($wpdb->last_error) {
                    payplus_Add_log_payplus($wpdb->last_error);
                }
                $order = wc_get_order($order_id);
                $linkRedirect = html_entity_decode(esc_url($this->payplus_gateway->get_return_url($order)));
                WC()->session->__unset('save_payment_method');
                wp_redirect($linkRedirect);
            }
        } elseif (isset($_GET['success_order_id']) && isset($_GET['charge_method']) && $_GET['charge_method'] === 'bit') {
            $order_id = isset($_GET['success_order_id']) ? intval($_GET['success_order_id']) : 0;
            $order = wc_get_order($order_id);
            if ($order) {
                $linkRedirect = html_entity_decode(esc_url($this->payplus_gateway->get_return_url($order)));
                WC()->session->__unset('save_payment_method');
                wp_redirect($linkRedirect);
            }
        }
    }

    /**
     * @return void
     */
    public function payplus_no_index_page_error()
    {
        global $wp;
        $error_page_payplus = get_option('error_page_payplus');
        $postIdcurrenttUrl = url_to_postid(home_url($wp->request));
        if (intval($postIdcurrenttUrl) === intval($error_page_payplus)) {
?>
            <meta name=" robots" content="noindex,nofollow">
        <?php
        }
    }

    /**
     * @param int $order_id
     * @param int $refund_id
     * @return void
     */
    public function payplus_after_refund($order_id, $refund_id)
    {
        $order = wc_get_order($order_id);
        $invoice_api = new PayplusInvoice();
        $payment_method = $order->get_payment_method();
        if (strpos($payment_method, 'payplus') === false) {
            //$amount = WC_PayPlus_Meta_Data::get_meta($refund_id, '_refund_amount', true);
            $amount = $order->get_total_refunded();
            if (floatval($amount)) {
                $invoice_api->payplus_create_document_dashboard(
                    $order_id,
                    $invoice_api->payplus_get_invoice_type_document_refund(),
                    array(),
                    $amount,
                    'payplus_order_refund' . $order_id
                );
            }
        }
    }


    /**
     * @param  $order
     * @param $sent_to_admin
     * @param $plain_text
     * @param $email
     * @return void
     */
    public function payplus_add_content_specific_email($order, $sent_to_admin, $plain_text, $email)
    {

        if ($email->id == 'new_order') {
            $payplusFourDigits = WC_PayPlus_Meta_Data::get_meta($order->get_id(), "payplus_four_digits", true);
            if ($payplusFourDigits) {
                $payplusFourDigits = __("Four last digits", "payplus-payment-gateway") . " : " . $payplusFourDigits;
                echo '<p class="email-upsell-p">' . esc_html($payplusFourDigits) . '</p>';
            }
        }
    }

    /**
     * @return static|null instance
     */
    public static function get_instance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }
        return static::$instance;
    }

    /**
     * @param string $column
     * @param int $post_id
     * @return void
     */
    public function payplus_custom_column_product($column, $post_id)
    {
        if ($column == "payplus_transaction_type") {
            $transactionTypes = array(
                '1' => __('Charge', 'payplus-payment-gateway'),
                '2' => __('Authorization', 'payplus-payment-gateway'),
            );
            $payplusTransactionType = WC_PayPlus_Meta_Data::get_meta($post_id, 'payplus_transaction_type', true);
            if (!empty($payplusTransactionType)) {
                echo '<p>' . esc_html($transactionTypes[$payplusTransactionType]) . "</p>";
            }
        }
    }


    /**
     * @return string|void
     */
    public function payplus_text_error_page()
    {
        $optionlanguages = get_option('settings_payplus_page_error_option');
        $locale = get_locale();
        if (count($optionlanguages)) {
            foreach ($optionlanguages as $key => $optionlanguage) {
                if (strpos($key, $locale) !== false) {
                    return "<p style='text-align: center' class='payplus-error-text'>" . $optionlanguage . "</p>";
                }
            }
            return "<p  style='text-align: center' class='payplus-error-text'>" . $optionlanguages['en_US_-English'] . "</p>";
        }
    }

    /**
     * @return void
     */
    public function check_environment()
    {
        if (is_admin() && current_user_can('activate_plugins') && !is_plugin_active('woocommerce/woocommerce.php')) {
            $message = __('This plugin requires <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> to be activated.', 'payplus-payment-gateway');
            $this->add_admin_notice('error', $message);
            // Deactivate the plugin
            deactivate_plugins(__FILE__);
            return;
        }
        $php_version = phpversion();
        $required_php_version = '7.4';
        $woocommerce_price_num_decimal = get_option('woocommerce_price_num_decimals');

        if (version_compare($required_php_version, $php_version, '>')) {
            $message = sprintf(
                /* translators: %1$s: Current PHP version, %2$s: Required PHP version */
                __('Your server is running PHP version %1$s but some features require at least %2$s.', 'payplus-payment-gateway'),
                $php_version,
                $required_php_version
            );
            $this->add_admin_notice('warning', $message);
        }

        if ($woocommerce_price_num_decimal > 2 || $woocommerce_price_num_decimal == 1 || $woocommerce_price_num_decimal < 0) {
            $message = '<b>' . esc_html__('Please change the "Number of decimal digits" to 2 or 0 in your WooCommerce settings>General>Currency setting', 'payplus-payment-gateway') . '</b>';
            $this->add_admin_notice('warning', $message);
        }
    }

    /**
     * @param string $type
     * @param string $message
     * @return void
     */
    public function add_admin_notice($type, $message)
    {
        $this->notices[] = [
            'class' => "notice notice-$type is-dismissible",
            'message' => $message,
        ];
    }

    /**
     * @return void
     */
    public function admin_notices()
    {
        $output = '';
        $title = esc_html__('PayPlus Payment Gateway', 'payplus-payment-gateway');
        if (count($this->notices)) {
            foreach ($this->notices as $notice) {
                $class = esc_attr($notice['class']);
                $message = esc_html($notice['message']);
                $output .= "<div class='$class'><p><b>$title:</b> $message</p></div>";
            }
        }
        echo wp_kses_post($output);
    }

    /**
     * @param array $links
     * @return array|string[]
     */
    public static function plugin_action_links($links)
    {
        $action_links = [
            'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway') . '" aria-label="' . esc_html__('View PayPlus Settings', 'payplus-payment-gateway') . '">' . esc_html__('Settings') . '</a>',
        ];
        $links = array_merge($action_links, $links);

        return $links;
    }

    /**
     * @return void
     */
    public function init()
    {

        load_plugin_textdomain('payplus-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        if (class_exists("WooCommerce")) {
            $this->_wpnonce = wp_create_nonce('_wp_payplus');
            require_once PAYPLUS_PLUGIN_DIR . '/includes/class-wc-payplus-statics.php';
            require_once PAYPLUS_PLUGIN_DIR . '/includes/admin/class-wc-payplus-admin-settings.php';
            require_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_gateway.php';
            require_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_subgateways.php';
            require_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_invoice.php';
            require_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_express_checkout.php';
            require_once PAYPLUS_PLUGIN_DIR . '/includes/class-wc-payplus-payment-tokens.php';
            require_once PAYPLUS_PLUGIN_DIR . '/includes/class-wc-payplus-order-data.php';
            add_action('woocommerce_blocks_loaded', [$this, 'woocommerce_payplus_woocommerce_block_support']);
            require_once PAYPLUS_PLUGIN_DIR . '/includes/admin/class-wc-payplus-admin.php';
            if (in_array('elementor/elementor.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                add_action('elementor/widgets/register', [$this, 'payplus_register_widgets']);
            }

            add_action('woocommerce_after_checkout_validation', [$this, 'payplus_validation_cart_checkout'], 10, 2);

            add_action('wp_enqueue_scripts', [$this, 'load_checkout_assets']);

            add_action('woocommerce_api_callback_response', [$this, 'callback_response']);
            if (WP_DEBUG_LOG) {
                add_action('woocommerce_api_callback_response_hash', [$this, 'callback_response_hash']);
            }

            add_action('woocommerce_review_order_before_submit', [$this, 'payplus_view_iframe_payment'], 1);

            $this->invoice_api = new PayplusInvoice();
            add_action('manage_shop_order_posts_custom_column', [$this->invoice_api, 'payplus_add_order_column_order_invoice'], 100, 2);
            add_action('woocommerce_shop_order_list_table_custom_column', [$this->invoice_api, 'payplus_add_order_column_order_invoice'], 100, 2);

            if ($this->invoice_api->payplus_get_invoice_enable() && !$this->invoice_api->payplus_get_create_invoice_manual()) {

                add_action('woocommerce_order_status_' . $this->invoice_api->payplus_get_invoice_status_order(), [$this->invoice_api, 'payplus_invoice_create_order']);
                if ($this->invoice_api->payplus_get_create_invoice_automatic()) {
                    add_action('woocommerce_order_status_on-hold', [$this->invoice_api, 'payplus_invoice_create_order_automatic']);
                    add_action('woocommerce_order_status_processing', [$this->invoice_api, 'payplus_invoice_create_order_automatic']);
                }
            }

            if (
                $this->payplus_payment_gateway_settings
                && property_exists($this->payplus_payment_gateway_settings, 'add_product_field_transaction_type')
                && $this->payplus_payment_gateway_settings->add_product_field_transaction_type == "yes"
            ) {
                add_action('add_meta_boxes', [$this, 'payplus_add_product_meta_box_transaction_type']);
                add_action('manage_product_posts_columns', [$this, 'payplus_add_order_column_order_product'], 100);
                add_action('manage_shop_order_posts_custom_column', [$this, 'payplus_add_order_column_order_transaction_type'], 100);
                add_filter('manage_edit-shop_order_columns', [$this, 'payplus_add_order_column_orders'], 20);
            }
            if (
                $this->payplus_payment_gateway_settings
                && property_exists($this->payplus_payment_gateway_settings, 'balance_name')
                && $this->payplus_payment_gateway_settings->balance_name == "yes"
            ) {
                add_action('add_meta_boxes', [$this, 'payplus_add_product_meta_box_balance_name']);
            }

            add_action('save_post', [$this, 'payplus_save_meta_box_data']);
            add_filter('woocommerce_payment_gateways', [$this, 'add_payplus_gateway'], 20);
            payplusUpdateActivate();
            if ($this->isApplePayGateWayEnabled || $this->isApplePayExpressEnabled) {
                payplus_add_file_ApplePay();
            }
        }
    }

    /**
     * @return void
     */
    public function load_checkout_assets()
    {
        $script_version = filemtime(plugin_dir_path(__FILE__) . 'assets/js/front.min.js');
        $importAapplepayScript = null;
        $isModbile = (wp_is_mobile()) ? true : false;
        $multipassIcons = WC_PayPlus_Statics::getMultiPassIcons();
        $custom_icons = property_exists($this->payplus_payment_gateway_settings, 'custom_icons') ? $this->payplus_payment_gateway_settings->custom_icons : false;
        $customIcons = [];
        $custom_icons = explode(";", $custom_icons);
        foreach ($custom_icons as $icon) {
            $customIcons[] = esc_url($icon);
        }
        $isSubscriptionOrder = false;

        if (is_checkout() || is_product()) {
            if ($this->importApplePayScript) {
                wp_register_script('applePayScript', 'https://payments.payplus.co.il/statics/applePay/script.js', array('jquery'), PAYPLUS_VERSION, true);
                wp_enqueue_script('applePayScript');
            }
        }

        if (is_checkout()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (get_class($cart_item['data']) === "WC_Product_Subscription" || get_class($cart_item['data']) === "WC_Product_Subscription_Variation") {
                    $isSubscriptionOrder = true;
                    break;
                }
            }
            wp_scripts()->registered['wc-checkout']->src = PAYPLUS_PLUGIN_URL . 'assets/js/checkout.js?ver=1' . PAYPLUS_VERSION;
            if ($this->isApplePayGateWayEnabled || $this->isApplePayExpressEnabled) {
                if (in_array($this->payplus_payment_gateway_settings->display_mode, ['samePageIframe', 'popupIframe', 'iframe'])) {
                    $importAapplepayScript = 'https://payments.payplus.co.il/statics/applePay/script.js?var=' . PAYPLUS_VERSION;
                }
            }
            wp_localize_script(
                'wc-checkout',
                'payplus_script_checkout',
                [
                    "payplus_import_applepay_script" => $importAapplepayScript,
                    "payplus_mobile" => $isModbile,
                    "multiPassIcons" => $multipassIcons,
                    "customIcons" => $customIcons,
                    "isSubscriptionOrder" => $isSubscriptionOrder,
                    "isAutoPPCC" => $this->isAutoPPCC,
                    "isHostedFields" => isset($this->hostedFieldsOptions['enabled']) ? boolval($this->hostedFieldsOptions['enabled'] === "yes") : false,
                    "hostedFieldsWidth" => isset($this->hostedFieldsOptions['hosted_fields_width']) ? $this->hostedFieldsOptions['hosted_fields_width'] : 50,
                    "hidePPGateway" => isset($this->hostedFieldsOptions['hide_payplus_gateway']) ? boolval($this->hostedFieldsOptions['hide_payplus_gateway'] === "yes") : false,
                ]
            );
        }
        $isElementor = in_array('elementor/elementor.php', apply_filters('active_plugins', get_option('active_plugins')));
        $isEnableOneClick = (isset($this->payplus_payment_gateway_settings->enable_google_pay) && $this->payplus_payment_gateway_settings->enable_google_pay === "yes") ||
            (isset($this->payplus_payment_gateway_settings->enable_apple_pay) && $this->payplus_payment_gateway_settings->enable_apple_pay === "yes");
        if (is_checkout() || is_product() || is_cart() || $isElementor) {
            if (
                $this->payplus_payment_gateway_settings->enable_design_checkout === "yes" || $isEnableOneClick

            ) {
                $this->payplus_gateway = $this->get_main_payplus_gateway();
                add_filter('body_class', [$this, 'payplus_body_classes']);
                wp_enqueue_style('payplus-css', PAYPLUS_PLUGIN_URL . 'assets/css/style.min.css', [], $script_version);

                if ($isEnableOneClick) {
                    $payment_url_google_pay_iframe = $this->payplus_gateway->payplus_iframe_google_pay_oneclick;
                    wp_register_script('payplus-front-js', PAYPLUS_PLUGIN_URL . 'assets/js/front.min.js', [], $script_version, true);
                    wp_localize_script(
                        'payplus-front-js',
                        'payplus_script',
                        [
                            "payment_url_google_pay_iframe" => $payment_url_google_pay_iframe,
                            'ajax_url' => admin_url('admin-ajax.php'),
                            'frontNonce' => wp_create_nonce('frontNonce'),
                        ]
                    );
                    wp_enqueue_script('payplus-front-js');
                }
            }

            $userId = get_current_user_id();
            if ($userId > 0) {
                if (!is_cart() && !is_product() && !is_shop()) {
                    if (boolval($this->hostedFieldsOptions['enabled'] === "yes")) {
                        require_once PAYPLUS_PLUGIN_DIR . '/includes/payplus-hosted-fields.php';
                        if (isset($hostedResponse) && $hostedResponse && json_decode($hostedResponse, true)['results']['status'] === "success") {

                            $template_path = plugin_dir_path(__FILE__) . 'templates/hostedFields.php';

                            if (file_exists($template_path)) {
                                wp_enqueue_style('hosted-css', PAYPLUS_PLUGIN_URL . 'assets/css/hostedFields.min.css', [], $script_version);
                                include $template_path;
                            }
                            wp_enqueue_script('payplus-hosted-fields-js', plugin_dir_url(__FILE__) . 'assets/js/payplus-hosted-fields/dist/payplus-hosted-fields.min.js', array('jquery'), '1.0', true);
                            wp_register_script('payplus-hosted', plugin_dir_url(__FILE__) . 'assets/js/hostedFieldsScript.js', array('jquery'), '1.0', true);
                            wp_localize_script(
                                'payplus-hosted',
                                'payplus_script',
                                [
                                    "hostedResponse" => $hostedResponse,
                                    'ajax_url' => admin_url('admin-ajax.php'),
                                    'frontNonce' => wp_create_nonce('frontNonce'),
                                    'payPlusLogo' => PAYPLUS_PLUGIN_URL . 'assets/images/PayPlusLogo.svg',
                                ]
                            );
                            wp_enqueue_script('payplus-hosted');
                        }
                    }
                }
            }
        }

        wp_enqueue_style('alertifycss', '//cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/css/alertify.min.css', array(), '1.14.0', 'all');
        wp_register_script('alertifyjs', '//cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/alertify.min.js', array('jquery'), '1.14.0', true);
        wp_enqueue_script('alertifyjs');
    }

    /**
     * @param array $classes
     * @return array
     */
    public function payplus_body_classes($classes)
    {
        if ($this->payplus_payment_gateway_settings->enable_design_checkout == "yes") {
            $classes[] = 'checkout-payplus';
        }
        return $classes;
    }

    /**
     * @return void
     */
    public function payplus_view_iframe_payment()
    {
        $height = $this->payplus_payment_gateway_settings->iframe_height;
        ob_start();
        ?>
        <div class="payplus-option-description-area"></div>
        <div class="pp_iframe" data-height="<?php echo esc_attr($height); ?>"></div>
        <div class="pp_iframe_h" data-height="<?php echo esc_attr($height); ?>"></div>
<?php
        $html = ob_get_clean();
        echo wp_kses_post($html);
    }

    /**
     * @param array $available_gateways
     * @return array
     */
    public function payplus_applepay_disable_manager($available_gateways)
    {
        $currency = strtolower(get_woocommerce_currency());
        if (
            isset($available_gateways['payplus-payment-gateway-applepay']) && !is_admin() && isset($_SERVER['HTTP_USER_AGENT']) &&
            !preg_match('/Mac|iPad|iPod|iPhone/', sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])))
        ) {
            unset($available_gateways['payplus-payment-gateway-applepay']);
        }
        if (!is_admin() && $currency != 'ils') {
            $arrPayment = array(
                'payplus-payment-gateway',
                'payplus-payment-gateway-bit',
                'payplus-payment-gateway-googlepay',
                'payplus-payment-gateway-applepay',
                'payplus-payment-gateway-paypal',
            );
            foreach ($available_gateways as $key => $available_gateway) {
                if (strpos($key, 'payplus-payment-gateway') && !in_array($key, $arrPayment)) {
                    unset($available_gateways[$key]);
                }
            }
        }
        return $available_gateways;
    }
    /*
    ===  Begin Section  field "transaction_type" ==
     */
    /**
     * @param $post
     * @return void
     */
    public function payplus_meta_box_product_transaction_type($post)
    {

        ob_start();
        wp_nonce_field('payplus_notice_proudct_nonce', 'payplus_notice_proudct_nonce');
        $transactionTypeValue = WC_PayPlus_Meta_Data::get_meta($post->ID, 'payplus_transaction_type', true);

        $transactionTypes = array(
            '1' => __('Charge', 'payplus-payment-gateway'),
            '2' => __('Authorization', 'payplus-payment-gateway'),
        );
        if (count($transactionTypes)) {
            echo "<select id='payplus_transaction_type' name='payplus_transaction_type'>";
            echo "<option value=''>" . esc_html__('Transactions Type', 'payplus-payment-gateway') . "</option>";

            foreach ($transactionTypes as $key => $transactionType) {
                $selected = ($transactionTypeValue == $key) ? "selected" : "";
                echo '<option ' . esc_attr($selected) . ' value="' . esc_attr($key) . '">' . esc_html($transactionType) . '</option>';
            }
            echo "</select>";
        }
        echo ob_get_clean();
    }

    /**
     * @param array $columns
     * @return array
     */
    public function payplus_add_order_column_orders($columns)
    {
        $this->payplus_gateway = $this->get_main_payplus_gateway();
        $new_columns = array();
        if (count($columns)) {
            foreach ($columns as $column_name => $column_info) {
                $new_columns[$column_name] = $column_info;
                if ('shipping_address' === $column_name && $this->payplus_gateway->enabled === "yes") {

                    $new_columns['payplus_transaction_type'] = "<span class='text-center'>" . esc_html__('Transaction Type ', 'payplus-payment-gateway') . "</span>";
                }
            }
        }
        return $new_columns;
    }

    /**
     * @param array $columns
     * @return array
     */
    public function payplus_add_order_column_order_product($columns)
    {
        $new_columns = array();
        $this->payplus_gateway = $this->get_main_payplus_gateway();
        if (count($columns)) {
            foreach ($columns as $column_name => $column_info) {
                $new_columns[$column_name] = $column_info;
                if ('price' === $column_name && $this->payplus_gateway->enabled === "yes") {
                    $new_columns['payplus_transaction_type'] = "<span class='text-center'>" . esc_html__('Transaction Type ', 'payplus-payment-gateway') . "</span>";
                }
            }
        }
        return $new_columns;
    }

    /**
     * @return void
     */
    public function payplus_add_product_meta_box_transaction_type()
    {
        global $post;
        if (!empty($post) && get_post_type() === "product") {
            $product = wc_get_product($post->ID);
            $typeProducts = array('variable-subscription', 'subscription');
            if (!in_array($product->get_type(), $typeProducts)) {
                add_meta_box(
                    'payplus_transaction_type',
                    __('Transaction Type', 'payplus-payment-gateway'),
                    [$this, 'payplus_meta_box_product_transaction_type'],
                    'product'
                );
            }
        }
    }
    /*
    ===  END Section  field "transaction_type" ==
     */
    /*
    ===  Begin Section  field "balance_name" ==
     */
    /**
     * @return void
     */
    public function payplus_add_product_meta_box_balance_name()
    {
        global $post;
        if (!empty($post) && get_post_type() === "product") {

            add_meta_box(
                'payplus_balance_name',
                __('Balance Name', 'payplus-payment-gateway'),
                [$this, 'payplus_meta_box_product_balance_name'],
                'product'
            );
        }
    }

    /**
     * @param $post
     * @return void
     */
    public function payplus_meta_box_product_balance_name($post)
    {
        ob_start();
        wp_nonce_field('payplus_notice_product_nonce', 'payplus_notice_product_nonce');
        $balanceName = WC_PayPlus_Meta_Data::get_meta($post->ID, 'payplus_balance_name', true);

        printf('<input maxlength="20" value="%s" placeholder="%s" type="text" id="payplus_balance_name" name="payplus_balance_name" />', esc_attr($balanceName), esc_attr__('Balance Name', 'payplus-payment-gateway'));

        echo wp_kses_post(ob_get_clean());
    }
    /*
    ===  End Section  field "balance_name" ==
     */
    /**
     * @param int $post_id
     * @return void
     */
    public function payplus_save_meta_box_data($post_id)
    {
        // Check if our nonce is set.
        if (!isset($_POST['payplus_notice_proudct_nonce'])) {
            return;
        }
        // Verify that the nonce is valid.
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['payplus_notice_proudct_nonce'])), 'payplus_notice_proudct_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (isset($_POST['post_type']) && 'product' == $_POST['post_type']) {

            if (!current_user_can('edit_post', $post_id)) {

                return;
            }
        }
        if (!isset($_POST['payplus_transaction_type']) && !isset($_POST['payplus_balance_name'])) {

            return;
        }

        if (isset($_POST['payplus_transaction_type'])) {

            $transaction_type = sanitize_text_field(wp_unslash($_POST['payplus_transaction_type']));
            update_post_meta($post_id, 'payplus_transaction_type', $transaction_type);
        }
        if (isset($_POST['payplus_balance_name'])) {

            $payplus_balance_name = sanitize_text_field(wp_unslash($_POST['payplus_balance_name']));
            update_post_meta($post_id, 'payplus_balance_name', $payplus_balance_name);
        }
    }

    /**
     * @return void
     */
    public function callback_response()
    {
        $this->payplus_gateway = $this->get_main_payplus_gateway();
        $this->payplus_gateway->callback_response();
    }
    /**
     * @return void
     */
    public function callback_response_hash()
    {
        $this->payplus_gateway = $this->get_main_payplus_gateway();
        $this->payplus_gateway->callback_response_hash();
    }

    /**
     * @param string $column
     * @return void
     */
    public function payplus_add_order_column_order_transaction_type($column)
    {
        $this->payplus_gateway = $this->get_main_payplus_gateway();

        if ($column == "payplus_transaction_type" && $this->payplus_gateway->add_product_field_transaction_type) {
            global $post;
            $payplusTransactionType = WC_PayPlus_Meta_Data::get_meta($post->ID, 'payplus_transaction_type', true);
            if (!empty($payplusTransactionType)) {
                $transactionTypes = array(
                    '1' => __('Charge', 'payplus-payment-gateway'),
                    '2' => __('Authorization', 'payplus-payment-gateway'),
                );
                if (isset($transactionTypes[$payplusTransactionType])) {
                    echo esc_html($transactionTypes[$payplusTransactionType]);
                }
            }
        }
    }

    /**
     * @param array $methods
     * @return array
     */
    public function add_payplus_gateway($methods)
    {
        $methods[] = 'WC_PayPlus_Gateway';
        $methods[] = 'WC_PayPlus_Gateway_Bit';
        $methods[] = 'WC_PayPlus_Gateway_GooglePay';
        $methods[] = 'WC_PayPlus_Gateway_ApplePay';
        $methods[] = 'WC_PayPlus_Gateway_Multipass';
        $methods[] = 'WC_PayPlus_Gateway_Paypal';
        $methods[] = 'WC_PayPlus_Gateway_TavZahav';
        $methods[] = 'WC_PayPlus_Gateway_Valuecard';
        $methods[] = 'WC_PayPlus_Gateway_FinitiOne';
        $methods[] = 'WC_PayPlus_Gateway_HostedFields';
        $payplus_payment_gateway_settings = get_option('woocommerce_payplus-payment-gateway_settings');
        if ($payplus_payment_gateway_settings) {
            if (isset($payplus_payment_gateway_settings['disable_menu_header']) && $payplus_payment_gateway_settings['disable_menu_header'] !== "yes") {
                add_action('admin_bar_menu', ['WC_PayPlus_Form_Fields', 'adminBarMenu'], 100);
            }
            if (isset($payplus_payment_gateway_settings['disable_menu_side']) && $payplus_payment_gateway_settings['disable_menu_side'] !== "yes") {
                add_action('admin_menu', ['WC_PayPlus_Form_Fields', 'addAdminPageMenu'], 99);
            }
        }
        return $methods;
    }


    /**
     * @return void
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, esc_html__('Cheatin&#8217; huh?', 'payplus-payment-gateway'), '2.0');
    }

    /**
     * @return void
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, esc_html__('Cheatin&#8217; huh?', 'payplus-payment-gateway'), '2.0');
    }

    /**
     * @param $fields
     * @param $errors
     * @return void
     */
    public function payplus_validation_cart_checkout($fields, $errors)
    {
        $this->payplus_gateway = $this->get_main_payplus_gateway();

        $woocommerce_price_num_decimal = get_option('woocommerce_price_num_decimals');

        if ($woocommerce_price_num_decimal > 2 || $woocommerce_price_num_decimal == 1 || $woocommerce_price_num_decimal < 0) {
            $errors->add('error', esc_html__('Unable to create a payment page due to a site settings issue. Please contact the website owner', 'payplus-payment-gateway'));
        }
        if ($this->payplus_gateway->block_ip_transactions) {
            $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : "";
            if (filter_var($client_ip, FILTER_VALIDATE_IP) === false) {
                $client_ip = ""; // Handle invalid IP scenario if necessary
            }
            $counts = array_count_values($this->payplus_gateway->get_payment_ips());
            $howMany = isset($counts[$client_ip]) ? $counts[$client_ip] : 0;
            if (in_array($client_ip, $this->payplus_gateway->get_payment_ips()) && $howMany >= $this->payplus_gateway->block_ip_transactions_hour) {
                $errors->add(
                    'error',
                    __('Something went wrong with the payment page - This Ip is blocked', 'payplus-payment-gateway')
                );
            }
        }
    }

    /**
     * @param $widgets_manager
     * @return void
     */
    public function payplus_register_widgets($widgets_manager)
    {

        require_once PAYPLUS_PLUGIN_DIR . '/includes/elementor/widgets/express_checkout.php';
        $widgets_manager->register(new \Elementor_Express_Checkout());
    }

    /**
     * @return bool
     */
    public static function payplus_check_exists_table($wpnonce, $table = 'payplus_order')
    {
        if (!wp_verify_nonce(sanitize_key($wpnonce), 'PayPlusGateWayNonce')) {
            wp_die('Not allowed! - payplus_check_exists_table');
        }
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        $like_table_name = '%' . $wpdb->esc_like($table_name) . '%';
        $flag = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like_table_name)) != $table_name) ? true : false;
        return $flag;
    }
    public static function payplus_get_admin_menu($nonce)
    {
        if (!wp_verify_nonce(sanitize_key($nonce), 'menu_option')) {
            wp_die('Not allowed! - payplus_get_admin_menu');
        }
        ob_start();
        $currentSection = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : "";
        $adminTabs = WC_PayPlus_Admin_Settings::getAdminTabs();
        echo "<div id='payplus-options'>";
        if (count($adminTabs)) {
            echo "<nav class='nav-tab-wrapper tab-option-payplus'>";
            foreach ($adminTabs as $key => $arrValue) {
                $allowed_html = array(
                    'img' => array(
                        'src' => true,
                        'alt' => true,
                        // Add other allowed attributes as needed
                    ),
                );
                $selected = ($key == $currentSection) ? "nav-tab-active" : "";
                echo '<a href="' . esc_url($arrValue['link']) . '" class="nav-tab ' . esc_attr($selected) . '">' .
                    wp_kses($arrValue['img'], $allowed_html) .
                    esc_html($arrValue['name']) .
                    '</a>';
            }
            echo "</nav>";
        }
        echo "</div>";
        return ob_get_clean();
    }

    public function woocommerce_payplus_woocommerce_block_support()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {

            require_once 'includes/blocks/class-wc-payplus-blocks-support.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new WC_Gateway_Payplus_credit_Card_Block());
                    $payment_method_registry->register(new WC_Gateway_Payplus_Googlepay_Block());
                    $payment_method_registry->register(new WC_Gateway_Payplus_Multipas_Block());
                    $payment_method_registry->register(new WC_Gateway_Payplus_Bit_Block());
                    $payment_method_registry->register(new WC_Gateway_Payplus_Applepay_Block());
                    $payment_method_registry->register(new WC_Gateway_Payplus_TavZahav_Block());
                    $payment_method_registry->register(new WC_Gateway_Payplus_Valuecard_Block());
                    $payment_method_registry->register(new WC_Gateway_Payplus_FinitiOne_Block());
                    $payment_method_registry->register(new WC_PayPlus_Gateway_HostedFields_Block());
                    $payment_method_registry->register(new WC_Gateway_Payplus_Paypal_Block());
                }
            );
        }
    }
}
WC_PayPlus::get_instance();

require_once PAYPLUS_PLUGIN_DIR . '/includes/wc-payplus-activation-functions.php';

register_activation_hook(__FILE__, 'payplus_create_table_order');
register_activation_hook(__FILE__, 'payplus_create_table_change_status_order');
register_activation_hook(__FILE__, 'payplus_create_table_process');
register_activation_hook(__FILE__, 'checkSetPayPlusOptions');
register_activation_hook(__FILE__, 'payplusGenerateErrorPage');
// register_activation_hook(__FILE__, 'cron_activate');
register_deactivation_hook(__FILE__, 'payplus_cron_deactivate');
