<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC PayPlus Form Fields and Admin Settings.
 *
 */
class WC_PayPlus_Admin_Settings
{
    public $adminSettings;

    public static function getAdminTabs()
    {
        return $adminTabs = array(
            'payplus-payment-gateway-setup-wizard' => array(
                'name' => __('Basic Settings', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-setup-wizard',
                'img' => "<img src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "payplus-settings.svg'>",
            ),
            'payplus-payment-gateway' => array(
                'name' => __('Settings', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway',
                'img' => "<img src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "PayPlusLogo.svg'>",
            ),
            'payplus-invoice' => array(
                'name' => __('Invoice+', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-invoice',
                'img' => "<img src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "invoice+.png'>",
            ),
            'payplus-express-checkout' => array(
                'name' => __('Express Checkout', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-express-checkout',
                'img' => "<img src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "express.svg'>",
            ),
            'payplus-error-setting' => array(
                'name' => __('Error Page', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-error-setting',
                'img' => "",
            ),
        );
    }

    public static function getAdminSection($settings, $section)
    {
        switch ($section) {
            case 'payplus-payment-gateway-setup-wizard':
                $settings[$section][] = array(
                    'name' => __('PayPlus Basic Settings', 'payplus-payment-gateway') . ' (' . PAYPLUS_VERSION . ')',
                    'type' => 'title',
                    'desc' => __('Simple setup options - The base plugin options. Setup these and you can start working immediately!', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'id' => 'payplus-payment-gateway-setup-wizard'
                );
                $settings[$section][] = [
                    'title' => __('Enable/Disable', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayPlus+ Payment', 'payplus-payment-gateway'),
                    'default' => 'yes',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[enable]'
                ];
                $settings[$section][] = array(
                    'name' => __('API Key', 'payplus-payment-gateway'),
                    'type' => 'text',
                    'desc' => __('PayPlus Api Key you can find in your account under Settings', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'id' => 'woocommerce_payplus-payment-gateway_settings[api_key]'
                );
                $settings[$section][] = [
                    'name' => __('Secret Key', 'payplus-payment-gateway'),
                    'type' => 'text',
                    'desc' => __('PayPlus Secret Key you can find in your account under Settings', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'id' => 'woocommerce_payplus-payment-gateway_settings[secret_key]'
                ];
                $settings[$section][] = [
                    'title' => __('Payment Page UID', 'payplus-payment-gateway'),
                    'type' => 'text',
                    'desc' => __('Your payment page UID can be found under Payment Pages in your side menu in PayPlus account', 'payplus-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                    'id' => 'woocommerce_payplus-payment-gateway_settings[payment_page_id]'
                ];
                $settings[$section][] = [
                    'title' => __('Test mode', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'desc' => __('Enable Test/Sandbox Mode', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'id' => 'woocommerce_payplus-payment-gateway_settings[api_test_mode]'
                ];
                $settings[$section][] =  [
                    'title' => __('Transactions Type', 'payplus-payment-gateway'),
                    'type' => 'select',
                    'options' => [
                        '0' => __('Payment Page Default Setting', 'payplus-payment-gateway'),
                        '1' => __('Charge', 'payplus-payment-gateway'),
                        '2' => __('Authorization', 'payplus-payment-gateway'),
                    ],
                    'default' => '1',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[transaction_type]'
                ];
                $settings[$section][] = [
                    'title' => __('Display Mode', 'payplus-payment-gateway'),
                    'type' => 'select',
                    'options' => [
                        'redirect' => __('Redirect', 'payplus-payment-gateway'),
                        'iframe' => __('iFrame on the next page', 'payplus-payment-gateway'),
                        'samePageIframe' => __('iFrame on the same page', 'payplus-payment-gateway'),
                        'popupIframe' => __('iFrame in a Popup', 'payplus-payment-gateway'),
                    ],
                    'default' => 'redirect',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[display_mode]'
                ];
                $settings[$section][] = [
                    'title' => __('iFrame Height', 'payplus-payment-gateway'),
                    'type' => 'number',
                    'default' => 700,
                    'id' => 'woocommerce_payplus-payment-gateway_settings[iframe_height]'
                ];
                $settings[$section][] = [
                    'title' => __('Allow Saved Credit Cards', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable Payment via Saved Cards', 'payplus-payment-gateway'),
                    'default' => 'no',
                    'desc_tip' => true,
                    'desc' => __('Allow customers to securely save credit card information as tokens for convenient future or recurring purchases.
            <br>Saving cards can be done either during purchase or through the "My Account" section in the website.', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[create_pp_token]'
                ];
                $listOrderStatus = ['default-woo' => __('Default Woo', 'payplus-payment-gateway')];
                $listOrderStatus = array_merge($listOrderStatus, wc_get_order_statuses());
                $settings[$section][] = [
                    'title' => __('Successful Order Status', 'payplus-payment-gateway'),
                    'type' => 'select',
                    'options' => $listOrderStatus,
                    'default' => 'default-woo',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[successful_order_status]'
                ];
                $settings[$section][] = [
                    'title' => __('Payment Completed', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Fire Payment Completed On Successful Charge', 'payplus-payment-gateway'),
                    'desc' => __('Fire Payment Completed On Successful Charge.<br>Only relevant if you are using the "Default Woo" in Successful Order Status option above this one.', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'default' => 'yes',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[fire_completed]'
                ];
                $settings[$section][] = array('type' => 'sectionend', 'id' => 'payplus-payment-gateway-setup-wizard');
                break;
            case 'payplus-error-setting':
                $settings[$section][] = array(
                    'name' => __('PayPlus Page Error - Settings', 'payplus-payment-gateway'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'payplus-error-setting'
                );
                $settings[$section][] = array(
                    'name' => __('Content of the page', 'payplus-payment-gateway') . ":",
                    'id' => 'settings_payplus_page_error_option[post-content]',
                    'type' => 'textarea',
                    'desc' => __('Edit the error page content', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'default' => "העיסקה נכשלה, נא ליצור קשר עם בית העסק\nThe transaction failed, please contact the seller"
                );
                $settings[$section][] = array('type' => 'sectionend', 'id' => 'payplus-error-setting');
                break;
            case 'payplus-invoice':
                $payplus_invoice_option = get_option('payplus_invoice_option');
                $settings[$section][] = array(
                    'name' => __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'payplus-invoice'
                );
                $checked = (isset($payplus_invoice_option['payplus_invoice_enable']) && (
                    $payplus_invoice_option['payplus_invoice_enable'] == "on" || $payplus_invoice_option['payplus_invoice_enable'] == "yes")) ? array('checked' => 'checked') : array();

                $settings[$section][] = array(
                    'name' => __('Enable/Disable', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_enable]',
                    'type' => 'checkbox',
                    'custom_attributes' => $checked,
                );
                $settings[$section][] = array(
                    'name' => __('Display Only - Invoice+ Docs', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[display_only_invoice_docs]',
                    'desc' => __('Only display existing Invoice+ docs without creating or enabling the Invoice+', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'type' => 'checkbox',
                    'default' => 'no',
                );
                $checked = (isset($payplus_invoice_option['payplus_enable_sandbox']) &&
                    $payplus_invoice_option['payplus_enable_sandbox'] == "on") ? array('checked' => 'checked') : array();

                $selectTypeDoc = array(
                    '' => __('Type Documents', 'payplus-payment-gateway'),
                    'inv_tax' => __('Tax Invoice', 'payplus-payment-gateway'),
                    'inv_tax_receipt' => __('Tax Invoice Receipt ', 'payplus-payment-gateway'),
                    'inv_receipt' => __('Receipt', 'payplus-payment-gateway'),
                    'inv_don_receipt' => __('Donation Reciept', 'payplus-payment-gateway')
                );

                $settings[$section][] = array(
                    'name' => __('Enable Sandbox Mode', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_enable_sandbox]',
                    'type' => 'checkbox',
                    'custom_attributes' => $checked,
                );
                $settings[$section][] = array(
                    'name' => __('API Key', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_api_key]',
                    'type' => 'text'
                );

                $settings[$section][] = array(
                    'name' => __('Secret Key', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_secret_key]',
                    'type' => 'text'
                );

                $settings[$section][] = array(
                    'name' => __("Invoice's Language", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_langrage_invoice]',
                    'type' => 'select',
                    'options' => array('he' => 'he', 'en' => 'en'),

                );
                $settings[$section][] = array(
                    'name' => __("Document type for charge transaction", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_type_document]',
                    'type' => 'select',
                    'options' => $selectTypeDoc
                );

                $settings[$section][] = array(
                    'name' => __("Document type for refund transaction", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_type_document_refund]',
                    'type' => 'select',
                    'options' => array(
                        '' => __('Type Documents Refund', 'payplus-payment-gateway'),
                        'inv_refund' => __('Refund Invoice', 'payplus-payment-gateway'),
                        'inv_refund_receipt' => __('Refund Receipt', 'payplus-payment-gateway'),
                        'inv_refund_receipt_invoice' => __('Refund Invoice + Refund Receipt', 'payplus-payment-gateway')
                    )
                );

                $settings[$section][] = array(
                    'name' => __("Order status for issuing an invoice", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_status_order]',
                    'type' => 'select',
                    'options' => self::payplus_get_statuses()
                );

                $checked = (isset($payplus_invoice_option['payplus_invoice_send_document_email']) &&
                    ($payplus_invoice_option['payplus_invoice_send_document_email'] == "on" ||
                        $payplus_invoice_option['payplus_invoice_send_document_email'] == "yes")) ? array('checked' => 'checked') : array();

                $settings[$section][] = array(
                    'name' => __("Send invoice to the customer via e-mail", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_send_document_email]',
                    'type' => 'checkbox',
                    'custom_attributes' => $checked,
                );

                $checked = (isset($payplus_invoice_option['payplus_invoice_send_document_sms']) && (
                    $payplus_invoice_option['payplus_invoice_send_document_sms'] == "on"
                    || $payplus_invoice_option['payplus_invoice_send_document_sms'] == "yes"
                )) ? array('checked' => 'checked') : array();

                $settings[$section][] = array(
                    'name' => __('Send invoice to the customer via Sms (Only If you purchased an SMS package from PayPlus)', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_send_document_sms]',
                    'type' => 'checkbox',
                    'custom_attributes' => $checked,
                );
                $settings[$section][] = array(
                    'name' => __('If you create an invoice in a non-automatic management interface', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[create-invoice-manual]',
                    'type' => 'checkbox',
                    'class' => 'create-invoice-manual',
                );
                $settings[$section][] = array(
                    'name' => __('List of documents that can be produced manually', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[invoice-manual-list]',
                    'type' => 'select',
                    'options' => $selectTypeDoc,
                    'class' => 'invoice-manual-list',
                    'custom_attributes' => array('multiple' => 'multiple'),
                );
                $settings[$section][] = array(
                    'name' => __('', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[list-hidden]',
                    'type' => 'text',
                    'class' => 'list-hidden',

                );
                $settings[$section][] = array(
                    'name' => __('Whether to issue an automatic tax invoice that is paid in cash or by bank transfer', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[create-invoice-automatic]',
                    'type' => 'checkbox',
                );
                $settings[$section][] = array(
                    'name' => __("Brand Uid (Note only if you have more than one site you will need to activate the button)", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_brand_uid]',
                    'type' => 'text',
                    'class' => 'payplus_invoice_brand_uid',
                    'desc' => '<span class="arrow-payplus"></span>',
                );
                $settings[$section][] = array(
                    'name' => __("Website code", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_website_code]',
                    'type' => 'text',
                    'class' => 'payplus_website_code',
                    'desc' => '<span class="arrow-payplus">' . __("Add a unique string here if you have more than one website
                    connected to the service <br> This will create a unique id for invoices to each site (website code must be different for each site!)", 'payplus-payment-gateway') . '</span>',
                );
                $settings[$section][] = array(
                    'name' => __('Logging', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_enable_logging_mode]',
                    'type' => 'checkbox',
                    'custom_attributes' => array('disabled' => 'disabled', 'checked' => 'checked'),
                );
                $settings[$section][] = array(
                    'name' => __('Show Invoice+ metabox in order page', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[dedicated_invoice_metabox]',
                    'type' => 'checkbox',
                    'default' => 'yes',
                );
                $settings[$section][] = array(
                    'name' => __('Don`t add invoice+ links to order notes', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[invoices_notes_no]',
                    'type' => 'checkbox',
                    'default' => 'no',
                );
                $settings[$section][] = array('type' => 'sectionend', 'id' => 'payplus-invoice');
                break;
            case 'payplus-express-checkout':
                $rates = self::getTaxStandards();
                $settings[$section][] = array(
                    'name' => __('Express Checkout', 'payplus-payment-gateway'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'payplus-express-checkout'
                );

                $settings[$section][] = [
                    'name' => __('Google Pay', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[enable_google_pay]',
                    'type' => 'checkbox',
                    'class' => 'enable_google_pay enable_checkout',
                    'desc' => '<div style="color:red" class="error-express-checkout"></div>
                                <div class="loading-express">
                                <div class="spinner-icon"></div>
                                </div>',
                ];

                $settings[$section][] = [
                    'name' => __('Apple Pay', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[enable_apple_pay]',
                    'type' => 'checkbox',
                    'class' => 'enable_apple_pay enable_checkout',
                    'desc' => '<div style="color:red" class="error-express-checkout"></div>
                                <div class="loading-express">
                                <div class="spinner-icon"></div>
                                </div>',
                ];

                $settings[$section][] = [
                    'id' => 'woocommerce_payplus-payment-gateway_settings[apple_pay_identifier]',
                    'name' => __('Token Apple Pay', 'payplus-payment-gateway'),
                    'type' => 'text',
                    'class' => 'apple_pay_identifier',
                    'custom_attributes' => array('readonly' => 'readonly'),
                ];
                $settings[$section][] = [
                    'name' => __('If displayed on a product page ?', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[enable_product]',
                    'type' => 'checkbox'
                ];

                $settings[$section][] = [
                    'name' => __('If you create a new user ?', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[enable_create_user]',
                    'type' => 'checkbox'
                ];
                $settings[$section][] = array(
                    'id' => 'woocommerce_payplus-payment-gateway_settings[shipping_woo]',
                    'name' => __('Shipping according to woocommerce', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'class' => 'shipping_woo',
                    'label' => __('', 'payplus-payment-gateway'),

                );
                $settings[$section][] =
                    array(
                        'id' => 'woocommerce_payplus-payment-gateway_settings[global_shipping]',
                        'name' => __('Global shipping amount', 'payplus-payment-gateway') . ' ( ' . get_woocommerce_currency_symbol() . ' ) ',
                        'type' => 'text',
                        'class' => 'global_shipping',
                        'default' => '0'
                    );

                $settings[$section][] = array(
                    'id' => 'woocommerce_payplus-payment-gateway_settings[global_shipping_tax]',
                    'name' => __('Global shipping Tax', 'payplus-payment-gateway'),
                    'type' => 'select',
                    'options' => array(
                        'taxable' => __('Taxable', 'woocommerce'),
                        'none' => __('None', 'Tax status', 'woocommerce'),
                    ),
                    'class' => 'global_shipping_tax',
                    'default' => 'none'
                );

                if (count($rates)) {
                    $settings[$section][] = array(
                        'id' => 'woocommerce_payplus-payment-gateway_settings[global_shipping_tax_rate]',
                        'name' => __('Select tax rate', 'payplus-payment-gateway'),
                        'type' => 'select',
                        'options' => $rates,
                        'class' => 'global_shipping_tax_rate',
                        'default' => ''
                    );
                }
                $settings[$section][] = array('type' => 'sectionend', 'id' => 'payplus-express-checkout');
                break;
        }
        $settings = isset($settings[$section]) ? $settings[$section] : $settings;
        return $settings;
    }
    /**
     * @return array
     */
    public static function payplus_get_languages()
    {
        require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        $templanguages = wp_get_available_translations();
        $languages = array();
        if (count($templanguages)) {
            $languages = array('' => __('Language of the page', 'payplus-payment-gateway'));
            foreach ($templanguages as $key => $language) {
                $name = $language['native_name'];
                if ($name !== $language['english_name']) {
                    $name .= " - " . $language['english_name'];
                }
                $languages[$key . "-" . $language['english_name']] = $name;
            }
        }
        return $languages;
    }

    /**
     * @return array
     */
    public static function payplus_get_statuses()
    {

        $payplus_invoice_status_order = wc_get_order_statuses();

        if (count($payplus_invoice_status_order)) {
            $statusOrders = array('' => __('Order status for issuing an invoice', 'payplus-payment-gateway'));
            foreach ($payplus_invoice_status_order as $key => $value) {
                $keyValue = str_replace('wc-', '', $key);
                $statusOrders[$keyValue] = $value;
            }
        }
        return $statusOrders;
    }

    /**
     * @return array
     */
    public static function getTaxStandards()
    {
        global $wpdb;
        $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
        $countryDefault = get_option('woocommerce_default_country');
        $options = array();
        $rates = $wpdb->get_results($wpdb->prepare("SELECT tax_rate ,tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country = '%s'", $countryDefault));
        if (count($rates)) {
            $options[0] = __('Select  tax rate', 'payplus-payment-gateway');
            foreach ($rates as $key => $valueRate) {
                $taxRate = round($valueRate->tax_rate, $WC_PayPlus_Gateway->rounding_decimals);
                $options[$taxRate] = $valueRate->tax_rate_name . ' ( ' . $taxRate . ' ) ';
            }
        }
        return $options;
    }
}
