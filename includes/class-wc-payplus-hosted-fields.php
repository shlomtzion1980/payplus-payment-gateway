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
    public $isHostedStarted;
    public $isPlaceOrder;
    public $showSubmitButton;
    public $pwGiftCardData;


    /**
     *
     */
    public function __construct($order_id = "000", $order = null, $isPlaceOrder = false, $pwGiftCardData = false)
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
        $this->isPlaceOrder = $isPlaceOrder;
        $this->showSubmitButton = isset($this->payPlusGateway->hostedFieldsOptions['show_hide_submit_button']) && $this->payPlusGateway->hostedFieldsOptions['show_hide_submit_button'] === 'yes';
        if ($pwGiftCardData) {
            $this->pwGiftCardData = $pwGiftCardData;
        }

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
            WC()->session->set('hostedTimeStamp', false);
            WC()->session->set('hostedPayload', false);
            WC()->session->set('page_request_uid', false);
            WC()->session->set('hostedResponse', false);
            WC()->session->__unset('order_awaiting_payment');
            WC()->session->__unset('hostedFieldsUUID');
            WC()->session->set('hostedStarted', false);
            WC()->session->set('randomHash', bin2hex(random_bytes(16)));
            return;
        }

        WC()->session->set('hostedStarted', false);
        $this->checkHostedTime() ? $hostedResponse = $this->hostedFieldsData($this->order_id) : $hostedResponse = $this->emptyResponse();
        $hostedResponse = !empty($hostedResponse) ? $hostedResponse : $hostedResponse = $this->emptyResponse();
        $hostedResponseArray = json_decode($hostedResponse, true);
        $hostedResponseArray['results']['status'] === "error" ? $this->updateOrderId() : null;

        if (isset($hostedResponse) && $hostedResponse && json_decode($hostedResponse, true)['results']['status'] === "success") {
            $script_version = filemtime(plugin_dir_path(__DIR__) . 'assets/js/hostedFieldsScript.min.js');
            $template_path = plugin_dir_path(__DIR__) . 'templates/hostedFields.php';

            require_once PAYPLUS_PLUGIN_DIR . '/includes/class-wc-payplus-error-handler.php';
            $payPlusErrors = new WCPayPlusErrorCodes();

            if (file_exists($template_path)) {
                wp_enqueue_style('hosted-css', PAYPLUS_PLUGIN_URL . 'assets/css/hostedFields.css', [], $script_version);
                include $template_path;
            }
            wp_enqueue_script('payplus-hosted-fields-js', PAYPLUS_PLUGIN_URL . 'assets/js/payplus-hosted-fields/dist/payplus-hosted-fields.min.js', array('jquery'), '1.0', true);
            wp_register_script('payplus-hosted', PAYPLUS_PLUGIN_URL . 'assets/js/hostedFieldsScript.min.js', array('jquery'), $script_version, true);
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
                    'testMode' => $this->testMode,
                    "showSubmitButton" => $this->showSubmitButton,
                    'allErrors' => $payPlusErrors->getAllTranslations(),
                    'frontNonce' => wp_create_nonce('frontNonce'),
                    'payPlusLogo' => PAYPLUS_PLUGIN_URL . 'assets/images/PayPlusLogo.svg',
                ]
            );
            wp_enqueue_script('payplus-hosted');
        }
    }

    public function emptyResponse()
    {
        $this->order_id = WC()->session->get('order_awaiting_payment');
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

    public function updateOrderId($randomHash = null)
    {
        $randomHash = $randomHash ?? bin2hex(random_bytes(16));
        WC()->session->set('order_awaiting_payment', $randomHash);
        $order_id = $randomHash;
        return $order_id;
    }

    public function hostedFieldsData($order_id)
    {
        $order_id = !empty(WC()->session->get('order_awaiting_payment')) ? WC()->session->get('order_awaiting_payment') : $order_id;

        if ($order_id !== "000" && is_int($order_id)) {
            $order = wc_get_order($order_id);
            if (!$order && !empty(WC()->session->get('hostedPayload'))) {
                WC()->session->set('randomHash', $order_id = bin2hex(random_bytes(16)));
                $payload = json_decode(WC()->session->get('hostedPayload'), true);
                $payload['more_info'] = $order_id;
                WC()->session->set('order_awaiting_payment', $order_id);
            }
        }

        $this->payPlusGateway->payplus_add_log_all("hosted-fields-data", 'HostedFields-hostedFieldsData(1): (' . $order_id . ')');
        $discountPrice = 0;
        $products = array();
        $merchantCountryCode = substr(get_option('woocommerce_default_country'), 0, 2);
        WC()->customer->set_shipping_country($merchantCountryCode);
        WC()->cart->calculate_totals();
        $cart = WC()->cart->get_cart();

        $wc_tax_enabled = wc_tax_enabled();
        $isTaxIncluded = wc_prices_include_tax();

        if (isset($order) && $order) {
            $products = [];
            if (isset($this->pwGiftCardData) && $this->pwGiftCardData && is_array($this->pwGiftCardData['gift_cards'])) {
                foreach ($this->pwGiftCardData['gift_cards'] as $giftCardId => $giftCard) {
                    $priceGift = 0;
                    $productPrice = -1 * ($giftCard);
                    $priceGift += number_format($productPrice, 2, '.', '');

                    $giftCards = [
                        'title' => __('PW Gift Card', 'payplus-payment-gateway'),
                        'barcode' => $giftCardId,
                        'quantity' => 1,
                        'priceProductWithTax' => $priceGift,
                    ];

                    $products[] = $giftCards;
                }
            }
            $objectProducts = $this->payPlusGateway->payplus_get_products_by_order_id($order_id);
            foreach ($objectProducts->productsItems as $item) {
                $product = json_decode($item, true);
                $productId = isset($product['barcode']) ? $product['barcode'] : str_replace(' ', '', $product['name']);
                $product_name = $product['name'];
                $product_quantity = $product['quantity'];
                $product_total = $product['price'];
                $productVat = isset($product['vat_type']) ? $product['vat_type'] : 0;

                $products[] = array(
                    'title' => $product_name,
                    'priceProductWithTax' => number_format($product_total, 2, '.', ''),
                    'barcode' => $productId,
                    'quantity' => $product_quantity,
                    'vat_type' => $productVat,
                );
            }
        } elseif (count($cart)) {
            foreach ($cart as $cart_item_key => $cart_item) {
                $productId = $cart_item['product_id'];

                if (isset($cart_item['variation_id']) && !empty($cart_item['variation_id'])) {
                    $product = new WC_Product_Variable($productId);
                    $productData = $product->get_available_variation($cart_item['variation_id']);
                    $tax = (WC()->cart->get_total_tax()) ? WC()->cart->get_total_tax() / $cart_item['quantity'] : 0;
                    $tax = number_format($tax, 2, '.', '');
                    $priceProductWithTax = number_format($productData['display_price'] + $tax, 2, '.', '');
                    $priceProductWithoutTax = number_format($productData['display_price'], 2, '.', '');
                } else {
                    $product = new WC_Product($productId);
                    $priceProductWithTax = number_format(wc_get_price_including_tax($product), 2, '.', '');
                    $priceProductWithoutTax = number_format(wc_get_price_excluding_tax($product), 2, '.', '');
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
                $discountPrice = number_format(floatval(WC()->cart->get_discount_total()), 2, '.', '');
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
        $data->charge_method = intval($this->payPlusGateway->settings['transaction_type']);
        /**
         * Origin domain is the domain of the page that is requesting the payment page.
         * This is necessary for the hosted fields to be able to communicate with the client website.
         */
        $data->refURL_origin = ORIGIN_DOMAIN;
        /**
         * Also notice that we set hosted_fields to true.
         */
        $data->hosted_fields = true;

        if (is_int($order_id)) {
            $payPlusInvoice = new PayplusInvoice;
            $customer = $payPlusInvoice->payplus_get_client_by_order_id($order_id);
            $data->customer = new stdClass();
            $data->customer->customer_name = $customer['name'];
            $data->customer->email = $customer['email'];
            $data->customer->phone = $customer['phone'];
            $data->customer->address = $customer['street_name'];
            $data->customer->city = $customer['city'];
            $data->customer->postal_code = $customer['postal_code'];
            $data->customer->country_iso = $customer['country_iso'];
            $data->customer->customer_external_number = $order->get_customer_id();
            $payingVat = isset($this->payPlusGateway->settings['paying_vat']) && in_array($this->payPlusGateway->settings['paying_vat'], [0, 1, 2]) ? $this->payPlusGateway->settings['paying_vat'] : false;
            if ($payingVat) {
                $payingVat = $payingVat === "0" ? true : false;
                $payingVat = $payingVat === "1" ? false : true;
                $payingVat = $payingVat === "2" ? ($customer['country_iso'] !== trim(strtolower($this->payPlusGateway->settings['paying_vat_iso_code'])) ? false : true) : $payingVat;
                $data->paying_vat = $payingVat;
            }
        } else {
            $data->customer = new stdClass();
            $data->customer->customer_name = "$billing_first_name $billing_last_name";
            $data->customer->email = $billing_email;
            $data->customer->phone = $phone;
        }

        foreach ($products as $product) {
            $item = new stdClass();
            $item->name = $product['title'];
            $item->quantity = $product['quantity'];
            $item->barcode = $product['barcode'];
            $item->price = $product['priceProductWithTax'];
            isset($product['vat_type']) ? $item->vat_type = $product['vat_type'] : $item->vat_type = $payingVat;
            $data->items[] = $item;
        }

        $randomHash = WC()->session->get('randomHash') ? WC()->session->get('randomHash') : bin2hex(random_bytes(16));
        WC()->session->set('randomHash', $randomHash);

        $order_id = $order_id === "000" ? $this->updateOrderId($randomHash) : $order_id;
        $data->more_info = $order_id;

        if ($order_id !== "000" && isset($order) && $order) {
            WC()->session->set('order_awaiting_payment', $order_id);
        }

        $totalAmount = 0;
        foreach ($data->items as $item) {
            $totalAmount += $item->price * $item->quantity;
        }

        $hostedResponse = WC()->session->get('hostedPayload');
        $hostedResponseArray = !empty($hostedResponse) ? json_decode($hostedResponse, true) : '{}';

        $data->amount = number_format($totalAmount, 2, '.', '');
        $firstMessage = $order_id === "000" ? "-=#* 1st field generated *%=- - " : "";

        $payload = wp_json_encode($data, JSON_UNESCAPED_UNICODE);
        is_int($data->more_info) && $data->more_info === $order_id ? WC_PayPlus_Meta_Data::update_meta($order, ['payplus_hosted_page_request_uid' => $hostedResponseArray['payment_page_uid'], 'payplus_payload' => $payload]) : null;

        $this->payPlusGateway->payplus_add_log_all("hosted-fields-data", "HostedFields-hostedFieldsData-Class Payload: \n$payload");

        WC()->session->set('hostedPayload', $payload);

        $order = wc_get_order($order_id);
        $hostedFieldsUUID = WC()->session->get('hostedFieldsUUID');

        if ($order) {
            $this->isPlaceOrder ? $this->payplus_gateway->payplus_add_log_all("hosted-fields-data", "Updating Order #:$order_id") : null;
            $this->payPlusGateway->payplus_add_log_all("hosted-fields-data", "HostedFields-hostedFieldsData-after update Payload: \n$payload\nhostedFieldsUUID: $hostedFieldsUUID");
            $hostedResponse = WC_PayPlus_Statics::createUpdateHostedPaymentPageLink($payload, $this->isPlaceOrder);
        } else {
            $hostedResponse = WC_PayPlus_Statics::createUpdateHostedPaymentPageLink($payload, $this->isPlaceOrder = false);
        }


        $hostedResponseArray = json_decode($hostedResponse, true);

        if ($hostedResponseArray['results']['status'] === "error") {
            WC()->session->set('page_request_uid', false);
            $hostedResponse = WC_PayPlus_Statics::createUpdateHostedPaymentPageLink($payload, $this->isPlaceOrder);
        }

        return $hostedResponse;
    }

    public function checkHostedTime()
    {
        $savedTimestamp = WC()->session->get('hostedTimeStamp');
        if (!$savedTimestamp) {
            WC()->session->__unset('hostedPayload');
            WC()->session->set('page_request_uid', false);
            WC()->session->set('hostedResponse', false);
            $randomHash = bin2hex(random_bytes(16));
            WC()->session->set('order_awaiting_payment', $randomHash);
            WC()->session->__unset('hostedFieldsUUID');
            WC()->session->set('hostedStarted', false);
            WC()->session->set('randomHash', $randomHash);
            // First run or if no timestamp is saved, save the current time
            $savedTimestamp = time(); // Store this in the database or file
            WC()->session->set('hostedTimeStamp', $savedTimestamp);
            $this->payplus_gateway->payplus_add_log_all("hosted-fields-data", "HostedFields timestamp started: $savedTimestamp");
        }

        $currentTimestamp = time();

        $timeLimit = 30 * 60; // 30 minutes

        if (($currentTimestamp - $savedTimestamp) <= $timeLimit) {
            return true;
        } else {
            $this->payplus_gateway->payplus_add_log_all("hosted-fields-data", "HostedFields timestamp ended: $currentTimestamp");
            WC()->session->set('hostedTimeStamp', false);
            WC()->session->__unset('hostedPayload');
            WC()->session->set('page_request_uid', false);
            WC()->session->set('hostedResponse', false);
            $randomHash = bin2hex(random_bytes(16));
            WC()->session->set('order_awaiting_payment', $randomHash);
            WC()->session->__unset('hostedFieldsUUID');
            WC()->session->set('hostedStarted', false);
            WC()->session->set('randomHash', $randomHash);
            return false;
        }
    }
}
