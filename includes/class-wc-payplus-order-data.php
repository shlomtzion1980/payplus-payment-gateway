<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles and process WC PayPlus Orders Data.
 *
 */
class WC_PayPlus_Order_Data
{

    public function is_associative_array($array)
    {
        return array_values($array) !== $array;
    }

    /**
     * @return bool
     */
    public static function isHPOS()
    {
        return (get_option('woocommerce_custom_orders_table_enabled')) == "yes" ? true : false;
    }

    public static function update_meta($order, $values)
    {
        if ($order) {
            $isHPOS = WC_PayPlus_Order_Data::isHPOS();
            if ($isHPOS) {
                foreach ($values as $key => $value) {
                    $order->update_meta_data($key, $value);
                }
                $order->save();
            } else {
                $id = $order->get_id();
                foreach ($values as $key => $value) {
                    update_post_meta($id, $key, $value);
                }
            }
        }
    }

    public static function get_meta($order, $values)
    {
        //check if $order is an object or an id and if it id convert it to an order object
        $payplusOptions = get_option('woocommerce_payplus-payment-gateway_settings');
        $useOldFields = isset($payplusOptions['use_old_fields']) && $payplusOptions['use_old_fields'] == 'yes' ? true : false;

        $order = is_object($order) ? $order : wc_get_order($order);
        $singleValue = !is_array($values) ? true : false;
        $values = is_array($values) ? $values : [$values];

        if ($order) {
            $orderMetaValues = [];
            $isHPOS = WC_PayPlus_Order_Data::isHPOS();
            if ($isHPOS && $useOldFields) {
                foreach ($values as $key) {
                    if ($order->get_meta($key, true) != null) {
                        $orderMetaValues[$key] = $order->get_meta($key, true);
                    }
                }
                $id = $order->get_id();
                foreach ($values as $key) {
                    if (get_post_meta($id, $key, true) != null) {
                        $orderMetaValues[$key] = get_post_meta($id, $key, true);
                    }
                }
            } else {
                if ($isHPOS) {
                    foreach ($values as $key) {
                        if ($order->get_meta($key, true) != null) {
                            $orderMetaValues[$key] = $order->get_meta($key, true);
                        }
                    }
                } else {
                    $id = $order->get_id();
                    foreach ($values as $key) {
                        if (get_post_meta($id, $key, true) != null) {
                            $orderMetaValues[$key] = get_post_meta($id, $key, true);
                        }
                    }
                }
            }

            //return the value of the first key if $values is not an array
            $orderMetaValues = $singleValue ? reset($orderMetaValues) : $orderMetaValues;
            return $orderMetaValues;
        }
    }
}
