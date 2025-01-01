<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'payplus_register_order_statuses');
/**
 * @return void
 */
function payplus_register_order_statuses()
{
    /* translators: Order status label for recurring subscriptions */
    $messageOrderStatuses = _n_noop('Recurring subscription created <span class="count">(%s)</span>', 'Recurring subscription created<span class="count">(%s)</span>', 'payplus-payment-gateway');
    register_post_status('wc-recsubc', array(
        'label' => _x('Recurring subscription created', 'Order status', 'payplus-payment-gateway'),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => $messageOrderStatuses,
    ));
}
add_filter('wc_order_statuses', 'payplus_wc_order_statuses');
/**
 * @param array $order_statuses
 * @return array
 */
function payplus_wc_order_statuses($order_statuses)
{
    $order_statuses['wc-recsubc'] = _x('Recurring subscription created', 'Order status', 'payplus-payment-gateway');

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

function display_hash_check_notice()
{
    $plugin_dir = PAYPLUS_PLUGIN_DIR;
    $integrity_check_result = verify_plugin_integrity($plugin_dir);
    if ($integrity_check_result !== 'All files are intact.') {
        set_transient('payplus_plugin_integrity_check_failed', $integrity_check_result);
    }
}

function verify_plugin_integrity($plugin_dir)
{
    $trunk_url = 'https://plugins.svn.wordpress.org/payplus-payment-gateway/trunk/';
    $hashes_file_url = $trunk_url . 'hashes.json';

    // Fetch the hashes.json file
    $response = wp_remote_get($hashes_file_url);
    if (is_wp_error($response)) {
        return 'Failed to fetch integrity file.';
    }

    $body = wp_remote_retrieve_body($response);
    $expected_hashes = json_decode($body, true);
    if (!$expected_hashes) {
        return 'Invalid integrity file.';
    }

    $current_hashes = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_dir));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relative_path = str_replace($plugin_dir, '', $file->getPathname());
            $current_hashes[$relative_path] = hash_file('sha256', $file->getPathname());
        }
    }

    foreach ($expected_hashes as $path => $expected_hash) {
        if (!isset($current_hashes[$path]) || $current_hashes[$path] !== $expected_hash) {
            return "File integrity check failed: $path";
        }
    }

    return 'All files are intact.';
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
add_filter('woocommerce_gateway_title', 'change_payment_gateway_title', 100, 2);

function change_payment_gateway_title($title, $payment_id)
{
    $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
    if (is_checkout() && !is_admin() && $WC_PayPlus_Gateway->enable_design_checkout && strpos($payment_id, 'payplus-payment-gateway') !== false) {
        echo
        '<script>
            for(let label of document.querySelectorAll(`label`)){
                label.htmlFor.search(`payplus-payment-gateway`) !== -1 ? label.style.display = "inline-flex" : null;
                label.htmlFor.search(`payplus-payment-gateway`) !== -1 ? label.style.position = "relative" : null;
                label.htmlFor.search(`payment_method_payplus-payment-gateway`) !== -1 ? label.style.top = "9px" : label.style.top = "0px";
            };
        </script>';
        $title = "&nbsp;&nbsp;$title";
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
function payplusUpdateActivate()
{
    $dataBaseVersion = get_option('payplus_db_version');
    if (PAYPLUS_VERSION_DB != $dataBaseVersion) {
        payplus_create_table_order();
        payplus_create_table_process();
        checkSetPayPlusOptions();
        update_option('payplus_db_version', PAYPLUS_VERSION_DB);
    }
}

function check_if_page_exists_by_slug($page_slug)
{
    $page = get_page_by_path($page_slug, OBJECT, 'page');
    $pageId = $page->ID ?? null;
    return $pageId;
}
/**
 * Create the payplus error page
 * @return void
 */
function payplusGenerateErrorPage()
{
    $page_slug = 'error-payment-payplus';
    $errorPageOptions = get_option('settings_payplus_page_error_option') ?? false;
    $pageId = check_if_page_exists_by_slug($page_slug);
    if ($pageId) {
        checkPayPlusErrorPage($errorPageOptions);
        update_option('error_page_payplus', $pageId);
        return;
    } else {
        $error_page_payplus = get_option('error_page_payplus');
        if (!empty($error_page_payplus)) {
            $errorPagePayPlus = get_post($error_page_payplus);
        }
        if (isset($errorPagePayPlus) && !$errorPagePayPlus) {
            $errorPagePayPlus = wp_insert_post(array(
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_title' => ucwords('error payment payplus'),
                "post_content" => "The transaction has failed, please contact the seller."
            ));
            update_option('error_page_payplus', $errorPagePayPlus);
        }
    }
}

/**
 * To get rid of the shortcodes...
 * Check if the payplus error page has to shortcode instead of content if so replace it.
 * 
 * @return void
 */
function checkPayPlusErrorPage($errorPageOptions)
{
    $error_page_payplus = get_option('error_page_payplus') ?? false;

    if ($error_page_payplus) {
        $errorPagePayPlus = get_post($error_page_payplus);
        if ($errorPagePayPlus->post_content !== null && strpos($errorPagePayPlus->post_content, "[error-payplus-content]") === 0 || $errorPagePayPlus->post_content !== null && strpos($errorPagePayPlus->post_content, "[error-payplus-content]") > 0) {
            wp_update_post(array(
                'ID' => $error_page_payplus,
                "post_content" => "The transaction has failed, please contact the seller."
            ));
        }
    }
}

/**
 * Checks if the new options exist and if not adds them // this is version dependant and should be 
 * changed and edited after few version updates. before it becomes redundant!
 * 
 * @return void
 */
function checkSetPayPlusOptions()
{
    $invoiceOptions = !is_bool(get_option('payplus_invoice_option', [])) ? get_option('payplus_invoice_option', []) : [];
    $payPlusOptions = !is_bool(get_option('woocommerce_payplus-payment-gateway_settings', [])) ? get_option('woocommerce_payplus-payment-gateway_settings', []) : [];
    $payPlusErrorOptions = !is_bool(get_option('settings_payplus_page_error_option', [])) ? get_option('settings_payplus_page_error_option', []) : [];

    $newPayPlusOptionsYes = ['hide_custom_fields_buttons', 'show_payplus_data_metabox'];
    $newPayPlusOptionsNo  = ['use_old_fields', 'enable_design_checkout', 'balance_name', 'add_product_field_transaction_type', 'payplus_data_save_order_note'];

    $newInvoicOptionsYes  = ['dedicated_invoice_metabox'];
    $newInvoiceOptionsNo  = ['invoices_notes_no', 'payplus_invoice_enable', 'display_only_invoice_docs'];

    $removeOptions = ['enable'];

    foreach ($removeOptions as $option) {
        if (array_key_exists($option, $payPlusOptions)) {
            unset($payPlusOptions[$option]);
            $savePayPlus = true;
        }
    }
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
    ($saveInvoice ?? false) ? update_option('payplus_invoice_option', $invoiceOptions) : null;

    foreach ($newPayPlusOptionsYes as $option) {
        if (!array_key_exists($option, $payPlusOptions)) {
            $payPlusOptions[$option] = 'yes';
            $savePayPlus = true;
        }
    }
    foreach ($newPayPlusOptionsNo as $option) {
        if (!array_key_exists($option, $payPlusOptions)) {
            $payPlusOptions[$option] = 'no';
            $savePayPlus = true;
        }
    }
    ($savePayPlus ?? false) ? update_option('woocommerce_payplus-payment-gateway_settings', $payPlusOptions) : null;

    if (!array_key_exists('post-content', $payPlusErrorOptions)) {
        $payPlusErrorOptions['post-content'] = "The transaction failed, please contact the seller.";
        update_option('settings_payplus_page_error_option', $payPlusErrorOptions);
    }
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

/**
 * @return void
 */
function payplus_check_table_exist_db($nameTable)
{
    global $wpdb;

    $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($nameTable)));
    return ($result == $nameTable);
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
    global $wp_filesystem;

    // Initialize the WordPress filesystem
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    // Setup the filesystem if it's not already initialized
    if (!WP_Filesystem()) {
        return false;
    }

    $sourceFile = PAYPLUS_SRC_FILE_APPLE . '/' . PAYPLUS_APPLE_FILE;
    $destinationFile = PAYPLUS_DEST_FILE_APPLE . '/' . PAYPLUS_APPLE_FILE;

    // Check if destination file exists
    if (file_exists($destinationFile)) {
        return true; // File already exists, nothing to do
    }

    // Ensure source file exists
    if (!file_exists($sourceFile)) {
        return false; // Source file does not exist
    }

    // Ensure destination directory exists
    WP_Filesystem();
    global $wp_filesystem;

    if (!$wp_filesystem->is_dir(PAYPLUS_DEST_FILE_APPLE)) {
        if (!$wp_filesystem->mkdir(PAYPLUS_DEST_FILE_APPLE, 0777)) {
            return false; // Failed to create destination directory
        }
    }

    // Copy the file using WordPress filesystem API
    if ($wp_filesystem->copy($sourceFile, $destinationFile, true)) {
        return true; // File copied successfully
    } else {
        return false; // Failed to copy the file
    }
}


function payplus_Add_log_payplus($last_error)
{
    $beforeMsg = 'Plugin Version: ' . PAYPLUS_VERSION;
    $logger = wc_get_logger();
    $logger->add('error-db-payplus', $beforeMsg . "\n" . $last_error . "\n" . str_repeat("=", 232));
}

add_filter('woocommerce_price_trim_zeros', '__return_true');
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
                    'value' => WC_PayPlus_Meta_Data::get_meta($theorder->get_id(), '_billing_vat_number', true),
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

add_action('woocommerce_after_checkout_billing', 'add_vat_number_nonce_field');
function add_vat_number_nonce_field()
{
    wp_nonce_field('vat_number_nonce_action', 'vat_number_nonce');
}


add_action('woocommerce_process_shop_order_meta', 'payplus_checkout_field_update_order_meta', 10,);
function payplus_checkout_field_update_order_meta($order_id)
{
    if (!isset($_POST['vat_number_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vat_number_nonce'])), 'vat_number_nonce_action')) {
        return;
    }

    if (isset($_POST['_billing_vat_number'])) {
        update_post_meta($order_id, '_billing_vat_number', sanitize_text_field(wp_unslash($_POST['_billing_vat_number'])));
    }
}

function payplus_cron_deactivate()
{
    $timestamp = wp_next_scheduled('payplus_hourly_cron_job');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'payplus_hourly_cron_job');
    }
}
