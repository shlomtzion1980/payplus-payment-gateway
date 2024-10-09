<?php
defined('ABSPATH') || exit; // Exit if accessed directly
class WC_PayPlus_Admin_Payments extends WC_PayPlus_Gateway
{
    protected static $instance = null;
    private $initiated = false;
    public $payPlusInvoice;
    public $error_msg = 0;
    public $arrPayment = array(
        'payplus-payment-gateway',
        'payplus-payment-gateway-bit',
        'payplus-payment-gateway-googlepay',
        'payplus-payment-gateway-applepay',
        'payplus-payment-gateway-multipass',
        'payplus-payment-gateway-paypal',
        'payplus-payment-gateway-tavzahav',
        'payplus-payment-gateway-valuecard',
        'payplus-payment-gateway-finitione',
        'payplus-payment-gateway-hostedfields',
    );
    public $applePaySettings;
    public $isApplePayEnabled;
    public $isInvoiceEnable;
    public $useDedicatedMetaBox;
    public $invoiceDisplayOnly;
    public $saveOrderNote;
    public $showPayPlusDataMetabox;
    public $allSettings;
    public $transactionType;
    private $_wpnonce;
    public $api_test_mode;

    /**
     * @return null
     */
    public static function get_instance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }
        return static::$instance;
    }
    /**
     *
     */
    public function __construct()
    {
        $this->_wpnonce = wp_create_nonce('PayPlusGateWayAdminNonce');
        if (!wp_verify_nonce($this->_wpnonce, 'PayPlusGateWayAdminNonce')) {
            wp_die('Not allowed! - __construct - class-wp-payplus-admin.php');
        }
        global $pagenow;
        $postKey = array_key_exists('post', $_GET) ? 'post' : 'id';

        $isPageOrder = ('post.php' === $pagenow || 'admin.php' === $pagenow) && isset($_GET[$postKey]) &&
            ('shop_order' === get_post_type(sanitize_text_field(wp_unslash($_GET[$postKey])))
                || 'shop_subscription' === get_post_type(sanitize_text_field(wp_unslash($_GET[$postKey])))
                || 'shop_order_placehold' === get_post_type(sanitize_text_field(wp_unslash($_GET[$postKey]))));

        $sections = $this->arrPayment;
        $sections[] = 'payplus-payment-gateway-setup-wizard';
        $sections[] = 'payplus-error-setting';
        $sections[] = 'payplus-invoice';
        $sections[] = 'payplus-express-checkout';

        if (
            $isPageOrder
            || (('admin.php' === $pagenow) && isset($_GET['section']) && in_array($_GET['section'], $sections))
        ) {

            add_action('admin_enqueue_scripts', [$this, 'load_admin_assets']);
        }

        $this->transactionType = $this->get_option('transaction_type');
        $this->api_test_mode = $this->get_option('api_test_mode');

        $this->payPlusInvoice = new PayplusInvoice();
        $payPlusInvoiceOptions = get_option('payplus_invoice_option');
        $this->isInvoiceEnable = isset($payPlusInvoiceOptions['payplus_invoice_enable']) && $payPlusInvoiceOptions['payplus_invoice_enable'] === 'yes' ? true : false;
        $this->useDedicatedMetaBox = isset($payPlusInvoiceOptions['dedicated_invoice_metabox']) && $payPlusInvoiceOptions['dedicated_invoice_metabox'] === 'yes' ? true : false;
        $this->invoiceDisplayOnly = isset($payPlusInvoiceOptions['display_only_invoice_docs']) && $payPlusInvoiceOptions['display_only_invoice_docs'] === 'yes' ? true : false;
        $this->allSettings = get_option('woocommerce_payplus-payment-gateway_settings');
        $this->saveOrderNote = isset($this->settings['payplus_data_save_order_note']) ? boolval($this->settings['payplus_data_save_order_note'] === 'yes') : null;
        $this->showPayPlusDataMetabox = isset($this->allSettings['show_payplus_data_metabox']) ? boolval($this->allSettings['show_payplus_data_metabox'] === 'yes') : null;
        $this->applePaySettings = get_option('woocommerce_payplus-payment-gateway-applepay_settings');
        $this->isApplePayEnabled = boolval(isset($this->applePaySettings['enabled']) && $this->applePaySettings['enabled'] === "yes");
        // make payment button for j2\j5
        add_action('woocommerce_order_actions_end', [$this, 'make_payment_button'], 10, 1);
        // process make payment
        add_action('save_post_shop_order', [$this, 'process_make_payment'], 10, 1);
        // admin notices
        add_action('admin_notices', [$this, 'admin_notices'], 15);
        add_action('wp_ajax_payplus-token-payment', [$this, 'ajax_payplus_token_payment']);
        add_action('wp_ajax_payplus-api-payment', [$this, 'ajax_payplus_payment_api']);
        add_action('wp_ajax_generate-link-payment', [$this, 'ajax_payplus_generate_link_payment']);
        add_action('wp_ajax_payment-payplus-transaction-review', [$this, 'ajax_payment_payplus_transaction_review']);
        add_action('wp_ajax_payplus-create-invoice', [$this, 'ajax_payplus_create_invoice']);
        add_action('wp_ajax_payplus-create-invoice-refund', [$this, 'ajax_payplus_create_invoice_refund']);
        add_action('wp_ajax_payplus-refund-club-amount', [$this, 'ajax_payplus_refund_club_amount']);
        // adds the callback js query action of the "Get order details" from PayPlus custom button.
        add_action('wp_ajax_payplus_ipn', [$this, 'payplusIpn']);
        add_action('wp_ajax_make-token-payment', [$this, 'makeTokenPayment']);

        add_action('woocommerce_admin_order_totals_after_total', [$this, 'payplus_woocommerce_admin_order_totals_after_total'], 10, 1);
        // Place "Get Order Details" button from PayPlus if the order is marked as unpaid - allows to get the order details from PayPlus if exists and
        // updates the order status to processing if the payment was successful and adds order note!
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'add_custom_button_to_order'], 10, 1);
        add_action('add_meta_boxes', [$this, 'payPlusMetaboxes']);
        add_action('admin_head', [$this, 'hide_delete_update_buttons_css']);

        // remove query args after error shown
        add_filter('removable_query_args', [$this, 'add_removable_arg']);
        add_filter('woocommerce_get_settings_checkout', [$this, 'payplusGetAdminSettings'], 10, 2);
        add_filter('admin_body_class', [$this, 'payplus_admin_classes']);


        if ($this->payPlusInvoice->payplus_get_invoice_enable()) {
            add_action('woocommerce_order_refunded', [$this, 'payplus_after_refund'], 10, 2);
        }
    }


    public function makeTokenPayment()
    {
        check_ajax_referer('payplus_token_payment', '_ajax_nonce');

        $this->isInitiated();
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : wp_die('No order id received.');
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : null;

        $payload = $this->generatePayloadLink($order_id, true, $token);

        $order->set_payment_method('payplus-payment-gateway');
        $order->set_payment_method_title('Pay with Debit or Credit Card');

        $response = $this->post_payplus_ws($this->payment_url, $payload);
        $response = json_decode(wp_remote_retrieve_body($response));

        if ($response->data->status_code === "000") {
            $updateData = [
                'payplus_page_request_uid' => $response->data->page_request_uid,
                'payplus_transaction_uid' => $response->data->transaction_uid,
            ];
            WC_PayPlus_Meta_Data::update_meta($order, $updateData);
        }
        $this->payplusIpn();
    }

    /**
     * Hide Delete/Update buttons of custom fields
     * @return void
     */
    public function hide_delete_update_buttons_css()
    {
        $this->isInitiated();
        if ($this->hide_custom_fields_buttons) {
            echo "<style>.post-type-shop_order #the-list .deletemeta { display: none !important; } #order_custom {textarea,input {pointer-events: none;opacity: 0.5;background-color: #f5f5f5;} #newmeta input,#newmeta textarea,.submit.add-custom-field input {pointer-events: auto !important;opacity: 1 !important;background-color: white !important;}} 
             .post-type-shop_order #the-list .updatemeta { display: none !important; }</style>";
        }
    }




    public function payPlusMetaboxes()
    {
        $screen = get_current_screen();
        if ($screen->post_type === 'shop_order') {
            if (($this->isInvoiceEnable  && $this->useDedicatedMetaBox) || $this->invoiceDisplayOnly) {
                add_meta_box(
                    'invoice_plus_order_metabox',
                    "<img style='height: 30px;' src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "Invoice+logo.png'>",
                    [$this, 'display_invoice_order_metabox'],
                    $screen->id,
                    'side',
                    'default',
                    ['metaBoxType' => 'payplusInvoice']
                );
            }
            if ($this->showPayPlusDataMetabox) {
                add_meta_box(
                    'payplus_order_metabox',
                    "<img style='height: 35px;' src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "PayPlusLogo.svg'>",
                    [$this, 'display_payplus_order_metabox'],
                    $screen->id,
                    'side',
                    'default',
                    ['metaBoxType' => 'payplus']
                );
            }
        }
    }

    public function display_invoice_order_metabox($post, $metaBox)
    {
        WC_PayPlus_Statics::payPlusOrderMetaBox($post, $metaBox);
    }
    public function display_payplus_order_metabox($post, $metaBox)
    {
        WC_PayPlus_Statics::payPlusOrderMetaBox($post, $metaBox);
    }


    public function isInitiated()
    {
        if (!$this->initiated) {
            $this->initiated = true;
            parent::__construct();
        }
    }

    /**
     * @param $order
     * @return void
     */
    public function payplusIpn($order_id = null, $_wpnonce = null, $saveToken = false, $isHostedPayment = false)
    {

        $this->isInitiated();

        if (!wp_verify_nonce($this->_wpnonce, 'PayPlusGateWayAdminNonce')) {
            check_ajax_referer('payplus_payplus_ipn', '_ajax_nonce');
        }

        if (!current_user_can('edit_shop_orders') && !wp_verify_nonce($_wpnonce, '_wp_payplusIpn')) {
            wp_send_json_error('You do not have permission to edit orders. - paplusIpn');
            wp_die();
        }

        $this->payplus_add_log_all('payplus-ipn', 'PayPlus IPN started.', 'default');
        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order_id = boolval(empty($order_id)) ? $orderId : $order_id;
        $order = wc_get_order($order_id);

        $this->payplus_add_log_all('payplus-ipn', 'Begin for order: ' . $order_id, 'default');
        $payment_request_uid = isset($_POST['payment_request_uid']) ? sanitize_text_field(wp_unslash($_POST['payment_request_uid'])) : WC_PayPlus_Meta_Data::get_meta($order, 'payplus_page_request_uid');



        $url = $this->ipn_url;

        $payload['payment_request_uid'] = $payment_request_uid;

        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : "";
        $args = array(
            'body' => wp_json_encode($payload),
            'timeout' => '60',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'domain' => home_url(),
                'User-Agent' => "WordPress $userAgent",
                'Content-Type' => 'application/json',
                'Authorization' => '{"api_key":"' . $this->api_key . '","secret_key":"' . $this->secret_key . '"}',
            ),
        );

        $response = wp_remote_post($url, $args);
        $responseBody = json_decode(wp_remote_retrieve_body($response), true);

        if ($this->create_pp_token && $isHostedPayment && $saveToken) {
            $user_id = $order->get_user_id();
            $this->save_token($responseBody['data'], $user_id);
        }

        if (!empty($responseBody['data'])) {
            $type = $responseBody['data']['type'];

            $type_text = ($type == "Approval" || $type == "Check") ? __('Pre-Authorization', 'payplus-payment-gateway') : __('Payment', 'payplus-payment-gateway');
            $successNote = sprintf(
                '<div style="font-weight:600;">PayPlus %s Successful</div>
            <table style="border-collapse:collapse">
                <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Last digits</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Expiry date</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Voucher ID</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                <tr><td style="vertical-align:top;">Token</td><td style="vertical-align:top;">%s</td></tr>
                <tr><td style="vertical-align:top;">Total</td><td style="vertical-align:top;">%s</td></tr>
            </table>',
                esc_html($type_text),
                esc_html($responseBody['data']['number']),
                esc_html($responseBody['data']['four_digits']),
                esc_html($responseBody['data']['expiry_month'] . "/" . $responseBody['data']['expiry_year']),
                esc_html($responseBody['data']['voucher_num']),
                esc_html($responseBody['data']['token_uid']),
                esc_html($responseBody['data']['amount']),
                esc_html($order->get_total())
            );

            $responseArray = [
                'payplus_response' => wp_json_encode($responseBody['data']),
                'payplus_type' => esc_html($responseBody['data']['type']),
                'payplus_brand_name' => esc_html($responseBody['data']['brand_name']),
                'payplus_method' => esc_html($responseBody['data']['method']),
                'payplus_number' => esc_html($responseBody['data']['number']),
                'payplus_number_of_payments' => esc_html($responseBody['data']['number_of_payments']),
                'payplus_clearing_name' => esc_html($responseBody['data']['clearing_name']),
                'payplus_credit_terms' => esc_html($responseBody['data']['credit_terms']),
                'payplus_credit-card' => esc_html($responseBody['data']['amount']),
                'payplus_customer_name' => esc_html($responseBody['data']['customer_name']),
                'payplus_expiry_month' => esc_html($responseBody['data']['expiry_month']),
                'payplus_expiry_year' => esc_html($responseBody['data']['expiry_year']),
                'payplus_four_digits' => esc_html($responseBody['data']['four_digits']),
                'payplus_issuer_id' => esc_html($responseBody['data']['issuer_id']),
                'payplus_issuer_name' => esc_html($responseBody['data']['issuer_name']),
                'payplus_more_info' => esc_html($responseBody['data']['more_info']),
                'payplus_secure3D_tracking' => esc_html($responseBody['data']['secure3D_tracking']),
                'payplus_status' => esc_html($responseBody['data']['status']),
                'payplus_status_code' => esc_html($responseBody['data']['status_code']),
                'payplus_status_description' => esc_html($responseBody['data']['status_description']),
                'payplus_token_uid' => esc_html($responseBody['data']['token_uid']),
                'payplus_voucher_num' => esc_html($responseBody['data']['voucher_num'])
            ];

            $responseBody['data']['status'] === "approved" && $responseBody['data']['status_code'] === "000" ? WC_PayPlus_Meta_Data::update_meta($order, $responseArray) : $order->add_order_note('PayPlus IPN: ' . sanitize_text_field(wp_unslash($responseBody['data']['status'])));

            $transactionUid = $responseBody['data']['transaction_uid'];

            if ($responseBody['data']['status'] === 'approved' && $responseBody['data']['status_code'] === '000' && $responseBody['data']['type'] === 'Charge') {
                WC_PayPlus_Meta_Data::sendMoreInfo($order, 'wc-processing', $transactionUid);
                $order->update_status('wc-processing');
                if ($this->saveOrderNote) {
                    $order->add_order_note(
                        $successNote
                    );
                }
            } elseif ($responseBody['data']['status'] === 'approved' && $responseBody['data']['status_code'] === '000' && $responseBody['data']['type'] === 'Approval') {
                WC_PayPlus_Meta_Data::sendMoreInfo($order, 'wc-on-hold', $transactionUid);
                $order->update_status('wc-on-hold');
                if ($this->saveOrderNote) {
                    $order->add_order_note(
                        $successNote
                    );
                }
            }
        } else {
            $note = $responseBody['data']['status'] ?? $responseBody['results']['description'] . ' - If token payment - token doesn`t fit billing or no payment.';
            $order->add_order_note('PayPlus IPN: ' . $note);
        }
    }

    /**
     * @param $classes
     * @return mixed|string
     */
    public function payplus_admin_classes($classes)
    {
        if (!wp_verify_nonce($this->_wpnonce, 'PayPlusGateWayAdminNonce')) {
            wp_die('Not allowed! - payplus_admin_classes');
        }
        if (isset($_GET['section']) && $_GET['section'] === 'payplus-error-setting') {
            $classes .= "payplus-error-setting";
        }
        return $classes;
    }



    /**
     *
     * Get the current section settings
     *
     * @param $settings
     * @param $current_section
     * @return mixed
     */
    public function payplusGetAdminSettings($settings, $current_section)
    {
        $settings = WC_PayPlus_Admin_Settings::getAdminSection($settings, $current_section);
        return $settings;
    }

    public function ajax_payplus_refund_club_amount()
    {
        check_ajax_referer('payplus_refund_club_amount', '_ajax_nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('You do not have permission to edit orders.');
            wp_die();
        }
        if (
            !empty($_POST['transactionUid'])
            && !empty($_POST['orderID'])
            && !empty($_POST['amount'])
            && !empty($_POST['method'])
            && !empty($_POST['id'])
        ) {
            $handle = 'payplus_process_refund';
            $this->isInitiated();

            // Sanitize input data
            $amount = floatval($_POST['amount']);
            $method = sanitize_text_field(wp_unslash($_POST['method']));
            $transactionUid = sanitize_text_field(wp_unslash($_POST['transactionUid']));
            $orderID = intval($_POST['orderID']);
            $id = intval($_POST['id']);

            $indexRow = 0;
            $urlEdit = esc_url(get_admin_url()) . "post.php?post=" . $orderID . "&action=edit";
            $this->payplus_add_log_all($handle, 'WP Refund club card(' . $orderID . ')');
            $order = wc_get_order($orderID);
            $refunded_amount = round((float) $order->get_meta('payplus_total_refunded_amount'), 2);

            $payload['transaction_uid'] = $transactionUid;
            $payload['amount'] = $amount;
            $payload['more_info'] = __('Refund for Order Number: ', 'payplus-payment-gateway') . $orderID;

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
                    WC_PayPlus_Meta_Data::update_meta($order, array('payplus_total_refunded_amount' => round($refunded_amount + $amount, 2)));
                    $this->payplus_update_order_payment($id, $amount);
                    $this->payplus_add_log_all($handle, print_r($res, true), 'completed');
                    $order->add_order_note(sprintf('PayPlus Refund is Successful<br />Refund Transaction Number: %s<br />Amount: %s %s<br />Reason: %s', $res->data->transaction->number, $res->data->transaction->amount, $order->get_currency(), 'refund ' . $method));
                    // Create refund
                    $refund = wc_create_refund(array(
                        'amount' => $amount,
                        'reason' => "refund " . $method,
                        'order_id' => $orderID,
                    ));

                    // Invoice API
                    if (
                        $this->invoice_api->payplus_get_invoice_enable() &&
                        !$this->invoice_api->payplus_get_create_invoice_manual()
                    ) {
                        $payments = $this->payplus_get_order_payment(false, $id);
                        if ($payments[$indexRow]->price > round($amount, $this->rounding_decimals)) {
                            $payments[$indexRow]->price = $amount * 100;
                        }
                        $rand = wp_rand(0, intval($orderID));
                        $this->invoice_api->payplus_create_document_dashboard(
                            $orderID,
                            $this->invoice_api->payplus_get_invoice_type_document_refund(),
                            $payments,
                            round($amount, $this->rounding_decimals),
                            'payplus_order_refund_' . $rand . "_" . $orderID
                        );
                    }
                    echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => true));
                } else {
                    $this->payplus_add_log_all($handle, print_r($res, true), 'error');
                    $order->add_order_note(sprintf('PayPlus Refund is Failed<br />Status: %s<br />Description: %s', $res->results->status, $res->results->description));
                    echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => false));
                }
            }
        } else {
            wp_send_json_error('Missing required parameters.');
        }
        wp_die();
    }

    public function payplus_add_payments($order_id, $payments)
    {
        global $wpdb;
        $order_id = intval($order_id);
        $table = $wpdb->prefix . 'payplus_order';

        // Update existing rows for the order
        $wpdb->update(
            $table,
            array('delete_at' => 1),
            array('order_id' => $order_id),
            array('%d'), // Data format for delete_at (integer)
            array('%d')  // Data format for order_id (integer)
        );

        // Insert new payments
        $order = wc_get_order($order_id);
        WC_PayPlus_Meta_Data::update_meta($order, ['payplus_order_payments' => wp_json_encode($payments)]);

        foreach ($payments as $key => $payment) {
            $payment['parent_id'] = 0;
            $payment['delete_at'] = 0;
            $payment['price'] = floatval($payment['price']) * 100;
            unset($payment['row_id']);
            if (isset($payment['transaction_type']) && $payment['transaction_type'] == 'normal') {
                $payment['number_of_payments'] = 1;
            } else {
                if (isset($payment['first_payment']) || isset($payment['subsequent_payments'])) {
                    $payment['first_payment'] = floatval($payment['first_payment']) * 100;
                    $payment['subsequent_payments'] = floatval($payment['subsequent_payments']) * 100;
                }
            }

            $date_string = $payment['create_at'];

            // Validate and sanitize the date
            $date = DateTime::createFromFormat('Y-m-d', $date_string);

            if ($date && $date->format('Y-m-d') === $date_string) {
                // Date is valid and sanitized
                $sanitized_date = $date->format('Y-m-d');
            }

            $payment['method_payment'] = sanitize_text_field($payment['method_payment']);
            $payment['create_at'] = $sanitized_date;

            $dataTypes = [];
            foreach ($payment as $key => $val) {
                if (in_array($key, [
                    'method_payment',
                    'create_at',
                    'bank_number',
                    'transaction_id',
                    'account_number',
                    'branch_number',
                    'check_number',
                    'four_digits',
                    'brand_name',
                    'transaction_type',
                    'payment_app',
                    'transaction_id',
                    'payer_account',
                    'notes'
                ])) {
                    $payment[$key] = sanitize_text_field($payment[$key]);
                    $dataTypes[] = '%s';
                }
                if (in_array($key, ['number_of_payments', 'order_id', 'parent_id', 'delete_at'])) {
                    $payment[$key] = intval($payment[$key]);
                    $dataTypes[] = '%d';
                }
                if (in_array($key, ['subsequent_payments', 'first_payment'])) {
                    $payment[$key] = floatval($payment[$key])  * 100;
                    $dataTypes[] = '%f';
                }
                if ($key === 'price') {
                    $payment[$key] = floatval($payment[$key]);
                    $dataTypes[] = '%f';
                }
            }
            // Insert sanitized data into the database
            $wpdb->insert(
                $table,
                $payment,
                $dataTypes // Data format for each column
            );
        }
    }

    /**
     * @return void
     */

    /**
     * @return void
     */
    public function ajax_payplus_create_invoice()
    {
        check_ajax_referer('create_invoice_nonce', '_ajax_nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('You do not have permission to edit orders.');
            wp_die();
        }

        if (!empty($_POST) && !empty($_POST['order_id'])) {
            $order_id = intval($_POST['order_id']);
            $urlEdit = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
            $type_document = isset($_POST['typeDocument']) ? sanitize_text_field(wp_unslash($_POST['typeDocument'])) : false;
            $payments = !empty($_POST['payments']) ? WC_PayPlus_Statics::sanitize_recursive(wp_unslash($_POST['payments'])) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized	

            if (!empty($payments)) {
                function set_payment_payplus($value)
                {
                    if ($value['method_payment'] == "payment-app") {
                        $value['method_payment'] = sanitize_text_field($value['payment_app']);
                        unset($value['payment_app']);
                    }
                    return $value;
                }
                $payments = array_map('set_payment_payplus', $payments);
                $this->payplus_add_payments($order_id, $payments);
            }

            $this->payPlusInvoice->payplus_invoice_create_order($order_id, $type_document);
            echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => true));
            wp_die();
        }
        wp_die();
    }
    /**
     * @return void
     */
    public function ajax_payplus_create_invoice_refund()
    {

        check_ajax_referer('create_invoice_refund_nonce', '_ajax_nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('You do not have permission to edit orders.');
            wp_die();
        }

        if (!empty($_POST) && !empty($_POST['order_id'])) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'payplus_order';
            $indexRow = 0;
            $order_id = intval($_POST['order_id']);
            $order = wc_get_order($order_id);
            $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : null;
            $urlEdit = html_entity_decode(esc_url(get_edit_post_link($order_id)));
            $this->isInitiated();
            $type_document = isset($_POST['type_document']) ? sanitize_text_field(wp_unslash(($_POST['type_document']))) : null;
            $resultApps = $this->payPlusInvoice->payplus_get_payments($order_id);

            if (empty($resultApps)) {
                $resultApps = [];
                $payplusResponse = json_decode(WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_response'), true);
                $resultApps[$indexRow]['method_payment'] = $payplusResponse['method'];
                $resultApps[$indexRow]['price'] = $payplusResponse['amount'] * 100;
                $resultApps[$indexRow]['transaction_uid'] = $payplusResponse['transaction_uid'];
                $resultApps[$indexRow]['page_request_uid'] = $payplusResponse['page_request_uid'];
                $resultApps[$indexRow]['four_digits'] = $payplusResponse['four_digits'];
                $resultApps[$indexRow]['number_of_payments'] = $payplusResponse['number_of_payments'];
                $resultApps[$indexRow]['brand_name'] = $payplusResponse['brand_name'];
                $resultApps[$indexRow]['type_payment'] = $payplusResponse['type'];
                $resultApps[$indexRow]['token_uid'] = $payplusResponse['token_uid'];
                $resultApps[$indexRow]['refund'] = $payplusResponse['amount'] * 100;
                $resultApps[$indexRow]['invoice_refund'] = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_total_refunded_amount') * 100;
                $resultApps[$indexRow] = (object) $resultApps[$indexRow];
            }

            $sum = 0;
            $sumTransactionRefund = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_total_refunded_amount', true) * 100;

            if (floatval($sumTransactionRefund) != floatval($amount)) {
                if (count($resultApps) > 1) {
                    $resultApps = array();
                    $objectInvoicePaymentNoPayplus = array('method_payment' => 'other', 'price' => $amount * 100);
                    $objectInvoicePaymentNoPayplus = (object) $objectInvoicePaymentNoPayplus;
                    $resultApps[] = $objectInvoicePaymentNoPayplus;
                } else {
                    $resultApps[$indexRow]->price = $amount * 100;
                }
            }

            if (count($resultApps)) {
                $this->payPlusInvoice->payplus_create_document_dashboard(
                    $order_id,
                    $type_document,
                    $resultApps,
                    round($amount, $this->rounding_decimals),
                    'payplus_order_refund' . $order_id . "_" . wp_rand(1, 1000)
                );

                $wpdb->update(
                    $table_name,
                    array('invoice_refund' => 0),
                    array('order_id' => $order_id),
                    array('%d'),
                    array('%d')
                );
                if ($amount == $order->get_total()) {
                    WC_PayPlus_Meta_Data::update_meta($order, array('payplus_send_refund' => true));
                }
            }
            echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => true));
            wp_die();
        }
        wp_die();
    }

    /**
     * @return void
     */
    public function ajax_payment_payplus_transaction_review()
    {
        check_ajax_referer('payplus_transaction_review', '_ajax_nonce');
        if (!empty($_POST) && !empty($_POST['order_id'])) {
            $handle = "payplus_process_payment";
            $this->isInitiated();
            $order_id = intval($_POST['order_id']);
            $urlEdit = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
            $order = wc_get_order($order_id);
            if ($order) {
                $payload = array();
                $status = $order->get_type();

                if ($status == "shop_subscription") {
                    $parent_id = ($order->get_parent_id());
                    if ($order->get_user() != false) {
                        $userID = $order->get_user_id();
                        $payload = array();
                        $token = get_user_meta($userID, 'cc_token', true);
                        if (empty($token)) {
                            $transaction_uid = WC_PayPlus_Meta_Data::get_meta($parent_id, 'payplus_transaction_uid', true);
                            if ($transaction_uid) {
                                $payload['transaction_uid'] = $transaction_uid;
                            } else {
                                $payload['more_info'] = $parent_id;
                            }
                            $payload = wp_json_encode($payload);
                            $this->payplus_add_log_all($handle, 'New IPN Fired (' . $order_id . ')');
                            $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');
                            $data['order_id'] = $order_id;
                            $returnIpn = $this->requestPayPlusIpn($payload, $data, 1, $handle, true);

                            if ($returnIpn->results->status == 'success') {

                                $StatusCode = $returnIpn->data->status_code;

                                if ($StatusCode == "000") {
                                    $token = $returnIpn->data->token_uid;
                                    $order->update_status('wc-active');
                                    add_user_meta($userID, 'cc_token', $token);
                                    WC_PayPlus_Meta_Data::update_meta($order, array('payplus_token_uid' => $token));
                                    $order = wc_get_order($parent_id);
                                    $order->add_order_note('Update token:' . $token);
                                    $order->save();
                                    echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => true));
                                    wp_die();
                                }
                            }
                        } else {
                            WC_PayPlus_Meta_Data::update_meta($order, array('order_validated' => "1"));
                            delete_post_meta($order_id, 'order_validated_error');
                            $order->update_status('wc-active');
                            echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => true));
                        }
                    }
                } else {

                    $payload['more_info'] = $order_id;
                    $payload = wp_json_encode($payload);
                    $this->payplus_add_log_all($handle, 'New IPN Fired (' . $order_id . ')');
                    $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');
                    $this->requestPayPlusIpn($payload, array('order_id' => $order_id), 1);
                    WC_PayPlus_Meta_Data::update_meta($order, array('order_validated' => '1'));
                    $order->delete_meta_data('order_validated_error');
                    $order->save();
                    delete_post_meta($order_id, 'order_validated_error');
                    echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => true));
                    wp_die();
                }
            }
        }
        wp_die();
    }

    /**
     * @return void
     */
    public function ajax_payplus_generate_link_payment()
    {
        check_ajax_referer('payplus_generate_link_payment', '_ajax_nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('You do not have permission to edit orders.');
            wp_die();
        }

        $response = array("payment_response" => "", "status" => false);

        if (!empty($_POST)) {
            $this->isInitiated();
            $handle = "payplus_process_payment";
            $date = new DateTime();
            $dateNow = $date->format('Y-m-d H:i');
            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
            $order = wc_get_order($order_id);
            $order->set_payment_method('payplus-payment-gateway');
            $order->set_payment_method_title('Pay with Debit or Credit Card');
            $this->payplus_add_log_all($handle, 'New Payment Process Fired (' . $order_id . ')');
            $payload = $this->generatePayloadLink($order_id, true);
            $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');
            $response = $this->post_payplus_ws($this->payment_url, $payload);

            if (is_wp_error($response)) {
                $this->payplus_add_log_all($handle, print_r($response, true), 'error');
            } else {

                $res = json_decode(wp_remote_retrieve_body($response));
                if (isset($res->data->payment_page_link) && $this->validateUrl($res->data->payment_page_link)) {
                    $this->payplus_add_log_all($handle, print_r($res, true), 'completed');
                    $this->payplus_add_log_all($handle, 'WS Redirecting to Page: ' . $res->data->payment_page_link . "\n" . $this->payplus_get_space());
                    WC_PayPlus_Meta_Data::update_meta($order, array('payplus_page_request_uid' => $res->data->page_request_uid));
                    WC_PayPlus_Meta_Data::update_meta($order, array('payplus_payment_page_link' => $res->data->payment_page_link));
                    $response = array("status" => true, "payment_response" => $res->data->payment_page_link);
                } else {
                    $this->payplus_add_log_all($handle, print_r($res, true), 'error');
                    $response = (is_array($response)) ? $response['body'] : $response->body;
                    $response = array("status" => false, "payment_response" => $response);
                }
            }
        }
        echo wp_json_encode($response);
        wp_die();
    }
    /**
     * @return void
     */
    public function ajax_payplus_payment_api()
    {
        check_ajax_referer('payplus_api_payment', '_ajax_nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('You do not have permission to edit orders.');
            wp_die();
        }

        if (!empty($_POST)) {
            $this->isInitiated();
            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
            $order = wc_get_order($order_id);
            $handle = "payplus_process_payment";
            $transaction_uid = $order->get_meta('payplus_transaction_uid');
            $transaction_uid = "";
            $payload = array();
            $createToken = false;
            if ($order->get_user() != false) {
                $userID = $order->get_user_id();
            }
            $type = $order->get_meta('payplus_type');

            if ($transaction_uid) {
                $payload['transaction_uid'] = $transaction_uid;
            } else {

                $payload['more_info'] = $order_id;
            }

            $payload['related_transaction'] = true;
            $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');
            $payload = wp_json_encode($payload);
            $response = $this->post_payplus_ws($this->ipn_url, $payload);

            $res = json_decode(wp_remote_retrieve_body($response));
            if ($res->results->status == "error" || $res->data->status_code !== "000") {

                $transaction_uid = ($transaction_uid) ? $transaction_uid : $res->data->transaction_uid;
                $this->payplus_add_log_all($handle, print_r($res, true), 'error');
                $order->update_status($this->failure_order_status);
                $order->add_order_note(sprintf('PayPlus IPN Failed<br/>Transaction UID: %s', $transaction_uid));
                $order->add_meta_data('order_validated', "1");
                $urlEdit = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
                echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => false));
                wp_die();
            } else if ($res->data->status_code === '000') {
                $this->payplus_add_log_all($handle, print_r($res, true), 'completed');
                $inData = (array) $res->data;

                $this->updateMetaData($order_id, $inData);

                if (isset($res->data->recurring_type)) {
                    if ($this->recurring_order_set_to_paid == 'yes') {
                        $order->payment_complete();
                    }
                    $order->update_status('wc-recsubc');
                } else {
                    if ($type == "Charge") {
                        if ($this->fire_completed) {
                            $order->payment_complete();
                        }
                        if ($this->successful_order_status !== 'default-woo') {
                            $order->update_status($this->successful_order_status);
                        }
                    } else {
                        $order->update_status('wc-on-hold');
                    }

                    $html = '<div style="font-weight:600;">PayPlus Related Transaction';
                    if (property_exists($res->data, 'related_transactions')) {
                        $relatedTransactions = $res->data->related_transactions;
                        for ($i = 0; $i < count($relatedTransactions); $i++) {
                            $relatedTransaction = $relatedTransactions[$i];
                            if ($relatedTransaction->method == "credit-card") {
                                $html .= sprintf(
                                    '<div style="font-weight:600;border-bottom: 1px solid #000;padding: 5px 0px">PayPlus ' . (($type == "Approval" || $type == "Check") ? 'Pre-Authorization' : 'Payment') . ' Successful
                                        <table style="border-collapse:collapse">
                                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Last digits</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Expiry date</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Voucher ID</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                            <tr><td style="vertical-align:top;">Token</td><td style="vertical-align:top;">%s</td></tr>
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
                                    '<div class="row" style="font-weight:600;border-bottom: 1px solid #000;padding: 5px 0px">PayPlus  Successful ' . $relatedTransaction->method . '
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
                            if ($this->saveOrderNote) {
                                $order->add_order_note(sprintf(
                                    '<div class="row" style="font-weight:600;border-bottom: 1px solid #000;padding: 5px 0px">PayPlus  Successful ' . $res->data->method . '
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
                                    '<div style="font-weight:600;">PayPlus ' . (($type == "Approval" || $type == "Check") ? 'Pre-Authorization' : 'Payment') . ' Successful</div>
                                        <table style="border-collapse:collapse">
                                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Last digits</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Expiry date</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Voucher ID</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                            <tr><td style="vertical-align:top;">Token</td><td style="vertical-align:top;">%s</td></tr>
                                            <tr><td style="vertical-align:top;">Total</td><td style="vertical-align:top;">%s</td></tr>
                                        </table>
                                    ',
                                    $res->data->number,
                                    $res->data->four_digits,
                                    $res->data->expiry_month . $res->data->expiry_year,
                                    $res->data->voucher_id,
                                    $res->data->token_uid,
                                    $res->data->amount,
                                    $order->get_total()
                                ));
                            }
                        }
                    }
                }
                $urlEdit = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
                echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => true));
                wp_die();
            }
        }
        wp_die();
    }

    public function payplus_get_section_invoice_not_automatic($orderId, $theTokens)
    {
        $this->isInitiated();
        $order = wc_get_order($orderId);
        $selectInvoice = array(
            'inv_tax' => __('Tax Invoice', 'payplus-payment-gateway'),
            'inv_tax_receipt' => __('Tax Invoice Receipt ', 'payplus-payment-gateway'),
            'inv_receipt' => __('Receipt', 'payplus-payment-gateway'),
            'inv_don_receipt' => __('Donation Reciept', 'payplus-payment-gateway')
        );

        $payPlusInvoiceDocs = json_decode(WC_PayPlus_Meta_Data::get_meta($orderId, 'payplus_invoice_plus_docs', true), true);
        if (is_array($payPlusInvoiceDocs)) {
            $payPlusInvoiceDocs = array_keys($payPlusInvoiceDocs);
            $selectInvoice = array_diff_key($selectInvoice, array_flip($payPlusInvoiceDocs));
            if (array_key_exists('inv_tax_receipt', $payPlusInvoiceDocs) || array_key_exists('inv_don_receipt', $payPlusInvoiceDocs)) {
                return;
            } elseif (array_key_exists('inv_tax', $selectInvoice) || array_key_exists('inv_receipt', $selectInvoice)) {
                unset($selectInvoice['inv_tax_receipt']);
                unset($selectInvoice['inv_don_receipt']);
            }
        }

        $invoiceManualList = $this->payPlusInvoice->payplus_get_invoice_manual_list();
        $currentStatus = $this->payPlusInvoice->payplus_get_invoice_type_document();
        $chackStatus = array('inv_receipt', 'inv_tax_receipt');
        $chackAllPayment = in_array($currentStatus, $chackStatus) ? "block" : 'none';
        $payments = count($this->invoice_api->payplus_get_payments($orderId)) ? $this->invoice_api->payplus_get_payments($orderId) : WC_PayPlus_Meta_Data::get_meta($orderId, 'payplus_response');
        $checkInvoiceSend = WC_PayPlus_Meta_Data::get_meta($orderId, 'payplus_check_invoice_send', true);
        if ($invoiceManualList) {
            $invoiceManualList = explode(",", $invoiceManualList);
            if (count($invoiceManualList) == 1 && $invoiceManualList[0] == "") {
                $invoiceManualList = array();
            }
        }

        function is_json($string)
        {
            json_decode($string);
            return (json_last_error() === JSON_ERROR_NONE);
        }

        if (!is_array($payments)) {
            if (is_json($payments)) {
                $array = [];
                $array[] = json_decode($payments);
                $payments = $array;
            }
        }

?>
        <div class="flex-row">
            <div class="flex-item">
                <select id="select-type-invoice-<?php echo esc_attr($orderId); ?>" class="select-type-invoice"
                    name="select-type-invoice-<?php echo esc_attr($orderId); ?>">
                    <option value="">
                        <?php echo esc_html(__('Select a document type to create an invoice', 'payplus-payment-gateway')); ?>
                    </option>
                    <?php foreach ($selectInvoice as $key => $value) :
                        $flag = true;
                        if (count($invoiceManualList)) {
                            if (!in_array($key, $invoiceManualList)) {
                                $flag = false;
                            }
                        }
                        if ($flag) :
                            $selected = ($currentStatus == $key) ? 'selected' : '';
                    ?>
                            <option <?php echo esc_attr($selected); ?> value="<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($value); ?> </option>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </select>
            </div>
        </div>

        <?php

        if (empty($payments) || !count($payments)) {

            $installed_payment_methods = WC()->payment_gateways()->payment_gateways();
            function get_payment_payplus($key, $value)
            {
                if (strpos($key, "payplus") !== false) {
                    return $value->default_charge_method;
                }
            }

            $installed_payment_methods = array_map('get_payment_payplus', array_keys($installed_payment_methods), array_values($installed_payment_methods));
            $installed_payment_methods = array_filter($installed_payment_methods, function ($value) {
                return $value != '' && $value != null;
            });
            $installed_payment_methods[] = 'pay-box';
        ?>
            <input id="all-sum" type="hidden" value="<?php echo esc_attr($order->get_total()); ?>">
            <div id="all-payment-invoice" style="display: <?php echo esc_attr($chackAllPayment); ?>">
                <div class="flex-row">
                    <h2><strong><?php esc_html(__("Payment details", "payplus-payment-gateway")) ?> </strong></h2>
                </div>
                <div class="flex-row">
                    <div class="flex-item">
                        <button id="" data-type="<?php echo esc_attr('credit-card') ?>"
                            class="credit-card type-payment"><?php echo esc_html__("Credit Card", "payplus-payment-gateway"); ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="<?php echo esc_attr('cash'); ?>"
                            class="cash type-payment"><?php echo esc_html__("Cash", "payplus-payment-gateway"); ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="<?php echo esc_attr('payment-check'); ?>"
                            class="payment-check  type-payment"><?php echo esc_html__("Check", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="<?php echo esc_attr('bank-transfer'); ?>"
                            class="bank-transfer  type-payment"><?php echo esc_html__("Bank Transfer", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="<?php echo esc_attr('payment-app'); ?>"
                            class="payment-app  type-payment"><?php echo esc_html__("Payment App", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="<?php echo esc_attr('paypal'); ?>"
                            class="paypal  type-payment"><?php echo esc_html__("PayPal", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="<?php echo esc_attr('withholding-tax'); ?>"
                            class="withholding-tax  type-payment"><?php echo esc_html__("Withholding Tax", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="<?php echo esc_attr('other'); ?>"
                            class="other  type-payment"><?php echo esc_html__("Other", "payplus-payment-gateway") ?></button>
                    </div>
                </div>
                <!-- Credit Card -->
                <div class="select-type-payment credit-card">
                    <input class="credit-card-payment-payplus input-change  row_id" type="hidden" value="">
                    <input class="credit-card-payment-payplus input-change  method_payment" type="hidden" value="credit-card">
                    <div class="flex-row">
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Date", "payplus-payment-gateway") ?></label>
                            <input value="<?php echo esc_attr(gmdate("Y-m-d")) ?>" required
                                class="credit-card-payment-payplus input-change create_at" type="date"
                                placeholder="<?php esc_attr__("Date", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Credit card number", "payplus-payment-gateway") ?></label>
                            <input class="credit-card-payment-payplus input-change four_digits" type="number"
                                onkeypress="if (value.length == 4) return false;"
                                placeholder="<?php esc_attr__("Four Digits", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Card Type", "payplus-payment-gateway") ?></label>
                            <select class="credit-card-payment-payplus input-change brand_name">
                                <option value="">
                                    <?php echo esc_html__("Card Type", "payplus-payment-gateway") ?>
                                </option>
                                <option value="mastercard">
                                    <?php echo esc_html__("Mastercard", "payplus-payment-gateway") ?>
                                </option>
                                <option value="american-express">
                                    <?php echo esc_html__("American Express", "payplus-payment-gateway") ?>
                                </option>
                                <option value="american-express">
                                    <?php echo esc_html__("Discover", "payplus-payment-gateway") ?>
                                </option>
                                <option value="visa">
                                    <?php echo esc_html__("Visa", "payplus-payment-gateway") ?>
                                </option>
                                <option value="diners">
                                    <?php echo esc_html__("Diners", "payplus-payment-gateway") ?>
                                </option>
                                <option value="jcb">
                                    <?php echo esc_html__("Jcb", "payplus-payment-gateway") ?>
                                </option>
                                <option value="maestro">
                                    <?php echo esc_html__("Maestro", "payplus-payment-gateway") ?>
                                </option>
                                <option value="other">
                                    <?php echo esc_html__("Other", "payplus-payment-gateway") ?>
                                </option>

                            </select>
                        </div>
                        <div class="flex-item">
                            <label><?php echo esc_html__("Transaction type", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <select class="credit-card-payment-payplus input-change transaction_type" id="transaction_type"
                                    name="transaction_type">
                                    <option value=""><?php echo esc_html__("Transaction type", "payplus-payment-gateway") ?>
                                    </option>
                                    <option value="normal"><?php echo esc_html__("Normal", "payplus-payment-gateway") ?></option>
                                    <option value="payments"><?php echo esc_html__("Payments", "payplus-payment-gateway") ?>
                                    </option>
                                    <option value="credit"><?php echo esc_html__("Credit", "payplus-payment-gateway") ?></option>
                                    <option value="delayed"><?php echo esc_html__("Delayed", "payplus-payment-gateway") ?></option>
                                    <option value="other"><?php echo esc_html__("Other", "payplus-payment-gateway") ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="flex-item full-amount">
                            <label><?php echo esc_html__("Sum", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <input data-sum="<?php echo esc_attr($order->get_total()) ?>" step="0.01" min="1"
                                    max="<?php echo esc_attr($order->get_total()) ?>"
                                    class="credit-card-payment-payplus input-change price" type="number"
                                    placeholder="<?php echo esc_attr__("Sum", "payplus-payment-gateway") ?>"
                                    value="<?php echo esc_attr(floatval($order->get_total())) ?>">
                                <button data-sum="<?php echo esc_attr($order->get_total()) ?>"
                                    class="payplus-full-amount credit-card-payment-payplus">
                                    <?php echo esc_html__("Full Amount", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex-row payplus_payment" style="display: none">
                        <?php
                        $sum = $order->get_total();
                        $payment = $sum / 2;
                        ?>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Payments", "payplus-payment-gateway") ?></label>
                            <select class="credit-card-payment-payplus input-change number_of_payments" id="number_of_payments"
                                name="number_of_payments">
                                <?php
                                for ($i = 2; $i <= 99; $i++) :
                                    $selected = ($i == 2) ? "selected='selected'" : "";
                                ?>
                                    <option <?php echo esc_attr($selected) ?> value="<?php echo esc_attr($i) ?>">
                                        <?php echo esc_html($i) ?>
                                    </option>
                                <?php

                                endfor;
                                ?>
                            </select>
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("First Payment", "payplus-payment-gateway") ?></label>
                            <input name="first_payment" id="first_payment" readonly value=""
                                placeholder="<?php echo esc_attr__("First Payment", "payplus-payment-gateway") ?>" type="number"
                                class="credit-card-payment-payplus input-change first_payment">
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Additional payments", "payplus-payment-gateway") ?></label>
                            <input name="subsequent_payments" id="subsequent_payments" readonly value=""
                                placeholder="<?php echo esc_attr__("Additional payments", "payplus-payment-gateway") ?>"
                                type="number" class="credit-card-payment-payplus input-change subsequent_payments">

                        </div>
                    </div>
                    <div class="flex-row flex-row-reverse">
                        <div class="flex-item">
                            <button id="credit-card-payment-payplus" class="payplus-payment-button">
                                <?php echo esc_html__("Save payment", "payplus-payment-gateway") ?> </button>
                        </div>
                    </div>
                </div>
                <!-- End Credit card -->
                <!--  cash card -->
                <div class="select-type-payment cash">
                    <input class="cash-payment-payplus input-change  row_id" type="hidden" value="">
                    <input class="cash-payment-payplus input-change  method_payment" type="hidden" id="method_payment"
                        name="method_payment" value="cash">
                    <div class="flex-row">
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Date", "payplus-payment-gateway") ?></label>
                            <input value="<?php echo esc_attr(gmdate("Y-m-d")) ?>" required
                                class="cash-payment-payplus input-change create_at" type="date"
                                placeholder="<?php echo esc_attr__("Date", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item full-amount">
                            <label><?php echo esc_html__("Sum", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <input data-sum="<?php echo esc_attr($order->get_total()) ?>" step="0.01" min="1"
                                    max="<?php echo esc_attr($order->get_total()) ?>"
                                    class="cash-payment-payplus input-change price" type="number"
                                    placeholder="<?php echo esc_attr__("Sum", "payplus-payment-gateway") ?>"
                                    value="<?php echo esc_attr(floatval($order->get_total())) ?>">
                                <button data-sum="<?php echo esc_attr($order->get_total()) ?>"
                                    class="payplus-full-amount cash-payment-payplus">
                                    <?php echo esc_html__("Full Amount", "payplus-payment-gateway") ?> </button>
                            </div>

                        </div>
                        <div class=" flex-item">
                            <label> <?php echo esc_html__("Notes", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Notes", "payplus-payment-gateway") ?>" type="text"
                                class="cash-payment-payplus input-change notes">
                        </div>
                    </div>
                    <div class="flex-row flex-row-reverse">
                        <div class="flex-item">
                            <button id="cash-payment-payplus" class="payplus-payment-button">
                                <?php echo esc_html__("Save payment", "payplus-payment-gateway") ?> </button>
                        </div>
                    </div>
                </div>
                <div class="select-type-payment payment-check">
                    <input class="payment-check-payment-payplus input-change  row_id" type="hidden" value="">
                    <input class="payment-check-payment-payplus input-change  method_payment" type="hidden" id="method_payment"
                        name="method_payment" value="payment-check">
                    <div class="flex-row">
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Date", "payplus-payment-gateway") ?></label>
                            <input value="<?php echo esc_attr(gmdate("Y-m-d")) ?>" required
                                class="payment-check-payment-payplus  input-change create_at" type="date"
                                placeholder="<?php echo esc_attr__("Date", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item full-amount">
                            <label><?php echo esc_html__("Sum", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <input data-sum="<?php echo esc_attr($order->get_total()) ?>" step="0.01" min="1"
                                    max="<?php echo esc_attr($order->get_total()) ?>"
                                    class="payment-check-payment-payplus input-change price" type="number"
                                    placeholder="<?php echo esc_attr__("Sum", "payplus-payment-gateway") ?>"
                                    value="<?php echo esc_attr(floatval($order->get_total())) ?>">
                                <button data-sum="<?php echo esc_attr($order->get_total()) ?>"
                                    class="payplus-full-amount payment-check-payment-payplus">
                                    <?php echo esc_html__("Full Amount", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Bank number", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Bank number", "payplus-payment-gateway") ?>"
                                type="text" class="payment-check-payment-payplus input-change bank_number">
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Branch number", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Branch number", "payplus-payment-gateway") ?>"
                                type="text" class="payment-check-payment-payplus input-change branch_number">
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Account number", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Account number", "payplus-payment-gateway") ?>"
                                type="text" class="payment-check-payment-payplus input-change account_number">
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Check number", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Check number", "payplus-payment-gateway") ?>"
                                type="text" class="payment-check-payment-payplus input-change check_number">
                        </div>
                    </div>
                    <div class="flex-row flex-row-reverse">
                        <div class="flex-item">
                            <button id="payment-check-payment-payplus" class="payplus-payment-button">
                                <?php echo esc_html__("Save payment", "payplus-payment-gateway") ?> </button>
                        </div>
                    </div>
                </div>
                <div class="select-type-payment bank-transfer">
                    <input class="bank-transfer-payment-payplus input-change  row_id" type="hidden" value="">
                    <input class="bank-transfer-payment-payplus input-change  method_payment" type="hidden" id="method_payment"
                        name="method_payment" value="bank-transfer">
                    <div class="flex-row">
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Date", "payplus-payment-gateway") ?></label>
                            <input value="<?php echo esc_attr(gmdate("Y-m-d")) ?>" required
                                class="bank-transfer-payment-payplus  input-change create_at" type="date"
                                placeholder="<?php echo esc_attr__("Date", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item full-amount">
                            <label><?php echo esc_html__("Sum", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <input data-sum="<?php echo esc_attr($order->get_total()) ?>" step="0.01" min="1"
                                    max="<?php echo esc_attr($order->get_total()) ?>"
                                    class="bank-transfer-payment-payplus input-change price" type="number"
                                    placeholder="<?php echo esc_attr__("Sum", "payplus-payment-gateway") ?>"
                                    value="<?php echo esc_attr(floatval($order->get_total())) ?>">
                                <button data-sum="<?php echo esc_attr($order->get_total()) ?> "
                                    class="payplus-full-amount bank-transfer-payment-payplus">
                                    <?php echo esc_html__("Full Amount", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Bank number", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Bank number", "payplus-payment-gateway") ?>"
                                type="text" class="bank-transfer-payment-payplus input-change bank_number">
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Branch number", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Branch number", "payplus-payment-gateway") ?>"
                                type="text" class="bank-transfer-payment-payplus input-change branch_number">

                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Account number", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Account number", "payplus-payment-gateway") ?>"
                                type="text" class="bank-transfer-payment-payplus input-change account_number">
                        </div>

                    </div>
                    <div class="flex-row flex-row-reverse">
                        <div class="flex-item">
                            <button id="bank-transfer-payment-payplus" class="payplus-payment-button">
                                <?php echo esc_html__("Save payment", "payplus-payment-gateway") ?> </button>
                        </div>
                    </div>
                </div>
                <div class="select-type-payment payment-app">
                    <input class="payment-app-payment-payplus input-change  row_id" type="hidden" value="">
                    <input class="payment-app-payment-payplus input-change  method_payment" type="hidden" id="method_payment"
                        name="method_payment" value="payment-app">
                    <div class="flex-row">
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Date", "payplus-payment-gateway") ?></label>
                            <input value="<?php echo esc_attr(gmdate("Y-m-d")) ?>" required
                                class="payment-app-payment-payplus input-change create_at" type="date"
                                placeholder="<?php echo esc_attr__("Date", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item full-amount">
                            <label><?php echo esc_html__("Sum", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <input data-sum="<?php echo esc_attr($order->get_total()) ?>" step="0.01" min="1"
                                    max="<?php echo esc_attr($order->get_total()) ?>"
                                    class="payment-app-payment-payplus input-change price" type="number"
                                    placeholder="<?php echo esc_attr__("Sum", "payplus-payment-gateway") ?>"
                                    value="<?php echo esc_attr(floatval($order->get_total())) ?>">
                                <button data-sum="<?php echo esc_attr($order->get_total()) ?>"
                                    class="payplus-full-amount payment-app-payment-payplus">
                                    <?php echo esc_html__("Full Amount", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Type", "payplus-payment-gateway") ?></label>
                            <select class="payment-app-payment-payplus input-change payment_app">
                                <option value="">
                                    <?php echo esc_html__("Type", "payplus-payment-gateway") ?>
                                </option>
                                <?php
                                foreach ($installed_payment_methods as $installed_payment_method) : ?>
                                    <option value="<?php echo esc_attr($installed_payment_method) ?>">
                                        <?php echo esc_html($installed_payment_method) ?>
                                    </option>
                                <?php
                                endforeach;
                                ?>
                            </select>
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Transaction id", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Transaction id", "payplus-payment-gateway") ?>"
                                type="text" class="payment-app-payment-payplus input-change transaction_id">
                        </div>

                    </div>
                    <div class="flex-row flex-row-reverse">
                        <div class="flex-item">
                            <button id="payment-app-payment-payplus" class="payplus-payment-button">
                                <?php echo esc_html__("Save payment", "payplus-payment-gateway") ?> </button>
                        </div>
                    </div>
                </div>
                <div class="select-type-payment paypal">
                    <input class="paypal-payment-payplus input-change  row_id" type="hidden" value="">
                    <input class="paypal-payment-payplus input-change  method_payment" type="hidden" id="method_payment"
                        name="method_payment" value="paypal">
                    <div class="flex-row">
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Date", "payplus-payment-gateway") ?></label>
                            <input value="<?php echo esc_attr(gmdate("Y-m-d")) ?>" required
                                class="paypal-payment-payplus  input-change create_at" type="date"
                                placeholder="<?php echo esc_attr__("Date", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item full-amount">
                            <label><?php echo esc_html__("Sum", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <input data-sum="<?php echo esc_attr($order->get_total()) ?>" step="0.01" min="1"
                                    max="<?php echo esc_attr($order->get_total()) ?>"
                                    class="paypal-payment-payplus input-change price" type="number"
                                    placeholder="<?php echo esc_attr__("Sum", "payplus-payment-gateway") ?>"
                                    value="<?php echo esc_attr(floatval($order->get_total())) ?>">
                                <button data-sum="<?php echo esc_attr($order->get_total()) ?>"
                                    class="payplus-full-amount paypal-payment-payplus">
                                    <?php echo esc_html__("Full Amount", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Payer account", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Payer account", "payplus-payment-gateway") ?>"
                                type="text" class="paypal-payment-payplus input-change payer_account">
                        </div>

                        <div class="flex-item">
                            <label> <?php echo esc_html__("Transaction id", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Transaction id", "payplus-payment-gateway") ?>"
                                type="text" class="paypal-payment-payplus input-change transaction_id">
                        </div>

                    </div>
                    <div class="flex-row flex-row-reverse">
                        <div class="flex-item">
                            <button id="paypal-payment-payplus" class="payplus-payment-button">
                                <?php echo esc_html__("Save payment", "payplus-payment-gateway") ?> </button>
                        </div>
                    </div>
                </div>
                <div class="select-type-payment withholding-tax">
                    <input class="withholding-tax-payment-payplus input-change  row_id" type="hidden" value="">
                    <input class="withholding-tax-payment-payplus input-change  method_payment" type="hidden" id="method_payment"
                        name="method_payment" value="withholding-tax">
                    <div class="flex-row">
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Date", "payplus-payment-gateway") ?></label>
                            <input value="<?php echo esc_attr(gmdate("Y-m-d")) ?>" required
                                class="withholding-tax-payment-payplus input-change create_at" type="date"
                                placeholder="<?php echo esc_attr__("Date", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item full-amount">
                            <label><?php echo esc_html__("Sum", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <input data-sum="<?php echo esc_attr($order->get_total()) ?>" step="0.01" min="1"
                                    max="<?php echo esc_attr($order->get_total()) ?>"
                                    class="withholding-tax-payment-payplus input-change price" type="number"
                                    placeholder="<?php echo esc_attr__("Sum", "payplus-payment-gateway") ?>"
                                    value="<?php echo esc_attr(floatval($order->get_total())) ?>">
                                <button data-sum="<?php echo esc_attr($order->get_total()) ?>"
                                    class="payplus-full-amount withholding-tax-payment-payplus">
                                    <?php echo esc_html__("Full Amount", "payplus-payment-gateway") ?> </button>
                            </div>

                        </div>

                    </div>
                    <div class="flex-row flex-row-reverse">
                        <div class="flex-item">
                            <button id="withholding-tax-payment-payplus" class="payplus-payment-button">
                                <?php echo esc_html__("Save payment", "payplus-payment-gateway") ?> </button>
                        </div>
                    </div>
                </div>
                <div class="select-type-payment other">
                    <input class="other-payment-payplus input-change  row_id" type="hidden" value="">
                    <input class="other-payment-payplus input-change  method_payment" type="hidden" id="method_payment"
                        name="method_payment" value="other">
                    <div class="flex-row">
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Date", "payplus-payment-gateway") ?></label>
                            <input value="<?php echo esc_attr(gmdate("Y-m-d")) ?>" required
                                class="other-payment-payplus  input-change create_at" type="date"
                                placeholder="<?php echo esc_attr__("Date", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item full-amount">
                            <label><?php echo esc_html__("Sum", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <input data-sum="<?php echo esc_attr($order->get_total()) ?>" step="0.01" min="1"
                                    max="<?php echo esc_attr($order->get_total()) ?>"
                                    class="other-payment-payplus input-change price" type="number"
                                    placeholder="<?php echo esc_attr__("Sum", "payplus-payment-gateway") ?>"
                                    value="<?php echo esc_attr(floatval($order->get_total())) ?>">
                                <button data-sum="<?php echo esc_attr($order->get_total()) ?>"
                                    class="payplus-full-amount other-payment-payplus">
                                    <?php echo esc_html__("Full Amount", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Transaction id", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Transaction id", "payplus-payment-gateway") ?>"
                                type="text" class="other-payment-payplus input-change transaction_id">

                        </div>
                        <div class="flex-item">
                            <label> <?php echo esc_html__("Notes", "payplus-payment-gateway") ?></label>
                            <input value="" placeholder="<?php echo esc_attr__("Notes", "payplus-payment-gateway") ?>" type="text"
                                class="other-payment-payplus input-change notes">
                        </div>
                    </div>
                    <div class="flex-row flex-row-reverse">
                        <div class="flex-item">
                            <button id="other-payment-payplus" class="payplus-payment-button">
                                <?php echo esc_html__("Save payment", "payplus-payment-gateway") ?> </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php

        }
        $this->payplus_get_table_paypment($orderId, $currentStatus, $payments);
        if (empty($checkInvoiceSend)) :
        ?>
            <div class="flex-row">
                <div class="flex-item payplus-create-invoice">
                    <button id="payplus-create-invoice" data-id="<?php echo esc_attr($orderId) ?>"
                        class="button  button-primary"><span
                            class="refund_text"><?php echo esc_html__("Create Document", "payplus-payment-gateway") ?></span></button>
                    <div class='payplus_loader_gpp'>
                        <div class='loader'>
                            <div class='loader-background'>
                                <div class='text'></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        endif;
    }

    public function payplus_get_table_paypment($orderId, $currentStatus, $payments)
    {
        $order = wc_get_order($orderId);
        $chackStatus = array('inv_receipt', 'inv_tax_receipt');
        $chackAllPaymentTable = in_array($currentStatus, $chackStatus) ? "table" : 'none';
        ?>
        <table data-method="<?php echo esc_attr((strpos($order->get_payment_method(), 'payplus') !== false)) ? true : false ?>"
            id="payplus-table-payment" style="display: <?php echo esc_attr($chackAllPaymentTable) ?>"
            class="wc-order-totals payplus-table-payment">
            <thead>
                <tr>
                    <th><img style="display: block; margin: auto; padding: 1px 0 2px 0;"
                            src='<?php echo esc_url(PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "Invoice+logo.png"); ?>'></th>
                    <th><?php echo esc_html__("Sum", "payplus-payment-gateway") ?></th>
                    <th><?php echo esc_html__("Details", "payplus-payment-gateway") ?></th>
                    <th><?php echo esc_html__("Methods of Payment", "payplus-payment-gateway") ?></th>
                    <th><?php echo esc_html__("Date", "payplus-payment-gateway") ?></th>

                </tr>
            </thead>
            <tbody>
                <?php

                $detailsAll = [
                    'bank_number',
                    'account_number',
                    'branch_number',
                    'check_number',
                    'four_digits',
                    'brand_name',
                    'transaction_type',
                    'number_of_payments',
                    'first_payment',
                    'subsequent_payments',
                    'payment_app',
                    'transaction_id',
                    'payer_account',
                    'notes'
                ];

                if (is_array($payments)) {
                    foreach ($payments as $key => $payment) {
                        $payment->method_payment = property_exists($payment, 'method_payment') ? $payment->method_payment : $payment->method;
                        $payment->create_at = property_exists($payment, 'create_at') ? $payment->create_at : $payment->date;
                        $payment->price = property_exists($payment, 'price') ? $payment->price : $payment->amount * 100;
                        $create_at = explode(' ', $payment->create_at);
                        $create_at = explode('-', $create_at[0]);
                        $create_at = $create_at[2] . "-" . $create_at[1] . "-" . $create_at[0];
                        $orderAmount = WC_PayPlus_Meta_Data::get_meta($orderId, 'payplus_charged_j5_amount', true) ? WC_PayPlus_Meta_Data::get_meta($orderId, 'payplus_charged_j5_amount', true) : $payment->price / 100;
                        $currency_code = $order->get_currency();
                        // Get the currency symbol based on the currency code
                        $currency_symbol = get_woocommerce_currency_symbol($currency_code);

                ?>
                        <tr>
                            <td><img style="display: block; margin: auto;"
                                    src='<?php echo esc_url(PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "PayPlusLogo.svg"); ?>'></td>
                            <td>
                                <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">
                                            <?php echo esc_html($currency_symbol) ?></span><?php echo esc_html($orderAmount) ?></bdi></span>
                            </td>

                            <td>
                                <?php
                                foreach ($payment as $key => $value) {
                                    if (in_array($key, $detailsAll)) :

                                        if ($value) :
                                            if ($key == "first_payment" || $key == 'subsequent_payments') :
                                                $value /= 100;
                                            endif;
                                            $keyCurrent = str_replace("_", " ", ucfirst($key));
                                ?>
                                            <p> <strong> <?php echo esc_html($keyCurrent) ?> </strong> : <?php echo esc_html($value) ?> </p>
                                <?php
                                        endif;
                                    endif;
                                }
                                ?>

                            </td>
                            <td> <?php echo esc_html(str_replace("-", ' ', $payment->method_payment)) ?></td>
                            <td> <?php echo esc_html($create_at) ?></td>
                        </tr>

                <?php
                    }
                }


                ?>
            </tbody>
        </table>
        <div id="payplus_sum_payment"></div>
        <?php
    }

    /**
     * @param $order
     * @return void
     */
    public function add_custom_button_to_order($order)
    {
        if ($order->get_status() == 'pending') {
            $payplusResponse = WC_PayPlus_Meta_Data::get_meta($order->get_id(), 'payplus_response', true);
            $pageRequestUid = WC_PayPlus_Meta_Data::get_meta($order->get_id(), 'payplus_page_request_uid', true);
            if ($payplusResponse !== "" || $pageRequestUid !== "") {
                $payplusResponse = json_decode($payplusResponse, true);

                if (isset($payplusResponse['page_request_uid'])) {
                    $pageRequestUid = $payplusResponse['page_request_uid'];
                }
                // check if is rtl or ltr
                $rtl = is_rtl() ? 'left' : 'right';
                // show button only if pageRequestUid is not empty
                if (!empty($pageRequestUid)) {
                    echo '<button type="button" data-value="' . esc_attr($order->get_id()) . '" value="' . esc_attr($pageRequestUid) . '" class="button" id="custom-button-get-pp" style="position: absolute;' . esc_attr($rtl) . ': 5px; top: 0; margin: 10px 0 0 0; color: white; background-color: #35aa53; border-radius: 15px;">Get PayPlus Data</button>';
                    echo "<div class='payplus_loader_gpp'>
                        <div class='loader'>
                          <div class='loader-background'><div class='text'></div></div>
                        </div>
                      </div>";
                }
            }
        }
    }

    /**
     * @param $orderId
     * @return void
     */
    public function payplus_woocommerce_admin_order_totals_after_total($orderId)
    {

        global $wpdb;
        $this->isInitiated();
        $order = wc_get_order($orderId);
        $transaction_uid = $order->get_meta('payplus_transaction_uid');
        $order_validated = $order->get_meta('order_validated');
        $order_validated_error = $order->get_meta('order_validated_error');

        $invoice_manual = $this->payPlusInvoice->payplus_get_create_invoice_manual();
        $checkInvoiceSend = WC_PayPlus_Meta_Data::get_meta($orderId, 'payplus_check_invoice_send', true);
        $resultApps = $this->payPlusInvoice->payplus_get_payments($orderId, 'otherClub');
        $checkInvoiceRefundSend = WC_PayPlus_Meta_Data::get_meta($orderId, 'payplus_send_refund', true);
        $sum = 0;

        $sumTransactionRefund = array_reduce($resultApps, function ($sum, $item) {
            return $sum + $item->invoice_refund;
        });

        $sumTransactionRefund = $sumTransactionRefund !== null ? $sumTransactionRefund : WC_PayPlus_Meta_Data::get_meta($orderId, 'payplus_total_refunded_amount', true) * 100;

        $total = floatval($order->get_total());
        $payplus_related_transactions = WC_PayPlus_Meta_Data::get_meta($orderId, 'payplus_related_transactions', true);
        $payplus_response = json_decode(WC_PayPlus_Meta_Data::get_meta($orderId, 'payplus_response', true));
        $payplus_response = (array) $payplus_response;
        $selectInvoiceRefund = array(
            '' => __('Type Documents Refund', 'payplus-payment-gateway'),
            'inv_refund' => __('Refund Invoice', 'payplus-payment-gateway'),
            'inv_refund_receipt' => __('Refund Receipt', 'payplus-payment-gateway'),
            'inv_refund_receipt_invoice' => __('Refund Invoice + Refund Receipt', 'payplus-payment-gateway')
        );

        $order = wc_get_order($orderId);
        $user_id = $order->get_user_id();

        ob_start();
        $tokenOrderPayment = boolval(isset($this->allSettings['token_order_payment']) && $this->allSettings['token_order_payment'] === 'yes');
        if ($order->get_status() === "pending" && $user_id > 0 && empty($payplus_response) && $tokenOrderPayment && $total !== 0.0) {
            $customerTokens = WC_Payment_Tokens::get_customer_tokens($user_id);
            $status = WC_PayPlus_Meta_Data::get_meta($order, 'payplus_status_code');
            $theTokens = [];

            foreach ($customerTokens as $customerToken) {
                $theTokens[$customerToken->get_last4()]['token'] = $customerToken->get_token();
                $theTokens[$customerToken->get_last4()]['type'] = $customerToken->get_card_type();
            };
            $payplusOrderPayments = WC_PayPlus_Meta_Data::get_meta($order, 'payplus_order_payments');
            if (!empty($theTokens) && !$payplusOrderPayments) {
        ?>
                <select type="select" id="ccToken">
                    <?php
                    foreach ($theTokens as $key => $token) {
                    ?>
                        <option id="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($token['token']); ?>">
                            <?php echo esc_attr($key); ?></option>
                    <?php
                    }
                    ?>
                </select>
                <button id="makeTokenPayment" data-token="<?php echo esc_attr($token); ?>"
                    data-id="<?php echo esc_attr((int)$orderId); ?>">
                    <?php echo esc_html__('Pay With Token', 'payplus-payment-gateway'); ?>
                </button>
            <?php
            }
        }

        if (!empty($payplus_related_transactions) && !WC_PayPlus::payplus_check_exists_table(wp_create_nonce('PayPlusGateWayNonce'))) {
            ?>
            <table class="wc-order-totals payplus-table-refund">
                <tr class="payplus-row">
                    <th><img style="height: 30px; margin: auto; display: block; padding: 1px 0 2px 0;"
                            src="<?php echo esc_url(PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "PayPlusLogo.svg"); ?>"></th>
                    <th><?php echo esc_html__('Refund amount', 'payplus-payment-gateway'); ?></th>
                    <th><?php echo esc_html__('Amount already refunded', 'payplus-payment-gateway'); ?></th>
                    <th><?php echo esc_html__('Sum', 'payplus-payment-gateway'); ?></th>
                    <th><?php echo esc_html__('Methods of Payment', 'payplus-payment-gateway'); ?></th>
                </tr>
                <?php
                $result = $this->payplus_get_order_payment($orderId);
                if (!count($result)) {
                    if (count($payplus_response)) {
                    } else {
                        $transaction_uid = WC_PayPlus_Meta_Data::get_meta($orderId, 'payplus_transaction_uid', true);

                        if (!empty($transaction_uid)) {
                            $payload['transaction_uid'] = $transaction_uid;
                        } else {
                            $payload['more_info'] = $orderId;
                        }
                        $payload['related_transaction'] = true;
                        $payload = wp_json_encode($payload);
                        $data['order_id'] = $orderId;
                        $res = $this->requestPayPlusIpn($payload, $data, 1, 'payplus_process_payment', true);
                        WC_PayPlus_Meta_Data::update_meta($order, array('payplus_response' => wp_json_encode($res->data, true)));
                    }
                    $result = $this->payplus_get_order_payment($orderId);
                }
                if (count($result)) :
                    foreach ($result as $key => $values) :
                        if (!empty($values->method_payment)) :
                            $refund = ($values->price / 100) - ($values->refund / 100);

                ?>

                            <tr class="payplus-row coupon-<?php echo esc_attr($values->id) ?>">

                                <td>
                                    <?php

                                    if ($refund) : ?>
                                        <button data-refund="<?php echo esc_attr($refund) ?>"
                                            data-method='<?php echo esc_attr($values->method_payment) ?>'
                                            data-id="<?php echo esc_attr($values->id) ?>"
                                            data-transaction-uid="<?php echo esc_attr($values->transaction_uid) ?>"
                                            class="button button-primary width-100 do-api-refund-payplus">
                                            <span class="refund_text"><?php echo esc_html__('Refund', 'payplus-payment-gateway'); ?></span></button>
                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?php
                                    if ($refund) : ?>
                                        <input class="width-100 sum-coupon-<?php echo esc_attr($values->id) ?>" type="number" step="0.1" min="0"
                                            max="<?php echo esc_attr($refund) ?>" value="0" />
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <bdi><?php echo esc_html($values->refund / 100) ?>&nbsp;<span
                                            class="woocommerce-Price-currencySymbol">₪</span></bdi>
                                </td>
                                <td>
                                    <span class="woocommerce-Price-amount amount"><bdi><?php echo esc_html($values->price / 100) ?>&nbsp;<span
                                                class="woocommerce-Price-currencySymbol">₪</span></bdi></span>
                                </td>
                                <td class="label label-highlight"><?php echo esc_html($values->method_payment) ?>
                                </td>
                            </tr>
                <?php
                        endif;
                    endforeach;
                endif;
                ?>
            </table>
        <?php

        }

        if (
            $order->get_status() != 'auto-draft' && $order->get_status() != 'on-hold' && $total && $this->payPlusInvoice->payplus_get_invoice_enable()
            && $invoice_manual
        ) {

            if (empty($checkInvoiceSend)) :
                $this->payplus_get_section_invoice_not_automatic($orderId, $theTokens = []);
            endif;
        }

        $orderRefunded = boolval($order->get_total_refunded() === $total && $sumTransactionRefund === "");

        if (
            $total && $this->payPlusInvoice->payplus_get_invoice_enable()
            && $invoice_manual && $sumTransactionRefund && !$checkInvoiceRefundSend && !$orderRefunded
        ) {
        ?>
            <div class="payment-order-ajax  payment-invoice" style="margin:20px 0px">
                <input type="hidden" name="amount-refund-<?php echo esc_attr($orderId) ?>"
                    id="amount-refund-<?php echo esc_attr($orderId) ?>" value="<?php echo esc_attr($sumTransactionRefund / 100) ?>">
                <select id="select-type-invoice-refund-<?php echo esc_attr($orderId) ?>"
                    name="select-type-invoice-refund-<?php echo esc_attr($orderId) ?>">
                    <?php

                    foreach ($selectInvoiceRefund as $key => $value) :
                        $flag = true;
                        if ($flag) :
                            $selected = ($this->payPlusInvoice->payplus_get_invoice_type_document_refund() == $key) ?
                                'selected' : '';
                    ?>
                            <option <?php echo esc_attr($selected) ?> value="<?php echo esc_attr($key) ?>"><?php echo esc_html($value) ?>
                            </option>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </select>
                <button id="payplus-create-invoice-refund" data-id="<?php echo esc_attr($orderId) ?>"
                    class="button  button-primary"><span
                        class="refund_text"><?php echo esc_html__("Create Refund Document", "payplus-payment-gateway") ?></span></button>

            </div>
        <?php
        }
        if (($order->get_type() === "shop_subscription" && $order->get_status() === "on-hold")
            || $order_validated_error === "1"
        ) {
        ?>
            <div class="payment-order-ajax">
                <button id="payment-payplus-transaction" data-id="<?php echo esc_attr($orderId) ?>"
                    class="button  button-primary"><?php echo esc_html__("Transaction review", "payplus-payment-gateway") ?></button>
                <div class="payplus_loader">
                    <div class="loader">
                        <div class="loader-background">
                            <div class="text"></div>
                        </div>
                    </div>
                </div>
            </div>

        <?php
        }
        $flagPayment = !floatval($order->get_total()) || !empty($transaction_uid) || $order_validated === "1" || $this->enabled === "no" || $order->get_status() !== "pending";

        if (!$flagPayment && !$order_validated_error && empty($checkInvoiceSend)) {
        ?>
            <div class="payment-order-ajax">
                <button id="payment-payplus-dashboard" data-id="<?php echo esc_attr($orderId) ?>"
                    class="button  button-primary"><?php echo esc_html__("Payment", "payplus-payment-gateway") ?></button>
                <div class="payplus_loader">
                    <div class="loader">
                        <div class="loader-background">
                            <div class="text"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="box-payplus-payment" style="display: none">
                <iframe scrolling="no" src="" style="width: 100%;height: 900px"></iframe>
            </div>

<?php
        }
        $output = ob_get_clean();
        echo $output;
    }

    /**
     * @return void
     */
    public function ajax_payplus_token_payment()
    {
        check_ajax_referer('payplus_token_payment', '_ajax_nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('You do not have permission to edit orders.');
            wp_die();
        }

        $totalCartAmount = 0;
        $handle = 'payplus_process_j5_payment';
        $urlEdit = site_url();
        if (!empty($_POST)) {
            $postPayPlus = $_POST;
            $this->isInitiated();
            $order_id = $postPayPlus['payplus_order_id'];
            $payplusTokenPayment = $postPayPlus['payplus_token_payment'];
            $payplusChargeAmount = $postPayPlus['payplus_charge_amount'];
            $urlEdit = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => false));
                wp_die();
            }

            if (!($payplusTokenPayment) || !$payplusChargeAmount) {
                echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => false));
                wp_die();
            }
            $order = wc_get_order($order_id);
            $charged_amount = (float) $order->get_meta('payplus_charged_j5_amount');

            if ($charged_amount) {
                return;
            }

            $OrderType = $order->get_meta('payplus_type');

            $chargeByItems = false;
            $amount = round((float) $payplusChargeAmount, 2);
            $transaction_uid = $order->get_meta('payplus_transaction_uid');
            if (empty($transaction_uid)) {
                $transaction_uid = json_decode($order->get_meta('payplus_response'), true)['transaction_uid'];
                WC_PayPlus_Meta_Data::update_meta($order, array('transaction_uid' => $transaction_uid));
            }
            if ($OrderType == "Charge") {
                echo esc_url($urlEdit);
                wp_die();
            }

            if ($OrderType != "Approval" and $OrderType != "Check") {
                $order->add_order_note(sprintf('The charge in PayPlus already made. Please check your PayPlus account<br />Amount: %s %s', $charged_amount, $order->get_currency()));
                echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => false));
                wp_die();
            }

            if ($OrderType != "Approval" and $OrderType != "Check") {
                $this->payplus_add_log_all($handle, 'Transaction Not J5 Or Changed to J4 After Charge');

                echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => false));
                wp_die();
            }
            if ($amount == $order->get_total() and $charged_amount == 0) {
                $chargeByItems = true;
                $objectProducts = $this->payplus_get_products_by_order_id($order_id, true);
            }
            $totalCartAmount = $objectProducts->amount;
            $payplusRefunded = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_refunded', true);
            if (!$payplusRefunded) {
                WC_PayPlus_Meta_Data::update_meta($order, array('payplus_refunded' => $order->get_total()));
            }

            $payload = '{
                        "transaction_uid": "' . $transaction_uid . '",
                        ' . ($this->send_add_data ? '"add_data": "' . $order_id . '",' : '') . '
                        "amount": "' . ($chargeByItems ? $totalCartAmount : $amount) . '",
                                   ' . ($chargeByItems ? '
                                    "items": [
                                                ' . implode(",", $objectProducts->productsItems) . '
                                            ],' : '') . '
                        "more_info": "' . __('Charge for Order Number: ', 'payplus-payment-gateway') . $order_id . '"
                        }';

            $this->payplus_add_log_all($handle, 'New Payment Process Fired (' . $order_id . ')');
            $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');

            $response = $this->post_payplus_ws($this->api_url . 'Transactions/ChargeByTransactionUID', $payload);

            if (is_wp_error($response)) {
                $this->payplus_add_log_all($handle, print_r($response, true), 'error');
                echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => false));
                wp_die();
            } else {
                $insertMeta = array();
                $res = json_decode(wp_remote_retrieve_body($response));

                if ($res->results->status == "success" && $res->data->transaction->status_code == "000") {
                    $this->payplus_add_log_all($handle, print_r($response, true), 'completed');
                    if ($this->payplus_check_all_product($order, "1")) {
                        $insertMeta['payplus_transaction_type'] = "1";
                    }
                    if ($this->payplus_check_all_product($order, "2")) {
                        $insertMeta['payplus_transaction_type'] = "2";
                    }
                    $insertMeta['payplus_charged_j5_amount'] = $amount;

                    $keyMethod = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_alternative_method_name', true);
                    if (empty($keyMethod)) {
                        $keyMethod = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_method', true);
                    }
                    $insertMeta['payplus_' . $keyMethod] = $amount;
                    $insertMeta['payplus_type'] = 'Charge';
                    $insertMeta['payplus_number'] = $res->data->transaction->number;
                    $insertMeta['payplus_transaction_uid'] = $res->data->transaction->uid;
                    $insertMeta['payplus_status_code'] = $res->data->transaction->status_code;
                    $insertMeta['payplus_number_of_payments'] = $res->data->transaction->payments->number_of_payments;
                    $insertMeta['payplus_first_payment_amount'] = $res->data->transaction->payments->first_payment_amount;
                    $insertMeta['payplus_payments_rest_payments_amount'] = $res->data->transaction->payments->rest_payments_amount;
                    $insertMeta['payplus_auth_num'] = $res->data->transaction->approval_number;
                    if (property_exists($res->data->transaction, 'voucher_number')) {
                        $insertMeta['payplus_voucher_num'] = trim($res->data->transaction->voucher_number);
                    }
                    if (property_exists($res->data->transaction, 'alternative_method_name')) {
                        $insertMeta['payplus_alternative_method_name'] = trim($res->data->transaction->alternative_method_name);
                    }
                    $order->add_order_note(sprintf('PayPlus Charge is Successful<br />Charge Transaction Number: %s<br />Amount: %s %s', $res->data->transaction->number, $res->data->transaction->amount, $order->get_currency()));
                    WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
                    $_POST['order_status'] = $order->needs_processing() ? 'wc-processing' : 'wc-completed';
                    $order->payment_complete();

                    echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => true));
                    wp_die();
                } else {
                    $this->payplus_add_log_all($handle, print_r($response, true), 'error');
                    $order->add_order_note(sprintf('PayPlus Charge is Failed<br />Status: %s<br />Description: %s', $res->results->status, $res->results->description));
                    $this->error_msg = 2;
                    echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => false));
                    wp_die();
                }
            }
            add_filter('redirect_post_location', [$this, 'add_notice_query_var'], 99);
        }
        echo wp_json_encode(array("urlredirect" => $urlEdit, "status" => false));
        wp_die();
    }

    /**
     * @return void
     */
    public function load_admin_assets()
    {
        if (!wp_verify_nonce($this->_wpnonce, 'PayPlusGateWayAdminNonce')) {
            wp_die('Not allowed! - load_admin_assets');
        }
        $enabled = false;
        $isInvoice = false;
        if (!empty($_GET) && !empty($_GET['section'])) {
            $currentSection = sanitize_text_field(wp_unslash($_GET['section']));
            $currentPayment = get_option('woocommerce_' . $currentSection . '_settings');
            $enabled = (isset($currentPayment['enabled']) && $currentPayment['enabled'] === "yes") ? false : true;
            $isInvoice = (!empty($_GET['invoicepayplus']) && $_GET['invoicepayplus'] === "1") ? true : false;
        }

        if (isset($_GET['post']) && isset($_GET['action']) && $_GET['action'] === 'edit') {
            $order_id = intval($_GET['post']);
        }

        if (!empty($order_id)) {
            $order = wc_get_order($order_id);
            // Get the currency code
            $currency_code = $order->get_currency();
            // Get the currency symbol based on the currency code
            $currency_symbol = get_woocommerce_currency_symbol($currency_code);
        } else {
            $currency_symbol = get_woocommerce_currency_symbol();
        }

        $current_language = get_locale();
        $transactionType = $this->get_option('transaction_type');

        wp_enqueue_style('payplus', PAYPLUS_PLUGIN_URL . 'assets/css/admin.css', [], PAYPLUS_VERSION);
        wp_register_script('payplus-admin-payment', PAYPLUS_PLUGIN_URL . '/assets/js/admin-payments.min.js', ['jquery'], time(), true);
        wp_localize_script(
            'payplus-admin-payment',
            'payplus_script_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'error_payment' => __('Cannot charge more than original order sum!', 'payplus-payment-gateway'),
                "payplus_title_tab" => array(
                    "tab-payplus-error-page" => __('PayPlus Page Error - Settings', 'payplus-payment-gateway'),
                    "tab-invoice-payplus" => __('Invoice+ (PayPlus)', 'payplus-payment-gateway')
                ),
                "currentLanguage" => $current_language,
                "payplus_enabled_payment" => $enabled,
                "payplusTransactionType" => $this->transactionType,
                "payplus_invoice" => $isInvoice,
                "testMode" => $this->api_test_mode,
                "payplus_refund_error" => __('Incorrect amount or amount greater than amount that can be refunded', 'payplus-payment-gateway'),
                "menu_option" => WC_PayPlus::payplus_get_admin_menu(wp_create_nonce('menu_option')),
                "payplus_refund_club_amount" => wp_create_nonce('payplus_refund_club_amount'),
                "payplusApiPaymentRefund" => wp_create_nonce('payplus_api_payment_refund'),
                "payplusTokenPayment" => wp_create_nonce('payplus_token_payment'),
                "payplusApiPayment" => wp_create_nonce('payplus_api_payment'),
                "payplusTransactionReview" => wp_create_nonce('payplus_transaction_review'),
                "payplusGenerateLinkPayment" => wp_create_nonce('payplus_generate_link_payment'),
                "payplusCustomAction" => wp_create_nonce('payplus_payplus_ipn'),
                "frontNonce" => wp_create_nonce('frontNonce'),
                "isApplePayEnabled" => $this->isApplePayEnabled,
                "tokenPaymentConfirmMessage" => __('Are you sure you want to charge this order with token of CC that ends with: ', 'payplus-payment-gateway'),
            )
        );
        wp_enqueue_script('payplus-admin-payment');
        wp_register_script('wc-payplus-gateway-admin', PAYPLUS_PLUGIN_URL . 'assets/js/admin.min.js', ['jquery'], time(), true);
        wp_localize_script(
            'wc-payplus-gateway-admin',
            'payplus_script_payment',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'error_payment_sum' => __('Total payment amounts are not equal to the order sum', 'payplus-payment-gateway'),
                'error_payment_sum_withholding_tax' => __('The amount of receipts is not equal to the amount of withholding tax clearance', 'payplus-payment-gateway'),
                'error_payment_select_doc' => __('No document type selected', 'payplus-payment-gateway'),
                'btn_edit' => __('Edit', 'payplus-payment-gateway'),
                'btn_delete' => __('Delete', 'payplus-payment-gateway'),
                'error_price' => __('The payment item cannot be 0', 'payplus-payment-gateway'),
                'currency_symbol' => $currency_symbol,
                'transactionType' => $transactionType,
                'payplus_sum' => __('Total payments', 'payplus-payment-gateway'),
                'delete_confim' => __('Are you sure you want to delete this payment method?', 'payplus-payment-gateway'),
                'create_invoice_refund_nonce' => wp_create_nonce('create_invoice_refund_nonce'),
                'create_invoice_nonce' => wp_create_nonce('create_invoice_nonce'),
                "frontNonce" => wp_create_nonce('frontNonce'),
            )
        );
        wp_enqueue_script('wc-payplus-gateway-admin');
    }

    /**
     * @param $post_id
     * @return void
     */
    public function make_payment_button($post_id)
    {
        $this->isInitiated();

        if (!in_array(WC_PayPlus_Meta_Data::get_meta($post_id, 'payplus_type', true), ["Approval", "Check"])) {
            return;
        }

        $order = wc_get_order($post_id);
        $total = $order->get_total();

        $class = ($this->check_amount_authorization) ? 'payplus-visibility' : '';
        echo "<li class='wide delayed-payment'>
                    <h3>" . esc_html__('Charge Order Using PayPlus', 'payplus-payment-gateway') . "</h3>
                        <input class='" . esc_attr($class) . "'  data-amount='" . esc_attr($total) . "'  type='number' id='payplus_charge_amount' name='payplus_charge_amount' value='" . esc_attr($total) . "' min='0' max='" . esc_attr($total) . "' step='0.01' required />
                        <input type='hidden' id='payplus_order_id' name='payplus_order_id' value='" . esc_attr($post_id) . "'>
                        <button id='payplus-token-payment' type='button' name='payplus-token-payment' class='button button-primary'><span class='dashicons dashicons-cart'></span> " . esc_html__('Make Payment', 'payplus-payment-gateway') . "</button>
                <div class='payplus_error'></div>
                     <div class='payplus_loader'>
      <div class='loader'>
        <div class='loader-background'><div class='text'></div></div>
      </div>
    </div>
                </li>";
    }

    /**
     * @param int $order_id
     * @return false|void
     */
    public function process_make_payment($order_id)
    {

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST['payplus-token-payment'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        $order = wc_get_order($order_id);
        $handle = 'payplus_process_j5_payment';
        $this->isInitiated();
        $charged_amount = 0;
        $charged_amount = (float) $order->get_meta('payplus_charged_j5_amount');
        if ($charged_amount) {
            return;
        }

        $OrderType = $order->get_meta('payplus_type');
        $chargeByItems = false;

        $amount = isset($_POST['payplus_charge_amount']) ? round((float) $_POST['payplus_charge_amount'], 2) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $transaction_uid = $order->get_meta('payplus_transaction_uid');
        if ($OrderType == "Charge") {
            return;
        }

        if ($OrderType != "Approval" and $OrderType != "Check") {
            $this->payplus_add_log_all($handle, 'Transaction Not J5 Or Changed to J4 After Charge');
            $order->add_order_note(sprintf('The charge in PayPlus already made. Please check your PayPlus account<br />Amount: %s %s', $charged_amount, $order->get_currency()));
            return false;
        }
        if ($amount == $order->get_total() and $charged_amount == 0) {
            $chargeByItems = true;
            $objectProducts = $this->payplus_get_products_by_order_id($order_id);
        }
        $totalCartAmount = round($objectProducts->amount, $this->rounding_decimals);
        $payload = '{
                        "transaction_uid": "' . $transaction_uid . '",
                        ' . ($this->send_add_data ? '"add_data": "' . $order_id . '",' : '') . '
                        "amount": "' . ($chargeByItems ? $totalCartAmount : $amount) . '",
                        ' . ($chargeByItems ? '
                        "items": [
                            ' . implode(",", $objectProducts->productsItems) . '
                            ]' : '
                        "more_info": "' . __('Charge for Order Number: ', 'payplus-payment-gateway') . $order_id . '"
                        ') . '
                        }';
        if ($this->api_test_mode) {
            $apiURL = 'https://restapidev.payplus.co.il/api/v1.0/';
        } else {
            $apiURL = 'https://restapi.payplus.co.il/api/v1.0/';
        }

        $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');
        $response = $this->post_payplus_ws($apiURL . 'Transactions/ChargeByTransactionUID', $payload);
        $res = json_decode(wp_remote_retrieve_body($response));
        if ($res->results->status == "success" && $res->data->transaction->status_code == "000") {
            delete_post_meta($order_id, 'payplus_type');
            $insertMeta['payplus_charged_j5_amount'] = $amount;
            $insertMeta['payplus_type'] = 'Charge';
            $insertMeta['payplus_number'] = $res->data->transaction->number;
            $insertMeta['payplus_transaction_uid'] = $res->data->transaction->uid;
            $insertMeta['payplus_status_code'] = $res->data->transaction->status_code;
            $insertMeta['payplus_number_of_payments'] = $res->data->transaction->payments->number_of_payments;
            $insertMeta['payplus_first_payment_amount'] = $res->data->transaction->payments->first_payment_amount;
            $insertMeta['payplus_payments_rest_payments_amount'] = $res->data->transaction->payments->rest_payments_amount;
            $insertMeta['payplus_auth_num'] = trim($res->data->transaction->approval_number);
            $insertMeta['payplus_voucher_num'] = trim($res->data->transaction->voucher_number);
            WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);

            $order->add_order_note(sprintf('PayPlus Charge is Successful<br />Charge Transaction Number: %s<br />Amount: %s %s', $res->data->transaction->number, $res->data->transaction->amount, $order->get_currency()));
            $this->payplus_add_log_all($handle, print_r($res, true), 'completed');
            $order->payment_complete();
        } else {
            $order->add_order_note(sprintf('PayPlus Charge is Failed<br />Status: %s<br />Description: %s', $res->results->status, $res->results->description));
            $this->payplus_add_log_all($handle, print_r($res, true), 'error');
            $this->error_msg = 2;
        }
        add_filter('redirect_post_location', [$this, 'add_notice_query_var'], 99);
    }

    /**
     * @param $url
     * @param $payload
     * @param $method
     * @return array|WP_Error
     */
    public function post_payplus_ws($url, $payload = array(), $method = "post")
    {
        $args = array(
            'body' => $payload,
            'timeout' => '60',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'domain' => home_url(),
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress',
                'Authorization' => '{"api_key":"' . $this->api_key . '","secret_key":"' . $this->secret_key . '"}',
            )
        );
        $response = wp_remote_post($url, $args);
        return $response;
    }

    /**
     * @param $location
     * @return string
     */
    public function add_notice_query_var($location)
    {
        remove_filter('redirect_post_location', [$this, 'add_notice_query_var'], 99);

        return add_query_arg(['error_msg' => $this->error_msg], $location);
    }

    /**
     * @return void
     */
    public function admin_notices()
    {
        if (!wp_verify_nonce($this->_wpnonce, 'PayPlusGateWayAdminNonce')) {
            wp_die('Not allowed! - admin_notices');
        }
        if (!isset($_GET['error_msg'])) {
            return;
        }

        $title = __('PayPlus Payment Gateway', 'payplus-payment-gateway');
        $class = 'notice-error';
        switch ($_GET['error_msg']) {
            case 1:
                $message = esc_html__('user or other, please contact payplus support', 'payplus-payment-gateway');
                break;
            case 2:
                $message = esc_html__('Credit card company declined, check credit card details and credit line', 'payplus-payment-gateway');
                break;
            default:
                $message = esc_html__('PayPlus Payment Successful', 'payplus-payment-gateway');
                $class = 'notice-success';
        }
        echo "<div class='notice " . esc_attr($class) . " is-dismissible'><p><b>" . esc_html($title) . ":</b>" . esc_html($message) . "</p></div>";
    }

    /**
     * @param $args
     * @return mixed
     */
    public function add_removable_arg($args)
    {
        $args[] = 'error_msg';

        return $args;
    }

    /**
     * @param $order_id
     * @param $id
     * @return array|object|stdClass[]|null
     */
    public function payplus_get_order_payment($order_id = null, $id = null)
    {
        global $wpdb;
        $order_id = intval($order_id);
        $table = $wpdb->prefix . 'payplus_order';
        $table = esc_sql($table);

        if ($id) {
            $id = intval($id);
            $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}payplus_order WHERE id = %d", $id));
        } else {
            $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}payplus_order WHERE order_id = %d", $order_id));
        }

        return $result;
    }


    /**
     * @param $order_id
     * @param $amount
     * @return bool|int|mysqli_result|null
     */
    public function payplus_update_order_payment($id, $amount)
    {
        global $wpdb;

        $id = intval($id);
        $amount = floatval($amount);

        $table = $wpdb->prefix . 'payplus_order';

        $result = $this->payplus_get_order_payment(false, $id);

        if (!empty($result) && isset($result[0])) {
            $refund = floatval($result[0]->refund) + ($amount * 100);
            $invoice_refund = floatval($result[0]->invoice_refund) + ($amount * 100);

            $result = $wpdb->update(
                $table,
                array(
                    'refund' => $refund,
                    'invoice_refund' => $invoice_refund
                ),
                array('id' => $id),
                array('%f', '%f'),
                array('%d')
            );

            return $result;
        }

        return false;
    }

    /**
     * @param int $order_id
     * @param int $refund_id
     * @return void
     */
    public function payplus_after_refund($order_id, $refund_id)
    {
        // Sanitize the incoming parameters
        $order_id = intval($order_id);
        $refund_id = intval($refund_id);

        // Instantiate the refund object securely
        $refund = new WC_Order_Refund($refund_id);
        $amount = floatval($refund->get_amount());

        // Get the order payment details
        $order = $this->payplus_get_order_payment($order_id);
        $payplus_related_transactions = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_related_transactions', true);

        // Update the order payment if no related transactions are found
        if (empty($payplus_related_transactions)) {
            if (is_array($order) && count($order)) {
                $this->payplus_update_order_payment(intval($order[0]->id), $amount);
            }
        }

        // Get the order object
        $order = wc_get_order($order_id);

        // Get and sanitize the payment method
        $payment_method = sanitize_text_field($order->get_payment_method());

        if (!empty($payment_method) && strpos($payment_method, 'payplus') === false) {
            if (!$this->payPlusInvoice->payplus_get_create_invoice_manual() && $amount > 0) {
                $this->payPlusInvoice->payplus_create_document_dashboard(
                    $order_id,
                    sanitize_text_field($this->payPlusInvoice->payplus_get_invoice_type_document_refund()),
                    array(),
                    $amount,
                    'payplus_order_refund' . $order_id
                );
            }
        }
    }
}
WC_PayPlus_Admin_Payments::get_instance();
