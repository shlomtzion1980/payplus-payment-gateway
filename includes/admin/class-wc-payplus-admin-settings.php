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
            // 'payplus-payment-gateway-setup-wizard' => array(
            //     'name' => __('Basic Settings', 'payplus-payment-gateway'),
            //     'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-setup-wizard',
            //     'img' => "<img style='height: 75%' src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "payPlusSettings.svg'>",
            // ),
            'payplus-payment-gateway' => array(
                'name' => __('Settings', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway',
                'img' => "<img src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "PayPlusLogo.svg'>",
            ),
            'payplus-invoice' => array(
                'name' => __('Settings', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-invoice',
                'img' => "<img style='height: 75%;' src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "Invoice+logo.png'>",
            ),
            'payplus-express-checkout' => array(
                'name' => __('Express Checkout', 'payplus-payment-gateway'),
                'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-express-checkout',
                'img' => "<img src='" . PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "express.svg'>",
            ),
            // 'payplus-error-setting' => array(
            //     'name' => __('Error Page', 'payplus-payment-gateway'),
            //     'link' => get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=payplus-error-setting',
            //     'img' => "",
            // ),
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
                    'id' => 'woocommerce_payplus-payment-gateway_settings[enabled]'
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
                $settings[$section][] = [
                    'title' => __('Transaction data in order notes', 'payplus-payment-gateway'),
                    'desc' => __('Save PayPlus transaction data to the order notes', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'desc_tip' => true,
                    'id' => 'woocommerce_payplus-payment-gateway_settings[payplus_data_save_order_note]'
                ];
                $settings[$section][] = [
                    'title' => __('Show PayPlus Metabox', 'payplus-payment-gateway'),
                    'desc' => __('Show the transaction data in the PayPlus dedicated metabox', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => true,
                    'id' => 'woocommerce_payplus-payment-gateway_settings[show_payplus_data_metabox]'
                ];
                $settings[$section][] = array('type' => 'sectionend', 'id' => 'payplus-payment-gateway-setup-wizard');
                break;
            case 'payplus-error-setting':
                $settings[$section][] = array('type' => 'sectionend', 'id' => 'payplus-error-setting');
                break;
            case 'payplus-payment-gateway-multipass':
                $settings[$section][] = array(
                    'name' => '',
                    'type' => 'title',
                    'id' => 'payplus-payment-gateway-multipass[clubsTitle]'
                );
                $settings[$section][] = array(
                    'name' => esc_html__('Select your clubs:', 'payplus-payment-gateway'),
                    'id' => 'payplus-payment-gateway-multipass[clubs]',
                    'desc' => __("Choose clubs to show their icon on the checkout page.", 'payplus-payment-gateway'),
                    'type' => 'multiselect',
                    'class' => 'myClubs',
                    'options' => [
                        'buyme' => esc_html__('BuyMe', 'payplus-payment-gateway'),
                        'rami-levy' => esc_html__('Rami Levy', 'payplus-payment-gateway'),
                        'yenot-bitan' => esc_html__('Yenot Bitan', 'payplus-payment-gateway'),
                        'hitech-zone' => esc_html__('Hitechzone', 'payplus-payment-gateway'),
                        'jd-club' => esc_html__('JD Club', 'payplus-payment-gateway'),
                        'club-911' => esc_html__('911 Club', 'payplus-payment-gateway'),
                        'forever-21' => esc_html__('Forever 21', 'payplus-payment-gateway'),
                        'dolce-vita' => esc_html__('Dolce Vita', 'payplus-payment-gateway'),
                        'dolce-vita-moadon-shelra' => esc_html__('Dolce Vita - Shelra', 'payplus-payment-gateway'),
                        'payis-plus-moadon-mifal-hapayis' => esc_html__('Mifal Hapayis', 'payplus-payment-gateway'),
                        'gold-tsafon' => esc_html__('Gold Tsafon', 'payplus-payment-gateway'),
                        'dolce-vita-i-student' => esc_html__('iStudent', 'payplus-payment-gateway'),
                        'yerushalmi' => esc_html__('Yerushalmi', 'payplus-payment-gateway'),
                        'dolce-vita-cal' => esc_html__('Dolce Vita - Cal', 'payplus-payment-gateway'),
                        'extra-bonus-tapuznet' => esc_html__('Extra Bonus', 'payplus-payment-gateway'),
                        'bituah-yashir' => esc_html__('Dolce Vita - Bituah Yashir', 'payplus-payment-gateway'),
                        'nofshonit' => esc_html__('Nofshonit', 'payplus-payment-gateway'),
                        'ksharim-plus' => esc_html__('Happy Gift', 'payplus-payment-gateway'),
                        'swagg' => esc_html__('Swagg', 'payplus-payment-gateway'),
                        'raayonit' => esc_html__('Raayonit', 'payplus-payment-gateway'),
                        'share-spa' => esc_html__('Share Spa', 'payplus-payment-gateway'),
                        'gifta' => esc_html__('Gifta', 'payplus-payment-gateway'),
                        'mega-lean' => esc_html__('MegaLean', 'payplus-payment-gateway'),
                    ],
                );
                $settings[$section][] = array('type' => 'sectionend', 'id' => 'payplus-payment-gateway-multipass');
                break;
            case 'payplus-invoice':
                $payplus_invoice_option = get_option('payplus_invoice_option');
                $payplus_gateway_option = get_option('woocommerce_payplus-payment-gateway_settings');

                $settings[$section][] = array(
                    'name' => __('Invoice+ (PayPlus)', 'payplus-payment-gateway'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'payplus-invoice+'
                );
                $checked = (isset($payplus_invoice_option['payplus_invoice_enable']) && (
                    $payplus_invoice_option['payplus_invoice_enable'] == "on" || $payplus_invoice_option['payplus_invoice_enable'] == "yes")) ? array('checked' => 'checked') : array();

                $settings[$section][] = array(
                    'name' => __('Check this to activate Invoice+', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_enable]',
                    'type' => 'checkbox',
                    'custom_attributes' => $checked,
                    'desc' => __('Enable/Disable', 'payplus-payment-gateway'),
                    'desc_tip' => true
                );
                $settings[$section][] = array(
                    'name' => __('Display Only - Invoice+ Docs', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[display_only_invoice_docs]',
                    'desc' => __('Only display existing Invoice+ docs without creating or enabling the Invoice+', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'type' => 'checkbox',
                    'default' => 'no',
                );

                $checked = (isset($payplus_gateway_option['api_test_mode']) &&
                    $payplus_gateway_option['api_test_mode'] == "on") ? array('checked' => 'checked') : array();

                $selectTypeDoc = array(
                    '' => __('Type Documents', 'payplus-payment-gateway'),
                    'inv_tax' => __('Tax Invoice', 'payplus-payment-gateway'),
                    'inv_tax_receipt' => __('Tax Invoice Receipt ', 'payplus-payment-gateway'),
                    'inv_receipt' => __('Receipt', 'payplus-payment-gateway'),
                    'inv_don_receipt' => __('Donation Reciept', 'payplus-payment-gateway')
                );
                $settings[$section][] = array(
                    'name' => __("Invoice's Language", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_langrage_invoice]',
                    'type' => 'select',
                    'options' => array('he' => 'HE', 'en' => 'EN'),
                    'class' => 'payplus-languages-class',
                );
                $settings[$section][] = array(
                    'name' => __("Website code", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_website_code]',
                    'type' => 'text',
                    'class' => 'payplus_website_code',
                    'desc' => '<span class="arrow-payplus">' . __("Add a unique string here if you have more than one website
                    connected to the service <br> This will create a unique id for invoices to each site (website code must be different for each site!)", 'payplus-payment-gateway') . '</span>',
                    'desc_tip' => true,
                    'class' => 'payplus-documents'
                );
                $settings[$section][] = array(
                    'name' => __("Brand UID", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_brand_uid]',
                    'type' => 'text',
                    'class' => 'payplus_invoice_brand_uid',
                    'desc' => __('Set brand UID from which the system will issue the documents (Leave blank if you only have one brand)', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'class' => 'payplus-documents'
                );
                $settings[$section][] = array(
                    'name' => __("Brand UID - Development/Sandbox", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_brand_uid_sandbox]',
                    'type' => 'text',
                    'class' => 'payplus_invoice_brand_uid',
                    'desc' => __('Set development - brand UID from which the system will issue the documents (Leave blank if you only have one brand)', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'class' => 'payplus-documents'
                );
                $settings[$section][] = array(
                    'name' => __("Document type for charge transaction", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_type_document]',
                    'type' => 'select',
                    'options' => $selectTypeDoc,
                    'class' => 'payplus-documents'
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
                    ),
                    'class' => 'payplus-documents'
                );

                $settings[$section][] = array(
                    'name' => __("Order status for issuing an invoice", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_status_order]',
                    'type' => 'select',
                    'options' => self::payplus_get_statuses(),
                    'class' => 'payplus-documents'
                );

                $checked = (isset($payplus_invoice_option['payplus_invoice_send_document_email']) &&
                    ($payplus_invoice_option['payplus_invoice_send_document_email'] == "on" ||
                        $payplus_invoice_option['payplus_invoice_send_document_email'] == "yes")) ? array('checked' => 'checked') : array();

                $settings[$section][] = array(
                    'name' => __("Send invoice to the customer via e-mail", 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[payplus_invoice_send_document_email]',
                    'type' => 'checkbox',
                    'custom_attributes' => $checked,
                    'class' => 'payplus-notifications'
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
                    'class' => 'payplus-notifications'
                );
                $settings[$section][] = array(
                    'name' => __('Invoice Creation Mode:', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[create-invoice-manual]',
                    'type' => 'select',
                    'options' => ['no' => __('Automatic', 'payplus-payment-gateway'), 'yes' => __('Manual', 'payplus-payment-gateway')],
                    'class' => 'create-invoice-manual',
                    'desc' => __('Invoice creation: Automatic(Default) or Manual', 'payplus-payment-gateway'),
                    'default' => 'no',
                    'desc_tip' => true
                );
                $settings[$section][] = [
                    'name' => __('Allow amount change', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => true,
                    'label' => __('Transaction amount change', 'payplus-payment-gateway'),
                    'desc' => __('Choose this to be able to charge a different amount higher/lower than the order total (A number field will appear beside the "Make Paymet" button)', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[check_amount_authorization]',
                ];
                $settings[$section][] = array(
                    'name' => __('Select the types you want available for docs creation', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[invoice-manual-list]',
                    'type' => 'select',
                    'options' => $selectTypeDoc,
                    'class' => 'invoice-manual-list',
                    'desc' => __('Choose from mulitple types of documents to be available in the order page.', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'custom_attributes' => array('multiple' => 'multiple'),
                );
                $settings[$section][] = array(
                    'name' => '',
                    'id' => 'payplus_invoice_option[list-hidden]',
                    'type' => 'text',
                    'class' => 'list-hidden',
                );
                $settings[$section][] = array(
                    'name' => __('Issue automatic tax invoice for orders paid in cash or bank transfers', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[create-invoice-automatic]',
                    'desc' => __('This overrides the default setting for automatic documents created for an order and is applicable only for "cod - cash on delivery" or "bacs - bank transfer" payment.', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'type' => 'checkbox',
                    'class' => 'payplus-documents'
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
                    'class' => 'payplus-display'
                );
                $settings[$section][] = array(
                    'name' => __('Don`t add invoice+ links to order notes', 'payplus-payment-gateway'),
                    'id' => 'payplus_invoice_option[invoices_notes_no]',
                    'type' => 'checkbox',
                    'default' => 'no',
                    'class' => 'payplus-display'
                );
                // $settings[$section][] = [
                //     'title' => __('Show pick up  method on invoice', 'payplus-payment-gateway'),
                //     'type' => 'checkbox',
                //     'label' => '',
                //     'default' => 'no',
                //     'id' => 'woocommerce_payplus-payment-gateway_settings[is_Local_pickup]',
                //     'class' => 'payplus-documents'
                // ];
                $settings[$section][] = [
                    'title' => __('Every order is subject to VAT', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[paying_vat_all_order]',
                    'class' => 'payplus-vat',
                ];
                $settings[$section][] = [
                    'title' => __('VAT change in Eilat', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => '',
                    'default' => 'no',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[change_vat_in_eilat]',
                    'class' => 'payplus-vat',
                ];
                $settings[$section][] = [
                    'title' => __('Keywords for deliveries in Eilat', 'payplus-payment-gateway'),
                    'type' => 'text',
                    'default' => 'אילת,איילת,אלת,eilat,elat,EILAT,ELAT',
                    'description' => __('Keywords must be separated with a comma', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[keywords_eilat]',
                    'class' => 'payplus-vat'
                ];
                $settings[$section][] = [
                    'title' => __('Hide products from Invoice+ docs', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Use "General Product" invoice+ documents', 'payplus-payment-gateway'),
                    'desc' => __('Send all items as: "General Product" in Invoice+ document creation.', 'payplus-payment-gateway'),
                    'desc_tip' => true,
                    'id' => 'payplus_invoice_option[hide_products_invoice]',
                    'class' => 'payplus-documents'
                ];
                $settings[$section][] = [
                    'title' => __('Add product variations data to the invoice', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'desc' => __('Display product variations metadata (If applicable) in a new line.', 'payplus-payment-gateway'),
                    'default' => 'yes',
                    'desc_tip' => true,
                    'id' => 'woocommerce_payplus-payment-gateway_settings[send_variations]',
                    'class' => 'payplus-documents'
                ];
                $settings[$section][] = [
                    'title' => __('Calculate VAT According to:', 'payplus-payment-gateway'),
                    'type' => 'select',
                    'options' => [
                        '0' => __('Payment Page Default Setting', 'payplus-payment-gateway'),
                        '1' => __('PayPlus', 'payplus-payment-gateway'),
                        '2' => __('WooCommerce', 'payplus-payment-gateway'),
                    ],
                    'default' => '0',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[initial_invoice]',
                    'class' => 'payplus-vat'
                ];
                $settings[$section][] = [
                    'title' => __('Invoice For Foreign Customers', 'payplus-payment-gateway'),
                    'type' => 'select',
                    'options' => [
                        '0' => __('Paying VAT', 'payplus-payment-gateway'),
                        '1' => __('Exempt VAT', 'payplus-payment-gateway'),
                        '2' => __('Exempt VAT If Customer Billing ISO Country Is Different Than...', 'payplus-payment-gateway'),
                    ],
                    'default' => '0',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[paying_vat]',
                    'class' => 'payplus-vat',
                ];
                $settings[$section][] = [
                    'title' => __('Your Business VAT Registration Country ISO Code', 'payplus-payment-gateway'),
                    'type' => 'text',
                    'default' => 'IL',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[paying_vat_iso_code]',
                    'class' => 'payplus-vat',
                ];
                $settings[$section][] = [
                    'title' => __('Custom checkout field name for vat number', 'payplus-payment-gateway'),
                    'type' => 'text',
                    'default' => '',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[vat_number_field]',
                    'class' => 'payplus-vat'
                ];
                $settings[$section][] = [
                    'title' => __('The language of the invoices or documents issued to foreign customers (assuming your invoicing company supports this language)', 'payplus-payment-gateway'),
                    'type' => 'text',
                    'default' => 'HE',
                    'id' => 'woocommerce_payplus-payment-gateway_settings[foreign_invoices_lang]',
                    'class' => 'payplus-languages-class',
                ];
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
                    'name' => __('Allow customers to create an account during checkout', 'payplus-payment-gateway'),
                    'id' => 'woocommerce_payplus-payment-gateway_settings[enable_create_user]',
                    'type' => 'checkbox'
                ];
                $settings[$section][] = array(
                    'id' => 'woocommerce_payplus-payment-gateway_settings[shipping_woo]',
                    'name' => __('Shipping according to woocommerce', 'payplus-payment-gateway'),
                    'type' => 'checkbox',
                    'class' => 'shipping_woo',
                    'label' => '',

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
                        'taxable' => __('Taxable', 'payplus-payment-gateway'),
                        'none' => __('None', 'payplus-payment-gateway'),
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
        $options = array();

        $countryDefault = sanitize_text_field(get_option('woocommerce_default_country'));

        $tax = new WC_Tax();
        $tax_classes = $tax->get_tax_classes();
        $tax_rates = $tax->get_rates($countryDefault);

        if ($tax_rates) {
            $options[0] = esc_html__('Select tax rate', 'payplus-payment-gateway');

            foreach ($tax_rates as $tax_rate) {
                $taxRate = round(floatval($tax_rate['rate']), 2); // Adjust rounding as needed
                $taxRateName = sanitize_text_field($tax_rate['label']);

                $options[$taxRate] = sprintf('%s ( %d )', $taxRateName, $taxRate);
            }
        }
        return $options;
    }
}
