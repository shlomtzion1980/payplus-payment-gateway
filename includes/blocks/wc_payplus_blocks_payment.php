<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Gateway_Payplus_Paymnet_Block extends AbstractPaymentMethodType
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
    protected $name;

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_' . $this->name . '_settings', []);

        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = $gateways[$this->name];
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
        $script_path = '/block/dist/js/woocommerce-blocks/checkout.js';

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
            'showSaveOption'                 => $this->settings['create_pp_token'] == 'yes' ? true : false,
            'icon' => ($this->gateway->hide_icon == "no") ? $this->gateway->icon : ''
        ];
    }
}
/**
 * Dummy Payments Blocks integration
 *
 * @since 1.0.3
 */
final class WC_Gateway_Payplus_credit_Card_Block extends WC_Gateway_Payplus_Paymnet_Block
{
    protected $name = 'payplus-payment-gateway';
}
final class WC_Gateway_Payplus_GooglePay_Block extends WC_Gateway_Payplus_Paymnet_Block
{
    protected $name = 'payplus-payment-gateway-googlepay';
}
final class WC_Gateway_Payplus_ApplePay_Block extends WC_Gateway_Payplus_Paymnet_Block
{
    protected $name = 'payplus-payment-gateway-applepay';
}

final class WC_Gateway_Payplus_Multipas_Block extends WC_Gateway_Payplus_Paymnet_Block
{
    protected $name = 'payplus-payment-gateway-multipass';
}
final class WC_Gateway_Payplus_Bit_Block extends WC_Gateway_Payplus_Paymnet_Block
{
    protected $name = 'payplus-payment-gateway-bit';
}
final class WC_Gateway_Payplus_TavZahav_Block extends WC_Gateway_Payplus_Paymnet_Block
{
    protected $name = 'payplus-payment-gateway-tavzahav';
}
final class WC_Gateway_Payplus_Valuecard_Block extends WC_Gateway_Payplus_Paymnet_Block
{
    protected $name = 'payplus-payment-gateway-valuecard';
}
final class WC_Gateway_Payplus_FinitiOne_Block extends WC_Gateway_Payplus_Paymnet_Block
{
    protected $name = 'payplus-payment-gateway-finitione';
}
final class WC_Gateway_Payplus_Paypal_Block extends WC_Gateway_Payplus_Paymnet_Block
{
    protected $name = 'payplus-payment-gateway-paypal';
}
