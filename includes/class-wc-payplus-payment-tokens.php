<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles and process WC payment tokens API.
 * Seen in checkout page and my account->add payment method page.
 *
 * @since 4.0.0
 */
class WC_PayPlus_Payment_Tokens
{
    private static $_this;

    /**
     * Constructor.
     *
     * @since 4.0.0
     * @version 4.0.0
     */
    public function __construct()
    {
        self::$_this = $this;
    }

    /**
     * Public access to instance object.
     *
     * @since 4.0.0
     * @version 4.0.0
     */
    public static function get_instance()
    {
        return self::$_this;
    }

    /**
     * Checks if customer has saved payment methods.
     *
     * @since 4.1.0
     * @param int $customer_id
     * @return bool
     */
    public static function customer_has_saved_methods($customer_id)
    {
        $gateways = ['payplus-payment-gateway'];

        if (empty($customer_id)) {
            return false;
        }

        $has_token = false;

        foreach ($gateways as $gateway) {
            $tokens = WC_Payment_Tokens::get_customer_tokens($customer_id, $gateway);

            if (!empty($tokens)) {
                $has_token = true;
                break;
            }
        }

        return $has_token;
    }
}
