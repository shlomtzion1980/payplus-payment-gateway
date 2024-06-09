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
    public $WC_PayPlus_Gateway;
    private $secretKey;
    public $iFrameHeight;
    /**
     * The Payment Request configuration class used for Shortcode PRBs. We use it here to retrieve
     * the same configurations.
     *
     * @var WC_Stripe_Payment_Request
     */
    private $payment_request_configuration;

    /**
     * Constructor
     *
     * @param WC_Stripe_Payment_Request  The Stripe Payment Request configuration used for Payment
     *                                   Request buttons.
     */
    public function __construct($payment_request_configuration = null)
    {

        //  $this->payment_request_configuration = null !== $payment_request_configuration ? $payment_request_configuration : new WC_Stripe_Payment_Request();
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->WC_PayPlus_Gateway = new WC_PayPlus_Gateway;
        $payplus_payment_gateway_settings = get_option('woocommerce_payplus-payment-gateway_settings');

        $this->displayMode = $payplus_payment_gateway_settings['display_mode'];
        $this->iFrameHeight = $payplus_payment_gateway_settings['iframe_height'];
        add_action('woocommerce_rest_checkout_process_payment_with_context', [$this, 'add_payment_request_order_meta'], 8, 2);

        $this->settings = get_option('woocommerce_' . $this->name . '_settings', []);
        $this->secretKey = $this->settings['secret_key'];
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
        $main_gateway              = $this->WC_PayPlus_Gateway;

        if (!$is_payplus_payment_method) {
            return;
        }

        $this->orderId = $context->order->id;
        $order = wc_get_order($this->orderId);
        $isSaveToken = $context->payment_data['wc-payplus-payment-gateway-new-payment-method'];
        $payload = $this->WC_PayPlus_Gateway->generatePayloadLink($this->orderId, false, $token = null, $subscription = false, $custom_more_info = '', $move_token = false);
        $response = $this->WC_PayPlus_Gateway->post_payplus_ws($this->WC_PayPlus_Gateway->payment_url, $payload);
        $payment_details['order_id'] = $this->orderId;
        $payment_details['secret_key'] = $this->secretKey;


        $res = json_decode(wp_remote_retrieve_body($response));
        $saveToken = $data['wc-payplus-payment-gateway-new-payment-method'] ? $data['wc-payplus-payment-gateway-new-payment-method'] : false;
        $dataLink = $res->data;
        $insertMeta = array(
            'payplus_page_request_uid' => $dataLink->page_request_uid,
            'payplus_payment_page_link' => $dataLink->payment_page_link,
            'save_payment_method' => $saveToken
        );
        WC_PayPlus_Order_Data::update_meta($order, $insertMeta);
        //unset($context->payment_data['token']);
        $token = $context->payment_data['token'] ?? false;
        // print_r($token);
        // print_r($this->displayMode);
        // die;
        if (!in_array($this->displayMode, ['iframe', 'redirect']) && !$token) {
            $result->set_payment_details($payment_details);
            $result->set_status('success');
            // echo 'not in array';
            // die;
        } elseif (!in_array($this->displayMode, ['iframe', 'redirect']) && $token) {
            // $result->set_payment_details($payment_details);
        } elseif (in_array($this->displayMode, ['iframe', 'redirect']) && !$token) {
            // echo 'not token!';
            // // die;
            // $result->set_payment_details($payment_details);
            // $result->set_status('success');
        }
    }


    /**
     * Handles adding information about the payment request type used to the order meta.
     *
     * @param \WC_Order $order The order being processed.
     * @param string    $payment_request_type The payment request type used for payment.
     */
    private function add_order_meta(\WC_Order $order, $payment_request_type)
    {
        if ('apple_pay' === $payment_request_type) {
            $order->set_payment_method_title('Apple Pay (Stripe)');
            $order->save();
        } elseif ('google_pay' === $payment_request_type) {
            $order->set_payment_method_title('Google Pay (Stripe)');
            $order->save();
        } elseif ('payment_request_api' === $payment_request_type) {
            $order->set_payment_method_title('Payment Request (Stripe)');
            $order->save();
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
            'displayMode' => $this->displayMode,
            'iFrameHeight' => $this->iFrameHeight . 'px',
            'secretKey' => $this->secretKey,
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
