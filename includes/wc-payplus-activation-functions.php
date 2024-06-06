<?php
if (!defined('ABSPATH')) {
    exit;
}


/**
 * @param $element
 * @return void
 */
function print_db($element)
{
    echo "<pre style='direction: ltr;'>";
    var_dump($element);
    echo "</pre>";
    echo "\n";
}

add_action('init', 'payplus_register_order_statuses');
/**
 * @return void
 */
function payplus_register_order_statuses()
{
    register_post_status('wc-recsubc', array(
        'label' => _x('Recurring subscription created', 'Order status', 'woocommerce'),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Recurring subscription created <span class="count">(%s)</span>', 'Recurring subscription created<span class="count">(%s)</span>', 'woocommerce'),
    ));
}
add_filter('wc_order_statuses', 'payplus_wc_order_statuses');
/**
 * @param array $order_statuses
 * @return array
 */
function payplus_wc_order_statuses($order_statuses)
{
    $order_statuses['wc-recsubc'] = _x('Recurring subscription created', 'Order status', 'woocommerce');

    return $order_statuses;
}
add_filter('handle_bulk_actions-edit-shop_order', 'payplus_orders_bulk_actions', 10, 3);
/**
 * @param $redirect_to
 * @param $action
 * @param $post_ids
 * @return mixed
 */
function payplus_orders_bulk_actions($redirect_to, $action, $post_ids)
{
    $invoice = new PayplusInvoice();
    $statusOrder = $invoice->payplus_get_invoice_status_order();

    $pos = strpos($action, "mark");

    if ($pos !== false) {
        $postStr = get_option('payplus_create_invoice');
        if (!$postStr) {
            $postStr = "";
        }
        $action = explode("_", $action);
        if ($action[1] == $statusOrder) {
            if ($invoice->payplus_get_invoice_enable()) {
                if (count($post_ids)) {
                    foreach ($post_ids as $key => $value) {
                        $postStr .= $value . ",";
                    }
                }
            }
        }
        update_option('payplus_create_invoice', $postStr);
    }
    return $redirect_to;
}

add_action('payplus_cron_send_order', function () {
    $invoice = new PayplusInvoice();

    if ($invoice->payplus_get_invoice_enable()) {
        $orders = get_option('payplus_create_invoice');
        $orders = explode(",", $orders);
        if (count($orders)) {
            foreach ($orders as $key => $order) {
                if (!empty($order)) {
                    $currentOrder = wc_get_order($order);
                    if ($invoice->payplus_get_invoice_status_order() == $currentOrder->get_status()) {
                        $invoice->payplus_invoice_create_order($order);
                    }
                }
            }
        }
        delete_option('payplus_create_invoice');
    }
});
/**
 * @param string $title
 * @param string $payment_id
 * @return string
 */
add_filter('woocommerce_gateway_title', 'change_cheque_payment_gateway_title', 100, 2);

function change_cheque_payment_gateway_title($title, $payment_id)
{
    $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
    if (is_checkout() && !is_admin() && $WC_PayPlus_Gateway->enable_design_checkout && strpos($payment_id, 'payplus-payment-gateway') !== false) {

        $title = "<span>" . $title . "</span>";
    }
    return $title;
}
/**
 * @return bool
 */
function payplus_check_woocommerce_custom_orders_table_enabled()
{
    return (get_option('woocommerce_custom_orders_table_enabled')) == "yes" ? true : false;
}

/**
 * @param $order
 * @return float | array
 */
function payplus_woocommerce_get_tax_rates($order)
{
    $rates = array();
    foreach ($order->get_items('tax') as $item_id => $item) {
        $tax_rate_id = $item->get_rate_id();
        $rates[] = WC_Tax::get_rate_percent_value($tax_rate_id);
    }
    if (count($rates) == 1) {
        return $rates[0];
    }
    return $rates;
}
function payplus_create_table_db()
{

    if (PAYPLUS_VERSION_DB != get_option('payplus_db_version')) {

        payplus_create_table_order();
        payplus_create_table_change_status_order();
        payplus_create_table_log();
        payplus_create_table_payment_session();
        payplus_create_table_process();
        check_payplus_options();
        update_option('payplus_db_version', PAYPLUS_VERSION_DB);
    }
}


/**
 * Checks if the new options exist and if not adds them // this is version dependant and should be 
 * changed and edited after few version updates. before it becomes redundant!
 * 
 * @return void
 */
function check_payplus_options()
{
    $invoiceOptions = get_option('payplus_invoice_option', []);
    $payPlusOptions = get_option('woocommerce_payplus-payment-gateway_settings', []);
    $newPayPlusOptionsYes = ['use_old_fields', 'enable_design_checkout', 'balance_name', 'add_product_field_transaction_type'];
    $newPayPlusOptionsNo = ['enable_design_checkout', 'balance_name', 'add_product_field_transaction_type'];
    $newInvoicOptionsYes = ['dedicated_invoice_metabox'];
    $newInvoiceOptionsNo = ['invoices_notes_no', 'payplus_invoice_enable', 'display_only_invoice_docs'];
    $saveInvoice = false;
    $savePayPlus = false;

    foreach ($newInvoicOptionsYes as $option) {
        if (!array_key_exists($option, $invoiceOptions)) {
            $invoiceOptions[$option] = 'yes';
            $saveInvoice = true;
        }
    }
    foreach ($newInvoiceOptionsNo as $option) {
        if (!array_key_exists($option, $invoiceOptions)) {
            $invoiceOptions[$option] = 'no';
            $saveInvoice = true;
        }
    }
    $saveInvoice ? update_option('payplus_invoice_option', $invoiceOptions) : null;

    foreach ($newPayPlusOptionsYes as $option) {
        if (!array_key_exists($option, $payPlusOptions) || PAYPLUS_VERSION_DB === 'payplus_2_1') {
            $payPlusOptions[$option] = 'yes';
            $savePayPlus = true;
        }
    }
    foreach ($newPayPlusOptionsNo as $option) {
        if (!array_key_exists($option, $payPlusOptions) || PAYPLUS_VERSION_DB === 'payplus_2_1') {
            $payPlusOptions[$option] = 'yes';
            $savePayPlus = true;
        }
    }
    $savePayPlus ? update_option('woocommerce_payplus-payment-gateway_settings', $payPlusOptions) : null;
}


/**
 * @return void
 */

function payplus_create_table_order()
{

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'payplus_order';

    $sql = "CREATE TABLE " . $table_name . " (
                id  int(11) NOT NULL AUTO_INCREMENT,
                create_at timestamp  default CURRENT_TIMESTAMP,
                order_id BIGINT NOT NULL,
                parent_id int(11) DEFAULT  0,
                transaction_uid varchar(255) DEFAULT NULL,
                method_payment  varchar(255) DEFAULT NULL,
                page_request_uid  varchar(255) DEFAULT NULL,
                four_digits varchar(4) DEFAULT NULL,
                number_of_payments int(11) DEFAULT  0,
                brand_name varchar(255) DEFAULT NULL,
                approval_num varchar(255) DEFAULT NULL,
                alternative_method_name varchar(20) DEFAULT NULL,
                type_payment  varchar(255) DEFAULT NULL ,
                token_uid varchar(255) DEFAULT NULL,
                price   int(11) DEFAULT  0,
                refund   int(11) DEFAULT  0,
                payplus_response  LONGTEXT  DEFAULT NULL,
                related_transactions int(11) DEFAULT  0 ,
                delete_at  int(11) DEFAULT  0 ,
                status_code  varchar(255) DEFAULT NULL ,
                invoice_refund int(11) DEFAULT  0,
                first_payment  int(11) DEFAULT  0,
                subsequent_payments int(11) DEFAULT  0,
                transaction_type varchar(255) DEFAULT NULL ,
                notes  LONGTEXT DEFAULT NULL,
                account_number  varchar(255) DEFAULT NULL,
                branch_number  varchar(255) DEFAULT NULL,
                 bank_number  varchar(255) DEFAULT NULL,
                 check_number  varchar(255) DEFAULT NULL,
                 transaction_id  varchar(255) DEFAULT NULL,
                 payer_account  varchar(255) DEFAULT NULL,
                PRIMARY KEY  (id)
                ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    if ($wpdb->last_error) {

        payplus_Add_log_payplus($wpdb->last_error);
    }
}

/**
 * @return void
 */
function payplus_create_table_change_status_order()
{

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'payplus_order_status';

    $sql = "CREATE TABLE " . $table_name . " (
                id  BIGINT NOT NULL AUTO_INCREMENT,
                order_id BIGINT NOT NULL,
                create_at timestamp  default CURRENT_TIMESTAMP,
                update_at  datetime DEFAULT NULL,
                create_at_refURL_success datetime  DEFAULT NULL,
                create_at_refURL_callback datetime  DEFAULT NULL,
                status  varchar(255) DEFAULT NULL,
                PRIMARY KEY  (id)
                ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    if ($wpdb->last_error) {
        payplus_Add_log_payplus($wpdb->last_error);
    }
}

function payplus_create_table_log()
{

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'payplus_order_log';

    $sql = "CREATE TABLE " . $table_name . " (
                id  int(11) NOT NULL AUTO_INCREMENT,
                order_id BIGINT NOT NULL,
                create_at timestamp  default CURRENT_TIMESTAMP,
                action_name varchar(255)  DEFAULT NULL ,
                status_transition_from varchar(255)  DEFAULT NULL ,
                status_transition_to varchar(255)  DEFAULT NULL ,
                log  text  DEFAULT NULL ,
                PRIMARY KEY  (id)
                ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    if ($wpdb->last_error) {
        payplus_Add_log_payplus($wpdb->last_error);
    }
}
/**
 * @return void
 */

function payplus_check_table_exist_db($nameTable)
{

    global $wpdb;
    if ($wpdb->get_var("show tables like '$nameTable'") != $nameTable) {
        return false;
    }
    return true;
}
function payplus_create_table_payment_session()
{
    global $wpdb;
    $tblname = $wpdb->prefix . PAYPLUS_TABLE_SESSION;
    $charset_collate = $wpdb->get_charset_collate();
    if (!payplus_check_table_exist_db($tblname)) {

        $sql = "CREATE TABLE $tblname (
                  id int(11) NOT NULL AUTO_INCREMENT,
                  payplus_date date NOT NULL,
                  payplus_created datetime NOT NULL,
                  payplus_update datetime NOT NULL,
                  payplus_ip text NOT NULL,
                  payplus_order int(11) NULL,
                  payplus_amount int(11) NULL,
                   payplus_status int(11) DEFAULT  1,
                  PRIMARY KEY (id)
                ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        if ($wpdb->last_error) {
            payplus_Add_log_payplus($wpdb->last_error);
        }
    }
}
/**
 * @return void
 */
function payplus_create_table_process()
{
    global $wpdb;
    $tblname = 'payplus_payment_process';
    $payplus_table = $wpdb->prefix . $tblname;
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $payplus_table (
                  id int(11) NOT NULL AUTO_INCREMENT,
                  order_id BIGINT NOT NULL,
                  create_at timestamp  default CURRENT_TIMESTAMP,
                  function_begin  varchar(255)  DEFAULT NULL ,
                  status_code  varchar(255)  DEFAULT NULL ,
                  count_process int(11) NOT NULL,
                  function_end varchar(255)  DEFAULT NULL ,
                  PRIMARY KEY (id)
                ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    if ($wpdb->last_error) {
        payplus_Add_log_payplus($wpdb->last_error);
    }
}
/**
 * @return bool|void
 */
function payplus_add_file_ApplePay()
{

    $sourceFile = PAYPLUS_SRC_FILE_APPLE . '/' . PAYPLUS_APPLE_FILE;
    $destinationFile = PAYPLUS_DEST_FILE_APPLE . '/' . PAYPLUS_APPLE_FILE;

    if (!file_exists($destinationFile)) {
        if (file_exists($sourceFile)) {
            if (!is_dir(PAYPLUS_DEST_FILE_APPLE)) {
                wp_mkdir_p(PAYPLUS_DEST_FILE_APPLE);
                chmod(PAYPLUS_DEST_FILE_APPLE, 0777);
            }
            if (!file_exists($destinationFile)) {
                if (copy($sourceFile, $destinationFile)) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    } else {
        return true;
    }
}

function payplus_Add_log_payplus($last_error)
{
    $beforeMsg = 'Plugin Version: ' . PAYPLUS_VERSION;
    $logger = wc_get_logger();
    $logger->add('error-db-payplus', $beforeMsg . "\n" . $last_error . "\n" . str_repeat("=", 232));
}


add_filter('woocommerce_price_trim_zeros', '__return_true');
add_filter('acf/settings/remove_wp_meta_box', '__return_false');
add_filter('woocommerce_admin_billing_fields', 'payplus_order_admin_custom_fields');

function payplus_order_admin_custom_fields($fields)
{
    global $theorder;

    if ($theorder) {
        $sorted_fields = [];
        foreach ($fields as $key => $values) {
            if ($key === 'company') {
                $sorted_fields[$key] = $values;
                $sorted_fields['vat_number'] = array(
                    'label' => __('ID \ VAT Number', 'payplus-payment-gateway'),
                    'value' => WC_PayPlus_Order_Data::get_meta($theorder->get_id(), '_billing_vat_number', true),
                    'show' => true,
                    'wrapper_class' => 'form-field-wide',
                    'position ' => 1,
                    'style' => '',
                );
            } else {
                $sorted_fields[$key] = $values;
            }
        }
        return $sorted_fields;
    }
    return $fields;
}

add_action('woocommerce_process_shop_order_meta', 'payplus_checkout_field_update_order_meta', 10,);
function payplus_checkout_field_update_order_meta($order_id)
{

    if (isset($_POST['_billing_vat_number'])) {
        update_post_meta($order_id, '_billing_vat_number', sanitize_text_field($_POST['_billing_vat_number']));
    }
}
