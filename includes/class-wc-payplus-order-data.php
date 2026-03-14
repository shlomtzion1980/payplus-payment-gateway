<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles and process WC PayPlus Orders Data.
 *
 */
class WC_PayPlus_Meta_Data
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
            $isHPOS = WC_PayPlus_Meta_Data::isHPOS();
            if ($isHPOS) {
                foreach ($values as $key => $value) {
                    $order->update_meta_data($key, $value);
                }
            } else {
                $id = $order->get_id();
                foreach ($values as $key => $value) {
                    update_post_meta($id, $key, $value);
                }
            }
            $order->save();
        }
    }

    /**
     * Delete Order metadata or Post metadata
     * Handles both HPOS and classic WooCommerce modes
     * 
     * @param WC_Order|int $order The order object or order ID
     * @param string|array $keys Single meta key or array of meta keys to delete
     * @return void
     */
    public static function delete_meta($order, $keys)
    {
        if (!$order) {
            return;
        }

        // Convert single key to array for unified processing
        $keys = is_array($keys) ? $keys : [$keys];

        $isHPOS = WC_PayPlus_Meta_Data::isHPOS();
        
        if ($isHPOS) {
            // HPOS: Use object methods
            foreach ($keys as $key) {
                $order->delete_meta_data($key);
            }
            $order->save();
        } else {
            // Classic: Use post meta functions
            $id = $order->get_id();
            foreach ($keys as $key) {
                delete_post_meta($id, $key);
            }
        }
    }

    /**
     * Get Order metadata or Post metadata
     * @return String|Array
     */
    public static function get_meta($order, $values)
    {
        //Keep the ID if indeed an id...
        $postId = is_numeric($order) ? $order : null;
        //check if $order is an object or an id and if it id convert it to an order object
        $payplusOptions = get_option('woocommerce_payplus-payment-gateway_settings');
        $useOldFields = isset($payplusOptions['use_old_fields']) && $payplusOptions['use_old_fields'] == 'yes' ? true : false;

        $order = is_object($order) ? $order : wc_get_order($order);
        $singleValue = !is_array($values) ? true : false;
        $values = is_array($values) ? $values : [$values];

        //In case the $order is actually a $post_id of a product for example...
        if (empty($order) && is_numeric($postId)) {
            $orderMetaValues = [];
            foreach ($values as $key) {
                if (get_post_meta($postId, $key, true) != null) {
                    $orderMetaValues[$key] = get_post_meta($postId, $key, true);
                }
            }
            $orderMetaValues = $singleValue ? reset($orderMetaValues) : $orderMetaValues;
            return $orderMetaValues;
        }

        if ($order) {
            $orderMetaValues = [];
            $isHPOS = WC_PayPlus_Meta_Data::isHPOS();
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

    /**
     * Append a PRUID entry to the payplus_page_request_uid_history meta.
     * Each entry records the UID, timestamp, and source for audit purposes.
     *
     * @param WC_Order|int $order Order object or order ID.
     * @param string       $uid   The page_request_uid value.
     * @param string       $source Where it originated (e.g. main_gateway, hosted_fields).
     */
    public static function append_pruid_history($order, $uid, $source = '')
    {
        if (empty($uid)) {
            return;
        }
        $order = is_object($order) ? $order : wc_get_order($order);
        if (!$order) {
            return;
        }
        $order_id = $order->get_id();
        $raw = self::get_meta($order_id, 'payplus_page_request_uid_history', true);
        $history = !empty($raw) ? json_decode($raw, true) : [];
        if (!is_array($history)) {
            $history = [];
        }
        // Avoid duplicates
        foreach ($history as $entry) {
            if (isset($entry['uid']) && $entry['uid'] === $uid) {
                return;
            }
        }
        $history[] = [
            'uid'        => $uid,
            'created_at' => current_time('Y-m-d H:i:s'),
            'source'     => $source,
        ];
        self::update_meta($order, ['payplus_page_request_uid_history' => wp_json_encode($history)]);
    }

    /**
     * Get the PRUID history array for an order.
     * Falls back to the current payplus_page_request_uid if history is empty.
     *
     * @param int $order_id
     * @return array
     */
    public static function get_pruid_history($order_id)
    {
        $raw = self::get_meta($order_id, 'payplus_page_request_uid_history', true);
        $history = !empty($raw) ? json_decode($raw, true) : [];
        if (!is_array($history)) {
            $history = [];
        }
        // Include current PRUID if not already in history (legacy orders)
        $current = self::get_meta($order_id, 'payplus_page_request_uid', true);
        if ($current && !in_array($current, array_column($history, 'uid'), true)) {
            array_unshift($history, [
                'uid'        => $current,
                'created_at' => '',
                'source'     => 'legacy',
            ]);
        }
        return $history;
    }

    public static function sendMoreInfo($order, $newStatus, $transactionUid = null)
    {
        if (!is_null($transactionUid)) {
            $currentStatus = $order->get_status();
            $payload['transaction_uid'] = $transactionUid;
            $payload['more_info_5'] = "$currentStatus => $newStatus";
            $payload = wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
            WC_PayPlus_Statics::payplusPost($payload, "post");
        }
    }
}
