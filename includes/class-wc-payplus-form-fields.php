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
            'href' => get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway",
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
        $nonce = wp_create_nonce('payPlusOrderChecker');

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
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('Run PayPlus Orders Check', 'payplus-payment-gateway'),
            __('Run PayPlus Orders Check', 'payplus-payment-gateway'),
            'edit_shop_orders', //Capability
            'runPayPlusOrdersChecker?_wpnonce=' . $nonce, //Page slug
            [__CLASS__, 'runPayPlusOrdersChecker']
        );
    }

    public static function runPayPlusOrdersChecker()
    {
        if (isset($_GET['page'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $pageSlug = sanitize_text_field($_GET['page']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $nonce = explode("?_wpnonce=", $pageSlug)[1];
            if (!wp_verify_nonce($nonce, 'payPlusOrderChecker')) {
                wp_die('Sorry this page is not allowed! - runPayPlusOrdersChecker');
            }
        }
        // The function that runs when the button is clicked
        if (current_user_can('edit_shop_orders')) {
            // Perform your custom action here
            echo 'Running PayPlus Order checker... - check your logs!';
            $payPlusGateway = new WC_PayPlus_Gateway;
            $payPlusGateway->payPlusOrdersCheck();
        } else {
            wp_die('You do not have permission to perform this action.');
        }
    }


    public static function getFormFields()
    {
        $listOrderStatus = ['default-woo' => __('Default Woo', 'payplus-payment-gateway')];
        $listOrderStatus = array_merge($listOrderStatus, wc_get_order_statuses());
        $formFields = [
            'plugin_title' => [
                'title' => __('PayPlus Plugin Settings', 'payplus-payment-gateway'),
                'type' => 'title',
                'description' => __('Basic plugin settings - set these and you`re good to go!', 'payplus-payment-gateway'),
            ],
            'enabled' => [
                'title' => __('Enable PayPlus+ Payment', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable/Disable', 'payplus-payment-gateway'),
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
            'api_test_mode' => [
                'title' => __('Plugin Environment', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => ['no' => __('Production Mode', 'payplus-payment-gateway'), 'yes' => __('Sandbox/Test Mode', 'payplus-payment-gateway')],
                'description' => __('Activate test mode', 'payplus-payment-gateway'),
                'label' => __('Enable Sandbox Mode', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'api_key' => [
                'title' => __('API Key', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('PayPlus API Key you can find in your account under Settings', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'secret_key' => [
                'title' => __('Secret Key', 'payplus-payment-gateway'),
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
            'dev_api_key' => [
                'title' => __('Development API Key', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('PayPlus Dev API Key you can find in your account under Settings', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],

            'dev_secret_key' => [
                'title' => __('Devlopment Secret Key', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('PayPlus Dev Secret Key you can find in your account under Settings', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'dev_payment_page_id' => [
                'title' => __('Development Payment Page UID', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('Your Dev payment page UID can be found under Payment Pages in your side menu in PayPlus account', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
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
            'checkout_page_title' => [
                'title' => __('Checkout Page Options', 'payplus-payment-gateway'),
                'type' => 'title',
                'description' => __('Setup for the wordpress checkout page.', 'payplus-payment-gateway'),
            ],
            'hide_icon' => [
                'title' => __('Hide PayPlus Icon', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'description' => __('Hide PayPlus Icon In The Checkout Page', 'payplus-payment-gateway'),
                'label' => __('Hide PayPlus Icon In The Checkout Page', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'default' => 'no',
            ],
            'enable_design_checkout' => [
                'title' => __('Design checkout', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'description' => __('Place the payment icons on the left of the text - relevant for classic checkout page only.', 'payplys-payment-gateway'),
                'desc_tip' => true,
                'label' => __('Change icon layout on checkout page.', 'payplus-payment-gateway'),
            ],
            'custom_icons' => [
                'title' => __('Custom Checkout Icons', 'payplus-payment-gateway'),
                'type' => 'textarea',
                'default' => '',
                'description' => __('Paste images urls here divided by ; (Semicolon) to show under payment method label side beside side.', 'payplus-payment-gateway'),
                'desc_tip' => true,
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
                'description' => __('Relevant only if the clearing company demands "add_data" or "x" parameters', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'label' => __('Send add data parameter on transaction', 'payplus-payment-gateway'),
                'default' => 'no',
            ],
            'import_applepay_script' => [
                'title' => __('Apple Pay', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Add Apple Pay Script to iframe', 'payplus-payment-gateway'),
                'description' => __('Include Apple Pay Script in Iframe Mode (You have to join the service first)', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'payment_page_title' => [
                'title' => __('Payment Page Options', 'payplus-payment-gateway'),
                'type' => 'title',
                'description' => __('Setup for the PayPlus Payment Page.', 'payplus-payment-gateway'),
            ],
            'display_mode' => [
                'title' => __('Display Mode', 'payplus-payment-gateway'),
                'type' => 'select',
                'description' => __('Set the way the PayPlus Payment Page will be loaded in/from the wordpress checkout page.', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'options' => [
                    'redirect' => __('Redirect', 'payplus-payment-gateway'),
                    'iframe' => __('iFrame on the next page', 'payplus-payment-gateway'),
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
            'hide_identification_id' => [
                'title' => __('Hide ID Field In Payment Page', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('Payment Page Default Setting', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                    '2' => __('No', 'payplus-payment-gateway'),
                ],
                'default' => '0',
                'description' => __('Hide the identification field in the payment page - ID or Social Security...', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'hide_payments_field' => [
                'title' => __('Hide Number Of Payments In Payment Page', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('Payment Page Default Setting', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                    '2' => __('No', 'payplus-payment-gateway'),
                ],
                'default' => '0',
                'description' => __('Hide the option to choose more than one payment.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'hide_other_charge_methods' => [
                'title' => __('Hide Other Payment Methods On Payment Page', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('No', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                ],
                'default' => '1',
                'description' => __('Hide the other payment methods on the payment page.<br>Example: If you have Google Pay and Credit Cards - 
                when the customer selects payment with Google Pay he will only see the Google Pay in the payment page and will not see the CC fields.', 'payplus-payment-gateway'),
                'desc_tip' => true,
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
            'callback_addr' => [
                'title' => __('Callback url', 'payplus-payment-gateway'),
                'type' => 'url',
                'description' => __('To receive transaction information you need a web address', 'payplus-payment-gateway'),
                'default' => '',
            ],
            'send_products' => [
                'title' => __('Hide products from transaction data', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Send all items as: "General Product" in PayPlus transaction data.', 'payplus-payment-gateway'),
                'description' => __('Send all items as: "General Product" in PayPlus transaction data.', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'class' => 'payplus-documents'
            ],
            'recurring_order_set_to_paid' => [
                'title' => __('Mark as "paid" successfully created subscription orders', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => '',
                'default' => 'no',
            ],
            'add_product_field_transaction_type' => [
                'title' => __('Add Product Field Transaction Type', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => '',
                'default' => 'no',
            ],
            'exist_company' => [
                'title' => __('Display company name on the invoice', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => '',
                'default' => 'no',
                'desc_tip' => true,
                'description' => __('If this option is selected,
                       the name that will appear on the invoice will be taken from the company name field and not from the personal name field.
                         If no company name is entered, the name that will be written on the invoice will be the first name', 'payplus-payment-gateway'),
            ],
            'balance_name' => [
                'title' => __('Display Balance Name', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => '',
                'default' => 'no',
            ],
            'block_ip_transactions' => [
                'title' => __('Block ip transactions', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => '',
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
            'advanced_title' => [
                'title' => __('PayPlus Advanced Features', 'payplus-payment-gateway'),
                'type' => 'title',
            ],
            'token_order_payment' => [
                'title' => __('Enable/Disable token payment (Through Admin)', 'payplus-payment-gateway'),
                'label' => __('Applicable for users that can edit orders.', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('If the user can edit orders, and there are saved tokens in the customer account. A token select and "Pay With Token" button will be shown.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'payplus_cron_service' => [
                'title' => __('Activate PayPlus cron', 'payplus-payment-gateway'),
                'label' => __('Add PayPlus orders cron checker.', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('This checks all orders created within the last 2 hours and are in "pending" status and verifies the PayPlus IPN Process.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'payplus_data_save_order_note' => [
                'title' => __('Transaction data in order notes', 'payplus-payment-gateway'),
                'label' => __('Save PayPlus transaction data to the order notes', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Whenever a transaction is done add the payplus data to the order note.<br>This data also appears in the PayPlus Data metabox.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'show_payplus_data_metabox' => [
                'title' => __('Show PayPlus Metabox', 'payplus-payment-gateway'),
                'label' => __('Show the transaction data in the PayPlus dedicated metabox', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'yes',
                'description' => __('Adds the PayPlus transaction data in a dedicated metabox on the side in the order page.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'use_old_fields' => [
                'title' => __('Legacy post meta support', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Slower! (For stores that support HPOS with old fields used)', 'payplus-payment-gateway'),
                'description' =>  __('Check this to view orders meta data created before HPOS was enabled on your store.<br>This doesn`t affect stores with no HPOS.<br>If you want to reduce DB queries and are viewing new orders, uncheck this.', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'default' => 'no',
            ],
            'disable_menu_header' => [
                'title' => __('Hide the top menu', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'description' =>  __('Hide the PayPlus top menu', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'label' => __('Hide the PayPlus top menu', 'payplus-payment-gateway'),
            ],
            'disable_menu_side' => [
                'title' => __('Hide the side menu', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'description' =>  __('Hide the PayPlus side menu', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'label' => __('Hide the PayPlus side menu', 'payplus-payment-gateway'),
            ],
            'hide_custom_fields_buttons' => [
                'title'   => __('Hide custom fields Delete/Update buttons', 'payplus-payment-gateway'),
                'type'    => 'checkbox',
                'default' => 'yes',
            ],
            'disable_woocommerce_scheduler' => [
                'title' => __('Disable woocommerce scheduler', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'logging' => [
                'title' => __('Logging', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Log debug messages', 'payplus-payment-gateway'),
                'default' => 'yes',
                'custom_attributes' => array('disabled' => 'disabled'),
            ],
        ];
        return $formFields;
    }
}
