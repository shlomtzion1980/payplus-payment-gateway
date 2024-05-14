<?php
if (!defined('ABSPATH')) {
    exit;
}


function payplus_create_table_db()
{
    if (PAYPLUS_VERSION_DB != get_option('payplus_db_version')) {

        payplus_create_table_order();
        payplus_create_table_change_status_order();
        payplus_create_table_log();
        payplus_create_table_payment_session();
        payplus_create_table_process();
        update_option('payplus_db_version', PAYPLUS_VERSION_DB);
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

function payplus_Add_log_payplus($last_error)
{
    $beforeMsg = 'Plugin Version: ' . PAYPLUS_VERSION;
    $logger = wc_get_logger();
    $logger->add('error-db-payplus', $beforeMsg . "\n" . $last_error . "\n" . str_repeat("=", 232));
}
