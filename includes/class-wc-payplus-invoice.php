<?php
defined('ABSPATH') || exit; // Exit if accessed directly
// define('REFUND_INVOICE', 'Refund Invoice');
// define('REFUND_RECEIPT', 'Refund Receipt');
// define("COUNT_BALANCE_NAME", 1);

class WC_PayPlus_Invoice extends WC_PayPlus_Gateway
{

    /**
     * The main PayPlus gateway instance. Use get_main_payplus_gateway() to access it.
     *
     * @var null|WC_PayPlus_Gateway
     */
    protected $payplus_gateway = null;

    public $hide_products_invoice;
    public $payplus_invoice_option;
    private $payplus_gateway_option;
    private $payplus_invoice_api_key;
    private $payplus_invoice_secret_key;
    private $payplus_invoice_brand_uid;
    private $payplus_website_code;
    private $payplus_invoice_type_document;
    private $payplus_invoice_type_document_refund;
    private $payplus_invoice_status_order;
    private $payplus_api_url;
    private $url_payplus_create_invoice;
    private $url_payplus_get_invoice;
    public $logging;
    private $payplus_create_invoice_manual;
    private $payment_method;
    private $payment_method_club;
    private $payplus_create_invoice_automatic;
    private $payplus_invoice_manual_list = null;
    private $payplus_is_table_exists = null;
    private $payplus_invoice_send_document_sms;
    private $payplus_invoice_send_document_email;
    private $payplus_unique_identifier;
    private $invoice_notes_no;
    private $invoiceDisplayOnly;
    private $_wpnonce;

    /**
     *
     */
    public function __construct()
    {
        $this->_wpnonce = wp_create_nonce('PayPlusGateWayInvoiceNonce');
        // $this->payplus_gateway_option = get_option('woocommerce_payplus-payment-gateway_settings');
        // $this->payplus_invoice_option = get_option('payplus_invoice_option');

        // $this->invoiceDisplayOnly = isset($this->payplus_invoice_option['display_only_invoice_docs']) && $this->payplus_invoice_option['display_only_invoice_docs'] === 'yes' ? true : false;

        // $this->hide_products_invoice = isset($this->payplus_invoice_option['hide_products_invoice']) ? boolval($this->payplus_invoice_option['hide_products_invoice'] === 'yes') : null;

        // $this->invoice_notes_no = isset($this->payplus_invoice_option['invoices_notes_no']) && $this->payplus_invoice_option['invoices_notes_no'] === 'yes' ? true : false;

        // $this->payplus_invoice_api_key = isset($this->payplus_gateway_option['api_test_mode']) && $this->payplus_gateway_option['api_test_mode'] === 'yes' ? $this->payplus_gateway_option['dev_api_key'] ?? null : $this->payplus_gateway_option['api_key'] ?? null;
        // $this->payplus_invoice_secret_key = isset($this->payplus_gateway_option['api_test_mode']) && $this->payplus_gateway_option['api_test_mode'] === 'yes' ? $this->payplus_gateway_option['dev_secret_key'] ?? null : $this->payplus_gateway_option['secret_key'] ?? null;

        // $this->payplus_invoice_brand_uid = (isset($this->payplus_invoice_option['payplus_invoice_brand_uid'])) ?
        //     $this->payplus_invoice_option['payplus_invoice_brand_uid'] : EMPTY_STRING_PAYPLUS;

        // $this->payplus_invoice_brand_uid = isset($this->payplus_gateway_option['api_test_mode']) && $this->payplus_gateway_option['api_test_mode'] === 'yes' ? (isset($this->payplus_invoice_option['payplus_invoice_brand_uid_sandbox']) ? $this->payplus_invoice_option['payplus_invoice_brand_uid_sandbox'] : null) : $this->payplus_invoice_brand_uid;

        // $this->payplus_create_invoice_automatic = (isset($this->payplus_invoice_option['create-invoice-automatic'])
        //     && $this->payplus_invoice_option['create-invoice-automatic'] == "yes") ?
        //     true : false;

        // $this->payplus_invoice_send_document_sms = (isset($this->payplus_invoice_option['payplus_invoice_send_document_sms'])
        //     && ($this->payplus_invoice_option['payplus_invoice_send_document_sms'] == "on" ||
        //         $this->payplus_invoice_option['payplus_invoice_send_document_sms'] == "yes")) ?
        //     true : false;

        // $this->payplus_invoice_send_document_email = (isset($this->payplus_invoice_option['payplus_invoice_send_document_email'])
        //     && ($this->payplus_invoice_option['payplus_invoice_send_document_email'] == "on" ||
        //         $this->payplus_invoice_option['payplus_invoice_send_document_email'] == "yes")) ?
        //     true : false;

        // $this->payplus_unique_identifier = "";

        // $this->payplus_invoice_status_order = "processing";

        // if (!empty($this->payplus_invoice_option['payplus_invoice_status_order'])) {
        //     $this->payplus_invoice_status_order = $this->payplus_invoice_option['payplus_invoice_status_order'];
        // }

        // $this->payplus_create_invoice_manual = (isset($this->payplus_invoice_option['create-invoice-manual']) &&
        //     $this->payplus_invoice_option['create-invoice-manual'] == "yes") ? true : false;

        // $this->payplus_api_url = ($this->payplus_gateway_option
        //     && isset($this->payplus_gateway_option['api_test_mode'])
        //     && ($this->payplus_gateway_option['api_test_mode'] === "yes"))
        //     ? PAYPLUS_PAYMENT_URL_DEV : PAYPLUS_PAYMENT_URL_PRODUCTION;

        // $this->logging = true;

        // $this->payment_method = array('credit-card', 'bit', 'apple-pay', 'google-pay', 'paypal');
        // $this->payment_method_club = array('multipass', 'valuecard', 'tav-zahav', 'finitione');
        // $this->url_payplus_create_invoice .= $this->payplus_api_url . "books/docs/new/";
        // $this->url_payplus_get_invoice .= $this->payplus_api_url . "books/docs/getBy/unique_identifier/";
        // $this->payplus_invoice_type_document =
        //     (isset($this->payplus_invoice_option['payplus_invoice_type_document'])) ?
        //     $this->payplus_invoice_option['payplus_invoice_type_document'] : "";
        // $this->payplus_invoice_type_document_refund = (isset($this->payplus_invoice_option['payplus_invoice_type_document_refund']))
        //     ? $this->payplus_invoice_option['payplus_invoice_type_document_refund'] : "";

        // $this->payplus_invoice_manual_list = (isset($this->payplus_invoice_option['list-hidden'])) ? $this->payplus_invoice_option['list-hidden'] : null;

        //actions
        add_action('admin_enqueue_scripts', [$this, 'payplus_invoice_css_admin']);
        add_action('admin_head', [$this, 'payplus_menu_css']);

        //filters
        add_filter('manage_edit-shop_order_columns', [$this, 'payplus_invoice_add_order_columns'], 20);
        add_filter('woocommerce_shop_order_list_table_columns', [$this, 'payplus_invoice_add_order_columns'], 20);
        // $this->payplus_is_table_exists = WC_PayPlus::payplus_check_exists_table(wp_create_nonce('PayPlusGateWayNonce'));
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

    public function payplusInvoiceCreator($order_id)
    {
        $this->get_main_payplus_gateway();
        echo '<pre>';
        print_r($this->payplus_gateway->invoice_api->payplus_invoice_option);
        die;
        $order = wc_get_order($order_id);
    }
}
