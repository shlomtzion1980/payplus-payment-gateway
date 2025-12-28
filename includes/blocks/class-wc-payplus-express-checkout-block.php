<?php
/**
 * PayPlus Express Checkout Block Integration
 * Support for WooCommerce Blocks Checkout
 *
 * @package PayPlus
 * @version 2.0.0
 */

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class for integrating with WooCommerce Blocks
 */
class WC_PayPlus_Express_Checkout_Block implements IntegrationInterface {

    /**
     * The name of the integration
     *
     * @return string
     */
    public function get_name() {
        return 'payplus-express-checkout';
    }

    /**
     * When called invokes any initialization/setup for the integration
     */
    public function initialize() {
        $this->register_block_frontend_scripts();
        $this->register_block_editor_scripts();
    }

    /**
     * Register scripts for frontend
     */
    private function register_block_frontend_scripts() {
        $script_asset_path = PAYPLUS_PLUGIN_DIR . '/block/dist/js/woocommerce-blocks/index.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : [
                'dependencies' => [],
                'version' => PAYPLUS_VERSION
            ];

        wp_register_script(
            'payplus-express-checkout-block-frontend',
            PAYPLUS_PLUGIN_URL . 'block/dist/js/woocommerce-blocks/index.js',
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        wp_set_script_translations(
            'payplus-express-checkout-block-frontend',
            'payplus-payment-gateway',
            PAYPLUS_PLUGIN_DIR . '/languages'
        );
    }

    /**
     * Register scripts for editor
     */
    private function register_block_editor_scripts() {
        $script_asset_path = PAYPLUS_PLUGIN_DIR . '/block/dist/js/woocommerce-blocks/index.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : [
                'dependencies' => [],
                'version' => PAYPLUS_VERSION
            ];

        wp_register_script(
            'payplus-express-checkout-block-editor',
            PAYPLUS_PLUGIN_URL . 'block/dist/js/woocommerce-blocks/index.js',
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        wp_set_script_translations(
            'payplus-express-checkout-block-editor',
            'payplus-payment-gateway',
            PAYPLUS_PLUGIN_DIR . '/languages'
        );
    }

    /**
     * Returns an array of script handles to enqueue in the frontend context
     *
     * @return string[]
     */
    public function get_script_handles() {
        return ['payplus-express-checkout-block-frontend'];
    }

    /**
     * Returns an array of script handles to enqueue in the editor context
     *
     * @return string[]
     */
    public function get_editor_script_handles() {
        return ['payplus-express-checkout-block-editor'];
    }

    /**
     * Returns an array of script data to pass to the block
     *
     * @return array
     */
    public function get_script_data() {
        $settings = get_option('woocommerce_payplus-payment-gateway_settings', []);
        $test_mode = isset($settings['api_test_mode']) && $settings['api_test_mode'] === 'yes';

        $apple_pay_enabled = isset($settings['express_apple_pay_enabled']) 
            && $settings['express_apple_pay_enabled'] === 'yes';
        
        $google_pay_enabled = isset($settings['express_google_pay_enabled']) 
            && $settings['express_google_pay_enabled'] === 'yes';

        return [
            'apple_pay_enabled' => $apple_pay_enabled,
            'google_pay_enabled' => $google_pay_enabled,
            'apple_merchant_id' => $settings['apple_merchant_identifier'] ?? '',
            'google_merchant_id' => $settings['google_merchant_id'] ?? '',
            'test_mode' => $test_mode,
            'environment' => $test_mode ? 'TEST' : 'PRODUCTION',
            'country_code' => substr(get_option('woocommerce_default_country'), 0, 2),
            'currency_code' => get_woocommerce_currency(),
            'store_name' => get_bloginfo('name'),
            'button_type' => $settings['express_button_type'] ?? 'buy',
            'button_color' => $settings['express_button_color'] ?? 'black',
            'button_height' => $settings['express_button_height'] ?? '48',
        ];
    }
}

