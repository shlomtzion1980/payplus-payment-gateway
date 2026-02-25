<?php

/**
 * Plugin Name: PayPlus Payment Gateway
 * Description: Accept credit/debit card payments or other methods such as bit, Apple Pay, Google Pay in one page. Create digitally signed invoices & much more.
 * Plugin URI: https://www.payplus.co.il/wordpress
 * Version: 8.1.3
 * Tested up to: 6.9
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
define('PAYPLUS_VERSION', '8.1.3');
define('PAYPLUS_VERSION_DB', 'payplus_8_1_3');
define('PAYPLUS_TABLE_PROCESS', 'payplus_payment_process');
class WC_PayPlus
{
    protected static $instance = null;
    public $notices = [];
    private $payplus_payment_gateway_settings;
    public $isPayPlus;
    public $applePaySettings;
    public $isApplePayGateWayEnabled;
    public $isApplePayExpressEnabled;
    public $invoice_api = null;
    public $isAutoPPCC;
    private $_wpnonce;
    public $importApplePayScript;
    public $hostedFieldsOptions;
    private $isHostedInitiated = false;
    public $secret_key;
    public $shipping_woo_js;
    public $disableCartHashCheck;
    public $updateStatusesIpn;
    public $hidePayPlusGatewayNMW;
    public $pwGiftCardData;
    public $iframeAutoHeight;
    public $popupTvEffect;

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
        $this->disableCartHashCheck = boolval(property_exists($this->payplus_payment_gateway_settings, 'disable_cart_hash_check') && $this->payplus_payment_gateway_settings->disable_cart_hash_check === 'yes');
        $this->updateStatusesIpn = boolval(property_exists($this->payplus_payment_gateway_settings, 'update_statuses_in_ipn') && $this->payplus_payment_gateway_settings->update_statuses_in_ipn === 'yes');
        $this->shipping_woo_js = property_exists($this->payplus_payment_gateway_settings, 'shipping_woo_js') && $this->payplus_payment_gateway_settings->shipping_woo_js === "yes" ? true : false;
        $this->hostedFieldsOptions = get_option('woocommerce_payplus-payment-gateway-hostedfields_settings');
        $this->applePaySettings = get_option('woocommerce_payplus-payment-gateway-applepay_settings');
        $this->isApplePayGateWayEnabled = boolval(isset($this->applePaySettings['enabled']) && $this->applePaySettings['enabled'] === "yes");
        $this->isApplePayExpressEnabled = boolval(property_exists($this->payplus_payment_gateway_settings, 'enable_apple_pay') && $this->payplus_payment_gateway_settings->enable_apple_pay === 'yes');
        $this->isAutoPPCC = boolval(property_exists($this->payplus_payment_gateway_settings, 'auto_load_payplus_cc_method') && $this->payplus_payment_gateway_settings->auto_load_payplus_cc_method === 'yes');
        $this->importApplePayScript = boolval(property_exists($this->payplus_payment_gateway_settings, 'import_applepay_script') && $this->payplus_payment_gateway_settings->import_applepay_script === 'yes');
        $this->isPayPlus = boolval(property_exists($this->payplus_payment_gateway_settings, 'enabled') && $this->payplus_payment_gateway_settings->enabled === 'yes');
        $is_test_mode = property_exists($this->payplus_payment_gateway_settings, 'api_test_mode') && $this->payplus_payment_gateway_settings->api_test_mode === "yes";
        $this->secret_key = $is_test_mode ? ($this->payplus_payment_gateway_settings->dev_secret_key ?? null) : ($this->payplus_payment_gateway_settings->secret_key ?? null);
        $this->hidePayPlusGatewayNMW = boolval(property_exists($this->payplus_payment_gateway_settings, 'hide_main_pp_checkout') && $this->payplus_payment_gateway_settings->hide_main_pp_checkout === 'yes');
        $this->iframeAutoHeight = boolval(property_exists($this->payplus_payment_gateway_settings, 'iframe_auto_height') && $this->payplus_payment_gateway_settings->iframe_auto_height === 'yes');
        $this->popupTvEffect = boolval(property_exists($this->payplus_payment_gateway_settings, 'popup_tv_effect') && $this->payplus_payment_gateway_settings->popup_tv_effect === 'yes');

        add_action('plugins_loaded', [$this, 'load_textdomain'], 0); // Load first for gateway settings
        add_action('admin_init', [$this, 'check_environment']);
        add_action('admin_notices', [$this, 'admin_notices'], 15);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_load_textdomain_for_checkout'], 5); // Load for checkout pages (hosted fields)
        add_action('init', [$this, 'load_textdomain'], 10); // Load for other frontend pages
        add_action('plugins_loaded', [$this, 'init']);
        add_action('manage_product_posts_custom_column', [$this, 'payplus_custom_column_product'], 10, 2);
        add_action('woocommerce_email_before_order_table', [$this, 'payplus_add_content_specific_email'], 20, 4);
        add_action('wp_head', [$this, 'payplus_no_index_page_error']);
        add_action('woocommerce_api_payplus_gateway', [$this, 'ipn_response']);
        add_action('wp_ajax_make-hosted-payment', [$this, 'hostedPayment']);
        add_action('wp_ajax_nopriv_make-hosted-payment', [$this, 'hostedPayment']);
        add_action('wp_ajax_run_payplus_invoice_runner', [$this, 'ajax_run_payplus_invoice_runner']);

        // AJAX endpoint for client-side polling of order status (Firefox iframe redirect fallback)
        add_action('wp_ajax_payplus_check_order_redirect', [$this, 'ajax_payplus_check_order_redirect']);
        add_action('wp_ajax_nopriv_payplus_check_order_redirect', [$this, 'ajax_payplus_check_order_redirect']);

        // AJAX endpoint: fetch PayPlus payment page link asynchronously (Blocks iframe modes)
        add_action('wp_ajax_payplus_get_iframe_link', [$this, 'ajax_get_iframe_link']);
        add_action('wp_ajax_nopriv_payplus_get_iframe_link', [$this, 'ajax_get_iframe_link']);

        //end custom hook

        add_action('woocommerce_before_checkout_form', [$this, 'msg_checkout_code']);
        add_action('payplus_twice_hourly_cron_job', [$this, 'getPayplusCron']);
        add_action('payplus_invoice_runner_cron_job', [$this, 'getPayplusInvoiceRunnerCron']);
        add_action('template_redirect', [$this, 'payplus_check_pruid_on_checkout_load'], 5);
        add_action('woocommerce_init', [$this, 'pwgc_remove_processing_redemption'], 11);
        add_action('woocommerce_checkout_order_processed', [$this, 'payplus_checkout_order_processed'], 25, 3);
        add_action('woocommerce_thankyou', [$this, 'payplus_clear_session_on_order_received'], 10, 1);
        add_action('woocommerce_thankyou', [$this, 'payplus_clear_pw_gift_cards_session'], 10, 1);
        add_action('wp_footer', [$this, 'payplus_thankyou_iframe_redirect_script'], 5);

        //FILTER
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
        add_filter('woocommerce_available_payment_gateways', [$this, 'payplus_applepay_disable_manager']);
        add_filter('cron_schedules', [$this, 'payplus_add_custom_cron_schedule']);
        add_filter('pwgc_redeeming_session_data', [$this, 'modify_gift_card_session_data'], 10, 2);

        if (boolval($this->isPayPlus && isset($this->payplus_payment_gateway_settings->payplus_cron_service) && $this->payplus_payment_gateway_settings->payplus_cron_service === 'yes')) {
            $this->payPlusCronActivate();
            // Remove old cron function
            $timestamp = wp_next_scheduled('payplus_hourly_cron_job');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'payplus_hourly_cron_job');
            }
            // Remove old cron function
        } else {
            $this->payPlusCronDeactivate();
        }

        // Invoice Runner Cron Management
        $payplus_invoice_option = get_option('payplus_invoice_option');
        $invoiceRunnerCronEnabled = boolval(isset($payplus_invoice_option['enable_invoice_runner_cron']) && ($payplus_invoice_option['enable_invoice_runner_cron'] === 'yes' || $payplus_invoice_option['enable_invoice_runner_cron'] === 'on'));

        if ($invoiceRunnerCronEnabled) {
            $this->payPlusInvoiceRunnerCronActivate();
        } else {
            $this->payPlusInvoiceRunnerCronDeactivate();
        }
    }

    /**
     * PayPlus Embedded order processed function
     * This function gets the order object and stops execution with wp_die()
     * Displays comprehensive order information for debugging and testing
     *
     * @param int $order_id Order ID
     * @param array $posted_data Posted checkout data
     * @param WC_Order $order Order object
     */
    public function payplus_checkout_order_processed($order_id, $posted_data, $order)
    {
        // Check if this is a PayPlus payment method (any method starting with 'payplus-payment-gateway') but not hosted fields
        $payment_method = $order->get_payment_method();
        if (strpos($payment_method, 'payplus-payment-gateway') !== 0 || $payment_method === 'payplus-payment-gateway-hostedfields') {
            return; // Only process for PayPlus payments (excluding hosted fields)
        }
        WC()->session->set('page_order_awaiting_payment', $order_id);
    }

    /**
     * Check PRUID when checkout page loads (for non-hosted fields payment methods)
     * This runs when the checkout page is opened, not during payment processing
     */
    public function payplus_check_pruid_on_checkout_load()
    {
        // Only run on checkout page (but not blocks checkout - that's handled separately)
        // Clean up session on order-received page FIRST (before any early return)
        if (is_wc_endpoint_url('order-received')) {
            if (WC()->session) {
                WC()->session->__unset('page_order_awaiting_payment');
            }
            return;
        }
        // Only run on checkout page (but not blocks checkout - that's handled separately)
        if (!is_checkout()) {
            return;
        }

        // Skip if it's blocks checkout (handled in blocks support class)
        $page_id = get_the_ID();
        if ($page_id) {
            $post = get_post($page_id);
            if ($post && has_block('woocommerce/checkout', $post->post_content)) {
                return;
            }
        }

        // Check if there's a page_order_awaiting_payment in session
        if (!WC()->session) {
            return;
        }

        $order_id = WC()->session->get('page_order_awaiting_payment');
        if (!$order_id || !is_numeric($order_id)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Only check for PayPlus payment methods (not hosted fields - they use order_awaiting_payment)
        $payment_method = $order->get_payment_method();
        if (strpos($payment_method, 'payplus-payment-gateway') !== 0 || $payment_method === 'payplus-payment-gateway-hostedfields') {
            return;
        }

        // Get the main gateway instance
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        if (!isset($gateways['payplus-payment-gateway'])) {
            return;
        }

        $main_gateway = $gateways['payplus-payment-gateway'];
        if (!isset($main_gateway->enableDoubleCheckIfPruidExists) || !$main_gateway->enableDoubleCheckIfPruidExists) {
            return;
        }

        $payplus_page_request_uid = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_page_request_uid', true);

        if (empty($payplus_page_request_uid)) {
            return;
        }

        $main_gateway->payplus_add_log_all('payplus_double_check', 'Double check IPN started on Checkout Page Load - Order ID: ' . $order_id . ' | Payment Method: ' . $payment_method . ' | Page Request UID: ' . $payplus_page_request_uid);

        $PayPlusAdminPayments = new WC_PayPlus_Admin_Payments;
        $_wpnonce = wp_create_nonce('_wp_payplusIpn');
        $status = $PayPlusAdminPayments->payplusIpn(
            $order_id,
            $_wpnonce,
            $saveToken = false,
            $isHostedPayment = false,
            $allowUpdateStatuses = true,
            $allowReturn = false,
            $getInvoice = false,
            $moreInfo = false,
            $returnStatusOnly = true
        );

        $main_gateway->payplus_add_log_all('payplus_double_check', 'Checkout Page Load - Order ID: ' . $order_id . ' | Payment Method: ' . $payment_method . ' | Page Request UID: ' . $payplus_page_request_uid . ' | Response Status: ' . ($status ? $status : 'null/empty'));

        if ($status === "processing" || $status === "on-hold" || $status === "approved") {
            $main_gateway->payplus_add_log_all('payplus_double_check', 'Checkout Page Load - Order ID: ' . $order_id . ' | Payment Method: ' . $payment_method . ' | Status approved - Payment already processed, redirecting');

            // Clear cart and unset page_order_awaiting_payment, then redirect to order received page
            if (WC()->cart) {
                WC()->cart->empty_cart();
            }

            // Unset page_order_awaiting_payment since payment is complete
            if (WC()->session) {
                WC()->session->__unset('page_order_awaiting_payment');
            }

            $redirect_url = $order->get_checkout_order_received_url();
            wp_safe_redirect($redirect_url);
            exit;
        } else {
            $main_gateway->payplus_add_log_all('payplus_double_check', 'Checkout Page Load - Order ID: ' . $order_id . ' | Payment Method: ' . $payment_method . ' | Status not approved (' . ($status ? $status : 'null/empty') . ') - Continuing with checkout');
        }
    }

    /**
     * Clear session and cart when customer successfully completes order and reaches thank you page
     * 
     * @param int $order_id The order ID
     */
    public function payplus_clear_session_on_order_received($order_id)
    {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Only clear for PayPlus payment methods (not hosted fields - they use order_awaiting_payment)
        $payment_method = $order->get_payment_method();
        if (strpos($payment_method, 'payplus-payment-gateway') !== 0 || $payment_method === 'payplus-payment-gateway-hostedfields') {
            return;
        }

        // Clear cart
        if (WC()->cart) {
            WC()->cart->empty_cart();
        }

        // Unset both our custom session key and WC's own order_awaiting_payment.
        // WC's get_cart_from_session() restores cart items from a pending order when
        // order_awaiting_payment is still set — clearing it stops that re-hydration.
        if (WC()->session) {
            WC()->session->__unset('page_order_awaiting_payment');
            WC()->session->__unset('order_awaiting_payment');
        }
    }

    /**
     * Internal helper: removes all PW Gift Cards data from the active WC session.
     * Called from ipn_response() and woocommerce_thankyou so we always clear in
     * whatever context has a valid session cookie.
     */
    public function payplus_clear_pw_gift_cards_session_data()
    {
        if (!WC()->session) {
            return;
        }
        $session_key = defined('PWGC_SESSION_KEY') ? PWGC_SESSION_KEY : 'pw-gift-card-data';
        WC()->session->__unset($session_key);
    }

    /**
     * Clear PW Gift Cards session data when the order-received (thank-you) page loads.
     * Acts as a secondary cleanup layer in addition to ipn_response() and the
     * pwgc_redeeming_session_data filter which auto-prunes depleted cards.
     */
    public function payplus_clear_pw_gift_cards_session($order_id)
    {
        $this->payplus_clear_pw_gift_cards_session_data();
    }

    /**
     * When thank-you page is loaded inside the PayPlus payment iframe (e.g. Firefox blocks
     * iframe from navigating top), tell the parent to redirect so the top window goes to thank-you.
     */
    public function payplus_thankyou_iframe_redirect_script()
    {
        if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-received')) {
            return;
        }
        // Only output when we're on order-received; parent will redirect when it receives this message.
        echo "<script>(function(){if(window.self!==window.top){window.top.postMessage({type:'payplus_redirect',url:window.location.href},'*');}})();</script>\n";
    }

    /**
     * Enqueue admin scripts on appropriate pages
     */
    public function enqueue_admin_scripts($hook)
    {
        // Check if we're on the PayPlus Invoice Runner admin page using multiple methods
        $is_invoice_runner_page = false;

        // Method 1: Check the hook parameter
        if (strpos($hook, 'payplus-invoice-runner-admin') !== false) {
            $is_invoice_runner_page = true;
        }

        // Method 2: Check current screen
        $current_screen = get_current_screen();
        if ($current_screen && (
            $current_screen->id === 'admin_page_payplus-invoice-runner-admin' ||
            strpos($current_screen->id, 'payplus-invoice-runner-admin') !== false
        )) {
            $is_invoice_runner_page = true;
        }

        if ($is_invoice_runner_page) {
            // Enqueue admin script and localize variables
            wp_enqueue_script(
                'payplus-invoice-runner-admin',
                PAYPLUS_PLUGIN_URL . 'assets/js/admin.min.js',
                array('jquery'),
                PAYPLUS_VERSION,
                true
            );

            // Localize script with translated strings and admin URL
            wp_localize_script('payplus-invoice-runner-admin', 'payplus_admin_vars', array(
                'security_token_missing' => __('Security token missing. Please refresh the page and try again.', 'payplus-payment-gateway'),
                'detailed_results' => __('Detailed Results', 'payplus-payment-gateway'),
                'metric' => __('Metric', 'payplus-payment-gateway'),
                'value' => __('Value', 'payplus-payment-gateway'),
                'started_at' => __('Started At', 'payplus-payment-gateway'),
                'completed_at' => __('Completed At', 'payplus-payment-gateway'),
                'total_orders_checked' => __('Total Orders Checked', 'payplus-payment-gateway'),
                'payplus_orders_found' => __('PayPlus Orders Found', 'payplus-payment-gateway'),
                'invoices_created' => __('Invoices Created', 'payplus-payment-gateway'),
                'invoices_already_exist' => __('Invoices Already Exist', 'payplus-payment-gateway'),
                'skipped_non_payplus' => __('Non-PayPlus Orders Skipped', 'payplus-payment-gateway'),
                'errors' => __('Errors', 'payplus-payment-gateway'),
                'errors_encountered' => __('Errors Encountered:', 'payplus-payment-gateway'),
                'order_processing_details' => __('Order Processing Details:', 'payplus-payment-gateway'),
                'order_id' => __('Order ID', 'payplus-payment-gateway'),
                'payment_method' => __('Payment Method', 'payplus-payment-gateway'),
                'status' => __('Status', 'payplus-payment-gateway'),
                'reason' => __('Reason', 'payplus-payment-gateway'),
                'more_orders_message' => __('... and more orders. Check logs for complete details.', 'payplus-payment-gateway'),
                'error_parsing_response' => __('Error parsing server response. Please check the server logs.', 'payplus-payment-gateway'),
                'runner_error' => __('An error occurred while running the runner.', 'payplus-payment-gateway'),
                'request_timeout' => __('Request timed out. The runner may still be running in the background.', 'payplus-payment-gateway'),
                'security_verification_failed' => __('Security verification failed. Please refresh the page and try again.', 'payplus-payment-gateway'),
                'invalid_request_method' => __('Invalid request method.', 'payplus-payment-gateway'),
                'admin_url' => admin_url()
            ));
        }
    }

    public function modify_gift_card_session_data($session_data, $cart)
    {
        // Capture session data for internal PayPlus use (gift card discount propagation).
        $this->pwGiftCardData = $session_data;

        // Auto-remove gift cards that have been fully debited (DB balance = 0) so they do
        // not reappear in subsequent orders after payment. We only remove a card when its
        // calculated session amount is also 0 — if another card already covered the total,
        // a valid card may legitimately show 0 amount and should not be pruned.
        if (!empty($session_data['gift_cards']) && is_array($session_data['gift_cards']) && class_exists('PW_Gift_Card')) {
            foreach ($session_data['gift_cards'] as $card_number => $amount) {
                if ($amount == 0) {
                    $gift_card_obj = new PW_Gift_Card($card_number);
                    if ($gift_card_obj->get_id() && floatval($gift_card_obj->get_balance()) <= 0) {
                        unset($session_data['gift_cards'][$card_number]);
                    }
                }
            }
        }

        return $session_data;
    }

    /**
     * Remove early PW Gift Cards redemption hooks so gift cards are only debited
     * when order status is "processing" or "completed" instead of immediately at checkout.
     * 
     * This prevents gift cards from being debited before payment is confirmed,
     * ensuring they are only used when the order is actually processing or completed.
     */
    public function pwgc_remove_processing_redemption()
    {
        global $pw_gift_cards_redeeming, $pw_gift_cards_blocks;

        // Classic checkout: remove hooks that debit gift cards immediately during checkout submission.
        if (isset($pw_gift_cards_redeeming) && is_object($pw_gift_cards_redeeming)) {
            remove_action('woocommerce_pre_payment_complete', array($pw_gift_cards_redeeming, 'woocommerce_pre_payment_complete'));
            remove_action('woocommerce_checkout_update_order_meta', array($pw_gift_cards_redeeming, 'woocommerce_checkout_update_order_meta'), 10, 2);
        }

        // Blocks checkout: PW Gift Cards hooks woocommerce_store_api_checkout_order_processed
        // and immediately calls debit_gift_cards() before PayPlus opens the payment page.
        // We replace it with our own handler that:
        //   - Still adds the gift card line item to the WC order and recalculates totals
        //     (so the order-pay page shows the correct discounted amount), but
        //   - Does NOT call debit_gift_cards() — the balance is debited only when the order
        //     reaches "processing" or "completed" status, exactly as with classic checkout.
        if (isset($pw_gift_cards_blocks) && is_object($pw_gift_cards_blocks)) {
            remove_action('woocommerce_store_api_checkout_order_processed', array($pw_gift_cards_blocks, 'woocommerce_store_api_checkout_order_processed'));

            add_action('woocommerce_store_api_checkout_order_processed', function ($order) {
                global $pw_gift_cards_redeeming;
                if (!is_a($pw_gift_cards_redeeming, 'PW_Gift_Cards_Redeeming')) {
                    return;
                }

                // Remove any existing gift card line items before adding fresh ones.
                // When the same pending order is resubmitted (e.g. the customer closes
                // and reopens the payment page), this hook fires again on the same order.
                // Without this removal each submission would stack another set of items.
                foreach ($order->get_items('pw_gift_card') as $item_id => $item) {
                    $order->remove_item($item_id);
                }

                // Add gift card items to the order and recalculate totals so the
                // WooCommerce order total reflects the gift card discount.
                $pw_gift_cards_redeeming->woocommerce_checkout_create_order($order);
                if ($order->get_items('pw_gift_card')) {
                    $order->calculate_totals();
                    $order->save();
                }
                // debit_gift_cards() intentionally omitted — debited on order status change.
            });
        }

        // Note: woocommerce_order_status_processing and woocommerce_order_status_completed
        // hooks remain intact so gift cards are still debited once payment is confirmed.
    }

    public function wc_payplus_check_version()
    {
        $previous_version = get_option('wc_payplus_version');
        $display_count = get_option('wc_payplus_display_maam_count', 0);

        if (version_compare($previous_version, '7.4.3', '<')) {
            if ($display_count < 520) {
                add_action('admin_notices', [$this, 'wc_payplus_show_update_message']);
                update_option('wc_payplus_display_maam_count', $display_count + 1);
            }
            update_option('wc_payplus_version', PAYPLUS_VERSION);
        }
    }

    public function wc_payplus_show_update_message()
    {
?>
        <div id="wc-payplus-update-message" class="notice notice-error is-dismissible">
            <p> <?php
                echo wp_kses_post(
                    __(
                        '<strong style="font-size: 1.2em;">Dear Customers,</strong><br><br>

        <span style="font-size: 1.2em;"><strong>Attention!</strong> For users of the <strong>PayPlus</strong> plugin who calculate VAT via WordPress, it is crucial to update the VAT rate from 17% to 18% starting on January 1st.<br>
        This update must be performed specifically on January 1st to ensure accurate calculations for transactions and payments.<br>
        Please ensure this update is completed on this date.<br><br>

        Thank you,<br>
        <strong>The PayPlus Team</strong></span>',
                        'payplus-payment-gateway'
                    )
                );
                ?>
            </p>
        </div>
    <?php
    }

    public function hostedPayment()
    {
        check_ajax_referer('frontNonce', '_ajax_nonce');
        $this->payplus_gateway = $this->get_main_payplus_gateway();
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $pwGiftCardData = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_pw_gift_cards');
        $decodedCardData = json_decode($pwGiftCardData, true);

        if (!empty($pwGiftCardData) && is_array($decodedCardData)) {
            // Get the first value from the $pwGiftCardData array
            $firstGiftCard = reset($decodedCardData['gift_cards']);
            if ($this->payplus_gateway->pw_gift_card_auto_cancel_unpaid_order && floatval($firstGiftCard) == 0) {
                $cancelledResponse = $this->payplus_gateway->cancel_pending_giftcard_orders_for_current_user($pwGiftCardData, $order_id);
                if ($cancelledResponse === false) {
                    wc_add_notice(__('Gift Card refreshed - Please <a href="#">try again</a>.', 'payplus-payment-gateway'), 'error');
                    wp_send_json_error([
                        'result' => 'fail',
                        'redirect' => '',
                    ]);
                }
            }
        }
        $order = wc_get_order($order_id);
        if ($order) {
            $saveToken = isset($_POST['saveToken']) ? filter_var(wp_unslash($_POST['saveToken']), FILTER_VALIDATE_BOOLEAN) : false;
            $linkRedirect = html_entity_decode(esc_url($this->payplus_gateway->get_return_url($order)));
            $metaData['payplus_page_request_uid'] = isset($_POST['page_request_uid']) ? sanitize_text_field(wp_unslash($_POST['page_request_uid'])) : null;
            WC_PayPlus_Meta_Data::update_meta($order, $metaData);
            $PayPlusAdminPayments = new WC_PayPlus_Admin_Payments;
            $_wpnonce = wp_create_nonce('_wp_payplusIpn');
            $PayPlusAdminPayments->payplusIpn($order_id, $_wpnonce, $saveToken, true);
            WC()->session->set('hostedTimeStamp', false);
            WC()->session->set('hostedPayload', false);
            WC()->session->set('page_request_uid', false);
            WC()->session->set('hostedResponse', false);
            WC()->session->__unset('order_awaiting_payment');
            WC()->session->__unset('hostedFieldsUUID');
            WC()->session->set('hostedStarted', false);
            WC()->session->set('randomHash', bin2hex(random_bytes(16)));
            wp_send_json_success(array('result' => "success"));
        }
    }

    public function payPlusCronDeactivate()
    {
        $timestamp = wp_next_scheduled('payplus_twice_hourly_cron_job');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'payplus_twice_hourly_cron_job');
        }
    }

    public function payplus_add_custom_cron_schedule($schedules)
    {
        $schedules['half_hour'] = array(
            'interval' => 1800, // 30 minutes in seconds
            'display'  => __('Every 30 Minutes', 'payplus-payment-gateway'),
        );
        return $schedules;
    }


    public function payPlusCronActivate()
    {
        if (!wp_next_scheduled('payplus_twice_hourly_cron_job')) {
            wp_schedule_event(time(), 'half_hour', 'payplus_twice_hourly_cron_job');
        }
    }

    /**
     * Activate PayPlus Invoice Runner Cron Job
     */
    public function payPlusInvoiceRunnerCronActivate()
    {
        if (!wp_next_scheduled('payplus_invoice_runner_cron_job')) {
            wp_schedule_event(time(), 'half_hour', 'payplus_invoice_runner_cron_job');
        }
    }

    /**
     * Deactivate PayPlus Invoice Runner Cron Job
     */
    public function payPlusInvoiceRunnerCronDeactivate()
    {
        $timestamp = wp_next_scheduled('payplus_invoice_runner_cron_job');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'payplus_invoice_runner_cron_job');
        }
    }

    /**
     * Cron job handler for running the PayPlus invoice runner automatically
     */
    public function getPayplusInvoiceRunnerCron()
    {
        $this->payplus_gateway = $this->get_main_payplus_gateway();
        $this->payplus_gateway->payplus_add_log_all('payplus-invoice-runner-log', 'getPayplusInvoiceRunnerCron: Starting automatic cron job execution.', 'default');

        $results = $this->getPayplusInvoiceRunner();

        $log_message = sprintf(
            'Cron execution completed. Processed %d orders total. PayPlus orders found: %d. Invoices created: %d. Invoices already existed: %d. Non-PayPlus orders skipped: %d.',
            $results['total_orders_checked'],
            $results['payplus_orders_found'],
            $results['invoices_created'],
            $results['invoices_already_exist'],
            $results['skipped_non_payplus']
        );

        if (!empty($results['errors'])) {
            $log_message .= ' Errors encountered: ' . count($results['errors']);
            foreach ($results['errors'] as $error) {
                $this->payplus_gateway->payplus_add_log_all('payplus-invoice-runner-log', 'Cron Error: ' . $error, 'default');
            }
        }

        $this->payplus_gateway->payplus_add_log_all('payplus-invoice-runner-log', $log_message, 'default');
    }

    /**
     * Ajax handler for running the PayPlus invoice runner manually
     */
    public function ajax_run_payplus_invoice_runner()
    {
        // Check if this is a POST request
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(wp_json_encode(['success' => false, 'message' => 'Invalid request method']), '', ['response' => 405]);
        }

        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(wp_json_encode(['success' => false, 'message' => 'Insufficient permissions. Only administrators can run this function.']), '', ['response' => 403]);
        }

        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'payplus_invoice_runner_nonce')) {
            wp_die(wp_json_encode(['success' => false, 'message' => 'Security verification failed. Please refresh the page and try again.']), '', ['response' => 403]);
        }

        // Additional check: verify the action parameter
        if (!isset($_POST['action']) || sanitize_text_field(wp_unslash($_POST['action'])) !== 'run_payplus_invoice_runner') {
            wp_die(wp_json_encode(['success' => false, 'message' => 'Invalid action parameter']), '', ['response' => 400]);
        }

        // Run the invoice runner function and capture results
        $results = $this->getPayplusInvoiceRunner();

        // Format the response message
        $message = sprintf(
            'Invoice runner completed successfully! Processed %d orders total. PayPlus orders found: %d. Invoices created: %d. Invoices already existed: %d. Non-PayPlus orders skipped: %d.',
            $results['total_orders_checked'],
            $results['payplus_orders_found'],
            $results['invoices_created'],
            $results['invoices_already_exist'],
            $results['skipped_non_payplus']
        );

        if (!empty($results['errors'])) {
            $message .= ' ' . sprintf('Errors encountered: %d', count($results['errors']));
        }

        wp_die(wp_json_encode([
            'success' => true,
            'message' => $message,
            'data' => $results
        ]), '', ['response' => 200]);
    }


    /**
     * Runner function to check and create missing PayPlus invoices for processing orders
     *
     * @return array
     */
    public function getPayplusInvoiceRunner()
    {
        $current_time = current_time('Y-m-d H:i:s');
        $results = [
            'started_at' => $current_time,
            'total_orders_checked' => 0,
            'payplus_orders_found' => 0,
            'invoices_already_exist' => 0,
            'invoices_created' => 0,
            'skipped_non_payplus' => 0,
            'processed_orders' => [],
            'errors' => []
        ];

        $args = array(
            'status' => ['processing'],
            'date_created' => '>=' . gmdate('Y-m-d 00:00:00'), // Only orders created today
            'return' => 'ids', // Just return IDs to save memory
            'limit'  => -1, // Retrieve all orders
        );
        $this->payplus_gateway = $this->get_main_payplus_gateway();

        $orders = wc_get_orders($args);
        $results['total_orders_checked'] = count($orders);

        $this->payplus_gateway->payplus_add_log_all('payplus-invoice-runner-log', 'getPayplusInvoiceRunner process started:' . "\n" . 'Checking orders with status "processing" for missing invoices.' . "\nOrders:" . wp_json_encode($orders), 'default');

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);

            // Skip subscription renewal orders if setting is enabled
            $payplus_settings = get_option('woocommerce_payplus-payment-gateway_settings');
            $skip_subscriptions = isset($payplus_settings['payplus_cron_skip_subscriptions']) &&
                $payplus_settings['payplus_cron_skip_subscriptions'] === 'yes';

            if ($skip_subscriptions) {
                // Skip subscription renewal orders - they inherit meta from parent subscription
                if (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($order)) {
                    $this->payplus_gateway->payplus_add_log_all('payplus-invoice-runner-log', "$order_id: Skipping - this is a subscription renewal order (setting enabled).\n");
                    $results['skipped_non_payplus']++;
                    $results['processed_orders'][] = [
                        'order_id' => $order_id,
                        'payment_method' => $order->get_payment_method(),
                        'status' => 'skipped',
                        'reason' => 'Subscription renewal order (setting enabled)'
                    ];
                    continue;
                }

                // Alternative check for renewal orders if the above function doesn't catch it
                $is_renewal = WC_PayPlus_Meta_Data::get_meta($order_id, '_subscription_renewal');
                if ($is_renewal) {
                    $this->payplus_gateway->payplus_add_log_all('payplus-invoice-runner-log', "$order_id: Skipping - this is a subscription renewal order (meta check, setting enabled).\n");
                    $results['skipped_non_payplus']++;
                    $results['processed_orders'][] = [
                        'order_id' => $order_id,
                        'payment_method' => $order->get_payment_method(),
                        'status' => 'skipped',
                        'reason' => 'Subscription renewal order (meta check, setting enabled)'
                    ];
                    continue;
                }
            }

            // Check if order uses PayPlus payment method
            $payment_method = $order->get_payment_method();

            // Process all orders regardless of payment method
            $payPlusInvoiceOptions = get_option('payplus_invoice_option');
            // Check if payment method is in do-not-create list
            if (
                isset($payPlusInvoiceOptions['do-not-create']) &&
                is_array($payPlusInvoiceOptions['do-not-create']) &&
                in_array($payment_method, $payPlusInvoiceOptions['do-not-create'])
            ) {

                $this->payplus_gateway->payplus_add_log_all('payplus-invoice-runner-log', "$order_id: Payment method '$payment_method' is in do-not-create list - skipping invoice creation.\n");
                $results['skipped_non_payplus']++;
                $results['processed_orders'][] = [
                    'order_id' => $order_id,
                    'payment_method' => $payment_method,
                    'status' => 'skipped',
                    'reason' => 'Payment method in do-not-create invoice docs list'
                ];
                continue;
            }

            $results['payplus_orders_found']++;

            // Check if invoice already exists
            $invoice_sent = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_check_invoice_send');
            $invoice_sent_refund = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_check_invoice_send_refund');
            $invoice_error = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_error_invoice');
            $invoice_doc_uid = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_docUID');

            $has_invoice = ($invoice_sent === "1" || $invoice_sent === true) ||
                ($invoice_sent_refund === "1" || $invoice_sent_refund === true) ||
                (!empty($invoice_error) && strpos($invoice_error, 'unique-identifier-exists') !== false) ||
                (!empty($invoice_doc_uid));

            if (!$has_invoice) {
                $this->payplus_gateway->payplus_add_log_all('payplus-invoice-runner-log', "$order_id: No invoice found - Creating invoice for processing order.\n");

                try {
                    // Create invoice using the invoice API
                    if ($this->invoice_api && $this->invoice_api->payplus_get_invoice_enable()) {
                        $this->invoice_api->payplus_invoice_create_order($order_id);
                        $order->add_order_note('PayPlus Invoice Runner: Invoice created automatically.');
                        $results['invoices_created']++;
                        $results['processed_orders'][] = [
                            'order_id' => $order_id,
                            'payment_method' => $payment_method,
                            'status' => 'invoice_created',
                            'reason' => 'Invoice created successfully'
                        ];
                    } else {
                        $results['errors'][] = "Order $order_id: Invoice API not available or not enabled";
                        $results['processed_orders'][] = [
                            'order_id' => $order_id,
                            'payment_method' => $payment_method,
                            'status' => 'error',
                            'reason' => 'Invoice API not available or not enabled'
                        ];
                    }
                } catch (Exception $e) {
                    $error_msg = "Order $order_id: Error creating invoice - " . $e->getMessage();
                    $results['errors'][] = $error_msg;
                    $results['processed_orders'][] = [
                        'order_id' => $order_id,
                        'payment_method' => $payment_method,
                        'status' => 'error',
                        'reason' => $e->getMessage()
                    ];
                }
            } else {
                $this->payplus_gateway->payplus_add_log_all('payplus-invoice-runner-log', "$order_id: Invoice already exists - skipping.\n");
                $results['invoices_already_exist']++;
                $results['processed_orders'][] = [
                    'order_id' => $order_id,
                    'payment_method' => $payment_method,
                    'status' => 'skipped',
                    'reason' => 'Invoice already exists'
                ];
            }
        }

        $results['completed_at'] = current_time('Y-m-d H:i:s');
        $this->payplus_gateway->payplus_add_log_all('payplus-invoice-runner-log', 'getPayplusInvoiceRunner process completed.', 'default');

        return $results;
    }

    /**
     * Admin page for PayPlus Invoice Runner Management
     */
    public static function payplus_invoice_runner_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'payplus-payment-gateway'));
        }

    ?>
        <div class="wrap">
            <h1><?php echo esc_html__('PayPlus Invoice Runner Management', 'payplus-payment-gateway'); ?></h1>
            <p><?php echo esc_html__('Use the button below to manually run the PayPlus invoice runner to check and create missing invoices for processing orders.', 'payplus-payment-gateway'); ?>
            </p>

            <div id="payplus-runner-result" style="margin: 20px 0;"></div>

            <?php wp_nonce_field('payplus_invoice_runner_form', 'payplus_invoice_runner_form_nonce'); ?>

            <button type="button" id="run-payplus-invoice-runner" class="button button-primary"
                data-nonce="<?php echo esc_attr(wp_create_nonce('payplus_invoice_runner_nonce')); ?>">
                <?php echo esc_html__('Run Invoice Runner Now', 'payplus-payment-gateway'); ?>
            </button>

            <div id="payplus-runner-loading" style="display: none; margin-top: 10px;">
                <span class="spinner is-active"></span>
                <?php echo esc_html__('Running invoice runner...', 'payplus-payment-gateway'); ?>
            </div>
        </div>

        <style>
            .payplus-results-detail table {
                margin-top: 15px;
            }

            .payplus-results-detail th {
                background-color: #f9f9f9;
                font-weight: bold;
            }

            .payplus-results-detail td {
                padding: 8px 12px;
            }

            #run-payplus-invoice-runner:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
        </style>
        <?php
    }
    public function getPayplusCron()
    {
        $current_time = current_time('Y-m-d H:i:s');

        // Extract the current hour and minute
        $current_hour = gmdate('H', strtotime($current_time));
        $current_minute = gmdate('i', strtotime($current_time));

        $args = array(
            'status' => ['pending', 'cancelled'],
            'date_created' => $current_time,
            'return' => 'ids', // Just return IDs to save memory
            'limit'  => -1, // Retrieve all orders
        );
        $this->payplus_gateway = $this->get_main_payplus_gateway();

        $orders = array_reverse(wc_get_orders($args));
        $this->payplus_gateway->payplus_add_log_all('payplus-cron-log', 'getPayplusCron process started:' . "\n" . 'Checking orders with statuses of: "pending" and "cancelled" created last half an hour ago and today.' . "\nOrders:" . wp_json_encode($orders), 'default');
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);

            // Skip subscription renewal orders if setting is enabled
            $skip_subscriptions = isset($this->payplus_payment_gateway_settings->payplus_cron_skip_subscriptions) &&
                $this->payplus_payment_gateway_settings->payplus_cron_skip_subscriptions === 'yes';

            if ($skip_subscriptions) {
                // Skip subscription renewal orders - they inherit meta from parent subscription
                if (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($order)) {
                    $this->payplus_gateway->payplus_add_log_all('payplus-cron-log', "$order_id: Skipping - this is a subscription renewal order (setting enabled).\n");
                    continue;
                }

                // Alternative check for renewal orders if the above function doesn't catch it
                $is_renewal = WC_PayPlus_Meta_Data::get_meta($order_id, '_subscription_renewal');
                if ($is_renewal) {
                    $this->payplus_gateway->payplus_add_log_all('payplus-cron-log', "$order_id: Skipping - this is a subscription renewal order (meta check, setting enabled).\n");
                    continue;
                }
            }

            $hour = $order->get_date_created()->date('H');
            $min = $order->get_date_created()->date('i');
            $calc = $current_minute - $min;
            $isEligible = boolval($current_hour === $hour && $calc < 30);
            $runIpn = true;
            $status = $order->get_status();
            if ($current_hour >= $hour - 2) {
                $paymentPageUid = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_page_request_uid') !== "" ? WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_page_request_uid') : false;
                $payPlusCronTested = !empty(WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_cron_tested')) ? WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_cron_tested') : 1;
                if ($paymentPageUid && $payPlusCronTested < 5) {
                    ++$payPlusCronTested;
                    if ($status === 'cancelled' || $status === 'wc-cancelled') {
                        $payPlusResponse = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_response');
                        if (WC_PayPlus_Statics::pp_is_json($payPlusResponse)) {
                            $responseStatus = json_decode($payPlusResponse, true)['status_code'];
                            $hasInvoice = $this->invoice_api->payplus_get_invoice_enable() && boolval(WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_check_invoice_send') === "1");
                            if ($responseStatus === "000" && $hasInvoice) {
                                $this->payplus_gateway->payplus_add_log_all('payplus-cron-log', "$order_id - status = $status: ATTENTION: Order has successful response,*PROBABLY* edited manually - NOT Running IPN -\n");
                                $runIpn = false;
                            } else {
                                $this->payplus_gateway->payplus_add_log_all('payplus-cron-log', "$order_id - status = $status: ATTENTION: Order has successful response but no invoice - Running IPN\n");
                            }
                        }
                    }
                    if ($runIpn) {
                        WC_PayPlus_Meta_Data::update_meta($order, ['payplus_cron_tested' => $payPlusCronTested]);
                        $this->payplus_gateway->payplus_add_log_all('payplus-cron-log', "$order_id: created in the last two hours - created at: $hour:$min diff calc (minutes): $calc - Running IPN - check order for results.\n");
                        $PayPlusAdminPayments = new WC_PayPlus_Admin_Payments;
                        $_wpnonce = wp_create_nonce('_wp_payplusIpn');
                        $order->add_order_note('PayPlus Cron: Running IPN.');
                        $PayPlusAdminPayments->payplusIpn(
                            $order_id,
                            $_wpnonce,
                            $saveToken = false,
                            $isHostedPayment = false,
                            $allowUpdateStatuses = true,
                            $allowReturn = false,
                            $getInvoice = false,
                            $moreInfo = false,
                            $returnStatusOnly = false,
                            $isCron = true
                        );
                    }
                } else {
                    $this->payplus_gateway->payplus_add_log_all('payplus-cron-log', "$order_id - status = $status: Was already tested with cron more than 4 times - skipping.\n");
                }
            } else {
                $this->payplus_gateway->payplus_add_log_all('payplus-cron-log', "$order_id - status = $status: is not yet eligible for test.\n");
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

    public function checkRunIpnResponse($order_id, $order, $number)
    {
        $this->payplus_gateway = $this->get_main_payplus_gateway();
        $this->payplus_gateway->payplus_add_log_all('payplus_ipn_response', "$order_id: checkRunIpnResponse - number: $number\n");
        $payPlusResponse = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_response');
        if (empty($payPlusResponse) || $order->get_status() === "pending") {
            $_wpnonce = wp_create_nonce('_wp_payplusIpn');
            $PayPlusAdminPayments = new WC_PayPlus_Admin_Payments;
            $PayPlusAdminPayments->payplusIpn($order_id, $_wpnonce);
        }
    }

    /**
     * @return void
     */
    public function ipn_response()
    {
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'payload_link')) {
            $order_id = isset($_REQUEST['more_info']) ? sanitize_text_field(wp_unslash($_REQUEST['more_info'])) : false;
            if ($order_id) {
                //failed nonce check, will be redirected to regular thank you page with ipn
                $order = wc_get_order($order_id);
                $this->updateStatusesIpn ? $this->checkRunIpnResponse($order_id, $order, 1) : null;
                if (WC()->cart) {
                    WC()->cart->empty_cart();
                }
                if (WC()->session) {
                    WC()->session->__unset('page_order_awaiting_payment');
                }
                $this->payplus_clear_pw_gift_cards_session_data();
                $redirect_to = add_query_arg('order-received', $order_id, get_permalink(wc_get_page_id('checkout')));
                $this->payplus_redirect_graceful($redirect_to);
            } else {
                // no order id
                wp_die('Invalid request');
            }
        }

        $this->payplus_gateway = $this->get_main_payplus_gateway();
        $REQUEST = $this->payplus_gateway->arr_clean($_REQUEST);

        if (isset($_GET['hostedFields']) && $_GET['hostedFields'] === "true") {
            $REQUEST = json_decode(stripslashes($REQUEST['jsonData']), true)['data'];
        }

        $order_id = isset($REQUEST['more_info']) ? sanitize_text_field(wp_unslash($REQUEST['more_info'])) : '';
        $order = wc_get_order($order_id);
        $this->updateStatusesIpn ? $this->checkRunIpnResponse($order_id, $order, 2) : null;

        global $wpdb;
        $tblname = $wpdb->prefix . 'payplus_payment_process';
        $tblname = esc_sql($tblname);
        $indexRow = 0;
        if (!empty($REQUEST['more_info'])) {
            if (!isset($_REQUEST['status_code'])) {
                $_REQUEST = $REQUEST;
            }

            $status_code = isset($_REQUEST['status_code']) ? sanitize_text_field(wp_unslash($_REQUEST['status_code'])) : '';

            if ($status_code !== '000') {
                $this->payplus_gateway->store_payment_ip();
            }

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

                if (!boolval(isset($_GET['hostedFields']) && $_GET['hostedFields'] === "true")) {
                    $order = $this->payplus_gateway->validateOrder($data);
                } else {
                    $order_id = $REQUEST['more_info'];
                    $order = wc_get_order($order_id);
                }

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
                WC()->session->__unset('order_awaiting_payment');
                WC()->session->__unset('page_order_awaiting_payment');
                $this->payplus_clear_pw_gift_cards_session_data();
                if (WC()->cart) {
                    WC()->cart->empty_cart();
                }
                $this->payplus_redirect_graceful($linkRedirect);
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

                // Check if payment was initiated from admin dashboard
                if (isset($REQUEST['paymentPayPlusDashboard']) && !empty($REQUEST['paymentPayPlusDashboard'])) {
                    $paymentPayPlusDashboard = $REQUEST['paymentPayPlusDashboard'];
                    if ($paymentPayPlusDashboard === $this->payplus_gateway->payplus_generate_key_dashboard) {
                        $order->set_payment_method('payplus-payment-gateway');
                        $order->set_payment_method_title('Pay with Debit or Credit Card');
                        $linkRedirect = esc_url(get_admin_url()) . "post.php?post=" . $order_id . "&action=edit";
                    }
                }

                WC()->session->__unset('save_payment_method');
                WC()->session->__unset('order_awaiting_payment');
                WC()->session->__unset('page_order_awaiting_payment');
                $this->payplus_clear_pw_gift_cards_session_data();
                if (WC()->cart) {
                    WC()->cart->empty_cart();
                }
                $this->payplus_redirect_graceful($linkRedirect);
            }
        } elseif (
            isset($_GET['success_order_id']) && isset($_GET['charge_method']) && $_GET['charge_method'] === 'bit' ||
            isset($_GET['success_order_id']) && isset($_GET['charge_method']) && $_GET['charge_method'] === 'credit-card'
        ) {
            $order_id = isset($_GET['success_order_id']) ? intval($_GET['success_order_id']) : 0;
            $order = wc_get_order($order_id);
            if ($order) {
                $linkRedirect = html_entity_decode(esc_url($this->payplus_gateway->get_return_url($order)));
                WC()->session->__unset('save_payment_method');
                WC()->session->__unset('order_awaiting_payment');
                WC()->session->__unset('page_order_awaiting_payment');
                $this->payplus_clear_pw_gift_cards_session_data();
                if (WC()->cart) {
                    WC()->cart->empty_cart();
                }
                $this->payplus_redirect_graceful($linkRedirect);
            }
        }
    }

    /**
     * AJAX handler: returns order status + redirect URL for client-side polling.
     *
     * The checkout page polls this endpoint after opening the payment iframe.
     * When the order moves to processing/completed (via IPN callback), the
     * client-side JS detects it and redirects the top window — completely
     * bypassing any iframe-to-parent navigation that Firefox might block.
     */
    public function ajax_payplus_check_order_redirect()
    {
        check_ajax_referer('frontNonce', '_ajax_nonce');

        $order_id  = isset($_REQUEST['order_id']) ? absint($_REQUEST['order_id']) : 0;
        $order_key = isset($_REQUEST['order_key']) ? sanitize_text_field(wp_unslash($_REQUEST['order_key'])) : '';

        if (!$order_id || !$order_key) {
            wp_send_json_error(['status' => 'invalid']);
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $order_key) {
            wp_send_json_error(['status' => 'invalid']);
        }

        $status       = $order->get_status();
        $redirect_url = $order->get_checkout_order_received_url();

        wp_send_json_success([
            'status'       => $status,
            'redirect_url' => $redirect_url,
        ]);
    }

    /**
     * Async AJAX handler for Blocks Checkout iframe modes.
     *
     * During checkout submission, add_payment_request_order_meta() builds the payload
     * (fast, local) and saves it to order meta, then returns immediately — without
     * making the slow external PayPlus HTTP call.  This handler picks up the saved
     * payload and runs payPlusRemote() after the checkout form has already unblocked,
     * so the 7-10 s PayPlus API delay is hidden behind the loading overlay.
     */
    public function ajax_get_iframe_link()
    {
        check_ajax_referer('frontNonce', '_ajax_nonce');

        $order_id  = isset($_REQUEST['order_id']) ? absint($_REQUEST['order_id']) : 0;
        $order_key = isset($_REQUEST['order_key']) ? sanitize_text_field(wp_unslash($_REQUEST['order_key'])) : '';

        if (!$order_id || !$order_key) {
            wp_send_json_error(['message' => 'Invalid order']);
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $order_key) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        $payload     = WC_PayPlus_Meta_Data::get_meta($order, 'payplus_payload');
        $payment_url = WC_PayPlus_Meta_Data::get_meta($order, 'payplus_payment_url');

        if (empty($payload) || empty($payment_url)) {
            wp_send_json_error(['message' => 'Payment data missing — please retry checkout']);
        }

        // This is the only slow line: the external PayPlus HTTP request.
        $response      = WC_PayPlus_Statics::payPlusRemote($payment_url, $payload);
        $responseArray = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($responseArray['results']) || $responseArray['results']['status'] === 'error') {
            $msg = isset($responseArray['results']['description'])
                ? wp_strip_all_tags($responseArray['results']['description'])
                : (isset($responseArray['message']) ? $responseArray['message'] : 'Unknown PayPlus error');
            wp_send_json_error(['message' => $msg]);
        }

        $link = $responseArray['data']['payment_page_link'];

        // Persist IPN-matching data now that we have it.
        WC_PayPlus_Meta_Data::update_meta($order, [
            'payplus_page_request_uid'  => $responseArray['data']['page_request_uid'],
            'payplus_payment_page_link' => $link,
        ]);

        // Set session so cart restoration works correctly.
        $async_method = WC_PayPlus_Meta_Data::get_meta($order, 'payplus_async_method');
        if ($async_method !== 'payplus-payment-gateway-hostedfields' && WC()->session) {
            WC()->session->set('page_order_awaiting_payment', $order_id);
        }

        wp_send_json_success([
            'payment_page_link'  => $link,
            'order_id'           => $order_id,
            'order_key'          => $order_key,
            'order_received_url' => $order->get_checkout_order_received_url(),
        ]);
    }

    /**
     * Redirect to thank-you page in a way that works for both iframe and top-window contexts.
     *
     * IFRAME context (Sec-Fetch-Dest: iframe/frame/embed):
     *   Outputs a minimal HTML page that sends postMessage({type:'payplus_redirect', url})
     *   to the parent window. The parent checkout JS picks it up and redirects the top
     *   window to the thank-you URL. The IPN/callback URL is never visible in the address bar.
     *
     * TOP-WINDOW context (direct visit, Sec-Fetch-Dest: document/navigate/empty):
     *   Issues an immediate 302 to the thank-you URL so the IPN/callback URL is never
     *   the final URL in the browser address bar.
     *
     * @param string $url The thank-you URL to redirect to.
     */
    private function payplus_redirect_graceful($url)
    {
        $url = esc_url($url);

        // Detect request context via Sec-Fetch-Dest (Chrome 80+, Firefox 90+, Safari 17+).
        $fetch_dest = isset($_SERVER['HTTP_SEC_FETCH_DEST'])
            ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_SEC_FETCH_DEST'])))
            : '';

        // Server-to-server IPN: no browser, no Accept: text/html — skip silently.
        $accept     = isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'])) : '';
        $is_browser = (strpos($accept, 'text/html') !== false)
            || in_array($fetch_dest, ['iframe', 'frame', 'embed', 'document', 'navigate', ''], true);
        if (!$is_browser) {
            return;
        }

        // TOP-WINDOW request (redirect mode, or iframe mode after allow-top-navigation fires):
        // Issue an instant 302 — no HTML rendered, no spinner flash, clean URL immediately.
        $is_iframe = in_array($fetch_dest, ['iframe', 'frame', 'embed'], true);
        if (!$is_iframe) {
            wp_safe_redirect($url);
            exit;
        }

        // IFRAME request (edge case: PayPlus navigated the iframe itself to the callback URL).
        // Send postMessage to the parent so it can redirect the top window cleanly.
        // The spinner is shown as a brief visual while the parent processes the message.
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        $msg      = __('Payment received — redirecting…', 'payplus-payment-gateway');
        $dir      = is_rtl() ? 'rtl' : 'ltr';
        $lang     = get_bloginfo('language');
        echo '<!DOCTYPE html>
<html lang="' . esc_attr($lang) . '" dir="' . esc_attr($dir) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;width:100%}
body{
  display:flex;align-items:center;justify-content:center;
  flex-direction:column;gap:24px;
  background:#fff;
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,sans-serif;
  text-align:center;padding:20px;
}
.pp-msg{font-size:clamp(15px,4vw,20px);font-weight:500;color:#333;letter-spacing:.01em}
.pp-spinner{
  width:44px;height:44px;
  border:4px solid #e0e0e0;
  border-top-color:#2563eb;
  border-radius:50%;
  animation:pp-spin .8s linear infinite;
}
@keyframes pp-spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<p class="pp-msg">' . esc_html($msg) . '</p>
<div class="pp-spinner"></div>
<script>(function(){
  var u=' . wp_json_encode($url) . ';
  try{window.parent.postMessage({type:"payplus_redirect",url:u},"*");}catch(e){}
  if(window.self===window.top){window.location.href=u;}
})();</script>
</body>
</html>';
        exit;
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
        $invoice_api = $this->invoice_api;
        $payment_method = $order->get_payment_method();
        if (strpos($payment_method, 'payplus') === false) {
            //$amount = WC_PayPlus_Meta_Data::get_meta($refund_id, '_refund_amount', true);
            $amount = $order->get_total_refunded();
            if (floatval($amount)) {
                $invoice_api->payPlusCreateRefundInvoicePlus(
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
        $integrity_check_result = get_transient('payplus_plugin_integrity_check_failed');
        if ($integrity_check_result) {
            delete_transient('payplus_plugin_integrity_check_failed');
        ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong>PayPlus Plugin Security Warning:</strong> <?php echo esc_html($integrity_check_result); ?>
                </p>
            </div><?php
                }

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
                    'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway') . '" aria-label="' . esc_html__('View PayPlus Settings', 'payplus-payment-gateway') . '">' . esc_html__('Settings', 'payplus-payment-gateway') . '</a>',
                ];
                $links = array_merge($action_links, $links);

                return $links;
            }

            /**
             * Load translations for checkout pages (wp_enqueue_scripts hook)
             * This ensures hosted fields have translations available when they're instantiated
             * 
             * @return void
             */
            public function maybe_load_textdomain_for_checkout()
            {
                // Load for checkout pages where hosted fields are used
                // wp_enqueue_scripts fires after init, so this is safe and won't trigger warnings
                if (function_exists('is_checkout') && is_checkout() && !is_textdomain_loaded('payplus-payment-gateway')) {
                    $this->load_textdomain();
                }
            }

            /**
             * Load plugin text domain for translations
             * Called on admin_init (priority 5) for admin pages and init (priority 10) for frontend
             * 
             * @return void
             */
            public function load_textdomain()
            {
                // Load translations directly to avoid "too early" warnings
                // First try WordPress language directory (for translations from wordpress.org)
                $locale = determine_locale();
                $mofile = WP_LANG_DIR . '/plugins/payplus-payment-gateway-' . $locale . '.mo';

                if (file_exists($mofile)) {
                    load_textdomain('payplus-payment-gateway', $mofile);
                } else {
                    // Fallback to plugin's own languages directory
                    $mofile = PAYPLUS_PLUGIN_DIR . '/languages/payplus-payment-gateway-' . $locale . '.mo';
                    if (file_exists($mofile)) {
                        load_textdomain('payplus-payment-gateway', $mofile);
                    }
                }

                // Force reload for admin if already loaded by hosted fields
                if (is_admin() && is_textdomain_loaded('payplus-payment-gateway')) {
                    unload_textdomain('payplus-payment-gateway');
                    if (file_exists(WP_LANG_DIR . '/plugins/payplus-payment-gateway-' . $locale . '.mo')) {
                        load_textdomain('payplus-payment-gateway', WP_LANG_DIR . '/plugins/payplus-payment-gateway-' . $locale . '.mo');
                    } else {
                        $mofile = PAYPLUS_PLUGIN_DIR . '/languages/payplus-payment-gateway-' . $locale . '.mo';
                        if (file_exists($mofile)) {
                            load_textdomain('payplus-payment-gateway', $mofile);
                        }
                    }
                }
            }

            /**
             * @return void
             */
            public function init()
            {
                $isPayPlusEnabled = isset($this->payplus_payment_gateway_settings->enabled) && $this->payplus_payment_gateway_settings->enabled === 'yes';
                if (class_exists("WooCommerce")) {
                    $this->_wpnonce = wp_create_nonce('_wp_payplusIpn');
                    require_once PAYPLUS_PLUGIN_DIR . '/includes/class-wc-payplus-statics.php';
                    require_once PAYPLUS_PLUGIN_DIR . '/includes/admin/class-wc-payplus-admin-settings.php';
                    require_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_gateway.php';
                    require_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_subgateways.php';
                    require_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_invoice.php';
                    if ($isPayPlusEnabled) {
                        require_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_express_checkout.php';
                    }
                    require_once PAYPLUS_PLUGIN_DIR . '/includes/class-wc-payplus-payment-tokens.php';
                    require_once PAYPLUS_PLUGIN_DIR . '/includes/class-wc-payplus-order-data.php';
                    require_once PAYPLUS_PLUGIN_DIR . '/includes/class-wc-payplus-hosted-fields.php';
                    require_once PAYPLUS_PLUGIN_DIR . '/includes/admin/class-wc-payplus-admin.php';

                    if (is_array($this->hostedFieldsOptions) && boolval($this->hostedFieldsOptions['enabled'] === "yes")) {
                        require_once PAYPLUS_PLUGIN_DIR . '/includes/class-wc-payplus-embedded.php';
                        // Initialize the embedded order processing class
                        new WC_PayPlus_Embedded();
                    }

                    add_action('woocommerce_blocks_loaded', [$this, 'woocommerce_payplus_woocommerce_block_support']);
                    // Register checkout fields on init (priority 20) after translations are loaded
                    // woocommerce_register_additional_checkout_field will automatically handle woocommerce_blocks_loaded timing
                    add_action('init', [$this, 'register_customer_invoice_name_blocks_field'], 20);
                    add_action('init', [$this, 'register_customer_other_id_blocks_field'], 20);
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress filter
                    if (in_array('elementor/elementor.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                        add_action('elementor/widgets/register', [$this, 'payplus_register_widgets']);
                    }
                    add_action('woocommerce_after_checkout_validation', [$this, 'payplus_validation_cart_checkout'], 10, 2);
                    add_action('wp_enqueue_scripts', [$this, 'load_checkout_assets']);
                    add_action('woocommerce_api_callback_response', [$this, 'callback_response']);
                    // add_action('payplus_delayed_event', [$this, 'handle_delayed_event']);

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

            public function isHostedInitiated()
            {
                if (!$this->isHostedInitiated) {
                    $this->isHostedInitiated = true;
                    new WC_PayPlus_HostedFields;
                }
            }

            /**
             * @return void
             */
            public function load_checkout_assets()
            {
                $script_version = filemtime(plugin_dir_path(__FILE__) . 'assets/js/front.min.js');
                $css_script_version = filemtime(plugin_dir_path(__FILE__) . 'assets/css/style.min.css');
                $importAapplepayScript = null;
                $isModbile = (wp_is_mobile()) ? true : false;
                $multipassIcons = WC_PayPlus_Statics::getMultiPassIcons();
                $custom_icons = WC_PayPlus_Statics::getCardsLogos();
                foreach ($custom_icons as $icon) {
                    $customIcons[] = esc_url($icon);
                }
                $isSubscriptionOrder = false;

                if (is_checkout() || is_product()) {
                    if ($this->importApplePayScript && !wp_script_is('applePayScript', 'enqueued') && !$this->is_block_based_checkout()) {
                        wp_register_script('applePayScript', PAYPLUS_PLUGIN_URL . 'assets/js/scriptV2.js', array('jquery'), PAYPLUS_VERSION, true);
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

                    wp_scripts()->registered['wc-checkout']->src = PAYPLUS_PLUGIN_URL . 'assets/js/checkout.min.js?ver=3' . PAYPLUS_VERSION;
                    if ($this->isApplePayGateWayEnabled || $this->isApplePayExpressEnabled) {
                        if (in_array($this->payplus_payment_gateway_settings->display_mode, ['samePageIframe', 'popupIframe', 'iframe'])) {
                            $importAapplepayScript = PAYPLUS_PLUGIN_URL . 'assets/js/scriptV2.js' . '?ver=' . PAYPLUS_VERSION;
                        }
                    }
                    $this->payplus_gateway = $this->get_main_payplus_gateway();
                    wp_localize_script(
                        'wc-checkout',
                        'payplus_script_checkout',
                        [
                            "payplus_import_applepay_script" => $importAapplepayScript,
                            "payplus_mobile" => $isModbile,
                            'ajax_url' => admin_url('admin-ajax.php'),
                            "multiPassIcons" => $multipassIcons,
                            "customIcons" => isset($customIcons) ? $customIcons : [],
                            "isLoggedIn" => boolval(get_current_user_id() > 0),
                            'frontNonce' => wp_create_nonce('frontNonce'),
                            "isSubscriptionOrder" => $isSubscriptionOrder,
                            "iframeAutoHeight" => $this->iframeAutoHeight,
                            "popupTvEffect" => $this->popupTvEffect,
                            "viewMode" => $this->payplus_payment_gateway_settings->display_mode ?? 'redirect',
                            "iframeWidth" => $this->payplus_payment_gateway_settings->iframe_width ?? '40%',
                            "hasSavedTokens" => WC_Payment_Tokens::get_customer_tokens(get_current_user_id()),
                            "isHostedFields" => isset($this->hostedFieldsOptions['enabled']) ? boolval($this->hostedFieldsOptions['enabled'] === "yes") : false,
                            "hostedFieldsWidth" => isset($this->hostedFieldsOptions['hosted_fields_width']) ? $this->hostedFieldsOptions['hosted_fields_width'] : 100,
                            "hidePPGateway" => isset($this->hostedFieldsOptions['hide_payplus_gateway']) ? boolval($this->hostedFieldsOptions['hide_payplus_gateway'] === "yes") : false,
                            "hidePayPlusGatewayNMW" => $this->hidePayPlusGatewayNMW,
                            "hostedFieldsIsMain" => (isset($this->hostedFieldsOptions['enabled']) && $this->hostedFieldsOptions['enabled'] === "yes" && isset($this->hostedFieldsOptions['hosted_fields_is_main']) && $this->hostedFieldsOptions['hosted_fields_is_main'] === "yes"),
                            "saveCreditCard" => __("Save credit card in my account", "payplus-payment-gateway"),
                            "isSavingCerditCards" => boolval(property_exists($this->payplus_payment_gateway_settings, 'create_pp_token') && $this->payplus_payment_gateway_settings->create_pp_token === 'yes'),
                            "enableDoubleCheckIfPruidExists" => isset($this->payplus_gateway) && $this->payplus_gateway->enableDoubleCheckIfPruidExists ? true : false,
                            "hostedPayload" => WC()->session ? WC()->session->get('hostedPayload') : null,
                        ]
                    );
                    if (!is_cart() && !is_product() && !is_shop()) {
                        if (boolval($this->hostedFieldsOptions['enabled'] === "yes") && !$isSubscriptionOrder) {
                            $this->isHostedInitiated();
                        }
                        if (boolval($this->hostedFieldsOptions['enabled'] === "yes") && $isSubscriptionOrder && get_current_user_id() !== 0) {
                            $this->isHostedInitiated();
                        }
                    }
                }

                $this->is_block_based_checkout() && boolval($this->hostedFieldsOptions['enabled'] === "yes") && !$isSubscriptionOrder ? $this->isHostedInitiated() : null;
                $this->is_block_based_checkout() && boolval($this->hostedFieldsOptions['enabled'] === "yes") && $isSubscriptionOrder && get_current_user_id() !== 0 ? $this->isHostedInitiated() : null;

                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress filter
                $isElementor = in_array('elementor/elementor.php', apply_filters('active_plugins', get_option('active_plugins')));
                $isEnableOneClick = (isset($this->payplus_payment_gateway_settings->enable_google_pay) && $this->payplus_payment_gateway_settings->enable_google_pay === "yes") ||
                    (isset($this->payplus_payment_gateway_settings->enable_apple_pay) && $this->payplus_payment_gateway_settings->enable_apple_pay === "yes");
                if (is_checkout() || is_product() || is_cart() || $isElementor) {
                    if (
                        $this->payplus_payment_gateway_settings->enable_design_checkout === "yes" || $isEnableOneClick

                    ) {
                        $this->payplus_gateway = $this->get_main_payplus_gateway();
                        add_filter('body_class', [$this, 'payplus_body_classes']);
                        wp_enqueue_style('payplus-css', PAYPLUS_PLUGIN_URL . 'assets/css/style.min.css', [], $css_script_version);

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
                                    'isShippingWooJs' => $this->shipping_woo_js,
                                    'requirePhoneText' => __('Phone number is required.', 'payplus-payment-gateway'),
                                    'successPhoneText' => __('Click again to continue!', 'payplus-payment-gateway'),
                                ]
                            );
                            wp_enqueue_script('payplus-front-js');
                        }
                    }
                }

                wp_enqueue_style('alertifycss', PAYPLUS_PLUGIN_URL . 'assets/css/alertify.min.css', array(), '1.14.0', 'all');
                wp_register_script('alertifyjs', PAYPLUS_PLUGIN_URL . 'assets/js/alertify.min.js', array('jquery'), '1.14.0', true);
                wp_enqueue_script('alertifyjs');
            }

            public function is_block_based_checkout()
            {
                // Get the WooCommerce Checkout page ID
                $page_id = get_the_ID();
                // Check if we're currently on the checkout page
                if (is_page($page_id)) {
                    // Get the content of the checkout page
                    $post = get_post($page_id);

                    // Check if the 'woocommerce/checkout' block is present in the page content
                    if (has_block('woocommerce/checkout', $post->post_content)) {
                        // Block-based checkout is active
                        return true;
                    }
                }

                // Return false if not a block-based checkout
                return false;
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
                isset($this->payplus_payment_gateway_settings->iframe_auto_height) && $this->payplus_payment_gateway_settings->iframe_auto_height === "yes" ? $iframeAutoHeight = "max-height: 100vh;height: 90%;" : $iframeAutoHeight = "";
                ob_start();
                    ?>
        <div class="payplus-option-description-area"></div>
        <div class="pp_iframe" data-height="<?php echo esc_attr($height); ?>"
            style="<?php echo esc_attr($iframeAutoHeight); ?>"></div>
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
                echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

                echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
                $json = file_get_contents('php://input');
                $response = json_decode($json, true);
                $payplusHash = isset($_SERVER['HTTP_HASH']) ? sanitize_text_field($_SERVER['HTTP_HASH']) : ""; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
                $payplusGenHash = base64_encode(hash_hmac('sha256', $json, $this->secret_key, true));

                if ($payplusGenHash === $payplusHash) {
                    $this->payplus_gateway = $this->get_main_payplus_gateway();
                    $order_id = intval($response['transaction']['more_info']);
                    if ($order_id === 0) {
                        // Log an error or handle the case where order_id is invalid
                        $this->payplus_gateway->payplus_add_log_all(
                            'payplus_callback_secured',
                            "\nPayPlus order # $order_id INVALID ORDER ID - ABORTING CALLBACK FUNCTION:"
                        );
                        wp_die(
                            wp_json_encode(array(
                                'status' => 'error',
                                'message' => 'Invalid order ID.',
                            )),
                            '',
                            array(
                                'response' => 200, // Bad Request
                                'content_type' => 'application/json'
                            )
                        );
                        return;
                    }
                    $order = wc_get_order($order_id);
                    WC_PayPlus_Meta_Data::update_meta($order, ['payplus_callback_response' => $json]);
                    // Add a delayed event
                    $this->payplus_gateway->payplus_add_log_all(
                        'payplus_callback_secured',
                        "\nPayPlus order # $order_id STARTING CALLBACK FUNCTION:"
                    );
                    $this->payplus_gateway->legacy_callback_response($order_id, $json);
                    $response = array(
                        'status' => 'success',
                        'message' => 'PayPlus callback function ended.',
                    );

                    wp_die(
                        wp_json_encode($response),
                        '',
                        array(
                            'response' => 200,
                            'content_type' => 'application/json'
                        )
                    );
                }
            }

            /**
             * Processes the delayed event for an order.
             *
             * @param int $order_id The order ID to process.
             */
            public function handle_delayed_event($order_id)
            {
                $order = wc_get_order($order_id);

                if ($order) {
                    // Perform delayed processing logic here
                    $datetime = current_datetime();
                    $LocalTime = $datetime->format('Y-m-d H:i:s');
                    $order->add_order_note('PayPlus secure callback event initiated - ' . $LocalTime);
                    $this->payplus_gateway = $this->get_main_payplus_gateway();
                    $this->payplus_gateway->legacy_callback_response($order_id);
                    $this->payplus_gateway->payplus_add_log_all(
                        'payplus_callback_secured',
                        "PayPlus order # $order_id callback function ended."
                    );
                    // Add further actions like updating order status, sending notifications, etc.
                }
            }

            /**
             * Schedules a delayed event for the order processing.
             *
             * @param int $order_id The order ID to process.
             */
            private function schedule_delayed_event($order_id)
            {
                if (!wp_next_scheduled('payplus_delayed_event', [$order_id])) {
                    wp_schedule_single_event(time() + 5, 'payplus_delayed_event', [$order_id]); // 5 seconds delay
                }
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
                $methods[] = 'WC_PayPlus_Gateway_POS_EMV';
                $methods[] = 'WC_PayPlus_Gateway_WireTransfer';
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
                $transient_key = 'payplus_check_exists_table_' . $table;
                $flag = get_transient($transient_key);
                if ($flag === false) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . $table;
                    $like_table_name = '%' . $wpdb->esc_like($table_name) . '%';
                    $flag = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like_table_name)) != $table_name) ? true : false;
                    set_transient($transient_key, $flag, HOUR_IN_SECONDS);
                }
                return $flag;
            }
            public static function payplus_get_admin_menu($nonce)
            {
                ob_start();
                $currentSection = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : ""; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
                            $payment_method_registry->register(new WC_PayPlus_Gateway_POS_EMV_Block());
                            $payment_method_registry->register(new WC_Gateway_Payplus_WireTransfer_Block());
                            $payment_method_registry->register(new WC_Gateway_Payplus_Paypal_Block());
                        }
                    );
                }
            }

            /**
             * Register customer invoice name field for WooCommerce Blocks checkout
             */
            public function register_customer_invoice_name_blocks_field()
            {
                $payplus_settings = get_option('woocommerce_payplus-payment-gateway_settings');
                $enable_customer_invoice_name   = isset($payplus_settings['enable_customer_invoice_name']) && $payplus_settings['enable_customer_invoice_name'] === 'yes';
                $customer_invoice_name_required = $enable_customer_invoice_name && isset($payplus_settings['customer_invoice_name_required']) && $payplus_settings['customer_invoice_name_required'] === 'yes';
                $customer_invoice_name_label    = $enable_customer_invoice_name && !empty($payplus_settings['customer_invoice_name_label']) ? trim($payplus_settings['customer_invoice_name_label']) : '';

                if (!$enable_customer_invoice_name) {
                    return;
                }

                // Get current language
                $current_locale = get_locale();
                $is_hebrew = (strpos($current_locale, 'he') === 0 || strpos($current_locale, 'iw') === 0);

                // Determine label: admin-defined text takes priority over language defaults.
                $field_label = $customer_invoice_name_label !== ''
                    ? $customer_invoice_name_label
                    : ($is_hebrew ? __('שם על החשבונית', 'payplus-payment-gateway') : __('Name on invoice', 'payplus-payment-gateway'));

                // Register the field for WooCommerce Blocks
                // Note: woocommerce_register_additional_checkout_field automatically handles woocommerce_blocks_loaded timing
                // If woocommerce_blocks_loaded hasn't fired yet, it will re-hook itself to that hook
                if (function_exists('woocommerce_register_additional_checkout_field')) {
                    try {
                        woocommerce_register_additional_checkout_field([
                            'id'       => 'payplus/customer-invoice-name',
                            'label'    => $field_label,
                            'location' => 'contact',
                            'type'     => 'text',
                            'required' => $customer_invoice_name_required,
                        ]);
                    } catch (Exception $e) {
                        // Log error if field registration fails
                        if (WP_DEBUG_LOG) {
                            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only logs when WP_DEBUG_LOG is enabled
                            error_log('PayPlus: Failed to register customer invoice name field for blocks: ' . $e->getMessage());
                        }
                    }
                }
            }

            /**
             * Register customer other ID field for WooCommerce Blocks checkout
             */
            public function register_customer_other_id_blocks_field()
            {
                $payplus_settings = get_option('woocommerce_payplus-payment-gateway_settings');
                $enable_customer_other_id = isset($payplus_settings['enable_customer_other_id']) && $payplus_settings['enable_customer_other_id'] === 'yes';

                if (!$enable_customer_other_id) {
                    return;
                }

                // Get current language
                $current_locale = get_locale();
                $is_hebrew = (strpos($current_locale, 'he') === 0 || strpos($current_locale, 'iw') === 0);

                // Register the field for WooCommerce Blocks
                // Note: woocommerce_register_additional_checkout_field automatically handles woocommerce_blocks_loaded timing
                // If woocommerce_blocks_loaded hasn't fired yet, it will re-hook itself to that hook
                if (function_exists('woocommerce_register_additional_checkout_field')) {
                    try {
                        woocommerce_register_additional_checkout_field([
                            'id' => 'payplus/customer-other-id',
                            'label' => $is_hebrew ? __('מספר זהות אחר לחשבונית', 'payplus-payment-gateway') : __('Other ID for invoice', 'payplus-payment-gateway'),
                            'location' => 'contact',
                            'type' => 'text',
                            'required' => false,
                        ]);
                    } catch (Exception $e) {
                        // Log error if field registration fails
                        if (WP_DEBUG_LOG) {
                            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only logs when WP_DEBUG_LOG is enabled
                            error_log('PayPlus: Failed to register customer other ID field for blocks: ' . $e->getMessage());
                        }
                    }
                }
            }
        }

        WC_PayPlus::get_instance();

        require_once PAYPLUS_PLUGIN_DIR . '/includes/wc-payplus-activation-functions.php';

        register_activation_hook(__FILE__, 'payplus_create_table_order');
        register_activation_hook(__FILE__, 'payplus_create_table_change_status_order');
        register_activation_hook(__FILE__, 'payplus_create_table_process');
        register_activation_hook(__FILE__, 'payplus_check_set_payplus_options');
        register_activation_hook(__FILE__, 'payplusGenerateErrorPage');
        register_activation_hook(__FILE__, 'payplus_display_hash_check_notice');
        register_deactivation_hook(__FILE__, 'payplus_cron_deactivate');
