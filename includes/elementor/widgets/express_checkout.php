<?php

class Elementor_Express_Checkout extends \Elementor\Widget_Base
{
    /**
     * @return string
     */
    public function get_name()
    {
        return 'payplus_express_checkout';
    }

    /**
     * @return string
     */
    public function get_title()
    {
        return esc_html__('Payplus Express Checkout', 'payplus-payment-gateway');
    }

    /**
     * @return string
     */
    public function get_icon()
    {
        return 'eicon-share-arrow';
    }

    /**
     * @return string[]
     */
    public function get_categories()
    {
        return ['woocommerce-elements'];
    }

    /**
     * @return string[]
     */
    public function get_keywords()
    {
        return ['payplus', 'extra', 'express', 'checkout'];
    }

    /**
     * @return void
     */
    protected function render()
    {
        echo do_shortcode('[payplus-extra-express-checkout]');
    }
}