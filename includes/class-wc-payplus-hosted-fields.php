<?php
defined('ABSPATH') or die('Hey, You can\'t access this file!'); // Exit if accessed directly

class WC_PayPlus_HostedFields extends WC_PayPlus
{
    private $order_id;
    private $order;
    private $initiated = false;
    protected static $instance = null;
    public $options;
    public $testMode;
    public $url;
    public $apiKey;
    public $secretKey;
    public $paymentPageUid;
    public $apiUrl;
    public $vat4All;
    public $payPlusGateway;
    public $isHideLoaderLogo;
    // public $hostedFieldsResponse;


    /**
     *
     */
    public function __construct($order_id = "000", $order = null)
    {
        $this->payPlusGateway = $this->get_main_payplus_gateway();
        $this->isHideLoaderLogo = boolval(isset($this->payPlusGateway->hostedFieldsOptions['hide_loader_logo']) && $this->payPlusGateway->hostedFieldsOptions['hide_loader_logo'] === 'yes');
        $this->vat4All = isset($this->payPlusGateway->settings['paying_vat_all_order']) ? boolval($this->payPlusGateway->settings['paying_vat_all_order'] === "yes") : false;
        $this->testMode = boolval($this->payPlusGateway->settings['api_test_mode'] === 'yes');
        $this->url = $this->testMode ? PAYPLUS_PAYMENT_URL_DEV . 'Transactions/updateMoreInfos' : PAYPLUS_PAYMENT_URL_PRODUCTION . 'Transactions/updateMoreInfos';
        $this->apiKey = $this->testMode ? $this->payPlusGateway->settings['dev_api_key'] : $this->payPlusGateway->settings['api_key'];
        $this->secretKey = $this->testMode ? $this->payPlusGateway->settings['dev_secret_key'] : $this->payPlusGateway->settings['secret_key'];
        $this->paymentPageUid = $this->testMode ? $this->payPlusGateway->settings['dev_payment_page_id'] : $this->payPlusGateway->settings['payment_page_id'];
        $this->order_id = $order_id;
        $this->order = $order;



        define('API_KEY', $this->apiKey);
        define('SECRET_KEY', $this->secretKey);
        define('PAYMENT_PAGE_UID', $this->paymentPageUid);
        define('ORIGIN_DOMAIN', site_url());
        define('SUCCESS_URL', site_url() . '?wc-api=payplus_gateway&hostedFields=true');
        define('FAILURE_URL', site_url() . "/error-payment-payplus/");
        define('CANCEL_URL', site_url() . "/cancel-payment-payplus/");

        /**
         * PAYPLUS_API_URL_DEV is the URL of the API in the development environment.
         */
        define('PAYPLUS_API_URL_DEV', 'https://restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink');

        /**
         * PAYPLUS_API_URL_PROD is the URL of the API in the production environment.
         */
        define('PAYPLUS_API_URL_PROD', 'https://restapi.payplus.co.il/api/v1.0/PaymentPages/generateLink');

        $this->apiUrl = $this->testMode ? PAYPLUS_API_URL_DEV : PAYPLUS_API_URL_PROD;

        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        if (WC()->cart->get_subtotal() <= 0 || empty($available_gateways)) {
            WC()->session->__unset('hostedPayload');
            WC()->session->__unset('page_request_uid');
            WC()->session->set('hostedResponse', false);
            WC()->session->__unset('order_awaiting_payment');
            WC()->session->__unset('hostedFieldsUUID');
            WC()->session->set('hostedStarted', false);
            WC()->session->set('randomHash', bin2hex(random_bytes(16)));
            return;
        }
        $this->emptyResponse();
        $this->checkHostedTime() ? $hostedResponse = $this->hostedFieldsData($this->order_id) : $hostedResponse = $this->emptyResponse();
        $hostedResponse = !empty($hostedResponse) ? $hostedResponse : $this->emptyResponse();

        if (isset($hostedResponse) && $hostedResponse && json_decode($hostedResponse, true)['results']['status'] === "success") {
            $script_version = filemtime(plugin_dir_path(__DIR__) . 'assets/js/hostedFieldsScript.js');
            $template_path = plugin_dir_path(__DIR__) . 'templates/hostedFields.php';

            if (file_exists($template_path)) {
                wp_enqueue_style('hosted-css', PAYPLUS_PLUGIN_URL . 'assets/css/hostedFields.css', [], $script_version);
                include $template_path;
            }
            wp_enqueue_script('payplus-hosted-fields-js', PAYPLUS_PLUGIN_URL . 'assets/js/payplus-hosted-fields/dist/payplus-hosted-fields.min.js', array('jquery'), '1.0', true);
            wp_register_script('payplus-hosted', PAYPLUS_PLUGIN_URL . 'assets/js/hostedFieldsScript.js', array('jquery'), '1.0', true);
            wp_localize_script(
                'payplus-hosted',
                'payplus_script_hosted',
                [
                    "hostedResponse" => $hostedResponse,
                    "isHideLoaderLogo" => $this->isHideLoaderLogo,
                    "isLoggedIn" => boolval(get_current_user_id() > 0),
                    "isSavingCerditCards" => boolval(isset($this->payPlusGateway->settings['create_pp_token']) && $this->payPlusGateway->settings['create_pp_token'] === 'yes'),
                    'ajax_url' => admin_url('admin-ajax.php'),
                    "saveCreditCard" => __("Save credit card in my account", "payplus-payment-gateway"),
                    'dddd' => 'ddd',
                    'frontNonce' => wp_create_nonce('frontNonce'),
                    'payPlusLogo' => PAYPLUS_PLUGIN_URL . 'assets/images/PayPlusLogo.svg',
                ]
            );
            wp_enqueue_script('payplus-hosted');
        }
    }

    public function emptyResponse()
    {
        WC()->session->set('randomHash', $this->order_id = bin2hex(random_bytes(16)));
        WC()->session->__unset('order_awaiting_payment');
        return $this->hostedFieldsData($this->order_id);
    }

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

    public function isInitiated()
    {
        if (!$this->initiated) {
            $this->initiated = true;
            parent::__construct();
        }
    }

    function createUpdateHostedPaymentPageLink($payload)
    {
        $testMode = boolval($this->payPlusGateway->settings['api_test_mode'] === 'yes');
        $apiUrl = $testMode ? 'https://restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink' : 'https://restapi.payplus.co.il/api/v1.0/PaymentPages/generateLink';
        $apiKey = $testMode ? $this->payPlusGateway->settings['dev_api_key'] : $this->payPlusGateway->settings['api_key'];
        $secretKey = $testMode ? $this->payPlusGateway->settings['dev_secret_key'] : $this->payPlusGateway->settings['secret_key'];


        $auth = wp_json_encode([
            'api_key' => $apiKey,
            'secret_key' => $secretKey
        ]);
        $requestHeaders = [];
        $requestHeaders[] = 'Content-Type:application/json';
        $requestHeaders[] = 'Authorization: ' . $auth;


        $pageRequestUid = WC()->session->get('page_request_uid');
        $hostedFieldsUUID = WC()->session->get('hostedFieldsUUID');

        if ($pageRequestUid && $hostedFieldsUUID) {
            $apiUrl = str_replace("/generateLink", "/Update/$pageRequestUid", $apiUrl);
        }

        $hostedResponse = $this->payPlusGateway->post_payplus_ws($apiUrl, $payload, "post");
        // $this->payplus_gateway->payplus_add_log_all('payplus-hostedfields-create-update', "HostedFields payload: $payload");

        $hostedResponseArray = json_decode(wp_remote_retrieve_body($hostedResponse), true);

        if (isset($hostedResponseArray['data']['page_request_uid'])) {
            $pageRequestUid = $hostedResponseArray['data']['page_request_uid'];
            WC()->session->set('page_request_uid', $pageRequestUid);
        }

        $body = wp_remote_retrieve_body($hostedResponse);
        $bodyArray = json_decode($body, true);

        if (isset($bodyArray['data']['hosted_fields_uuid']) && $bodyArray['data']['hosted_fields_uuid'] !== null) {
            $hostedFieldsUUID = $bodyArray['data']['hosted_fields_uuid'];
            WC()->session->set('hostedFieldsUUID', $hostedFieldsUUID);
        } else {

            $bodyArray['data']['hosted_fields_uuid'] = $hostedFieldsUUID;
        }
        $hostedResponse = wp_json_encode($bodyArray);
        WC()->session->set('hostedResponse', $hostedResponse);

        return $hostedResponse;
    }

    public function hostedFieldsData($order_id)
    {

        $order_id = !empty(WC()->session->get('order_awaiting_payment')) ? WC()->session->get('order_awaiting_payment') : $order_id;

        if ($order_id !== "000" && !is_string($order_id)) {
            WC()->session->set('randomHash', bin2hex(random_bytes(16)));
            $order = wc_get_order($order_id);

            if (! $order) {
                return;
            }
        } else {
            $payload = json_decode(WC()->session->get('hostedPayload'), true);
            $payload['more_info'] = WC()->session->get('randomHash');
            $hostedResponse = $this->createUpdateHostedPaymentPageLink(wp_json_encode($payload));
            return $hostedResponse;
        }

        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();
        $WC_PayPlus_Gateway->payplus_add_log_all("hosted-fields-data", 'HostedFields-hostedFieldsData(1): (' . $order_id . ')');
        $discountPrice = 0;
        $products = array();
        $merchantCountryCode = substr(get_option('woocommerce_default_country'), 0, 2);
        WC()->customer->set_shipping_country($merchantCountryCode);
        WC()->cart->calculate_totals();
        $cart = WC()->cart->get_cart();

        $wc_tax_enabled = wc_tax_enabled();
        $isTaxIncluded = wc_prices_include_tax();

        if (count($cart)) {
            foreach ($cart as $cart_item_key => $cart_item) {
                $productId = $cart_item['product_id'];

                if (isset($cart_item['variation_id']) && !empty($cart_item['variation_id'])) {
                    $product = new WC_Product_Variable($productId);
                    $productData = $product->get_available_variation($cart_item['variation_id']);
                    $tax = (WC()->cart->get_total_tax()) ? WC()->cart->get_total_tax() / $cart_item['quantity'] : 0;
                    $tax = round($tax, $WC_PayPlus_Gateway->rounding_decimals);
                    $priceProductWithTax = round($productData['display_price'] + $tax, ROUNDING_DECIMALS);
                    $priceProductWithoutTax = round($productData['display_price'], ROUNDING_DECIMALS);
                } else {
                    $product = new WC_Product($productId);
                    $priceProductWithTax = round(wc_get_price_including_tax($product), ROUNDING_DECIMALS);
                    $priceProductWithoutTax = round(wc_get_price_excluding_tax($product), ROUNDING_DECIMALS);
                }


                $productVat = 0;

                if ($wc_tax_enabled) {
                    $productVat = $isTaxIncluded && $product->get_tax_status() === 'taxable' ? 0 : 1;
                    $productVat = $product->get_tax_status() === 'none' ? 2 : $productVat;
                    $productVat = $this->vat4All ? 0 : $productVat;
                }

                $products[] = array(
                    'title' => $product->get_name(),
                    'priceProductWithTax' => $priceProductWithTax,
                    'priceProductWithoutTax' => $priceProductWithoutTax,
                    'barcode' => ($product->get_sku()) ? (string) $product->get_sku() : (string) $productId,
                    'quantity' => $cart_item['quantity'],
                    'vat_type' => $productVat,
                    'org_product_tax' => $product->get_tax_status(),
                );
            }

            if (WC()->cart->get_total_discount()) {
                $discountPrice = round(floatval(WC()->cart->get_discount_total()), ROUNDING_DECIMALS);
            }
        }

        // this will be the create initial order data function that calls the curl to create at it's end.
        $checkout = WC()->checkout();

        // Get posted checkout data
        $billing_first_name = !empty($checkout->get_value('billing_first_name')) ? $checkout->get_value('billing_first_name') : "general-first-name";
        $billing_last_name  = !empty($checkout->get_value('billing_last_name')) ? $checkout->get_value('billing_last_name') : "general-last-name";
        $billing_email      = !empty($checkout->get_value('billing_email')) ? $checkout->get_value('billing_email') : "general@payplus.co.il";
        $shipping_address   = !empty($checkout->get_value('shipping_address_1')) ? $checkout->get_value('shipping_address_1') : "general-shipping-address";
        $phone              = !empty($checkout->get_value('billing_phone')) ? $checkout->get_value('billing_phone') : "050-0000000";

        // Building sample request to create a payment page
        $data = new stdClass();
        $data->payment_page_uid = PAYMENT_PAGE_UID;
        $data->refURL_success = SUCCESS_URL;
        $_wpnonce = wp_create_nonce('PayPlusGateWayNonce');
        $data->refURL_callback = get_site_url(null, '/?wc-api=callback_response&_wpnonce=' . $_wpnonce);
        $data->refURL_failure = FAILURE_URL;
        $data->refURL_cancel = CANCEL_URL;
        $data->create_token = true;
        $data->currency_code = get_woocommerce_currency();
        $data->charge_method = intval($WC_PayPlus_Gateway->settings['transaction_type']);

        /**
         * Origin domain is the domain of the page that is requesting the payment page.
         * This is necessary for the hosted fields to be able to communicate with the client website.
         */
        $data->refURL_origin = ORIGIN_DOMAIN;
        /**
         * Also notice that we set hosted_fields to true.
         */
        $data->hosted_fields = true;

        $data->customer = new stdClass();
        $data->customer->customer_name = "$billing_first_name $billing_last_name";
        $data->customer->email = $billing_email;
        $data->customer->phone = $phone;

        foreach ($products as $product) {
            $item = new stdClass();
            $item->name = $product['title'];
            $item->quantity = $product['quantity'];
            $item->barcode = $product['barcode'];
            $item->price = $product['priceProductWithTax'];
            $item->vat_type = $product['vat_type'];
            $data->items[] = $item;
        }

        $randomHash = WC()->session->get('randomHash') ? WC()->session->get('randomHash') : bin2hex(random_bytes(16));
        WC()->session->set('randomHash', $randomHash);
        $data->more_info = $order_id === "000" ? $randomHash : $order_id;

        if ($order_id !== "000") {
            WC()->session->set('order_awaiting_payment', $order_id);
            $shipping_items = $order->get_items('shipping');
            // Check if there are shipping items
            if (! empty($shipping_items)) {
                foreach ($shipping_items as $shipping_item) {
                    // Get the shipping method ID (e.g., 'flat_rate:1')
                    $method_id = $shipping_item->get_method_id();

                    // Get the shipping method title (e.g., 'Flat Rate')
                    $method_title = $shipping_item->get_method_title();
                    $shipping_cost = $shipping_item->get_total();
                    $shipping_taxes = $shipping_item->get_taxes();

                    $shipping_tax_total = $wc_tax_enabled ? array_sum($shipping_taxes['total']) : 0;

                    $item = new stdClass();
                    $item->name = $method_title;
                    $item->quantity = 1;
                    $item->price = $shipping_cost + array_sum($shipping_taxes['total']);
                    $item->vat_type = $shipping_tax_total > 0 ? 1 : 0;
                    $data->items[] = $item;
                }
            }

            $coupons = $order->get_coupon_codes();
            $totalFromOrder = $order->get_total();

            if (! empty($coupons)) {
                foreach ($coupons as $coupon_code) {
                    // Get the WC_Coupon object
                    $coupon = new WC_Coupon($coupon_code);

                    // Get the coupon discount amount
                    $coupon_value = $coupon->get_amount();
                }
                if ($coupon_value > 0) {
                    $item = new stdClass();
                    $item->name = "coupon_discount";
                    $item->quantity = 1;
                    $item->price = -$coupon_value;
                    $item->vat_type = !$wc_tax_enabled ? 0 : 1;
                    $data->items[] = $item;
                }
            }
        }

        $totalAmount = 0;
        foreach ($data->items as $item) {
            $totalAmount += $item->price * $item->quantity;
        }

        $hostedResponse = WC()->session->get('hostedPayload');
        $hostedResponseArray = json_decode($hostedResponse, true);

        $data->amount = number_format($totalAmount, 2, '.', '');
        $firstMessage = $order_id === "000" ? "-=#* 1st field generated *%=- - " : "";
        $payload = wp_json_encode($data);
        is_int($data->more_info) && $data->more_info === $order_id ? WC_PayPlus_Meta_Data::update_meta($order, ['payplus_hosted_page_request_uid' => $hostedResponseArray['payment_page_uid'], 'payplus_payload' => $payload]) : null;
        if ($hostedResponse === $payload) {
            $WC_PayPlus_Gateway->payplus_add_log_all("hosted-fields-data", "HostedFields-hostedFieldsData(2): ($order_id)\nPayload is identical no need to run.");
            return WC()->session->get('hostedResponse');
        } else {
            $WC_PayPlus_Gateway->payplus_add_log_all("hosted-fields-data", $firstMessage  . "HostedFields-hostedFieldsData(2)\n");
        }

        $WC_PayPlus_Gateway->payplus_add_log_all("hosted-fields-data", "HostedFields-hostedFieldsData(3) Payload: \n$payload");

        WC()->session->set('hostedPayload', $payload);

        $hostedResponse = $this->createUpdateHostedPaymentPageLink($payload);

        $hostedResponseArray = json_decode($hostedResponse, true);

        if ($hostedResponseArray['results']['status'] === "error") {
            WC()->session->__unset('page_request_uid');
            $hostedResponse = $this->createUpdateHostedPaymentPageLink($payload);
        }

        return $hostedResponse;
    }

    public function checkHostedTime()
    {
        $savedTimestamp = WC()->session->get('hostedTimeStamp');
        if (!$savedTimestamp) {
            // First run or if no timestamp is saved, save the current time
            $savedTimestamp = time(); // Store this in the database or file
            WC()->session->set('hostedTimeStamp', $savedTimestamp);
        }

        $currentTimestamp = time();

        $timeLimit = 30 * 60; // 30 minutes

        if (($currentTimestamp - $savedTimestamp) <= $timeLimit) {
            return true;
        } else {
            WC()->session->set('hostedTimeStamp', false);
            WC()->session->__unset('order_awaiting_payment');
            WC()->session->set('randomHash', bin2hex(random_bytes(16)));
            return false;
        }
    }
}
