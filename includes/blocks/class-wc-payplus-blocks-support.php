<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

class WC_Gateway_Payplus_Payment_Block extends AbstractPaymentMethodType
{

    /**
     * The gateway instance.
     *
     * @var WC_PayPlus_Gateway
     */
    protected $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'payplus-payment-gateway';

    public $orderId;
    public $displayMode;
    private $secretKey;
    public $iFrameHeight;
    public $hideOtherPayments;
    public $payPlusSettings;
    public $customIcons;
    public $importApplePayScript;
    public $applePaySettings;
    public $isSubscriptionOrder;
    public $isAutoPPCC;
    /**
     * The main PayPlus gateway instance. Use get_main_payplus_gateway() to access it.
     *
     * @var null|WC_PayPlus_Gateway
     */
    protected $payplus_gateway = null;


    /**
     * Constructor
     *
     */
    public function __construct($payment_request_configuration = null)
    {
        add_action('woocommerce_rest_checkout_process_payment_with_context', [$this, 'add_payment_request_order_meta'], 8, 2);
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);
        $this->payPlusSettings = get_option("woocommerce_payplus-payment-gateway_settings");
        $this->displayMode = $this->settings['display_mode'] ?? null;
        $this->iFrameHeight = $this->settings['iframe_height'] ?? null;
        $this->hideOtherPayments = boolval(isset($this->settings['hide_other_charge_methods']) && $this->settings['hide_other_charge_methods']) ?? null;
        $this->applePaySettings = get_option('woocommerce_payplus-payment-gateway-applepay_settings');
        $this->importApplePayScript = boolval(boolval(isset($this->payPlusSettings['enable_apple_pay']) && $this->payPlusSettings['enable_apple_pay'] === 'yes') || boolval(isset($this->applePaySettings['enabled']) && $this->applePaySettings['enabled'] === "yes"));
        $this->isAutoPPCC = boolval(isset($this->settings['auto_load_payplus_cc_method']) && $this->settings['auto_load_payplus_cc_method'] === 'yes');


        if (isset($this->settings['custom_icons']) && strlen($this->settings['custom_icons']) > 0) {
            $this->customIcons = explode(";", $this->settings['custom_icons']);
        } else {
            $this->customIcons = [];
        }


        $this->secretKey = $this->settings['secret_key'] ?? null;
        $gateways = WC()->payment_gateways->payment_gateways();

        $this->settings['gateways'] = [];
        foreach (array_keys($gateways) as $payPlusGateWay) {
            $this->settings['gateways'][] = strpos($payPlusGateWay, 'payplus-payment-gateway') === 0 ? $payPlusGateWay : null;
        }
        // $this->settings['gateways'] = array_filter($this->settings['gateways'], function ($item) {
        //     return $item !== "payplus-payment-gateway-hostedfields";
        // });
        $this->settings['gateways'] = array_values(array_filter($this->settings['gateways']));
        $this->gateway = $gateways[$this->name];
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

    function createUpdateHostedPaymentPageLink($payload)
    {
        $options = get_option('woocommerce_payplus-payment-gateway_settings');
        $testMode = boolval($options['api_test_mode'] === 'yes');
        $apiUrl = $testMode ? 'https://restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink' : 'https://restapi.payplus.co.il/api/v1.0/PaymentPages/generateLink';
        $apiKey = $testMode ? $options['dev_api_key'] : $options['api_key'];
        $secretKey = $testMode ? $options['dev_secret_key'] : $options['secret_key'];
        $payPlusGateWay = $this->get_main_payplus_gateway();

        $auth = wp_json_encode([
            'api_key' => $apiKey,
            'secret_key' => $secretKey
        ]);
        $requestHeaders = [];
        $requestHeaders[] = 'Content-Type:application/json';
        $requestHeaders[] = 'Authorization: ' . $auth;


        $pageRequestUid = WC()->session->get('page_request_uid');

        if ($pageRequestUid) {
            $apiUrl = str_replace("/generateLink", "/Update/$pageRequestUid", $apiUrl);
        }

        $hostedResponse = $payPlusGateWay->post_payplus_ws($apiUrl, $payload, "post");

        $hostedResponseArray = json_decode(wp_remote_retrieve_body($hostedResponse), true);

        if (isset($hostedResponseArray['data']['page_request_uid'])) {
            $pageRequestUid = $hostedResponseArray['data']['page_request_uid'];
        }

        return wp_remote_retrieve_body($hostedResponse);
    }


    public function hostedFieldsData($order_id)
    {
        $options = get_option('woocommerce_payplus-payment-gateway_settings');
        $testMode = boolval($options['api_test_mode'] === 'yes');
        $apiUrl = $testMode ? 'https://restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink' : 'https://restapi.payplus.co.il/api/v1.0/PaymentPages/generateLink';
        $apiKey = $testMode ? $options['dev_api_key'] : $options['api_key'];
        $secretKey = $testMode ? $options['dev_secret_key'] : $options['secret_key'];
        $paymentPageUid = $testMode ? $options['dev_payment_page_id'] : $options['payment_page_id'];

        if ($order_id !== "000") {
            $order = wc_get_order($order_id);

            if (! $order) {
                return;
            }
        }

        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();
        $WC_PayPlus_Gateway->payplus_add_log_all("hosted-fields-data", "HostedFields: ($order_id)\nblocks!!!");
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

                if (!empty($cart_item['variation_id'])) {
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
                }

                $products[] = array(
                    'title' => $product->get_title(),
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
        $data->payment_page_uid = $paymentPageUid;
        $data->refURL_success = site_url() . '?wc-api=payplus_gateway&hostedFields=true';
        $data->refURL_failure = site_url() . "/error-payment-payplus/";
        $data->refURL_cancel = 'https://www.example.com/cancel';
        $data->create_token = true;
        $data->currency_code = get_woocommerce_currency();
        $data->charge_method = intval($WC_PayPlus_Gateway->settings['transaction_type']);

        /**
         * Origin domain is the domain of the page that is requesting the payment page.
         * This is necessary for the hosted fields to be able to communicate with the client website.
         */
        $data->refURL_origin = site_url();
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

        if ($order_id !== "000") {
            $data->more_info = $order_id;
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

                    $item = new stdClass();
                    $item->name = $method_title;
                    $item->quantity = 1;
                    $item->price = $shipping_cost;
                    $item->vat_type = !$wc_tax_enabled ? 0 : 1;
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

        $data->amount = number_format($totalAmount, 2, '.', '');

        $payload = wp_json_encode($data);

        $hostedResponse = $this->createUpdateHostedPaymentPageLink($payload);

        return $hostedResponse;
    }
    /**
     * Add payment request data to the order meta as hooked on the
     * woocommerce_rest_checkout_process_payment_with_context action.
     *
     * @param PaymentContext $context Holds context for the payment.
     * @param PaymentResult  $result  Result object for the payment.
     */
    public function add_payment_request_order_meta(PaymentContext $context, PaymentResult &$result)
    {
        $data = $context->payment_data;
        $is_payplus_payment_method = $this->name === $context->payment_method;
        $main_gateway              = new WC_PayPlus_Gateway;



        $this->isSubscriptionOrder = false;
        if (is_checkout()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (get_class($cart_item['data']) === "WC_Product_Subscription" || get_class($cart_item['data']) === "WC_Product_Subscription_Variation") {
                    $this->isSubscriptionOrder = true;
                    break;
                }
            }
        }


        $token_id = $context->payment_data['token'];
        $token = WC_Payment_Tokens::get($token_id);

        // Hook into PayPlus error processing so that we can capture the error to payment details.
        // This error would have been registered via wc_add_notice() and thus is not helpful for block checkout processing.
        add_action(
            'wc_gateway_payplus_process_payment_error',
            function ($error) use (&$result) {
                $payment_details = $result->payment_details;
                $payment_details['errorMessage'] = wp_strip_all_tags($error);
                $result->set_payment_details($payment_details);
            }
        );

        if ($context->payment_method === "payplus-payment-gateway-hostedfields") {
            $this->orderId = $context->order->id;
            $order = wc_get_order($this->orderId);

            $hostedFieldsClass = new WC_PayPlus_Gateway_HostedFields;

            // $this->hostedFieldsData($this->orderId);

            $result->set_payment_details('');
            $payment_details = $result->payment_details;
            $payment_details['order_id'] = $this->orderId;
            $payment_details['secret_key'] = $this->secretKey;
            $result->set_payment_details($payment_details);
            $result->set_status('pending');
        } else {
            if (!in_array($context->payment_method, $this->settings['gateways'])) {
                return;
            }

            $gatewaySettings = get_option("woocommerce_{$context->payment_method}_settings");

            if ($token) {
                return;
            }

            if (in_array($gatewaySettings['display_mode'], ['iframe', 'redirect'])) {
                return;
            }

            if (isset($gatewaySettings['sub_hide_other_charge_methods'])) {
                $hideOtherPayments = $gatewaySettings['sub_hide_other_charge_methods'] == 2 ? $this->payPlusSettings['hide_other_charge_methods'] : $gatewaySettings['sub_hide_other_charge_methods'];
                $hideOtherPayments = $hideOtherPayments == 1 ? 'true' : 'false';
            } else {
                $hideOtherPayments = boolval($this->hideOtherPayments) ? 'true' : 'false';
            }

            $names = [
                "payplus-payment-gateway" => 'credit-card',
                "payplus-payment-gateway-bit" => 'bit',
                "payplus-payment-gateway-applepay" => 'apple-pay',
                "payplus-payment-gateway-googlepay" => 'google-pay',
                "payplus-payment-gateway-paypal" => 'paypal',
                "payplus-payment-gateway-multipass" => 'multipass',
                "payplus-payment-gateway-valuecard" => 'valuecard',
                "payplus-payment-gateway-tavzahav" => 'tav-zahav',
                "payplus-payment-gateway-finitione" => 'finitione',
                "payplus-payment-gateway-hostedfields" => 'hostedFields'
            ];
            $chargeDefault = $names[$context->payment_method];

            $this->orderId = $context->order->id;
            $order = wc_get_order($this->orderId);
            $isSaveToken = $context->payment_data['wc-payplus-payment-gateway-new-payment-method'];

            if ($main_gateway->block_ip_transactions) {
                $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : "";
                if (filter_var($client_ip, FILTER_VALIDATE_IP) === false) {
                    $client_ip = ""; // Handle invalid IP scenario if necessary
                }
                $counts = array_count_values($main_gateway->get_payment_ips());
                $howMany = isset($counts[$client_ip]) ? $counts[$client_ip] : 0;
                if (in_array($client_ip, $main_gateway->get_payment_ips()) && $howMany >= $main_gateway->block_ip_transactions_hour) {
                    $result->set_payment_details('');
                    $payment_details['errorMessage'] = __('Something went wrong with the payment page - This Ip is blocked', 'payplus-payment-gateway');
                    $result->set_payment_details($payment_details);
                    wp_die(esc_html__('Something went wrong with the payment page - This Ip is blocked', 'payplus-payment-gateway'));
                }
            } else {
                $result->set_payment_details('');
            }

            $payload = $main_gateway->generatePayloadLink($this->orderId, is_admin(), null, $subscription = false, $custom_more_info = '', $move_token = false, ['chargeDefault' => $chargeDefault, 'hideOtherPayments' => $hideOtherPayments, 'isSubscriptionOrder' => $this->isSubscriptionOrder]);
            $response = $main_gateway->post_payplus_ws($main_gateway->payment_url, $payload);

            $payment_details = $result->payment_details;
            $payment_details['order_id'] = $this->orderId;
            $payment_details['secret_key'] = $this->secretKey;

            $responseArray = json_decode(wp_remote_retrieve_body($response), true);

            if ($responseArray['results']['status'] === 'error' || !isset($responseArray['results']) && isset($responseArray['message'])) {
                $payment_details['errorMessage'] = isset($responseArray['results']['description']) ? wp_strip_all_tags($responseArray['results']['description']) : $responseArray['message'];
            } else {
                $orderMeta = [
                    'payplus_page_request_uid' => $responseArray['data']['page_request_uid'],
                    'payplus_payment_page_link' => $responseArray['data']['payment_page_link']
                ];

                isset($data['wc-payplus-payment-gateway-new-payment-method']) ? $orderMeta['save_payment_method'] = $data['wc-payplus-payment-gateway-new-payment-method'] : null;


                WC_PayPlus_Meta_Data::update_meta($order, $orderMeta);

                $payment_details['paymentPageLink'] = $responseArray['data']['payment_page_link'];
            }
            $result->set_payment_details($payment_details);
            !isset($payment_details['errorMessage']) ? $result->set_status('pending') : $result->set_status('failure');
        }
    }


    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {

        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $script_path = '/block/dist/js/woocommerce-blocks/blocks.js';
        $style_path = 'block/dist/css/woocommerce-blocks/style.css'; // Add path to your CSS file

        $script_asset = array(
            'dependencies' => array(),
            'version' => '1.0.0'
        );
        $script_url = PAYPLUS_PLUGIN_URL . $script_path;
        $style_url = PAYPLUS_PLUGIN_URL . $style_path;

        // Register the script
        wp_register_script(
            'wc-payplus-payments-block',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
        wp_localize_script(
            'wc-payplus-payments-block',
            'payplus_script',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'frontNonce' => wp_create_nonce('frontNonce'),
            ]
        );

        // Register the style
        wp_register_style(
            'wc-payplus-payments-block-style',
            $style_url,
            array(), // Add dependencies if needed
            $script_asset['version']
        );

        // Enqueue the style
        wp_enqueue_style('wc-payplus-payments-block-style');

        // Set script translations if available
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wc-payplus-payments-block', 'payplus-payment-gateway', PAYPLUS_PLUGIN_URL . 'languages/');
        }

        return ['wc-payplus-payments-block'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        $isSubscriptionOrder = false;
        if (is_page() && is_checkout()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (get_class($cart_item['data']) === "WC_Product_Subscription" || get_class($cart_item['data']) === "WC_Product_Subscription_Variation") {
                    $isSubscriptionOrder = true;
                    break;
                }
            }
        }

        return [
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            'showSaveOption' => $this->settings['create_pp_token'] == 'yes' ? true : false,
            'secretKey' => $this->secretKey,
            'hideOtherPayments' => $this->hideOtherPayments,
            'multiPassIcons' => WC_PayPlus_Statics::getMultiPassIcons(),
            'isSubscriptionOrder' => $isSubscriptionOrder,
            'isAutoPPCC' => $this->isAutoPPCC,
            'importApplePayScript' => $this->importApplePayScript ? $importAapplepayScript = 'https://payments.payplus.co.il/statics/applePay/script.js?var=' . PAYPLUS_VERSION : false,
            "{$this->name}-settings" => [
                'displayMode' => $this->displayMode !== 'default' ? $this->displayMode : $this->payPlusSettings['display_mode'],
                'iFrameHeight' => $this->iFrameHeight . 'px',
                'secretKey' => $this->secretKey,
                'hideOtherPayments' => $this->hideOtherPayments,
            ],
            'gateways' => $this->settings['gateways'],
            'customIcons' => $this->customIcons,
            'icon' => ($this->gateway->hide_icon == "no") ? $this->gateway->icon : ''
        ];
    }
}
/**
 * Dummy Payments Blocks integration
 *
 * @since 1.0.3
 */
final class WC_Gateway_Payplus_credit_Card_Block extends WC_Gateway_Payplus_Payment_Block
{
    protected $name = 'payplus-payment-gateway';
}
final class WC_Gateway_Payplus_GooglePay_Block extends WC_Gateway_Payplus_Payment_Block
{
    protected $name = 'payplus-payment-gateway-googlepay';
}
final class WC_Gateway_Payplus_ApplePay_Block extends WC_Gateway_Payplus_Payment_Block
{
    protected $name = 'payplus-payment-gateway-applepay';
}
final class WC_Gateway_Payplus_Multipas_Block extends WC_Gateway_Payplus_Payment_Block
{
    protected $name = 'payplus-payment-gateway-multipass';
}
final class WC_Gateway_Payplus_Bit_Block extends WC_Gateway_Payplus_Payment_Block
{
    protected $name = 'payplus-payment-gateway-bit';
}
final class WC_Gateway_Payplus_TavZahav_Block extends WC_Gateway_Payplus_Payment_Block
{
    protected $name = 'payplus-payment-gateway-tavzahav';
}
final class WC_Gateway_Payplus_Valuecard_Block extends WC_Gateway_Payplus_Payment_Block
{
    protected $name = 'payplus-payment-gateway-valuecard';
}
final class WC_Gateway_Payplus_FinitiOne_Block extends WC_Gateway_Payplus_Payment_Block
{
    protected $name = 'payplus-payment-gateway-finitione';
}
final class WC_Gateway_Payplus_Paypal_Block extends WC_Gateway_Payplus_Payment_Block
{
    protected $name = 'payplus-payment-gateway-paypal';
}
final class WC_PayPlus_Gateway_HostedFields_Block extends WC_Gateway_Payplus_Payment_Block
{
    protected $name = 'payplus-payment-gateway-hostedfields';
}
