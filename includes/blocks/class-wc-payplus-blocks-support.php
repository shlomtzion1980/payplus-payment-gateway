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
        $this->hideOtherPayments = boolval($this->settings['hide_other_charge_methods']) ?? null;
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
        $this->settings['gateways'] = array_values(array_filter($this->settings['gateways']));

        $this->gateway = $gateways[$this->name];
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
                if (get_class($cart_item['data']) === "WC_Product_Subscription") {
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
        $script_path = '/block/dist/js/woocommerce-blocks/blocks.min.js';
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
                if (get_class($cart_item['data']) === "WC_Product_Subscription") {
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
