<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles and process WC PayPlus Orders Data.
 *
 */
class WC_PayPlus_Form_Fields
{
    public $formFields;

    /**
     * @param WP_Admin_Bar $admin_bar
     * @return void
     */
    public static function adminBarMenu($admin_bar)
    {

        $admin_bar->add_menu(array(
            'id' => 'PayPlus-toolbar',
            'title' => __('PayPlus Gateway', 'payplus-payment-gateway'),
            'href' => get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-setup-wizard",
            'meta' => array(
                'title' => __('PayPlus Gateway', 'payplus-payment-gateway'),
                'target' => '_blank',
            ),
        ));
        $admin_bar->add_menu(array(
            'id' => 'payPlus-toolbar-sub',
            'parent' => 'PayPlus-toolbar',
            'title' => __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
            'href' => get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=payplus-invoice",
            'meta' => array(
                'title' => __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
                'target' => '_blank',
                'class' => 'my_menu_item_class',
            ),
        ));
    }

    /**
     * @return void
     */
    public static function getGateway()
    {
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway'));
        exit;
    }

    /**
     * @return void
     */
    public static function addAdminPageMenu()
    {
        global $submenu;
        $parent_slug = 'payplus-payment-gateway';

        add_menu_page(
            __('PayPlus Gateway', 'payplus-payment-gateway'),
            __('PayPlus Gateway', 'payplus-payment-gateway'),
            "administrator",
            'payplus-payment-gateway',
            ['WC_PayPlus_Form_Fields', 'getGateway'],
            PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "payplus-icon.svg"
        );

        add_submenu_page(
            'payplus-payment-gateway',
            __('bit', 'payplus-payment-gateway'),
            __('bit', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-bit'

        );
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('Google Pay', 'payplus-payment-gateway'),
            __('Google Pay', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-googlepay' //Page slug

        );
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('Apple Pay', 'payplus-payment-gateway'),
            __('Apple Pay', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-applepay' //Page slug

        );

        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('MULTIPASS', 'payplus-payment-gateway'),
            __('MULTIPASS', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-multipass' //Page slug

        );
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('PayPal', 'payplus-payment-gateway'),
            __('PayPal', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-paypal' //Page slug

        );
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('Tav zahav', 'payplus-payment-gateway'),
            __('Tav Zahav', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-tavzahav' //Page slug

        );
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
            __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-invoice' //Page slug

        );
    }

    public static function getFormFields()
    {
        $listOrderStatus = ['default-woo' => __('Default Woo', 'payplus-payment-gateway')];
        $listOrderStatus = array_merge($listOrderStatus, wc_get_order_statuses());
        $formFields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable PayPlus+ Payment', 'payplus-payment-gateway'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout', 'payplus-payment-gateway'),
                'default' => __('Pay with Debit or Credit Card', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'payplus-payment-gateway'),
                'type' => 'textarea',
                'default' => __('Pay securely by Debit or Credit Card through PayPlus', 'payplus-payment-gateway'),
            ],
            'use_old_fields' => [
                'title' => __('Legacy post meta support', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Slower! (For stores that support HPOS with old fields used)', 'payplus-payment-gateway'),
                'description' =>  __('Check this to view orders meta data created before HPOS was enabled on your store.<br>This doesn`t affect stores with no HPOS.<br>If you want to reduce DB queries and are viewing new orders, uncheck this.', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'default' => 'no',
            ],
            'settings_title' => [
                'title' => __('PayPlus API Settings', 'payplus-payment-gateway'),
                'type' => 'title',
            ],
            'api_test_mode' => [
                'title' => __('Test mode', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'description' => __('Activate test mode', 'payplus-payment-gateway'),
                'label' => __('Enable Sandbox Mode', 'payplus-payment-gateway'),
                'desc_tip' => true,

            ],
            'api_key' => [
                'title' => __('API KEY', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('PayPlus API Key you can find in your account under Settings', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],

            'secret_key' => [
                'title' => __('SECRET KEY', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('PayPlus Secret Key you can find in your account under Settings', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'payment_page_id' => [
                'title' => __('Payment Page UID', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('Your payment page UID can be found under Payment Pages in your side menu in PayPlus account', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'params_title' => [
                'title' => __('PayPlus Payment Options', 'payplus-payment-gateway'),
                'type' => 'title',
            ],
            'transaction_type' => [
                'title' => __('Transactions Type', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('Payment Page Default Setting', 'payplus-payment-gateway'),
                    '1' => __('Charge', 'payplus-payment-gateway'),
                    '2' => __('Authorization', 'payplus-payment-gateway'),
                ],
                'default' => '1',
            ],
            'check_amount_authorization' => [
                'title' => __('If you allow an amount field in the transaction without a charge?', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'yes',
                // 'custom_attributes' =>$disabled
            ],

            'disable_woocommerce_scheduler' => [
                'title' => __('Disable woocommerce scheduler', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
            ],

            'invoice_display_title' => [
                'title' => __('Invoicing & Documents Settings', 'payplus-payment-gateway'),
                'type' => 'title',
            ],
            'hide_custom_fields_buttons' => [
                'title'   => __('Hide custom fields Delete/Update buttons', 'payplus-payment-gateway'),
                'type'    => 'checkbox',
                'default' => 'yes',
            ],
            'is_Local_pickup' => [
                'title' => __('Show pick up  method on invoice', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),
                'default' => 'no',
            ],
            'paying_vat_all_order' => [
                'title' => __('Every order is subject to VAT', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'yes',
            ],

            'change_vat_in_eilat' => [
                'title' => __('VAT change in Eilat', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),
                'default' => 'no',
            ],
            'keywords_eilat' => [
                'title' => __('Keywords for deliveries in Eilat', 'payplus-payment-gateway'),
                'type' => 'text',
                'default' => 'אילת,איילת,אלת,eilat,elat,EILAT,ELAT',
                'description' => __('Keywords must be separated with a comma', 'payplus-payment-gateway'),
            ],
            'send_products' => [
                'title' => __('Hide Products To Invoices and Documents', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Hide Products To Invoices and Documents', 'payplus-payment-gateway'),
            ],
            'send_variations' => [
                'title' => __('Send product variations among with products (for invoices)', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Send product variations among with products (for invoices)', 'payplus-payment-gateway'),
                'default' => 'yes',
            ],
            'initial_invoice' => [
                'title' => __('Initial Invoice / Receipt', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('Payment Page Default Setting', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                    '2' => __('No', 'payplus-payment-gateway'),
                ],
                'default' => '0',
            ],

            'paying_vat' => [
                'title' => __('Invoice For Foreign Customers', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('Paying VAT', 'payplus-payment-gateway'),
                    '1' => __('Exempt VAT', 'payplus-payment-gateway'),
                    '2' => __('Exempt VAT If Customer Billing ISO Country Is Different Than...', 'payplus-payment-gateway'),
                ],
                'default' => '0',
            ],

            'paying_vat_iso_code' => [
                'title' => __('Your Business VAT Registration Country ISO Code', 'payplus-payment-gateway'),
                'type' => 'text',
                'default' => 'IL',
            ],
            'vat_number_field' => [
                'title' => __('Custom checkout field name for vat number', 'payplus-payment-gateway'),
                'type' => 'text',
                'default' => '',
            ],
            'foreign_invoices_lang' => [
                'title' => __('The language of the invoices or documents issued to foreign customers (assuming your invoicing company supports this language)', 'payplus-payment-gateway'),
                'type' => 'text',
                'default' => 'HE',
            ],
            'display_title' => [
                'title' => __('Display Options', 'payplus-payment-gateway'),
                'type' => 'title',
            ],
            'display_mode' => [
                'title' => __('Display Mode', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'redirect' => __('Redirect', 'payplus-payment-gateway'),
                    'iframe' => __('iFrame on the next page', 'payplus-payment-gateway'),
                    'samePageIframe' => __('iFrame on the same page', 'payplus-payment-gateway'),
                    'popupIframe' => __('iFrame in a Popup', 'payplus-payment-gateway'),
                ],
                'default' => 'redirect',
                'description' => __('Also supported with the new "WooCommerce Checkout Blocks"', 'payplus-payment-gateway'),
            ],
            'iframe_height' => [
                'title' => __('iFrame Height', 'payplus-payment-gateway'),
                'type' => 'number',
                'default' => 700,
            ],
            'import_applepay_script' => [
                'title' => __('Apple Pay', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Include Apple Pay Script in Iframe Mode (You have to join the service first)', 'payplus-payment-gateway'),
            ],
            'api_title' => [
                'title' => __('Payment Page Options', 'payplus-payment-gateway'),
                'type' => 'title',
                'description' => __('You can set your payment pages as you like to', 'payplus-payment-gateway'),
            ],
            'hide_icon' => [
                'title' => __('Hide PayPlus Icon In The Checkout Page', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Hide PayPlus Icon In The Checkout Page', 'payplus-payment-gateway'),
                'default' => 'no',
            ],
            'create_pp_token' => [
                'title' => __('Saved Credit Cards', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Payment via Saved Cards', 'payplus-payment-gateway'),
                'default' => 'no',
                'desc_tip' => true,
                'description' => __('Allow customers to securely save credit card information as tokens for convenient future or recurring purchases.
                <br><br>Saving cards can be done either during purchase or through the "My Account" section in the website.', 'payplus-payment-gateway'),
            ],
            'send_add_data' => [
                'title' => __('Add Data Parameter', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Send Add Data Parameter When Payment Created', 'payplus-payment-gateway'),
                'default' => 'no',
            ],
            'hide_identification_id' => [
                'title' => __('Hide Identification Field In Payment Form', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('Payment Page Default Setting', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                    '2' => __('No', 'payplus-payment-gateway'),
                ],
                'default' => '0',
            ],
            'hide_payments_field' => [
                'title' => __('Hide Payments Number In Payment Form', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('Payment Page Default Setting', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                    '2' => __('No', 'payplus-payment-gateway'),
                ],
                'default' => '0',
            ],
            'hide_other_charge_methods' => [
                'title' => __('Hide other payment types on payment page', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('No', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                ],
                'default' => '1',
            ],
            'order_status_title' => [
                'title' => __('Order Settings', 'payplus-payment-gateway'),
                'type' => 'title',
            ],
            'successful_order_status' => [
                'title' => __('Successful Order Status', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => $listOrderStatus,
                'default' => 'default-woo',
            ],
            'fire_completed' => [
                'title' => __('Payment Completed', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Fire Payment Completed On Successful Charge', 'payplus-payment-gateway'),
                'description' => __('Only relevant if you are using the "Default Woo" in Successful Order Status option above this one.', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'default' => 'yes',
            ],
            'failure_order_status' => [
                'title' => __('Failure Order Status', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => $listOrderStatus,
                'default' => 'default-woo',
            ],
            'sendEmailApproval' => [
                'title' => __('Successful Transaction E-mail Through PayPlus Servers', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('No', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                ],
                'default' => '0',
            ],
            'sendEmailFailure' => [
                'title' => __('Failure Transaction E-mail Through PayPlus Servers', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('No', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                ],
                'default' => '0',
            ],
            'logging' => [
                'title' => __('Logging', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Log debug messages', 'payplus-payment-gateway'),
                'default' => 'yes',
                'custom_attributes' => array('disabled' => 'disabled'),
            ],

            'callback_addr' => [
                'title' => __('Callback url', 'payplus-payment-gateway'),
                'type' => 'url',
                'description' => __('To receive transaction information you need a web address', 'payplus-payment-gateway'),
                'default' => '',
            ],
            'recurring_order_set_to_paid' => [
                'title' => __('Mark as "paid" successfully created subscription orders', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),
                'default' => 'no',
            ],
            'add_product_field_transaction_type' => [
                'title' => __('Add Product Field Transaction Type', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),
                'default' => 'no',
            ],
            'exist_company' => [
                'title' => __('Display company name on the invoice', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),
                'default' => 'no',
                'desc_tip' => true,
                'description' => __('If this option is selected,
                       the name that will appear on the invoice will be taken from the company name field and not from the personal name field.
                         If no company name is entered, the name that will be written on the invoice will be the first name', 'payplus-payment-gateway'),

            ],
            'balance_name' => [
                'title' => __('Display Balance Name', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),
                'default' => 'no',
            ],

            'block_ip_transactions' => [
                'title' => __('Block ip transactions', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),
                'default' => 'yes',
                'desc_tip' => true,
                'description' => __('If the client fails transactions more than the number
                         of times you entered, his IP will be blocked for one hour.', 'payplus-payment-gateway'),

            ],
            'block_ip_transactions_hour' => [
                'title' => __('Number of times per hour to block ip', 'payplus-payment-gateway'),
                'type' => 'text',
                'default' => '10',
            ],
            'menu_plugin' => [
                'title' => __('Menu plugin', 'payplus-payment-gateway'),
                'type' => 'title',
            ],
            'disable_menu_header' => [
                'title' => __('Top menu cancellation', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),

            ],
            'disable_menu_side' => [
                'title' => __('Canceling the side menu', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),

            ],
            'menu_plugin_external' => [
                'title' => __('Enabling external plugins', 'payplus-payment-gateway'),
                'type' => 'title',
            ],
            'enable_pickup' => [
                'title' => __('Enable PickUP', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),

            ],
            'enable_design_checkout' => [
                'title' => __('Enable design checkout', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),

            ],
            'log_status' => [
                'title' => __('Order status log', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('', 'payplus-payment-gateway'),
                'default' => 'no',

            ],
        ];
        return $formFields;
    }
}
