<?php
defined('ABSPATH') || exit; // Exit if accessed directly
define('REFUND_INVOICE', 'Refund Invoice');
define('REFUND_RECEIPT', 'Refund Receipt');
define("COUNT_BALANCE_NAME", 1);

class PayplusInvoice
{
    private $payplus_invoice_option;
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
    private $logging;
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

    /**
     *
     */
    public function __construct()
    {
        $this->payplus_gateway_option = get_option('woocommerce_payplus-payment-gateway_settings');
        $this->payplus_invoice_option = get_option('payplus_invoice_option');
        $this->invoiceDisplayOnly = $this->payplus_invoice_option['display_only_invoice_docs'] === 'yes' ? true : false;

        $this->invoice_notes_no = isset($this->payplus_invoice_option['invoices_notes_no']) && $this->payplus_invoice_option['invoices_notes_no'] === 'yes' ? true : false;

        $this->payplus_invoice_api_key = (isset($this->payplus_invoice_option['payplus_invoice_api_key'])) ?
            $this->payplus_invoice_option['payplus_invoice_api_key'] : EMPTY_STRING_PAYPLUS;

        $this->payplus_invoice_secret_key = (isset($this->payplus_invoice_option['payplus_invoice_secret_key'])) ?
            $this->payplus_invoice_option['payplus_invoice_secret_key'] : EMPTY_STRING_PAYPLUS;

        $this->payplus_invoice_brand_uid = (isset($this->payplus_invoice_option['payplus_invoice_brand_uid'])) ?
            $this->payplus_invoice_option['payplus_invoice_brand_uid'] : EMPTY_STRING_PAYPLUS;

        $this->payplus_create_invoice_automatic = (isset($this->payplus_invoice_option['create-invoice-automatic'])
            && $this->payplus_invoice_option['create-invoice-automatic'] == "yes") ?
            true : false;

        $this->payplus_invoice_send_document_sms = (isset($this->payplus_invoice_option['payplus_invoice_send_document_sms'])
            && ($this->payplus_invoice_option['payplus_invoice_send_document_sms'] == "on" ||
                $this->payplus_invoice_option['payplus_invoice_send_document_sms'] == "yes")) ?
            true : false;

        $this->payplus_invoice_send_document_email = (isset($this->payplus_invoice_option['payplus_invoice_send_document_email'])
            && ($this->payplus_invoice_option['payplus_invoice_send_document_email'] == "on" ||
                $this->payplus_invoice_option['payplus_invoice_send_document_email'] == "yes")) ?
            true : false;

        $this->payplus_unique_identifier = "";
        if (empty($this->payplus_invoice_api_key)) {
            $this->payplus_invoice_api_key =
                (!empty($this->payplus_gateway_option['api_key'])) ?
                $this->payplus_gateway_option['api_key'] : EMPTY_STRING_PAYPLUS;
        }
        if (empty($this->payplus_invoice_secret_key)) {
            $this->payplus_invoice_secret_key = (!empty($this->payplus_gateway_option['secret_key'])) ?
                $this->payplus_gateway_option['secret_key'] : EMPTY_STRING_PAYPLUS;
        }
        $this->payplus_invoice_status_order = "processing";

        if (!empty($this->payplus_invoice_option['payplus_invoice_status_order'])) {
            $this->payplus_invoice_status_order = $this->payplus_invoice_option['payplus_invoice_status_order'];
        }

        $this->payplus_create_invoice_manual = (isset($this->payplus_invoice_option['create-invoice-manual']) &&
            $this->payplus_invoice_option['create-invoice-manual'] == "yes") ? true : false;

        $this->payplus_api_url = ($this->payplus_invoice_option
            && isset($this->payplus_invoice_option['payplus_enable_sandbox'])
            && ($this->payplus_invoice_option['payplus_enable_sandbox'] === "yes"))
            ? PAYPLUS_PAYMENT_URL_DEV : PAYPLUS_PAYMENT_URL_PRODUCTION;

        $this->logging = true;

        $this->payment_method = array('credit-card', 'bit', 'apple-pay', 'google-pay', 'paypal');
        $this->payment_method_club = array('multipass', 'valuecard', 'tav-zahav', 'finitione');
        $this->url_payplus_create_invoice .= $this->payplus_api_url . "books/docs/new/";
        $this->url_payplus_get_invoice .= $this->payplus_api_url . "books/docs/getBy/unique_identifier/";
        $this->payplus_invoice_type_document =
            (isset($this->payplus_invoice_option['payplus_invoice_type_document'])) ?
            $this->payplus_invoice_option['payplus_invoice_type_document'] : "";
        $this->payplus_invoice_type_document_refund = (isset($this->payplus_invoice_option['payplus_invoice_type_document_refund']))
            ? $this->payplus_invoice_option['payplus_invoice_type_document_refund'] : "";

        $this->payplus_invoice_manual_list = (isset($this->payplus_invoice_option['list-hidden'])) ? $this->payplus_invoice_option['list-hidden'] : null;

        //actions
        add_action('admin_enqueue_scripts', [$this, 'payplus_invoice_css_admin']);
        add_action('wp_ajax_payplus-api-payment-refund', [$this, 'ajax_payplus_api_payment_refund']);
        add_action('admin_head', [$this, 'payplus_menu_css']);

        //filters
        add_filter('manage_edit-shop_order_columns', [$this, 'payplus_invoice_add_order_columns'], 20);
        add_filter('woocommerce_shop_order_list_table_columns', [$this, 'payplus_invoice_add_order_columns'], 20);
        $this->payplus_is_table_exists = WC_PayPlus::payplus_check_exists_table();
    }
    /**
     * @return mixed
     */
    public function payplus_get_invoice_manual_list()
    {

        return $this->payplus_invoice_manual_list;
    }

    /**
     * @return mixed|string
     */
    public function payplus_get_invoice_status_order()
    {

        return $this->payplus_invoice_status_order;
    }
    /**
     * @return mixed|string
     */
    public function payplus_get_create_invoice_automatic()
    {
        return $this->payplus_create_invoice_automatic;
    }

    /**
     * @return bool
     */
    public function payplus_get_create_invoice_manual()
    {
        return $this->payplus_create_invoice_manual;
    }

    /**
     * @return bool
     */
    public function payplus_get_invoice_enable()
    {
        return (isset($this->payplus_invoice_option['payplus_invoice_enable'])
            && ($this->payplus_invoice_option['payplus_invoice_enable'] == "yes"
                || $this->payplus_invoice_option['payplus_invoice_enable'] == "on")) ?
            true : false;
    }

    /**
     * @return mixed|string
     */
    public function payplus_get_invoice_type_document()
    {
        return $this->payplus_invoice_type_document;
    }

    /**
     * @return mixed|string
     */
    public function payplus_get_invoice_type_document_refund()
    {
        return $this->payplus_invoice_type_document_refund;
    }

    /**
     * @return void
     */
    public function payplus_menu_css()
    {
        echo '<style>
            #adminmenu .wp-submenu li.sub-payplus{
                margin: 0px  10px;
                position: relative;
            }
            .sub-payplus.menu-multipass:before{
                content:"... BuyMe,Mifal Hapais  etc";
                position: absolute;
                top:25px;
                left:0px;
                background: #fff;
                color: #000;
                display: none;
                max-width: 160px;
                width: 100%;
                text-align: center;
                border-radius: 15px;
                padding: 5px 5px;
                font-size: 12px;
             }
             .sub-payplus.menu-multipass:hover:before{
                 display: block;
             }
             .toplevel_page_payplus,
             #adminmenu li.menu-top.toplevel_page_payplus:hover,
             #adminmenu li>a.menu-top.toplevel_page_payplus:focus ,
             #adminmenu li.opensub.toplevel_page_payplus>a.menu-top,
             #adminmenu li.opensub.toplevel_page_payplus div.wp-menu-image:before,
             #adminmenu li.toplevel_page_payplus  div.wp-menu-image:before,
             #adminmenu  li.toplevel_page_payplus a:hover
             {
                 background: #34aa54;
                 color:#fff
             }
              </style>';
    }

    /**
     * @param $order
     * @return void
     */
    public function payplus_order_item_add_action_buttons_callback($order)
    {
        ob_start();
        $orderId = (payplus_check_woocommerce_custom_orders_table_enabled()) ? $order->get_id() : $order;
        $payplusInvoiceOriginalDocAddressRefund = WC_PayPlus_Meta_Data::get_meta($orderId, "payplus_refund", true);
        $payplusType = WC_PayPlus_Meta_Data::get_meta($orderId, "payplus_type", true);
        $optionPaypluspaymentgGteway = (object) get_option('woocommerce_payplus-payment-gateway_settings');

        if (
            $this->payplus_get_invoice_enable() && $optionPaypluspaymentgGteway->enabled == "no"
            && !$payplusInvoiceOriginalDocAddressRefund
            && $payplusType !== "Approval"
            && $payplusType !== "Check"
        ) :
?>
            <button type="button" id="order-payment-payplus-refund" data-id="<?php echo $orderId ?>" class="button item-refund"><?php echo __("create invoice refund", "payplus-payment-gateway") ?></button>
            <div class='payplus_loader_refund'></div>

<?php
        endif;
        $output = ob_get_clean();
        echo $output;
    }

    /**
     * @param $order_id
     * @return array
     */
    public function payplus_get_client_by_order_id($order_id)
    {

        $customer = [];
        $order = wc_get_order($order_id);
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $address = trim(str_replace(["'", '"', "\\"], '', $order->get_billing_address_1() . ' ' . $order->get_billing_address_2()));
        $city = str_replace(["'", '"', "\\"], '', $order->get_billing_city());
        $postal_code = str_replace(["'", '"', "\\"], '', $order->get_billing_postcode());
        $customer_country_iso = $order->get_billing_country();
        $customerName = "";
        $vat_number = WC_PayPlus_Meta_Data::get_meta($order_id, '_billing_vat_number', true);
        $company = $order->get_billing_company();

        if ($WC_PayPlus_Gateway->exist_company && !empty($company)) {
            $customerName = $company;
        } else {
            if (!empty($order->get_billing_first_name()) || !empty($order->get_billing_last_name())) {
                $customerName = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            }
            if (!$customerName) {
                $customerName = $company;
            } elseif ($company) {
                $customerName .= " (" . $company . ")";
            }
        }
        if (empty($customerName)) {
            $customer['name'] = __("General Customer - לקוח כללי", 'payplus-payment-gateway');
        } else {
            $customer['name'] = $customerName;
        }
        if (!empty($vat_number)) {
            $customer['vat_number'] = $vat_number;
        }
        $customer['phone'] = $order->get_billing_phone();
        $customer['email'] = $order->get_billing_email();
        $customer['street_name'] = $address;
        $customer['create_customer'] = true;

        if ($city) {
            $customer['city'] = $city;
        }
        if ($postal_code) {
            $customer['postal_code'] = $postal_code;
        }
        if ($customer_country_iso) {
            $customer['country_iso'] = $customer_country_iso;
        }
        return $customer;
    }

    /**
     * @param $order_id
     * @param $typeDocument
     * @param $typePayment
     * @param $nameRefund
     * @return bool
     */
    public function payplus_create_dcoment($order_id, $typeDocument, $typePayment = "", $nameRefund = "")
    {

        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $order = wc_get_order($order_id);
        $payplus_invoice_option = get_option('payplus_invoice_option');

        $payplusTransactionUid = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_transaction_uid', true);
        $payplusApprovalNum = WC_PayPlus_Meta_Data::get_meta($order_id, "payplus_approval_num", true);
        $payplusApprovalNumPaypl = $order->get_transaction_id();
        $payplusApprovalNum = ($payplusApprovalNum) ? $payplusApprovalNum : $payplusApprovalNumPaypl;
        $dual = 1;
        $resultApps = $this->payplus_get_payments($order_id);
        if ($typeDocument === "inv_refund_receipt") {
            $dual = -1;
            $typeDocument = "inv_receipt";
        }
        $payload = array();
        $payload['customer'] = $this->payplus_get_client_by_order_id($order_id);
        $totalCartAmount = 0;
        $totallCart = floatval($order->get_total());
        $objectProducts = $this->payplus_get_products_by_order_id($order_id, $dual);
        $productsItems = $objectProducts->productsItems;
        $payload['currency_code'] = $order->get_currency();
        $payload['autocalculate_rate'] = true;
        $payload['totalAmount'] = $dual * $totallCart;
        $payload['language'] = $this->payplus_invoice_option['payplus_langrage_invoice'];
        $payload['more_info'] = $order_id;
        $payload['send_document_email'] = $this->payplus_invoice_send_document_email;
        $payload['send_document_sms'] = $this->payplus_invoice_send_document_sms;

        if (!empty($payplusTransactionUid)) {
            $payload['transaction_uuid'] = $payplusTransactionUid;
        }
        if (!count($resultApps)) {
            $objectInvoicePaymentNoPayplus = array('meta_key' => 'other', 'price' => $dual * $totallCart * 100);
            $objectInvoicePaymentNoPayplus = (object) $objectInvoicePaymentNoPayplus;
            $resultApps[] = $objectInvoicePaymentNoPayplus;
        }
        $sumPayment = floatval($this->payplus_sum_payment($resultApps));
        if ($totalCartAmount == $sumPayment || $totalCartAmount == $order->get_total()) {
            $payload['items'] = $productsItems;
            $payload['totalAmount'] = $dual * $totallCart;
        } else {
            $payload['items'][] = [
                'name' => __('General product', 'payplus-payment-gateway'),
                'quantity' => 1,
                'price' => $sumPayment,
            ];
            $payload['totalAmount'] = $sumPayment;
        }
        $payload = array_merge($payload, $this->payplus_get_payments_invoice($resultApps, $payplusApprovalNum, $dual, $order->get_total()));
        $handle = 'payplus_process_invoice';
        $handle .= ($typePayment == "charge") ? "" : "_refund";

        $WC_PayPlus_Gateway->payplus_add_log_all($handle, 'Fired  (' . $order_id . '  ) ');
        $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($payload, true), 'payload');

        $payload = json_encode($payload);

        $response = $this->post_payplus_ws($this->url_payplus_create_invoice . $typeDocument, $payload);

        if (is_wp_error($response)) {
            $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($response, true), 'error');
            return false;
        } else {
            $res = json_decode(wp_remote_retrieve_body($response));
            if ($res->status === "success") {
                $responeType = ($typePayment == "charge") ? "" : "_refund_";
                $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($res, true), 'completed');
                $refundsJson = WC_PayPlus_Meta_Data::get_meta($order, "payplus_refunds");
                $refundsArray = !empty($refundsJson) > 0 ? json_decode($refundsJson, true) : $refundsJson;
                $refundsArray[$res->details->number]['link'] = $res->details->originalDocAddress;
                $refundsArray[$res->details->number]['type'] = $nameRefund;
                $insetData["payplus_refunds"] = json_encode($refundsArray);
                $insetData["payplus_invoice_docUID_refund_" . $responeType] = $res->details->docUID;
                $insetData["payplus_invoice_numberD_refund_" . $responeType] = $res->details->number;
                $insetData["payplus_invoice_originalDocAddress_refund_" . $responeType] = $res->details->originalDocAddress;
                $insetData["payplus_invoice_copyDocAddress" . $responeType] = $res->details->copyDocAddress;
                $insetData["payplus_invoice_customer_uuid" . $responeType] = $res->details->customer_uuid;
                $insetData["payplus_check_invoice_send_refund"] = 1;
                WC_PayPlus_Meta_Data::update_meta($order, $insetData);
                if (!$this->invoice_notes_no) {
                    $titleNote = ($typePayment == "charge") ? "PayPlus Document" : "PayPlus Document " . $nameRefund;
                    $link = ($typePayment == "charge") ? __('Link Document', 'payplus-payment-gateway') : __('Link Document Refund', 'payplus-payment-gateway');
                    $order->add_order_note('<div style="font-weight:600">' . $titleNote . '</div>
                     <a class="link-invoice" target="_blank" href="' . $res->details->originalDocAddress . '">' . $link . '</a>');
                }

                return true;
            } else {
                $order->add_order_note('<div style="font-weight:600">PayPlus Error Invoice</div>' . $res->error);
                $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($res, true), 'error');
                return false;
            }
        }
    }

    /**
     * @param $order_id
     * @param $payplus_invoice_type_document_refund
     * @param $payments
     * @param $sum
     * @param $unique_identifier
     * @return array
     */
    public function generatePayloadInvoice($order_id, $payplus_invoice_type_document_refund, $resultApps, $sum, $unique_identifier)
    {

        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $payload = array();
        $productsItems = [];
        $payplus_invoice_rounding_decimals = $WC_PayPlus_Gateway->rounding_decimals;
        $payplus_invoice_option = get_option('payplus_invoice_option');

        $order = wc_get_order($order_id);
        $payplusApprovalNum = WC_PayPlus_Meta_Data::get_meta($order_id, "payplus_approval_num", true);
        $payplusTransactionUid = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_transaction_uid', true);

        $dual = 1;
        if ($payplus_invoice_type_document_refund === "inv_refund_receipt") {
            $dual = -1;
        }

        $payload['customer'] = $this->payplus_get_client_by_order_id($order_id);
        if (!empty($this->payplus_invoice_brand_uid)) {
            $payload['brand_uuid'] = $this->payplus_invoice_brand_uid;
        }
        if (!empty($payplusTransactionUid)) {
            $payload['transaction_uuid'] = $payplusTransactionUid;
        }
        $objectProducts = $this->payplus_get_products_by_order_id($order_id, $dual);
        $payplusBalanceNames = $objectProducts->balanceNames;
        if ($sum == round($order->get_total(), $WC_PayPlus_Gateway->rounding_decimals)) {
            $productsItems = $objectProducts->productsItems;
            $sum = $objectProducts->amount;
        }
        $payload['currency_code'] = $order->get_currency();
        $payload['autocalculate_rate'] = true;
        $payload['totalAmount'] = round($sum, $WC_PayPlus_Gateway->rounding_decimals);
        $payload['language'] = $payplus_invoice_option['payplus_langrage_invoice'];
        $payload['more_info'] = $order_id;
        if (count($productsItems)) {
            $payload['items'] = $productsItems;
        } else {
            $sum = $sum * $dual;
            $sum = round($sum, $WC_PayPlus_Gateway->rounding_decimals);
            $payload['totalAmount'] = $sum;
            $payload['items'][] = array(
                'name' => __('Refund for Order Number: ', 'payplus-payment-gateway') . $order_id,
                'price' => $sum,
                "quantity" => 1,
            );
        }
        $payload['send_document_email'] = $this->payplus_invoice_send_document_email;
        $payload['send_document_sms'] = $this->payplus_invoice_send_document_sms;

        if (!empty($unique_identifier)) {
            $payload['unique_identifier'] = $unique_identifier . $this->payplus_unique_identifier . $this->payplus_invoice_option['payplus_website_code'];
        }
        if (!count($resultApps)) {
            $method_payment = 'other';
            $otherMethod = strtolower($order->get_payment_method_title());
            if (strpos($otherMethod, 'paypal') !== false) {
                $method_payment = 'paypal';
            }
            $objectInvoicePaymentNoPayplus = array('method_payment' => $method_payment, 'price' => ($dual * $sum) * 100);
            $objectInvoicePaymentNoPayplus = (object) $objectInvoicePaymentNoPayplus;
            $resultApps[] = $objectInvoicePaymentNoPayplus;
        }
        $payload = array_merge($payload, $this->payplus_get_payments_invoice($resultApps, $payplusApprovalNum, $dual, $order->get_total()));
        if ($WC_PayPlus_Gateway->balance_name && count($payplusBalanceNames)) {
            if (count($payplusBalanceNames) == COUNT_BALANCE_NAME) {
                $payload['customer']['balance_name'] = $payplusBalanceNames[COUNT_BALANCE_NAME - 1];
            }
        }
        return $payload;
    }

    /**
     * @param $order_id
     * @param $documentType
     * @param $payload
     * @param $nameDocment
     * @return bool
     */
    public function createRefundInvoice($order_id, $documentType, $payload, $nameDocment)
    {
        $order = wc_get_order($order_id);
        $payload = json_encode($payload);
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $handle = 'payplus_process_invoice_refund';
        $WC_PayPlus_Gateway->payplus_add_log_all($handle, 'Fired  (' . $order_id . '  )');
        $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($payload, true), 'payload');
        $response = $this->post_payplus_ws($this->url_payplus_create_invoice . $documentType, $payload);
        if (is_wp_error($response)) {
            $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($response, true), 'error');
            return false;
        } else {
            $res = json_decode(wp_remote_retrieve_body($response));
            if ($res->status === "success") {
                $responeType = "_refund" . $documentType;
                $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($res, true), 'completed');
                $refundsJson = WC_PayPlus_Meta_Data::get_meta($order, "payplus_refunds");
                $refundsArray = !empty($refundsJson) > 0 ? json_decode($refundsJson, true) : $refundsJson;
                $refundsArray[$res->details->number]['link'] = $res->details->originalDocAddress;
                $refundsArray[$res->details->number]['type'] = $nameDocment;
                $insetData["payplus_refunds"] = json_encode($refundsArray);
                $insetData["payplus_invoice_docUID_refund_" . $responeType] = $res->details->docUID;
                $insetData["payplus_invoice_numberD_refund_" . $responeType] = $res->details->number;
                $insetData["payplus_invoice_originalDocAddress_refund_" . $responeType] = $res->details->originalDocAddress;
                $insetData["payplus_invoice_copyDocAddress" . $responeType] = $res->details->copyDocAddress;
                $insetData["payplus_invoice_customer_uuid" . $responeType] = $res->details->customer_uuid;
                $insetData["payplus_check_invoice_send_refund"] = 1;
                WC_PayPlus_Meta_Data::update_meta($order, $insetData);
                if (!$this->invoice_notes_no) {
                    $titleNote = "PayPlus Document " . $nameDocment;
                    $link = __('Link Document Refund', 'payplus-payment-gateway');
                    $order->add_order_note('<div style="font-weight:600">' . $titleNote . '</div>
                     <a class="link-invoice" target="_blank" href="' . $res->details->originalDocAddress . '">' . $link . '</a>');
                }
                return true;
            } else {
                $order->add_order_note('<div style="font-weight:600">PayPlus Error Invoice</div>' . $res->error);
                $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($res, true), 'error');
                return false;
            }
        }
    }

    /**
     * @param $order_id
     * @param $payplus_invoice_type_document_refund
     * @param $payments
     * @param $sum
     * @param $unique_identifier
     * @return void
     */
    public function payplus_create_document_dashboard($order_id, $payplus_invoice_type_document_refund, $payments, $sum, $unique_identifier = null)
    {
        if ($payplus_invoice_type_document_refund === "inv_refund_receipt") {
            $payplus_document_type = "inv_refund_receipt";
            $payload = $this->generatePayloadInvoice($order_id, $payplus_document_type, $payments, $sum, null);
            $payplus_document_type = "inv_receipt";
            $this->createRefundInvoice($order_id, $payplus_document_type, $payload, REFUND_RECEIPT);
        } else if ($payplus_invoice_type_document_refund == "inv_refund_receipt_invoice") {
            $payload = $this->generatePayloadInvoice($order_id, 'inv_refund', $payments, $sum, null);
            $this->createRefundInvoice($order_id, 'inv_refund', $payload, REFUND_INVOICE);
            $payplus_document_type = "inv_receipt";
            $payload = $this->generatePayloadInvoice($order_id, 'inv_refund_receipt', $payments, $sum, null);
            $this->createRefundInvoice($order_id, $payplus_document_type, $payload, REFUND_RECEIPT);
        } else {
            $payload = $this->generatePayloadInvoice($order_id, $payplus_invoice_type_document_refund, $payments, $sum, null);
            $this->createRefundInvoice($order_id, $payplus_invoice_type_document_refund, $payload, REFUND_INVOICE);
        }
    }

    /**
     * @return void
     */
    public function ajax_payplus_api_payment_refund()
    {
        if (!empty($_POST)) {

            $order_id = $_POST['order_id'];
            $order = wc_get_order($order_id);
            $urlEdit = get_admin_url() . "post.php?post=" . $order_id . "&action=edit";
            $payplus_document_type = $this->payplus_invoice_option['payplus_invoice_type_document_refund'];
            if ($payplus_document_type == "inv_refund_receipt_invoice") {
                $resultinvoice = $this->payplus_create_dcoment($order_id, 'inv_refund', '', REFUND_INVOICE);
                $resultReceipt = $this->payplus_create_dcoment($order_id, "inv_refund_receipt", '', REFUND_RECEIPT);
                if ($resultinvoice && $resultReceipt) {
                    echo json_encode(array("urlredirect" => $urlEdit, "status" => true));
                    WC_PayPlus_Meta_Data::update_meta($order, array('payplus_refund' => true));
                    wp_die();
                } else {
                    echo json_encode(array("urlredirect" => $urlEdit, "status" => false));
                    wp_die();
                }
            } else {
                $nameRefund = ($payplus_document_type == "inv_refund") ? REFUND_INVOICE : REFUND_RECEIPT;
                $resultinvoice = $this->payplus_create_dcoment($order_id, $payplus_document_type, '', $nameRefund);

                if ($resultinvoice) {
                    echo json_encode(array("urlredirect" => $urlEdit, "status" => true));
                    WC_PayPlus_Meta_Data::update_meta($order, array('payplus_refund' => true));
                    wp_die();
                } else {
                    echo json_encode(array("urlredirect" => $urlEdit, "status" => false));
                    wp_die();
                }
            }
        }
        wp_die();
    }

    /**
     * @param $column
     * @param $order
     * @return void
     */
    public function payplus_add_order_column_order_invoice($column, $order)
    {
        if ($order) {

            $order = is_numeric($order) ? $order : $order->get_id();
            $payplus_invoice_option = $this->payplus_get_invoice_enable();

            if (('order_invoice' === $column && $payplus_invoice_option) || ('order_invoice' === $column && $this->invoiceDisplayOnly)) {
                WC_PayPlus_Statics::invoicePlusDocsSelect($order, ['no-headlines' => true]);
            }
        }
    }

    /**
     * @param $columns
     * @return array|mixed
     */
    public function payplus_invoice_add_order_columns($columns)
    {
        $payplus_invoice_option = $this->payplus_get_invoice_enable();
        $new_columns = array();
        if ($payplus_invoice_option || $this->invoiceDisplayOnly) {
            if (count($columns)) {
                foreach ($columns as $column_name => $column_info) {
                    $new_columns[$column_name] = $column_info;
                    if ('shipping_address' === $column_name) {
                        $new_columns['order_invoice'] = "<span class='text-center'>" . __('Link Document  ', 'payplus-payment-gateway') . "</span>";
                    }
                }
            }
            $columns = $new_columns;
        }

        return $columns;
    }

    /**
     * @param $hook
     * @return void
     */
    public function payplus_invoice_css_admin($hook)
    {
        $current_screen = get_current_screen();

        if (
            strpos($current_screen->base, 'page_invoice-payplus') !== false
            || $current_screen->id === "edit-shop_order" ||
            $current_screen->id === "shop_order"
            || $current_screen->id === "woocommerce_page_wc-orders"
        ) {
            wp_enqueue_style('payplus_invoice-admin-css', PAYPLUS_PLUGIN_URL . 'assets/css/invoice_admin.min.css', __FILE__);
        }
    }

    /**
     * @param $order_id
     * @return bool
     */
    public function payplus_check_vat_payment($order_id)
    {
        $handle = 'payplus_process_invoice';
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $order = wc_get_order($order_id);
        $customer_country_iso = $order->get_billing_country();
        $WC_PayPlus_Gateway->payplus_add_log_all($handle . "_log", 'paying_vat:' . $WC_PayPlus_Gateway->paying_vat);
        $WC_PayPlus_Gateway->payplus_add_log_all($handle . "_log", 'customer_country_iso:' . $customer_country_iso);
        $WC_PayPlus_Gateway->payplus_add_log_all($handle . "_log", 'paying_vat_iso_code:' . $WC_PayPlus_Gateway->paying_vat_iso_code);
        if ($WC_PayPlus_Gateway->paying_vat == "2") {
            if (trim(strtolower($customer_country_iso)) != trim(strtolower($WC_PayPlus_Gateway->paying_vat_iso_code))) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return int
     */
    public function getRateShipping()
    {
        global $wpdb;
        $tax_rate_shipping = 0;
        $rates = $wpdb->get_results($wpdb->prepare("SELECT *
            FROM {$wpdb->prefix}woocommerce_tax_rates"));

        if (count($rates)) {
            if ($rates[0]->tax_rate_country == "" || $rates[0]->tax_rate_country == 'IL') {
                $tax_rate_shipping = intval($rates[0]->tax_rate_shipping);
            }
        }
        return $tax_rate_shipping;
    }

    /**
     * @param $order_id
     * @param $dual
     * @return object
     */
    public function payplus_get_products_by_order_id($order_id, $dual)
    {

        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $order = wc_get_order($order_id);
        $tax_rate_shipping = $this->getRateshipping();

        $productsItems = array();
        $totalCartAmount = 0;
        $arrBalanceName = array();
        $allProductSku = "";
        $temptax = payplus_woocommerce_get_tax_rates($order);
        $isAdmin = is_admin();
        $items = $order->get_items(['line_item', 'fee', 'coupon']);
        $tax = 1;
        $wc_tax_enabled = wc_tax_enabled();
        if (is_numeric($temptax)) {
            $tax = 1 + ($temptax / 100);
        }

        foreach ($items as $item => $item_data) {
            $discount = 0;
            $product = new WC_Product($item_data['product_id']);
            $balanceName = WC_PayPlus_Meta_Data::get_meta($item_data['product_id'], 'payplus_balance_name', true);
            if (!empty($balanceName)) {
                $arrBalanceName[] = $balanceName;
            }
            $dataArr = $item_data->get_data();

            $item_name = $item_data['name'];
            $name = str_replace(["'", '"', "\n", "\\", '”'], '', strip_tags($item_data['name']));
            $quantity = ($item_data['quantity'] ? round($item_data['quantity'], $WC_PayPlus_Gateway->rounding_decimals) : '1');
            $meta_html = wc_display_item_meta($item_data, array(
                'before' => '', 'after' => '',
                'separator' => ' | ', 'echo' => false, 'autop' => false
            ));

            if ($item_data['type'] == "coupon") {
                $allProductSku .= (empty($allProductSku)) ? " ( " . $name : ' , ' . $name;
            } else {
                if ($item_data['type'] == "fee") {
                    $productPrice = $item_data['total'];
                    if ($WC_PayPlus_Gateway->rounding_decimals != 0 && $wc_tax_enabled) {
                        $productPrice += $item_data['total_tax'];
                    }
                    $productPrice *= $dual;
                    $productPrice = round($productPrice, $WC_PayPlus_Gateway->rounding_decimals);
                    $totalCartAmount += ($productPrice);
                } else {
                    if ($WC_PayPlus_Gateway->single_quantity_per_line == 'yes') {
                        $productPrice = $order->get_item_subtotal($item_data, $wc_tax_enabled) * $quantity * $dual;
                        $productPrice = round($productPrice, $WC_PayPlus_Gateway->rounding_decimals);
                        $totalCartAmount += $productPrice;
                        $item_name .= ' ×  ' . $quantity;
                        $quantity = 1;
                    } else {
                        if ($WC_PayPlus_Gateway->rounding_decimals == 0 && $wc_tax_enabled) {
                            $productPrice = $order->get_item_subtotal($item_data);
                        } else {
                            $productPrice = $order->get_item_subtotal($item_data, $wc_tax_enabled);
                        }
                        $productPrice *= $dual;
                        $productPrice = round($productPrice, $WC_PayPlus_Gateway->rounding_decimals);
                        if ($isAdmin && $item_data->get_subtotal() !== $item_data->get_total()) {
                            $discount = (($item_data->get_subtotal() - $item_data->get_total()) * $tax);
                            if ($dual == -1) {
                                $discount *= $dual;
                            }
                            $discount = round($discount, $WC_PayPlus_Gateway->rounding_decimals);
                        }

                        $totalCartAmount += ($productPrice * $quantity) - $discount;
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
                    $productVariation = new WC_Product_Variation($dataArr['variation_id']);
                    $productSKU = $productVariation->get_sku();
                }

                $itemDetails = [
                    'name' => str_replace(["'", '"', "\n", "\\", '”'], '', strip_tags($item_name)),
                    'barcode' => (string) $productSKU,
                    'quantity' => ($quantity ? $quantity : '1'),
                    'price' => round($productPrice, $WC_PayPlus_Gateway->rounding_decimals),
                ];
                if ($discount) {
                    $itemDetails['discount_type'] = 'amount';
                    $itemDetails['discount_value'] = $discount;
                }
                if ($productImageData && isset($productImageData[0])) {
                    $itemDetails['image_url'] = $productImageData[0];
                }

                if (!empty($meta_html) && $WC_PayPlus_Gateway->send_variations) {
                    $itemDetails['product_invoice_extra_details'] = str_replace(["'", '"', "\n", "\\"], '', strip_tags($meta_html));
                }

                if ($item_data->get_tax_status() == 'none' || !$wc_tax_enabled) {
                    $itemDetails['vat_type_code'] = 'vat-type-exempt';
                } else {
                    $itemDetails['vat_type_code'] = 'vat-type-included';
                }

                $productsItems[] = $itemDetails;
            }
        }

        $shipping_methods = $order->get_shipping_methods();
        foreach ($shipping_methods as $shipping_method) {
            $shipping_tax = 0;
            $shipping_method_data = $shipping_method->get_data();
            $shipping_total = $order->get_shipping_total();
            if ($WC_PayPlus_Gateway->rounding_decimals != 0 && $wc_tax_enabled) {
                $shipping_tax = $order->get_shipping_tax();
            }
            $productPrice = ($shipping_total + $shipping_tax) * $dual;

            /*   if (
            || $shipping_method_data['method_id'] === "free_shipping"
            || ($shipping_method_data['method_id'] === "woo-ups-pickups" && $WC_PayPlus_Gateway->enable_pickup)
            || ($shipping_method_data['method_id'] === "local_pickup" && $WC_PayPlus_Gateway->is_Local_pickup)
            ) {*/
            $description = "";
            if ($shipping_method_data['method_id'] === "woo-ups-pickups") {
                $description = $WC_PayPlus_Gateway->getDiscrptionUpPickup($order_id);
            }

            $name = __('Shipping', 'payplus-payment-gateway') . ' - ' . str_replace(["'", '"', "\\"], '', $shipping_method_data['name']) . ' ' . $description;
            $itemDetails = [
                'name' => $name,
                'barcode' => 'order-shipping',
                'quantity' => 1,
                'price' => round($productPrice, $WC_PayPlus_Gateway->rounding_decimals),

            ];
            if ($tax_rate_shipping || !$wc_tax_enabled) {
                $itemDetails['vat_type_code'] = 'vat-type-included';
            }
            $productsItems[] = $itemDetails;
            $totalCartAmount += $productPrice;
            // }

        }
        if (!$isAdmin && $order->get_total_discount()) {
            $productCouponPrice = ($order->get_total_discount());
            if ($WC_PayPlus_Gateway->rounding_decimals != 0 && $wc_tax_enabled) {
                $productCouponPrice += $order->get_discount_tax();
            }
            $productCouponPrice *= -1;
            $productCouponPrice *= $dual;
            $productCouponPrice = round($productCouponPrice, $WC_PayPlus_Gateway->rounding_decimals);
            $totalCartAmount += $productCouponPrice;

            $itemDetails = [
                'name' => ($allProductSku) ? $allProductSku . " ) " : __('Discount coupons', 'payplus-payment-gateway'),
                'barcode' => __('Discount coupons', 'payplus-payment-gateway'),
                'quantity' => 1,
                'price' => round($productCouponPrice, $WC_PayPlus_Gateway->rounding_decimals),
            ];
            $productsItems[] = $itemDetails;
        }
        if ($WC_PayPlus_Gateway->rounding_decimals == 0 && $order->get_total_tax()) {
            $productPrice = round($order->get_total_tax(), $WC_PayPlus_Gateway->rounding_decimals);
            $productPrice *= $dual;
            $itemDetails = [
                'name' => __("Round", "payplus-payment-gateway"),
                'quantity' => 1,
                'price' => $productPrice,
                'is_summary_item' => true,

            ];
            $productsItems[] = $itemDetails;
            $totalCartAmount += $productPrice;
        }
        $gift_cards = $order->get_meta('_ywgc_applied_gift_cards');
        $updated_as_fee = $order->get_meta('ywgc_gift_card_updated_as_fee');
        $priceGift = 0;
        $allProductSku = "";
        if ($gift_cards && $updated_as_fee == false) {

            foreach ($gift_cards as $key => $gift) {
                $productPrice = -1 * ($gift) * $dual;
                $allProductSku .= (empty($allProductSku)) ? " ( " . $key : ' , ' . $key;
                $priceGift += round($productPrice, $WC_PayPlus_Gateway->rounding_decimals);
            }

            $itemDetails = [
                'name' => ($allProductSku) ? $allProductSku . " ) " : __('Discount coupons', 'payplus-payment-gateway'),
                'barcode' => __('Discount coupons', 'payplus-payment-gateway'),
                'quantity' => 1,
                'price' => $priceGift,
            ];
            $productsItems[] = $itemDetails;
            $totalCartAmount += $priceGift;
        }
        $productsItems = $this->payplus_set_vat_all_product($order_id, $productsItems);
        $totalCartAmount = round($totalCartAmount, $WC_PayPlus_Gateway->rounding_decimals);
        $return = (object) ["productsItems" => $productsItems, 'amount' => $totalCartAmount, 'balanceNames' => $arrBalanceName];
        return $return;
    }

    /**
     * @param $order_id
     * @param $productsItems
     * @return array
     */
    public function payplus_set_vat_all_product($order_id, $productsItems)
    {
        $handle = 'payplus_process_invoice';
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $payingVatAllOrder = $WC_PayPlus_Gateway->paying_vat_all_order === "yes";
        $changevatInEilat = $WC_PayPlus_Gateway->change_vat_in_eilat && $WC_PayPlus_Gateway->payplus_check_is_vat_eilat($order_id);
        $OtherVatCountry = $this->payplus_check_vat_payment($order_id) || $WC_PayPlus_Gateway->paying_vat == "1";
        foreach ($productsItems as $key => $productsItem) {

            if ($payingVatAllOrder) {
                $productsItems[$key]['vat_type_code'] = 'vat-type-included';
            }
            if ($changevatInEilat) {
                $productsItems[$key]['vat_type_code'] = 'vat-type-exempt';
            }
            if ($OtherVatCountry) {
                $productsItems[$key]['vat_type_code'] = 'vat-type-exempt';
            }
        }
        return $productsItems;
    }

    /**
     * @param $resultApps
     * @return int
     */
    public function payplus_sum_payment($resultApps)
    {

        $total = array_reduce($resultApps, function ($sum, $entry) {
            $sum += ($entry->price / 100);
            return $sum;
        }, 0);
        return $total;
    }

    /**
     * @param $order_id
     * @return array|object|stdClass[]|null
     */
    public function payplus_get_payments($order_id, $notPayment = '')
    {
        global $wpdb;
        if (!WC_PayPlus::payplus_check_exists_table()) {
            $payplus_related_transactions = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_related_transactions', true);
            if (empty($notPayment)) {
                $sql = "SELECT *  FROM {$wpdb->prefix}payplus_order WHERE order_id =" . $order_id . " AND delete_at =0 ";

                if ($payplus_related_transactions) {
                    $sql .= " AND related_transactions=0 ";
                }
            } else {
                $sql = "SELECT *  FROM {$wpdb->prefix}payplus_order WHERE order_id =" . $order_id . " AND delete_at =0";
            }
            $resultApps = $wpdb->get_results($sql, OBJECT);
        } else {
            $sql = "SELECT * FROM {$wpdb->prefix}postmeta WHERE post_id = " . $order_id . " AND (";

            foreach ($this->payment_method as $key => $value) {
                $operator = ($key) ? ' OR ' : '';
                $sql .= $operator . " meta_key LIKE '%payplus_" . $value . "%'";
            }
            if (empty($notPayment)) {
                foreach ($this->payment_method_club as $key => $value) {
                    $operator = ' OR ';
                    $sql .= $operator . " meta_key LIKE '%payplus_" . $value . "%'";
                }
            }
            $sql .= " ) ";
            $resultApps = $wpdb->get_results($sql, OBJECT);
            $resultApps = $this->payplus_set_object_paymnet($order_id, $resultApps);
        }
        return $resultApps;
    }
    public function payplus_set_object_paymnet($order_id, $resultApps)
    {
        $arr = array();
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        foreach ($resultApps as $key => $resultApp) {
            $objectPayment = new stdClass();
            $objectPayment->order_id = $order_id;
            $objectPayment->method_payment = str_replace("payplus_", '', $resultApp->meta_key);
            $objectPayment->price = round(floatval($resultApp->meta_value) * 100, $WC_PayPlus_Gateway->rounding_decimals);
            $objectPayment->four_digits = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_four_digits', true);
            $objectPayment->brand_name = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_brand_name', true);
            $objectPayment->number_of_payments = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_number_of_payments', true);
            $arr[] = $objectPayment;
        }
        return $arr;
    }

    /**
     * @param $order_id
     * @return void
     */
    public function payplus_invoice_create_order_automatic($order_id)
    {
        $order = wc_get_order($order_id);
        $typePaymentMethod = $order->get_payment_method();
        if ($typePaymentMethod == "bacs" || $typePaymentMethod == "cod") {
            $this->payplus_invoice_create_order($order_id, 'inv_tax');
        }
    }
    public function payplus_check_sum_withholding_tax($payments)
    {

        $sumWithholdingTax = array_reduce($payments, function ($sum, $entry) {
            if ($entry->method_payment == 'withholding-tax') {
                $sum += ($entry->price / 100);
            }

            return $sum;
        }, 0);
        $sumNotWithholdingTax = array_reduce($payments, function ($sum, $entry) {
            if ($entry->method_payment != 'withholding-tax') {
                $sum += ($entry->price / 100);
            }

            return $sum;
        }, 0);
        if ($sumWithholdingTax == $sumNotWithholdingTax) {
            return true;
        }
        return false;
    }
    /**
     * @param $order_id
     * @return void
     */
    public function payplus_invoice_create_order($order_id, $typeInvoice = false)
    {
        $payload = array();
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $handle = 'payplus_process_invoice';

        $checkInvoiceSend = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_check_invoice_send', true);
        $payplusErrorInvoice = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_error_invoice', true);
        $payplusTransactionUid = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_transaction_uid', true);

        $invoice_manual = $this->payplus_get_create_invoice_manual();

        $order = wc_get_order($order_id);
        if ($payplusErrorInvoice !== "unique-identifier-exists") {
            if (!$checkInvoiceSend && $this->payplus_get_invoice_enable()) {

                $payplusType = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_type', true);
                $payplusUniqueIdentifier = "payplus_order_" . $order_id . $this->payplus_unique_identifier . $this->payplus_invoice_option['payplus_website_code'];

                if ($invoice_manual) {
                    $invoiceVerify = $this->post_payplus_ws($this->url_payplus_get_invoice . $payplusUniqueIdentifier, null, 'GET');
                    if (is_wp_error($invoiceVerify)) {
                        $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($invoiceVerify, true), 'error');
                    } else {
                        $res = json_decode(wp_remote_retrieve_body($invoiceVerify));
                        if ($res->status === "success") {
                            WC_PayPlus_Meta_Data::update_meta($order, array('payplus_check_invoice_send' => true));
                            $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($res, true), 'completed');
                            $insetData['payplus_invoice_type'] = $_POST['typeDocument'];
                            $insetData['payplus_invoice_docUID'] = $res->details->uuid;
                            $insetData['payplus_invoice_numberD'] = $res->details->number;
                            $insetData['payplus_invoice_originalDocAddress'] = $res->details->original_doc;
                            $insetData['payplus_invoice_copyDocAddress'] = $res->details->true_copy_doc;
                            $insetData['payplus_invoice_customer_uuid'] = $res->details->customer->uuid;
                            WC_PayPlus_Meta_Data::update_meta($order, $insetData);
                            if (!$this->invoice_notes_no) {
                                $order->add_order_note('<div style="font-weight:600">PayPlus Document</div>
                                <a class="link-invoice" target="_blank" href="' . $res->details->original_doc . '">' . __('Link Document  ', 'payplus-payment-gateway') . '</a>');
                            }
                            return;
                        }
                    }
                }

                $j5 = ($this->payplus_get_invoice_enable() && $payplusType === "Charge");

                if ($invoice_manual || $j5 || ($this->payplus_gateway_option['enabled'] === "no" || ($this->payplus_gateway_option['transaction_type'] !== "2"
                    && $payplusType !== "Check" && $payplusType !== "Approval"))) {
                    $payplus_document_type = ($typeInvoice) ? $typeInvoice : $this->payplus_invoice_option['payplus_invoice_type_document'];
                    $typePaymentMethod = $order->get_payment_method();
                    if ($this->payplus_get_create_invoice_automatic() && ($typePaymentMethod == "bacs" || $typePaymentMethod == "cod")) {
                        $payplus_document_type = 'inv_tax';
                    }
                    $dual = 1;
                    $resultApps = $this->payplus_get_payments($order_id);

                    if ($payplus_document_type === "inv_refund_receipt") {
                        $dual = -1;
                        $payplus_document_type = "inv_receipt";
                    }

                    $date = new DateTime();
                    $date = $date->format('m-d-Y H:i');
                    $order = wc_get_order($order_id);
                    $payload['customer'] = $this->payplus_get_client_by_order_id($order_id);
                    if (!empty($payplusTransactionUid)) {
                        $payload['transaction_uuid'] = $payplusTransactionUid;
                    }
                    if (!empty($this->payplus_invoice_brand_uid)) {
                        $payload['brand_uuid'] = $this->payplus_invoice_brand_uid;
                    }

                    $objectProducts = $this->payplus_get_products_by_order_id($order_id, $dual);

                    $totalCartAmount = round($objectProducts->amount, $WC_PayPlus_Gateway->rounding_decimals);
                    $payplusBalanceNames = $objectProducts->balanceNames;
                    $productsItems = $objectProducts->productsItems;
                    $payload['currency_code'] = $order->get_currency();
                    $payload['autocalculate_rate'] = true;
                    $payload['totalAmount'] = $dual * $totalCartAmount;
                    $payload['language'] = $this->payplus_invoice_option['payplus_langrage_invoice'];
                    $payload['more_info'] = $order_id;

                    $payload['unique_identifier'] = $payplusUniqueIdentifier;

                    $payload['send_document_email'] = $this->payplus_invoice_send_document_email;
                    $payload['send_document_sms'] = $this->payplus_invoice_send_document_sms;

                    if (!count($resultApps)) {
                        $method_payment = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_method', true) == "" ? 'other' : WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_method', true);
                        if ($method_payment == 'credit-card') {
                            $paymentArray['method_payment'] = 'credit-card';
                            $paymentArray['four_digits'] = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_four_digits', true);
                            $paymentArray['brand_name'] = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_brand_name', true);
                            $paymentArray['number_of_payments'] = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_number_of_payments', true);
                            $paymentArray['price'] = ($dual * $totalCartAmount) * 100;
                            $resultApps[] = (object) $paymentArray;
                        } else {
                            $otherMethod = strtolower($order->get_payment_method_title());
                            if (strpos($otherMethod, 'paypal') !== false) {
                                $method_payment = 'paypal';
                            }
                            $objectInvoicePaymentNoPayplus = array('method_payment' => $method_payment, 'price' => ($dual * $totalCartAmount) * 100);
                            $objectInvoicePaymentNoPayplus = (object) $objectInvoicePaymentNoPayplus;
                            $resultApps[] = $objectInvoicePaymentNoPayplus;
                        }
                    }

                    $sumPayment = floatval($this->payplus_sum_payment($resultApps));

                    $checkWithHoldingtTax = $this->payplus_check_sum_withholding_tax($resultApps);
                    if ($totalCartAmount == $sumPayment || $totalCartAmount == $order->get_total() || $checkWithHoldingtTax) {
                        $payload['items'] = $productsItems;
                        $payload['totalAmount'] = $dual * $totalCartAmount;
                    } else {

                        $payload['items'][] = [
                            'name' => __('General product', 'payplus-payment-gateway'),
                            'quantity' => 1,
                            'price' => $sumPayment,
                        ];
                        $payload['totalAmount'] = $dual * $sumPayment;
                    }

                    $payplusApprovalNum = WC_PayPlus_Meta_Data::get_meta($order_id, "payplus_approval_num", true);
                    $payplusApprovalNumPaypl = $order->get_transaction_id();
                    $payplusApprovalNum = ($payplusApprovalNum) ? $payplusApprovalNum : $payplusApprovalNumPaypl;
                    $payload = array_merge($payload, $this->payplus_get_payments_invoice($resultApps, $payplusApprovalNum, $dual, $order->get_total()));

                    if ($j5) {
                        $j5Amount = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_charged_j5_amount', true);
                        if ($j5Amount && ($j5Amount != $payload['totalAmount'])) {

                            $payload['items'] = [];
                            $payload['items'][] = [
                                'name' => __('General product', 'payplus-payment-gateway'),
                                'quantity' => 1,
                                'price' => $j5Amount,
                            ];
                            $payload['totalAmount'] = $dual * $j5Amount;
                            $payload['payments'][0]['amount'] = $dual * $j5Amount;
                        }
                    }

                    if ($WC_PayPlus_Gateway->balance_name && count($payplusBalanceNames)) {
                        if (count($payplusBalanceNames) == COUNT_BALANCE_NAME) {
                            $payload['customer']['balance_name'] = $payplusBalanceNames[COUNT_BALANCE_NAME - 1];
                        } else {
                            $order->add_order_note(__("We will not send a balance number to create an invoice because you have more than one product with a balance number", 'payplus-payment-gateway'));
                        }
                    }

                    $payload = json_encode($payload);
                    $WC_PayPlus_Gateway->payplus_add_log_all($handle, 'Fired  (' . $order_id . ')');
                    $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($payload, true), 'payload');

                    $response = $this->post_payplus_ws($this->url_payplus_create_invoice . $payplus_document_type, $payload);

                    if (is_wp_error($response)) {
                        $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($response, true), 'error');
                    } else {
                        $res = json_decode(wp_remote_retrieve_body($response));

                        if ($res->status === "success") {
                            WC_PayPlus_Meta_Data::update_meta($order, array('payplus_check_invoice_send' => true));
                            $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($res, true), 'completed');
                            $insetData['payplus_invoice_type'] = $payplus_document_type;
                            $insetData['payplus_invoice_docUID'] = $res->details->docUID;
                            $insetData['payplus_invoice_numberD'] = $res->details->number;
                            $insetData['payplus_invoice_numberD'] = $res->details->number;
                            $insetData['payplus_invoice_originalDocAddress'] = $res->details->originalDocAddress;
                            $insetData['payplus_invoice_copyDocAddress'] = $res->details->copyDocAddress;
                            $insetData['payplus_invoice_customer_uuid'] = $res->details->customer_uuid;
                            WC_PayPlus_Meta_Data::update_meta($order, $insetData);
                            if (!$this->invoice_notes_no) {
                                $order->add_order_note('<div style="font-weight:600">PayPlus Document</div>
                             <a class="link-invoice" target="_blank" href="' . $res->details->originalDocAddress . '">' . __('Link Document  ', 'payplus-payment-gateway') . '</a>');
                            }
                        } else {
                            WC_PayPlus_Meta_Data::update_meta($order, array('payplus_error_invoice' => $res->error));
                            $order->add_order_note('<div style="font-weight:600">PayPlus Error Invoice</div>' . $res->error);
                            $WC_PayPlus_Gateway->payplus_add_log_all($handle, print_r($res, true), 'error');
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $url
     * @param $payload
     * @return array|WP_Error
     */
    public function post_payplus_ws($url, $payload = [], $method = "post")
    {
        $args = array(
            'body' => $payload,
            'timeout' => '60',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => [],
            'headers' => array(
                'User-Agent' => 'WordPress ' . $_SERVER['HTTP_USER_AGENT'],
                'Content-Type' => 'application/json',
                'Authorization' => '{"api_key":"' . $this->payplus_invoice_api_key . '","secret_key":"' . $this->payplus_invoice_secret_key . '"}',
                'X-creationsource' => 'WordPress Source',
                'X-versionpayplus' => PAYPLUS_VERSION,
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

    public function payplus_get_payments_invoice($resultApps, $payplusApprovalNum, $dual = 1, $total = 0)
    {
        $payments = array();
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        if (count($resultApps)) {
            $sum = 0;
            for ($i = 0; $i < count($resultApps); $i++) {

                $resultApp = $resultApps[$i];
                $create_at = $resultApp->create_at;
                $paymentType = 'payment-app';
                $typePayment = array();
                if (in_array($resultApp->method_payment, array('credit-card', 'paypal', 'other', 'cash', 'payment-check', 'bank-transfer', 'withholding-tax'))) {
                    $paymentType = $resultApp->method_payment;
                }
                $arrTypeNotApp = array('credit-card', 'paypal', 'other', 'cash', 'payment-check', 'bank-transfer', 'withholding-tax');
                $typeAll = array('multipass', 'credit-card', 'google-pay', 'apple-pay', 'tav-zahav', 'valuecard', 'finitione');
                $typePayment['payment_type'] = $paymentType;
                $amount = round($dual * floatval($resultApp->price / 100), $WC_PayPlus_Gateway->rounding_decimals);
                $sum += $amount;
                $typePayment['amount'] = round($dual * floatval($resultApp->price / 100), $WC_PayPlus_Gateway->rounding_decimals);
                if (!in_array($resultApp->method_payment, $arrTypeNotApp)) {
                    $typePayment['payment_app'] = $resultApp->method_payment;
                }
                if (in_array($resultApp->method_payment, $typeAll)) {
                    if ($resultApp->method_payment == "credit-card") {
                        if ($resultApp->transaction_type == "payments" || $resultApp->transaction_type == "credit") {
                            $typePayment['payments'] = (intval($resultApp->number_of_payments) > 1) ? $resultApp->number_of_payments : 1;
                        }
                        $typePayment['transaction_type'] = $resultApp->transaction_type;
                        $typePayment['card_type'] = $resultApp->brand_name;
                    } elseif ($resultApp->method_payment == "tav-zahav" || $resultApp->method_payment == "multipass") {
                        $typePayment['transaction_number'] = $payplusApprovalNum;
                        $typePayment['transaction_id'] = $payplusApprovalNum;
                    }
                    $typePayment['four_digits'] = $resultApp->four_digits;
                } else {
                    $typePayment['transaction_number'] = $payplusApprovalNum;
                    $typePayment['transaction_id'] = $payplusApprovalNum;
                }
                if (!empty($resultApp->notes)) {
                    $typePayment['notes'] = $resultApp->notes;
                }
                if (!empty($resultApp->transaction_id)) {
                    $typePayment['transaction_id'] = $resultApp->transaction_id;
                    $typePayment['transaction_number'] = $resultApp->transaction_id;
                }
                if (!empty($resultApp->payer_account)) {
                    $typePayment['payer_account'] = $resultApp->payer_account;
                }

                if ($paymentType == 'payment-check' || $paymentType == "bank-transfer") {
                    $typePayment['account_number'] = $resultApp->account_number;
                    $typePayment['branch_number'] = $resultApp->branch_number;
                    $typePayment['bank_number'] = $resultApp->bank_number;
                    if ($paymentType == 'payment-check') {
                        $typePayment['check_number'] = $resultApp->check_number;
                    }
                }
                if (!empty($create_at)) {
                    $create_at = explode(' ', $create_at);
                    if (count($create_at)) {
                        $typePayment['payment_date'] = $create_at[0];
                    }
                }
                $payments['payments'][] = $typePayment;
                if ($sum == $total) {
                    break;
                }
            }
        }
        return $payments;
    }
}
