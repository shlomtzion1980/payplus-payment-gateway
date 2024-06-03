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
    );

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
    private function __construct()
    {
        global $pagenow;
        $postKey = array_key_exists('post', $_GET) ? 'post' : 'id';

        $isPageOrder = ('post.php' === $pagenow || 'admin.php' === $pagenow) && isset($_GET[$postKey]) &&
            ('shop_order' === get_post_type($_GET[$postKey])
                || 'shop_subscription' === get_post_type($_GET[$postKey])
                || 'shop_order_placehold' === get_post_type($_GET[$postKey])
            );

        $sections = $this->arrPayment;
        $sections[] = 'payplus-error-setting';
        $sections[] = 'payplus-invoice';
        $sections[] = 'payplus-express-checkout';
        if (
            $isPageOrder
            || (('admin.php' === $pagenow) && isset($_GET['section']) && in_array($_GET['section'], $sections))
        ) {

            add_action('admin_enqueue_scripts', [$this, 'load_admin_assets']);
        }

        $this->payPlusInvoice = new PayplusInvoice();
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
        add_action('wp_ajax_custom_action', [$this, 'custom_action_callback']);

        add_action('woocommerce_admin_order_totals_after_total', [$this, 'payplus_woocommerce_admin_order_totals_after_total'], 10, 1);
        // Place "Get Order Details" button from PayPlus if the order is marked as unpaid - allows to get the order details from PayPlus if exists and
        // updates the order status to processing if the payment was successful and adds order note!
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'add_custom_button_to_order'], 10, 1);
        add_action('add_meta_boxes', [$this, 'add_custom_order_metabox']);
        add_action('admin_head', [$this, 'hide_delete_update_buttons_css']);

        // remove query args after error shown
        add_filter('removable_query_args', [$this, 'add_removable_arg']);
        add_filter('woocommerce_get_settings_checkout', [$this, 'payplus_get_error_setting'], 10, 2);
        add_filter('admin_body_class', [$this, 'payplus_admin_classes']);


        if ($this->payPlusInvoice->payplus_get_invoice_enable()) {
            add_action('woocommerce_order_refunded', [$this, 'payplus_after_refund'], 10, 2);
        }
    }

    /**
     * Hide Delete/Update buttons of custom fields
     * @return void
     */
    public function hide_delete_update_buttons_css()
    {
        $this->isInitiated();
        if ($this->hide_custom_fields_buttons) {
            echo '<style>.post-type-shop_order #the-list .deletemeta { display: none !important; } .post-type-shop_order #the-list .updatemeta { display: none !important; }</style>';
        }
    }




    public function add_custom_order_metabox()
    {
        $isInvoicePlus = get_option('payplus_invoice_option')['payplus_invoice_enable'];
        if ($isInvoicePlus === 'yes') {
            add_meta_box(
                'custom_order_metabox', // Unique ID for the metabox
                __('Invoice+ Docs', 'payplus-payment-gateway'), // Metabox title
                [$this, 'display_custom_order_metabox'], // Callback function to display the metabox content
                'woocommerce_page_wc-orders', // Post type where it should be displayed (order page)
                'side', // Context (position on the screen)
                'default' // Priority
            );
        }
    }

    public function display_custom_order_metabox($post)
    {
        $order_id = $post->ID;
        $refundDocs = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_refund_docs', true);
        $refundsJson = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_refunds', true);
        $refundsArray = !empty($refundsJson) ? json_decode($refundsJson, true) : $refundsJson;

        $invDoc = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_invoice_originalDocAddress', true);
        $invDocType = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_invoice_type', true);
        $invDocNumber = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_invoice_numberD', true);
        $chargeText = __('Charge', 'payplus-payment-gateway');
        $refundsText = __('Refunds', 'payplus-payment-gateway');

        switch ($invDocType) {
            case 'inv_tax':
                $docType = __('Tax Invoice', 'payplus-payment-gateway');
                break;
            case 'inv_tax_receipt':
                $docType = __('Tax Invoice Receipt ', 'payplus-payment-gateway');
                break;
            case 'inv_receipt':
                $docType = __('Receipt', 'payplus-payment-gateway');
                break;
            case 'inv_don_receipt':
                $docType = __('Donation Reciept', 'payplus-payment-gateway');
                break;
            default:
                $docType = __('Invoice', 'payplus-payment-gateway');
        }


        if (strlen($invDoc) > 0) { ?>
            <div>
                <h4><?php echo $chargeText; ?></h4>
                <a class="link-invoice" style="text-decoration: none;" target="_blank" href="<?php echo $invDoc; ?>"><?php echo $docType; ?> (<?php echo $invDocNumber; ?>)</a>
            </div>
        <?php
        }
        if (is_array($refundsArray)) {
        ?>
            <div>
                <h4><?php echo $refundsText; ?></h4>
                <?php
                foreach ($refundsArray as $docNumber => $doc) {
                    $docLink = $doc['link'];
                    $docText = __($doc['type'], 'payplus-payment-gateway');
                ?>
                    <a class="link-invoice" style="text-decoration: none;" target="_blank" href="<?php echo $docLink; ?>"><?php echo "$docText ($docNumber)"; ?></a>
                <?php
                }

                ?>
            </div>
        <?php }
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
    public function custom_action_callback()
    {
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $payment_request_uid = $_POST['payment_request_uid'];

        $this->isInitiated();

        $url = $this->ipn_url;

        $payload['payment_request_uid'] = $payment_request_uid;

        $args = array(
            'body' => json_encode($payload),
            'timeout' => '60',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'User-Agent' => 'WordPress ' . $_SERVER['HTTP_USER_AGENT'],
                'Content-Type' => 'application/json',
                'Authorization' => '{"api_key":"' . $this->api_key . '","secret_key":"' . $this->secret_key . '"}',
            ),
        );

        $order = wc_get_order($order_id);
        $response = wp_remote_post($url, $args);
        $responseBody = json_decode(wp_remote_retrieve_body($response), true);

        $type = $responseBody['data']['type'];

        $successNote = sprintf(
            __(
                '
        <div style="font-weight:600;">PayPlus ' . (($type == "Approval" || $type == "Check") ? 'Pre-Authorization' : 'Payment') . ' Successful</div>
            <table style="border-collapse:collapse">
                <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Last digits</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Expiry date</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Voucher ID</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                <tr><td style="vertical-align:top;">Token</td><td style="vertical-align:top;">%s</td></tr>
                <tr><td style="vertical-align:top;">Total</td><td style="vertical-align:top;">%s</td></tr>
            </table>
        ',
                'payplus-payment-gateway'
            ),
            $responseBody['data']['number'],
            $responseBody['data']['four_digits'],
            $responseBody['data']['expiry_month'] . "/" . $responseBody['data']['expiry_year'],
            $responseBody['data']['voucher_num'],
            $responseBody['data']['token_uid'],
            $responseBody['data']['amount'],
            $order->get_total()
        );

        $payplusResponse = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_response', true);

        if (!$payplusResponse && !empty($responseBody['data'])) {
            $responseArray = [
                'payplus_response' => json_encode($responseBody['data']),
                'payplus_type' => $responseBody['data']['type'],
                'payplus_brand_name' => $responseBody['data']['brand_name'],
                'payplus_method' => $responseBody['data']['method'],
                'payplus_number' => $responseBody['data']['number'],
                'payplus_number_of_payments' => $responseBody['data']['number_of_payments'],
                'payplus_clearing_name' => $responseBody['data']['clearing_name'],
                'payplus_credit_terms' => $responseBody['data']['credit_terms'],
                'payplus_credit-card' => $responseBody['data']['amount'],
                'payplus_customer_name' => $responseBody['data']['customer_name'],
                'payplus_expiry_month' => $responseBody['data']['expiry_month'],
                'payplus_expiry_year' => $responseBody['data']['expiry_year'],
                'payplus_four_digits' => $responseBody['data']['four_digits'],
                'payplus_issuer_id' => $responseBody['data']['issuer_id'],
                'payplus_issuer_name' => $responseBody['data']['issuer_name'],
                'payplus_more_info' => $responseBody['data']['more_info'],
                'payplus_secure3D_tracking' => $responseBody['data']['secure3D_tracking'],
                'payplus_status' => $responseBody['data']['status'],
                'payplus_status_code' => $responseBody['data']['status_code'],
                'payplus_status_description' => $responseBody['data']['status_description'],
                'payplus_token_uid' => $responseBody['data']['token_uid'],
                'payplus_voucher_num' => $responseBody['data']['voucher_num']
            ];
            WC_PayPlus_Order_Data::update_meta($order, $responseArray);
        }

        if ($responseBody['data']['status'] === 'approved' && $responseBody['data']['status_code'] === '000' && $responseBody['data']['type'] === 'Charge') {
            $order->update_status('wc-processing');
            $order->add_order_note(
                $successNote
            );
        } elseif ($responseBody['data']['status'] === 'approved' && $responseBody['data']['status_code'] === '000' && $responseBody['data']['type'] === 'Approval') {
            $order->update_status('wc-on-hold');
            $order->add_order_note(
                $successNote
            );
        } else {
            $note = $responseBody['data']['status'] ?: 'Failed/No Data';
            $order->add_order_note('PayPlus IPN: ' . $note);
        }
    }

    /**
     * @param $classes
     * @return mixed|string
     */
    public function payplus_admin_classes($classes)
    {

        if (isset($_GET['section']) && $_GET['section'] === 'payplus-error-setting') {
            $classes .= "payplus-error-setting";
        }
        return $classes;
    }

    /**
     * @return array
     */
    public function payplus_get_statuss()
    {

        $payplus_invoice_status_order = wc_get_order_statuses();

        if (count($payplus_invoice_status_order)) {
            $statusOrders = array('' => __('Order status for issuing an invoice', 'payplus-payment-gateway'));
            foreach ($payplus_invoice_status_order as $key => $value) {
                $keyValue = str_replace('wc-', '', $key);
                $statusOrders[$keyValue] = $value;
            }
        }
        return $statusOrders;
    }

    /**
     * @return array
     */
    public static function getTaxStandards()
    {
        global $wpdb;
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $countryDefault = get_option('woocommerce_default_country');
        $options = array();
        $rates = $wpdb->get_results($wpdb->prepare("SELECT tax_rate ,tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country = '%s'", $countryDefault));
        if (count($rates)) {
            $options[0] = __('Select  tax rate', 'payplus-payment-gateway');
            foreach ($rates as $key => $valueRate) {
                $taxRate = round($valueRate->tax_rate, $WC_PayPlus_Gateway->rounding_decimals);
                $options[$taxRate] = $valueRate->tax_rate_name . ' ( ' . $taxRate . ' ) ';
            }
        }
        return $options;
    }

    /**
     * @return array
     */
    public function payplus_get_languages()
    {
        require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        $templanguages = wp_get_available_translations();
        $languages = array();
        if (count($templanguages)) {
            $languages = array('' => __('Language of the page', 'payplus-payment-gateway'));
            foreach ($templanguages as $key => $language) {
                $name = $language['native_name'];
                if ($name !== $language['english_name']) {
                    $name .= " - " . $language['english_name'];
                }
                $languages[$key . "-" . $language['english_name']] = $name;
            }
        }
        return $languages;
    }

    /**
     * @param $settings
     * @param $current_section
     * @return mixed
     */
    public function payplus_get_error_setting($settings, $current_section)
    {

        /**
         * Check the current section is what we want
         **/

        switch ($current_section) {

            case 'payplus-error-setting':

                if (!empty($_POST) && isset($_POST['settings_payplus_page_error_option'])) {
                    $settingsPayplusPageErrorOption = $_POST['settings_payplus_page_error_option'];
                    unset($settingsPayplusPageErrorOption['select-languages-payplus']);
                    update_option('settings_payplus_page_error_option', $settingsPayplusPageErrorOption);
                }
                $languages = get_option('settings_payplus_page_error_option');
                unset($languages['select-languages-payplus']);
                if (!count($languages)) {

                    $languages['he_IL-Hebrew'] = "העיסקה נכשלה, נא ליצור קשר עם בית העסק";
                    $languages['en_US_-English'] = "The transaction failed, please contact the seller";
                }

                $settings[] = array(
                    'name' => __('PayPlus Page Error - Settings', 'payplus-payment-gateway'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'payplus-error-setting'
                );

                $settings[] = array(
                    'name' => __('Language of the page', 'payplus-payment-gateway') . ":",
                    'id' => 'settings_payplus_page_error_option[select-languages-payplus]',
                    'type' => 'select',
                    'options' => $this->payplus_get_languages(),
                    'class' => 'select-languages-payplus',

                );
                if ($languages) {
                    foreach ($languages as $key => $language) {
                        $otherKey = explode("-", $key);
                        $arrLang = array(
                            'name' => __($otherKey[1], 'payplus-payment-gateway') . ":",
                            'id' => 'settings_payplus_page_error_option[' . $key . ']',
                            'type' => 'textarea'
                        );

                        if ($key != 'he_IL-Hebrew' && $key != 'en_US_-English') {
                            $arrLang['desc'] = "<a class='button-primary woocommerce-save-button payplus-delete-error'>Delete</a>";
                        }
                        $settings[] = $arrLang;
                    }
                }
                $settings[] = array('type' => 'sectionend', 'id' => 'payplus-error-setting');
                break;
            case 'payplus-invoice':
                $payplus_invoice_option = get_option('payplus_invoice_option');
                $settings[] = array(
                    'name' => __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'payplus-invoice'
                );
                $checked = (isset($payplus_invoice_option['payplus_invoice_enable']) && (
                    $payplus_invoice_option['payplus_invoice_enable'] == "on" || $payplus_invoice_option['payplus_invoice_enable'] == "yes")) ? array('checked' => 'checked') : array();

                $settings[] = array(
                    'name' => __('Enable/Disable', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_enable]',
                    'type' => 'checkbox',
                    'custom_attributes' => $checked,
                );
                $checked = (isset($payplus_invoice_option['payplus_enable_sandbox']) &&
                    $payplus_invoice_option['payplus_enable_sandbox'] == "on") ? array('checked' => 'checked') : array();

                $selectTypeDoc = array(
                    '' => __('Type Documents', 'payplus-payment-gateway'),
                    'inv_tax' => __('Tax Invoice', 'payplus-payment-gateway'),
                    'inv_tax_receipt' => __('Tax Invoice Receipt ', 'payplus-payment-gateway'),
                    'inv_receipt' => __('Receipt', 'payplus-payment-gateway'),
                    'inv_don_receipt' => __('Donation Reciept', 'payplus-payment-gateway')
                );

                $settings[] = array(
                    'name' => __('Enable Sandbox Mode', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_enable_sandbox]',
                    'type' => 'checkbox',
                    'custom_attributes' => $checked,
                );
                $settings[] = array(
                    'name' => __('API Key', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_api_key]',
                    'type' => 'text'
                );

                $settings[] = array(
                    'name' => __('SECRET KEY', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_secret_key]',
                    'type' => 'text'
                );

                $settings[] = array(
                    'name' => __("Invoice's Language", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_langrage_invoice]',
                    'type' => 'select',
                    'options' => array('he' => 'he', 'en' => 'en'),

                );
                $settings[] = array(
                    'name' => __("Document type for charge transaction", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_type_document]',
                    'type' => 'select',
                    'options' => $selectTypeDoc
                );

                $settings[] = array(
                    'name' => __("Document type for refund transaction", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_type_document_refund]',
                    'type' => 'select',
                    'options' => array(
                        '' => __('Type Documents Refund', 'payplus-payment-gateway'),
                        'inv_refund' => __('Refund Invoice', 'payplus-payment-gateway'),
                        'inv_refund_receipt' => __('Refund Receipt', 'payplus-payment-gateway'),
                        'inv_refund_receipt_invoice' => __('Refund Invoice + Refund Receipt', 'payplus-payment-gateway')
                    )
                );

                $settings[] = array(
                    'name' => __("Order status for issuing an invoice", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_status_order]',
                    'type' => 'select',
                    'options' => $this->payplus_get_statuss()
                );

                $checked = (isset($payplus_invoice_option['payplus_invoice_send_document_email']) &&
                    ($payplus_invoice_option['payplus_invoice_send_document_email'] == "on" ||
                        $payplus_invoice_option['payplus_invoice_send_document_email'] == "yes")) ? array('checked' => 'checked') : array();

                $settings[] = array(
                    'name' => __("Send invoice to the customer via e-mail", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_send_document_email]',
                    'type' => 'checkbox',
                    'custom_attributes' => $checked,
                );

                $checked = (isset($payplus_invoice_option['payplus_invoice_send_document_sms']) && (
                    $payplus_invoice_option['payplus_invoice_send_document_sms'] == "on"
                    || $payplus_invoice_option['payplus_invoice_send_document_sms'] == "yes"
                )) ? array('checked' => 'checked') : array();

                $settings[] = array(
                    'name' => __('Send invoice to the customer via Sms (Only If you purchased an SMS package from PayPlus)', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_send_document_sms]',
                    'type' => 'checkbox',
                    'custom_attributes' => $checked,
                );
                $settings[] = array(
                    'name' => __('If you create an invoice in a non-automatic management interface', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[create-invoice-manual]',
                    'type' => 'checkbox',
                    'class' => 'create-invoice-manual',
                );
                $settings[] = array(
                    'name' => __('List of documents that can be produced manually', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[invoice-manual-list]',
                    'type' => 'select',
                    'options' => $selectTypeDoc,
                    'class' => 'invoice-manual-list',
                    'custom_attributes' => array('multiple' => 'multiple'),
                );
                $settings[] = array(
                    'name' => __('', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[list-hidden]',
                    'type' => 'text',
                    'class' => 'list-hidden',

                );
                $settings[] = array(
                    'name' => __('Whether to issue an automatic tax invoice that is paid in cash or by bank transfer', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[create-invoice-automatic]',
                    'type' => 'checkbox',
                );
                $settings[] = array(
                    'name' => __("Brand Uid (Note only if you have more than one site you will need to activate the button)", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_brand_uid]',
                    'type' => 'text',
                    'class' => 'payplus_invoice_brand_uid',
                    'desc' => '<span class="arrow-payplus"></span>',
                );
                $settings[] = array(
                    'name' => __("Website code", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_website_code]',
                    'type' => 'text',
                    'class' => 'payplus_website_code',
                    'desc' => '<span class="arrow-payplus">' . __("Add a unique string here if you have more than one website
                    connected to the service <br> This will create a unique id for invoices to each site (website code must be different for each site!)", 'payplus-payment-gateway') . '</span>',
                );
                $settings[] = array(
                    'name' => __('Logging', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_enable_logging_mode]',
                    'type' => 'checkbox',
                    'custom_attributes' => array('disabled' => 'disabled', 'checked' => 'checked'),
                );

                $settings[] = array('type' => 'sectionend', 'id' => 'payplus-invoice');
                break;
            case 'payplus-express-checkout':
                $rates = self::getTaxStandards();
                $settings[] = array(
                    'name' => __('Express Checkout', 'payplus-payment-gateway'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'payplus-express-checkout'
                );

                $settings[] = [
                    'name' => __('Google Pay', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[enable_google_pay]',
                    'type' => 'checkbox',
                    'class' => 'enable_google_pay enable_checkout',
                    'desc' => '<div style="color:red" class="error-express-checkout"></div>
                            <div class="loading-express">
                            <div class="spinner-icon"></div>
                            </div>',
                ];

                $settings[] = [
                    'name' => __('Apple Pay', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[enable_apple_pay]',
                    'type' => 'checkbox',
                    'class' => 'enable_apple_pay enable_checkout',
                    'desc' => '<div style="color:red" class="error-express-checkout"></div>
                            <div class="loading-express">
                            <div class="spinner-icon"></div>
                            </div>',
                ];

                $settings[] = [
                    'id' => 'woocommerce_payplus-payment-gateway_settings[apple_pay_identifier]',
                    'name' => __('Token Apple Pay', 'payplus-payment-gateway'),
                    'type' => 'text',
                    'class' => 'apple_pay_identifier',
                    'custom_attributes' => array('readonly' => 'readonly'),
                ];
                $settings[] = [
                    'name' => __('If displayed on a product page ?', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[enable_product]',
                    'type' => 'checkbox'
                ];

                $settings[] = [
                    'name' => __('If you create a new user ?', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[enable_create_user]',
                    'type' => 'checkbox'
                ];
                $settings[] = array(
                    'id' => 'woocommerce_payplus-payment-gateway_settings[shipping_woo]',
                    'name' => __('Shipping according to woocommerce', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'class' => 'shipping_woo',
                    'label' => __('', 'payplus-payment-gateway'),

                );
                $settings[] =
                    array(
                        'id' => 'woocommerce_payplus-payment-gateway_settings[global_shipping]',
                        'name' => __('Global shipping amount', 'payplus-payment-gateway') . ' ( ' . get_woocommerce_currency_symbol() . ' ) ',
                        'type' => 'text',
                        'class' => 'global_shipping',
                        'default' => '0'
                    );

                $settings[] = array(
                    'id' => 'woocommerce_payplus-payment-gateway_settings[global_shipping_tax]',
                    'name' => __('Global shipping Tax', 'payplus-payment-gateway'),
                    'type' => 'select',
                    'options' => array(
                        'taxable' => __('Taxable', 'woocommerce'),
                        'none' => __('None', 'Tax status', 'woocommerce'),
                    ),
                    'class' => 'global_shipping_tax',
                    'default' => 'none'
                );

                if (count($rates)) {
                    $settings[] = array(
                        'id' => 'woocommerce_payplus-payment-gateway_settings[global_shipping_tax_rate]',
                        'name' => __('Select tax rate', 'payplus-payment-gateway'),
                        'type' => 'select',
                        'options' => $rates,
                        'class' => 'global_shipping_tax_rate',
                        'default' => ''
                    );
                }
                $settings[] = array('type' => 'sectionend', 'id' => 'payplus-express-checkout');
                break;
        }

        return $settings;
    }

    public function ajax_payplus_refund_club_amount()
    {
        if (
            !empty($_POST)
            && !empty($_POST['transactionUid'])
            && !empty($_POST['orderID'])
            && !empty($_POST['amount'])
        ) {
            $handle = 'payplus_process_refund';
            $this->isInitiated();
            // $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
            $amount = $_POST['amount'];
            $method = $_POST['method'];
            $transactionUid = $_POST['transactionUid'];
            $orderID = $_POST['orderID'];
            $id = $_POST['id'];
            $indexRow = 0;
            $urlEdit = get_admin_url() . "post.php?post=" . $orderID . "&action=edit";
            $this->payplus_add_log_all($handle, 'WP Refund  club card(' . $orderID . ')');
            $order = wc_get_order($orderID);
            $refunded_amount = round((float) $order->get_meta('payplus_total_refunded_amount'), 2);

            $payload['transaction_uid'] = $transactionUid;
            $payload['amount'] = $amount;
            $payload['more_info'] = __('Refund for Order Number: ', 'payplus-payment-gateway') . $orderID;

            if ($this->invoice_api->payplus_get_invoice_enable()) {
                $payload['initial_invoice'] = false;
            }

            $payload = json_encode($payload);
            $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');
            $response = $this->post_payplus_ws($this->refund_url, $payload);
            if (is_wp_error($response)) {
                $this->payplus_add_log_all($handle, print_r($response, true), 'error');
            } else {
                $res = json_decode(wp_remote_retrieve_body($response));
                if ($res->results->status == "success" && $res->data->transaction->status_code == "000") {
                    WC_PayPlus_Order_Data::update_meta($order, array('payplus_total_refunded_amount' => round($refunded_amount + $amount, 2)));
                    $this->payplus_update_order_payment($id, $amount);
                    $this->payplus_add_log_all($handle, print_r($res, true), 'completed');
                    $order->add_order_note(sprintf(__('PayPlus Refund is Successful<br />Refund Transaction Number: %s<br />Amount: %s %s<br />Reason: %s', 'payplus-payment-gateway'), $res->data->transaction->number, $res->data->transaction->amount, $order->get_currency(), 'refund ' . $method));

                    /* refund*/
                    $refund = wc_create_refund(array(
                        'amount' => $amount,
                        'reason' => "refund  " . $method,
                        'order_id' => $orderID,
                    ));
                    /* end refund*/

                    /* invoice api*/
                    if (
                        $this->invoice_api->payplus_get_invoice_enable() &&
                        !$this->invoice_api->payplus_get_create_invoice_manual()
                    ) {
                        $payments = $this->payplus_get_order_payment(false, $id);
                        if ($payments[$indexRow]->price > round($amount, $this->rounding_decimals)) {
                            $payments[$indexRow]->price = $amount * 100;
                        }
                        $rand = rand(0, intval($orderID));
                        $this->invoice_api->payplus_create_document_dashboard(
                            $orderID,
                            $this->invoice_api->payplus_get_invoice_type_document_refund(),
                            $payments,
                            round($amount, $this->rounding_decimals),
                            'payplus_order_refund_' . $rand . "_" . $orderID
                        );
                    }
                    /* end invoice api**/
                    echo json_encode(array("urlredirect" => $urlEdit, "status" => true));
                } else {
                    $this->payplus_add_log_all($handle, print_r($res, true), 'error');
                    $order->add_order_note(sprintf(__('PayPlus Refund is Failed<br />Status: %s<br />Description: %s', 'payplus-payment-gateway'), $res->results->status, $res->results->description));
                    echo json_encode(array("urlredirect" => $urlEdit, "status" => false));
                }
            }
        }
        wp_die();
    }

    public function payplus_add_payments($order_id, $payments)
    {

        global $wpdb;
        $table = $wpdb->prefix . 'payplus_order';
        $wpdb->update($table, array('delete_at' => 1), array('order_id' => $order_id));
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
            $wpdb->insert(
                $table,
                $payment
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

        if (!empty($_POST) && !empty($_POST['order_id'])) {
            $order_id = $_POST['order_id'];
            $urlEdit = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
            $type_document = $_POST['typeDocument'];
            $payments = $_POST['payments'];

            if (!empty($payments)) {
                function set_payment_payplus($value)
                {
                    if ($value['method_payment'] == "payment-app") {
                        $value['method_payment'] = $value['payment_app'];
                        unset($value['payment_app']);
                    }
                    return $value;
                }

                $payments = array_map('set_payment_payplus', $payments);
                $this->payplus_add_payments($order_id, $payments);
            }

            $this->payPlusInvoice->payplus_invoice_create_order($order_id, $type_document);
            echo json_encode(array("urlredirect" => $urlEdit, "status" => true));
            wp_die();
        }
        wp_die();
    }
    /**
     * @return void
     */
    public function ajax_payplus_create_invoice_refund()
    {
        if (!empty($_POST) && !empty($_POST['order_id'])) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'payplus_order';
            $indexRow = 0;
            $order_id = $_POST['order_id'];
            $order = wc_get_order($order_id);
            $amount = $_POST['amount'];
            $urlEdit = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
            $this->isInitiated();
            // $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
            $type_document = $_POST['type_document'];

            $resultApps = $this->payPlusInvoice->payplus_get_payments($order_id);

            $sum = 0;
            $sumTransactionRefund = array_reduce($resultApps, function ($sum, $item) {
                return $sum + ($item->price / 100);
            });
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
                    'payplus_order_refund' . $order_id . "_" . rand(1, 1000)
                );
                $wpdb->update($table_name, array('invoice_refund' => 0), array('order_id' => $order_id));
                if ($amount == $order->get_total()) {
                    WC_PayPlus_Order_Data::update_meta($order, array('payplus_send_refund' => true));
                }
            }
            echo json_encode(array("urlredirect" => $urlEdit, "status" => true));
            wp_die();
        }
        wp_die();
    }

    /**
     * @return void
     */
    public function ajax_payment_payplus_transaction_review()
    {
        if (!empty($_POST) && !empty($_POST['order_id'])) {
            $handle = "payplus_process_payment";
            $this->isInitiated();
            // $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
            $order_id = $_POST['order_id'];
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
                            $transaction_uid = WC_PayPlus_Order_Data::get_meta($parent_id, 'payplus_transaction_uid', true);
                            if ($transaction_uid) {
                                $payload['transaction_uid'] = $transaction_uid;
                            } else {
                                $payload['more_info'] = $parent_id;
                            }
                            $payload = json_encode($payload);
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
                                    WC_PayPlus_Order_Data::update_meta($order, array('payplus_token_uid' => $token));
                                    $order = wc_get_order($parent_id);
                                    $order->add_order_note('Update token:' . $token);
                                    $order->save();
                                    echo json_encode(array("urlredirect" => $urlEdit, "status" => true));
                                    wp_die();
                                }
                            }
                        } else {
                            WC_PayPlus_Order_Data::update_meta($order, array('order_validated' => "1"));
                            delete_post_meta($order_id, 'order_validated_error');
                            $order->update_status('wc-active');
                            echo json_encode(array("urlredirect" => $urlEdit, "status" => true));
                        }
                    }
                } else {

                    $payload['more_info'] = $order_id;
                    $payload = json_encode($payload);
                    $this->payplus_add_log_all($handle, 'New IPN Fired (' . $order_id . ')');
                    $this->payplus_add_log_all($handle, print_r($payload, true), 'payload');
                    $this->requestPayPlusIpn($payload, array('order_id' => $order_id), 1);
                    WC_PayPlus_Order_Data::update_meta($order, array('order_validated' => '1'));
                    $order->delete_meta_data('order_validated_error');
                    $order->save();
                    delete_post_meta($order_id, 'order_validated_error');
                    echo json_encode(array("urlredirect" => $urlEdit, "status" => true));
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

        $response = array("payment_response" => "", "status" => false);

        if (!empty($_POST)) {
            $this->isInitiated();
            // $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
            $handle = "payplus_process_payment";
            $date = new DateTime();
            $dateNow = $date->format('Y-m-d H:i');
            $order_id = $_POST['order_id'];
            $order = wc_get_order($order_id);
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
                    WC_PayPlus_Order_Data::update_meta($order, array('payplus_page_request_uid' => $res->data->page_request_uid));
                    WC_PayPlus_Order_Data::update_meta($order, array('payplus_payment_page_link' => $res->data->payment_page_link));
                    $response = array("status" => true, "payment_response" => $res->data->payment_page_link);
                } else {
                    $this->payplus_add_log_all($handle, print_r($res, true), 'error');
                    $response = (is_array($response)) ? $response['body'] : $response->body;
                    $response = array("status" => false, "payment_response" => $response);
                }
            }
        }
        echo json_encode($response);
        wp_die();
    }
    /**
     * @return void
     */
    public function ajax_payplus_payment_api()
    {

        if (!empty($_POST)) {
            $this->isInitiated();
            // $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
            $order_id = $_POST['order_id'];
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
            $payload = json_encode($payload);
            $response = $this->post_payplus_ws($this->ipn_url, $payload);

            $res = json_decode(wp_remote_retrieve_body($response));
            if ($res->results->status == "error" || $res->data->status_code !== "000") {

                $transaction_uid = ($transaction_uid) ? $transaction_uid : $res->data->transaction_uid;
                $this->payplus_add_log_all($handle, print_r($res, true), 'error');
                $order->update_status($this->failure_order_status);
                $order->add_order_note(sprintf(__('PayPlus IPN Failed<br/>Transaction UID: %s', 'payplus-payment-gateway'), $transaction_uid));
                $order->add_meta_data('order_validated', "1");
                $urlEdit = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
                echo json_encode(array("urlredirect" => $urlEdit, "status" => false));
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
                                    __(
                                        '
                            <div style="font-weight:600;border-bottom: 1px solid #000;padding: 5px 0px">PayPlus ' . (($type == "Approval" || $type == "Check") ? 'Pre-Authorization' : 'Payment') . ' Successful
                                <table style="border-collapse:collapse">
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Last digits</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Expiry date</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Voucher ID</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                    <tr><td style="vertical-align:top;">Token</td><td style="vertical-align:top;">%s</td></tr>
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Total</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                </table></div>
                            ',
                                        'payplus-payment-gateway'
                                    ),
                                    $relatedTransaction->number,
                                    $relatedTransaction->four_digits,
                                    $relatedTransaction->expiry_month . $relatedTransaction->expiry_year,
                                    $relatedTransaction->voucher_id,
                                    $relatedTransaction->token_uid,
                                    $relatedTransaction->amount

                                );
                            } else {
                                $html .= sprintf(
                                    __(
                                        '
                              <div class="row" style="font-weight:600;border-bottom: 1px solid #000;padding: 5px 0px">PayPlus  Successful ' . $relatedTransaction->method . '
                                <table style="border-collapse:collapse">
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Total</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                </table></div>
                            ',
                                        'payplus-payment-gateway'
                                    ),
                                    $relatedTransaction->number,
                                    $relatedTransaction->amount
                                );
                            }
                        }
                        $html .= "</div>";
                        $order->add_order_note($html);
                    } else {
                        if ($res->data->method !== "credit-card") {
                            $order->add_order_note(sprintf(
                                __(
                                    '
                              <div class="row" style="font-weight:600;border-bottom: 1px solid #000;padding: 5px 0px">PayPlus  Successful ' . $res->data->method . '
                                <table style="border-collapse:collapse">
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Total</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                </table></div>
                            ',
                                    'payplus-payment-gateway'
                                ),
                                $res->data->number,
                                $res->data->amount
                            ));
                        } else {

                            $order->add_order_note(sprintf(
                                __(
                                    '
                            <div style="font-weight:600;">PayPlus ' . (($type == "Approval" || $type == "Check") ? 'Pre-Authorization' : 'Payment') . ' Successful</div>
                                <table style="border-collapse:collapse">
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Last digits</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Expiry date</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                    <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Voucher ID</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                    <tr><td style="vertical-align:top;">Token</td><td style="vertical-align:top;">%s</td></tr>
                                    <tr><td style="vertical-align:top;">Total</td><td style="vertical-align:top;">%s</td></tr>
                                </table>
                            ',
                                    'payplus-payment-gateway'
                                ),
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
                $urlEdit = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
                echo json_encode(array("urlredirect" => $urlEdit, "status" => true));
                wp_die();
            }
        }
        wp_die();
    }

    public function payplus_get_section_invoice_not_automatic($orderId)
    {
        $this->isInitiated();
        // $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $order = wc_get_order($orderId);
        $selectInvoice = array(
            'inv_tax' => __('Tax Invoice', 'payplus-payment-gateway'),
            'inv_tax_receipt' => __('Tax Invoice Receipt ', 'payplus-payment-gateway'),
            'inv_receipt' => __('Receipt', 'payplus-payment-gateway'),
            'inv_don_receipt' => __('Donation Reciept', 'payplus-payment-gateway')
        );

        $invoiceManualList = $this->payPlusInvoice->payplus_get_invoice_manual_list();
        $currentStatus = $this->payPlusInvoice->payplus_get_invoice_type_document();
        $chackStatus = array('inv_receipt', 'inv_tax_receipt');
        $chackAllPaymnet = in_array($currentStatus, $chackStatus) ? "block" : 'none';
        $payments = $this->invoice_api->payplus_get_payments($orderId);
        $checkInvoiceSend = WC_PayPlus_Order_Data::get_meta($orderId, 'payplus_check_invoice_send', true);
        if ($invoiceManualList) {
            $invoiceManualList = explode(",", $invoiceManualList);
            if (count($invoiceManualList) == 1 && $invoiceManualList[0] == "") {
                $invoiceManualList = array();
            }
        }

        ?>
        <div class="flex-row">
            <div class="flex-item">
                <select id="select-type-invoice-<?php echo $orderId ?>" class="select-type-invoice" name="select-type-invoice-<?php echo $orderId ?>">
                    <option value=""><?php echo __('Select a document type to create an invoice', 'payplus-payment-gateway') ?>
                    </option>
                    <?php

                    foreach ($selectInvoice as $key => $value) :
                        $flag = true;
                        if (count($invoiceManualList)) {
                            if (!in_array($key, $invoiceManualList)) {
                                $flag = false;
                            }
                        }
                        if ($flag) :
                            $selected = ($currentStatus == $key) ?
                                'selected' : '';
                    ?>
                            <option <?php echo $selected ?> value="<?php echo $key ?>"><?php echo $value ?> </option>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </select>
            </div>

        </div>

        <?php

        if (!count($payments)) {

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
            <input id="all-sum" type="hidden" value="<?php echo $order->get_total() ?>">
            <div id="all-paymnet-invoice" style="display: <?php echo $chackAllPaymnet ?>">

                <div class="flex-row">
                    <h2><strong><?php echo __("Payment details", "payplus-payment-gateway") ?> </strong></h2>
                </div>
                <div class="flex-row">
                    <div class="flex-item">
                        <button id="" data-type="credit-card" class=" credit-card type-payment"><?php echo __("Credit Card", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="cash" class="cash type-payment"><?php echo __("Cash", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="payment-check" class="payment-check  type-payment"><?php echo __("Check", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="bank-transfer" class="bank-transfer  type-payment"><?php echo __("Bank Transfer", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="payment-app" class="payment-app  type-payment"><?php echo __("Payment App", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="paypal" class="paypal  type-payment"><?php echo __("PayPal", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="withholding-tax" class="withholding-tax  type-payment"><?php echo __("Withholding Tax", "payplus-payment-gateway") ?></button>
                    </div>
                    <div class="flex-item">
                        <button data-type="other" class="other  type-payment"><?php echo __("Other", "payplus-payment-gateway") ?></button>
                    </div>


                </div>
                <!-- Credit Card -->
                <div class="select-type-payment credit-card">
                    <input class="credit-card-payment-payplus input-change  row_id" type="hidden" value="">
                    <input class="credit-card-payment-payplus input-change  method_payment" type="hidden" value="credit-card">
                    <div class="flex-row">
                        <div class="flex-item">
                            <label> <?php echo __("Date", "payplus-payment-gateway") ?></label>
                            <input value="<?php echo date("Y-m-d") ?>" required class="credit-card-payment-payplus input-change create_at" type="date" placeholder="<?php echo __("Date", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item">
                            <label> <?php echo __("Credit card number", "payplus-payment-gateway") ?></label>
                            <input class="credit-card-payment-payplus input-change four_digits" type="number" onkeypress="if (value.length == 4) return false;" placeholder="<?php echo __("Four Digits", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item">
                            <label> <?php echo __("Card Type", "payplus-payment-gateway") ?></label>
                            <select class="credit-card-payment-payplus input-change brand_name">
                                <option value="">
                                    <?php echo __("Card Type", "payplus-payment-gateway") ?>
                                </option>
                                <option value="mastercard">
                                    <?php echo __("Mastercard", "payplus-payment-gateway") ?>
                                </option>
                                <option value="american-express">
                                    <?php echo __("American Express", "payplus-payment-gateway") ?>
                                </option>
                                <option value="american-express">
                                    <?php echo __("Discover", "payplus-payment-gateway") ?>
                                </option>
                                <option value="visa">
                                    <?php echo __("Visa", "payplus-payment-gateway") ?>
                                </option>
                                <option value="diners">
                                    <?php echo __("Diners", "payplus-payment-gateway") ?>
                                </option>
                                <option value="jcb">
                                    <?php echo __("Jcb", "payplus-payment-gateway") ?>
                                </option>
                                <option value="maestro">
                                    <?php echo __("Maestro", "payplus-payment-gateway") ?>
                                </option>
                                <option value="other">
                                    <?php echo __("Other", "payplus-payment-gateway") ?>
                                </option>

                            </select>
                        </div>
                        <div class="flex-item">
                            <label><?php echo __("Transaction type", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <select class="credit-card-payment-payplus input-change transaction_type" id="transaction_type" name="transaction_type">
                                    <option value=""><?php echo __("Transaction type", "payplus-payment-gateway") ?></option>
                                    <option value="normal"> <?php echo __("Normal", "payplus-payment-gateway") ?></option>
                                    <option value="payments"> <?php echo __("Payments", "payplus-payment-gateway") ?></option>
                                    <option value="credit"> <?php echo __("Credit", "payplus-payment-gateway") ?></option>
                                    <option value="delayed"> <?php echo __("Delayed", "payplus-payment-gateway") ?></option>
                                    <option value="other"> <?php echo __("Other", "payplus-payment-gateway") ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="flex-item full-amount">
                            <label><?php echo __("Sum", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <input data-sum="<?php echo $order->get_total() ?>" step="0.01" min="1" max="<?php echo $order->get_total() ?>" class="credit-card-payment-payplus input-change price" type="number" placeholder="<?php echo __("Sum", "payplus-payment-gateway") ?>" value="<?php echo floatval($order->get_total()) ?>">
                                <button data-sum="<?php echo $order->get_total() ?>" class="payplus-full-amount credit-card-payment-payplus">
                                    <?php echo __("Full Amount", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex-row payplus_payment" style="display: none">
                        <?php
                        $sum = $order->get_total();
                        $payment = $sum / 2;
                        ?>
                        <div class="flex-item">
                            <label> <?php echo __("Payments", "payplus-payment-gateway") ?></label>
                            <select class="credit-card-payment-payplus input-change number_of_payments" id="number_of_payments" name="number_of_payments">
                                <?php
                                for ($i = 2; $i <= 99; $i++) :
                                    $selected = ($i == 2) ? "selected='selected'" : "";
                                ?>
                                    <option <?php echo $selected ?> value="<?php echo $i ?>">
                                        <?php echo $i ?>
                                    </option>
                                <?php

                                endfor;
                                ?>
                            </select>
                        </div>
                        <div class="flex-item">
                            <label> <?php echo __("First Payment", "payplus-payment-gateway") ?></label>
                            <input name="first_payment" id="first_payment" readonly value="" placeholder="<?php echo __("First Payment", "payplus-payment-gateway") ?>" type="number" class="credit-card-payment-payplus input-change first_payment">
                        </div>
                        <div class="flex-item">
                            <label> <?php echo __("Additional payments", "payplus-payment-gateway") ?></label>
                            <input name="subsequent_payments" id="subsequent_payments" readonly value="" placeholder="<?php echo __("Additional payments", "payplus-payment-gateway") ?>" type="number" class="credit-card-payment-payplus input-change subsequent_payments">

                        </div>
                    </div>
                    <div class="flex-row flex-row-reverse">
                        <div class="flex-item">
                            <button id="credit-card-payment-payplus" class="payplus-paymnet-button">
                                <?php echo __("Save payment", "payplus-payment-gateway") ?> </button>
                        </div>
                    </div>
                </div>
                <!-- End Credit card -->
                <!--  cash card -->
                <div class="select-type-payment cash">
                    <input class="cash-payment-payplus input-change  row_id" type="hidden" value="">
                    <input class="cash-payment-payplus input-change  method_payment" type="hidden" id="method_payment" name="method_payment" value="cash">
                    <div class="flex-row">
                        <div class="flex-item">
                            <label> <?php echo __("Date", "payplus-payment-gateway") ?></label>
                            <input value="<?php echo date("Y-m-d") ?>" required class="cash-payment-payplus input-change create_at" type="date" placeholder="<?php echo __("Date", "payplus-payment-gateway") ?>">
                        </div>
                        <div class="flex-item full-amount">
                            <label><?php echo __("Sum", "payplus-payment-gateway") ?></label>
                            <div class="flex-row">
                                <input data-sum="<?php echo $order->get_total() ?>" step="0.01" min="1" max="<?php echo $order->get_total() ?>" class="cash-payment-payplus input-change price" type="number" placeholder="<?php echo __("Sum", "payplus-payment-gateway") ?>" value="<?php echo floatval($order->get_total()) ?>">
                                <button data-sum="<?php echo $order->get_total() ?>" class="payplus-full-amount cash-payment-payplus""> <?php echo __("Full Amount", "payplus-payment-gateway") ?> </button>
                            </div>

                        </div>
                        <div class=" flex-item">
                                    <label> <?php echo __("Notes", "payplus-payment-gateway") ?></label>
                                    <input value="" placeholder="<?php echo __("Notes", "payplus-payment-gateway") ?>" type="text" class="cash-payment-payplus input-change notes">
                            </div>
                        </div>
                        <div class="flex-row flex-row-reverse">
                            <div class="flex-item">
                                <button id="cash-payment-payplus" class="payplus-paymnet-button">
                                    <?php echo __("Save payment", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                    </div>
                    <div class="select-type-payment payment-check">
                        <input class="payment-check-payment-payplus input-change  row_id" type="hidden" value="">
                        <input class="payment-check-payment-payplus input-change  method_payment" type="hidden" id="method_payment" name="method_payment" value="payment-check">
                        <div class="flex-row">
                            <div class="flex-item">
                                <label> <?php echo __("Date", "payplus-payment-gateway") ?></label>
                                <input value="<?php echo date("Y-m-d") ?>" required class="payment-check-payment-payplus  input-change create_at" type="date" placeholder="<?php echo __("Date", "payplus-payment-gateway") ?>">
                            </div>
                            <div class="flex-item full-amount">
                                <label><?php echo __("Sum", "payplus-payment-gateway") ?></label>
                                <div class="flex-row">
                                    <input data-sum="<?php echo $order->get_total() ?>" step="0.01" min="1" max="<?php echo $order->get_total() ?>" class="payment-check-payment-payplus input-change price" type="number" placeholder="<?php echo __("Sum", "payplus-payment-gateway") ?>" value="<?php echo floatval($order->get_total()) ?>">
                                    <button data-sum="<?php echo $order->get_total() ?>" class="payplus-full-amount payment-check-payment-payplus">
                                        <?php echo __("Full Amount", "payplus-payment-gateway") ?> </button>
                                </div>
                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Bank number", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Bank number", "payplus-payment-gateway") ?>" type="text" class="payment-check-payment-payplus input-change bank_number">
                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Branch number", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Branch number", "payplus-payment-gateway") ?>" type="text" class="payment-check-payment-payplus input-change branch_number">
                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Account number", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Account number", "payplus-payment-gateway") ?>" type="text" class="payment-check-payment-payplus input-change account_number">
                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Check number", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Check number", "payplus-payment-gateway") ?>" type="text" class="payment-check-payment-payplus input-change check_number">
                            </div>
                        </div>
                        <div class="flex-row flex-row-reverse">
                            <div class="flex-item">
                                <button id="payment-check-payment-payplus" class="payplus-paymnet-button">
                                    <?php echo __("Save payment", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                    </div>
                    <div class="select-type-payment bank-transfer">
                        <input class="bank-transfer-payment-payplus input-change  row_id" type="hidden" value="">
                        <input class="bank-transfer-payment-payplus input-change  method_payment" type="hidden" id="method_payment" name="method_payment" value="bank-transfer">
                        <div class="flex-row">
                            <div class="flex-item">
                                <label> <?php echo __("Date", "payplus-payment-gateway") ?></label>
                                <input value="<?php echo date("Y-m-d") ?>" required class="bank-transfer-payment-payplus  input-change create_at" type="date" placeholder="<?php echo __("Date", "payplus-payment-gateway") ?>">
                            </div>
                            <div class="flex-item full-amount">
                                <label><?php echo __("Sum", "payplus-payment-gateway") ?></label>
                                <div class="flex-row">
                                    <input data-sum="<?php echo $order->get_total() ?>" step="0.01" min="1" max="<?php echo $order->get_total() ?>" class="bank-transfer-payment-payplus input-change price" type="number" placeholder="<?php echo __("Sum", "payplus-payment-gateway") ?>" value="<?php echo floatval($order->get_total()) ?>">
                                    <button data-sum="<?php echo $order->get_total() ?> " class="payplus-full-amount bank-transfer-payment-payplus">
                                        <?php echo __("Full Amount", "payplus-payment-gateway") ?> </button>
                                </div>
                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Bank number", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Bank number", "payplus-payment-gateway") ?>" type="text" class="bank-transfer-payment-payplus input-change bank_number">
                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Branch number", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Branch number", "payplus-payment-gateway") ?>" type="text" class="bank-transfer-payment-payplus input-change branch_number">

                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Account number", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Account number", "payplus-payment-gateway") ?>" type="text" class="bank-transfer-payment-payplus input-change account_number">
                            </div>

                        </div>
                        <div class="flex-row flex-row-reverse">
                            <div class="flex-item">
                                <button id="bank-transfer-payment-payplus" class="payplus-paymnet-button">
                                    <?php echo __("Save payment", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                    </div>
                    <div class="select-type-payment payment-app">
                        <input class="payment-app-payment-payplus input-change  row_id" type="hidden" value="">
                        <input class="payment-app-payment-payplus input-change  method_payment" type="hidden" id="method_payment" name="method_payment" value="payment-app">
                        <div class="flex-row">
                            <div class="flex-item">
                                <label> <?php echo __("Date", "payplus-payment-gateway") ?></label>
                                <input value="<?php echo date("Y-m-d") ?>" required class="payment-app-payment-payplus input-change create_at" type="date" placeholder="<?php echo __("Date", "payplus-payment-gateway") ?>">
                            </div>
                            <div class="flex-item full-amount">
                                <label><?php echo __("Sum", "payplus-payment-gateway") ?></label>
                                <div class="flex-row">
                                    <input data-sum="<?php echo $order->get_total() ?>" step="0.01" min="1" max="<?php echo $order->get_total() ?>" class="payment-app-payment-payplus input-change price" type="number" placeholder="<?php echo __("Sum", "payplus-payment-gateway") ?>" value="<?php echo floatval($order->get_total()) ?>">
                                    <button data-sum="<?php echo $order->get_total() ?>" class="payplus-full-amount payment-app-payment-payplus">
                                        <?php echo __("Full Amount", "payplus-payment-gateway") ?> </button>
                                </div>
                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Type", "payplus-payment-gateway") ?></label>
                                <select class="payment-app-payment-payplus input-change payment_app">
                                    <option value="">
                                        <?php echo __("Type", "payplus-payment-gateway") ?>
                                    </option>
                                    <?php
                                    foreach ($installed_payment_methods as $installed_payment_method) : ?>
                                        <option value="<?php echo $installed_payment_method ?>"><?php echo $installed_payment_method ?>
                                        </option>
                                    <?php
                                    endforeach;
                                    ?>
                                </select>
                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Transaction id", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Transaction id", "payplus-payment-gateway") ?>" type="text" class="payment-app-payment-payplus input-change transaction_id">
                            </div>

                        </div>
                        <div class="flex-row flex-row-reverse">
                            <div class="flex-item">
                                <button id="payment-app-payment-payplus" class="payplus-paymnet-button">
                                    <?php echo __("Save payment", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                    </div>
                    <div class="select-type-payment paypal">
                        <input class="paypal-payment-payplus input-change  row_id" type="hidden" value="">
                        <input class="paypal-payment-payplus input-change  method_payment" type="hidden" id="method_payment" name="method_payment" value="paypal">
                        <div class="flex-row">
                            <div class="flex-item">
                                <label> <?php echo __("Date", "payplus-payment-gateway") ?></label>
                                <input value="<?php echo date("Y-m-d") ?>" required class="paypal-payment-payplus  input-change create_at" type="date" placeholder="<?php echo __("Date", "payplus-payment-gateway") ?>">
                            </div>
                            <div class="flex-item full-amount">
                                <label><?php echo __("Sum", "payplus-payment-gateway") ?></label>
                                <div class="flex-row">
                                    <input data-sum="<?php echo $order->get_total() ?>" step="0.01" min="1" max="<?php echo $order->get_total() ?>" class="paypal-payment-payplus input-change price" type="number" placeholder="<?php echo __("Sum", "payplus-payment-gateway") ?>" value="<?php echo floatval($order->get_total()) ?>">
                                    <button data-sum="<?php echo $order->get_total() ?>" class="payplus-full-amount paypal-payment-payplus">
                                        <?php echo __("Full Amount", "payplus-payment-gateway") ?> </button>
                                </div>
                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Payer account", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Payer account", "payplus-payment-gateway") ?>" type="text" class="paypal-payment-payplus input-change payer_account">
                            </div>

                            <div class="flex-item">
                                <label> <?php echo __("Transaction id", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Transaction id", "payplus-payment-gateway") ?>" type="text" class="paypal-payment-payplus input-change transaction_id">
                            </div>

                        </div>
                        <div class="flex-row flex-row-reverse">
                            <div class="flex-item">
                                <button id="paypal-payment-payplus" class="payplus-paymnet-button">
                                    <?php echo __("Save payment", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                    </div>
                    <div class="select-type-payment withholding-tax">
                        <input class="withholding-tax-payment-payplus input-change  row_id" type="hidden" value="">
                        <input class="withholding-tax-payment-payplus input-change  method_payment" type="hidden" id="method_payment" name="method_payment" value="withholding-tax">
                        <div class="flex-row">
                            <div class="flex-item">
                                <label> <?php echo __("Date", "payplus-payment-gateway") ?></label>
                                <input value="<?php echo date("Y-m-d") ?>" required class="withholding-tax-payment-payplus input-change create_at" type="date" placeholder="<?php echo __("Date", "payplus-payment-gateway") ?>">
                            </div>
                            <div class="flex-item full-amount">
                                <label><?php echo __("Sum", "payplus-payment-gateway") ?></label>
                                <div class="flex-row">
                                    <input data-sum="<?php echo $order->get_total() ?>" step="0.01" min="1" max="<?php echo $order->get_total() ?>" class="withholding-tax-payment-payplus input-change price" type="number" placeholder="<?php echo __("Sum", "payplus-payment-gateway") ?>" value="<?php echo floatval($order->get_total()) ?>">
                                    <button data-sum="<?php echo $order->get_total() ?>" class="payplus-full-amount withholding-tax-payment-payplus">
                                        <?php echo __("Full Amount", "payplus-payment-gateway") ?> </button>
                                </div>

                            </div>

                        </div>
                        <div class="flex-row flex-row-reverse">
                            <div class="flex-item">
                                <button id="withholding-tax-payment-payplus" class="payplus-paymnet-button">
                                    <?php echo __("Save payment", "payplus-payment-gateway") ?> </button>
                            </div>
                        </div>
                    </div>
                    <div class="select-type-payment other">
                        <input class="other-payment-payplus input-change  row_id" type="hidden" value="">
                        <input class="other-payment-payplus input-change  method_payment" type="hidden" id="method_payment" name="method_payment" value="other">
                        <div class="flex-row">
                            <div class="flex-item">
                                <label> <?php echo __("Date", "payplus-payment-gateway") ?></label>
                                <input value="<?php echo date("Y-m-d") ?>" required class="other-payment-payplus  input-change create_at" type="date" placeholder="<?php echo __("Date", "payplus-payment-gateway") ?>">
                            </div>
                            <div class="flex-item full-amount">
                                <label><?php echo __("Sum", "payplus-payment-gateway") ?></label>
                                <div class="flex-row">
                                    <input data-sum="<?php echo $order->get_total() ?>" step="0.01" min="1" max="<?php echo $order->get_total() ?>" class="other-payment-payplus input-change price" type="number" placeholder="<?php echo __("Sum", "payplus-payment-gateway") ?>" value="<?php echo floatval($order->get_total()) ?>">
                                    <button data-sum="<?php echo $order->get_total() ?>" class="payplus-full-amount other-payment-payplus">
                                        <?php echo __("Full Amount", "payplus-payment-gateway") ?> </button>
                                </div>
                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Transaction id", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Transaction id", "payplus-payment-gateway") ?>" type="text" class="other-payment-payplus input-change transaction_id">

                            </div>
                            <div class="flex-item">
                                <label> <?php echo __("Notes", "payplus-payment-gateway") ?></label>
                                <input value="" placeholder="<?php echo __("Notes", "payplus-payment-gateway") ?>" type="text" class="other-payment-payplus input-change notes">
                            </div>
                        </div>
                        <div class="flex-row flex-row-reverse">
                            <div class="flex-item">
                                <button id="other-payment-payplus" class="payplus-paymnet-button">
                                    <?php echo __("Save payment", "payplus-payment-gateway") ?> </button>
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
                        <button id="payplus-create-invoice" data-id="<?php echo $orderId ?>" class="button  button-primary"><span class="refund_text"><?php echo __("Create Invoice", "payplus-payment-gateway") ?></span></button>
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
        $chackAllPaymnetTable = in_array($currentStatus, $chackStatus) ? "table" : 'none';

            ?>
            <table data-method="<?php echo (strpos($order->get_payment_method(), 'payplus') !== false) ? true : false ?>" id="payplus-table-payment" style="display: <?php echo $chackAllPaymnetTable ?>" class="wc-order-totals payplus-table-payment">
                <thead>
                    <tr>
                        <th></th>
                        <th><?php echo __("Sum", "payplus-payment-gateway") ?></th>
                        <th><?php echo __("Details", "payplus-payment-gateway") ?></th>
                        <th><?php echo __("Methods of Payment", "payplus-payment-gateway") ?></th>
                        <th><?php echo __("Date", "payplus-payment-gateway") ?></th>

                    </tr>
                </thead>
                <tbody>
                    <?php

                    $detailsAll = [
                        'bank_number', 'account_number', 'branch_number', 'check_number',
                        'four_digits', 'brand_name', 'transaction_type', 'number_of_payments', 'first_payment', 'subsequent_payments',
                        'payment_app', 'transaction_id', 'payer_account', 'notes'
                    ];
                    foreach ($payments as $key => $payment) {
                        $create_at = explode(' ', $payment->create_at);
                        $create_at = explode('-', $create_at[0]);
                        $create_at = $create_at[2] . "-" . $create_at[1] . "-" . $create_at[0];
                        $orderAmount = WC_PayPlus_Order_Data::get_meta($orderId, 'payplus_charged_j5_amount', true) ? WC_PayPlus_Order_Data::get_meta($orderId, 'payplus_charged_j5_amount', true) : $payment->price / 100;
                        $currency_code = $order->get_currency();
                        // Get the currency symbol based on the currency code
                        $currency_symbol = get_woocommerce_currency_symbol($currency_code);

                    ?>
                        <tr>
                            <td></td>
                            <td>
                                <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">
                                            <?php echo $currency_symbol ?></span><?php echo $orderAmount ?></bdi></span>
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
                                            <p> <strong> <?php echo $keyCurrent ?> </strong> : <?php echo $value ?> </p>
                                <?php
                                        endif;
                                    endif;
                                }
                                ?>

                            </td>
                            <td> <?php echo str_replace("-", ' ', $payment->method_payment) ?></td>
                            <td> <?php echo $create_at ?></td>
                        </tr>

                    <?php
                    }

                    ?>
                </tbody>
            </table>
            <div id="payplus_sum_paymnet"></div>
            <?php
        }

        /**
         * @param $order
         * @return void
         */
        public function add_custom_button_to_order($order)
        {
            if ($order->get_status() == 'pending') {
                $payplusResponse = WC_PayPlus_Order_Data::get_meta($order->get_id(), 'payplus_response', true);
                $pageRequestUid = WC_PayPlus_Order_Data::get_meta($order->get_id(), 'payplus_page_request_uid', true);
                if ($payplusResponse !== "" || $pageRequestUid !== "") {
                    $payplusResponse = json_decode($payplusResponse, true);

                    if (isset($payplusResponse['page_request_uid'])) {
                        $pageRequestUid = $payplusResponse['page_request_uid'];
                    }
                    // check if is rtl or ltr
                    $rtl = is_rtl() ? 'left' : 'right';
                    // show button only if pageRequestUid is not empty
                    if (!empty($pageRequestUid)) {
                        echo '<button type="button" data-value="' . $order->get_id() . '" value="' . $pageRequestUid . '" class="button" id="custom-button-get-pp" style="position: absolute;' . $rtl . ': 5px;top:0px;margin: 10px 0 0 0;color: white;background-color: green">Get PayPlus Data</button>';
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
            // $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
            $transaction_uid = $order->get_meta('payplus_transaction_uid');
            $order_validated = $order->get_meta('order_validated');
            $order_validated_error = $order->get_meta('order_validated_error');

            $invoice_manual = $this->payPlusInvoice->payplus_get_create_invoice_manual();
            $checkInvoiceSend = WC_PayPlus_Order_Data::get_meta($orderId, 'payplus_check_invoice_send', true);
            $resultApps = $this->payPlusInvoice->payplus_get_payments($orderId, 'otherClub');
            $checkInvoiceRefundSend = WC_PayPlus_Order_Data::get_meta($orderId, 'payplus_send_refund', true);
            $sum = 0;
            $sumTransactionRefund = array_reduce($resultApps, function ($sum, $item) {
                return $sum + $item->invoice_refund;
            });

            $total = floatval($order->get_total());
            $payplus_related_transactions = WC_PayPlus_Order_Data::get_meta($orderId, 'payplus_related_transactions', true);
            $payplus_response = json_decode(WC_PayPlus_Order_Data::get_meta($orderId, 'payplus_response', true));
            $payplus_response = (array) $payplus_response;
            $selectInvoiceRefund = array(
                '' => __('Type Documents Refund', 'payplus-payment-gateway'),
                'inv_refund' => __('Refund Invoice', 'payplus-payment-gateway'),
                'inv_refund_receipt' => __('Refund Receipt', 'payplus-payment-gateway'),
                'inv_refund_receipt_invoice' => __('Refund Invoice + Refund Receipt', 'payplus-payment-gateway')
            );
            ob_start();
            if (!empty($payplus_related_transactions) && !WC_PayPlus::payplus_check_exists_table()) {
            ?>
                <table class="wc-order-totals payplus-table-refund">
                    <tr class="payplus-row">
                        <th></th>
                        <th><?php echo __('Refund amount', 'payplus-payment-gateway'); ?></th>
                        <th> <?php echo __('Amount already refunded', 'payplus-payment-gateway'); ?></th>
                        <th> <?php echo __('Sum', 'payplus-payment-gateway'); ?></th>
                        <th> <?php echo __('Methods of Payment', 'payplus-payment-gateway'); ?></th>
                    </tr>
                    <?php
                    $result = $this->payplus_get_order_payment($orderId);
                    if (!count($result)) {
                        if (count($payplus_response)) {
                            $this->payplus_add_order($orderId, $payplus_response);
                        } else {
                            $transaction_uid = WC_PayPlus_Order_Data::get_meta($orderId, 'payplus_transaction_uid', true);

                            if (!empty($transaction_uid)) {
                                $payload['transaction_uid'] = $transaction_uid;
                            } else {
                                $payload['more_info'] = $orderId;
                            }
                            $payload['related_transaction'] = true;
                            $payload = json_encode($payload);
                            $data['order_id'] = $orderId;
                            $res = $this->requestPayPlusIpn($payload, $data, 1, 'payplus_process_payment', true);
                            WC_PayPlus_Order_Data::update_meta($order, array('payplus_response' => json_encode($res->data, true)));
                            $this->payplus_add_order($orderId, (array) $res->data);
                        }
                        $result = $this->payplus_get_order_payment($orderId);
                    }
                    if (count($result)) :
                        foreach ($result as $key => $values) :
                            if (!empty($values->method_payment)) :
                                $refund = ($values->price / 100) - ($values->refund / 100);

                    ?>

                                <tr class="payplus-row coupon-<?php echo $values->id ?>">

                                    <td>
                                        <?php

                                        if ($refund) : ?>
                                            <button data-refund="<?php echo $refund ?>" data-method='<?php echo $values->method_payment ?>' data-id="<?php echo $values->id ?>" data-transaction-uid="<?php echo $values->transaction_uid ?>" class="button button-primary width-100 do-api-refund-payplus">
                                                <span class="refund_text"> <?php echo __('Refund', 'payplus-payment-gateway') ?> </span></button>
                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?php
                                        if ($refund) : ?>
                                            <input class="width-100 sum-coupon-<?php echo $values->id ?>" type="number" step="0.1" min="0" max="<?php echo $refund ?>" value="0" />
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <bdi><?php echo $values->refund / 100 ?>&nbsp;<span class="woocommerce-Price-currencySymbol">₪</span></bdi>
                                    </td>
                                    <td>
                                        <span class="woocommerce-Price-amount amount"><bdi><?php echo $values->price / 100 ?>&nbsp;<span class="woocommerce-Price-currencySymbol">₪</span></bdi></span>
                                    </td>
                                    <td class="label label-highlight"><?php echo __($values->method_payment, 'payplus-payment-gateway') ?></td>
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
                    $this->payplus_get_section_invoice_not_automatic($orderId);
                endif;
            }

            if (
                $total && $this->payPlusInvoice->payplus_get_invoice_enable()
                && $invoice_manual && $sumTransactionRefund && !$checkInvoiceRefundSend
            ) {
            ?>
                <div class="payment-order-ajax  payment-invoice" style="margin:20px 0px">
                    <input type="hidden" name="amount-refund-<?php echo $orderId ?>" id="amount-refund-<?php echo $orderId ?>" value="<?php echo $sumTransactionRefund / 100 ?>">
                    <select id="select-type-invoice-refund-<?php echo $orderId ?>" name="select-type-invoice-refund-<?php echo $orderId ?>">
                        <?php

                        foreach ($selectInvoiceRefund as $key => $value) :
                            $flag = true;
                            if ($flag) :
                                $selected = ($this->payPlusInvoice->payplus_get_invoice_type_document_refund() == $key) ?
                                    'selected' : '';
                        ?>
                                <option <?php echo $selected ?> value="<?php echo $key ?>"><?php echo $value ?> </option>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </select>
                    <button id="payplus-create-invoice-refund" data-id="<?php echo $orderId ?>" class="button  button-primary"><span class="refund_text"><?php echo __("Create Invoice refund", "payplus-payment-gateway") ?></span></button>

                </div>
            <?php
            }
            if (($order->get_type() === "shop_subscription" && $order->get_status() === "on-hold")
                || $order_validated_error === "1"
            ) {
            ?>
                <div class="payment-order-ajax">
                    <button id="payment-payplus-transaction" data-id="<?php echo $orderId ?>" class="button  button-primary"><?php echo __("Transaction review", "payplus-payment-gateway") ?></button>
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
                    <button id="payment-payplus-dashboard" data-id="<?php echo $orderId ?>" class="button  button-primary"><?php echo __("Payment", "payplus-payment-gateway") ?></button>
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

            $table = $wpdb->prefix . 'payplus_order_log';
            $logs = $wpdb->get_results(' SELECT  *  FROM  ' . $table . ' WHERE order_id= ' . $orderId . ' AND action_name ="change-status"');

            if ($this->log_status && count($logs)) : ?>
                <div class="flex">

                </div>

                <table class="payplus-change-status wc-order-totals">
                    <tr>
                        <td colspan="4" class="payplus-no-border">
                            <button id="payplus-change-status" class="button button-primary"><?php echo __('Show Log Status', "payplus-payment-gateway") ?></button>
                        </td>
                    </tr>
                    <tr class="payplus-row">
                        <th><?php echo __('Log', "payplus-payment-gateway") ?></th>
                        <th> <?php echo __('Status Transition To', "payplus-payment-gateway") ?></th>
                        <th> <?php echo __('Status Transition From', "payplus-payment-gateway") ?></th>
                        <th><?php echo __("Create At", "payplus-payment-gateway") ?></th>
                    </tr>
                    <?php
                    foreach ($logs as $key => $log) :
                        $tempLogs = explode("\n", $log->log);

                        $dateTime = explode(" ", $log->create_at);
                        $date = explode("-", $dateTime[0]);
                        $date = $date[2] . "-" . $date[1] . "-" . $date[0];
                        $time = $dateTime[1];
                        $dateTime = $date . " " . $time;
                    ?>
                        <tr class="payplus-row">
                            <td class="log-row">
                                <?php
                                foreach ($tempLogs as $key1 => $tempLog) :
                                    if (!empty($tempLog)) :
                                ?>
                                        <p class="log"><?php echo ($key1 + 1) . " ) " . $tempLog ?></p>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </td>
                            <td style="text-align: center">
                                <p><?php echo $log->status_transition_to ?></p>
                            </td>
                            <td style="text-align: center">
                                <p><?php echo $log->status_transition_from ?></p>
                            </td>

                            <td style="text-align: center"><?php echo $dateTime ?></td>
                        </tr>
                    <?php
                    endforeach;
                    ?>
                </table>
    <?php
            endif;
            $output = ob_get_clean();
            echo $output;
        }

        /**
         * @return void
         */
        public function ajax_payplus_token_payment()
        {

            $totalCartAmount = 0;
            $handle = 'payplus_process_j5_payment';
            $urlEdit = site_url();
            if (!empty($_POST)) {
                $postPayPlus = $_POST;
                $this->isInitiated();
                // $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
                $order_id = $postPayPlus['payplus_order_id'];
                $payplusTokenPayment = $postPayPlus['payplus_token_payment'];
                $payplusChargeAmount = $postPayPlus['payplus_charge_amount'];
                $urlEdit = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    echo json_encode(array("urlredirect" => $urlEdit, "status" => false));
                    wp_die();
                }

                if (!($payplusTokenPayment) || !$payplusChargeAmount) {
                    echo json_encode(array("urlredirect" => $urlEdit, "status" => false));
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
                if ($OrderType == "Charge") {
                    echo $urlEdit;
                    wp_die();
                }

                if ($OrderType != "Approval" and $OrderType != "Check") {
                    $order->add_order_note(sprintf(__('The charge in PayPlus already made. Please check your PayPlus account<br />Amount: %s %s', 'payplus-payment-gateway'), $charged_amount, $order->get_currency()));
                    echo json_encode(array("urlredirect" => $urlEdit, "status" => false));
                    wp_die();
                }

                if ($OrderType != "Approval" and $OrderType != "Check") {
                    $this->payplus_add_log_all($handle, 'Transaction Not J5 Or Changed to J4 After Charge');

                    echo json_encode(array("urlredirect" => $urlEdit, "status" => false));
                    wp_die();
                }
                if ($amount == $order->get_total() and $charged_amount == 0) {
                    $chargeByItems = true;
                    $objectProducts = $this->payplus_get_products_by_order_id($order_id, true);
                }
                $totalCartAmount = $objectProducts->amount;
                $payplusRefunded = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_refunded', true);
                if (!$payplusRefunded) {
                    WC_PayPlus_Order_Data::update_meta($order, array('payplus_refunded' => $order->get_total()));
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
                    echo json_encode(array("urlredirect" => $urlEdit, "status" => false));
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

                        $keyMethod = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_alternative_method_name', true);
                        if (empty($keyMethod)) {
                            $keyMethod = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_method', true);
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
                        $order->add_order_note(sprintf(__('PayPlus Charge is Successful<br />Charge Transaction Number: %s<br />Amount: %s %s', 'payplus-payment-gateway'), $res->data->transaction->number, $res->data->transaction->amount, $order->get_currency()));
                        WC_PayPlus_Order_Data::update_meta($order, $insertMeta);
                        $_POST['order_status'] = $order->needs_processing() ? 'wc-processing' : 'wc-completed';
                        $order->payment_complete();

                        echo json_encode(array("urlredirect" => $urlEdit, "status" => true));
                        wp_die();
                    } else {
                        $this->payplus_add_log_all($handle, print_r($response, true), 'error');
                        $order->add_order_note(sprintf(__('PayPlus Charge is Failed<br />Status: %s<br />Description: %s', 'payplus-payment-gateway'), $res->results->status, $res->results->description));
                        $this->error_msg = 2;
                        echo json_encode(array("urlredirect" => $urlEdit, "status" => false));
                        wp_die();
                    }
                }
                add_filter('redirect_post_location', [$this, 'add_notice_query_var'], 99);
            }
            echo json_encode(array("urlredirect" => $urlEdit, "status" => false));
            wp_die();
        }

        /**
         * @return void
         */
        public function load_admin_assets()
        {

            $enabled = false;
            $isInvoice = false;
            if (!empty($_GET) && !empty($_GET['section'])) {
                $currentSection = $_GET['section'];
                $currentPayment = get_option('woocommerce_' . $currentSection . '_settings');
                $enabled = (isset($currentPayment['enabled']) && $currentPayment['enabled'] === "yes") ? false : true;
                $isInvoice = (!empty($_GET['invoicepayplus']) && $_GET['invoicepayplus'] === "1") ? true : false;
            }

            if (isset($_GET['post']) && isset($_GET['action']) && $_GET['action'] === 'edit') {
                $order_id = $_GET['post'];
            }
            // if (isset($_GET['page']) && isset($_GET['action']) && $_GET['page'] === 'wc-orders' && $_GET['action'] === 'edit') {
            //     $order_id = $_GET['id'];
            // }

            if (isset($order_id)) {
                $order = wc_get_order($order_id);
                // Get the currency code
                $currency_code = $order->get_currency();
                // Get the currency symbol based on the currency code
                $currency_symbol = get_woocommerce_currency_symbol($currency_code);
            } else {
                $currency_symbol = get_woocommerce_currency_symbol();
            }

            wp_enqueue_style('payplus', PAYPLUS_PLUGIN_URL . 'assets/css/admin.css', [], time());
            wp_register_script('payplus-admin-payment', PAYPLUS_PLUGIN_URL . '/assets/js/admin-payments.min.js', ['jquery'], time(), true);
            wp_localize_script(
                'payplus-admin-payment',
                'payplus_script_admin',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'error_payment' => __('Cannot Charge more than original order sum', 'payplus-payment-gateway'),
                    "payplus_title_tab" => array(
                        "tab-payplus-error-page" => __('PayPlus Page Error - Settings', 'payplus-payment-gateway'),
                        "tab-invoice-payplus" => __('Invoice+ (PayPlus)', 'payplus-payment-gateway')
                    ),
                    "payplus_enabled_payment" => $enabled,
                    "payplus_invoice" => $isInvoice,
                    "payplus_refund_error" => __('Incorrect amount or amount greater than amount that can be refunded', 'payplus-payment-gateway'),
                    "menu_option" => WC_PayPlus::payplus_get_admin_menu(),

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
                    'payplus_sum' => __('Total payments', 'payplus-payment-gateway'),
                    'delete_confim' => __('Are you sure you want to delete this payment method?', 'payplus-payment-gateway'),
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

            if (!in_array(WC_PayPlus_Order_Data::get_meta($post_id, 'payplus_type', true), ["Approval", "Check"])) {
                return;
            }

            $order = wc_get_order($post_id);
            $total = $order->get_total();

            $class = ($this->check_amount_authorization) ? 'payplus-visibility' : '';
            echo "<li class='wide delayed-payment'>
                    <h3>" . __('Charge Order Using PayPlus', 'payplus-payment-gateway') . "</h3>
                        <input class='" . $class . "'  data-amount='" . $total . "'  type='number' id='payplus_charge_amount' name='payplus_charge_amount' value='" . $total . "' min='0' max='" . $total . "' step='0.01' required />
                        <input type='hidden' id='payplus_order_id' name='payplus_order_id' value='" . $post_id . "'>
                        <button id='payplus-token-payment' type='button' name='payplus-token-payment' class='button button-primary'><span class='dashicons dashicons-cart'></span> " . __('Make Payment', 'payplus-payment-gateway') . "</button>
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

            if (!isset($_POST['payplus-token-payment'])) {
                return;
            }

            $order = wc_get_order($order_id);
            $handle = 'payplus_process_j5_payment';
            $this->isInitiated();
            // $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
            $charged_amount = 0;
            $charged_amount = (float) $order->get_meta('payplus_charged_j5_amount');
            if ($charged_amount) {
                return;
            }

            $OrderType = $order->get_meta('payplus_type');
            $chargeByItems = false;
            $amount = round((float) $_POST['payplus_charge_amount'], 2);
            $transaction_uid = $order->get_meta('payplus_transaction_uid');
            if ($OrderType == "Charge") {
                return;
            }

            if ($amount > $order->get_total()) {
                $this->payplus_add_log_all($handle, 'Cannot Charge more than original order sum');
                $order->add_order_note(sprintf(__('Cannot Charge more than original order sum', 'payplus-payment-gateway'), $charged_amount, $order->get_currency()));
                return false;
            }

            if ($OrderType != "Approval" and $OrderType != "Check") {
                $this->payplus_add_log_all($handle, 'Transaction Not J5 Or Changed to J4 After Charge');
                $order->add_order_note(sprintf(__('The charge in PayPlus already made. Please check your PayPlus account<br />Amount: %s %s', 'payplus-payment-gateway'), $charged_amount, $order->get_currency()));
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
                WC_PayPlus_Order_Data::update_meta($order, $insertMeta);

                $order->add_order_note(sprintf(__('PayPlus Charge is Successful<br />Charge Transaction Number: %s<br />Amount: %s %s', 'payplus-payment-gateway'), $res->data->transaction->number, $res->data->transaction->amount, $order->get_currency()));
                $this->payplus_add_log_all($handle, print_r($res, true), 'completed');
                $_POST['order_status'] = $order->needs_processing() ? 'wc-processing' : 'wc-completed';
                $order->payment_complete();
            } else {
                $order->add_order_note(sprintf(__('PayPlus Charge is Failed<br />Status: %s<br />Description: %s', 'payplus-payment-gateway'), $res->results->status, $res->results->description));
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
                'headers' => [],
                'headers' => array(
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
            if (!isset($_GET['error_msg'])) {
                return;
            }

            $title = __('PayPlus Payment Gateway', 'payplus-payment-gateway');
            $class = 'notice-error';
            switch ($_GET['error_msg']) {
                case 1:
                    $message = __('user or other, please contact payplus support', 'payplus-payment-gateway');
                    break;
                case 2:
                    $message = __('Credit card company declined, check credit card details and credit line', 'payplus-payment-gateway');
                    break;
                default:
                    $message = __('PayPlus Payment Successful', 'payplus-payment-gateway');
                    $class = 'notice-success';
            }
            $output = "<div class='notice $class is-dismissible'><p><b>$title:</b> $message</p></div>";

            echo $output;
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
            $table = $wpdb->prefix . 'payplus_order';
            if ($id) {
                $result = $wpdb->get_results('SELECT  * FROM ' . $table . ' WHERE id= ' . $id);
            } else {
                $result = $wpdb->get_results('SELECT * FROM ' . $table . ' WHERE order_id= ' . $order_id);
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
            $table = $wpdb->prefix . 'payplus_order';
            $result = $this->payplus_get_order_payment(false, $id);
            $refund = $result[0]->refund + ($amount * 100);
            $invoice_refund = $result[0]->invoice_refund + ($amount * 100);
            $result = $wpdb->update($table, array('refund' => $refund, 'invoice_refund' => $invoice_refund), array('id' => $id));

            return $result;
        }

        /**
         * @param int $order_id
         * @param int $refund_id
         * @return void
         */
        public function payplus_after_refund($order_id, $refund_id)
        {

            $refund = new WC_Order_Refund($refund_id);
            $amount = $refund->get_amount();
            $order = $this->payplus_get_order_payment($order_id);
            $payplus_related_transactions = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_related_transactions', true);
            if (empty($payplus_related_transactions)) {
                if (count($order)) {
                    $this->payplus_update_order_payment($order[0]->id, $amount);
                }
            }
            $order = wc_get_order($order_id);
            $payment_method = $order->get_payment_method();
            if (
                $payment_method != ""
                && strpos($payment_method, 'payplus') === false
            ) {

                if (
                    !$this->payPlusInvoice->payplus_get_create_invoice_manual()
                    && floatval($amount)
                ) {
                    $this->payPlusInvoice->payplus_create_document_dashboard(
                        $order_id,
                        $this->payPlusInvoice->payplus_get_invoice_type_document_refund(),
                        array(),
                        $amount,
                        'payplus_order_refund' . $order_id
                    );
                }
            }
        }
    }
    WC_PayPlus_Admin_Payments::get_instance();
