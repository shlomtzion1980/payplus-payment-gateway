
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
        $values = is_array($values) ? $values : [$values];
        if ($order) {
            $orderMetaValues = [];
            $isHPOS = WC_PayPlus_Order_Data::isHPOS();
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
            return $orderMetaValues;
        }
    }
}