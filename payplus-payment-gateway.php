<?php
/*
Plugin Name: PayPlus Payment Gateway
Description: WooCommerce integration for PayPlus Payment Gateway. Accept credit cards and more alternative methods directly to your WordPress e-commerce websites, More options to Refund, Charge, Capture, Subscriptions, Tokens and much more!
Plugin URI: https://www.payplus.co.il/wordpress
Version:6.6.3newSaveCC
Tested up to:6.5.2
Author: PayPlus LTD
Author URI: https://www.payplus.co.il/
License: GPLv2 or later
Text Domain: PayPlus Payment Gateway Plugin
 */

defined('ABSPATH') or die('Hey, You can\'t access this file!'); // Exit if accessed directly
define('PAYPLUS_PLUGIN_URL', plugins_url('/', __FILE__));
define('PAYPLUS_PLUGIN_URL_ASSETS_IMAGES', PAYPLUS_PLUGIN_URL . "assets/images/");
define('PAYPLUS_PLUGIN_DIR', dirname(__FILE__));
define('PAYPLUS_VERSION', '6.6.3');
define('PAYPLUS_VERSION_DB', 'payplus_2_0');
define('PAYPLUS_TABLE_PROCESS', 'payplus_payment_process');
define('PAYPLUS_TABLE_SESSION', 'payplus_payment_session');
class WC_PayPlus
{
    protected static $instance = null;
    public $notices = [];
    private $payplus_payment_gateway_settings = null;
    public $invocie_api = null;

    /**
     *
     */
    private function __construct()
    {
        //ACTION
        add_action('admin_init', [$this, 'check_environment']);
        add_action('admin_notices', [$this, 'admin_notices'], 15);
        add_action('plugins_loaded', [$this, 'init']);
        add_action('manage_product_posts_custom_column', [$this, 'payplus_custom_column_product'], 10, 2);
        add_action('woocommerce_email_before_order_table', [$this, 'payplus_add_content_specific_email'], 20, 4);
        add_action('wp_head', [$this, 'payplus_no_index_page_error']);
        //this is a custom callback hook that runs when the wc-api=payplus_gateway is returned in the url!
        add_action('woocommerce_api_payplus_gateway', [$this, 'ipn_response']);
        //end custom hook

        add_action('woocommerce_before_checkout_form', [$this, 'msg_checkout_code']);
        add_action('woocommerce_order_status_changed', [$this, 'payplus_order_status_changed_action'], 50, 4);
        add_action('woocommerce_thankyou', [$this, 'thankyou_check_save_tokens'], 10, 1);
        add_action('wp_ajax_payplus-check-tokens', [$this, 'ajax_payplus_check_tokens']);
        add_action('wp_ajax_payplus-delete-token', [$this, 'ajax_payplus_delete_token']);

        //FILTER
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
        add_filter('woocommerce_available_payment_gateways', [$this, 'payplus_applepay_disable_manager']);

        //SHORTCODE
        add_shortcode('error-payplus-content', [$this, 'payplus_text_error_page']);
    }

    public function ajax_payplus_check_tokens()
    {

        $customerTokens = WC_Payment_Tokens::get_customer_tokens($_POST['userId']);
        $theTokens = [];

        foreach ($customerTokens as $customerToken) {
            $theTokens[] = $customerToken->get_token();
        };
        print_r($theTokens);
        wp_die();

    }

    /**
     * @param int $order_id
     * @param int $meta_key
     * @return void
     */
    public function removeOrderMetaField($order_id, $meta_key)
    {
        global $wpdb;
        $tblname = $wpdb->prefix . 'wc_orders_meta';
        //$meta_key = 'payplus_token_uid';

        // Prepare the SQL query to delete the meta field
        $sql = $wpdb->prepare(
            "
    DELETE FROM $tblname
    WHERE order_id = %d
    AND meta_key = %s
    ",
            $order_id,
            $meta_key
        );
        // Execute the query
        $result = $wpdb->query($sql);

        if (false === $result) {
            // There was an error executing the query
            echo "Error deleting meta field.";
        } else {
            // Meta field deleted successfully
            echo "Meta field deleted successfully.";
        }
    }

    public function ajax_payplus_delete_token()
    {
        echo 'delete token!';
        print_r($_POST);
        $order_id = $_POST['orderId'];
        delete_post_meta($order_id, 'payplus_token_uid');
        $this->removeOrderMetaField($order_id, 'payplus_token_uid');
        wp_die();
    }

    public function thankyou_check_save_tokens($order_id)
    {
        if ($this->payplus_payment_gateway_settings->save_pp_token_receipt_page === 'yes') {
            // Get the order object
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();
            $order_meta = get_post_meta($order_id);

            $data = json_decode($order_meta['payplus_response'][0], true);
            $customerTokens = WC_Payment_Tokens::get_customer_tokens($user_id);
            $theTokens = [];

            foreach ($customerTokens as $customerToken) {
                $theTokens[] = $customerToken->get_token();
            };
            // call thankyou.js and register the script
            wp_register_script('thankyou-js', PAYPLUS_PLUGIN_URL . '/assets/js/thankyou.js', ['jquery'], time(), true);
            wp_localize_script(
                'thankyou-js',
                'payplus_script_thankyou',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'userId' => $user_id,
                    'orderId' => $order_id,
                    'token' => $order_meta['payplus_token_uid'][0],
                )
            );
            wp_enqueue_script('thankyou-js');
            // checking the $order_meta array for the payplus_token_uid key - if it's not there, then we know it's a new card
            if (!in_array($order_meta['payplus_token_uid'][0], $theTokens) && $order_meta['payplus_token_uid'][0] != null) {
                ?>
                <div id="newToken"
                    style="background-color: white; min-height: 20%; display: flex; border: solid 0.7px; border-radius: 30px; padding: 30px 30px 30px 30px; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); flex-direction: column;">
                    <div class="payplus_save_token_messsage">
                        <?php echo __('This is a new credit card, would you like to save it securely to your account for future purchases?'); ?>
                    </div>
                    <form action="" method="post">
                        <input type="hidden" name="token" value="<?php echo $order_meta['payplus_token_uid'][0]; ?>">
                        <input type="hidden" id="user_id" value="<?php echo $user_id; ?>">
                        <input type="hidden" id="order_id" value="<?php echo $order_id; ?>">
                        <input type="submit" name="saveToken" value="<?php echo __('Yes'); ?>">
                        <input type="submit" name="deleteToken" value="<?php echo __('No'); ?>">
                        <div class='payplus_loader'></div>
                    </form>
                </div>
<?php
}
            if (isset($_POST['saveToken'])) {
                $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
                $WC_PayPlus_Gateway->save_token($data, $user_id);
                delete_post_meta($order_id, 'payplus_token_uid');
                $this->removeOrderMetaField($order_id, 'payplus_token_uid');
            }
        }

    }

    /**
     * @return void
     */
    public function msg_checkout_code()
    {
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $woocommerce_price_num_decimal = get_option('woocommerce_price_num_decimals');
        if ($WC_PayPlus_Gateway->api_test_mode) {
            echo '<div
    style="background: #d23d3d; border-right: 8px #b33434 solid; border-radius: 4px; color: #FFF; padding: 5px;margin: 5px 0px">
    ' . __('Sandbox mode is active and real transaction cannot be processed. Please make sure to move production when
    finishing testing', 'payplus-payment-gateway') . '</div>';
        }

        if ($woocommerce_price_num_decimal > 2 || $woocommerce_price_num_decimal == 1 || $woocommerce_price_num_decimal < 0) {
            echo '<div style="background: #d23d3d; border-right: 8px #b33434 solid; border-radius: 4px; color: #FFF; padding: 5px;margin: 5px 0px">'
            . __('Please change the "Number of decimal digits" to 2 or 0 in your WooCommerce settings>General>Currency
    settings', 'payplus-payment-gateway') . '</div>';
        }
    }

    /**
     * @return void
     */
    public function ipn_response()
    {
        global $wpdb;
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $REQUEST = $WC_PayPlus_Gateway->arr_clean($_REQUEST);
        $tblname = $wpdb->prefix . 'payplus_payment_process';
        $indexRow = 0;
        if (!empty($REQUEST['more_info'])) {
            $status_code = $REQUEST['status_code'];
            $order_id = $REQUEST['more_info'];
            $sql = 'SELECT id as rowId,count(*) as rowCount ,count_process FROM ' . $tblname . ' WHERE order_id=' . $order_id .
                ' AND ( status_code="' . $status_code . '")';
            $result = $wpdb->get_results($sql);
            $result = $result[$indexRow];
            if (!$result->rowCount) {

                $wpdb->insert($tblname, array(
                    'order_id' => $order_id,
                    'function_begin' => 'ipn_response',
                    'status_code' => $status_code,
                    'count_process' => 1,
                ));
                if ($wpdb->last_error) {
                    payplus_Add_log_payplus($wpdb->last_error);
                }
                $data = [
                    'transaction_uid' => $REQUEST['transaction_uid'] ?? null,
                    'page_request_uid' => $REQUEST['page_request_uid'] ?? null,
                    'voucher_id' => $REQUEST['voucher_num'] ?? null,
                    'token_uid' => $REQUEST['token_uid'] ?? null,
                    'type' => $REQUEST['type'] ?? null,
                    'order_id' => $REQUEST['more_info'] ?? null,
                    'status_code' => $REQUEST['status_code'] ?? null,
                    'number' => $REQUEST['number'] ?? null,
                    'expiry_year' => $REQUEST['expiry_year'] ?? null,
                    'expiry_month' => $REQUEST['expiry_month'] ?? null,
                    'four_digits' => $REQUEST['four_digits'] ?? null,
                    'brand_id' => $REQUEST['brand_id'] ?? null,
                ];

                $order = $WC_PayPlus_Gateway->validateOrder($data);

                $linkRedirect = $WC_PayPlus_Gateway->get_return_url($order);

                if (!empty($REQUEST['paymentPayPlusDashboard'])) {
                    $order_id = $REQUEST['more_info'];
                    $order = wc_get_order($order_id);
                    $paymentPayPlusDashboard = $REQUEST['paymentPayPlusDashboard'];
                    if ($paymentPayPlusDashboard === $WC_PayPlus_Gateway->payplus_generate_key_dashboard) {
                        $insetData['_payment_method'] = "payplus-payment-gateway";
                        $insetData['_payment_method_title'] = "Pay with Debit or Credit Card";
                        payplus_update_post_meta_object($order, $insetData);
                        $linkRedirect = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
                    }
                }
                WC()->session->__unset('save_payment_method');
                wp_redirect($linkRedirect);
            } else {
                $wpdb->update($tblname, array(
                    'count_process' => $result->count_process + 1,
                ), array('id' => $result->rowId));
                if ($wpdb->last_error) {
                    payplus_Add_log_payplus($wpdb->last_error);
                }
                $order = wc_get_order($order_id);
                $linkRedirect = $WC_PayPlus_Gateway->get_return_url($order);
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
        $invocie_api = new PayplusInvoice();
        $payment_method = get_post_meta($order_id, '_payment_method', true);
        if (strpos($payment_method, 'payplus') === false) {
            $amount = get_post_meta($refund_id, '_refund_amount', true);
            if (floatval($amount)) {
                $invocie_api->payplus_create_dcoment_dashboard(
                    $order_id,
                    $invocie_api->payplus_get_invoice_type_document_refund(),
                    array(),
                    $amount,
                    'payplus_order_refund' . $order_id
                );
            }
        }
    }

    /**
     * @param WP_Admin_Bar $admin_bar
     * @return void
     */
    public function payplus_add_toolbar_items($admin_bar)
    {

        $admin_bar->add_menu(array(
            'id' => 'PayPlus-toolbar',
            'title' => __('PayPlus  Gateway', 'payplus-payment-gateway'),
            'href' => get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway",
            'meta' => array(
                'title' => __('PayPlus  Gateway', 'payplus-payment-gateway'),
                'target' => '_blank',
            ),
        ));
        $admin_bar->add_menu(array(
            'id' => 'payPlus-toolbar-sub',
            'parent' => 'PayPlus-toolbar',
            'title' => __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
            'href' => get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=payplus-invoice",
            'meta' => array(
                'title' => __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
                'target' => '_blank',
                'class' => 'my_menu_item_class',
            ),
        ));
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
            $payplusFourDigits = get_post_meta($order->get_id(), "payplus_four_digits", true);
            if ($payplusFourDigits) {
                $payplusFourDigits = __("Four last digits", "payplus-payment-gateway") . " : " . $payplusFourDigits;
                echo '<p class="email-upsell-p">' . $payplusFourDigits . '</p>';
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
            $payplusTransactionType = get_post_meta($post_id, 'payplus_transaction_type', true);
            if (!empty($payplusTransactionType)) {
                echo '<p>' . $transactionTypes[$payplusTransactionType] . "</p>";
            }
        }
    }

    /**
     * @return void
     */
    public function payplus_get_gateway()
    {
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway'));
        exit;
    }

    /**
     * @return void
     */
    public function payplus_add_admin_page_menu()
    {
        global $submenu;
        $parent_slug = 'payplus-payment-gateway';

        add_menu_page(
            __('PayPlus  Gateway', 'payplus-payment-gateway'),
            __('PayPlus  Gateway', 'payplus-payment-gateway'),
            "administrator",
            'payplus-payment-gateway',
            [$this, 'payplus_get_gateway'],
            PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "payplus-icon.svg"
        );

        add_submenu_page(
            'payplus-payment-gateway',
            __('bit', 'payplus-payment-gateway'),
            __('bit', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-bit'

        );
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('Google Pay', 'payplus-payment-gateway'),
            __('Google Pay', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-googlepay' //Page slug

        );
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('Apple Pay', 'payplus-payment-gateway'),
            __('Apple Pay', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-applepay' //Page slug

        );

        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('MULTIPASS', 'payplus-payment-gateway'),
            __('MULTIPASS', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-multipass' //Page slug

        );
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('PayPal', 'payplus-payment-gateway'),
            __('PayPal', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-paypal' //Page slug

        );
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('Tav zahav', 'payplus-payment-gateway'),
            __('Tav Zahav', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-tavzahav' //Page slug

        );
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
            __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-invoice' //Page slug

        );
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
            $message = sprintf(__('Your server is running PHP version %1$s but some features requires at least %2$s.', 'payplus-payment-gateway'), $php_version, $required_php_version);
            $this->add_admin_notice('warning', $message);
        }

        if ($woocommerce_price_num_decimal > 2 || $woocommerce_price_num_decimal == 1 || $woocommerce_price_num_decimal < 0) {
            $message = '<b>' . __('Please change the "Number of decimal digits" to 2 or 0 in your WooCommerce settings>General>Currency setting', 'payplus-payment-gateway') . '</b>';
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
        $title = __('PayPlus Payment Gateway', 'payplus-payment-gateway');
        if (count($this->notices)) {
            foreach ($this->notices as $notice) {
                $output .= "<div class='$notice[class]'><p><b>$title:</b> $notice[message]</p></div>";
            }
        }
        echo $output;
    }

    /**
     * @param array $links
     * @return array|string[]
     */
    public static function plugin_action_links($links)
    {
        $action_links = [
            'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway') . '" aria-label="' . __('View PayPlus Settings', 'payplus-payment-gateway') . '">' . __('Settings') . '</a>',
        ];
        $links = array_merge($action_links, $links);

        return $links;
    }

    /**
     * @return void
     */
    public function payplus_get_seetting_plugin()
    {
        if (get_option('woocommerce_payplus-payment-gateway_settings')) {
            $options = get_option('woocommerce_payplus-payment-gateway_settings');
            $arrNotSave = array('enable_design_checkout', 'balance_name', 'add_product_field_transaction_type');
            $isSave = false;
            if (count($arrNotSave)) {
                foreach ($arrNotSave as $key => $value) {
                    if (!array_key_exists($value, $options)) {
                        $options[$value] = "no";
                        $isSave = true;
                    }
                }
                if ($isSave) {
                    update_option('woocommerce_payplus-payment-gateway_settings', $options);
                }
            }
            $payplus_payment_gateway_settings = (object) $options;
            $this->payplus_payment_gateway_settings = $payplus_payment_gateway_settings;
        }
    }

    /**
     * @return void
     */
    public function init()
    {
        load_plugin_textdomain('payplus-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');
        if (class_exists("WooCommerce")) {

            include_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_gateway.php';
            include_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_subgateways.php';
            include_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_invoice.php';
            include_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_express_checkout.php';
            add_action('woocommerce_blocks_loaded', [$this, 'woocommerce_payplus_woocommerce_block_support']);
            include_once PAYPLUS_PLUGIN_DIR . '/includes/wc_payplus_admin_payments.php';
            if (in_array('elementor/elementor.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                add_action('elementor/widgets/register', [$this, 'payplus_register_widgets']);
            }

            add_action('woocommerce_after_checkout_validation', [$this, 'payplus_validation_cart_checkout'], 10, 2);
            $this->payplus_get_seetting_plugin();

            add_action('wp_enqueue_scripts', [$this, 'load_checkout_assets']);
            add_action('woocommerce_api_callback_response', [$this, 'callback_response']);
            if (WP_DEBUG_LOG) {
                add_action('woocommerce_api_callback_response_hash', [$this, 'callback_response_hash']);
            }

            add_action('woocommerce_review_order_before_submit', [$this, 'payplus_view_iframe_payment'], 1);

            $this->invocie_api = new PayplusInvoice();
            add_action('manage_shop_order_posts_custom_column', [$this->invocie_api, 'payplus_add_order_column_order_invoice'], 100, 2);
            add_action('woocommerce_shop_order_list_table_custom_column', [$this->invocie_api, 'payplus_add_order_column_order_invoice'], 100, 2);
            add_action('woocommerce_order_item_add_action_buttons', [$this->invocie_api, 'payplus_order_item_add_action_buttons_callback'], 100, 1);

            if ($this->invocie_api->payplus_get_invoice_enable() && !$this->invocie_api->payplus_get_create_invocie_manual()) {

                add_action('woocommerce_order_status_' . $this->invocie_api->payplus_get_invoice_status_order(), [$this->invocie_api, 'payplus_invoice_create_order']);
                if ($this->invocie_api->payplus_get_create_invoice_automatic()) {
                    add_action('woocommerce_order_status_on-hold', [$this->invocie_api, 'payplus_invoice_create_order_automatic']);
                    add_action('woocommerce_order_status_processing', [$this->invocie_api, 'payplus_invoice_create_order_automatic']);
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
            payplus_create_table_db();
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

                $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();

                add_filter('body_class', [$this, 'payplus_body_classes']);
                wp_enqueue_style('payplus-css', PAYPLUS_PLUGIN_URL . 'assets/css/style.css', [], time());

                if ($isEnableOneClick) {
                    $payment_url_google_pay_iframe = $WC_PayPlus_Gateway->payplus_iframe_google_pay_oneclick;
                    wp_register_script('payplus-front-js', PAYPLUS_PLUGIN_URL . 'assets/js/front.min.js', [], PAYPLUS_VERSION, true);
                    wp_localize_script(
                        'payplus-front-js',
                        'payplus_script',
                        array("payment_url_google_pay_iframe" => $payment_url_google_pay_iframe, 'ajax_url' => admin_url('admin-ajax.php'))
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
        <div class="pp_iframe" data-height="<?php echo $height ?>"></div>
        <?php
$html = ob_get_clean();
        echo $html;
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
        $transactionTypeValue = get_post_meta($post->ID, 'payplus_transaction_type', true);

        $transactionTypes = array(
            '1' => __('Charge', 'payplus-payment-gateway'),
            '2' => __('Authorization', 'payplus-payment-gateway'),
        );
        if (count($transactionTypes)) {
            echo "<select id='payplus_transaction_type' name='payplus_transaction_type'>";
            echo "<option value=''>" . __('Transactions Type', 'payplus-payment-gateway') . "</option>";

            foreach ($transactionTypes as $key => $transactionType) {
                $selected = ($transactionTypeValue == $key) ? "selected" : "";
                echo "<option " . $selected . " value='" . $key . "'>" . $transactionType . "</option>";
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
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $new_columns = array();
        if (count($columns)) {
            foreach ($columns as $column_name => $column_info) {
                $new_columns[$column_name] = $column_info;
                if ('shipping_address' === $column_name && $WC_PayPlus_Gateway->enabled === "yes") {

                    $new_columns['payplus_transaction_type'] = "<span class='text-center'>" . __('Transaction Type ', 'payplus-payment-gateway') . "</span>";
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
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        if (count($columns)) {
            foreach ($columns as $column_name => $column_info) {
                $new_columns[$column_name] = $column_info;
                if ('price' === $column_name && $WC_PayPlus_Gateway->enabled === "yes") {
                    $new_columns['payplus_transaction_type'] = "<span class='text-center'>" . __('Transaction Type ', 'payplus-payment-gateway') . "</span>";
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
        wp_nonce_field('payplus_notice_proudct_nonce', 'payplus_notice_proudct_nonce');
        $balanceName = get_post_meta($post->ID, 'payplus_balance_name', true);

        printf('<input maxlength="20"   value="' . $balanceName . '" placeholder ="' . __('Balance Name', 'payplus-payment-gateway') . '"   type="text" id="payplus_balance_name" name="payplus_balance_name" />');
        echo ob_get_clean();
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
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $WC_PayPlus_Gateway->callback_response();
    }
    /**
     * @return void
     */
    public function callback_response_hash()
    {

        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $WC_PayPlus_Gateway->callback_response_hash();
    }

    /**
     * @param string $column
     * @return void
     */
    public function payplus_add_order_column_order_transaction_type($column)
    {
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();

        if ($column == "payplus_transaction_type" && $WC_PayPlus_Gateway->add_product_field_transaction_type) {
            global $post;
            $payplusTransactionType = get_post_meta($post->ID, 'payplus_transaction_type', true);
            if (!empty($payplusTransactionType)) {
                $transactionTypes = array(
                    '1' => __('Charge', 'payplus-payment-gateway'),
                    '2' => __('Authorization', 'payplus-payment-gateway'),
                );
                echo $transactionTypes[$payplusTransactionType];
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
            if ($payplus_payment_gateway_settings['disable_menu_header'] !== "yes") {
                add_action('admin_bar_menu', [$this, 'payplus_add_toolbar_items'], 100);
            }
            if ($payplus_payment_gateway_settings['disable_menu_side'] !== "yes") {
                add_action('admin_menu', [$this, 'payplus_add_admin_page_menu'], 99);
            }
        }
        return $methods;
    }

    /**
     * @return void
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'payplus-payment-gateway'), '2.0');
    }

    /**
     * @return void
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'payplus-payment-gateway'), '2.0');
    }

    /**
     * @param $fields
     * @param $errors
     * @return void
     */
    public function payplus_validation_cart_checkout($fields, $errors)
    {
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $woocommerce_price_num_decimal = get_option('woocommerce_price_num_decimals');

        if ($woocommerce_price_num_decimal > 2 || $woocommerce_price_num_decimal == 1 || $woocommerce_price_num_decimal < 0) {
            $errors->add('error', __('Unable to create a payment page due to a site settings issue. Please contact the website owner', 'payplus-payment-gateway'));
        }
        if ($WC_PayPlus_Gateway->payplus_check_blocked_ip()) {
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

        include_once PAYPLUS_PLUGIN_DIR . '/includes/elementor/widgets/express_checkout.php';
        $widgets_manager->register(new \Elementor_Express_Checkout());
    }

    /**
     * @return bool
     */
    public static function payplus_check_exists_table($table = 'payplus_order')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        $flag = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) ? true : false;
        return $flag;
    }
    public static function payplus_get_admin_menu()
    {

        ob_start();
        $currentSection = isset($_GET['section']) ? $_GET['section'] : "";
        $arrLink = array(
            'payplus-payment-gateway' => array(
                'name' => __('PayPlus  Gateway', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway',
                'img' => "<img src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "/payplus.svg'>",
            ),
            'payplus-invoice' => array(
                'name' => __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-invoice',
                'img' => "<img src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "/invocie+.svg'>",
            ),
            'payplus-express-checkout' => array(
                'name' => __('Express Checkout', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-express-checkout',
                'img' => "<img src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "/express.svg'>",
            ),
            'payplus-error-setting' => array(
                'name' => __('PayPlus Page Error - Settings', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-error-setting',
                'img' => "",
            ),
        );
        echo "<div id='payplus-options'>";
        if (count($arrLink)) {
            echo "<nav  class='nav-tab-wrapper tab-option-payplus'>";
            foreach ($arrLink as $key => $arrValue) {
                $seleted = ($key == $currentSection) ? "nav-tab-active" : "";
                echo "<a href='" . $arrValue['link'] . "'  class='nav-tab " . $seleted . "' >
                               " . $arrValue['img'] .
                    $arrValue['name'] .
                    "</a>";
            }
            echo "</nav>";
        }
        echo "</div>";
        return ob_get_clean();
    }

    /**
     * Function for `woocommerce_order_status_changed` action-hook.
     *
     * @param  $id
     * @param  $status_transition_from
     * @param  $status_transition_to
     * @param  $that
     *
     * @return void
     */
    public function payplus_order_status_changed_action($order_id, $status_transition_from, $status_transition_to, $order)
    {

        global $wpdb;
        $table_name = $wpdb->prefix . 'payplus_order_log';
        $debug_backtrace = debug_backtrace();
        $log = "";
        foreach ($debug_backtrace as $key => $debug) {
            $class = (isset($debug['class'])) ? $debug['class'] . "=>" : '';
            $log .= $debug['file'] . "=>" . $class . $debug['function'] . "=>" . $debug['line'] . "\n";
        }
        $data = array(
            'action_name' => 'change-status',
            'order_id' => $order_id,
            'status_transition_from' => $status_transition_from,
            'status_transition_to' => $status_transition_to,
            "log" => $log,
        );
        $wpdb->insert($table_name, $data);
        if ($wpdb->last_error) {
            payplus_Add_log_payplus($wpdb->last_error);
        }
    }
    public function woocommerce_payplus_woocommerce_block_support()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {

            require_once 'includes/blocks/wc_payplus_blocks_paymnet.php';
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

/**
 * @param $element
 * @return void
 */
function print_db($element)
{
    echo "<pre style='direction: ltr;'>";
    var_dump($element);
    echo "</pre>";
    echo "\n";
}

add_action('init', 'payplus_register_order_statuses');
/**
 * @return void
 */
function payplus_register_order_statuses()
{
    register_post_status('wc-recsubc', array(
        'label' => _x('Recurring subscription created', 'Order status', 'woocommerce'),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Recurring subscription created <span class="count">(%s)</span>', 'Recurring subscription created<span class="count">(%s)</span>', 'woocommerce'),
    ));
}
add_filter('wc_order_statuses', 'payplus_wc_order_statuses');
/**
 * @param array $order_statuses
 * @return array
 */
function payplus_wc_order_statuses($order_statuses)
{
    $order_statuses['wc-recsubc'] = _x('Recurring subscription created', 'Order status', 'woocommerce');

    return $order_statuses;
}
add_filter('handle_bulk_actions-edit-shop_order', 'payplus_orders_bulk_actions', 10, 3);
/**
 * @param $redirect_to
 * @param $action
 * @param $post_ids
 * @return mixed
 */
function payplus_orders_bulk_actions($redirect_to, $action, $post_ids)
{
    $invocie = new PayplusInvoice();
    $statusOrder = $invocie->payplus_get_invoice_status_order();

    $pos = strpos($action, "mark");

    if ($pos !== false) {
        $postStr = get_option('payplus_create_invoice');
        if (!$postStr) {
            $postStr = "";
        }
        $action = explode("_", $action);
        if ($action[1] == $statusOrder) {
            if ($invocie->payplus_get_invoice_enable()) {
                if (count($post_ids)) {
                    foreach ($post_ids as $key => $value) {
                        $postStr .= $value . ",";
                    }
                }
            }
        }
        update_option('payplus_create_invoice', $postStr);
    }
    return $redirect_to;
}

add_action('payplus_cron_send_order', function () {
    $invocie = new PayplusInvoice();

    if ($invocie->payplus_get_invoice_enable()) {
        $orders = get_option('payplus_create_invoice');
        $orders = explode(",", $orders);
        if (count($orders)) {
            foreach ($orders as $key => $order) {
                if (!empty($order)) {
                    $currentOrder = wc_get_order($order);
                    if ($invocie->payplus_get_invoice_status_order() == $currentOrder->get_status()) {
                        $invocie->payplus_invoice_create_order($order);
                    }
                }
            }
        }
        delete_option('payplus_create_invoice');
    }
});
/**
 * @param string $title
 * @param string $payment_id
 * @return string
 */
add_filter('woocommerce_gateway_title', 'change_cheque_payment_gateway_title', 100, 2);

function change_cheque_payment_gateway_title($title, $payment_id)
{
    $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
    if (is_checkout() && !is_admin() && $WC_PayPlus_Gateway->enable_design_checkout && strpos($payment_id, 'payplus-payment-gateway') !== false) {

        $title = "<span>" . $title . "</span>";
    }
    return $title;
}
/**
 * @return bool
 */
function payplus_check_woocommerce_custom_orders_table_enabled()
{
    return (get_option('woocommerce_custom_orders_table_enabled')) == "yes" ? true : false;
}

/**
 * @param  $order
 * @param $key
 * @param $value
 * @return void
 */
function payplus_update_post_meta_object($order, $values)
{
    if ($order) {
        $isOrderCustomEnable = payplus_check_woocommerce_custom_orders_table_enabled();
        $id = $order->get_id();
        foreach ($values as $key => $value) {
            $meta = get_post_meta($id, $key, true);
            if ($isOrderCustomEnable) {
                if ($key != "_payment_method_title") {
                    $order->update_meta_data($key, $value);
                }
            }
            if (!empty($meta)) {
                update_post_meta($id, $key, $value, $meta);
            } else {
                update_post_meta($id, $key, $value);
            }
        }
        if ($isOrderCustomEnable) {
            $order->save();
        }
    }
}
/**
 * @param $order
 * @return float | array
 */
function payplus_woocommerce_get_tax_rates($order)
{
    $rates = array();
    foreach ($order->get_items('tax') as $item_id => $item) {
        $tax_rate_id = $item->get_rate_id();
        $rates[] = WC_Tax::get_rate_percent_value($tax_rate_id);
    }
    if (count($rates) == 1) {
        return $rates[0];
    }
    return $rates;
}
function payplus_create_table_db()
{

    if (PAYPLUS_VERSION_DB != get_option('payplus_db_version')) {

        payplus_create_table_order();
        payplus_create_table_change_status_order();
        payplus_create_table_log();
        payplus_create_table_payment_session();
        payplus_create_table_process();
        update_option('payplus_db_version', PAYPLUS_VERSION_DB);
    }
}
/**
 * @return void
 */

function payplus_create_table_order()
{

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'payplus_order';

    $sql = "CREATE TABLE " . $table_name . " (
                id  int(11) NOT NULL AUTO_INCREMENT,
                create_at timestamp  default CURRENT_TIMESTAMP,
                order_id BIGINT NOT NULL,
                parent_id int(11) DEFAULT  0,
                transaction_uid varchar(255) DEFAULT NULL,
                method_payment  varchar(255) DEFAULT NULL,
                page_request_uid  varchar(255) DEFAULT NULL,
                four_digits varchar(4) DEFAULT NULL,
                number_of_payments int(11) DEFAULT  0,
                brand_name varchar(255) DEFAULT NULL,
                approval_num varchar(255) DEFAULT NULL,
                alternative_method_name varchar(20) DEFAULT NULL,
                type_payment  varchar(255) DEFAULT NULL ,
                token_uid varchar(255) DEFAULT NULL,
                price   int(11) DEFAULT  0,
                refund   int(11) DEFAULT  0,
                payplus_response  LONGTEXT  DEFAULT NULL,
                related_transactions int(11) DEFAULT  0 ,
                delete_at  int(11) DEFAULT  0 ,
                status_code  varchar(255) DEFAULT NULL ,
                invoice_refund int(11) DEFAULT  0,
                first_payment  int(11) DEFAULT  0,
                subsequent_payments int(11) DEFAULT  0,
                transaction_type varchar(255) DEFAULT NULL ,
                notes  LONGTEXT DEFAULT NULL,
                account_number  varchar(255) DEFAULT NULL,
                branch_number  varchar(255) DEFAULT NULL,
                 bank_number  varchar(255) DEFAULT NULL,
                 check_number  varchar(255) DEFAULT NULL,
                 transaction_id  varchar(255) DEFAULT NULL,
                 payer_account  varchar(255) DEFAULT NULL,
                PRIMARY KEY  (id)
                ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    if ($wpdb->last_error) {
        payplus_Add_log_payplus($wpdb->last_error);
    }
}

/**
 * @return void
 */
function payplus_create_table_change_status_order()
{

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'payplus_order_status';

    $sql = "CREATE TABLE " . $table_name . " (
                id  BIGINT NOT NULL AUTO_INCREMENT,
                order_id BIGINT NOT NULL,
                create_at timestamp  default CURRENT_TIMESTAMP,
                update_at  datetime DEFAULT NULL,
                create_at_refURL_success datetime  DEFAULT NULL,
                create_at_refURL_callback datetime  DEFAULT NULL,
                status  varchar(255) DEFAULT NULL,
                PRIMARY KEY  (id)
                ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    if ($wpdb->last_error) {
        payplus_Add_log_payplus($wpdb->last_error);
    }
}

function payplus_create_table_log()
{

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'payplus_order_log';

    $sql = "CREATE TABLE " . $table_name . " (
                id  int(11) NOT NULL AUTO_INCREMENT,
                order_id BIGINT NOT NULL,
                create_at timestamp  default CURRENT_TIMESTAMP,
                action_name varchar(255)  DEFAULT NULL ,
                status_transition_from varchar(255)  DEFAULT NULL ,
                status_transition_to varchar(255)  DEFAULT NULL ,
                log  text  DEFAULT NULL ,
                PRIMARY KEY  (id)
                ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    if ($wpdb->last_error) {
        payplus_Add_log_payplus($wpdb->last_error);
    }
}
/**
 * @return void
 */

function payplus_check_table_exist_db($nameTable)
{

    global $wpdb;
    if ($wpdb->get_var("show tables like '$nameTable'") != $nameTable) {
        return false;
    }
    return true;
}
function payplus_create_table_payment_session()
{
    global $wpdb;
    $tblname = $wpdb->prefix . PAYPLUS_TABLE_SESSION;
    $charset_collate = $wpdb->get_charset_collate();
    if (!payplus_check_table_exist_db($tblname)) {

        $sql = "CREATE TABLE $tblname (
                  id int(11) NOT NULL AUTO_INCREMENT,
                  payplus_date date NOT NULL,
                  payplus_created datetime NOT NULL,
                  payplus_update datetime NOT NULL,
                  payplus_ip text NOT NULL,
                  payplus_order int(11) NULL,
                  payplus_amount int(11) NULL,
                   payplus_status int(11) DEFAULT  1,
                  PRIMARY KEY (id)
                ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        if ($wpdb->last_error) {
            payplus_Add_log_payplus($wpdb->last_error);
        }
    }
}
/**
 * @return void
 */
function payplus_create_table_process()
{
    global $wpdb;
    $tblname = 'payplus_payment_process';
    $payplus_table = $wpdb->prefix . $tblname;
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $payplus_table (
                  id int(11) NOT NULL AUTO_INCREMENT,
                  order_id BIGINT NOT NULL,
                  create_at timestamp  default CURRENT_TIMESTAMP,
                  function_begin  varchar(255)  DEFAULT NULL ,
                  status_code  varchar(255)  DEFAULT NULL ,
                  count_process int(11) NOT NULL,
                  function_end varchar(255)  DEFAULT NULL ,
                  PRIMARY KEY (id)
                ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    if ($wpdb->last_error) {
        payplus_Add_log_payplus($wpdb->last_error);
    }
}
/**
 * @return bool|void
 */
function payplus_add_file_ApplePay()
{

    $sourceFile = PAYPLUS_SRC_FILE_APPLE . '/' . PAYPLUS_APPLE_FILE;
    $destinationFile = PAYPLUS_DEST_FILE_APPLE . '/' . PAYPLUS_APPLE_FILE;

    if (!file_exists($destinationFile)) {
        if (file_exists($sourceFile)) {
            if (!is_dir(PAYPLUS_DEST_FILE_APPLE)) {
                wp_mkdir_p(PAYPLUS_DEST_FILE_APPLE);
                chmod(PAYPLUS_DEST_FILE_APPLE, 0777);
            }
            if (!file_exists($destinationFile)) {
                if (copy($sourceFile, $destinationFile)) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    } else {
        return true;
    }
}
function payplus_Add_log_payplus($last_error)
{
    $beforeMsg = 'Plugin Version: ' . PAYPLUS_VERSION;
    $logger = wc_get_logger();
    $logger->add('error-db-payplus', $beforeMsg . "\n" . $last_error . "\n" . str_repeat("=", 232));
}
register_activation_hook(__FILE__, 'payplus_create_table_order');
register_activation_hook(__FILE__, 'payplus_create_table_change_status_order');
register_activation_hook(__FILE__, 'payplus_create_table_log');
register_activation_hook(__FILE__, 'payplus_create_table_payment_session');
register_activation_hook(__FILE__, 'payplus_create_table_process');
add_filter('woocommerce_price_trim_zeros', '__return_true');
add_filter('acf/settings/remove_wp_meta_box', '__return_false');

add_filter('woocommerce_admin_billing_fields', 'payplus_order_admin_custom_fields');
function payplus_order_admin_custom_fields($fields)
{
    global $theorder;

    if ($theorder) {
        $sorted_fields = [];
        foreach ($fields as $key => $values) {
            if ($key === 'company') {
                $sorted_fields[$key] = $values;
                $sorted_fields['vat_number'] = array(
                    'label' => __('ID \ VAT Number', 'payplus-payment-gateway'),
                    'value' => get_post_meta($theorder->get_id(), '_billing_vat_number', true),
                    'show' => true,
                    'wrapper_class' => 'form-field-wide',
                    'position ' => 1,
                    'style' => '',
                );
            } else {
                $sorted_fields[$key] = $values;
            }
        }
        return $sorted_fields;
    }
    return $fields;
}

add_action('woocommerce_process_shop_order_meta', 'payplus_checkout_field_update_order_meta', 10, );
function payplus_checkout_field_update_order_meta($order_id)
{

    if (isset($_POST['_billing_vat_number'])) {
        update_post_meta($order_id, '_billing_vat_number', sanitize_text_field($_POST['_billing_vat_number']));
    }
}