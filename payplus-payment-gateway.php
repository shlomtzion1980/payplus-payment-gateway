<?php

/**
 * Plugin Name: PayPlus Payment Gateway
 * Description: Accept credit/debit card payments or other methods such as bit, Apple Pay, Google Pay in one page. Create digitally signed invoices & much more.
 * Plugin URI: https://www.payplus.co.il/wordpress
 * Version: 7.0.8
 * Tested up to: 6.5.3
 * Requires Plugins: woocommerce
 * Requires at least: 6.2
 * Author: PayPlus LTD
 * Author URI: https://www.payplus.co.il/
 * License: GPLv2 or later
 * Text Domain: payplus-payment-gateway
 */

defined('ABSPATH') or die('Hey, You can\'t access this file!'); // Exit if accessed directly
define('PAYPLUS_PLUGIN_URL', plugins_url('/', __FILE__));
define('PAYPLUS_PLUGIN_URL_ASSETS_IMAGES', PAYPLUS_PLUGIN_URL . "assets/images/");
define('PAYPLUS_PLUGIN_DIR', dirname(__FILE__));
define('PAYPLUS_VERSION', '7.0.8');
define('PAYPLUS_VERSION_DB', 'payplus_2_6');
define('PAYPLUS_TABLE_PROCESS', 'payplus_payment_process');
define('PAYPLUS_TABLE_SESSION', 'payplus_payment_session');
class WC_PayPlus
{
    protected static $instance = null;
    public $notices = [];
    private $payplus_payment_gateway_settings;
    public $invoice_api = null;

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
        add_action('admin_init', [$this, 'check_environment']);
        add_action('admin_notices', [$this, 'admin_notices'], 15);
        add_action('plugins_loaded', [$this, 'init']);
        add_action('manage_product_posts_custom_column', [$this, 'payplus_custom_column_product'], 10, 2);
        add_action('woocommerce_email_before_order_table', [$this, 'payplus_add_content_specific_email'], 20, 4);
        add_action('wp_head', [$this, 'payplus_no_index_page_error']);
        add_action('woocommerce_api_payplus_gateway', [$this, 'ipn_response']);
        //end custom hook

        add_action('woocommerce_before_checkout_form', [$this, 'msg_checkout_code']);

        //FILTER
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
        add_filter('woocommerce_available_payment_gateways', [$this, 'payplus_applepay_disable_manager']);
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
        if (!wp_verify_nonce(sanitize_key($_REQUEST['_wpnonce']), 'payload_link')) {
            wp_die('Not allowed!');
        }
        global $wpdb;
        $this->payplus_gateway = $this->get_main_payplus_gateway();
        $REQUEST = $this->payplus_gateway->arr_clean($_REQUEST);
        $tblname = $wpdb->prefix . 'payplus_payment_process';
        $indexRow = 0;
        if (!empty($REQUEST['more_info'])) {
            $status_code = isset($_REQUEST['status_code']) ? sanitize_text_field($_REQUEST['status_code']) : '';
            $order_id = isset($_REQUEST['more_info']) ? sanitize_text_field($_REQUEST['more_info']) : '';
            $result = $wpdb->get_results($wpdb->prepare(
                'SELECT id as rowId, count(*) as rowCount, count_process FROM %s WHERE order_id = %d AND ( status_code = %d )',
                $tblname,
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
                    'transaction_uid' => isset($_REQUEST['transaction_uid']) ? sanitize_text_field($_REQUEST['transaction_uid']) : null,
                    'page_request_uid' => isset($_REQUEST['page_request_uid']) ? sanitize_text_field($_REQUEST['page_request_uid']) : null,
                    'voucher_id' => isset($_REQUEST['voucher_num']) ? sanitize_text_field($_REQUEST['voucher_num']) : null,
                    'token_uid' => isset($_REQUEST['token_uid']) ? sanitize_text_field($_REQUEST['token_uid']) : null,
                    'type' => isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : null,
                    'order_id' => isset($_REQUEST['more_info']) ? sanitize_text_field($_REQUEST['more_info']) : null,
                    'status_code' => isset($_REQUEST['status_code']) ? intval($_REQUEST['status_code']) : null,
                    'number' => isset($_REQUEST['number']) ? sanitize_text_field($_REQUEST['number']) : null,
                    'expiry_year' => isset($_REQUEST['expiry_year']) ? sanitize_text_field($_REQUEST['expiry_year']) : null,
                    'expiry_month' => isset($_REQUEST['expiry_month']) ? sanitize_text_field($_REQUEST['expiry_month']) : null,
                    'four_digits' => isset($_REQUEST['four_digits']) ? sanitize_text_field($_REQUEST['four_digits']) : null,
                    'brand_id' => isset($_REQUEST['brand_id']) ? sanitize_text_field($_REQUEST['brand_id']) : null,
                ];

                $order = $this->payplus_gateway->validateOrder($data);

                $linkRedirect = esc_url($this->payplus_gateway->get_return_url($order));

                if (isset($REQUEST['paymentPayPlusDashboard']) && !empty($REQUEST['paymentPayPlusDashboard'])) {
                    $order_id = $REQUEST['more_info'];
                    $order = wc_get_order($order_id);
                    $paymentPayPlusDashboard = $REQUEST['paymentPayPlusDashboard'];
                    if ($paymentPayPlusDashboard === $this->payplus_gateway->payplus_generate_key_dashboard) {
                        $order->set_payment_method('payplus-payment-gateway');
                        $order->set_payment_method_title('Pay with Debit or Credit Card');
                        $linkRedirect = esc_url(get_admin_url() . "post.php?post=" . $order_id . "&action=edit");
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
                $linkRedirect = esc_url($this->payplus_gateway->get_return_url($order));
                WC()->session->__unset('save_payment_method');
                wp_redirect($linkRedirect);
            }
        } elseif (isset($_GET['success_order_id']) && isset($_GET['charge_method']) && $_GET['charge_method'] === 'bit') {
            $order_id = isset($_GET['success_order_id']) ? intval($_GET['success_order_id']) : 0;
            $order = wc_get_order($order_id);
            if ($order) {
                $linkRedirect = esc_url($this->payplus_gateway->get_return_url($order));
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
        $required_php_version = '7.2';
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
        load_plugin_textdomain('payplus-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');
        if (class_exists("WooCommerce")) {

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
            add_action('woocommerce_order_item_add_action_buttons', [$this->invoice_api, 'payplus_order_item_add_action_buttons_callback'], 100, 1);

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
            payplus_add_file_ApplePay();
        }
    }

    /**
     * @return void
     */
    public function load_checkout_assets()
    {

        $importAapplepayScript = null;
        $isModbile = (wp_is_mobile()) ? true : false;
        if (is_checkout()) {
            wp_scripts()->registered['wc-checkout']->src = PAYPLUS_PLUGIN_URL . 'assets/js/checkout.min.js';
            if (
                property_exists($this->payplus_payment_gateway_settings, 'import_applepay_script') &&
                $this->payplus_payment_gateway_settings->import_applepay_script === "yes"
                && in_array($this->payplus_payment_gateway_settings->display_mode, ['samePageIframe', 'popupIframe'])
            ) {
                $importAapplepayScript = 'https://payments.payplus.co.il/statics/applePay/script.js?var=' . PAYPLUS_VERSION;
            }
            wp_localize_script(
                'wc-checkout',
                'payplus_script_checkout',
                array("payplus_import_applepay_script" => $importAapplepayScript, "payplus_mobile" => $isModbile)
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
                wp_enqueue_style('payplus-css', PAYPLUS_PLUGIN_URL . 'assets/css/style.min.css', [], PAYPLUS_VERSION);

                if ($isEnableOneClick) {
                    $payment_url_google_pay_iframe = $this->payplus_gateway->payplus_iframe_google_pay_oneclick;
                    wp_register_script('payplus-front-js', PAYPLUS_PLUGIN_URL . 'assets/js/front.js', [], PAYPLUS_VERSION, true);
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
        }

        wp_enqueue_style('alertifycss', '//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css', array(), false, 'all');
        wp_register_script('alertifyjs', '//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js', array('jquery'), false, true);
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
            isset($available_gateways['payplus-payment-gateway-applepay']) && !is_admin() &&
            !preg_match('/Mac|iPad|iPod|iPhone/', $_SERVER['HTTP_USER_AGENT'])
        ) {
            unset($available_gateways['payplus-payment-gateway-applepay']);
        }
        if (!is_admin() && $currency != 'ils') {
            $arrPayment = array(
                'payplus-payment-gateway', 'payplus-payment-gateway-bit', 'payplus-payment-gateway-googlepay',
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
        if (!wp_verify_nonce($_POST['payplus_notice_proudct_nonce'], 'payplus_notice_proudct_nonce')) {
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

            $transaction_type = sanitize_text_field($_POST['payplus_transaction_type']);
            update_post_meta($post_id, 'payplus_transaction_type', $transaction_type);
        }
        if (isset($_POST['payplus_balance_name'])) {

            $payplus_balance_name = sanitize_text_field($_POST['payplus_balance_name']);
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
        if ($this->payplus_gateway->payplus_check_blocked_ip()) {
            $errors->add(
                'error',
                __('Something went wrong with the payment page - This Ip is blocked', 'payplus-payment-gateway')
            );
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
    public static function payplus_check_exists_table($table = 'payplus_order')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        $like_table_name = '%' . $wpdb->esc_like($table_name) . '%';
        $flag = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like_table_name)) != $table_name) ? true : false;
        return $flag;
    }
    public static function payplus_get_admin_menu($nonce)
    {
        if (!wp_verify_nonce(sanitize_key($nonce), 'menu_option')) {
            wp_die('Not allowed!');
        }
        ob_start();
        $currentSection = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : "";
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
// register_activation_hook(__FILE__, 'payplus_create_table_log');
register_activation_hook(__FILE__, 'payplus_create_table_payment_session');
register_activation_hook(__FILE__, 'payplus_create_table_process');
register_activation_hook(__FILE__, 'checkSetPayPlusOptions');
register_activation_hook(__FILE__, 'payplusGenerateErrorPage');