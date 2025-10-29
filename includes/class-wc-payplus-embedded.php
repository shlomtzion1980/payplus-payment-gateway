<?php
defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * PayPlus Embedded Payment Processing Class
 * 
 * Handles embedded payment processing and order data collection
 * for PayPlus payment gateways during the checkout process.
 */
class WC_PayPlus_Embedded
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
     * Constructor
     */
    public function __construct()
    {
        $payplus_payment_gateway_settings = get_option('woocommerce_payplus-payment-gateway_settings');

        $this->testMode = boolval($payplus_payment_gateway_settings['api_test_mode'] === 'yes');
        $this->apiKey = $this->testMode ? $payplus_payment_gateway_settings['dev_api_key'] : $payplus_payment_gateway_settings['api_key'];
        $this->secretKey = $this->testMode ? $payplus_payment_gateway_settings['dev_secret_key'] : $payplus_payment_gateway_settings['secret_key'];
        $this->paymentPageUid = $this->testMode ? $payplus_payment_gateway_settings['dev_payment_page_id'] : $payplus_payment_gateway_settings['payment_page_id'];
        // // Hook into the checkout order processed event
        define('API_KEY', $this->apiKey);
        define('SECRET_KEY', $this->secretKey);
        define('PAYMENT_PAGE_UID', $this->paymentPageUid);
        define('ORIGIN_DOMAIN', site_url());
        define('SUCCESS_URL', site_url() . '?wc-api=payplus_gateway&hostedFields=true');
        define('FAILURE_URL', site_url() . "/error-payment-payplus/");
        define('CANCEL_URL', site_url() . "/cancel-payment-payplus/");
        add_action('woocommerce_checkout_order_processed', [$this, 'payplus_embedded_order_processed'], 25, 3);
        add_filter('pwgc_redeeming_session_data', [$this, 'modify_gift_card_session_data'], 10, 2);
    }

    /**
     * Get the PayPlus gateway instance (lazy loading)
     */
    private function get_payplus_gateway()
    {
        if (!$this->payPlusGateway) {
            $this->payPlusGateway = WC_PayPlus::get_instance()->get_main_payplus_gateway();
        }
        return $this->payPlusGateway;
    }

    public function modify_gift_card_session_data($session_data, $gift_card_number)
    {
        // Modify session data if necessary
        $this->pwGiftCardData = $session_data;
        return $session_data;
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
    public function payplus_embedded_order_processed($order_id, $posted_data, $order)
    {
        // Check if this is a PayPlus payment method (any method starting with 'payplus-payment-gateway')
        if (strpos($order->get_payment_method(), 'payplus-payment-gateway-hostedfields') !== 0) {
            return; // Only process for PayPlus payments
        }
        WC()->session->set('order_awaiting_payment', $order_id);
        $this->hostedFieldsData($order_id);
    }

    public function hostedFieldsData($order_id)
    {
        if ($order_id !== "000" && is_int($order_id)) {
            $order = wc_get_order($order_id);
        }

        $this->get_payplus_gateway()->payplus_add_log_all("hosted-fields-data", 'PayPlus Embedded Class update for order #: (' . $order_id . ')');

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
            $objectProducts = $this->get_payplus_gateway()->payplus_get_products_by_order_id($order_id);
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
        $data->charge_method = intval($this->get_payplus_gateway()->settings['transaction_type']);
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
            $gateway = $this->get_payplus_gateway();
            $payingVat = isset($gateway->settings['paying_vat']) && in_array($gateway->settings['paying_vat'], [0, 1, 2]) ? $gateway->settings['paying_vat'] : false;
            if ($payingVat) {
                $payingVat = $payingVat === "0" ? true : false;
                $payingVat = $payingVat === "1" ? false : true;
                $payingVat = $payingVat === "2" ? ($customer['country_iso'] !== trim(strtolower($gateway->settings['paying_vat_iso_code'])) ? false : true) : $payingVat;
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
            isset($product['vat_type']) ? $item->vat_type = $product['vat_type'] : $payingVat;
            $data->items[] = $item;
        }

        $data->more_info = $order_id;

        $totalAmount = 0;
        foreach ($data->items as $item) {
            $totalAmount += $item->price * $item->quantity;
        }

        $data->amount = number_format($totalAmount, 2, '.', '');

        $payload = wp_json_encode($data, JSON_UNESCAPED_UNICODE);
        $hostedResponse = WC_PayPlus_Statics::createUpdateHostedPaymentPageLink($payload, true);
        WC_PayPlus_Meta_Data::update_meta($order, ['payplus_embedded_payload' => $payload]);
        WC_PayPlus_Meta_Data::update_meta($order, ['payplus_embedded_update_page_response' => $hostedResponse]);
    }
}
