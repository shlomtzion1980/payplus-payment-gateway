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
        $this->hideOtherPayments = $this->settings['hide_other_charge_methods'] ?? null;

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
            "payplus-payment-gateway-finitione" => 'finitione'
        ];
        $chargeDefault = $names[$context->payment_method];

        $this->orderId = $context->order->id;
        $order = wc_get_order($this->orderId);
        $isSaveToken = $context->payment_data['wc-payplus-payment-gateway-new-payment-method'];

        if ($main_gateway->block_ip_transactions) {
            $client_ip = $_SERVER['REMOTE_ADDR'];
            $counts = array_count_values($main_gateway->get_payment_ips());
            $howMany = $counts[$client_ip];
            if (in_array($client_ip, $main_gateway->get_payment_ips()) && $howMany >= $main_gateway->block_ip_transactions_hour) {
                $result->set_payment_details('');
                $payment_details['errorMessage'] = __('Something went wrong with the payment page - This Ip is blocked', 'payplus-payment-gateway');
                $result->set_payment_details($payment_details);
                wp_die(esc_html__('Something went wrong with the payment page - This Ip is blocked', 'payplus-payment-gateway'));
            }
        } else {
            $result->set_payment_details('');
        }
        $payload = $main_gateway->generatePayloadLink($this->orderId, is_admin(), null, $subscription = false, $custom_more_info = '', $move_token = false, ['chargeDefault' => $chargeDefault, 'hideOtherPayments' => $hideOtherPayments]);
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
        $script_path = '/block/dist/js/woocommerce-blocks/blocks.js';

        $script_asset = array(
            'dependencies' => array(),
            'version' => '1.0.0'
        );
        $script_url = PAYPLUS_PLUGIN_URL . $script_path;
        wp_register_script(
            'wc-payplus-payments-block',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
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

        return [
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            'showSaveOption' => $this->settings['create_pp_token'] == 'yes' ? true : false,
            'secretKey' => $this->secretKey,
            'hideOtherPayments' => $this->hideOtherPayments,
            'multiPassIcons' => WC_PayPlus_Statics::getMultiPassIcons(),
            "{$this->name}-settings" => [
                'displayMode' => $this->displayMode !== 'default' ? $this->displayMode : $this->payPlusSettings['display_mode'],
                'iFrameHeight' => $this->iFrameHeight . 'px',
                'secretKey' => $this->secretKey,
                'hideOtherPayments' => $this->hideOtherPayments,
            ],
            'gateways' => $this->settings['gateways'],
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
