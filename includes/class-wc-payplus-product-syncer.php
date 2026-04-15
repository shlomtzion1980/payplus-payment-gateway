<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles PayPlus Product Syncing functionality
 */
class WC_PayPlus_Product_Syncer
{
    private static $skip_stock_sync = false;

    private static $sent_product_ids = array();

    private static $order_stock_context = null;

    private static function get_site_uid()
    {
        $uid = get_option('payplus_site_uid');
        if (empty($uid)) {
            $uid = wp_generate_uuid4();
            add_option('payplus_site_uid', $uid, '', 'no');
        }
        return $uid;
    }

    /**
     * Constructor - Register AJAX handlers
     */
    public function __construct()
    {
        add_action('wp_ajax_payplus_get_products_json', [__CLASS__, 'ajax_get_products_json']);
        add_action('wp_ajax_payplus_send_products_to_gateway', [__CLASS__, 'ajax_send_products_to_gateway']);
        add_action('wp_ajax_payplus_activate_product_syncer', [__CLASS__, 'ajax_activate_product_syncer']);
        add_action('wp_ajax_payplus_deactivate_product_syncer', [__CLASS__, 'ajax_deactivate_product_syncer']);
        add_action('wp_ajax_payplus_toggle_auto_sync', [__CLASS__, 'ajax_toggle_auto_sync']);
        add_action('payplus_run_export_job', [__CLASS__, 'run_export_job'], 10, 1);

        $settings = get_option('woocommerce_payplus-payment-gateway_settings');
        if (!empty($settings['enable_partners_features']) && $settings['enable_partners_features'] === 'yes') {
            add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

            $token     = get_option('payplus_product_syncer_token', '');
            $auto_sync = get_option('payplus_product_syncer_auto_sync', 'no');
            if (!empty($token) && $auto_sync === 'yes') {
                add_action('woocommerce_new_product', [__CLASS__, 'on_product_created'], 10, 1);
                add_action('woocommerce_update_product', [__CLASS__, 'on_product_updated'], 10, 1);
                add_action('wp_trash_post', [__CLASS__, 'on_product_trashed'], 10, 1);
                add_action('delete_post', [__CLASS__, 'on_variation_deleted'], 10, 1);
                add_action('woocommerce_payment_complete', [__CLASS__, 'set_order_stock_context'], 5, 1);
                add_action('woocommerce_order_status_completed', [__CLASS__, 'set_order_stock_context'], 5, 1);
                add_action('woocommerce_order_status_processing', [__CLASS__, 'set_order_stock_context'], 5, 1);
                add_action('woocommerce_order_status_on-hold', [__CLASS__, 'set_order_stock_context'], 5, 1);
                add_action('woocommerce_order_status_cancelled', [__CLASS__, 'set_order_stock_context'], 5, 1);
                add_action('woocommerce_order_status_pending', [__CLASS__, 'set_order_stock_context'], 5, 1);
                add_action('woocommerce_product_set_stock', [__CLASS__, 'on_product_stock_changed'], 10, 1);
                add_action('woocommerce_variation_set_stock', [__CLASS__, 'on_variation_stock_changed'], 10, 1);
            }
        }
    }

    /**
     * Register REST API routes for the product syncer.
     */
    public static function register_rest_routes()
    {
        register_rest_route('payplus/v1', '/products', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'rest_get_products'],
            'permission_callback' => [__CLASS__, 'rest_permission_check'],
            'args'                => [
                'page' => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && intval($value) >= 1;
                    },
                ],
                'per_page' => [
                    'default'           => 50,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && intval($value) >= 1 && intval($value) <= 200;
                    },
                ],
            ],
        ]);

        register_rest_route('payplus/v1', '/products/export', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'rest_export_products'],
            'permission_callback' => [__CLASS__, 'rest_permission_check'],
        ]);

        register_rest_route('payplus/v1', '/products/export', [
            'methods'             => 'DELETE',
            'callback'            => [__CLASS__, 'rest_delete_export'],
            'permission_callback' => [__CLASS__, 'rest_permission_check'],
            'args'                => [
                'file' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_file_name',
                ],
            ],
        ]);

        register_rest_route('payplus/v1', '/products/inventory', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'rest_update_inventory'],
            'permission_callback' => [__CLASS__, 'rest_permission_check'],
        ]);

        register_rest_route('payplus/v1', '/products/inventory/bulk', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'rest_update_inventory_bulk'],
            'permission_callback' => [__CLASS__, 'rest_permission_check'],
        ]);

        register_rest_route('payplus/v1', '/products/activated', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'rest_activated'],
            'permission_callback' => [__CLASS__, 'rest_permission_check'],
        ]);

        register_rest_route('payplus/v1', '/products/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'rest_get_single_product'],
            'permission_callback' => [__CLASS__, 'rest_permission_check'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'by' => [
                    'default'           => 'id',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return in_array($value, ['id', 'external_id'], true);
                    },
                ],
            ],
        ]);
    }

    /**
     * Permission check for the REST endpoint.
     * Validates the request via PayPlus API key + secret key sent in the Authorization header.
     */
    public static function rest_permission_check(WP_REST_Request $request)
    {
        $auth_header = $request->get_header('Authorization');
        if (empty($auth_header)) {
            return new WP_Error(
                'rest_forbidden',
                __('Missing Authorization header.', 'payplus-payment-gateway'),
                ['status' => 401]
            );
        }

        $credentials = json_decode($auth_header, true);
        if (!is_array($credentials) || empty($credentials['api_key']) || empty($credentials['secret_key'])) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid Authorization format. Expected JSON with api_key and secret_key.', 'payplus-payment-gateway'),
                ['status' => 401]
            );
        }

        $settings = get_option('woocommerce_payplus-payment-gateway_settings');
        $test_mode = isset($settings['api_test_mode']) && $settings['api_test_mode'] === 'yes';

        $stored_api_key    = $test_mode ? ($settings['dev_api_key'] ?? '') : ($settings['api_key'] ?? '');
        $stored_secret_key = $test_mode ? ($settings['dev_secret_key'] ?? '') : ($settings['secret_key'] ?? '');

        if (
            !hash_equals($stored_api_key, $credentials['api_key']) ||
            !hash_equals($stored_secret_key, $credentials['secret_key'])
        ) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid API credentials.', 'payplus-payment-gateway'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * REST callback — receives activation token from PayPlus after app-install login.
     * POST /wp-json/payplus/v1/products/activated
     * Authorization: {"api_key":"...","secret_key":"..."}
     * Body: {"token":"...", "success": true, "message": "Activated Successfully"}
     */
    public static function rest_activated(WP_REST_Request $request)
    {
        $body    = $request->get_json_params();
        $token   = isset($body['token']) ? sanitize_text_field($body['token']) : '';
        $success = isset($body['success']) ? (bool) $body['success'] : false;
        $message = isset($body['message']) ? sanitize_text_field($body['message']) : '';

        $logger = wc_get_logger();
        $logCtx = array('source' => 'payplus-product-syncer');
        $logger->info('Activated callback received. Token: ' . ($token ? substr($token, 0, 8) . '...' : '(empty)') . ' | success: ' . ($success ? 'true' : 'false') . ' | message: ' . $message, $logCtx);

        if (empty($token)) {
            return new WP_Error(
                'missing_token',
                __('No token provided.', 'payplus-payment-gateway'),
                array('status' => 400)
            );
        }

        update_option('payplus_product_syncer_token', $token);

        $logger->info('Product Syncer activated successfully. Token stored.', $logCtx);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Activated Successfully',
        ), 200);
    }

    /**
     * REST callback — return a single product in PayPlus Commerce Format.
     * GET /wp-json/payplus/v1/products/{id}
     * Optional query: ?by=external_id  (default: by=id)
     */
    public static function rest_get_single_product(WP_REST_Request $request)
    {
        $lookup_id = absint($request->get_param('id'));
        $by        = $request->get_param('by') ?: 'id';

        if ($by === 'external_id') {
            $product = wc_get_product($lookup_id);
        } else {
            $product = wc_get_product($lookup_id);
        }

        if (!$product) {
            return new WP_Error(
                'product_not_found',
                /* translators: %d: product ID */
                sprintf(__('Product %d not found.', 'payplus-payment-gateway'), $lookup_id),
                array('status' => 404)
            );
        }

        if ($product->is_type('variation')) {
            $product = wc_get_product($product->get_parent_id());
            if (!$product) {
                return new WP_Error(
                    'parent_not_found',
                    __('Parent product not found for this variation.', 'payplus-payment-gateway'),
                    array('status' => 404)
                );
            }
        }

        $options = get_option('woocommerce_payplus-payment-gateway_settings');
        $testMode = isset($options['api_test_mode']) && $options['api_test_mode'] === 'yes';
        $pageUid  = $testMode
            ? (isset($options['dev_payment_page_id']) ? $options['dev_payment_page_id'] : '')
            : (isset($options['payment_page_id']) ? $options['payment_page_id'] : '');

        $company = array(
            'id' => self::get_site_uid(),
        );

        $commerce_data = self::transform_to_commerce_format($product, $company);

        return new WP_REST_Response(array(
            'payment_page_uid' => $pageUid,
            'products'         => array($commerce_data),
        ), 200);
    }

    /**
     * REST callback — returns products in PayPlus Commerce Format with pagination.
     */
    public static function rest_get_products(WP_REST_Request $request)
    {
        $page     = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $offset   = ($page - 1) * $per_page;

        $total_products = self::get_products_count();
        $total_pages    = max(1, intval(ceil($total_products / $per_page)));
        $products       = self::get_commerce_format_data($offset, $per_page);

        return new WP_REST_Response([
            'page'           => $page,
            'per_page'       => $per_page,
            'total_products' => $total_products,
            'total_pages'    => $total_pages,
            'products_count' => count($products),
            'products'       => $products,
        ], 200);
    }

    /**
     * REST callback — builds a full JSON file of all products in Commerce Format
     * and returns a download URL.
     */
    public static function rest_export_products(WP_REST_Request $request)
    {
        $upload_dir = wp_upload_dir();
        $export_dir = trailingslashit($upload_dir['basedir']) . 'payplus-exports';

        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            @file_put_contents($export_dir . '/.htaccess', "Options -Indexes\n");
        }

        $job_id = wp_generate_password(16, false);

        wp_schedule_single_event(time(), 'payplus_run_export_job', array($job_id));
        spawn_cron();

        return new WP_REST_Response(array(
            'status'  => 'processing',
            'job_id'  => $job_id,
            'message' => __('Export started in background. Results will be posted to the gateway when complete.', 'payplus-payment-gateway'),
        ), 202);
    }

    /**
     * Background cron handler — runs the actual export and POSTs result to gateway.
     *
     * @param string $job_id Unique job identifier.
     */
    public static function run_export_job($job_id)
    {
        $logger = wc_get_logger();
        $logCtx = array('source' => 'payplus-product-syncer');
        $logger->info('Export job started — job_id: ' . $job_id, $logCtx);

        $upload_dir = wp_upload_dir();
        $export_dir = trailingslashit($upload_dir['basedir']) . 'payplus-exports';

        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        $total_products = self::get_products_count();
        $batch_size     = 50;
        $all_products   = array();
        $offset         = 0;

        while ($offset < $total_products) {
            $batch = self::get_commerce_format_data($offset, $batch_size);
            $all_products = array_merge($all_products, $batch);
            $offset += $batch_size;
        }

        $filename = 'payplus-products-' . gmdate('Y-m-d-His') . '-' . $job_id . '.json';
        $filepath = trailingslashit($export_dir) . $filename;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $written = file_put_contents(
            $filepath,
            wp_json_encode($all_products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        if ($written === false) {
            $logger->error('Export job failed — could not write file: ' . $filepath, $logCtx);
            return;
        }

        $download_url = trailingslashit($upload_dir['baseurl']) . 'payplus-exports/' . $filename;

        $logger->info(sprintf('Export job complete — %d products, file: %s', count($all_products), $filename), $logCtx);

        $options   = get_option('woocommerce_payplus-payment-gateway_settings');
        $testMode  = isset($options['api_test_mode']) && $options['api_test_mode'] === 'yes';
        $apiKey    = $testMode ? (isset($options['dev_api_key']) ? $options['dev_api_key'] : '') : (isset($options['api_key']) ? $options['api_key'] : '');
        $secretKey = $testMode ? (isset($options['dev_secret_key']) ? $options['dev_secret_key'] : '') : (isset($options['secret_key']) ? $options['secret_key'] : '');
        $pageUid   = $testMode
            ? (isset($options['dev_payment_page_id']) ? $options['dev_payment_page_id'] : '')
            : (isset($options['payment_page_id']) ? $options['payment_page_id'] : '');

        $payload = array(
            'payment_page_uid' => $pageUid,
            'job_id'           => $job_id,
            'total_products'   => count($all_products),
            'file'             => $filename,
            'download_url'     => $download_url,
            'generated_at'     => gmdate('Y-m-d\TH:i:s\Z'),
        );

        $url  = 'https://henevent-gateway.invoiceplus.co.il/v1/wc-hooks/bulk-operation/products';
        $args = array(
            'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
            'headers' => array(
                'domain'        => home_url(),
                'Content-Type'  => 'application/json',
                'Authorization' => '{"api_key":"' . $apiKey . '","secret_key":"' . $secretKey . '"}',
            ),
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $logger->error('Export job — gateway POST failed: ' . $response->get_error_message(), $logCtx);
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $logger->info('Export job — gateway POST response HTTP ' . $code, $logCtx);
        }
    }

    /**
     * REST callback — deletes a previously exported JSON file.
     */
    public static function rest_delete_export(WP_REST_Request $request)
    {
        $filename = $request->get_param('file');

        if (!preg_match('/^payplus-products-.*\.json$/', $filename)) {
            return new WP_Error(
                'invalid_filename',
                __('Invalid export filename.', 'payplus-payment-gateway'),
                ['status' => 400]
            );
        }

        $upload_dir = wp_upload_dir();
        $filepath   = trailingslashit($upload_dir['basedir']) . 'payplus-exports/' . $filename;

        if (!file_exists($filepath)) {
            return new WP_Error(
                'file_not_found',
                __('Export file not found.', 'payplus-payment-gateway'),
                ['status' => 404]
            );
        }

        wp_delete_file($filepath);
        if (file_exists($filepath)) {
            return new WP_Error(
                'delete_failed',
                __('Failed to delete export file.', 'payplus-payment-gateway'),
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'deleted' => true,
            'file'    => $filename,
        ], 200);
    }

    /**
     * REST callback — update inventory for a single product.
     * Body: { "product_id": 123, "stock_quantity": 10 }
     */
    public static function rest_update_inventory(WP_REST_Request $request)
    {
        $body       = $request->get_json_params();
        $product_id = isset($body['product_id']) ? absint($body['product_id']) : 0;
        $delta      = isset($body['delta']) ? intval($body['delta']) : null;
        $timestamp  = isset($body['timestamp']) ? sanitize_text_field($body['timestamp']) : '';

        if (!$product_id || $delta === null || $delta === 0 || $timestamp === '') {
            return new WP_Error(
                'missing_params',
                __('product_id, a non-zero delta, and timestamp are required.', 'payplus-payment-gateway'),
                ['status' => 400]
            );
        }

        $result = self::apply_stock_update($product_id, $delta, $timestamp);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * REST callback — bulk update inventory for multiple products.
     * Body: { "products": [ { "product_id": 123, "delta": -2, "timestamp": "..." }, ... ] }
     */
    public static function rest_update_inventory_bulk(WP_REST_Request $request)
    {
        $body     = $request->get_json_params();
        $products = isset($body['products']) && is_array($body['products']) ? $body['products'] : [];

        if (empty($products)) {
            return new WP_Error(
                'missing_params',
                __('products array is required and must not be empty.', 'payplus-payment-gateway'),
                ['status' => 400]
            );
        }

        $results  = [];
        $success  = 0;
        $failed   = 0;

        foreach ($products as $item) {
            $product_id = isset($item['product_id']) ? absint($item['product_id']) : 0;
            $delta      = isset($item['delta']) ? intval($item['delta']) : null;
            $timestamp  = isset($item['timestamp']) ? sanitize_text_field($item['timestamp']) : '';

            if (!$product_id || $delta === null || $delta === 0 || $timestamp === '') {
                $failed++;
                $results[] = [
                    'product_id' => $product_id,
                    'success'    => false,
                    'error'      => __('product_id, a non-zero delta, and timestamp are required.', 'payplus-payment-gateway'),
                ];
                continue;
            }

            $result = self::apply_stock_update($product_id, $delta, $timestamp);

            if (is_wp_error($result)) {
                $failed++;
                $results[] = [
                    'product_id' => $product_id,
                    'success'    => false,
                    'error'      => $result->get_error_message(),
                ];
            } else {
                $success++;
                $results[] = $result;
            }
        }

        return new WP_REST_Response([
            'total'   => count($products),
            'success' => $success,
            'failed'  => $failed,
            'results' => $results,
        ], 200);
    }

    /**
     * Apply a stock delta to a single WooCommerce product or variation.
     * Uses a transient to prevent the same timestamp from being applied twice.
     *
     * @param int    $product_id
     * @param int    $delta
     * @param string $timestamp  Unique identifier from PayPlus for idempotency.
     * @return array|WP_Error
     */
    private static function apply_stock_update($product_id, $delta, $timestamp)
    {
        $transient_key = 'payplus_inv_' . $product_id . '_' . md5($timestamp);

        $cached = get_transient($transient_key);
        if ($cached !== false) {
            $cached['skipped'] = true;
            return $cached;
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_Error(
                'product_not_found',
                /* translators: %d: product ID */
                sprintf(__('Product %d not found.', 'payplus-payment-gateway'), $product_id),
                ['status' => 404]
            );
        }

        $previous_qty = $product->get_stock_quantity();
        $previous_qty = $previous_qty !== null ? intval($previous_qty) : 0;

        $operation = $delta > 0 ? 'increase' : 'decrease';
        $abs_delta = absint($delta);

        self::$skip_stock_sync = true;
        wc_update_product_stock($product, $abs_delta, $operation);

        $new_qty    = $previous_qty + $delta;
        $new_status = $new_qty > 0 ? 'instock' : 'outofstock';
        $product->set_stock_status($new_status);
        $product->save();
        self::$skip_stock_sync = false;

        $logger = wc_get_logger();
        $logger->info(
            sprintf('Inventory update — Product #%d: %d %+d → %d (status: %s)', $product_id, $previous_qty, $delta, $new_qty, $new_status),
            ['source' => 'payplus-product-syncer']
        );

        $result = [
            'product_id'     => $product_id,
            'success'        => true,
            'name'           => $product->get_name(),
            'sku'            => $product->get_sku() ?: null,
            'previous_stock' => $previous_qty,
            'delta'          => $delta,
            'new_stock'      => $new_qty,
            'stock_status'   => $new_status,
        ];

        set_transient($transient_key, $result, DAY_IN_SECONDS);

        return $result;
    }

    /**
     * AJAX handler to get products JSON
     *
     * @return void
     */
    public static function ajax_get_products_json()
    {
        check_ajax_referer('payplus_product_sync', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'payplus-payment-gateway')]);
        }

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $format = isset($_POST['format']) ? sanitize_text_field(wp_unslash($_POST['format'])) : 'woocommerce';
        $limit = max(10, min(200, $limit)); // Clamp between 10-200

        if ($format === 'commerce') {
            $products_data = self::get_commerce_format_data($offset, $limit);
        } else {
            $products_data = self::get_all_products_data($offset, $limit);
        }
        
        $total_products = self::get_products_count();
        $processed = min($offset + $limit, $total_products);

        wp_send_json_success([
            'products' => $products_data,
            'total' => $total_products,
            'offset' => $offset,
            'limit' => $limit,
            'processed' => $processed,
            'has_more' => $processed < $total_products,
        ]);
    }

    /**
     * AJAX handler to send Commerce JSON to the PayPlus gateway endpoint.
     */
    public static function ajax_send_products_to_gateway()
    {
        check_ajax_referer('payplus_product_sync', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'payplus-payment-gateway')]);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload validated by json_decode below.
        $raw = isset($_POST['products_json']) ? wp_unslash($_POST['products_json']) : '';
        $products = json_decode($raw, true);
        if (!is_array($products)) {
            wp_send_json_error(['message' => __('Invalid JSON data', 'payplus-payment-gateway')]);
        }

        $options  = get_option('woocommerce_payplus-payment-gateway_settings');
        $testMode = boolval($options['api_test_mode'] === 'yes');
        $apiKey    = $testMode ? $options['dev_api_key'] : $options['api_key'];
        $secretKey = $testMode ? $options['dev_secret_key'] : $options['secret_key'];
        $pageUid   = $testMode
            ? ($options['dev_payment_page_id'] ?? '')
            : ($options['payment_page_id'] ?? '');

        $payload = [
            'payment_page_uid' => $pageUid,
            'products'         => $products,
        ];

        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $args = [
            'body'        => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'     => 60,
            'headers'     => [
                'domain'        => home_url(),
                'User-Agent'    => "WordPress $userAgent",
                'Content-Type'  => 'application/json',
                'Authorization' => '{"api_key":"' . $apiKey . '","secret_key":"' . $secretKey . '"}',
            ],
        ];

        $url = 'https://henevent-gateway.invoiceplus.co.il/v1/wc-hooks/products/create';

        $logger = wc_get_logger();
        $logCtx = ['source' => 'payplus-product-syncer'];
        $logger->info('Send to PayPlus — URL: ' . $url, $logCtx);
        $logger->info('Send to PayPlus — payment_page_uid: ' . $pageUid, $logCtx);
        $logger->info('Send to PayPlus — test mode: ' . ($testMode ? 'yes' : 'no'), $logCtx);
        $logger->info('Send to PayPlus — products count: ' . count($products), $logCtx);
        $logger->info('Send to PayPlus — payload (first 2000 chars): ' . substr(wp_json_encode($payload, JSON_UNESCAPED_UNICODE), 0, 2000), $logCtx);

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $logger->error('Send to PayPlus — WP Error: ' . $response->get_error_message(), $logCtx);
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $logger->info('Send to PayPlus — Response HTTP ' . $code . ': ' . substr($body, 0, 2000), $logCtx);

        wp_send_json_success([
            'status_code' => $code,
            'response'    => json_decode($body, true) ?: $body,
        ]);
    }

    /**
     * AJAX handler — request app-install URL from PayPlus and return redirect URL.
     */
    public static function ajax_activate_product_syncer()
    {
        check_ajax_referer('payplus_product_sync', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'payplus-payment-gateway')));
        }

        try {
            $options   = get_option('woocommerce_payplus-payment-gateway_settings');
            $testMode  = isset($options['api_test_mode']) && $options['api_test_mode'] === 'yes';
            $apiKey    = $testMode ? (isset($options['dev_api_key']) ? $options['dev_api_key'] : '') : (isset($options['api_key']) ? $options['api_key'] : '');
            $secretKey = $testMode ? (isset($options['dev_secret_key']) ? $options['dev_secret_key'] : '') : (isset($options['secret_key']) ? $options['secret_key'] : '');

            $storeUrl = site_url('/');
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
            $url = add_query_arg('store_url', rawurlencode($storeUrl), 'https://henevent-gateway.invoiceplus.co.il/v1/api/wc-app/app-install');

            $headers_arr = array(
                'domain'        => home_url(),
                'User-Agent'    => 'WordPress ' . $userAgent,
                'Content-Type'  => 'application/json',
                'Authorization' => '{"api_key":"' . $apiKey . '","secret_key":"' . $secretKey . '"}',
            );

            $args = array(
                'timeout' => 60,
                'headers' => $headers_arr,
            );

            $request_debug = array(
                'method'  => 'GET',
                'url'     => $url,
                'headers' => $headers_arr,
            );

            $logger = wc_get_logger();
            $logCtx = array('source' => 'payplus-product-syncer');
            $logger->info('App Install — URL: ' . $url, $logCtx);
            $logger->info('App Install — store_url: ' . site_url('/'), $logCtx);

            $response = wp_remote_get($url, $args);

            if (is_wp_error($response)) {
                $logger->error('App Install — WP Error: ' . $response->get_error_message(), $logCtx);
                wp_send_json_error(array(
                    'message' => $response->get_error_message(),
                    'request' => $request_debug,
                ));
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            $response_headers = array();
            try {
                $raw_headers = wp_remote_retrieve_headers($response);
                if (is_object($raw_headers) && method_exists($raw_headers, 'getAll')) {
                    $response_headers = $raw_headers->getAll();
                } elseif ($raw_headers instanceof \ArrayIterator || $raw_headers instanceof \IteratorAggregate) {
                    $response_headers = iterator_to_array($raw_headers);
                } elseif (is_array($raw_headers)) {
                    $response_headers = $raw_headers;
                }
            } catch (\Exception $e) {
                $response_headers = array('_error' => 'Could not parse headers');
            }

            $logger->info('App Install — Response HTTP ' . $code . ': ' . substr($body, 0, 2000), $logCtx);

            $result = array(
                'status_code'      => $code,
                'response'         => $data ? $data : $body,
                'response_headers' => $response_headers,
                'request'          => $request_debug,
            );

            if ($code >= 200 && $code < 300) {
                wp_send_json_success($result);
            } else {
                $result['message'] = is_array($data) && !empty($data['message']) ? $data['message'] : __('Activation failed.', 'payplus-payment-gateway');
                wp_send_json_error($result);
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'PHP Exception: ' . $e->getMessage()));
        } catch (\Error $e) {
            wp_send_json_error(array('message' => 'PHP Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()));
        }
    }

    /**
     * AJAX handler — deactivate (uninstall) the Product Syncer on PayPlus side.
     */
    public static function ajax_deactivate_product_syncer()
    {
        check_ajax_referer('payplus_product_sync', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'payplus-payment-gateway')));
        }

        try {
            $options   = get_option('woocommerce_payplus-payment-gateway_settings');
            $testMode  = isset($options['api_test_mode']) && $options['api_test_mode'] === 'yes';
            $apiKey    = $testMode ? (isset($options['dev_api_key']) ? $options['dev_api_key'] : '') : (isset($options['api_key']) ? $options['api_key'] : '');
            $secretKey = $testMode ? (isset($options['dev_secret_key']) ? $options['dev_secret_key'] : '') : (isset($options['secret_key']) ? $options['secret_key'] : '');

            $storeUrl  = site_url('/');
            $url       = 'https://henevent-gateway.invoiceplus.co.il/v1/api/wc-app/app-uninstall';

            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

            $headers_arr = array(
                'domain'        => home_url(),
                'User-Agent'    => 'WordPress ' . $userAgent,
                'Content-Type'  => 'application/json',
                'Authorization' => '{"api_key":"' . $apiKey . '","secret_key":"' . $secretKey . '"}',
            );

            $payload = array(
                'store_url' => $storeUrl,
            );

            $args = array(
                'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => 60,
                'headers' => $headers_arr,
            );

            $logger = wc_get_logger();
            $logCtx = array('source' => 'payplus-product-syncer');
            $logger->info('App Uninstall — URL: ' . $url, $logCtx);

            $response = wp_remote_post($url, $args);

            if (is_wp_error($response)) {
                $logger->error('App Uninstall — WP Error: ' . $response->get_error_message(), $logCtx);
                wp_send_json_error(array('message' => $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            $logger->info('App Uninstall — Response HTTP ' . $code . ': ' . substr($body, 0, 2000), $logCtx);

            if ($code >= 200 && $code < 300) {
                delete_option('payplus_product_syncer_token');
                $logger->info('Product Syncer deactivated. Token removed.', $logCtx);
                wp_send_json_success(array(
                    'status_code' => $code,
                    'response'    => $data ? $data : $body,
                ));
            } else {
                $error_msg = is_array($data) && !empty($data['message']) ? $data['message'] : __('Deactivation failed.', 'payplus-payment-gateway');
                wp_send_json_error(array(
                    'message'     => $error_msg,
                    'status_code' => $code,
                    'response'    => $data ? $data : $body,
                ));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'PHP Exception: ' . $e->getMessage()));
        } catch (\Error $e) {
            wp_send_json_error(array('message' => 'PHP Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()));
        }
    }

    /**
     * AJAX handler — toggle auto-sync option.
     */
    public static function ajax_toggle_auto_sync()
    {
        check_ajax_referer('payplus_product_sync', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'payplus-payment-gateway')));
        }

        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'yes' ? 'yes' : 'no';
        update_option('payplus_product_syncer_auto_sync', $enabled);
        wp_send_json_success(array('auto_sync' => $enabled));
    }

    /**
     * Render the product syncer admin page
     *
     * @return void
     */
    public static function render_product_syncer_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'payplus-payment-gateway'));
        }

        // Get products count
        $products_count = self::get_products_count();
        
        // Get sample products
        $sample_products = self::get_sample_products(10);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('PayPlus Product Syncer', 'payplus-payment-gateway'); ?></h1>
            <p><?php echo esc_html__('Sync your WooCommerce products with PayPlus servers.', 'payplus-payment-gateway'); ?></p>

            <div class="payplus-syncer-stats" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 5px;">
                <h2><?php echo esc_html__('Product Statistics', 'payplus-payment-gateway'); ?></h2>
                <p><strong><?php echo esc_html__('Total Products:', 'payplus-payment-gateway'); ?></strong> <?php echo esc_html($products_count); ?></p>
            </div>

            <div class="payplus-syncer-sample" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 5px;">
                <h2><?php echo esc_html__('Sample Products', 'payplus-payment-gateway'); ?></h2>
                <?php if (!empty($sample_products)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('ID', 'payplus-payment-gateway'); ?></th>
                                <th><?php echo esc_html__('Name', 'payplus-payment-gateway'); ?></th>
                                <th><?php echo esc_html__('SKU', 'payplus-payment-gateway'); ?></th>
                                <th><?php echo esc_html__('Price', 'payplus-payment-gateway'); ?></th>
                                <th><?php echo esc_html__('Stock', 'payplus-payment-gateway'); ?></th>
                                <th><?php echo esc_html__('Status', 'payplus-payment-gateway'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sample_products as $product) : ?>
                                <tr>
                                    <td><?php echo esc_html($product['id']); ?></td>
                                    <td><?php echo esc_html($product['name']); ?></td>
                                    <td><?php echo esc_html($product['sku']); ?></td>
                                    <td><?php echo esc_html($product['price']); ?></td>
                                    <td><?php echo esc_html($product['stock']); ?></td>
                                    <td><?php echo esc_html($product['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php echo esc_html__('No products found.', 'payplus-payment-gateway'); ?></p>
                <?php endif; ?>
            </div>

            <div class="payplus-syncer-activation" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 5px;">
                <h2><?php echo esc_html__('Service Activation', 'payplus-payment-gateway'); ?></h2>
                <div id="payplus-activation-area">
                    <?php $syncer_token = get_option('payplus_product_syncer_token', ''); ?>
                    <?php if (!empty($syncer_token)) : ?>
                        <p style="color: green; font-weight: bold;">
                            <?php echo esc_html__('Service is activated.', 'payplus-payment-gateway'); ?>
                        </p>
                        <p>
                            <strong><?php echo esc_html__('Token:', 'payplus-payment-gateway'); ?></strong>
                            <code><?php echo esc_html(substr($syncer_token, 0, 16) . '...'); ?></code>
                        </p>
                        <button type="button" id="payplus-deactivate-btn" class="button" style="padding: 8px 24px; font-size: 14px; background: #d63638; color: #fff; border-color: #d63638;">
                            <?php echo esc_html__('Deactivate Product Syncer', 'payplus-payment-gateway'); ?>
                        </button>

                        <div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                            <?php $auto_sync = get_option('payplus_product_syncer_auto_sync', 'no'); ?>
                            <label>
                                <input type="checkbox" id="payplus-auto-sync-checkbox" <?php checked($auto_sync, 'yes'); ?>>
                                <?php echo esc_html__('Automatically sync product changes (create, update, delete, stock) to PayPlus', 'payplus-payment-gateway'); ?>
                            </label>
                        </div>
                    <?php else : ?>
                        <p><?php echo esc_html__('Connect your store with PayPlus Product Syncer.', 'payplus-payment-gateway'); ?></p>
                        <button type="button" id="payplus-activate-btn" class="button button-primary" style="padding: 8px 24px; font-size: 14px;">
                            <?php echo esc_html__('Activate Product Syncer', 'payplus-payment-gateway'); ?>
                        </button>
                    <?php endif; ?>
                    <span id="payplus-activation-status" style="margin-left: 10px; display: none;"></span>
                </div>
            </div>

            <div class="payplus-syncer-actions" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 5px;">
                <h2><?php echo esc_html__('Sync Actions', 'payplus-payment-gateway'); ?></h2>
                <form method="post" id="payplus-product-sync-form">
                    <?php wp_nonce_field('payplus_product_sync', 'payplus_product_sync_nonce'); ?>
                    
                    <p>
                        <label for="products_per_batch"><?php echo esc_html__('Products per batch:', 'payplus-payment-gateway'); ?></label>
                        <input type="number" name="products_per_batch" id="products_per_batch" value="50" min="10" max="200" step="10" />
                        <span class="description"><?php echo esc_html__('Number of products to sync in each request (10-200)', 'payplus-payment-gateway'); ?></span>
                    </p>

                    <p>
                        <button type="submit" name="start_sync" class="button button-primary button-large" style="padding: 10px 30px; font-size: 16px;">
                            <?php echo esc_html__('Start Sync', 'payplus-payment-gateway'); ?>
                        </button>
                    </p>
                </form>

                <div id="payplus-sync-progress" style="display: none; margin-top: 20px;">
                    <h3><?php echo esc_html__('Sync Progress', 'payplus-payment-gateway'); ?></h3>
                    <div style="background: #f0f0f0; border: 1px solid #ddd; padding: 10px; border-radius: 3px;">
                        <div id="payplus-sync-status"><?php echo esc_html__('Initializing...', 'payplus-payment-gateway'); ?></div>
                        <div style="margin-top: 10px;">
                            <progress id="payplus-sync-progressbar" value="0" max="100" style="width: 100%; height: 30px;"></progress>
                        </div>
                        <div id="payplus-sync-details" style="margin-top: 10px; font-size: 12px; color: #666;"></div>
                    </div>
                </div>

                <div id="payplus-sync-results" style="display: none; margin-top: 20px;">
                    <h3><?php echo esc_html__('WooCommerce Raw Data', 'payplus-payment-gateway'); ?></h3>
                    <div id="payplus-sync-results-content" style="background: #f0f0f0; border: 1px solid #ddd; padding: 10px; border-radius: 3px;"></div>
                </div>

                <div id="payplus-commerce-results" style="display: none; margin-top: 20px;">
                    <h3><?php echo esc_html__('PayPlus Commerce Format', 'payplus-payment-gateway'); ?></h3>
                    <div id="payplus-commerce-results-content" style="background: #f0f0f0; border: 1px solid #ddd; padding: 10px; border-radius: 3px;"></div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Activation handler
            $('#payplus-activate-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#payplus-activation-status');

                $btn.prop('disabled', true);
                $status.show().css('color', '#666').text('<?php echo esc_js(__('Connecting...', 'payplus-payment-gateway')); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'payplus_activate_product_syncer',
                        nonce: '<?php echo esc_js(wp_create_nonce('payplus_product_sync')); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        var d = response.data || {};
                        var html = '';

                        // Build request debug block
                        var reqInfo = d.request || {};
                        html += '<div style="margin-top:8px;">';
                        html += '<strong><?php echo esc_js(__('Request:', 'payplus-payment-gateway')); ?></strong>';
                        html += '<pre style="background:#f5f5f5;border:1px solid #ddd;padding:8px;margin:4px 0 10px;max-height:200px;overflow:auto;font-size:12px;white-space:pre-wrap;">';
                        html += $('<span>').text(
                            (reqInfo.method || 'GET') + ' ' + (reqInfo.url || '') + '\n\n' +
                            'Headers:\n' + JSON.stringify(reqInfo.headers || {}, null, 2) + '\n\n' +
                            'Body:\n' + JSON.stringify(reqInfo.body || {}, null, 2)
                        ).html();
                        html += '</pre>';

                        // Build response debug block
                        html += '<strong><?php echo esc_js(__('Response:', 'payplus-payment-gateway')); ?> HTTP ' + (d.status_code || '?') + '</strong>';
                        var respHeaders = d.response_headers || {};
                        var respBody = d.response || '';
                        var respRaw = typeof respBody === 'object' ? JSON.stringify(respBody, null, 2) : respBody;
                        html += '<pre style="background:#f5f5f5;border:1px solid #ddd;padding:8px;margin:4px 0 10px;max-height:300px;overflow:auto;font-size:12px;white-space:pre-wrap;">';
                        html += $('<span>').text(
                            'Headers:\n' + JSON.stringify(respHeaders, null, 2) + '\n\n' +
                            'Body:\n' + respRaw
                        ).html();
                        html += '</pre>';

                        if (response.success) {
                            var data = d.response || d;
                            var redirectUrl = '';

                            // Check response headers for Location redirect
                            if (respHeaders.location) {
                                redirectUrl = respHeaders.location;
                            }

                            if (!redirectUrl) {
                                if (typeof data === 'string') {
                                    var urlMatch = data.match(/https?:\/\/[^\s"'<>]+/);
                                    if (urlMatch) redirectUrl = urlMatch[0];
                                } else if (typeof data === 'object') {
                                    redirectUrl = data.redirect_url || data.url || data.redirect
                                        || data.redirectUrl || data.login_url || data.loginUrl
                                        || data.link || data.location || '';
                                }
                            }

                            if (redirectUrl) {
                                html = '<p><span style="color:green;font-weight:bold;"><?php echo esc_js(__('Ready!', 'payplus-payment-gateway')); ?></span> <a href="' + redirectUrl + '" target="_blank" style="font-weight:bold;"><?php echo esc_js(__('Click here to login', 'payplus-payment-gateway')); ?></a></p>' + html;
                                $status.html(html);
                                window.open(redirectUrl, '_blank');
                            } else {
                                html = '<p style="color:#666;"><?php echo esc_js(__('No redirect URL found in response.', 'payplus-payment-gateway')); ?></p>' + html;
                                $status.html(html);
                            }
                        } else {
                            var msg = d.message || '<?php echo esc_js(__('Unknown error', 'payplus-payment-gateway')); ?>';
                            html = '<p style="color:red;font-weight:bold;"><?php echo esc_js(__('Error:', 'payplus-payment-gateway')); ?> ' + $('<span>').text(msg).html() + '</p>' + html;
                            $status.html(html);
                        }
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false);
                        var html = '<p style="color:red;font-weight:bold;"><?php echo esc_js(__('AJAX Error:', 'payplus-payment-gateway')); ?> ' + $('<span>').text(error).html() + '</p>';
                        html += '<pre style="background:#fff5f5;border:1px solid #dcc;padding:8px;margin-top:6px;max-height:200px;overflow:auto;font-size:12px;white-space:pre-wrap;">';
                        html += $('<span>').text('Status: ' + status + '\nHTTP: ' + xhr.status + ' ' + xhr.statusText + '\nResponse:\n' + (xhr.responseText || '(empty)')).html();
                        html += '</pre>';
                        $status.html(html);
                    }
                });
            });

            // Deactivation handler
            $('#payplus-deactivate-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#payplus-activation-status');

                if (!confirm('<?php echo esc_js(__('Are you sure you want to deactivate the Product Syncer?', 'payplus-payment-gateway')); ?>')) {
                    return;
                }

                $btn.prop('disabled', true);
                $status.show().css('color', '#666').text('<?php echo esc_js(__('Deactivating...', 'payplus-payment-gateway')); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'payplus_deactivate_product_syncer',
                        nonce: '<?php echo esc_js(wp_create_nonce('payplus_product_sync')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.css('color', 'green').text('<?php echo esc_js(__('Deactivated successfully. Reloading...', 'payplus-payment-gateway')); ?>');
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            $btn.prop('disabled', false);
                            $status.css('color', 'red').text('<?php echo esc_js(__('Error:', 'payplus-payment-gateway')); ?> ' + (response.data.message || '<?php echo esc_js(__('Unknown error', 'payplus-payment-gateway')); ?>'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false);
                        $status.css('color', 'red').text('<?php echo esc_js(__('AJAX Error:', 'payplus-payment-gateway')); ?> ' + error);
                    }
                });
            });

            $('#payplus-auto-sync-checkbox').on('change', function() {
                var enabled = $(this).is(':checked') ? 'yes' : 'no';
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'payplus_toggle_auto_sync',
                        nonce: '<?php echo esc_js(wp_create_nonce('payplus_product_sync')); ?>',
                        enabled: enabled
                    }
                });
            });

            var allProductsData = [];
            var allCommerceData = [];
            var totalProducts = 0;
            var currentOffset = 0;
            var currentFormat = 'woocommerce';

            $('#payplus-product-sync-form').on('submit', function(e) {
                e.preventDefault();
                
                if (!confirm('<?php echo esc_js(__('Are you sure you want to get all products data?', 'payplus-payment-gateway')); ?>')) {
                    return;
                }

                var $form = $(this);
                var $button = $form.find('button[name="start_sync"]');
                var productsPerBatch = parseInt($('#products_per_batch').val());
                
                // Reset data
                allProductsData = [];
                allCommerceData = [];
                totalProducts = 0;
                currentOffset = 0;
                currentFormat = 'woocommerce';
                
                // Disable form and show progress
                $button.prop('disabled', true);
                $form.find('input').prop('disabled', true);
                $('#payplus-sync-progress').show();
                $('#payplus-sync-results').hide();
                $('#payplus-commerce-results').hide();
                
                $('#payplus-sync-status').text('<?php echo esc_js(__('Loading WooCommerce products...', 'payplus-payment-gateway')); ?>');
                $('#payplus-sync-progressbar').val(0);
                
                // Start loading products
                loadProductsBatch(productsPerBatch, $button, $form);
            });

            function loadProductsBatch(batchSize, $button, $form) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'payplus_get_products_json',
                        nonce: '<?php echo esc_js(wp_create_nonce('payplus_product_sync')); ?>',
                        offset: currentOffset,
                        limit: batchSize,
                        format: currentFormat
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            
                            if (currentFormat === 'woocommerce') {
                                allProductsData = allProductsData.concat(data.products);
                            } else {
                                allCommerceData = allCommerceData.concat(data.products);
                            }
                            
                            totalProducts = data.total;
                            currentOffset += batchSize;
                            
                            var progress = Math.min((data.processed / totalProducts) * 100, 100);
                            $('#payplus-sync-progressbar').val(progress);
                            
                            var formatLabel = currentFormat === 'woocommerce' ? 'WooCommerce' : 'Commerce';
                            $('#payplus-sync-status').text('<?php echo esc_js(__('Loading', 'payplus-payment-gateway')); ?> ' + formatLabel + ' <?php echo esc_js(__('products:', 'payplus-payment-gateway')); ?> ' + data.processed + ' / ' + totalProducts);
                            $('#payplus-sync-details').text('<?php echo esc_js(__('Batch size:', 'payplus-payment-gateway')); ?> ' + batchSize + ' | <?php echo esc_js(__('Current offset:', 'payplus-payment-gateway')); ?> ' + currentOffset);
                            
                            if (data.has_more) {
                                // Load next batch
                                setTimeout(function() {
                                    loadProductsBatch(batchSize, $button, $form);
                                }, 100);
                            } else {
                                // Check if we need to load commerce format
                                if (currentFormat === 'woocommerce') {
                                    displayResults('woocommerce');
                                    // Now load commerce format
                                    currentOffset = 0;
                                    currentFormat = 'commerce';
                                    $('#payplus-sync-progressbar').val(0);
                                    loadProductsBatch(batchSize, $button, $form);
                                } else {
                                    // All done
                                    displayResults('commerce');
                                    $button.prop('disabled', false);
                                    $form.find('input').prop('disabled', false);
                                    $('#payplus-sync-status').html('<span style="color: green;"><?php echo esc_js(__('Complete! Loaded', 'payplus-payment-gateway')); ?> ' + allProductsData.length + ' <?php echo esc_js(__('products in both formats', 'payplus-payment-gateway')); ?></span>');
                                }
                            }
                        } else {
                            $('#payplus-sync-status').html('<span style="color: red;"><?php echo esc_js(__('Error:', 'payplus-payment-gateway')); ?> ' + (response.data.message || '<?php echo esc_js(__('Unknown error', 'payplus-payment-gateway')); ?>') + '</span>');
                            $button.prop('disabled', false);
                            $form.find('input').prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#payplus-sync-status').html('<span style="color: red;"><?php echo esc_js(__('AJAX Error:', 'payplus-payment-gateway')); ?> ' + error + '</span>');
                        $button.prop('disabled', false);
                        $form.find('input').prop('disabled', false);
                    }
                });
            }

            function displayResults(format) {
                if (format === 'woocommerce') {
                    $('#payplus-sync-results').show();
                    var jsonOutput = JSON.stringify(allProductsData, null, 2);
                    var resultHtml = '<div style="margin-bottom: 10px;">';
                    resultHtml += '<button id="copy-wc-json-btn" class="button"><?php echo esc_js(__('Copy JSON to Clipboard', 'payplus-payment-gateway')); ?></button> ';
                    resultHtml += '<button id="download-wc-json-btn" class="button"><?php echo esc_js(__('Download JSON File', 'payplus-payment-gateway')); ?></button>';
                    resultHtml += '</div>';
                    resultHtml += '<textarea readonly style="width: 100%; height: 400px; font-family: monospace; font-size: 12px; padding: 10px;">' + jsonOutput + '</textarea>';
                    
                    $('#payplus-sync-results-content').html(resultHtml);
                    
                    // Copy to clipboard handler
                    $('#copy-wc-json-btn').on('click', function() {
                        var $textarea = $('#payplus-sync-results-content textarea');
                        $textarea.select();
                        document.execCommand('copy');
                        alert('<?php echo esc_js(__('JSON copied to clipboard!', 'payplus-payment-gateway')); ?>');
                    });
                    
                    // Download JSON handler
                    $('#download-wc-json-btn').on('click', function() {
                        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(jsonOutput);
                        var downloadAnchorNode = document.createElement('a');
                        downloadAnchorNode.setAttribute("href", dataStr);
                        downloadAnchorNode.setAttribute("download", "payplus-woocommerce-products-" + Date.now() + ".json");
                        document.body.appendChild(downloadAnchorNode);
                        downloadAnchorNode.click();
                        downloadAnchorNode.remove();
                    });
                } else {
                    $('#payplus-commerce-results').show();
                    var jsonOutput = JSON.stringify(allCommerceData, null, 2);
                    var resultHtml = '<div style="margin-bottom: 10px;">';
                    resultHtml += '<button id="copy-commerce-json-btn" class="button button-primary"><?php echo esc_js(__('Copy Commerce JSON to Clipboard', 'payplus-payment-gateway')); ?></button> ';
                    resultHtml += '<button id="download-commerce-json-btn" class="button button-primary"><?php echo esc_js(__('Download Commerce JSON File', 'payplus-payment-gateway')); ?></button> ';
                    resultHtml += '<button id="send-commerce-json-btn" class="button" style="background:#00a32a;color:#fff;border-color:#00a32a;"><?php echo esc_js(__('Send to PayPlus', 'payplus-payment-gateway')); ?></button>';
                    resultHtml += '<span id="send-commerce-status" style="margin-left:10px;display:none;"></span>';
                    resultHtml += '</div>';
                    resultHtml += '<textarea readonly style="width: 100%; height: 400px; font-family: monospace; font-size: 12px; padding: 10px;">' + jsonOutput + '</textarea>';
                    
                    $('#payplus-commerce-results-content').html(resultHtml);
                    
                    // Copy to clipboard handler
                    $('#copy-commerce-json-btn').on('click', function() {
                        var $textarea = $('#payplus-commerce-results-content textarea');
                        $textarea.select();
                        document.execCommand('copy');
                        alert('<?php echo esc_js(__('Commerce JSON copied to clipboard!', 'payplus-payment-gateway')); ?>');
                    });
                    
                    // Download JSON handler
                    $('#download-commerce-json-btn').on('click', function() {
                        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(jsonOutput);
                        var downloadAnchorNode = document.createElement('a');
                        downloadAnchorNode.setAttribute("href", dataStr);
                        downloadAnchorNode.setAttribute("download", "payplus-commerce-products-" + Date.now() + ".json");
                        document.body.appendChild(downloadAnchorNode);
                        downloadAnchorNode.click();
                        downloadAnchorNode.remove();
                    });

                    // Send to PayPlus handler
                    $('#send-commerce-json-btn').on('click', function() {
                        var $btn = $(this);
                        var $status = $('#send-commerce-status');

                        if (!confirm('<?php echo esc_js(__('Send all products to PayPlus?', 'payplus-payment-gateway')); ?>')) {
                            return;
                        }

                        $btn.prop('disabled', true);
                        $status.show().css('color', '#666').text('<?php echo esc_js(__('Sending...', 'payplus-payment-gateway')); ?>');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'payplus_send_products_to_gateway',
                                nonce: '<?php echo esc_js(wp_create_nonce('payplus_product_sync')); ?>',
                                products_json: JSON.stringify(allCommerceData)
                            },
                            success: function(response) {
                                $btn.prop('disabled', false);
                                if (response.success) {
                                    $status.css('color', 'green').text('<?php echo esc_js(__('Sent successfully!', 'payplus-payment-gateway')); ?> (HTTP ' + response.data.status_code + ')');
                                } else {
                                    $status.css('color', 'red').text('<?php echo esc_js(__('Error:', 'payplus-payment-gateway')); ?> ' + (response.data.message || '<?php echo esc_js(__('Unknown error', 'payplus-payment-gateway')); ?>'));
                                }
                            },
                            error: function(xhr, status, error) {
                                $btn.prop('disabled', false);
                                $status.css('color', 'red').text('<?php echo esc_js(__('AJAX Error:', 'payplus-payment-gateway')); ?> ' + error);
                            }
                        });
                    });
                }
            }
        });
        </script>

        <style>
        .payplus-syncer-stats,
        .payplus-syncer-sample,
        .payplus-syncer-activation,
        .payplus-syncer-actions {
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .payplus-syncer-stats h2,
        .payplus-syncer-sample h2,
        .payplus-syncer-activation h2,
        .payplus-syncer-actions h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        #payplus-sync-progressbar {
            -webkit-appearance: none;
            appearance: none;
        }
        
        #payplus-sync-progressbar::-webkit-progress-bar {
            background-color: #f0f0f0;
            border-radius: 3px;
        }
        
        #payplus-sync-progressbar::-webkit-progress-value {
            background-color: #2271b1;
            border-radius: 3px;
        }
        
        #payplus-sync-progressbar::-moz-progress-bar {
            background-color: #2271b1;
            border-radius: 3px;
        }
        </style>
        <?php
    }

    /**
     * Get total count of products
     *
     * @return int
     */
    private static function get_products_count()
    {
        $args = array(
            'status' => array('publish', 'draft', 'pending', 'private'),
            'limit' => -1,
            'return' => 'ids',
        );
        
        $products = wc_get_products($args);
        return count($products);
    }

    /**
     * Get sample products for display
     *
     * @param int $limit Number of products to retrieve
     * @return array
     */
    private static function get_sample_products($limit = 10)
    {
        $args = array(
            'status' => 'publish',
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        $products = wc_get_products($args);
        $sample_products = array();
        
        foreach ($products as $product) {
            $sample_products[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku() ? $product->get_sku() : __('N/A', 'payplus-payment-gateway'),
                'price' => wc_price($product->get_price()),
                'stock' => $product->is_in_stock() ? __('In Stock', 'payplus-payment-gateway') : __('Out of Stock', 'payplus-payment-gateway'),
                'status' => ucfirst($product->get_status()),
            );
        }
        
        return $sample_products;
    }

    /**
     * Get all products data with complete information
     *
     * @param int $offset Starting offset
     * @param int $limit Number of products per batch
     * @return array
     */
    public static function get_all_products_data($offset = 0, $limit = 50)
    {
        $args = array(
            'status' => array('publish', 'draft', 'pending', 'private'),
            'limit' => $limit,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
        );
        
        $products = wc_get_products($args);
        $products_data = array();
        
        foreach ($products as $product) {
            $products_data[] = self::get_complete_product_data($product);
        }
        
        return $products_data;
    }

    /**
     * Get products for syncing (legacy method, kept for compatibility)
     *
     * @param int $offset Starting offset
     * @param int $limit Number of products per batch
     * @return array
     */
    public static function get_products_for_sync($offset = 0, $limit = 50)
    {
        return self::get_all_products_data($offset, $limit);
    }

    /**
     * Get complete product data with ALL information
     *
     * @param WC_Product $product
     * @return array
     */
    private static function get_complete_product_data($product)
    {
        $product_id = $product->get_id();
        $product_type = $product->get_type();

        $data = array(
            // Basic Information
            'id' => $product_id,
            'parent_id' => $product->get_parent_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'permalink' => get_permalink($product_id),
            'type' => $product_type,
            'status' => $product->get_status(),
            'featured' => $product->get_featured(),
            'catalog_visibility' => $product->get_catalog_visibility(),
            'sku' => $product->get_sku(),
            
            // Descriptions
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            
            // Pricing
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'price_html' => $product->get_price_html(),
            'on_sale' => $product->is_on_sale(),
            'date_on_sale_from' => $product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->date('Y-m-d H:i:s') : null,
            'date_on_sale_to' => $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->date('Y-m-d H:i:s') : null,
            
            // Tax
            'tax_status' => $product->get_tax_status(),
            'tax_class' => $product->get_tax_class(),
            
            // Stock
            'manage_stock' => $product->get_manage_stock(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'backorders' => $product->get_backorders(),
            'backorders_allowed' => $product->backorders_allowed(),
            'backordered' => $product->is_on_backorder(),
            'low_stock_amount' => $product->get_low_stock_amount(),
            'sold_individually' => $product->get_sold_individually(),
            
            // Shipping
            'weight' => $product->get_weight(),
            'length' => $product->get_length(),
            'width' => $product->get_width(),
            'height' => $product->get_height(),
            'dimensions' => array(
                'length' => $product->get_length(),
                'width' => $product->get_width(),
                'height' => $product->get_height(),
            ),
            'shipping_class' => $product->get_shipping_class(),
            'shipping_class_id' => $product->get_shipping_class_id(),
            
            // Upsells & Cross-sells
            'upsell_ids' => $product->get_upsell_ids(),
            'cross_sell_ids' => $product->get_cross_sell_ids(),
            
            // Attributes
            'attributes' => self::get_product_attributes_complete($product),
            'default_attributes' => $product->get_default_attributes(),
            
            // Categories & Tags
            'categories' => self::get_product_terms($product_id, 'product_cat'),
            'tags' => self::get_product_terms($product_id, 'product_tag'),
            
            // Images
            'images' => self::get_product_images_complete($product),
            
            // Reviews
            'reviews_allowed' => $product->get_reviews_allowed(),
            'average_rating' => $product->get_average_rating(),
            'rating_count' => $product->get_rating_count(),
            'rating_counts' => $product->get_rating_counts(),
            'review_count' => $product->get_review_count(),
            
            // Purchase note
            'purchase_note' => $product->get_purchase_note(),
            
            // Menu order
            'menu_order' => $product->get_menu_order(),
            
            // Virtual & Downloadable
            'virtual' => $product->is_virtual(),
            'downloadable' => $product->is_downloadable(),
            'downloads' => self::get_product_downloads($product),
            'download_limit' => $product->get_download_limit(),
            'download_expiry' => $product->get_download_expiry(),
            
            // Dates
            'date_created' => $product->get_date_created() ? $product->get_date_created()->date('Y-m-d H:i:s') : null,
            'date_modified' => $product->get_date_modified() ? $product->get_date_modified()->date('Y-m-d H:i:s') : null,
            
            // Meta data
            'meta_data' => self::get_product_meta_data($product),
            
            // Variations (for variable products)
            'variations' => array(),
            
            // Grouped products
            'grouped_products' => array(),
        );

        // Add variations for variable products
        if ($product_type === 'variable') {
            $data['variations'] = self::get_product_variations($product);
        }

        // Add grouped products
        if ($product_type === 'grouped') {
            $data['grouped_products'] = $product->get_children();
        }

        return $data;
    }

    /**
     * Format a single product for syncing (legacy method for compatibility)
     *
     * @param WC_Product $product
     * @return array
     */
    private static function format_product_for_sync($product)
    {
        return self::get_complete_product_data($product);
    }

    /**
     * Get product images with complete data
     *
     * @param WC_Product $product
     * @return array
     */
    private static function get_product_images_complete($product)
    {
        $images = array();
        
        // Main image
        if ($product->get_image_id()) {
            $images[] = array(
                'id' => $product->get_image_id(),
                'src' => wp_get_attachment_url($product->get_image_id()),
                'name' => get_the_title($product->get_image_id()),
                'alt' => get_post_meta($product->get_image_id(), '_wp_attachment_image_alt', true),
                'position' => 0,
            );
        }
        
        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        $position = 1;
        foreach ($gallery_ids as $image_id) {
            $images[] = array(
                'id' => $image_id,
                'src' => wp_get_attachment_url($image_id),
                'name' => get_the_title($image_id),
                'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
                'position' => $position,
            );
            $position++;
        }
        
        return $images;
    }

    /**
     * Get product attributes with complete data
     *
     * @param WC_Product $product
     * @return array
     */
    private static function get_product_attributes_complete($product)
    {
        $attributes = array();
        
        foreach ($product->get_attributes() as $attribute) {
            if (is_object($attribute)) {
                $attribute_data = array(
                    'id' => $attribute->get_id(),
                    'name' => $attribute->get_name(),
                    'position' => $attribute->get_position(),
                    'visible' => $attribute->get_visible(),
                    'variation' => $attribute->get_variation(),
                    'options' => array(),
                );

                // Get attribute options/values
                if ($attribute->is_taxonomy()) {
                    $terms = $attribute->get_terms();
                    foreach ($terms as $term) {
                        $attribute_data['options'][] = array(
                            'id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                        );
                    }
                } else {
                    $attribute_data['options'] = $attribute->get_options();
                }

                $attributes[] = $attribute_data;
            }
        }
        
        return $attributes;
    }

    /**
     * Get product categories and tags with complete data
     *
     * @param int $product_id
     * @param string $taxonomy
     * @return array
     */
    private static function get_product_terms($product_id, $taxonomy)
    {
        $terms = wp_get_post_terms($product_id, $taxonomy);
        $terms_data = array();

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $terms_data[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }

        return $terms_data;
    }

    /**
     * Get product downloads
     *
     * @param WC_Product $product
     * @return array
     */
    private static function get_product_downloads($product)
    {
        $downloads = array();
        
        foreach ($product->get_downloads() as $download_id => $download) {
            $downloads[] = array(
                'id' => $download_id,
                'name' => $download->get_name(),
                'file' => $download->get_file(),
            );
        }
        
        return $downloads;
    }

    /**
     * Get all product meta data
     *
     * @param WC_Product $product
     * @return array
     */
    private static function get_product_meta_data($product)
    {
        $meta_data = array();
        
        foreach ($product->get_meta_data() as $meta) {
            $meta_data[] = array(
                'id' => $meta->id,
                'key' => $meta->key,
                'value' => $meta->value,
            );
        }
        
        return $meta_data;
    }

    /**
     * Get all variations for a variable product
     *
     * @param WC_Product_Variable $product
     * @return array
     */
    private static function get_product_variations($product)
    {
        $variations = array();
        $variation_ids = $product->get_children();

        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);
            
            if (!$variation) {
                continue;
            }

            $variations[] = array(
                'id' => $variation->get_id(),
                'sku' => $variation->get_sku(),
                'permalink' => get_permalink($variation->get_id()),
                'description' => $variation->get_description(),
                
                // Pricing
                'price' => $variation->get_price(),
                'regular_price' => $variation->get_regular_price(),
                'sale_price' => $variation->get_sale_price(),
                'on_sale' => $variation->is_on_sale(),
                'date_on_sale_from' => $variation->get_date_on_sale_from() ? $variation->get_date_on_sale_from()->date('Y-m-d H:i:s') : null,
                'date_on_sale_to' => $variation->get_date_on_sale_to() ? $variation->get_date_on_sale_to()->date('Y-m-d H:i:s') : null,
                
                // Stock
                'manage_stock' => $variation->get_manage_stock(),
                'stock_quantity' => $variation->get_stock_quantity(),
                'stock_status' => $variation->get_stock_status(),
                'backorders' => $variation->get_backorders(),
                'backorders_allowed' => $variation->backorders_allowed(),
                
                // Shipping
                'weight' => $variation->get_weight(),
                'length' => $variation->get_length(),
                'width' => $variation->get_width(),
                'height' => $variation->get_height(),
                'shipping_class' => $variation->get_shipping_class(),
                'shipping_class_id' => $variation->get_shipping_class_id(),
                
                // Tax
                'tax_status' => $variation->get_tax_status(),
                'tax_class' => $variation->get_tax_class(),
                
                // Images
                'image' => $variation->get_image_id() ? array(
                    'id' => $variation->get_image_id(),
                    'src' => wp_get_attachment_url($variation->get_image_id()),
                    'name' => get_the_title($variation->get_image_id()),
                    'alt' => get_post_meta($variation->get_image_id(), '_wp_attachment_image_alt', true),
                ) : null,
                
                // Attributes
                'attributes' => ($variation instanceof WC_Product_Variation) ? $variation->get_variation_attributes() : array(),
                
                // Virtual & Downloadable
                'virtual' => $variation->is_virtual(),
                'downloadable' => $variation->is_downloadable(),
                'downloads' => self::get_product_downloads($variation),
                'download_limit' => $variation->get_download_limit(),
                'download_expiry' => $variation->get_download_expiry(),
                
                // Meta data
                'meta_data' => self::get_product_meta_data($variation),
            );
        }

        return $variations;
    }

    /**
     * Get products in PayPlus Commerce format
     *
     * @param int $offset Starting offset
     * @param int $limit Number of products per batch
     * @return array
     */
    public static function get_commerce_format_data($offset = 0, $limit = 50)
    {
        $args = array(
            'status' => array('publish', 'draft', 'pending', 'private'),
            'limit' => $limit,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
        );
        
        $products = wc_get_products($args);
        $products_data = array();
        
        $payplus_settings = get_option('woocommerce_payplus-payment-gateway_settings');
        $testMode = isset($payplus_settings['api_test_mode']) && $payplus_settings['api_test_mode'] === 'yes';
        $pageUid  = $testMode
            ? (isset($payplus_settings['dev_payment_page_id']) ? $payplus_settings['dev_payment_page_id'] : '')
            : (isset($payplus_settings['payment_page_id']) ? $payplus_settings['payment_page_id'] : '');

        $company = array(
            'id' => self::get_site_uid(),
        );
        
        foreach ($products as $product) {
            $products_data[] = self::transform_to_commerce_format($product, $company);
        }
        
        return $products_data;
    }

    /**
     * Transform WooCommerce product to PayPlus Commerce format
     *
     * @param WC_Product $product
     * @param array $company Company info with 'id' (payment_page_uid)
     * @param array $exclude_variation_ids Variation IDs to exclude.
     * @return array
     */
    private static function transform_to_commerce_format($product, $company, $exclude_variation_ids = array())
    {
        $product_id = intval($product->get_id());
        $product_type = strval($product->get_type());
        $currency = strval(get_woocommerce_currency());
        
        // Get product media
        $media = self::compose_product_media_for_bus($product);
        
        // Get product options (for variants)
        $product_options = self::get_product_options($product);
        
        // Get product variants
        $product_variants = array();
        if ($product_type === 'variable') {
            $variation_ids = $product->get_children();
            foreach ($variation_ids as $variation_id) {
                if (in_array($variation_id, $exclude_variation_ids, true)) {
                    continue;
                }
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $product_variants[] = $variation;
                }
            }
        } else {
            $product_variants[] = $product;
        }
        
        // Get categories (collections) with media
        $categories = self::transform_categories_to_handle($product_id);
        
        // Get tags
        $tags = self::get_tags_from_product($product);
        
        // Transform variants using composeProductVariantForBus
        $variants = self::compose_product_variant_for_bus($product_variants, $product_options, $product);
        
        // Get VAT type from variants
        $vat_type = self::get_product_vat_type($product_variants);
        
        // Get description (bodyHtml/body_html equivalent)
        $description = $product->get_description();
        if (!$description || $description === '') {
            $description = $product->get_short_description();
        }
        // Convert HTML entities and clean up
        $description = $description ?: '';
        
        // Determine manage_inventory from first variant
        // Matches: product.variants && product.variants.length > 0 && product.variants[0].inventoryManagement ? true : false
        $manage_inventory = false;
        if (!empty($product_variants) && $product_variants[0] instanceof WC_Product) {
            $first_variant = $product_variants[0];
            $manage_stock = $first_variant->get_manage_stock();
            $manage_inventory = ($manage_stock === true || $manage_stock === 'yes') ? true : false;
        }

        $commerce_product = array(
            'company_id' => strval($company['id']),
            'name' => strval($product->get_name()),
            'external_id' => strval($product_id),
            'description' => strval($description),
            'valid' => strtolower($product->get_status()) === 'publish',
            'vat_type' => $vat_type,
            'default' => false,
            'system_product' => false,
            'guide_document_url' => null,
            'currency_code' => !empty($variants) && isset($variants[0]['pricing'][0]['currency_code']) 
                ? strval($variants[0]['pricing'][0]['currency_code']) 
                : strval($currency),
            'has_variants' => count($variants) > 1,
            'selling_unit_type' => 'UNITS',
            'manage_inventory' => $manage_inventory,
            'is_serial' => false,
            'variants' => $variants,
            'categories_to_handle' => $categories,
            'tags_to_handle' => $tags,
            'media_to_handle' => $media,
            'external_ids' => array(
                array(
                    'platform_id' => intval(2),
                    'external_id' => strval($product_id),
                    'external_id_source_field' => 'id',
                ),
            ),
            'source_type' => 'woocommerce',
        );

        return $commerce_product;
    }

    /**
     * Transform simple product to variant format
     *
     * @param WC_Product $product
     * @param string $currency
     * @param bool $is_main
     * @return array
     */
    private static function transform_simple_product_variant($product, $currency, $is_main = true)
    {
        // Properly round prices like TypeScript (Math.round(price * 100) / 100)
        $price = round(floatval($product->get_price() ?: 0) * 100) / 100;
        $regular_price = round(floatval($product->get_regular_price() ?: $price) * 100) / 100;
        $sale_price = round(floatval($product->get_sale_price() ?: 0) * 100) / 100;

        // Determine inventory status
        $stock_quantity = $product->get_stock_quantity();
        $inventory_status = 'AVAILABLE';
        if ($stock_quantity !== null) {
            $stock_quantity = intval($stock_quantity);
            if ($stock_quantity > 10) {
                $inventory_status = 'AVAILABLE';
            } elseif ($stock_quantity > 0) {
                $inventory_status = 'SLOW';
            } else {
                $inventory_status = 'DEAD';
            }
        } elseif (!$product->is_in_stock()) {
            $inventory_status = 'DEAD';
        }

        $backorders = $product->get_backorders();
        $continue_selling = boolval($backorders === 'yes' || $backorders === 'notify');
        
        $sku = $product->get_sku();
        $sku_value = $sku && $sku !== '' ? strval($sku) : null;

        $variant = array(
            'id' => intval(0),
            'uuid' => strval(''),
            'sku' => $sku_value,
            'name' => strval($product->get_name()),
            'is_main' => boolval($is_main),
            'system_default' => boolval(false),
            'inventory_status' => strval($inventory_status),
            'inventory_quantity' => $stock_quantity !== null ? intval($stock_quantity) : 0,
            'continue_selling_out_of_stock' => $continue_selling,
            'item_type' => strval('P'),
            'pricing' => array(
                array(
                    'uuid' => strval(''),
                    'currency_code' => strval($currency),
                    'value' => floatval($price),
                    'price' => floatval($price),
                    'start_at' => strval(gmdate('Y-m-d\TH:i:s\Z')),
                    'finish_at' => null,
                )
            ),
            'external_ids' => array(
                array(
                    'platform_id' => intval(2),
                    'external_id' => intval($product->get_id()),
                    'external_id_source_field' => strval('id')
                )
            ),
            'media' => self::transform_variant_media($product),
            'properties' => array(),
            'created_at' => $product->get_date_created() ? strval($product->get_date_created()->date('c')) : strval(gmdate('c')),
            'updated_at' => $product->get_date_modified() ? strval($product->get_date_modified()->date('c')) : strval(gmdate('c')),
            'deleted_at' => null,
            'is_deleted' => boolval(false),
        );

        // Add sale price if exists
        if ($sale_price > 0 && $sale_price < $regular_price) {
            $date_on_sale_from = $product->get_date_on_sale_from();
            $date_on_sale_to = $product->get_date_on_sale_to();
            
            $variant['pricing'][] = array(
                'uuid' => strval(''),
                'currency_code' => strval($currency),
                'value' => floatval($sale_price),
                'price' => floatval($sale_price),
                'start_at' => $date_on_sale_from ? strval($date_on_sale_from->date('c')) : strval(gmdate('Y-m-d\TH:i:s\Z')),
                'finish_at' => $date_on_sale_to ? strval($date_on_sale_to->date('c')) : null,
            );
        }

        return $variant;
    }

    /**
     * Transform variable product variations to commerce variants
     *
     * @param WC_Product_Variable $product
     * @param string $currency
     * @return array
     */
    private static function transform_variable_product_variants($product, $currency)
    {
        $variants = array();
        $variation_ids = $product->get_children();
        $is_first = true;

        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);
            
            if (!$variation) {
                continue;
            }

            // Properly round prices
            $price = round(floatval($variation->get_price() ?: 0) * 100) / 100;
            $regular_price = round(floatval($variation->get_regular_price() ?: $price) * 100) / 100;
            $sale_price = round(floatval($variation->get_sale_price() ?: 0) * 100) / 100;

            // Determine inventory status
            $stock_quantity = $variation->get_stock_quantity();
            $inventory_status = 'AVAILABLE';
            if ($stock_quantity !== null) {
                $stock_quantity = intval($stock_quantity);
                if ($stock_quantity > 10) {
                    $inventory_status = 'AVAILABLE';
                } elseif ($stock_quantity > 0) {
                    $inventory_status = 'SLOW';
                } else {
                    $inventory_status = 'DEAD';
                }
            } elseif (!$variation->is_in_stock()) {
                $inventory_status = 'DEAD';
            }

            // Get variation attributes as properties
            $properties = array();
            $attributes = ($variation instanceof WC_Product_Variation) ? $variation->get_variation_attributes() : array();
            foreach ($attributes as $attr_name => $attr_value) {
                // Remove 'attribute_' prefix if present
                $property_name = str_replace('attribute_', '', $attr_name);
                $property_name = str_replace('pa_', '', $property_name); // Remove taxonomy prefix
                $property_name = ucwords(str_replace('-', ' ', $property_name));
                
                $properties[] = array(
                    'property_type_uid' => intval(0), // Will be created/matched on commerce side
                    'value' => strval($attr_value),
                    'property_type_name' => strval($property_name),
                );
            }

            $backorders = $variation->get_backorders();
            $continue_selling = boolval($backorders === 'yes' || $backorders === 'notify');
            
            $sku = $variation->get_sku();
            $sku_value = $sku && $sku !== '' ? strval($sku) : null;

            $variant_data = array(
                'id' => intval(0),
                'uuid' => strval(''),
                'sku' => $sku_value,
                'name' => strval($variation->get_name()),
                'is_main' => boolval($is_first),
                'system_default' => boolval(false),
                'inventory_status' => strval($inventory_status),
                'inventory_quantity' => $stock_quantity !== null ? intval($stock_quantity) : 0,
                'continue_selling_out_of_stock' => $continue_selling,
                'item_type' => strval('P'),
                'pricing' => array(
                    array(
                        'uuid' => strval(''),
                        'currency_code' => strval($currency),
                        'value' => floatval($price),
                        'price' => floatval($price),
                        'start_at' => strval(gmdate('Y-m-d\TH:i:s\Z')),
                        'finish_at' => null,
                    )
                ),
                'external_ids' => array(
                    array(
                        'platform_id' => intval(2),
                        'external_id' => intval($variation_id),
                        'external_id_source_field' => strval('id')
                    )
                ),
                'media' => self::transform_variant_media($variation),
                'properties' => $properties,
                'created_at' => $variation->get_date_created() ? strval($variation->get_date_created()->date('c')) : strval(gmdate('c')),
                'updated_at' => $variation->get_date_modified() ? strval($variation->get_date_modified()->date('c')) : strval(gmdate('c')),
                'deleted_at' => null,
                'is_deleted' => boolval(false),
            );

            // Add sale price if exists
            if ($sale_price > 0 && $sale_price < $regular_price) {
                $date_on_sale_from = $variation->get_date_on_sale_from();
                $date_on_sale_to = $variation->get_date_on_sale_to();
                
                $variant_data['pricing'][] = array(
                    'uuid' => strval(''),
                    'currency_code' => strval($currency),
                    'value' => floatval($sale_price),
                    'price' => floatval($sale_price),
                    'start_at' => $date_on_sale_from ? strval($date_on_sale_from->date('c')) : strval(gmdate('Y-m-d\TH:i:s\Z')),
                    'finish_at' => $date_on_sale_to ? strval($date_on_sale_to->date('c')) : null,
                );
            }

            $variants[] = $variant_data;
            $is_first = false;
        }

        return $variants;
    }

    /**
     * Transform product categories for commerce (legacy method)
     *
     * @param int $product_id
     * @return array
     */
    private static function transform_categories($product_id)
    {
        $categories = array();
        $terms = wp_get_post_terms($product_id, 'product_cat');

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[] = array(
                    'id' => intval($term->term_id),
                    'uuid' => strval($term->slug),
                    'name' => strval($term->name),
                );
            }
        }

        return $categories;
    }

    /**
     * Transform product categories to categories_to_handle format with media
     *
     * @param int $product_id
     * @return array
     */
    private static function transform_categories_to_handle($product_id)
    {
        $categories = array();
        $terms = wp_get_post_terms($product_id, 'product_cat');

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                // Get category image/thumbnail
                $image_id = get_term_meta($term->term_id, 'thumbnail_id', true);
                $category_images = array();
                
                if ($image_id) {
                    $image_url = wp_get_attachment_url($image_id);
                    if ($image_url) {
                        // Format as object similar to Shopify's structure
                        $category_images[] = array(
                            'id' => intval($image_id),
                            'url' => strval($image_url),
                            'altText' => get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: '',
                            'width' => 0, // WooCommerce doesn't store this in term meta
                            'height' => 0, // WooCommerce doesn't store this in term meta
                        );
                    }
                }
                
                $categories[] = array(
                    'id' => intval($term->term_id),
                    'name' => strval($term->name),
                    'media_to_handle' => self::compose_category_media_for_bus($category_images),
                );
            }
        }

        return $categories;
    }

    /**
     * Transform product tags for commerce (legacy method)
     *
     * @param int $product_id
     * @return array
     */
    private static function transform_tags($product_id)
    {
        $tags = array();
        $terms = wp_get_post_terms($product_id, 'product_tag');

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $tags[] = strval($term->name);
            }
        }

        return $tags;
    }

    /**
     * Get tags from product (for tags_to_handle)
     * Handles both string and array formats
     *
     * @param WC_Product $product
     * @return array
     */
    private static function get_tags_from_product($product)
    {
        $tags = array();
        $product_id = $product->get_id();
        $terms = wp_get_post_terms($product_id, 'product_tag');

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $tags[] = strval($term->name);
            }
        }

        return $tags;
    }

    /**
     * Transform product media for commerce (returns URLs for media service to download) - legacy method
     *
     * @param WC_Product $product
     * @return array
     */
    private static function transform_product_media($product)
    {
        $media = array();
        
        // Main image
        if ($product->get_image_id()) {
            $image_url = wp_get_attachment_url($product->get_image_id());
            if ($image_url) {
                $media[] = strval($image_url);
            }
        }
        
        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $image_id) {
            $image_url = wp_get_attachment_url($image_id);
            if ($image_url) {
                $media[] = strval($image_url);
            }
        }
        
        return $media;
    }

    /**
     * Compose product media for bus (media_to_handle format)
     * Returns array of media objects with {url, mimetype, name}
     *
     * @param WC_Product $product
     * @return array
     */
    private static function compose_product_media_for_bus($product)
    {
        $media = array();
        
        // Main image
        if ($product->get_image_id()) {
            $image_id = $product->get_image_id();
            $image_url = wp_get_attachment_url($image_id);
            if ($image_url) {
                $jpg_url = strtok($image_url, '?'); // Remove query params
                $extension = strtolower(pathinfo($jpg_url, PATHINFO_EXTENSION));
                $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
                
                if (in_array($extension, $allowed_extensions)) {
                    $name = basename($jpg_url) ?: "image_{$image_id}.{$extension}";
                    // Use image/jpg for jpg files to match Shopify format
                    $mimetype = ($extension === 'jpg' || $extension === 'jpeg') ? 'image/jpg' : "image/{$extension}";
                    
                    $media[] = array(
                        'url' => strval($jpg_url),
                        'mimetype' => $mimetype,
                        'name' => $name,
                    );
                }
            }
        }
        
        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $image_id) {
            $image_url = wp_get_attachment_url($image_id);
            if ($image_url) {
                $jpg_url = strtok($image_url, '?'); // Remove query params
                $extension = strtolower(pathinfo($jpg_url, PATHINFO_EXTENSION));
                $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
                
                if (in_array($extension, $allowed_extensions)) {
                    $name = basename($jpg_url) ?: "image_{$image_id}.{$extension}";
                    // Use image/jpg for jpg files to match Shopify format
                    $mimetype = ($extension === 'jpg' || $extension === 'jpeg') ? 'image/jpg' : "image/{$extension}";
                    
                    $media[] = array(
                        'url' => strval($jpg_url),
                        'mimetype' => $mimetype,
                        'name' => $name,
                    );
                }
            }
        }
        
        return $media;
    }

    /**
     * Compose category media for bus (media_to_handle format for categories)
     * Returns array of media objects with {url, mimetype, name}
     * Accepts array of objects with {id, url, altText, width, height} or URL strings
     *
     * @param array $images Array of image objects or URL strings
     * @return array
     */
    private static function compose_category_media_for_bus($images)
    {
        $media = array();
        
        if (is_array($images)) {
            foreach ($images as $image_data) {
                // Handle both URL strings and objects with url property
                $image_url = is_string($image_data) ? $image_data : (isset($image_data['url']) ? $image_data['url'] : null);
                $image_id = is_array($image_data) && isset($image_data['id']) ? $image_data['id'] : null;
                
                if ($image_url) {
                    $jpg_url = strtok($image_url, '?'); // Remove query params
                    $extension = strtolower(pathinfo($jpg_url, PATHINFO_EXTENSION));
                    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
                    
                    if (in_array($extension, $allowed_extensions)) {
                        $name = basename($jpg_url) ?: ($image_id ? "image_{$image_id}.{$extension}" : "image_" . uniqid() . ".{$extension}");
                        // Use image/jpg for jpg files to match Shopify format
                        $mimetype = ($extension === 'jpg' || $extension === 'jpeg') ? 'image/jpg' : "image/{$extension}";
                        
                        $media[] = array(
                            'url' => strval($jpg_url),
                            'mimetype' => $mimetype,
                            'name' => $name,
                        );
                    }
                }
            }
        }
        
        return $media;
    }

    /**
     * Transform variant media for commerce
     *
     * @param WC_Product $product
     * @return array
     */
    private static function transform_variant_media($product)
    {
        $media = array();
        
        if ($product->get_image_id()) {
            $image_url = wp_get_attachment_url($product->get_image_id());
            if ($image_url) {
                $media[] = strval($image_url);
            }
        }
        
        return $media;
    }

    /**
     * Get product VAT type from variants
     * Returns 1 for VAT_INCLUDED, 0 for VAT_EXEMPT
     *
     * @param array $variants Array of WC_Product objects
     * @return int
     */
    private static function get_product_vat_type($variants)
    {
        if (empty($variants)) {
            return 1; // Default to VAT_INCLUDED
        }
        
        // Check first variant's tax status
        $first_variant = $variants[0];
        if ($first_variant instanceof WC_Product) {
            if ($first_variant->get_tax_status() === 'none') {
                return 0; // VAT_EXEMPT
            }
        }
        
        return 1; // VAT_INCLUDED
    }

    /**
     * Get product options (for variant composition)
     *
     * @param WC_Product $product
     * @return array
     */
    private static function get_product_options($product)
    {
        $options = array();
        $attributes = $product->get_attributes();
        
        foreach ($attributes as $attribute) {
            if (is_object($attribute)) {
                $option = array(
                    'name' => $attribute->get_name(),
                    'position' => $attribute->get_position(),
                    'values' => array(),
                );
                
                if ($attribute->is_taxonomy()) {
                    $terms = $attribute->get_terms();
                    foreach ($terms as $term) {
                        $option['values'][] = $term->name;
                    }
                } else {
                    $option['values'] = $attribute->get_options();
                }
                
                $options[] = $option;
            }
        }
        
        return $options;
    }

    /**
     * Compose product variant for bus (new schema format)
     *
     * @param array $product_variants Array of WC_Product objects
     * @param array $product_options Array of product options
     * @param WC_Product $product Parent product
     * @return array
     */
    private static function compose_product_variant_for_bus($product_variants, $product_options, $product)
    {
        $variants = array();
        $currency = strval(get_woocommerce_currency());
        $is_first = true;
        
        foreach ($product_variants as $variant) {
            if (!($variant instanceof WC_Product)) {
                continue;
            }
            
            // Properly round prices
            $price = round(floatval($variant->get_price() ?: 0) * 100) / 100;
            $regular_price = round(floatval($variant->get_regular_price() ?: $price) * 100) / 100;
            $sale_price = round(floatval($variant->get_sale_price() ?: 0) * 100) / 100;
            
            // Determine inventory status
            $stock_quantity = $variant->get_stock_quantity();
            $inventory_status = 'AVAILABLE';
            if ($stock_quantity !== null) {
                $stock_quantity = intval($stock_quantity);
                if ($stock_quantity > 10) {
                    $inventory_status = 'AVAILABLE';
                } elseif ($stock_quantity > 0) {
                    $inventory_status = 'SLOW';
                } else {
                    $inventory_status = 'DEAD';
                }
            } elseif (!$variant->is_in_stock()) {
                $inventory_status = 'DEAD';
            }
            
            // Get variant properties from attributes (properties_to_handle format)
            $properties = array();
            $options_array = array(); // For variant options
            
            if ($variant instanceof WC_Product_Variation) {
                $attributes = $variant->get_variation_attributes();
                foreach ($attributes as $attr_name => $attr_value) {
                    // Remove 'attribute_' prefix if present
                    $property_name = str_replace('attribute_', '', $attr_name);
                    $property_name = str_replace('pa_', '', $property_name); // Remove taxonomy prefix
                    $property_name = ucwords(str_replace('-', ' ', $property_name));
                    
                    $properties[] = array(
                        'property_type_name' => strval($property_name),
                        'value' => strval($attr_value),
                    );
                    
                    // Add to options array
                    $options_array[] = strval($attr_value);
                }
            }
            
            $backorders = $variant->get_backorders();
            $continue_selling = ($backorders === 'yes' || $backorders === 'notify');
            
            $sku = $variant->get_sku();
            $sku_value = ($sku && $sku !== '') ? strval($sku) : null;
            
            // Get barcode from _global_unique_id field
            $barcode = $variant->get_meta('_global_unique_id') ?: null;
            $barcode_value = ($barcode && $barcode !== '') ? strval($barcode) : null;
            
            // Build pricing array with milliseconds in timestamps
            $now_millis = self::format_date_with_millis(null); // Current time with milliseconds
            $pricing = array(
                array(
                    'uuid' => '',
                    'currency_code' => $currency,
                    'value' => floatval($price),
                    'price' => floatval($price),
                    'start_at' => $now_millis,
                    'finish_at' => null,
                )
            );
            
            // Add sale price if exists
            if ($sale_price > 0 && $sale_price < $regular_price) {
                $date_on_sale_from = $variant->get_date_on_sale_from();
                $date_on_sale_to = $variant->get_date_on_sale_to();
                
                // Format dates with milliseconds
                $sale_from = $date_on_sale_from ? self::format_date_with_millis($date_on_sale_from) : $now_millis;
                $sale_to = $date_on_sale_to ? self::format_date_with_millis($date_on_sale_to) : null;
                
                $pricing[] = array(
                    'uuid' => '',
                    'currency_code' => $currency,
                    'value' => floatval($sale_price),
                    'price' => floatval($sale_price),
                    'start_at' => $sale_from,
                    'finish_at' => $sale_to,
                );
            }
            
            // Get variant media
            $variant_media = self::compose_variant_media_for_bus($variant);
            
            // Determine if this is the default variant (first variant with default name)
            $is_default_variant = ($is_first && strtolower($variant->get_name()) === strtolower($product->get_name()));
            
            $variant_data = array(
                'id' => 0,
                'uuid' => '',
                'sku' => $sku_value,
                'barcode' => $barcode_value,
                'name' => $is_default_variant ? strval($product->get_name()) : strval($variant->get_name()),
                'is_main' => $is_first,
                'system_default' => $is_default_variant,
                'price' => floatval($price),
                'value' => floatval($price),
                'inventory_status' => $inventory_status,
                'inventory_quantity' => $stock_quantity !== null ? intval($stock_quantity) : 0,
                'continue_selling_out_of_stock' => $continue_selling,
                'item_type' => 'P',
                'pricing' => $pricing,
                'external_ids' => array(
                    array(
                        'platform_id' => 2,
                        'external_id' => intval($variant->get_id()),
                        'external_id_source_field' => 'id'
                    )
                ),
                'media_to_handle' => $variant_media,
                'properties_to_handle' => $properties,
                'options' => $options_array,
                'created_at' => self::format_date_with_millis($variant->get_date_created()),
                'updated_at' => self::format_date_with_millis($variant->get_date_modified()),
                'deleted_at' => null,
                'is_deleted' => false,
            );
            
            $variants[] = $variant_data;
            $is_first = false;
        }
        
        return $variants;
    }

    /**
     * Format date with milliseconds to match Shopify format (e.g., "2025-10-26T21:11:31.000Z")
     *
     * @param WC_DateTime|null $date
     * @return string
     */
    private static function format_date_with_millis($date)
    {
        if ($date && $date instanceof WC_DateTime) {
            // WC_DateTime doesn't have millisecond precision, so use 000
            // Format: YYYY-MM-DDTHH:mm:ss.000Z
            return $date->date('Y-m-d\TH:i:s') . '.000Z';
        }
        // Default to current time with milliseconds from microtime
        $microtime = microtime(true);
        $seconds = floor($microtime);
        $millis = str_pad(intval(($microtime - $seconds) * 1000), 3, '0', STR_PAD_LEFT);
        return gmdate('Y-m-d\TH:i:s', $seconds) . '.' . $millis . 'Z';
    }

    /**
     * Compose variant media for bus (media_to_handle format for variants)
     * Returns array of media objects with {url, mimetype, name}
     *
     * @param WC_Product $variant
     * @return array
     */
    private static function compose_variant_media_for_bus($variant)
    {
        $media = array();
        
        if ($variant->get_image_id()) {
            $image_id = $variant->get_image_id();
            $image_url = wp_get_attachment_url($image_id);
            if ($image_url) {
                $jpg_url = strtok($image_url, '?'); // Remove query params
                $extension = strtolower(pathinfo($jpg_url, PATHINFO_EXTENSION));
                $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
                
                if (in_array($extension, $allowed_extensions)) {
                    $name = basename($jpg_url) ?: "image_{$image_id}.{$extension}";
                    // Use image/jpg for jpg files to match Shopify format
                    $mimetype = ($extension === 'jpg' || $extension === 'jpeg') ? 'image/jpg' : "image/{$extension}";
                    
                    $media[] = array(
                        'url' => strval($jpg_url),
                        'mimetype' => $mimetype,
                        'name' => $name,
                    );
                }
            }
        }
        
        return $media;
    }

    /**
     * Legacy method for getting product images
     *
     * @param WC_Product $product
     * @return array
     */
    private static function get_product_images($product)
    {
        return array_map(function($img) {
            return $img['src'];
        }, self::get_product_images_complete($product));
    }

    /**
     * Legacy method for getting product attributes
     *
     * @param WC_Product $product
     * @return array
     */
    private static function get_product_attributes($product)
    {
        return self::get_product_attributes_complete($product);
    }

    // ── Product Sync Webhook Callbacks ──────────────────────────────

    /**
     * Send a single product to the PayPlus gateway in Commerce Format.
     *
     * @param int    $product_id    WooCommerce product (or parent) ID.
     * @param string $endpoint_path e.g. '/products/create', '/products/update', '/products/delete'.
     * @param array  $exclude_variation_ids Variation IDs to exclude from the payload.
     */
    private static function send_single_product($product_id, $endpoint_path, $exclude_variation_ids = array())
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $options   = get_option('woocommerce_payplus-payment-gateway_settings');
        $testMode  = isset($options['api_test_mode']) && $options['api_test_mode'] === 'yes';
        $apiKey    = $testMode ? (isset($options['dev_api_key']) ? $options['dev_api_key'] : '') : (isset($options['api_key']) ? $options['api_key'] : '');
        $secretKey = $testMode ? (isset($options['dev_secret_key']) ? $options['dev_secret_key'] : '') : (isset($options['secret_key']) ? $options['secret_key'] : '');
        $pageUid   = $testMode
            ? (isset($options['dev_payment_page_id']) ? $options['dev_payment_page_id'] : '')
            : (isset($options['payment_page_id']) ? $options['payment_page_id'] : '');

        $company = array(
            'id' => self::get_site_uid(),
        );

        $commerce_data = self::transform_to_commerce_format($product, $company, $exclude_variation_ids);

        $payload = array(
            'payment_page_uid' => $pageUid,
            'products'         => array($commerce_data),
        );

        $url  = 'https://henevent-gateway.invoiceplus.co.il/v1/wc-hooks' . $endpoint_path;
        $args = array(
            'body'      => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'   => 0.01,
            'blocking'  => false,
            'headers'   => array(
                'domain'        => home_url(),
                'Content-Type'  => 'application/json',
                'Authorization' => '{"api_key":"' . $apiKey . '","secret_key":"' . $secretKey . '"}',
            ),
        );

        wp_remote_post($url, $args);

        $logger = wc_get_logger();
        $logger->info(
            sprintf('Product webhook — %s #%d to %s', $endpoint_path, $product_id, $url),
            array('source' => 'payplus-product-syncer')
        );
    }

    /**
     * Resolve a product ID to its parent if it is a variation.
     *
     * @param  int $product_id
     * @return int The parent product ID, or the same ID if not a variation.
     */
    private static function resolve_parent_id($product_id)
    {
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variation')) {
            return $product->get_parent_id();
        }
        return $product_id;
    }

    public static function on_product_created($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product || $product->is_type('variation')) {
            return;
        }
        self::send_single_product($product_id, '/products/create');
    }

    public static function on_product_updated($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product || $product->is_type('variation')) {
            return;
        }

        $key = 'update_' . $product_id;
        if (isset(self::$sent_product_ids[$key])) {
            return;
        }
        self::$sent_product_ids[$key] = true;

        self::send_single_product($product_id, '/products/update');
    }

    public static function on_product_trashed($post_id)
    {
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        self::send_single_product($post_id, '/products/delete');
    }

    public static function on_variation_deleted($post_id)
    {
        if (get_post_type($post_id) !== 'product_variation') {
            return;
        }

        $parent_id = wp_get_post_parent_id($post_id);
        if (!$parent_id) {
            return;
        }

        $key = 'update_' . $parent_id;
        if (isset(self::$sent_product_ids[$key])) {
            return;
        }
        self::$sent_product_ids[$key] = true;

        self::send_single_product($parent_id, '/products/update', array(intval($post_id)));
    }

    public static function set_order_stock_context($order)
    {
        if ($order instanceof WC_Order) {
            self::$order_stock_context = $order->get_id();
        } elseif (is_numeric($order)) {
            self::$order_stock_context = intval($order);
        }
    }

    public static function on_product_stock_changed($product)
    {
        if (self::$skip_stock_sync) {
            return;
        }

        $product_id = $product->get_id();

        $key = 'stock_' . $product_id;
        if (isset(self::$sent_product_ids[$key])) {
            return;
        }
        self::$sent_product_ids[$key] = true;

        self::send_inventory_update($product);
    }

    public static function on_variation_stock_changed($variation)
    {
        if (self::$skip_stock_sync) {
            return;
        }

        $variation_id = $variation->get_id();

        $key = 'stock_' . $variation_id;
        if (isset(self::$sent_product_ids[$key])) {
            return;
        }
        self::$sent_product_ids[$key] = true;

        self::send_inventory_update($variation);
    }

    /**
     * Send a lightweight inventory update to the gateway.
     * POST /v1/wc-hooks/inventory/update
     *
     * @param WC_Product $product The product or variation whose stock changed.
     */
    private static function send_inventory_update($product)
    {
        $product_id     = $product->get_id();
        $parent_id      = $product->get_parent_id();
        $stock_quantity = $product->get_stock_quantity();

        $options   = get_option('woocommerce_payplus-payment-gateway_settings');
        $testMode  = isset($options['api_test_mode']) && $options['api_test_mode'] === 'yes';
        $apiKey    = $testMode ? (isset($options['dev_api_key']) ? $options['dev_api_key'] : '') : (isset($options['api_key']) ? $options['api_key'] : '');
        $secretKey = $testMode ? (isset($options['dev_secret_key']) ? $options['dev_secret_key'] : '') : (isset($options['secret_key']) ? $options['secret_key'] : '');
        $pageUid   = $testMode
            ? (isset($options['dev_payment_page_id']) ? $options['dev_payment_page_id'] : '')
            : (isset($options['payment_page_id']) ? $options['payment_page_id'] : '');

        $payload = array(
            'payment_page_uid' => $pageUid,
            'company_id'       => self::get_site_uid(),
            'parent_id'        => strval($parent_id ? $parent_id : $product_id),
            'external_id'      => strval($product_id),
            'stock_quantity'   => $stock_quantity !== null ? intval($stock_quantity) : 0,
            'source_type'      => self::$order_stock_context ? 'order' : 'manual_adjustment',
        );

        if (self::$order_stock_context) {
            $payload['order_id'] = self::$order_stock_context;
        }

        $url  = 'https://henevent-gateway.invoiceplus.co.il/v1/wc-hooks/inventory/update';
        $args = array(
            'body'      => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'   => 0.01,
            'blocking'  => false,
            'headers'   => array(
                'domain'        => home_url(),
                'Content-Type'  => 'application/json',
                'Authorization' => '{"api_key":"' . $apiKey . '","secret_key":"' . $secretKey . '"}',
            ),
        );

        wp_remote_post($url, $args);

        $logger = wc_get_logger();
        $logger->info(
            sprintf('Inventory webhook — #%d stock_quantity=%s to %s', $product_id, ($stock_quantity !== null ? $stock_quantity : 'null'), $url),
            array('source' => 'payplus-product-syncer')
        );
    }
}

