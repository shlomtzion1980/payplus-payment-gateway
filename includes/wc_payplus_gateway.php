<?php
defined('ABSPATH') || exit; // Exit if accessed directly
define("COUNT_PAY_PLUS", 3);
define('CLUB_CARD', array('multipass', 'valuecard', 'tav-zahav', 'finitione'));
define('CREDIT_CARD', 'credit-card');
define('PAYPLUS_PAYMENT_URL_PRODUCTION', 'https://restapi.payplus.co.il/api/v1.0/');
define('PAYPLUS_PAYMENT_URL_DEV', 'https://restapidev.payplus.co.il/api/v1.0/');
define('PAYPLUS_GOOGLE_PAY_IFRAME_ONECLICK_PRODUCTION', 'https://payments.payplus.co.il/occ/google-pay');
define('PAYPLUS_GOOGLE_PAY_IFRAME_ONECLICK_DEV', 'https://paymentsdev.payplus.co.il/occ/google-pay');
define('ROUNDING_DECIMALS', 2);
define("EMPTY_STRING_PAYPLUS", "");
define('PAYPLUS_WC_VERSION', WC_VERSION);
define('PAYPLUS_SRC_FILE_APPLE', PAYPLUS_PLUGIN_DIR);
define('PAYPLUS_DEST_FILE_APPLE', ABSPATH . ".well-known");
define('PAYPLUS_APPLE_FILE', 'apple-developer-merchantid-domain-association');
define('PAYPLUS_LOG_INFO_LEVEL', 'info');

class WC_PayPlus_Gateway extends WC_Payment_Gateway_CC
{

    public $id = 'payplus-payment-gateway';
    public $add_product_field_transaction_type;
    public $disable_menu_side;
    public $disable_menu_header;
    public $invoice_api;
    public $check_amount_authorization;
    public $api_test_mode;
    public $block_ip_transactions;
    public $block_ip_transactions_hour;
    public $is_Local_pickup;
    public $single_quantity_per_line;
    public $rounding_decimals;
    public $hide_icon;
    public $api_key;
    public $transaction_type;
    public $secret_key;
    public $payment_page_id;
    public $disable_woocommerce_scheduler;
    public $initial_invoice;
    public $paying_vat;
    public $paying_vat_all_order;
    public $change_vat_in_eilat;
    public $keywords_eilat;
    public $paying_vat_iso_code;
    public $foreign_invoices_lang;
    public $exist_company;
    public $display_mode;
    public $iframe_height;
    public $send_products;
    public $import_applepay_script;
    public $use_ipn;
    public $send_variations;
    public $create_pp_token;
    public $send_add_data;
    public $hide_identification_id;
    public $hide_payments_field;
    public $default_charge_method;
    public $hide_other_charge_methods;
    public $vat_number_field;
    public $sendEmailApproval;
    public $sendEmailFailure;
    public $recurring_order_set_to_paid;
    public $balance_name;
    public $successful_order_status;
    public $failure_order_status;
    public $callback_addr;
    public $logging;
    public $fire_completed;
    public $invoice_lang;
    public $response_url;
    public $payplus_generate_key_dashboard;
    public $response_error_url;
    public $add_payment_res_url;
    public $api_url;
    public $payment_url;
    public $ipn_url;
    public $refund_url;
    public $clearing_companies_url;
    public $issuers_companies_url;
    public $brands_list_url;
    public $enable_design_checkout;
    public $payplus_iframe_google_pay_oneclick;
    public $shipping_woo;
    public $global_shipping;
    public $global_shipping_tax;
    public $global_shipping_tax_rate;
    public $token_apple_pay;
    public $enable_google_pay;
    public $enable_apple_pay;
    public $enable_product;
    public $enable_create_user;
    public $hide_custom_fields_buttons;
    public $saveOrderNote;
    public $currentApiKey;
    public $currentSecret;
    public $applePaySettings;
    public $allSettings;
    private $_wpnonce;
    private $current_time;

    /**
     *
     */
    public function __construct()
    {

        /******  VARIBLES START ******/
        $payplus_invoice_api_key = null;
        $payplus_invoice_secret_key = null;
        $this->has_fields = false;
        $this->method_title = __('PayPlus', 'payplus-payment-gateway');
        $this->method_description = __('PayPlus Credit Card Secure Payment', 'payplus-payment-gateway');
        $this->supports = [
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'multiple_subscriptions',
            'refunds',
            'pre-orders',
            'tokenization',
        ];
        $this->add_product_field_transaction_type =
            $this->get_option('add_product_field_transaction_type') == "yes" ? true : false;
        // menu
        $this->disable_menu_header = $this->get_option('disable_menu_header') == 'yes' ? false : true;
        $this->disable_menu_side = $this->get_option('disable_menu_side') == 'yes' ? false : true;
        $this->check_amount_authorization = $this->get_option('check_amount_authorization') == 'yes' ? false : true;
        $this->_wpnonce = wp_create_nonce('PayPlusGateWayNonce');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_test_mode = $this->get_option('api_test_mode') === 'yes' ? true : false;
        $this->block_ip_transactions = $this->get_option('block_ip_transactions') == 'yes' ? true : false;
        $this->is_Local_pickup = $this->get_option('is_Local_pickup') == 'yes' ? true : false;
        $this->block_ip_transactions_hour = $this->get_option('block_ip_transactions_hour');
        $this->api_key = $this->api_test_mode ? $this->get_option('dev_api_key') ?? null : $this->get_option('api_key');
        $this->secret_key = $this->api_test_mode ? $this->get_option('dev_secret_key') ?? null : $this->get_option('secret_key');
        $this->payment_page_id = $this->api_test_mode ? $this->get_option('dev_payment_page_id') ?? null : $this->get_option('payment_page_id');
        $this->current_time = wp_date('Y-m-d H:i:s', current_time('timestamp'));
        $this->rounding_decimals = ROUNDING_DECIMALS;
        $this->hide_custom_fields_buttons = $this->get_option('hide_custom_fields_buttons') == 'yes' ? true : false;

        if (wc_get_price_decimals() < ROUNDING_DECIMALS) {
            $this->rounding_decimals = wc_get_price_decimals();
        }
        $this->single_quantity_per_line = $this->get_option('single_quantity_per_line');

        // - PayPlus Payment Options
        $this->hide_icon = $this->get_option('hide_icon');
        $this->transaction_type = $this->get_option('transaction_type');
        $this->disable_woocommerce_scheduler = $this->get_option('disable_woocommerce_scheduler');
        $this->initial_invoice = $this->get_option('initial_invoice');
        $this->paying_vat = $this->get_option('paying_vat');
        $this->paying_vat_all_order = $this->get_option('paying_vat_all_order');

        $this->change_vat_in_eilat = ($this->get_option('change_vat_in_eilat') == "yes") ? true : false;
        $this->keywords_eilat = explode(",", $this->get_option('keywords_eilat'));

        $this->paying_vat_iso_code = $this->get_option('paying_vat_iso_code');
        $this->foreign_invoices_lang = $this->get_option('foreign_invoices_lang');
        $this->send_products = $this->get_option('send_products') == 'yes' ? true : false;
        $this->exist_company = $this->get_option('exist_company') == 'yes' ? true : false;
        // - PayPlus Settings Options
        $this->use_ipn = true;
        $this->send_variations = $this->get_option('send_variations') == 'yes' ? true : false;

        $this->create_pp_token = $this->get_option('create_pp_token') == 'yes' ? true : false;
        $this->send_add_data = $this->get_option('send_add_data') == 'yes' ? true : false;
        $this->hide_identification_id = $this->get_option('hide_identification_id');
        $this->vat_number_field = $this->get_option('vat_number_field');
        $this->hide_payments_field = $this->get_option('hide_payments_field');
        $this->default_charge_method = $this->get_option('default_charge_method');
        $this->hide_other_charge_methods = $this->get_option('hide_other_charge_methods');
        $this->sendEmailApproval = $this->get_option('sendEmailApproval');
        $this->sendEmailFailure = $this->get_option('sendEmailFailure');
        $this->recurring_order_set_to_paid = $this->get_option('recurring_order_set_to_paid');
        $this->paying_vat = $this->get_option('paying_vat');
        $this->balance_name = $this->get_option('balance_name') == 'yes' ? true : false;
        $this->saveOrderNote = isset($this->settings['payplus_data_save_order_note']) ? boolval($this->settings['payplus_data_save_order_note'] === 'yes') : null;
        $this->successful_order_status = $this->get_option('successful_order_status');
        $this->failure_order_status = $this->get_option('failure_order_status');
        $this->callback_addr = $this->get_option('callback_addr');
        $this->logging = wc_get_logger();
        $this->fire_completed = $this->get_option('fire_completed') == 'yes' ? true : false;
        $this->invoice_lang = $this->get_option('invoice_lang') == 'en' ? 'en' : '';

        //wc-api=payplus_gateway added to the response url will initiate the woocommerce_api_payplus_gateway action - which will start the ipn_response
        $this->response_url = add_query_arg('wc-api', 'payplus_gateway', home_url('/'));
        $this->enable_design_checkout = ($this->get_option('enable_design_checkout') == "yes") ? true : false;

        $this->display_mode = $this->get_option('display_mode');
        $this->iframe_height = $this->get_option('iframe_height');
        $this->allSettings = get_option('woocommerce_payplus-payment-gateway_settings');
        $this->applePaySettings = get_option('woocommerce_payplus-payment-gateway-applepay_settings');
        $this->import_applepay_script = boolval(boolval(isset($this->allSettings['enable_apple_pay']) && $this->allSettings['enable_apple_pay'] === 'yes') || boolval(isset($this->applePaySettings['enabled']) && $this->applePaySettings['enabled'] === "yes"));

        $payPlusErrorPage = get_option('error_page_payplus');
        $payplusLinkError = isset($payPlusErrorPage) ? get_permalink($payPlusErrorPage) : null;

        $this->payplus_generate_key_dashboard = $this->payplus_generate_key_dashboard();
        $this->response_error_url = $payplusLinkError;
        //wc-api=payplus_gateway added to the response url will initiate the woocommerce_api_payplus_add_payment action - which will start the add_payment_ipn_response
        $this->add_payment_res_url = add_query_arg('wc-api', 'payplus_add_payment', home_url('/'));

        $this->api_url = ($this->api_test_mode) ? PAYPLUS_PAYMENT_URL_DEV : PAYPLUS_PAYMENT_URL_PRODUCTION;
        $this->payment_url = $this->api_url . 'PaymentPages/generateLink';
        $this->ipn_url = $this->api_url . 'PaymentPages/ipn';
        $this->refund_url = $this->api_url . 'Transactions/RefundByTransactionUID';
        $this->clearing_companies_url = $this->api_url . 'ClearingCompanies';
        $this->issuers_companies_url = $this->api_url . 'issuerscompanies';
        $this->brands_list_url = $this->api_url . 'BrandsList';

        if ($this->hide_icon == "no") {
            $this->icon = PAYPLUS_PLUGIN_URL . 'assets/images/PayPlusLogo.svg';
        }
        $this->payplus_iframe_google_pay_oneclick = ($this->api_test_mode) ? PAYPLUS_GOOGLE_PAY_IFRAME_ONECLICK_DEV : PAYPLUS_GOOGLE_PAY_IFRAME_ONECLICK_PRODUCTION;

        $this->shipping_woo = ($this->get_option('shipping_woo') === "yes") ? true : false;
        $this->global_shipping = $this->get_option('global_shipping');
        $this->global_shipping_tax = $this->get_option('global_shipping_tax');
        $this->global_shipping_tax_rate = $this->get_option('global_shipping_tax_rate');
        $this->token_apple_pay = $this->get_option('apple_pay_identifier');
        $this->enable_google_pay = $this->get_option('enable_google_pay') == 'yes' ? true : false;
        $this->enable_apple_pay = $this->get_option('enable_apple_pay') == 'yes' ? true : false;
        $this->enable_product = $this->get_option('enable_product') == 'yes' ? true : false;
        $this->enable_create_user = $this->get_option('enable_create_user') == 'yes' ? true : false;
        /****** VARIBLES END ******/

        $this->init_form_fields();
        $this->init_settings();
        $this->update_option('logging', 'yes');
        $this->update_option('exceptpayplus', '6279071307118');

        /****** ACTION START ******/

        add_action('admin_enqueue_scripts', [$this, 'load_admin_assets']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
        add_action('woocommerce_api_payplus_add_payment', [$this, 'add_payment_ipn_response']);
        add_action('woocommerce_customer_save_address', [$this, 'show_update_card_notice'], 10, 2);
        add_action('woocommerce_api_update_payplus_payment_method', [$this, 'updatePaymentMethodHook']);

        /****** ACTION END ******/

        /****** FILTER START ******/

        add_filter('user_has_cap', [$this, 'payplus_disbale_page_delete'], 10, 3);
        add_filter('page_row_actions', [$this, 'payplus_remove_row_actions_post'], 10, 1);

        /****** FILTER END ******/

        // Subscription Handler
        if (class_exists('WC_Subscriptions_Order') && $this->disable_woocommerce_scheduler !== 'yes') {
            add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
        }

        $this->invoice_api = new PayplusInvoice();
        $payplus_invoice_option = get_option('payplus_invoice_option');
        if ($payplus_invoice_option) {
            $payplus_invoice_api_key = $payplus_invoice_option['payplus_invoice_api_key'] ?? null;
            $payplus_invoice_secret_key = $payplus_invoice_option['payplus_invoice_secret_key'] ?? null;
        }
        if (($this->api_key && $payplus_invoice_api_key !== $this->api_key)
            || ($this->secret_key && $payplus_invoice_secret_key !== $this->secret_key)
        ) {
            $payplus_invoice_option['payplus_invoice_api_key'] = $this->api_key;
            $payplus_invoice_option['payplus_invoice_secret_key'] = $this->secret_key;
            update_option('payplus_invoice_option', $payplus_invoice_option);
        }
    }


    public function payPlusOrdersCheck($nonce)
    {
        if (!wp_verify_nonce($nonce, 'payPlusOrderChecker')) {
            wp_die('Sorry this page is not allowed! - payPlusOrdersCheck');
        }

        if (!current_user_can('edit_shop_orders')) {
            wp_die('Sorry this page is not allowed! - payPlusOrdersCheck user privileges.');
        }

        $current_time = current_time('Y-m-d H:i:s');

        // Extract the current hour and minute
        $current_hour = gmdate('H', strtotime($current_time));
        $current_minute = gmdate('i', strtotime($current_time));

        $args = array(
            'status' => ['pending', 'cancelled'],
            'date_created' => $current_time,
            'return' => 'ids', // Just return IDs to save memory
        );

        $orders = array_reverse(wc_get_orders($args));
        if (count($orders)) {
            echo '<pre>';
            echo "The following orders were created today and are in pending status: <br>";
            echo "(This will not cancel the scheduled cron event)<br><br>";
            echo "Orders: ";
            print_r(implode(",", $orders) . "<br></br>");
            $this->payplus_add_log_all('payplus-orders-verify-log', '~=> payPlusOrdersCheck <=~ process started: ' . wp_json_encode($orders), 'default');
            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                $hour = $order->get_date_created()->date('H');
                $min = $order->get_date_created()->date('i');
                $calc = $current_minute - $min;
                $runIpn = true;
                $paymentPageUid = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_page_request_uid') !== "" ? WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_page_request_uid') : false;
                if ($paymentPageUid) {
                    echo esc_html("Order #$order_id status:" . $order->get_status() . "\n");
                    if ($order->get_status() === 'cancelled') {
                        $payPlusResponse = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_response');
                        if (WC_PayPlus_Statics::pp_is_json($payPlusResponse)) {
                            $responseStatus = json_decode($payPlusResponse, true)['status_code'];
                            if ($responseStatus === "000") {
                                echo esc_html("\n-=<Order: $order_id>=-\n");
                                echo "---------------------------------------------------\n";
                                echo "The order was created and paid for succcessfully\n";
                                echo "The order was edited to cancelled manually.\n";
                                echo "Not running ipn check.\n";
                                echo "---------------------------------------------------\n";
                                $runIpn = false;
                            }
                        }
                    }
                    if ($runIpn === true) {
                        echo esc_html("Order #$order_id contains payment page uid - running ipn! - check order notes and status for results!");
                        echo "\n";
                        $this->payplus_add_log_all('payplus-orders-verify-log', "$order_id: Running IPN validation.\n");
                        $PayPlusAdminPayments = new WC_PayPlus_Admin_Payments;
                        $_wpnonce = wp_create_nonce('_wp_payplusIpn');
                        $PayPlusAdminPayments->payplusIpn($order_id, $_wpnonce);
                    }
                }
            }
        } else {
            echo "<pre>";
            wp_die('No orders matching the criteria were found.');
        }
    }

    /**
     * The hook function callback that handles the post of update settings from PayPlus crm from endpoint
     * ?wc-api=update_payplus_payment_method
     * @param 
     * @return void
     */
    public function updatePaymentMethodHook()
    {
        $methodsOptions = [
            'bit' => 'woocommerce_payplus-payment-gateway-bit_settings',
            'googlepay' => 'woocommerce_payplus-payment-gateway-googlepay_settings',
            'applepay' => 'woocommerce_payplus-payment-gateway-applepay_settings',
            'multipass' => 'woocommerce_payplus-payment-gateway-multipass_settings',
            'paypal' => 'woocommerce_payplus-payment-gateway-paypal_settings',
            'tavzahav' => 'woocommerce_payplus-payment-gateway-tavzahav_settings',
            'valuecard' => 'woocommerce_payplus-payment-gateway-valuecard_settings',
            'finitone' => 'woocommerce_payplus-payment-gateway-finitione_settings',
            'hostedFields' => 'woocommerce_payplus-payment-gateway-hostedfields_settings'
        ];

        // Get the raw POST body
        $verified = $this->verify_request();

        $postBody = file_get_contents('php://input');
        $postData = json_decode($postBody, true);
        $methodType = sanitize_text_field($postData['method_type']);

        if (!$verified) {
            $message = "Webhook received for $methodType with action: {$postData['action']} but failed X-Signature verification.";
            wp_send_json_error($message, 403);
            exit;
        }

        if (json_last_error() === JSON_ERROR_NONE) {
            // Handle the data...
            $methodOptions = get_option(sanitize_text_field($methodsOptions[$methodType]));
            $action = $postData['action'] === 'enable' ? 'yes' : 'no';
            $methodOptions['enabled'] = $action;
            update_option(sanitize_text_field($methodsOptions[$methodType]), $methodOptions);
            $result = 'success';
            $postDataAction = esc_html($postData['action']);
            $methodReceived = esc_html($methodType);
            $message = esc_html("Webhook received for $methodReceived successfully with action: $postDataAction");
            wp_send_json_success($message);
        } else {
            wp_send_json_error('Invalid JSON', 400);
        }
        $postData = esc_html(wp_json_encode($postData));
        error_log('Received PayPlus CRM update_payplus_payment_method POST: ' . print_r($postData, true) . " - " . print_r($message, true));
    }

    public function get_current_time()
    {
        return $this->current_time;
    }

    public function verify_request()
    {
        $shared_secret = $this->secret_key;
        // Retrieve the signature from the headers
        $signature = isset($_SERVER['HTTP_X_SIGNATURE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_SIGNATURE'])) : '';
        // Retrieve the request body
        $body = wp_json_encode(json_decode(file_get_contents('php://input')));
        // Generate the expected signature
        $expected_signature = hash_hmac('sha256', $body, $shared_secret);
        // Verify the signature
        return hash_equals($expected_signature, $signature);
    }
    /**
     * @param $clearing_id
     * @return false|mixed|void
     */
    public function payplus_get_clearing_companies($clearing_id = null)
    {
        $clearingCompanies = get_option('payplus_clearing_companies');
        if (empty($clearingCompanies)) {
            $clearingCompanies = [];
            $response = $this->post_payplus_ws($this->clearing_companies_url, array(), 'get');
            $res = json_decode(wp_remote_retrieve_body($response));
            $res = $res->clearing;
            if ($res) {
                foreach ($res as $key => $value) {
                    $clearingCompanies[$value->id] = $value->name;
                }
            }
            update_option('payplus_clearing_companies', $clearingCompanies);
            $clearingCompanies = get_option('payplus_clearing_companies');
        }

        if ($clearing_id) {
            return $clearingCompanies[$clearing_id];
        }

        return $clearingCompanies;
    }

    /**
     * @param $issuer_id
     * @return false|mixed|void
     */
    public function payplus_get_issuers_companies($issuer_id = null)
    {
        $issuersCompanies = get_option('payplus_issuers_companies');

        if (empty($issuersCompanies)) {
            $issuersCompanies = [];
            $response = $this->post_payplus_ws($this->issuers_companies_url, array(), 'get');
            $res = json_decode(wp_remote_retrieve_body($response));
            $res = $res->isuuer;
            if ($res) {
                foreach ($res as $key => $value) {

                    $issuersCompanies[$value->id] = $value->name;
                }
            }
            $res = update_option('payplus_issuers_companies', $issuersCompanies);
            $issuersCompanies = get_option('payplus_issuers_companies');
        }

        if ($issuer_id) {
            return $issuersCompanies[$issuer_id];
        }
        return $issuersCompanies;
    }

    /**
     * @param $brand_id
     * @return false|mixed|void
     */
    public function payplus_get_brands_list($brand_id = null)
    {
        $brands = get_option('payplus_brands');
        if (empty($brands)) {
            $brands = [];
            $response = $this->post_payplus_ws($this->brands_list_url, array(), 'get');
            $res = json_decode(wp_remote_retrieve_body($response));

            $res = $res->transactionsCardsBrands;
            if ($res) {
                foreach ($res as $key => $value) {
                    $brands[$value->id] = $value->name;
                }
            }
            $res = update_option('payplus_brands', $brands);
            $brands = get_option('payplus_brands');
        }
        if ($brand_id) {
            return $brands[$brand_id];
        }
        return $brands;
    }

    /**
     * @param $actions
     * @return mixed
     */
    public function payplus_remove_row_actions_post($actions)
    {
        global $post;
        $post_error_page = get_option('error_page_payplus');

        if ($post_error_page == $post->ID) {
            unset($actions['clone']);
            unset($actions['trash']);
        }
        return $actions;
    }

    /**
     * Adds a notice for customer when they update their billing address.
     *
     * @since 4.1.0
     * @param int    $user_id      The ID of the current user.
     * @param string $load_address The address to load.
     */
    public function show_update_card_notice($user_id, $load_address)
    {

        if (
            is_admin() ||
            !$this->create_pp_token ||
            !WC_PayPlus_Payment_Tokens::customer_has_saved_methods($user_id) ||
            'billing' !== $load_address
        ) {
            return;
        }
        wc_clear_notices();
        /* translators: 1) Opening anchor tag 2) closing anchor tag */
        wc_add_notice(sprintf(__('If your billing address has been changed for saved payment methods, be sure to remove any %1$ssaved payment methods%2$s on file and re-add them.', 'paypluse-payment-gateway'), '<a href="' . esc_url(wc_get_endpoint_url('payment-methods')) . '" class="wc-payplus-update-card-notice" style="text-decoration:underline;">', '</a>'), 'notice');
    }

    /**
     * @param $allcaps
     * @param $caps
     * @param $args
     * @return mixed
     */
    public function payplus_disbale_page_delete($allcaps, $caps, $args)
    {
        $post_id = get_option('error_page_payplus');
        if (isset($args[0]) && isset($args[2]) && $args[2] == $post_id && ($args[0] == 'delete_post' || $args[0] == 'edit_pages')) {

            $allcaps[$caps[0]] = false;
        }
        return $allcaps;
    }


    /**
     * @return false|mixed|void
     */
    public function payplus_generate_key_dashboard()
    {
        $dashboardKey = get_option('payplus_generate_key_link_dashboard');

        if (empty($dashboardKey)) {
            $dashboardKey = wp_generate_password(50, false);
            add_option('payplus_generate_key_link_dashboard', $dashboardKey);
        }
        return $dashboardKey;
    }

    /**
     * @return void
     */
    public function load_admin_assets()
    {
        wp_enqueue_script('wc-payplus-gateway-admin', PAYPLUS_PLUGIN_URL . 'assets/js/admin.min.js', ['jquery'], time(), true);
    }

    /**
     * @return void
     */
    public function init_form_fields()
    {
        require_once PAYPLUS_PLUGIN_DIR . '/includes/class-wc-payplus-form-fields.php';
        $payplus_payment_gateway_settings = get_option('woocommerce_payplus-payment-gateway_settings');

        $disabled = (empty($payplus_payment_gateway_settings['transaction_type']) ||
            $payplus_payment_gateway_settings['transaction_type'] !== "2") ? array('disabled' => 'disabled') : array();

        $formFields = WC_PayPlus_Form_Fields::getFormFields();
        $this->form_fields = $formFields;
    }

    /**
     * @param int $order_id
     * @param float|null $amount
     * @param string $reason
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        global $wpdb;
        $flag = false;
        $order = wc_get_order($order_id);
        $invoice_manual = $this->invoice_api->payplus_get_create_invoice_manual();
        $indexRow = 0;
        $handle = 'payplus_process_refund';
        $table_name = $wpdb->prefix . 'payplus_order';
        $this->payplus_add_log_all($handle, 'WP Refund (' . $order_id . ')');

        $refunded_amount = round((float) $order->get_meta('payplus_total_refunded_amount'), 2);
        if (!$refunded_amount or $refunded_amount <= 0) {
            $refunded_amount = 0;
        }

        $transaction_uid = $order->get_meta('payplus_transaction_uid');
        if ($order->get_meta('payplus_type') != "Charge") {
            $this->payplus_add_log_all($handle, 'Already Charged or Original Transaction Are Not J5', 'error');
            /* translators: %s is the current transaction type status */
            $order->add_order_note(sprintf(__('PayPlus Refund is Failed<br />You cannot refund transaction that not charged. Current Transaction Type Status: %s', 'payplus-payment-gateway'), $order->get_meta('payplus_type')));
            return false;
        }
        if (round((float) $refunded_amount, ROUNDING_DECIMALS) >= round((float) $order->get_total(), ROUNDING_DECIMALS)) {
            $this->payplus_add_log_all($handle, 'You Cannot charge more then the original transaction amount', 'error');
            /* translators: %1$s is the refunded amount, %2$s is the currency */
            $order->add_order_note(sprintf(__('PayPlus Refund is Failed<br />You cannot refund more then the refunded total amount. Refunded Amount: %1$s %2$s', 'payplus-payment-gateway'), $refunded_amount, $order->get_currency()));
            return false;
        }
        $payplusRrelatedTransactions = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_related_transactions', true);

        if ($payplusRrelatedTransactions) {
            $transaction_uid = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_transaction_uid_credit-card', true);
        }
        $this->payplus_add_log_all($handle, 'WP Refund (' . $order_id . ')');
        if (floatval($amount)) {
            $payload = array();
            $payload['transaction_uid'] = $transaction_uid;
            $payload['amount'] = round($amount, $this->rounding_decimals);
            $payload['more_info'] = __('Refund for Order Number: ', 'payplus-payment-gateway') . $order_id;

            if ($this->invoice_api->payplus_get_invoice_enable()) {
                $payload['initial_invoice'] = false;
            }

            $payload = wp_json_encode($payload);
            $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');
            $response = $this->post_payplus_ws($this->refund_url, $payload);

            if (is_wp_error($response)) {
                $this->payplus_add_log_all($handle, print_r($response, true), 'error');
            } else {
                $res = json_decode(wp_remote_retrieve_body($response));

                if ($res->results->status == "success" && $res->data->transaction->status_code == "000") {

                    if ($this->invoice_api->payplus_get_invoice_enable() && !$invoice_manual) {
                        $resultApps = $this->invoice_api->payplus_get_payments($order_id, 'otherClub');
                        if ($resultApps[$indexRow]->price > round($amount, $this->rounding_decimals)) {
                            $resultApps[$indexRow]->price = $amount * 100;
                        }
                        $this->invoice_api->payplus_create_document_dashboard(
                            $order_id,
                            $this->invoice_api->payplus_get_invoice_type_document_refund(),
                            $resultApps,
                            round($amount, $this->rounding_decimals),
                            'payplus_order_refund' . $order_id
                        );
                    }
                    $insertMeta = array(
                        'payplus_credit-card' => floatval(WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_credit-card', true)) - $amount,
                        'payplus_total_refunded_amount' => round($refunded_amount + $amount, 2),
                    );
                    WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
                    $order->add_order_note(sprintf(
                        'PayPlus Refund is Successful<br />Refund Transaction Number: %1$s<br />Amount: %2$s %3$s<br />Reason: %4$s',
                        $res->data->transaction->number,
                        $res->data->transaction->amount,
                        $order->get_currency(),
                        $reason
                    ));
                    $this->payplus_add_log_all($handle, print_r($res, true), 'completed');
                    $flag = true;
                } else {
                    /* translators: %1$s is the status, %2$s is the description */
                    $order->add_order_note(sprintf(
                        'PayPlus Refund is Failed<br />Status: %1$s<br />Description: %2$s',
                        $res->results->status,
                        $res->results->description
                    ));
                    $this->payplus_add_log_all($handle, print_r($res, true), 'error');
                    $flag = false;
                }
            }
        }
        return $flag;
    }

    /**
     * @return void
     */
    public function payplus_get_nav_option()
    {
        if (!wp_verify_nonce($this->_wpnonce, 'PayPlusGateWayNonce')) {
            wp_die('Not allowed! - payplus_get_nav_option');
        }
        $currentSection = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : "";
        $adminTabs = WC_PayPlus_Admin_Settings::getAdminTabs();
        if (count($adminTabs)) {
            echo "<nav class='nav-tab-wrapper tab-option-payplus'>";
            foreach ($adminTabs as $key => $arrValue) {
                $selected = ($key == $currentSection) ? "nav-tab-active" : "";
                $name = esc_html($arrValue['name']); // Ensure this is appropriate for the content
                echo "<a href='" . esc_url($arrValue['link']) . "' class='nav-tab  " . esc_html($selected) . "'>
                           " . wp_kses($arrValue['img'], array(
                    'img' => array(
                        'src' => true,
                        'alt' => true,
                    ),
                )) . esc_html($name) . "</a>";
            }
            echo "</nav>";
        }
    }


    /**
     * @return void
     */
    public function admin_options()
    {
        if (!wp_verify_nonce($this->_wpnonce, 'PayPlusGateWayNonce')) {
            wp_die('Not allowed! - admin_options');
        }
        $title = esc_html(__('PayPlus', 'payplus-payment-gateway') . " ( " . PAYPLUS_VERSION . " )");
        $desc = wp_kses(
            __('For more information about PayPlus and Plugin versions <a href="https://www.payplus.co.il/wordpress" target="_blank">www.payplus.co.il/wordpress</a>', 'payplus-payment-gateway'),
            array(
                'a' => array(
                    'href' => array(),
                    'target' => array()
                )
            )
        );
        $credit = __('This plugin was developed by <a href="https://www.payplus.co.il">PayPlus LTD</a>', 'payplus-payment-gateway');
        ob_start();

        $currentSection = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : "";
        $this->generate_settings_html();
        $settings = ob_get_clean();

        $arrOtherPayment = array(
            "bit" => array(
                "icon" => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "bitLogo.png",
                "link" => "?page=wc-settings&tab=checkout&section=payplus-payment-gateway-bit",
                "section" => "payplus-payment-gateway-bit",
                "style" => "max-height: 100%;"
            ),
            "Google Pay" => array(
                "icon" => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "google-payLogo.png",
                "link" => "?page=wc-settings&tab=checkout&section=payplus-payment-gateway-googlepay",
                "section" => "payplus-payment-gateway-googlepay",
                "style" => "max-height: 100%;"
            ),
            "Apple Pay" => array(
                "icon" => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "apple-payLogo.png",
                "link" => "?page=wc-settings&tab=checkout&section=payplus-payment-gateway-applepay",
                "section" => "payplus-payment-gateway-applepay",
                "style" => "max-height: 100%;"
            ),
            "MULTIPASS" => array(
                "icon" => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipassLogo.png",
                "link" => "?page=wc-settings&tab=checkout&section=payplus-payment-gateway-multipass",
                "section" => "payplus-payment-gateway-multipass",
                "style" => "max-height: 100%;"
            ),
            "PayPal" => array(
                "icon" => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "paypalLogo.png",
                "link" => "?page=wc-settings&tab=checkout&section=payplus-payment-gateway-paypal",
                "section" => "payplus-payment-gateway-paypal",
                "style" => "max-height: 100%;"
            ),
            "Tav Zahav" => array(
                "icon" => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "verifoneLogo.png",
                "link" => "?page=wc-settings&tab=checkout&section=payplus-payment-gateway-tavzahav",
                "section" => "payplus-payment-gateway-tavzahav",
                "style" => "max-height: 100%;"
            ),
            "Valuecard" => array(
                "icon" => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "valuecardLogo.png",
                "link" => "?page=wc-settings&tab=checkout&section=payplus-payment-gateway-valuecard",
                "section" => "payplus-payment-gateway-valuecard",
                "style" => "max-height: 100%;"
            ),
            "finitiOne" => array(
                "icon" => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "finitioneLogo.png",
                "link" => "?page=wc-settings&tab=checkout&section=payplus-payment-gateway-finitione",
                "section" => "payplus-payment-gateway-finitione",
                "style" => "max-height: 100%;"
            ),
            "hostedFields" => array(
                "icon" => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "PayPlusLogo.svg",
                "link" => "?page=wc-settings&tab=checkout&section=payplus-payment-gateway-hostedfields",
                "section" => "payplus-payment-gateway-hostedfields",
                "style" => "max-height: 100%;"
            ),
        );

        echo "<div id='payplus-options'>";
        $this->payplus_get_nav_option();
        if (count($arrOtherPayment)) {
            echo "<nav  id='sub-option-paylus' class='nav-tab-wrapper tab-option-payplus'>";
            foreach ($arrOtherPayment as $key => $value) {
                $iconStyle = isset($value['style']) ? $value['style'] : '';
                $selected = ($currentSection == $value['section']) ? "nav-tab-active" : "";
                $translatedKey = "";
                switch ($key) {
                    case 'bit':
                        $translatedKey = esc_html__('bit', 'payplus-payment-gateway');
                        break;
                    case 'Google Pay':
                        $translatedKey = esc_html__('Google Pay', 'payplus-payment-gateway');
                        break;
                    case 'Apple Pay':
                        $translatedKey = esc_html__('Apple Pay', 'payplus-payment-gateway');
                        break;
                    case 'MULTIPASS':
                        $translatedKey = esc_html__('MULTIPASS', 'payplus-payment-gateway');
                        break;
                    case 'PayPal':
                        $translatedKey = esc_html__('PayPal', 'payplus-payment-gateway');
                        break;
                    case 'Tav Zahav':
                        $translatedKey = esc_html__('Tav Zahav', 'payplus-payment-gateway');
                        break;
                    case 'Valuecard':
                        $translatedKey = esc_html__('Valuecard', 'payplus-payment-gateway');
                        break;
                    case 'finitiOne':
                        $translatedKey = esc_html__('finitiOne', 'payplus-payment-gateway');
                        break;
                    case 'hostedFields':
                        $translatedKey = esc_html__('hostedFields', 'payplus-payment-gateway');
                        break;
                }
                if ($currentSection === $value['section']) {
                    $title = $translatedKey;
                }
                $allowed_html = array(
                    'img' => array(
                        'src' => true,
                        'alt' => true,
                        'style' => true,
                    ),
                );

                echo "<a data-tab='payplus-blank' href='" . esc_url($value['link']) . "' class='nav-tab " . esc_attr($selected) . "'>" .
                    wp_kses(
                        "<img style='" . esc_attr($iconStyle) . "' src='" . esc_url($value['icon']) . "' alt='" . esc_attr($translatedKey) . "'>",
                        $allowed_html
                    ) .
                    esc_html($translatedKey) .
                    "</a>";
            }
            echo "</nav>";
        }

        echo "<h3 id='payplus-title-section'>" . esc_html($title) . "</h3>
                    <p>" . wp_kses_post($desc) . "</p>";

        function hide($currentSection)
        {
            $hide = $currentSection === 'payplus-payment-gateway' ? 'hideIt' : null;
            return $hide;
        }

        $allowed_tags = array(
            'div' => array(
                'style' => array(),
                'class' => array(),
                'id' => array(),
            ),
            'table' => array(
                'class' => array(),
                'style' => array(),
            ),
            'tr' => array(),
            'td' => array(
                'style' => array(),
                'class' => array(),
                'colspan' => array(),
                'rowspan' => array(),
                'align' => array(),
                'valign' => array(),
            ),
            'a' => array(
                'href' => array(),
                'target' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'span' => array(
                'style' => array(),
                'class' => array(),
            ),
            'label' => array(
                'for' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'input' => array(
                'type' => array(),
                'name' => array(),
                'value' => array(),
                'class' => array(),
                'style' => array(),
                'placeholder' => array(),
                'checked' => array(),
                'id' => array(),
            ),
            'textarea' => array(
                'name' => array(),
                'class' => array(),
                'style' => array(),
                'id' => array(),
                'rows' => array(),
                'cols' => array(),
                'placeholder' => array(),
            ),
            'select' => array(
                'name' => array(),
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ),
            'option' => array(
                'value' => array(),
                'selected' => array(),
            ),
            'fieldset' => array(),
            'legend' => array(),
            'br' => array(),
            'ul' => array(
                'style' => array(),
                'class' => array(),
            ),
            'li' => array(
                'style' => array(),
                'class' => array(),
            ),
            'p' => array(
                'style' => array(),
                'class' => array(),
            ),
            'strong' => array(),
            'em' => array(),
            'blockquote' => array(),
            'hr' => array(),
            'h1' => array(),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'img' => array(
                'src' => array(),
                'alt' => array(),
                'class' => array(),
                'style' => array(),
            ),
            'abbr' => array(
                'title' => array(),
            ),
            'code' => array(),
            'pre' => array(),
            'iframe' => array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'frameborder' => array(),
                'allowfullscreen' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'a' => array(
                'href' => true,
                'target' => true,
                'class' => true,
                'id' => true,
                'style' => true,
                'title' => true,
            ),
            'abbr' => array(
                'title' => true,
            ),
            'b' => array(),
            'blockquote' => array(
                'cite' => true,
            ),
            'cite' => array(),
            'code' => array(),
            'del' => array(
                'datetime' => true,
            ),
            'dd' => array(),
            'div' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'dl' => array(),
            'dt' => array(),
            'em' => array(),
            'h1' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'h2' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'h3' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'h4' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'h5' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'h6' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'hr' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'i' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'img' => array(
                'alt' => true,
                'class' => true,
                'height' => true,
                'src' => true,
                'style' => true,
                'width' => true,
            ),
            'input' => array(
                'class' => true,
                'id' => true,
                'name' => true,
                'style' => true,
                'type' => true,
                'value' => true,
                'checked' => true,
            ),
            'label' => array(
                'for' => true,
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'legend' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'li' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'ol' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'p' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'pre' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'span' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'strong' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'table' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'tbody' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'td' => array(
                'class' => true,
                'colspan' => true,
                'id' => true,
                'rowspan' => true,
                'style' => true,
            ),
            'textarea' => array(
                'class' => true,
                'cols' => true,
                'id' => true,
                'name' => true,
                'rows' => true,
                'style' => true,
            ),
            'tfoot' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'th' => array(
                'class' => true,
                'colspan' => true,
                'id' => true,
                'rowspan' => true,
                'scope' => true,
                'style' => true,
            ),
            'thead' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
            'tr' => array(
                'class' => true,
                'id' => true,
                'style' => true,
                'valign' => true,
            ),
            'ul' => array(
                'class' => true,
                'id' => true,
                'style' => true,
            ),
        );

        // Allow data attributes for tooltips
        $allowed_tags = array_merge_recursive($allowed_tags, array(
            'div' => array(
                'data-tip' => array(),
            ),
            'span' => array(
                'data-tip' => array(),
            ),
            'label' => array(
                'data-tip' => array(),
            ),
        ));

        echo "<div id='settingsContainer'><div class='tab-section-payplus' id='tab-payplus-gateway' >
                        <table class='form-table " . esc_attr(hide($currentSection)) . " fullWidth'>" . wp_kses($settings, $allowed_tags) . "</table>
                    </div><div class='right-tab-section-payplus fullHeight hideIt'></div></div>
                    <div class='payplus-credit' style='left:20px;position: absolute; bottom: 0;'>" . wp_kses(
            $credit,
            array(
                'a' => array(
                    'href' => array()
                )
            )
        ) . "</div>
                </div>";
    }

    /**
     * @return void
     */
    public function payment_fields()
    {
        if ($this->supports('tokenization') && is_checkout()) {
            $this->tokenization_script();
            if ($this->create_pp_token) {
                $this->saved_payment_methods();
            }
            $this->save_payment_method_checkbox();
        }
    }

    /**
     * @return void
     */
    public function save_payment_method_checkbox()
    {
        $html = '<div class="payplus-option-description-area">';
        if ($this->description) {
            $html .= '<p class="form-row payment-method-description">' . esc_html($this->description) . '</p>';
        }
        if ($this->create_pp_token && $this->id) {
            $html .= sprintf(
                '<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
                            <input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
                            <label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
                        </p>',
                esc_attr($this->id),
                esc_html__('Save credit card in my account', 'payplus-payment-gateway')
            );
        }
        $html .= '</div>';

        echo apply_filters('woocommerce_payment_gateway_save_new_payment_method_option_html', $html, $this);
    }

    public function get_payment_ips()
    {
        $ips = get_transient('payment_ips');
        return $ips === false ? array() : $ips; // Return an empty array if the transient does not exist
    }

    public function store_payment_ip()
    {
        $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : "";

        // Retrieve the current IPs from the transient
        $ips = get_transient('payment_ips');
        if ($ips === false) {
            $ips = array();
        }

        // Add the new IP to the beginning of the array
        array_unshift($ips, $client_ip);

        // Keep only the last 10 IPs
        $ips = array_slice($ips, 0, 200);

        // Store the updated array in the transient with a 30-minute expiration
        set_transient('payment_ips', $ips, 1800); // 1800 seconds = 30 minutes
    }


    /**
     * @param int $order_id
     * @return array|void
     */
    public function process_payment($order_id)
    {
        if ($this->block_ip_transactions) {
            $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : "";
            if (filter_var($client_ip, FILTER_VALIDATE_IP) === false) {
                $client_ip = ""; // Handle invalid IP scenario if necessary
            }
            $counts = array_count_values($this->get_payment_ips());
            $howMany = isset($counts[$client_ip]) ? $counts[$client_ip] : 0;
            if (in_array($client_ip, $this->get_payment_ips()) && $howMany >= $this->block_ip_transactions_hour) {
                wp_die(
                    esc_html__('Something went wrong with the payment page - This Ip is blocked', 'payplus-payment-gateway')
                );
            }
        }
        $handle = 'payplus_payment_using_token';
        $order = wc_get_order($order_id);
        $objectLogging = new stdClass();
        $objectLogging->keyHandle = 'payplus_payment_using_token';
        $objectLogging->msg = array();
        $is_token = (isset($_POST['wc-' . $this->id . '-payment-token']) && $_POST['wc-' . $this->id . '-payment-token'] !== 'new') ? true : false;  // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $saveToken = isset($_POST['wc-' . $this->id . '-new-payment-method']) ? sanitize_text_field(wp_unslash($_POST['wc-' . $this->id . '-new-payment-method'])) : false;  // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($saveToken) {
            WC_PayPlus_Meta_Data::update_meta($order, array('save_payment_method' => true));
        }
        $order->save_meta_data();
        $redirect_to = add_query_arg('order-pay', $order_id, add_query_arg('key', $order->get_order_key(), get_permalink(wc_get_page_id('checkout'))));

        if ($is_token) {

            $token_id = wc_clean(sanitize_text_field(wp_unslash($_POST['wc-' . $this->id . '-payment-token'])));  // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $token = WC_Payment_Tokens::get($token_id);

            if (!$this->checkValidateCard($token)) {
                $this->payplus_add_log_all($handle, 'Token Expired: ' . $token, 'error');
                wc_add_notice(__('Error: user or other, please contact PayPlus support', 'payplus-payment-gateway'), 'error');
                do_action('wc_gateway_payplus_process_payment_error', __('Error: user or other, please contact PayPlus support', 'payplus-payment-gateway'), $order);
                $order->update_status('failed');

                return [
                    'result'   => 'fail',
                    'redirect' => '',
                ];
            }

            $response = $this->receipt_page($order_id, $token);

            if (property_exists($response, 'results') && $response->results->status === "error" && $response->results->code === 1) {
                // Customize the error message here
                $error_message = 'This credit card token was saved with different billing information. It cannot be used for this order. Please enter the credit card information manually.';
                // Translators: %s will be replaced with the error message received from the payment gateway.
                wc_add_notice(sprintf(__('Error: Credit card declined. %s', 'payplus-payment-gateway'), print_r($error_message, true)), 'error');
                // Translators: %s will be replaced with the error message received from the payment gateway.
                do_action('wc_gateway_payplus_process_payment_error', sprintf(__('Error: Credit card declined. %s', 'payplus-payment-gateway'), print_r($error_message, true)), $order);
                $order->update_status('failed');
                $this->store_payment_ip();
                return [
                    'result'   => 'fail',
                    'redirect' => '',
                ];
            }

            $this->payplus_add_log_all($handle, print_r($response, true), 'completed');

            if ($response->data->status == "approved" && $response->data->status_code == "000" && $response->data->transaction_uid) {
                $redirect_to = str_replace('order-pay', 'order-received', $redirect_to);
                $transactionUid = $response->data->transaction_uid;

                $this->updateMetaData($order_id, (array) $response->data);
                if ($response->data->type == "Charge") {
                    if ($this->fire_completed && $this->successful_order_status === 'default-woo') {
                        WC_PayPlus_Meta_Data::sendMoreInfo($order, 'process_payment->firePaymentComplete', $transactionUid);
                        $order->payment_complete();
                    }

                    if ($this->successful_order_status !== 'default-woo') {
                        WC_PayPlus_Meta_Data::sendMoreInfo($order,  'process_payment->' . $this->successful_order_status, $transactionUid);
                        $order->update_status($this->successful_order_status);
                    }
                } else {
                    WC_PayPlus_Meta_Data::sendMoreInfo($order,  'process_payment->wc-on-hold', $transactionUid);
                    $order->update_status('wc-on-hold');
                }
                // Translators: %s will be replaced with the transaction number received from the payment gateway.
                $order->add_order_note(sprintf(__('PayPlus Token Payment Successful<br/>Transaction Number: %s', 'payplus-payment-gateway'), $response->data->number));
                // Add payments data to the DB
                $inData = json_decode(wp_json_encode($response->data), true);
                $this->payplus_add_order($order_id, $inData);
            } else {
                if ($this->display_mode !== 'iframe') {
                    // Translators: %s will be replaced with the transaction number received from the payment gateway.
                    $order->add_order_note(sprintf(__('PayPlus Token Payment Failed<br/>Transaction Number: %s', 'payplus-payment-gateway'), $response->data->number));
                    if ($this->failure_order_status !== 'default-woo') {
                        $order->update_status($this->failure_order_status);
                    }
                    // Translators: %s will be replaced with the status description of the error received from the payment gateway.
                    wc_add_notice(sprintf(__('Error: credit card declined: %s', 'payplus-payment-gateway'), print_r($response->data->status_description, true)), 'error');
                    return;
                }
            }
        }

        $result = [
            'result' => 'success',
            'redirect' => $redirect_to,
            'viewMode' => $this->display_mode,
        ];
        if (in_array($this->display_mode, ['samePageIframe', 'popupIframe']) && !$is_token) {

            $result['payplus_iframe'] = $this->receipt_page($order_id, null, false, '', 0, true);
        }
        return $result;
    }

    /**
     * @param $token
     * @return bool|void
     */
    public function checkValidateCard($token = null)
    {
        if (!$token->get_token()) {
            return false;
        }

        if ($token->get_expiry_year() > gmdate("Y")) {
            return true;
        }

        if ($token->get_expiry_year() < gmdate("Y")) {
            return false;
        }

        if ($token->get_expiry_month() >= gmdate("m")) {
            return true;
        }
    }

    /**
     * @param array $products
     * @param int $order_id
     * @return string
     */
    public function payplus_generate_products_link($products, $order_id)
    {
        $mainPluginOptions = get_option('woocommerce_payplus-payment-gateway_settings');
        $displayNode = ($mainPluginOptions['display_mode'] ?: 'redirect');
        $generate_products_link = "payplus#" . $order_id . "|";
        if ($products) {
            foreach ($products as $key => $product) {

                $product = json_decode($product);
                $barcode = "";
                if (!empty($product->barcode)) {
                    $barcode = $product->barcode;
                }
                $generate_products_link .= $barcode . "-" . $product->quantity . "-" . $product->price . "|";
            }
            $generate_products_link .= "-" . $this->default_charge_method . "-" . $displayNode;
            return $generate_products_link;
        }
    }

    /**
     * @param $order
     * @param $checkChargemMethod
     * @return bool
     */
    public function payplus_check_all_product($order, $checkChargemMethod)
    {
        $items = $order->get_items(['line_item', 'fee', 'coupon']);
        if (count($items)) {
            foreach ($items as $item => $item_data) {
                $transactionTypeValue = WC_PayPlus_Meta_Data::get_meta($item_data['product_id'], 'payplus_transaction_type', true);
                if ($transactionTypeValue == $checkChargemMethod) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param $post_id
     * @param $fields
     * @return array|object|stdClass[]|null
     */
    public function payplus_get_posts_id($post_id = "", $fields = array())
    {
        global $wpdb;
        $post_id = !empty($post_id) ? intval($post_id) : null;
        $sql = "SELECT * FROM {$wpdb->posts}";

        $where = "";

        if ($post_id || !empty($fields)) {
            if ($post_id) {
                $where .= $wpdb->prepare(" ID = %d", $post_id);
            }
            if (!empty($fields)) {
                foreach ($fields as $key => $value) {
                    $where .= $where ? $wpdb->prepare(" AND %s = %s", sanitize_key($key), sanitize_text_field($value)) : $wpdb->prepare(" %s = %s ", $key, $value);
                }
            }
        }

        if ($where) {
            $sql .= " WHERE" . $where;
        }
        $posts = $wpdb->get_results($sql);

        return $posts;
    }

    /**
     * @param $external_recurring_id
     * @param $order_id
     * @return false|string
     */
    public function getRecurring($external_recurring_id, $order_id)
    {

        $billing_interval = WC_PayPlus_Meta_Data::get_meta($external_recurring_id, '_billing_interval', true);
        $billing_period = WC_PayPlus_Meta_Data::get_meta($external_recurring_id, '_billing_period', true);
        $external_recurring_payment['external_recurring_id'] = $external_recurring_id;
        $external_recurring_payment['external_recurring_charge_id'] = $order_id;

        if ($billing_period === "day") {
            $external_recurring_payment['external_recurring_type'] = 0;
            $external_recurring_payment['external_recurring_range'] = $billing_interval;
        } elseif ($billing_period === "week") {
            $external_recurring_payment['external_recurring_type'] = 1;
            $external_recurring_payment['external_recurring_range'] = $billing_interval;
        } elseif ($billing_period === "month") {
            $external_recurring_payment['external_recurring_type'] = 2;
            $external_recurring_payment['external_recurring_range'] = $billing_interval;
        } elseif ($billing_period === "year") {
            $external_recurring_payment['external_recurring_type'] = 2;
            $external_recurring_payment['external_recurring_range'] = $billing_interval * 12;
        }

        return wp_json_encode($external_recurring_payment);
    }

    /**
     * @param $order
     * @return false
     */
    public function getShippingMethod($order)
    {
        $shipping_method_data = false;
        $shipping_methods = $order->get_shipping_methods();
        if ($shipping_methods) {
            foreach ($shipping_methods as $shipping_method) {
                $shipping_method_data = $shipping_method->get_data();
                return $shipping_method_data;
            }
        }
        return $shipping_method_data;
    }

    /**
     * @param $count
     * @return string
     */
    public function payplus_get_space($count = 150)
    {
        return str_repeat("=", $count);
    }

    /**
     * @param $order_id
     * @return string
     */
    public function getDiscrptionUpPickup($order_id)
    {
        $jsondata = str_replace('\\"', '"', WC_PayPlus_Meta_Data::get_meta($order_id, 'pkps_json', true));
        $jsondata = preg_replace('/\\\"/', "\"", $jsondata);
        $jsondata = preg_replace('/\\\'/', "\'", $jsondata);
        $jsondata = json_decode($jsondata);
        $title = str_replace(["'", '"', "\n", "\\", '”'], '', $jsondata->title);
        $street = str_replace(["'", '"', "\n", "\\", '”'], '', $jsondata->street);
        $city = str_replace(["'", '"', "\n", "\\", '”'], '', $jsondata->city);
        $description = $title . " (" . $street . " ,$city )";
        return $description;
    }

    /**
     * @param $order_id
     * @return array
     */
    public function payplus_get_client_by_order_id($order_id)
    {
        $customer = [];
        $customerName = "";
        $order = wc_get_order($order_id);
        $cell_phone = str_replace(["'", '"', "\\"], '', $order->get_billing_phone());
        $address = trim(str_replace(["'", '"', "\\"], '', $order->get_billing_address_1() . ' ' . $order->get_billing_address_2()));
        $city = str_replace(["'", '"', "\\"], '', $order->get_billing_city());
        $postal_code = str_replace(["'", '"', "\\"], '', $order->get_billing_postcode());
        $customer_country_iso = $order->get_billing_country();
        $company = $order->get_billing_company();
        if (!empty($order->get_billing_email())) {
            $customer['email'] = $order->get_billing_email();
        }

        if ($this->exist_company && !empty($company)) {
            $customerName = $company;
        } else {
            if (!empty($order->get_billing_first_name()) || !empty($order->get_billing_last_name())) {
                $customerName = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            }
            if (!$customerName) {
                $customerName = $order->get_billing_company();
            } elseif ($order->get_billing_company()) {
                $customerName .= " (" . $order->get_billing_company() . ")";
            }
        }
        if (!empty($customerName)) {
            $customer['customer_name'] = $customerName;
        }
        if ($cell_phone) {
            $customer['phone'] = $cell_phone;
        }
        if ($address) {
            $customer['address'] = $address;
        }
        if ($city) {
            $customer['city'] = $city;
        }
        if ($postal_code) {
            $customer['postal_code'] = $postal_code;
        }

        if ($customer_country_iso) {
            $customer['country_iso'] = $customer_country_iso;
        }
        if ($this->vat_number_field && $order->get_meta($this->vat_number_field)) {
            $customer['vat_number'] = $order->get_meta($this->vat_number_field);
        }
        if (intval($order->get_customer_id())) {
            $customer['customer_external_number'] = $order->get_customer_id();
        }
        return $customer;
    }

    /**
     * @param $order_id
     * @param $json
     * @return object
     */
    public function payplus_get_products_by_order_id($order_id, $json = true)
    {

        $order = wc_get_order($order_id);
        $allProductSku = "";
        $productsItems = array();
        $totalCartAmount = 0;
        $items = $order->get_items(['line_item', 'fee', 'coupon']);
        $wc_tax_enabled = (wc_tax_enabled());
        $temptax = payplus_woocommerce_get_tax_rates($order);
        $tax = 1;
        $isAdmin = is_admin();
        if (is_numeric($temptax)) {
            $tax = 1 + ($temptax / 100);
        }

        foreach ($items as $item => $item_data) {
            $discount = 0;
            $tempTaxValue = 0;
            $product = new WC_Product($item_data['product_id']);
            $dataArr = $item_data->get_data();
            $name = str_replace(["'", '"', "\n", "\\", '”'], '', wp_strip_all_tags($item_data['name']));
            $metaAll = wc_display_item_meta($item_data, array(
                'before' => '',
                'after' => '',
                'separator' => ' | ',
                'echo' => false,
                'autop' => false
            ));
            $quantity = ($item_data['quantity'] ? round($item_data['quantity'], $this->rounding_decimals) : '1');

            if ($item_data['type'] == "coupon") {
                $allProductSku .= (empty($allProductSku)) ? " ( " . $name : ' , ' . $name;
            } else {
                if ($item_data['type'] == "fee") {
                    $productPrice = $item_data['line_total'];
                    if ($this->rounding_decimals != 0 && $wc_tax_enabled) {
                        $productPrice += $item_data['total_tax'];
                    }
                    $productPrice = round($productPrice, $this->rounding_decimals);
                    $totalCartAmount += ($productPrice);
                } else {
                    if ($this->single_quantity_per_line == 'yes') {
                        $productPrice = $order->get_item_subtotal($item_data, $wc_tax_enabled) * $quantity;
                        $productPrice = round($productPrice, $this->rounding_decimals);
                        $totalCartAmount += $productPrice;
                        $name .= ' ×  ' . $quantity;
                        $quantity = 1;
                    } else {
                        if ($this->rounding_decimals == 0 && $wc_tax_enabled) {
                            $productPrice = $order->get_item_subtotal($item_data);
                        } else {
                            $productPrice = $order->get_item_subtotal($item_data, $wc_tax_enabled);
                        }
                        $productPrice = round($productPrice, $this->rounding_decimals);
                        if ($isAdmin && $item_data->get_subtotal() !== $item_data->get_total()) {
                            $discount = ($item_data->get_subtotal() - $item_data->get_total()) * $tax;
                            $discount = round($discount, $this->rounding_decimals);
                        }
                        $totalCartAmount += $productPrice * $quantity - $discount;
                    }
                }
                //LearnPress
                if (get_class($item_data) === "WC_Order_Item_LP_Course") {
                    $product = new WC_Product_LP_Course($item_data['product_id']);
                    $productImageData = wp_get_attachment_image_src(WC_PayPlus_Meta_Data::get_meta($item_data['product_id'], '_thumbnail_id', true), 'full');
                } else {
                    $product = new WC_Product($item_data['product_id']);
                    $productImageData = wp_get_attachment_image_src($product->get_image_id(), 'full');
                }
                $productSKU = ($product->get_sku()) ? $product->get_sku() : $item_data['product_id'];

                if (!empty($dataArr['variation_id'])) {

                    $product1 = new WC_Product_Variable($dataArr['product_id']);
                    $variationsProduct = $product1->get_available_variations();
                    if (count($variationsProduct)) {
                        $productSKU = ($variationsProduct[0]['sku']) ? $variationsProduct[0]['sku'] :
                            $variationsProduct[0]['variation_id'];
                    }
                }

                $itemDetails = [
                    'name' => $name,
                    'barcode' => (string) $productSKU,
                    'quantity' => ($quantity ? $quantity : '1'),
                    'price' => round($productPrice, $this->rounding_decimals),
                ];

                if ($discount) {
                    $itemDetails['discount_type'] = 'amount';
                    $itemDetails['discount_value'] = $discount;
                }
                if ($productImageData && isset($productImageData[0])) {
                    $itemDetails['image_url'] = $productImageData[0];
                }

                if (!empty($metaAll) && $this->send_variations) {
                    $itemDetails['product_invoice_extra_details'] = str_replace(["'", '"', "\n", "\\"], '', wp_strip_all_tags($metaAll));
                }

                $itemDetails['vat_type'] = $item_data->get_tax_status() == 'none' || !$wc_tax_enabled ? 2 : 0;
                $itemDetails['vat_type'] = $this->paying_vat_all_order === "yes" ? 0 : $itemDetails['vat_type'];

                if ($this->change_vat_in_eilat) {
                    $itemDetails['vat_type'] = $this->payplus_check_is_vat_eilat($order_id) ? 2 : 0;
                }

                if ($productPrice) {
                    $productsItems[] = ($json) ? wp_json_encode($itemDetails) : $itemDetails;
                }
            }
        }

        if ($this->rounding_decimals == 0 && $order->get_total_tax()) {
            $productPrice = round($order->get_total_tax(), $this->rounding_decimals);

            $itemDetails = [
                'name' => __("Round", "payplus-payment-gateway"),
                'quantity' => 1,
                'price' => $productPrice,
                'is_summary_item' => true,

            ];
            $productsItems[] = ($json) ? wp_json_encode($itemDetails) : (object) $itemDetails;
            $totalCartAmount += $productPrice;
        }
        $shipping_methods = $order->get_shipping_methods();
        if ($shipping_methods) {
            foreach ($shipping_methods as $shipping_method) {
                $shipping_tax = 0;
                $shipping_method_data = $shipping_method->get_data();
                $shipping_total = $order->get_shipping_total();
                if ($this->rounding_decimals != 0 && $wc_tax_enabled) {
                    $shipping_tax = $order->get_shipping_tax();
                }
                $productPrice = $shipping_total + $shipping_tax;
                $productPrice = round($productPrice, $this->rounding_decimals);

                $description = "";
                if ($shipping_method_data['method_id'] === "woo-ups-pickups") {
                    $description = $this->getDiscrptionUpPickup($order_id);
                }

                $name = __('Shipping', 'payplus-payment-gateway') . ' - ' . str_replace(["'", '"', "\\"], '', $shipping_method_data['name']) . ' ' . $description;
                $itemDetails = [
                    'name' => $name,
                    'quantity' => 1,
                    'price' => $productPrice,
                ];
                $productsItems[] = ($json) ? wp_json_encode($itemDetails) : $itemDetails;
                $totalCartAmount += $productPrice;
            }
        }
        // coupons

        if (!$isAdmin && $order->get_total_discount()) {
            $productCouponPrice = ($order->get_total_discount());
            if ($this->rounding_decimals != 0 && $wc_tax_enabled) {
                $productCouponPrice += $order->get_discount_tax();
            }
            $productCouponPrice *= -1;
            $productCouponPrice = round($productCouponPrice, $this->rounding_decimals);
            $totalCartAmount += $productCouponPrice;

            $itemDetails = [
                'name' => ($allProductSku) ? $allProductSku . " ) " : __('Discount coupons', 'payplus-payment-gateway'),
                'barcode' => __('Discount coupons', 'payplus-payment-gateway'),
                'quantity' => 1,
                'price' => round($productCouponPrice, $this->rounding_decimals),
            ];
            $productsItems[] = ($json) ? wp_json_encode($itemDetails) : $itemDetails;
        }

        $gift_cards = $order->get_meta('_ywgc_applied_gift_cards');
        $updated_as_fee = $order->get_meta('ywgc_gift_card_updated_as_fee');
        $priceGift = 0;
        $allProductSku = "";
        if ($gift_cards && $updated_as_fee == false) {

            foreach ($gift_cards as $key => $gift) {
                $productPrice = -1 * ($gift);
                $allProductSku .= (empty($allProductSku)) ? " ( " . $key : ' , ' . $key;
                $priceGift += round($productPrice, $this->rounding_decimals);
            }

            $itemDetails = [
                'name' => ($allProductSku) ? $allProductSku . " ) " : __('Discount coupons', 'payplus-payment-gateway'),
                'barcode' => __('Discount coupons', 'payplus-payment-gateway'),
                'quantity' => 1,
                'price' => $priceGift,
            ];
            $productsItems[] = ($json) ? wp_json_encode($itemDetails) : $itemDetails;
            $totalCartAmount += $priceGift;
        }
        $totalCartAmount = round($totalCartAmount, $this->rounding_decimals);

        $return = (object) ["productsItems" => $productsItems, 'amount' => $totalCartAmount];
        return $return;
    }

    /**
     * @param int $order_id
     * @return bool
     */
    public function payplus_check_is_vat_eilat($order_id)
    {
        $order = wc_get_order($order_id);
        $shippingMethod = $this->getShippingMethod($order);
        $cityShipping = trim($order->get_shipping_city());
        $isEilat = (is_array($this->keywords_eilat) && in_array($cityShipping, $this->keywords_eilat)) ? true : false;

        if ((isset($shippingMethod['method_id']) && $shippingMethod['method_id'] === 'local_pickup') || $isEilat) {
            return true;
        }
        return false;
    }

    /**
     * @param int $order_id
     * @param bool $isAdmin
     * @param $token
     * @param $subscription
     * @param $custom_more_info
     * @return string
     */
    public function generatePayloadLink($order_id, $isAdmin = false, $token = null, $subscription = false, $custom_more_info = '', $move_token = false, $options = [])
    {
        $order = wc_get_order($order_id);
        $langCode = explode("_", get_locale());
        $customer_country_iso = $order->get_billing_country();
        $totallCart = round($order->get_total(), $this->rounding_decimals);

        $shippingMethod = $this->getShippingMethod($order);
        $customer = $this->payplus_get_client_by_order_id($order_id);
        if (!$this->send_products) {
            $objectProducts = $this->payplus_get_products_by_order_id($order_id);
        }

        $customer = (count($customer)) ? '"customer":' . wp_json_encode($customer) . "," : "";
        $redriectSuccess = ($isAdmin) ? $this->response_url . "&paymentPayPlusDashboard=" . $this->payplus_generate_key_dashboard . "&_wpnonce=" . wp_create_nonce('payload_link') : $this->response_url . "&success_order_id=$order_id&_wpnonce=" . wp_create_nonce('payload_link');
        $setInvoice = '';
        $payingVat = '';
        $invoiceLanguage = '';
        $addChargeLine = '';
        if ($subscription) {
            $addChargeLine = '"charge_method": 1,';
        } else if ($this->settings['transaction_type'] != "0") {
            $addChargeLine = '"charge_method": ' . $this->settings['transaction_type'] . ',';
        }
        if (!$subscription && $this->add_product_field_transaction_type) {
            if ($this->payplus_check_all_product($order, "2")) {
                $addChargeLine = '"charge_method": 2,';
            } elseif ($this->payplus_check_all_product($order, "1")) {
                $addChargeLine = '"charge_method": 1,';
            }
        }

        if ($this->invoice_api->payplus_get_invoice_enable()) {
            $flagInvoice = 'false';
            $setInvoice = '"initial_invoice": ' . $flagInvoice . ',';
        } elseif ($this->initial_invoice == "1") {
            $flagInvoice = 'true';
            $setInvoice = '"initial_invoice": ' . $flagInvoice . ',';
        } elseif ($this->initial_invoice == "2") {
            $flagInvoice = 'false';
            $setInvoice = '"initial_invoice": ' . $flagInvoice . ',';
        }
        if ($this->paying_vat_all_order == "yes") {
            $payingVat = '"paying_vat": true,';
        }
        // Paying Vat & Invoices
        if ($this->paying_vat == "0") {
            $payingVat = '"paying_vat": true,';
        } else if ($this->paying_vat == "1") {
            $payingVat = '"paying_vat": false,';
        } else if ($this->paying_vat == "2") {
            if (trim(strtolower($customer_country_iso)) != trim(strtolower($this->paying_vat_iso_code))) {
                $payingVat = '"paying_vat": false,';
                if (!empty($this->foreign_invoices_lang)) {
                    $invoiceLanguage = '"invoice_language": "' . strtolower($this->foreign_invoices_lang) . '",';
                }
            } else {
                $payingVat = '"paying_vat": true,';
            }
        }
        if ($this->change_vat_in_eilat) {

            if ($this->payplus_check_is_vat_eilat($order_id)) {
                $payingVat = '"paying_vat": false,';
            }
        }

        $this->default_charge_method = ($this->default_charge_method) ?: 'credit-card';
        $this->default_charge_method = isset($options['chargeDefault']) ? $options['chargeDefault'] : $this->default_charge_method;

        $bSaveToken = true;

        $hideOtherChargeMethods = (isset($this->hide_other_charge_methods) && $this->hide_other_charge_methods === '1') ? 'true' : 'false';

        if (in_array($this->default_charge_method, CLUB_CARD)) {
            $hideOtherChargeMethods = 'false';
        }

        $hideOtherChargeMethods = isset($options['hideOtherPayments']) ? $options['hideOtherPayments'] : $hideOtherChargeMethods;
        $hideOtherChargeMethods = $this->default_charge_method === 'multipass' ? 'false' : $hideOtherChargeMethods;

        if (isset($options['isSubscriptionOrder']) && $options['isSubscriptionOrder']) {
            $hideOtherChargeMethods = 'true';
            $this->default_charge_method = 'credit-card';
        }

        $callback = $this->callback_addr ? $this->callback_addr : get_site_url(null, '/?wc-api=callback_response&_wpnonce=' . $this->_wpnonce);

        $post = $this->payplus_get_posts_id("", array("post_parent" => $order_id));
        $external_recurring_payment = "";
        if ($post && $post[0]->post_type === "shop_subscription") {
            $external_recurring_id = $post[0]->ID;
            $external_recurring_payment = '"external_recurring_payment":' . $this->getRecurring($external_recurring_id, $order_id) . ",";
        } elseif ($subscription) {

            $external_recurring_id = WC_PayPlus_Meta_Data::get_meta($order_id, '_subscription_renewal', true);
            $external_recurring_payment = '"external_recurring_payment":' . $this->getRecurring($external_recurring_id, $order_id) . ",";
        }
        $json_move_token = "";
        if ($move_token) {
            $json_move_token = ',"move_token": true';
        }

        $totalCartAmount = $objectProducts->amount;
        $secure3d = (isset($token) && $token !== null) ? '"secure3d": {"activate":false},' : "";

        $payload = '{
            "payment_page_uid": "' . $this->payment_page_id . '",
            ' . $addChargeLine . '
            "expiry_datetime": "30",
            "hide_other_charge_methods": ' . $hideOtherChargeMethods . ',
            "language_code": "' . trim(strtolower($langCode[0])) . '",
            "refURL_success": "' . $redriectSuccess . '&charge_method=' . $this->default_charge_method . '",
            "refURL_failure": "' . $this->response_error_url . '",
            "refURL_callback": "' . $callback . '",
            "charge_default":"' . $this->default_charge_method . '",
            ' . $payingVat . $customer
            . (!$this->send_products ? '
            "items": [
                ' . implode(",", $objectProducts->productsItems) . '
            ],' : '') . '
            ' . ($token ? '"token" : "' . (is_object($token) ? $token->get_token() : $token) . '",' : '') . '
            ' . $secure3d . '
            "amount": ' . ($this->send_products ? $totallCart : $totalCartAmount) . ',
            "currency_code": "' . $order->get_currency() . '",
            "sendEmailApproval": ' . ($this->sendEmailApproval == 1 ? 'true' : 'false') . ',
            "sendEmailFailure": ' . ($this->sendEmailFailure == 1 ? 'true' : 'false') . ',
            "create_token": ' . ($bSaveToken ? 'true' : 'false') . ',
            ' . $setInvoice . '
            ' . $invoiceLanguage
            . $external_recurring_payment
            . ($this->send_add_data ? '"add_data": "' . $order_id . '",' : '') . '
            ' . ($this->hide_payments_field > 0 ? '"hide_payments_field": ' . ($this->hide_payments_field == 1 ? 'true' : 'false') . ',' : '') . '
            ' . ($this->hide_identification_id > 0 ? '"hide_identification_id": ' . ($this->hide_identification_id == 1 ? 'true' : 'false') . ',' : '') . '
            "more_info": "' . ($custom_more_info ? $custom_more_info : $order_id) . '"' .
            $json_move_token . '}';
        $payloadArray = json_decode($payload, true);
        $payloadArray['more_info_4'] = PAYPLUS_VERSION;
        $payload = wp_json_encode($payloadArray);
        return $payload;
    }

    /**
     * @param $order_id
     * @param $check_payplus_generate_products_link
     * @return bool
     * @throws Exception
     */
    public function checkPayemntPageTime($order_id, $check_payplus_generate_products_link)
    {
        $date = new DateTime();
        $payplus_generate_products_link = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_generate_products_link', true);
        $dateLink = new DateTime(WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_time_link', true));
        $interval = $dateLink->diff($date);

        if ($interval->i < 30 && $interval->h == 0) {
            if ($payplus_generate_products_link === $check_payplus_generate_products_link) {
                return true;
            }
        }
        return false;
    }

    // receipt page - iframe or redirect

    /**
     * @param $order_id
     * @param $token
     * @param $subscription
     * @param $custom_more_info
     * @param $subscription_amount
     * @param $inline
     * @return false|mixed|void
     * @throws Exception
     */
    public function receipt_page($order_id, $token = null, $subscription = false, $custom_more_info = '', $subscription_amount = 0, $inline = false, $move_token = false)
    {
        $order = wc_get_order($order_id);
        $handle = 'payplus_process_payment';
        $handle .= ($subscription) ? '_subscription' : '';
        $date = new DateTime();
        $dateNow = $date->format('Y-m-d H:i');

        if ($token) {
            $this->payplus_add_log_all($handle, 'Token has been used order (' . $order_id . ')');
        }
        if ($subscription && !$token) {
            $this->payplus_add_log_all($handle, '--- Token is empty or invalid - exit ---', 'error');
            $this->payplus_add_log_all($handle, '', 'space');
            return false;
        }

        $payplus_payment_page_link = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_payment_page_link', true);
        $objectProducts = $this->payplus_get_products_by_order_id($order_id);
        $check_payplus_generate_products_link = $this->payplus_generate_products_link($objectProducts->productsItems, $order_id);

        // we need to add a fix here for if the payplus_payment_page_link exists we just do ipn call (custom-button-get-pp)
        if ($payplus_payment_page_link) {
            if ($this->checkPayemntPageTime($order_id, $check_payplus_generate_products_link)) {
                $this->get_payment_page($payplus_payment_page_link);
                return;
            }
        }
        $this->payplus_add_log_all($handle, 'New Payment Process Fired (' . $order_id . ')');

        $isSubscriptionOrder = false;
        if (is_checkout()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (get_class($cart_item['data']) === "WC_Product_Subscription") {
                    $isSubscriptionOrder = true;
                    break;
                }
            }
        }


        $options = $isSubscriptionOrder ? ['isSubscriptionOrder' => true] : [];
        $payload = $this->generatePayloadLink($order_id, false, $token, $subscription, $custom_more_info, $move_token, $options);

        $this->payplus_add_log_all($handle, 'Payload data before Sending to PayPlus');
        $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');
        $response = $this->post_payplus_ws($this->payment_url, $payload);

        $this->payplus_add_log_all($handle, 'WS PayPlus Response');
        if (is_wp_error($response)) {
            $this->payplus_add_log_all($handle, print_r($response, true), 'error');
        } else {
            $res = json_decode(wp_remote_retrieve_body($response));
            if (isset($res->data)) {
                try {
                    if (property_exists($res->data, 'page_request_uid')) {
                        $pageRequestUid = array('payplus_page_request_uid' => $res->data->page_request_uid);
                        WC_PayPlus_Meta_Data::update_meta($order, $pageRequestUid);
                    }
                } catch (Exception $e) {
                    // Translators: %s is the error message retrieved from the exception.
                    $error_message = sprintf(__('An error occurred: %s', 'text-domain'), $e->getMessage());
                    $wp_error = new WP_Error('payplus_update_meta_error', $error_message);
                    // Display or log the error as needed
                    wp_die(esc_html($wp_error));
                }
            } else {
                wc_add_notice(__('Error: The payment page failed to load - please check your page uid and domain settings.', 'payplus-payment-gateway'), 'error');

                return;
            }


            if ($token || $inline) {
                return $res;
            }
            $dataLink = $res->data;
            if (isset($dataLink->payment_page_link) && $this->validateUrl($dataLink->payment_page_link)) {
                $this->payplus_add_log_all($handle, print_r($res, true), 'completed');
                $this->payplus_add_log_all($handle, 'WS Redirecting to Page: ' . $dataLink->payment_page_link . "\n" . $this->payplus_get_space());
                $insertMeta = array(
                    'payplus_page_request_uid' => $dataLink->page_request_uid,
                    'payplus_payment_page_link' => $dataLink->payment_page_link,
                    'payplus_generate_products_link' => $check_payplus_generate_products_link,
                    'payplus_time_link' => $dateNow,
                );
                WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
                $this->get_payment_page($dataLink->payment_page_link);
            } else {
                $this->payplus_add_log_all($handle, print_r($response, true), 'error');
                echo esc_html__('Something went wrong with the payment page', 'payplus-payment-gateway') . '<hr /><b>Error:</b> ' . esc_html(print_r((is_array($response) ? $response['body'] : $response->body), true));
            }
        }
    }

    // get payment page - iframe or redirect

    /**
     * @param $res
     * @return void
     */
    public function get_payment_page($res)
    {
        if (!$this->display_mode || $this->display_mode == 'default') {
            $mainPluginOptions = get_option('woocommerce_payplus-payment-gateway_settings');
            $this->display_mode = ($mainPluginOptions['display_mode'] ?: 'redirect');
            $this->iframe_height = ($this->iframe_height ?: 600);
        }
        if ($this->display_mode == 'iframe') {
            echo "<form name='pp_iframe' target='payplus-iframe' method='GET' action='" . esc_url($res) . "'></form>";
            echo "<iframe  allowpaymentrequest id='pp_iframe' name='payplus-iframe' style='width: 100%; height: " . esc_attr($this->iframe_height) . "px; border: 0;'></iframe>";
            if ($this->import_applepay_script) {
                wp_enqueue_script(
                    'payplus-applepay-script',
                    'https://payments' . ($this->api_test_mode ? 'dev' : '') . '.payplus.co.il/statics/applePay/script.js',
                    array(),
                    PAYPLUS_VERSION,
                    true
                );
            }
        } else {
            echo "<form id='pp_iframe' name='pp_iframe' method='GET' action='" . esc_url($res) . "'></form>";
        }
        echo '<script type="text/javascript">  document.pp_iframe.submit()</script>';
    }

    /**
     * @param $url
     * @return bool
     */
    public function validateUrl($url = null)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $url
     * @param $payload
     * @param $method
     * @return array|WP_Error
     */
    public function post_payplus_ws($url, $payload = array(), $method = "post")
    {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : "";
        $args = array(
            'body' => $payload,
            'timeout' => '60',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'domain' => home_url(),
                'User-Agent' => "WordPress $userAgent",
                'Content-Type' => 'application/json',
                'Authorization' => '{"api_key":"' . $this->api_key . '","secret_key":"' . $this->secret_key . '"}',
            )
        );

        if ($method == "post") {
            $response = wp_remote_post($url, $args);
        } else {
            $response = wp_remote_get($url, $args);
        }

        return $response;
    }

    /**
     * @param $response
     * @return array
     */
    public function set_arrangement_callback($response)
    {

        $data['transaction_uid'] = $response['transaction']['uid'] ?? null;
        $data['voucher_id'] = $response['transaction']['voucher_number'] ?? null;
        $data['token_uid'] = $response['data']['card_information']['token'] ?? null;
        $data['type'] = $response['transaction_type'] ?? null;
        $data['order_id'] = $response['transaction']['more_info'] ?? null;
        $data['status_code'] = $response['transaction']['status_code'] ?? null;
        $data['number'] = $response['transaction']['number'] ?? null;
        $data['expiry_year'] = $response['data']['card_information']['expiry_year'] ?? null;
        $data['expiry_month'] = $response['data']['card_information']['expiry_month'] ?? null;
        $data['four_digits'] = $response['data']['card_information']['four_digits'] ?? null;
        $data['clearing_name'] = $response['data']['card_information']['clearing_id'] ?
            $this->payplus_get_clearing_companies($response['data']['card_information']['clearing_id']) : null;
        $issuer_id = $response['data']['card_information']['issuer_id'];
        $data['issuer_id'] = $issuer_id ? $issuer_id : null;
        $data['issuer_name'] = $issuer_id ? $this->payplus_get_issuers_companies($issuer_id) : null;
        $brand_id = $response['data']['card_information']['brand_id'];
        $data['brand_id'] = $brand_id ?? null;
        $data['brand_name'] = $brand_id ? $this->payplus_get_brands_list($brand_id) : null;
        $data['approval_num'] = $response['transaction']['approval_number'] ?? null;
        $data['credit_terms'] = $response['transaction']['credit_terms'] ?? null;
        $data['currency'] = $response['transaction']['currency'] ?? null;
        $data['number_of_payments'] = $response['transaction']['payments']['number_of_payments'] ?? null;
        $data['secure3D_tracking'] = $response['transaction']['secure3D']['tracking'] ?? null;
        $data['status'] = $response['transaction']['secure3D']['status'] ?? null;
        $data['voucher_num'] = $response['transaction']['voucher_number'] ?? null;
        $data['more_info'] = $response['transaction']['more_info'] ?? null;
        $data['alternative_method_name'] = $response['transaction']['alternative_method_name'] ?? null;
        $data['amount'] = $response['transaction']['amount'] ?? null;
        return $data;
    }

    /**
     * @param $userAgent
     * @return bool
     */
    public function get_check_user_agent($userAgent = 'PayPlus')
    {
        $handle = 'payplus_callback';
        if (isset($_SERVER['HTTP_USER_AGENT']) && sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) != $userAgent) {
            $this->payplus_add_log_all($handle, sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 'error');
            return true;
        }
        return false;
    }
    // check ipn response
    /**
     * @return false|void
     */
    public function callback_response_hash()
    {
        $json = file_get_contents('php://input');
        $payplusGenHash = base64_encode(hash_hmac('sha256', $json, $this->secret_key, true));
        die(esc_html($payplusGenHash));
    }
    /**
     * @return false|void
     */
    public function callback_response()
    {
        global $wpdb;
        $indexRow = 0;
        $json = file_get_contents('php://input');
        $response = json_decode($json, true);
        $payplusGenHash = base64_encode(hash_hmac('sha256', $json, $this->secret_key, true));
        $tblname = $wpdb->prefix . 'payplus_payment_process';
        $tblname = esc_sql($tblname);
        $handle = 'payplus_callback_begin';
        $payplusHash = isset($_SERVER['HTTP_HASH']) ? sanitize_text_field($_SERVER['HTTP_HASH']) : ""; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

        if ($payplusGenHash === $payplusHash) {
            $order_id = intval($response['transaction']['more_info']);
            $order = wc_get_order($order_id);
            $orderStatus = $order->get_status();
            $orderStatusNote = $orderStatus === 'processing' ? 'Order is on processing status! - callback will end.' : $orderStatus;
            $this->payplus_add_log_all(
                'payplus_callback_secured',
                "
                Time: $this->current_time
                Order: $order_id
                HTTP_HASH: $payplusHash
                PayPlus Generated Hash: $payplusGenHash
                Order Status: $orderStatusNote
                PayPlus Transaction Callback: $json
                "
            );
            if ($orderStatus === 'processing') {
                return;
            }

            $status_code = sanitize_text_field($response['transaction']['status_code']);
            $this->payplus_add_log_all(
                'payplus_callback_secured',
                "
                Callback continues: $order_id - doing database query now, status_code: $status_code
                "
            );

            $result = $wpdb->get_results($wpdb->prepare(
                "SELECT id as rowId, count(*) as rowCount, count_process, function_begin FROM {$wpdb->prefix}payplus_payment_process WHERE order_id = %d AND status_code = %s",
                $order_id,
                $status_code
            ));
            $result = $result[$indexRow];
            if (!$result->rowCount) {
                $wpdb->insert(
                    $tblname,
                    array(
                        'order_id' => $order_id,
                        'function_begin' => 'callback_response',
                        'status_code' => $status_code,
                        'count_process' => 1,
                    ),
                    array(
                        '%d', // order_id
                        '%s', // function_begin
                        '%s', // status_code
                        '%d', // count_process
                    )
                );
                $handle = 'payplus_callback_begin';
                $this->logOrderBegin($order_id, 'callback');
                $rowOrder = $this->invoice_api->payplus_get_payments($order_id);

                if ($this->get_check_user_agent() || (count($rowOrder) && $rowOrder[0]->status_code == $status_code)) {
                    $this->payplus_add_log_all($handle, 'payplus_end_proces-' . $order_id);
                    $this->payplus_add_log_all($handle, '', 'space');
                    return false;
                }

                if ($order) {
                    $dataInsert = array(
                        'order_id' => $order_id,
                        'status' => sanitize_text_field($order->get_status()),
                        'create_at_refURL_callback' => current_time('Y-m-d H:i:s'),
                    );
                    $handle = 'payplus_callback';
                    $data = $this->set_arrangement_callback($response);
                    $this->payplus_add_log_all($handle, 'Fired  (' . $order_id . ')');
                    $this->payplus_add_log_all($handle, 'more_info' . sanitize_text_field($data['order_id']));
                    $this->payplus_add_log_all($handle, print_r($response, true), 'before-payload');


                    $inData = array_merge($data, $response);
                    $this->payplus_add_log_all($handle, print_r($inData, true), 'completed');
                    $this->payplus_add_log_all($handle, 'more_info' . sanitize_text_field($inData['order_id']));
                    $page_request_uid = sanitize_text_field($inData['transaction']['payment_page_request_uid']);
                    $transaction_uid = sanitize_text_field($inData['transaction']['uid']);

                    if (!empty($page_request_uid)) {
                        $payload['payment_request_uid'] = $page_request_uid;
                    } elseif (!empty($transaction_uid)) {
                        $payload['transaction_uid'] = $transaction_uid;
                    } else {
                        $payload['more_info'] = $order_id;
                    }
                    $payload['related_transaction'] = true;
                    $payload = wp_json_encode($payload);
                    $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');
                    $this->requestPayPlusIpn($payload, $inData, 1, $handle);
                }
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
            }
        }
    }


    /**
     * @param $order_id
     * @param $order
     * @param $layout
     * @return void
     */
    public function logOrderBegin($order_id, $layout = 'ipn')
    {
        $handle = 'payplus_callback_begin';
        $order = wc_get_order($order_id);
        $textLog = "";
        if ($order) {
            $textLog = "status: " . $order->get_status() . " , ";
            $this->payplus_add_log_all($handle, 'New ' . $layout . ' Fired (' . $order_id . ')');
            $textLog .= "order_validated : " . WC_PayPlus_Meta_Data::get_meta($order_id, 'order_validated', true) . " , ";
            $textLog .= "status_code : " . WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_status_code', true) . " , ";
            $textLog .= "payplus_transaction_uid : " . WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_transaction_uid', true);
            $this->payplus_add_log_all($handle, $textLog);
            $this->payplus_add_log_all($handle, '', 'space');
        }
    }

    /**
     * @param $order_id
     * @param $order
     * @return bool
     */
    public function checkOrderBegin($order_id, $order)
    {
        if (($order && $order->get_status() != "pending")
            || WC_PayPlus_Meta_Data::get_meta($order_id, 'order_validated', true) == '1'
            || !empty(WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_transaction_uid', true))
        ) {
            return true;
        }
        return false;
    }

    /**
     * @param $order
     * @param $data
     * @return bool
     */
    public function checkOrderBeginData($order, $data)
    {
        $handle = 'payplus_process_payment';
        if (!isset($data['transaction_uid']) && !isset($data['order_id']) || $order === false) {
            $this->payplus_add_log_all($handle, 'IPN Error: missing Order ID, missing Voucher Number / Transaction UID or order not exists', 'error');
            return true;
        }
        return false;
    }

    /**
     * @param $data
     * @return bool|WC_Order|WC_Order_Refund|null
     */
    public function validateOrder($data)
    {

        $handle = 'payplus_process_payment';
        $order_id = trim($data['order_id']);
        $status_code = trim($data['status_code']);
        $order = wc_get_order($order_id);
        $rowOrder = $this->invoice_api->payplus_get_payments($order_id);
        if (count($rowOrder) && $rowOrder[0]->status_code == $status_code) {
            $this->payplus_add_log_all($handle, 'payplus_end_proces-' . $order_id);
            $this->payplus_add_log_all($handle, '', 'space');
            return false;
        }
        $dataInsert = array(
            'order_id' => $order_id,
            'status' => $order->get_status(),
            'create_at_refURL_success' => current_time('Y-m-d H:i:s'),
        );
        $this->logOrderBegin($order_id);
        if ($this->checkOrderBeginData($order_id, $data)) {
            return $order;
        }
        $transaction_uid = $data['transaction_uid'];
        $page_request_uid = $data['page_request_uid'];

        $titleMethod = $order->get_payment_method_title();
        $titleMethod = str_replace(array('<span>', "</span>"), '', $titleMethod);
        $order->set_payment_method_title($titleMethod);

        $this->payplus_add_log_all($handle, 'New  ipn  Fired (' . $order_id . ')');
        $this->payplus_add_log_all($handle, 'Result: ' . print_r($data, true));

        if ($data['type'] === 'Approval' && $data['status_code'] === '000') {
            WC_PayPlus_Meta_Data::sendMoreInfo($order, 'validateOrder->wc-on-hold', $transaction_uid);
            $order->update_status('wc-on-hold');
        } elseif ($data['type'] === 'Charge' && $data['status_code'] === '000') {
            if ($this->fire_completed && $this->successful_order_status === 'default-woo') {
                WC_PayPlus_Meta_Data::sendMoreInfo($order, 'validateOrder->firePaymentComplete', $transaction_uid);
                $order->payment_complete();
            } elseif ($this->successful_order_status !== 'default-woo') {
                WC_PayPlus_Meta_Data::sendMoreInfo($order, 'validateOrder->' . $this->successful_order_status, $transaction_uid);
                $order->update_status($this->successful_order_status);
            }
        }

        $payload = [];
        if (!empty($page_request_uid)) {
            $payload['payment_request_uid'] = $page_request_uid;
        } elseif (!empty($transaction_uid)) {
            $payload['transaction_uid'] = $transaction_uid;
        } else {
            $payload['more_info'] = $order_id;
        }
        $payload['related_transaction'] = true;

        $payload = wp_json_encode($payload);
        $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');

        $flag = $this->requestPayPlusIpn($payload, $data);
        if (!$flag) {
            $payplus_transaction_uid = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_transaction_uid', true);
            if (!$payplus_transaction_uid) {
                $flag = $this->requestPayPlusIpn($payload, $data, COUNT_PAY_PLUS);
            }
        }

        return $order;
    }

    /**
     * @param $order
     * @param $type
     * @param $res
     * @return bool
     */
    public function updateOrderStatus($order_id, $type, $res = null)
    {
        $indexRow = 0;
        $order = wc_get_order($order_id);
        if (isset($res->data->recurring_type)) {
            if ($this->recurring_order_set_to_paid == 'yes') {
                $order->payment_complete();
            }
            $order->update_status('wc-recsubc');
            $order->save();
            return false;
        } else {
            if ($type == "Charge") {
                if ($this->fire_completed) {
                    $order->payment_complete();
                }
                $order = wc_get_order($order_id);
                if ($this->successful_order_status !== 'default-woo' && $order->get_status() != $this->successful_order_status) {
                    $order->update_status($this->successful_order_status);
                    $order->save();
                }
            } else {
                $order->update_status('wc-on-hold');
                $order->save();
            }

            $data = array('status' => $order->get_status(), 'update_at' => current_time('Y-m-d H:i:s'));
            $where = array('order_id' => $order_id);
            return $order;
        }
    }
    public function getOrderPayplus($order_id)
    {
        global $wpdb;
        $tblname = $wpdb->prefix . PAYPLUS_TABLE_PROCESS;
        $tblname = esc_sql($tblname);
        if (payplus_check_table_exist_db($tblname)) {

            $order_id = intval($order_id);

            $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}payplus_payment_process WHERE order_id = %d", $order_id));

            if ($wpdb->last_error) {
                payplus_Add_log_payplus($wpdb->last_error);
            }

            if ($result) {
                return $result[0];
            }
        }
    }

    public function updateOrderPayplus($order_id, $functionCurrent)
    {
        global $wpdb;
        $order_id = intval($order_id);
        $tblname = esc_sql($wpdb->prefix . PAYPLUS_TABLE_PROCESS);
        if (payplus_check_table_exist_db($tblname)) {
            $result = $wpdb->update(
                $tblname,
                array(
                    'function_end' => sanitize_text_field($functionCurrent),
                ),
                array('order_id' => $order_id)
            );
            if ($wpdb->last_error) {
                payplus_Add_log_payplus($wpdb->last_error);
            }
        }
    }

    /**
     * @param $payload
     * @param $data
     * @param $countLoop
     * @return bool
     */
    public function requestPayPlusIpn($payload, $data, $countLoop = 1, $handle = 'payplus_process_payment', $inline = false)
    {
        $order_id = isset($data['order_id']) ? trim($data['order_id']) : '';
        $flagPayplus = true;
        $flagProcess = true;
        $handleLog = ($handle == "payplus_process_payment") ? 'Payment' : 'Callback';
        $transaction_uid = isset($data['transaction_uid']) ? $data['transaction_uid'] : '';
        $token_uid = isset($data['token_uid']) ? $data['token_uid'] : '';
        $type = isset($data['type']) ? $data['type'] : '';
        $userID = 0;
        $order = wc_get_order($order_id);

        // Get customer ID
        $customerId = $order->get_user_id();

        WC_PayPlus_Meta_Data::update_meta($order, array('payplus_function_end' => $handleLog));
        $this->updateOrderPayplus($order_id, $handleLog);

        $createToken = false;
        if ($order) {
            if ($order->get_user_id()) {
                $createToken = WC_PayPlus_Meta_Data::get_meta($order_id, 'save_payment_method');
                $userID = $order->get_user_id();
            }
            $insertMeta = array();
            for ($i = 0; $i < $countLoop; $i++) {
                $response = $this->post_payplus_ws($this->ipn_url, $payload);

                if (is_wp_error($response)) {
                    $error = $response->get_error_message();
                    $this->payplus_add_log_all($handle, print_r($error, true), 'error');
                    $html = '<div style="font-weight:600;border-bottom: 1px solid #000;padding: 5px 0px">
                    PayPlus Error <br>  ' . $error . '        </div>';
                    $order->add_order_note($html);
                } else {

                    $res = json_decode(wp_remote_retrieve_body($response));
                    $orderPayplus = $this->getOrderPayplus($order_id);
                    $payplus_function_end = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_function_end', true);
                    if ($payplus_function_end && $payplus_function_end != $handleLog || $orderPayplus->function_end != $handleLog) {
                        $flagPayplus = false;
                        $flagProcess = false;
                        break;
                    }
                    if ($inline) {
                        return $res;
                    }

                    if ($res->results->status == "error" || $res->results->status == "rejected") {

                        $this->payplus_add_log_all($handle, 'Error IPN Error: ' . print_r($res, true), 'error');
                        $this->store_payment_ip();
                        if ($this->failure_order_status !== 'default-woo') {
                            $order->update_status($this->failure_order_status);
                        }
                        // Translators: %s will be replaced with the transaction UID received from the payment gateway.
                        $order->add_order_note(sprintf(__('PayPlus IPN Failed<br/>Transaction UID: %s', 'payplus-payment-gateway'), $transaction_uid));
                        break;
                    } else {
                        $inData = array_merge($data, (array) $res->data);
                        if (property_exists($res->data, 'related_transactions')) {
                            $insertMeta['payplus_related_transactions'] = 1;
                            WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
                        }
                        $rowOrder = $this->invoice_api->payplus_get_payments($order_id);

                        if (!count($rowOrder)) {
                            $this->payplus_add_order($order_id, $inData);
                        }
                        $this->payplus_add_log_all($handle, 'status:' . $res->data->status_code);
                        if ($res->data->status_code === '000') {

                            $this->logOrderBegin($order_id, __FUNCTION__ . ':start');
                            $this->updateMetaData($order_id, $inData);
                            $returnStatus = $this->updateOrderStatus($order_id, $type, $res);
                            $this->logOrderBegin($order_id, __FUNCTION__ . ':end');

                            $this->payplus_add_log_all($handle, print_r($res, true), 'completed');
                            if ($this->add_product_field_transaction_type) {
                                if ($this->payplus_check_all_product($order, "2")) {
                                    $insertMeta['payplus_transaction_type'] = "2";
                                } elseif ($this->payplus_check_all_product($order, "1")) {
                                    $insertMeta['payplus_transaction_type'] = "1";
                                }
                            }
                            if ($this->create_pp_token && $token_uid && $userID && $createToken) {
                                $this->save_token($data, $userID);
                            }
                            if ($userID > 0) {
                                update_user_meta($userID, 'cc_token', $data['token_uid']);
                            }

                            if (empty(WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_print_log', true))) {
                                WC_PayPlus_Meta_Data::update_meta($order, array('payplus_print_log' => 1));
                                if (property_exists($res->data, 'related_transactions')) {

                                    $html = '<div style="font-weight:600;">PayPlus Related Transaction';
                                    $relatedTransactions = $res->data->related_transactions;
                                    for ($i = 0; $i < count($relatedTransactions); $i++) {
                                        $relatedTransaction = $relatedTransactions[$i];
                                        if (property_exists($relatedTransaction, 'alternative_method_name')) {
                                            if (
                                                $relatedTransaction->alternative_method_name == "multipass"
                                                || $relatedTransaction->alternative_method_name == "valuecard"
                                                || $relatedTransaction->alternative_method_name == "finitione"
                                                || $relatedTransaction->alternative_method_name == "tav-zahav"
                                            ) {
                                                $method = $relatedTransaction->alternative_method_name;
                                                $alternative_method_name = $relatedTransaction->alternative_method_name;
                                            } else {
                                                $method = "credit-card";
                                                $alternative_method_name = $method;
                                            }
                                        } else {
                                            $method = "credit-card";
                                            $alternative_method_name = $method;
                                        }

                                        $transactionUid = WC_PayPlus_Meta_Data::get_meta($order_id, "payplus_transaction_uid_" . $method, true);
                                        if (!empty($transactionUid)) {
                                            $transactionUid = $transactionUid . "|" . $relatedTransaction->transaction_uid;
                                            $insertMeta["payplus_transaction_uid_" . $method] = $transactionUid;
                                        } else {
                                            $transactionUid = $relatedTransaction->transaction_uid;
                                            $insertMeta["payplus_transaction_uid_" . $method] = $transactionUid;
                                        }
                                        if ($alternative_method_name == "credit-card") {

                                            $html .= sprintf(
                                                '<div style="font-weight:600;border-bottom: 1px solid #000;padding: 5px 0px">PayPlus ' . (($type == "Approval" || $type == "Check") ? 'Pre-Authorization' : $handleLog) . ' Successful
                                                    <table style="border-collapse:collapse">
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Last digits</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Expiry date</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Voucher ID</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                    <tr><td style="vertical-align:top;">Token</td><td style="vertical-align:top;"><a style="font-weight: bold;color:#000" class="copytoken" href="#"> %s</a></td></tr>
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Total</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                    </table></div>
                                                ',
                                                $relatedTransaction->number,
                                                $relatedTransaction->four_digits,
                                                $relatedTransaction->expiry_month . $relatedTransaction->expiry_year,
                                                $relatedTransaction->voucher_id,
                                                $relatedTransaction->token_uid,
                                                $relatedTransaction->amount

                                            );
                                        } else {
                                            $html .= sprintf(
                                                '<div class="row" style="font-weight:600;border-bottom: 1px solid #000;padding: 5px 0px">PayPlus  Successful ' . $alternative_method_name . '
                                                    <table style="border-collapse:collapse">
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Total</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                    </table></div>
                                                ',
                                                $relatedTransaction->number,
                                                $relatedTransaction->amount
                                            );
                                        }
                                    }
                                    $html .= "</div>";
                                    if ($this->saveOrderNote) {
                                        $order->add_order_note($html);
                                    }
                                } else {
                                    if ($res->data->method !== "credit-card") {
                                        if (!empty($res->data->alternative_method_name)) {
                                            $alternative_method_name = $res->data->alternative_method_name;
                                            if ($res->data->alternative_method_name === "multipass") {
                                                $method = $res->data->alternative_method_name;
                                            } else {
                                                $method = "credit-card";
                                            }
                                            $transactionUid = $res->data->transaction_uid;
                                            $insertMeta["payplus_transaction_uid_" . $method] = $transactionUid;
                                        }
                                        if ($this->saveOrderNote) {
                                            $order->add_order_note(sprintf(
                                                '<div class="row" style="font-weight:600;border-bottom: 1px solid #000;padding: 5px 0px">PayPlus  Successful ' . $alternative_method_name . '
                                                    <table style="border-collapse:collapse">
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Total</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                    </table></div>
                                                ',
                                                $res->data->number,
                                                $res->data->amount
                                            ));
                                        }
                                    } else {
                                        if ($this->saveOrderNote) {
                                            $order->add_order_note(sprintf(
                                                '<div style="font-weight:600;">PayPlus ' . (($type == "Approval" || $type == "Check") ? 'Pre-Authorization' : $handleLog) . ' Successful</div>
                                                    <table style="border-collapse:collapse">
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Last digits</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Expiry date</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Voucher ID</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                                        <tr><td style="vertical-align:top;">Token</td><td style="vertical-align:top;"><a style="font-weight: bold;color:#000" class="copytoken" href="#"> %s</a></td></tr>
                                                        <tr><td style="vertical-align:top;">Total</td><td style="vertical-align:top;">%s</td></tr>
                                                    </table>
                                                ',
                                                $res->data->number,
                                                $res->data->four_digits,
                                                $res->data->expiry_month . $res->data->expiry_year,
                                                $res->data->voucher_num,
                                                $res->data->token_uid,
                                                $res->data->amount
                                            ));
                                        }
                                    }
                                }
                            }

                            $flagPayplus = false;
                            break;
                        }
                    }
                }
                sleep(1);
            }
            WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
        }
        if ($flagPayplus) {
            if ($this->failure_order_status !== 'default-woo') {
                $order->update_status($this->failure_order_status);
            }
            $order->add_order_note(__('PayPlus payment failed', 'payplus-payment-gateway'));
            $insertMeta = array('order_validated_error' => '1');
            WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
        } else {
            if ($flagProcess) {
                $insertMeta = array('order_validated' => '1');
                WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
                delete_post_meta($order_id, 'order_validated_error');
            }
        }
        return !$flagPayplus;
    }

    /**
     * @param $relatedTransactions
     * @return array
     */
    public function arrangementRelatedTransactions($order, $relatedTransactions)
    {

        $tempArrrelatedTransactions = array();
        $insertMeta = array();
        for ($i = 0; $i < count($relatedTransactions); $i++) {
            $relatedTransactionsOther = $relatedTransactions[$i];
            if (property_exists($relatedTransactionsOther, 'alternative_method_name')) {
                $alternative_method_name = $relatedTransactionsOther->alternative_method_name;
                if (
                    $alternative_method_name == "multipass"
                    || $alternative_method_name == "bit"
                    || $alternative_method_name == "tav-zahav"
                    || $alternative_method_name == "valuecard"
                    || $alternative_method_name == "finitione"
                ) {
                    if (!array_key_exists($relatedTransactionsOther->alternative_method_name, $tempArrrelatedTransactions)) {
                        $tempArrrelatedTransactions[$alternative_method_name] = floatval($relatedTransactionsOther->amount);
                    } else {
                        $tempArrrelatedTransactions[$alternative_method_name] .= "|" . floatval($relatedTransactionsOther->amount);
                    }
                    if ($alternative_method_name == 'valuecard' || $alternative_method_name == 'finitione') {
                        $insertMeta['payplus_four_digits_' . $alternative_method_name] = $relatedTransactionsOther->four_digits;
                    }
                } else {
                    $tempArrrelatedTransactions['credit-card'] = floatval($relatedTransactionsOther->amount);
                    $insertMeta['payplus_four_digits'] = $relatedTransactionsOther->four_digits;
                }
            } else {
                $tempArrrelatedTransactions['credit-card'] = floatval($relatedTransactionsOther->amount);
                $insertMeta['payplus_four_digits'] = $relatedTransactionsOther->four_digits;
            }
        }
        WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
        return $tempArrrelatedTransactions;
    }

    /**
     * @param int $order_id
     * @param $response
     * @return void
     */
    public function updateMetaData($order_id, $response)
    {

        $order = wc_get_order($order_id);
        $insertMeta = array();
        $appVars = array(
            'type',
            'method',
            'number',
            'status',
            'status_code',
            'status_description',
            'currency',
            'four_digits',
            'expiry_month',
            'expiry_year',
            'number_of_payments',
            'first_payment_amount',
            'rest_payments_amount',
            'voucher_id',
            'voucher_num',
            'approval_num',
            'transaction_uid',
            'token_uid',
            'more_info',
            'alternative_method_id',
            'add_data',
            'customer_name',
            'identification_number',
            'brand_name',
            'clearing_name',
            'alternative_method_name',
            'credit_terms',
            'secure3D_tracking',
            'issuer_id',
            'issuer_name'
        );

        if (!empty($response['related_transactions'])) {
            $relatedTransactions = $response['related_transactions'];

            $relatedTransactions = $this->arrangementRelatedTransactions($order, $relatedTransactions);
            if (count($relatedTransactions)) {
                foreach ($relatedTransactions as $key => $value) {
                    $insertMeta['payplus_' . $key] = wc_clean($value);
                }
            }
        } else {

            $flagMethod = true;
            $method = (isset($response['method'])) ? $response['method'] : 'credit-card';
            if (!empty($response['alternative_method_name'])) {
                if (
                    $response['alternative_method_name'] == "bit"
                    || $response['alternative_method_name'] == "multipass" ||
                    $response['alternative_method_name'] == "paypal" ||
                    $response['alternative_method_name'] == "tav-zahav" ||
                    $response['alternative_method_name'] == "valuecard"

                ) {
                    $method = $response['alternative_method_name'];
                } else {
                    if ((isset($response['alternative_method_name'])) && ($response['alternative_method_name'] == "google-pay"
                            || $response['alternative_method_name'] == "apple-pay") &&

                        $this->invoice_api->payplus_get_invoice_enable()
                    ) {
                        $flagMethod = false;
                    }
                    $method = "credit-card";
                }
                $insertMeta['payplus_' . $response['alternative_method_name']] = wc_clean($response['amount']);
            }
            if ($flagMethod) {
                $insertMeta['payplus_' . $method] = wc_clean($response['amount']);
            }
        }
        for ($i = 0; $i < count($appVars); $i++) {
            unset($value);
            if (is_object($response)) {
                if (isset($response->data->{$appVars[$i]})) {
                    $value = $response->data->{$appVars[$i]};
                } else {
                    continue;
                }
            } else {
                if (isset($response[$appVars[$i]])) {
                    $value = $response[$appVars[$i]];
                } else {
                    continue;
                }
            }
            $insertMeta['payplus_' . $appVars[$i]] = wc_clean($value);
        }
        $insertMeta['payplus_refunded'] = $order->get_total();
        $insertMeta['payplus_response'] = wp_json_encode($response, true);
        WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
    }

    // save token to user

    /**
     * @param $res
     * @param $user_id
     * @return void
     */
    public function save_token($res, $user_id = false)
    {

        $customerTokens = WC_Payment_Tokens::get_customer_tokens($user_id);
        $theTokens = [];

        foreach ($customerTokens as $customerToken) {
            $theTokens[] = $customerToken->get_token();
        };

        if (!in_array($res['token_uid'], $theTokens)) {
            $this->payplus_add_log_all('payplus_save_tokens', 'Saving Token');

            $token_num = wc_clean($res['token_uid']);
            $brand = $this->payplus_get_brands_list($res['brand_id']);
            $card_type = trim(wc_clean($brand));
            $last_four = wc_clean($res['four_digits']);
            $exp_month = wc_clean($res['expiry_month']);
            $exp_year = '20' . wc_clean($res['expiry_year']);

            $token = new WC_Payment_Token_CC();
            $token->set_token($token_num);
            $token->set_gateway_id($this->id);
            $token->set_card_type($card_type);
            $token->set_last4($last_four);
            $token->set_expiry_month($exp_month);
            $token->set_expiry_year($exp_year);
            $token->set_default(true);
            $token->set_user_id($user_id ?: get_current_user_id());
            $token->save();

            $this->payplus_add_log_all('payplus_save_tokens', 'Saved And Finished: ' . sanitize_text_field($token));
        }
    }

    // add credit card to my account

    /**
     * @return array|void
     */
    public function add_payment_method()
    {
        $handle = 'payplus_add_payment_method';
        $this->payplus_add_log_all($handle, 'New Add Payment Method Fired');

        $current_user = wp_get_current_user();

        $customer = new WC_Customer($current_user->ID);

        if ($this->exist_company && !empty($customer->get_billing_company())) {
            $customerBilling['customer_name'] = $customer->get_billing_company();
        } else {
            if (!empty($customer->get_billing_first_name()) || !empty($customer->get_billing_last_name())) {
                $customerBilling['customer_name'] = $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name();
            }
            if (!$customerBilling['customer_name']) {
                $customerBilling['customer_name'] = $customer->get_billing_company();
            } elseif ($customer->get_billing_company()) {
                $customerBilling['customer_name'] .= " (" . $customer->get_billing_company() . ")";
            }
        }

        $customerBilling['email'] = $customer->get_billing_email();
        $customerBilling['phone'] = str_replace(["'", '"', "\\"], '', $customer->get_billing_phone());
        $customerBilling['address'] = trim(str_replace(["'", '"', "\\"], '', $customer->get_billing_address_1() . ' ' . $customer->get_billing_address_2()));
        $customerBilling['city'] = str_replace(["'", '"', "\\"], '', $customer->get_billing_city());
        $customerBilling['postal_code'] = str_replace(["'", '"', "\\"], '', $customer->get_billing_postcode());
        $customerBilling['country_iso'] = $customer->get_billing_country();
        $customerBilling['customer_external_number'] = $current_user->ID;

        $customerData = wp_json_encode($customerBilling);

        $langCode = explode("_", get_locale());
        $payload = '{
            "payment_page_uid": "' . $this->payment_page_id . '",
            "charge_method": 5,
            "language_code": "' . $langCode[0] . '",
            "expiry_datetime": "30",
            "refURL_success": "' . $this->add_payment_res_url . '",
            "refURL_failure": "' . wc_get_endpoint_url('add-payment-method') . '",
            "refURL_callback": null,
            "customer": ' . $customerData . ',
            "amount": ' . wp_rand(1, 9) . ',
            "currency_code": "' . get_woocommerce_currency() . '",
            "sendEmailApproval": false,
            "sendEmailFailure": false,
            "create_token": true,
            "more_info": "wp_token_' . get_current_user_id() . '"

        }';

        $this->payplus_add_log_all($handle, 'All data collected before Sending to PayPlus');
        $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');

        $response = $this->post_payplus_ws($this->payment_url, $payload);

        if (is_wp_error($response)) {
            $this->payplus_add_log_all($handle, print_r($response, true), 'error');
            echo esc_html__('Something went wrong with the payment page', 'payplus-payment-gateway') . '<hr /><b>Error:</b> ' . esc_html(print_r(($response), true));
        } else {
            $res = json_decode(wp_remote_retrieve_body($response));
            if (isset($res->data->payment_page_link) && $this->validateUrl($res->data->payment_page_link)) {
                $this->payplus_add_log_all($handle, print_r($res, true), 'completed');
                $this->get_payment_page($res->data->payment_page_link);
            } else {
                $this->payplus_add_log_all($handle, print_r($res, true), 'error');
                echo esc_html__('Something went wrong with the payment page', 'payplus-payment-gateway') . '<hr /><b>Error:</b> ' . esc_html(print_r((is_array($response) ? $response['body'] : $response->body), true));
            }
        }
    }
    // check add payment ipn response

    /**
     * @return void
     */
    public function add_payment_ipn_response()
    {
        if (!wp_verify_nonce($this->_wpnonce, 'PayPlusGateWayNonce')) {
            wp_die('Not allowed! - add_payment_ipn_response');
        }
        $handle = 'payplus_add_payment_ipn';
        $this->payplus_add_log_all($handle, 'New Token Has Been Generated');

        if (isset($_REQUEST['type']) && sanitize_text_field(wp_unslash($_REQUEST['type'])) != "token" || isset($_REQUEST['method']) && sanitize_text_field(wp_unslash($_REQUEST['method'])) != "credit-card") {
            $this->payplus_add_log_all($handle, 'WS Error, No Token Type or Credit Card Method In Response', 'error');
            return;
        }

        if (isset($_REQUEST['status']) && sanitize_text_field(wp_unslash(($_REQUEST['status']))) == "approved" && isset($_REQUEST['status_code']) && sanitize_text_field(wp_unslash($_REQUEST['status_code'])) == "000" && isset($_REQUEST['token_uid']) && sanitize_text_field(wp_unslash($_REQUEST['token_uid']))) {
            wc_add_notice(__('Your new payment method has been added', 'payplus-payment-gateway'));
            $this->payplus_add_log_all($handle, print_r($this->arr_clean($_REQUEST), 'completed'));
            $user_id = get_current_user_id();
            if (isset($_REQUEST['more_info'])) {
                $user_id = explode("_", sanitize_text_field(wp_unslash($_REQUEST['more_info'])));
                $user_id = $user_id[2];
            }
            update_user_meta($user_id, 'cc_token', sanitize_text_field(wp_unslash($_REQUEST['token_uid'])));
            $this->save_token($_REQUEST, $user_id);
        } else {
            wc_add_notice(__('There was a problem adding this card', 'payplus-payment-gateway'), 'error');
            $this->payplus_add_log_all($handle, 'IPN Error: There was a problem adding this card', 'error');
        }
        wp_redirect(wc_get_endpoint_url('payment-methods', '', wc_get_page_permalink('myaccount')));
        exit;
    }

    //subscription

    /**
     * @param $amount_to_charge
     * @param $order
     * @return array|void
     * @throws Exception
     */
    public function scheduled_subscription_payment($amount_to_charge, $order, $move_token = false)
    {

        $handle = 'payplus_process_payment_subscription';
        if ($order) {
            $insertMeta = array();
            $payplus_status_active = WC_PayPlus_Meta_Data::get_meta($order->get_id(), 'payplus_status_active', true);

            if (empty($payplus_status_active)) {
                $token = get_user_meta($order->user_id, 'cc_token', true);
                $this->payplus_add_log_all($handle, 'Subscription Started. Order ID:( ' . $order->get_id() . ' )- Token: ' . $token);

                $result = $this->receipt_page($order->get_id(), $token, true, 'WP_SUB_' . $order->get_id(), $amount_to_charge, false, $move_token);

                if ($result->data->status == "approved" && $result->data->status_code == "000" && $result->data->transaction_uid) {
                    $this->payplus_add_log_all($handle, print_r($result, true), 'completed');
                    $external_recurring_id = WC_PayPlus_Meta_Data::get_meta($order->get_id(), '_subscription_renewal', true);
                    $post = $this->payplus_get_posts_id($external_recurring_id);
                    if ($post && $post[0]->post_parent) {
                        $post_parent = $post[0]->post_parent;

                        $payplusFourDigits = WC_PayPlus_Meta_Data::get_meta($post_parent, "payplus_four_digits", true);
                        $payplusBrandName = WC_PayPlus_Meta_Data::get_meta($post_parent, "payplus_brand_name", true);
                        $payplusNumberOfPayments = WC_PayPlus_Meta_Data::get_meta($post_parent, "payplus_number_of_payments", true);
                        $insertMeta['payplus_credit-card'] = $amount_to_charge;
                        $insertMeta['payplus_four_digits'] = $payplusFourDigits;
                        $insertMeta['payplus_brand_name'] = $payplusBrandName;
                        $insertMeta['payplus_number_of_payments'] = $payplusNumberOfPayments;
                    }
                    // Translators: %s will be replaced with the transaction number received from the payment gateway.
                    $order->add_order_note(sprintf(__('PayPlus Subscription Payment Successful<br/>Transaction Number: %s', 'payplus-payment-gateway'), $result->data->number));
                    $insertMeta['payplus_type'] = $result->data->type;
                    $insertMeta['payplus_transaction_uid'] = $result->data->transaction_uid;
                    $insertMeta['payplus_status_active'] = 1;
                    delete_post_meta($order->get_id(), 'payplus_error_sub');
                    if ($this->recurring_order_set_to_paid === "yes") {
                        $order->payment_complete();
                    } else if ($this->successful_order_status !== 'default-woo') {
                        $order->update_status($this->successful_order_status);
                    } else {
                        $order->update_status('wc-processing');
                    }
                    WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
                    return ["success" => true, "msg" => ""];
                } else {
                    $this->payplus_add_log_all($handle, print_r($result, true), 'error');
                    if (
                        property_exists($result, 'results')
                        && property_exists($result->results, 'status')
                        && $result->results->status === "error"
                    ) {
                        $payplus_error_sub = WC_PayPlus_Meta_Data::get_meta($order->get_id(), 'payplus_error_sub', true);
                        if ($result->results->description == 'can-not-find-card' && empty($payplus_error_sub)) {
                            $insertMeta['payplus_error_sub'] = 1;
                            WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
                            $this->scheduled_subscription_payment($amount_to_charge, $order, true);
                        } else {
                            // Translators: %s will be replaced with the error description received from the payment gateway.
                            $order->add_order_note(sprintf(__('PayPlus Subscription Payment Failure<br/>error : %s', 'payplus-payment-gateway'), $result->results->description));
                            delete_post_meta($order->get_id(), 'payplus_error_sub');
                        }
                        return ["success" => false, "msg" => "Authorization error: " . $result->results->code];
                    } else {
                        // Translators: %s will be replaced with the error description received from the payment gateway.
                        $order->add_order_note(sprintf(__('PayPlus Subscription Payment Failure<br/>error : %s', 'payplus-payment-gateway'), $result->data->status_description));
                    }
                    return ["success" => false, "msg" => "Authorization error: "];
                }
            }
        }
    }

    /**
     * @param $req
     * @return array|array[]|string[]
     */
    public function arr_clean($req = [])
    {
        $REQUEST = array_map(function ($v) {
            return wc_clean($v);
        }, $req);
        return $REQUEST;
    }

    // /**
    //  * @return string|void
    //  */
    // public function payplus_ip()
    // {

    //     foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {

    //         if (array_key_exists($key, $_SERVER) === true) {

    //             foreach (explode(',', $_SERVER[$key]) as $ip) {

    //                 if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {

    //                     return $ip;
    //                 }
    //             }
    //         }
    //     }
    // }

    /**
     * @param $handle
     * @param $msg
     * @param $keyLog
     * @return void
     */
    public function payplus_add_log_all($handle, $msg, $keyLog = 'default')
    {
        $beforeMsg = 'Plugin Version: ' . PAYPLUS_VERSION . ' - ';
        switch ($keyLog) {
            case 'default':
                $this->logging->log(PAYPLUS_LOG_INFO_LEVEL, $beforeMsg . $msg, array('source' => $handle));
                break;
            case 'payload':
                $this->logging->log(PAYPLUS_LOG_INFO_LEVEL, $beforeMsg . 'WP Remote Post payload ' . $msg, array('source' => $handle));
                break;
            case 'before-payload':
                $this->logging->log(PAYPLUS_LOG_INFO_LEVEL, $beforeMsg . 'WP Remote Post  Before payload   ' . $msg, array('source' => $handle));
                break;

            case 'error':
                $this->logging->log(PAYPLUS_LOG_INFO_LEVEL, $beforeMsg . 'WP Remote Post Error ' . $msg . "\n" . $this->payplus_get_space(), array('source' => $handle));
                break;
            case 'completed':
                $this->logging->log(PAYPLUS_LOG_INFO_LEVEL, $beforeMsg . 'WP Remote Post Completed ' . $msg . "\n" . $this->payplus_get_space(), array('source' => $handle));
                break;
            case 'space':
                $this->logging->log(PAYPLUS_LOG_INFO_LEVEL, $this->payplus_get_space(), array('source' => $handle));
                break;
        }
    }

    /**
     * @param $order_id
     * @param $dataRow
     * @return void
     */
    public function payplus_add_order_express_checkout($order_id, $dataRow)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'payplus_order';

        $order_id = absint($order_id);
        $transaction_uid = sanitize_text_field($dataRow['transaction']->uid);
        $four_digits = (!empty($dataRow['data']->card_information->four_digits)) ? sanitize_text_field($dataRow['data']->card_information->four_digits) : '';
        $number_of_payments = (!empty($dataRow['transaction']->payments->number_of_payments)) ? absint($dataRow['transaction']->payments->number_of_payments) : 0;
        $alternative_method_name = (!empty($dataRow['transaction']->alternative_method_name)) ? sanitize_text_field($dataRow['transaction']->alternative_method_name) : '';
        $type_payment = sanitize_text_field($dataRow['transaction']->type);
        $token_uid = (!empty($dataRow['data']->card_information->token)) ? sanitize_text_field($dataRow['data']->card_information->token) : '';
        $price = floatval($dataRow['transaction']->amount) * 100;
        $payplus_response = wp_json_encode($dataRow);
        $status_code = sanitize_text_field($dataRow['transaction']->status_code);

        $data = array(
            'order_id' => $order_id,
            'parent_id' => 0,
            'transaction_uid' => $transaction_uid,
            'method_payment' => 'credit-card',
            'page_request_uid' => '',
            'four_digits' => $four_digits,
            'number_of_payments' => $number_of_payments,
            'brand_name' => '',
            'approval_num' => '',
            'alternative_method_name' => $alternative_method_name,
            'type_payment' => $type_payment,
            'token_uid' => $token_uid,
            'price' => $price,
            'payplus_response' => $payplus_response,
            'related_transactions' => 0,
            'status_code' => $status_code,
            'delete_at' => 0,
        );

        $format = array(
            '%d',   // order_id
            '%d',   // parent_id
            '%s',   // transaction_uid
            '%s',   // method_payment
            '%s',   // page_request_uid
            '%s',   // four_digits
            '%d',   // number_of_payments
            '%s',   // brand_name
            '%s',   // approval_num
            '%s',   // alternative_method_name
            '%s',   // type_payment
            '%s',   // token_uid
            '%f',   // price
            '%s',   // payplus_response
            '%d',   // related_transactions
            '%s',   // status_code
            '%d',   // delete_at
        );

        $wpdb->insert(
            $table,
            $data,
            $format
        );
    }




    /**
     * @param $order_id
     * @param $dataRow
     * @return void
     */
    public function payplus_add_order($order_id, $dataRow)
    {
        global $wpdb;
        if (!WC_PayPlus::payplus_check_exists_table($this->_wpnonce)) {
            $is_multiple_transaction = $dataRow['is_multiple_transaction'];
            $parent_id = 0;
            $table = $wpdb->prefix . 'payplus_order';
            $rowOrder = $this->invoice_api->payplus_get_payments($order_id);

            if (count($rowOrder)) {
                $wpdb->update($table, array('delete_at' => 1), array('order_id' => $order_id));
            }

            if (!empty($dataRow['alternative_method_name']) && in_array($dataRow['alternative_method_name'], array('google-pay', 'apple-pay'))) {
                $dataRow['method'] = $dataRow['alternative_method_name'];
            }
            /* parent payment */
            $data = array(
                'order_id' => intval($order_id),
                'parent_id' => intval($parent_id),
                'transaction_uid' => sanitize_text_field($dataRow['transaction_uid']),
                'method_payment' => ($is_multiple_transaction) ? "" : sanitize_text_field(strtolower($dataRow['method'])),
                'page_request_uid' => sanitize_text_field(strtolower($dataRow['page_request_uid'])),
                'four_digits' => (!empty($dataRow['four_digits'])) ? sanitize_text_field(strtolower($dataRow['four_digits'])) : '',
                'number_of_payments' => (!empty($dataRow['number_of_payments'])) ? intval($dataRow['number_of_payments']) : 0,
                'brand_name' => (!empty($dataRow['brand_name'])) ? sanitize_text_field($dataRow['brand_name']) : '',
                'approval_num' => (!empty($dataRow['approval_num'])) ? sanitize_text_field($dataRow['approval_num']) : '',
                'alternative_method_name' => (!empty($dataRow['alternative_method_name'])) ? sanitize_text_field($dataRow['alternative_method_name']) : '',
                'type_payment' => sanitize_text_field($dataRow['type']),
                'token_uid' => (!empty($dataRow['token_uid'])) ? sanitize_text_field($dataRow['token_uid']) : '',
                'price' => floatval($dataRow['amount']) * 100,
                'payplus_response' => wp_json_encode($dataRow),
                'related_transactions' => ($is_multiple_transaction) ? 1 : 0,
                'status_code' => sanitize_text_field($dataRow['status_code']),
                'delete_at' => 0,
            );

            $format = [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%f',
                '%s',
                '%d',
                '%s',
                '%d',
            ];

            $wpdb->insert($table, $data, $format);

            $parent_id = $wpdb->insert_id;
            /* end parent payment */

            /*related transactions*/
            if ($is_multiple_transaction) {

                $dataMultiples = $dataRow['related_transactions'];
                for ($i = 0; $i < count($dataMultiples); $i++) {
                    $dataRow = (array) $dataMultiples[$i];
                    if (isset($dataRow['alternative_method_name']) && in_array($dataRow['alternative_method_name'], array('google-pay', 'apple-pay'))) {
                        $dataRow['method'] = $dataRow['alternative_method_name'];
                    }
                    $data = array(
                        'order_id' => intval($order_id),
                        'parent_id' => intval($parent_id),
                        'transaction_uid' => sanitize_text_field($dataRow['transaction_uid']),
                        'method_payment' => (!$is_multiple_transaction) ? "" : sanitize_text_field(strtolower($dataRow['method'])),
                        'page_request_uid' => sanitize_text_field($dataRow['page_request_uid']),
                        'four_digits' => sanitize_text_field($dataRow['four_digits']),
                        'number_of_payments' => (!empty($dataRow['number_of_payments'])) ? intval($dataRow['number_of_payments']) : 0,
                        'brand_name' => (!empty($dataRow['brand_name'])) ? sanitize_text_field($dataRow['brand_name']) : '',
                        'approval_num' => (!empty($dataRow['approval_num'])) ? sanitize_text_field($dataRow['approval_num']) : '',
                        'alternative_method_name' => (!empty($dataRow['alternative_method_name'])) ? sanitize_text_field($dataRow['alternative_method_name']) : '',
                        'type_payment' => sanitize_text_field($dataRow['type']),
                        'token_uid' => (!empty($dataRow['token_uid'])) ? sanitize_text_field($dataRow['token_uid']) : '',
                        'price' => floatval($dataRow['amount']) * 100,
                        'payplus_response' => wp_json_encode($dataRow),
                        'delete_at' => 0,
                    );

                    $format = [
                        '%d',
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%f',
                        '%s',
                        '%d',
                    ];

                    $wpdb->insert($table, $data, $format);
                }
            }
        }
    }


    /**
     * @param $metas
     * @return void
     */
    public function get_meta_data($metas)
    {
        $name = "";
        $index = 0;
        if (count($metas)) {
            foreach ($metas as $key => $meta) {
                if ($index) {
                    $name .= "," . $meta->display_key . " : " . $meta->value;
                } else {
                    $name .= $meta->display_key . " : " . $meta->value;
                }
                $index++;
            }
        }
        return $name;
    }

    public function pauplus_get_current_date()
    {

        $dateNow = new DateTime();
        $dateNow = $dateNow->format('Y-m-d H:i:s');
        return $dateNow;
    }
}