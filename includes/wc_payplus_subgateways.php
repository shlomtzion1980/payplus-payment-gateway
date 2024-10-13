<?php
defined('ABSPATH') || exit; // Exit if accessed directly

abstract class WC_PayPlus_Subgateway extends WC_PayPlus_Gateway
{
    public $id;
    public $payplus_default_charge_method;
    public $iconURL;
    public $method_title_text;
    public $method_description_text;
    public $pay_with_text;
    public $default_description_settings_text;
    public $hide_other_charge_methods;
    public $allPayment;
    public $allTypePayment;

    /**
     *
     */
    public function __construct()
    {

        parent::__construct();
        if ($this->hide_icon == "no") {
            $this->icon = PAYPLUS_PLUGIN_URL . $this->iconURL;
        }

        $this->allPayment = array(
            __('Pay with bit via PayPlus', 'payplus-payment-gateway'),
            __('Pay with Google Pay via PayPlus', 'payplus-payment-gateway'),
            __('Pay with Apple Pay via PayPlus', 'payplus-payment-gateway'),
            __('Pay With MULTIPASS via PayPlus', 'payplus-payment-gateway'),
            __('Pay with PayPal via PayPlus', 'payplus-payment-gateway'),
            __('Pay with Tav Zahav via PayPlus', 'payplus-payment-gateway'),
            __('Pay with Valuecard via PayPlus', 'payplus-payment-gateway'),
            __('Pay with finitiOne via PayPlus', 'payplus-payment-gateway'),
            __('Pay with PayPlus Hosted Fields', 'payplus-payment-gateway')

        );
        $this->allTypePayment = array(
            __('bit', 'payplus-payment-gateway'),
            __('Google Pay', 'payplus-payment-gateway'),
            __('Apple Pay', 'payplus-payment-gateway'),
            __('MULTIPASS', 'payplus-payment-gateway'),
            __('PayPal', 'payplus-payment-gateway'),
            __('Tav Zahav', 'payplus-payment-gateway'),
            __('Valuecard', 'payplus-payment-gateway'),
            __('finitiOne', 'payplus-payment-gateway'),
            __('hostedFields', 'payplus-payment-gateway')
        );

        $this->method_title = $this->method_title_text;
        $this->description = $this->get_option('description');

        $this->default_charge_method = $this->payplus_default_charge_method;
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
        if ($this->settings['enabled'] === null) {
            $this->enabled = 'no';
        }

        if ($this->hide_other_charge_methods === "1") {
            $this->settings['sub_hide_other_charge_methods'] = "1";
        } else {
            $this->settings['sub_hide_other_charge_methods'] = "2";
        }
    }

    /**
     * @return void
     */
    public function init_form_fields()
    {
        $methodTitleText = "";
        switch ($this->method_title_text) {
            case 'PayPlus - bit':
                $methodTitleText = esc_html__('PayPlus - bit', 'payplus-payment-gateway');
                break;
            case 'PayPlus - Google Pay':
                $methodTitleText = esc_html__('PayPlus - Google Pay', 'payplus-payment-gateway');
                break;
            case 'PayPlus - Apple Pay':
                $methodTitleText = esc_html__('PayPlus - Apple Pay', 'payplus-payment-gateway');
                break;
            case 'PayPlus - MULTIPASS':
                $methodTitleText = esc_html__('PayPlus - MULTIPASS', 'payplus-payment-gateway');
                break;
            case 'PayPlus - PayPal':
                $methodTitleText = esc_html__('PayPlus - PayPal', 'payplus-payment-gateway');
                break;
            case 'PayPlus - Tav Zahav':
                $methodTitleText = esc_html__('PayPlus - Tav Zahav', 'payplus-payment-gateway');
                break;
            case 'PayPlus - finitiOne':
                $methodTitleText = esc_html__('PayPlus - finitiOne', 'payplus-payment-gateway');
                break;
            case 'PayPlus - PayPal':
                $methodTitleText = esc_html__('PayPlus - PayPal', 'payplus-payment-gateway');
                break;
            case 'PayPlus - Valuecard':
                $methodTitleText = esc_html__('PayPlus - Valuecard', 'payplus-payment-gateway');
                break;
            case 'PayPlus - Hosted Fields':
                $methodTitleText = esc_html__('PayPlus - Hosted Fields', 'payplus-payment-gateway');
                break;
        }
        $payWithText = '';
        switch ($this->pay_with_text) {
            case 'Pay with bit':
                $payWithText = esc_html__('Pay with bit', 'payplus-payment-gateway');
                break;
            case 'Pay with Google Pay':
                $payWithText = esc_html__('Pay with Google Pay', 'payplus-payment-gateway');
                break;
            case 'Pay with Apple Pay':
                $payWithText = esc_html__('Pay with Apple Pay', 'payplus-payment-gateway');
                break;
            case 'Pay with MULTIPASS':
                $payWithText = esc_html__('Pay with MULTIPASS', 'payplus-payment-gateway');
                break;
            case 'Pay with PayPal':
                $payWithText = esc_html__('Pay with PayPal', 'payplus-payment-gateway');
                break;
            case 'Pay with Tav Zahav':
                $payWithText = esc_html__('Pay with Tav Zahav', 'payplus-payment-gateway');
                break;
            case 'Pay with Valuecard':
                $payWithText = esc_html__('Pay with Valuecard', 'payplus-payment-gateway');
                break;
            case 'Pay with Tav finitiOne':
                $payWithText = esc_html__('Pay with Tav finitiOne', 'payplus-payment-gateway');
                break;
            case 'Pay with Hosted Fields':
                $payWithText = esc_html__('Pay with Hosted Fields', 'payplus-payment-gateway');
                break;
        }
        $this->form_fields = [
            'enabled' => [
                'title' => $methodTitleText,
                'type' => 'select',
                'options' => ['yes' => __('Enable', 'payplus-payment-gateway'), 'no' => __('Disable', 'payplus-payment-gateway')],
                'label' => __('Enable/Disable', 'payplus-payment-gateway'),
                'default' => 'no'
            ],
            'title' => [
                'title' => __('Title', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout', 'payplus-payment-gateway'),
                'default' => $payWithText,
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'payplus-payment-gateway'),
                'type' => 'text',
                'default' => $this->default_description_settings_text,
            ],
            'display_mode' => [
                'title' => __('Display Mode', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'default' => __('Use global default', 'payplus-payment-gateway'),
                    'redirect' => __('Redirect', 'payplus-payment-gateway'),
                    'iframe' => __('iFrame', 'payplus-payment-gateway'),
                    'samePageIframe' => __('iFrame on the same page', 'payplus-payment-gateway'),
                    'popupIframe' => __('iFrame in a Popup', 'payplus-payment-gateway'),
                ],
                'default' => 'redirect',
            ],
            'iframe_height' => [
                'title' => __('iFrame Height', 'payplus-payment-gateway'),
                'type' => 'number',
                'default' => 600,
            ],
            'hide_icon' => [
                'title' => __('Hide Payment Method Icon In The Checkout Page', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Hide Payment Method Icon In The Checkout Page', 'payplus-payment-gateway'),
                'default' => 'no'
            ],
            'sub_hide_other_charge_methods' => [
                'title' => __('Hide other payment types on payment page', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('No', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                    '2' => __('Use global default', 'payplus-payment-gateway'),
                ],
                'default' => '1',
            ],
            'hosted_fields_width' => [
                'title' => __('Set width for hosted fields container (%)', 'payplus-payment-gateway'),
                'description' => __('This sets the width of the hosted fields container in percentage (Max is 100).', 'payplus-payment-gateway'),
                'type' => 'number',
                'default' => '50'
            ],
            'hide_payplus_gateway' => [
                'title' => __('Hide PayPlus gateway (No saved tokens)', 'payplus-payment-gateway'),
                'description' => __('Hide PayPlus gateway if current user has no saved tokens', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no'
            ]
        ];
        if ($this->id === 'payplus-payment-gateway-multipass') {
            unset($this->form_fields['sub_hide_other_charge_methods']);
        }
        if ($this->id !== 'payplus-payment-gateway-hostedfields') {
            unset($this->form_fields['hosted_fields_width']);
        }
        if ($this->id === 'payplus-payment-gateway-hostedfields') {
            unset($this->form_fields['display_mode']);
            unset($this->form_fields['iframe_height']);
        }
    }


    /**
     * @return void
     */
    public function admin_options()
    {
        parent::admin_options();
        // echo esc_html__("Before enabling this option, please ensure you have proper PayPlus credentials and authorization", 'payplus-payment-gateway');
    }

    /**
     * @return void
     */
    public function init_settings()
    {
        $defaultOptions = [
            'enabled' => 'no',
            'title' => '',
            'description' => '',
            'display_mode' => 'default',
            'iframe_height' => 600,
            'hide_icon' => 'no',
            'hide_other_charge_methods' => '1',
        ];

        $methodDescriptionText = "";
        switch ($this->method_description_text) {
            case 'Pay with bit via PayPlus':
                $methodDescriptionText = esc_html__('Pay with bit via PayPlus', 'payplus-payment-gateway');
                break;
            case 'Pay with Google Pay via PayPlus':
                $methodDescriptionText = esc_html__('Pay with Google Pay via PayPlus', 'payplus-payment-gateway');
                break;
            case 'Pay with Apple Pay via PayPlus':
                $methodDescriptionText = esc_html__('Pay with Apple Pay via PayPlus', 'payplus-payment-gateway');
                break;
            case 'Pay With MULTIPASS via PayPlus':
                $methodDescriptionText = esc_html__('Pay With MULTIPASS via PayPlus', 'payplus-payment-gateway');
                break;
            case 'Pay with PayPal via PayPlus':
                $methodDescriptionText = esc_html__('Pay with PayPal via PayPlus', 'payplus-payment-gateway');
                break;
            case 'Pay with Tav Zahav via PayPlus':
                $methodDescriptionText = esc_html__('Pay with Tav Zahav via PayPlus', 'payplus-payment-gateway');
                break;
            case 'Pay with Valuecard via PayPlus':
                $methodDescriptionText = esc_html__('Pay with Valuecard via PayPlus', 'payplus-payment-gateway');
                break;
            case 'Pay with finitiOne via PayPlus':
                $methodDescriptionText = esc_html__('Pay with finitiOne via PayPlus', 'payplus-payment-gateway');
                break;
            case 'Pay with PayPlus Hosted Fields':
                $methodDescriptionText = esc_html__('Pay with PayPlus Hosted Fields', 'payplus-payment-gateway');
                break;
        }

        $subOptionsettings = get_option($this->get_option_key(), $defaultOptions);
        $this->settings = get_option('woocommerce_payplus-payment-gateway_settings', $defaultOptions);

        $this->enabled = $this->settings['enabled'] = $subOptionsettings['enabled'];
        $this->settings['description'] = $subOptionsettings['description'];
        $this->settings['title'] = (!empty($subOptionsettings['title'])) ? $subOptionsettings['title'] : $methodDescriptionText;
        $this->settings['display_mode'] = $subOptionsettings['display_mode'];
        $this->settings['hide_icon'] = $subOptionsettings['hide_icon'];
        $this->settings['iframe_height'] = $subOptionsettings['iframe_height'];
        $this->settings['hosted_fields_width'] = isset($subOptionsettings['hosted_fields_width']) ? $subOptionsettings['hosted_fields_width'] : 50;
        $this->settings['hide_payplus_gateway'] = isset($subOptionsettings['hide_payplus_gateway']) ? $subOptionsettings['hide_payplus_gateway'] : 'no';
        $this->settings['default_charge_method'] = $this->payplus_default_charge_method;
        $this->settings['sub_hide_other_charge_methods'] = isset($subOptionsettings['sub_hide_other_charge_methods']) ? $subOptionsettings['sub_hide_other_charge_methods'] : null;

        if ($this->settings['sub_hide_other_charge_methods'] != 2 && $this->settings['sub_hide_other_charge_methods'] !== null) {
            $this->settings['hide_other_charge_methods'] = $this->settings['sub_hide_other_charge_methods'];
        }
    }

    /**
     * @return void
     */
    public function payment_fields()
    {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description)); // @codingStandardsIgnoreLine.
        }

        if ($this->supports('default_credit_card_form')) {
            $this->credit_card_form(); // Deprecated, will be removed in a future version.
        }
    }

    /**
     * @return void
     */
    public function save_payment_method_checkbox() {}

    /**
     * @return void
     */
    public function msg_checkout_code() {}
}

class WC_PayPlus_Gateway_Bit extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-bit';
    public $method_title_text = 'PayPlus - bit';
    public $default_description_settings_text = 'Bit payment via PayPlus';
    public $method_description_text = 'Pay with bit via PayPlus';
    public $payplus_default_charge_method = 'bit';
    public $iconURL = 'assets/images/bitLogo.png';
    public $pay_with_text = 'Pay with bit';
}

class WC_PayPlus_Gateway_GooglePay extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-googlepay';
    public $method_title_text = 'PayPlus - Google Pay';
    public $default_description_settings_text = 'Google Pay payment via PayPlus';
    public $method_description_text = 'Pay with Google Pay via PayPlus';
    public $payplus_default_charge_method = 'google-pay';
    public $iconURL = 'assets/images/google-payLogo.png';
    public $pay_with_text = 'Pay with Google Pay';
}

class WC_PayPlus_Gateway_ApplePay extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-applepay';
    public $method_title_text = 'PayPlus - Apple Pay';
    public $default_description_settings_text = 'Apple1 Pay payment via PayPlus';
    public $method_description_text = 'Pay with Apple Pay via PayPlus';
    public $payplus_default_charge_method = 'apple-pay';
    public $iconURL = 'assets/images/apple-payLogo.png';
    public $pay_with_text = 'Pay with Apple Pay';
}

class WC_PayPlus_Gateway_Multipass extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-multipass';
    public $method_title_text = 'PayPlus - MULTIPASS';
    public $default_description_settings_text = 'BUYME payment via PayPlus';
    public $method_description_text = 'Pay With MULTIPASS via PayPlus';
    public $payplus_default_charge_method = 'multipass';
    public $iconURL = 'assets/images/multipassLogo.png';
    public $pay_with_text = 'Pay with MULTIPASS';
}

class WC_PayPlus_Gateway_Paypal extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-paypal';
    public $method_title_text = 'PayPlus - PayPal';
    public $default_description_settings_text = 'PayPal payment via PayPlus';
    public $method_description_text = 'Pay with PayPal via PayPlus';
    public $payplus_default_charge_method = 'paypal';
    public $iconURL = 'assets/images/paypalLogo.png';
    public $pay_with_text = 'Pay with PayPal';
}

class WC_PayPlus_Gateway_TavZahav extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-tavzahav';
    public $method_title_text = 'PayPlus - Tav Zahav';
    public $default_description_settings_text = 'Tav Zahav payment via PayPlus';
    public $method_description_text = 'Pay with Tav Zahav via PayPlus';
    public $payplus_default_charge_method = 'tav-zahav';
    public $iconURL = 'assets/images/verifoneLogo.png';
    public $pay_with_text = 'Pay with Tav Zahav';
}

class WC_PayPlus_Gateway_Valuecard extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-valuecard';
    public $method_title_text = 'PayPlus - Valuecard';
    public $default_description_settings_text = 'Valuecard  payment via PayPlus';
    public $method_description_text = 'Pay with Valuecard via PayPlus';
    public $payplus_default_charge_method = 'valuecard';
    public $iconURL = 'assets/images/valuecardLogo.png';
    public $pay_with_text = 'Pay with  Valuecard ';
}

class WC_PayPlus_Gateway_FinitiOne extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-finitione';
    public $method_title_text = 'PayPlus - finitiOne';
    public $default_description_settings_text = 'finitiOne  payment via PayPlus';
    public $method_description_text = 'Pay with finitiOne via PayPlus';
    public $payplus_default_charge_method = 'finitione';
    public $iconURL = 'assets/images/finitioneLogo.png';
    public $pay_with_text = 'Pay with Tav finitiOne';
}

class WC_PayPlus_Gateway_HostedFields extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-hostedfields';
    public $method_title_text = 'PayPlus - Hosted Fields';
    public $default_description_settings_text = 'payment via PayPlus Hosted Fields';
    public $method_description_text = 'Pay with PayPlus Hosted Fields';
    public $payplus_default_charge_method = 'hostedFields';
    public $iconURL = 'assets/images/PayPlusLogo.svg';
    public $pay_with_text = 'Pay with PayPlus Hosted Fields';

    public function __construct()
    {
        parent::__construct();
        $this->id = 'payplus-payment-gateway-hostedfields';
        $this->method_title = __('PayPlus - Hosted Fields', 'woocommerce');
    }

    // Override the process_payment method
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ($this->id === "payplus-payment-gateway-hostedfields") {
            new WC_PayPlus_HostedFields($order, $order_id);
        }
        return array(
            'result'   => 'success',
            'redirect' => '#', // No redirect here
            'custom_js_trigger' => true, // Custom flag for JS
        );
    }
}
