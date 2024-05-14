<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.Files.FileName

/**
 * Abstract class that will be inherited by all payment methods.
 *
 * @extends WC_Payment_Gateway_CC
 *
 * @since 4.0.0
 */
abstract class WC_PayPlus_Payment_Gateway extends WC_Payment_Gateway_CC
{

    static function PayPlusInit()
    {

        add_action('woocommerce_process_shop_order_meta', 'payplus_checkout_field_update_order_meta', 10,);
        function payplus_checkout_field_update_order_meta($order_id)
        {
            if (isset($_POST['_billing_vat_number'])) {
                update_post_meta($order_id, '_billing_vat_number', sanitize_text_field($_POST['_billing_vat_number']));
            }
        }

        add_action('init', 'payplus_register_order_statuses');

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

        add_filter('wc_order_statuses', 'payplus_wc_order_statuses');

        add_filter('woocommerce_gateway_title', 'change_cheque_payment_gateway_title', 100, 2);
        /**
         * @param string $title
         * @param string $payment_id
         * @return string
         */

        function change_cheque_payment_gateway_title($title, $payment_id)
        {
            $WC_PayPlus_Gateway = new WC_PayPlus_Gateway();
            if (is_checkout() && !is_admin() && $WC_PayPlus_Gateway->enable_design_checkout && strpos($payment_id, 'payplus-payment-gateway') !== false) {

                $title = "<span>" . $title . "</span>";
            }
            return $title;
        }

        /**
         * @param array $order_statuses
         * @return array
         */
        function payplus_wc_order_statuses($order_statuses)
        {
            $order_statuses['wc-recsubc'] = _x('Recurring subscription created', 'Order status', 'woocommerce');

            return $order_statuses;
        }

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
    }


    /**
     * @return bool|void
     */
    static function payplus_add_file_ApplePay()
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



    /**
     * @param $order
     * @return float | array
     */
    static function payplus_woocommerce_get_tax_rates($order)
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

    /**
     * @return bool
     */
    static function payplus_check_woocommerce_custom_orders_table_enabled()
    {
        return (get_option('woocommerce_custom_orders_table_enabled')) == "yes" ? true : false;
    }
}
