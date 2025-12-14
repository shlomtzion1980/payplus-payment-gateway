<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles PayPlus Product Syncing functionality
 */
class WC_PayPlus_Product_Syncer
{
    /**
     * Constructor - Register AJAX handlers
     */
    public function __construct()
    {
        add_action('wp_ajax_payplus_get_products_json', [__CLASS__, 'ajax_get_products_json']);
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
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'woocommerce';
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
     * Render the product syncer admin page
     *
     * @return void
     */
    public static function render_product_syncer_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to access this page.', 'payplus-payment-gateway'));
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
                    resultHtml += '<button id="download-commerce-json-btn" class="button button-primary"><?php echo esc_js(__('Download Commerce JSON File', 'payplus-payment-gateway')); ?></button>';
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
                }
            }
        });
        </script>

        <style>
        .payplus-syncer-stats,
        .payplus-syncer-sample,
        .payplus-syncer-actions {
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .payplus-syncer-stats h2,
        .payplus-syncer-sample h2,
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
                'attributes' => $variation->get_variation_attributes(),
                
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
        
        // Get PayPlus settings for company info
        $payplus_settings = get_option('woocommerce_payplus-payment-gateway_settings');
        $company_id = isset($payplus_settings['api_key']) ? crc32($payplus_settings['api_key']) : 1; // Generate pseudo company_id from API key
        
        foreach ($products as $product) {
            $products_data[] = self::transform_to_commerce_format($product, $company_id);
        }
        
        return $products_data;
    }

    /**
     * Transform WooCommerce product to PayPlus Commerce format
     *
     * @param WC_Product $product
     * @param int $company_id
     * @return array
     */
    private static function transform_to_commerce_format($product, $company_id)
    {
        $product_id = $product->get_id();
        $product_type = $product->get_type();
        $currency = get_woocommerce_currency();
        
        // Determine VAT type
        $vat_type = 1; // VAT_INCLUDED as default
        if ($product->get_tax_status() === 'none') {
            $vat_type = 0; // VAT_EXEMPT
        }

        // Get categories
        $categories = self::transform_categories($product_id);
        
        // Get tags
        $tags = self::transform_tags($product_id);
        
        // Transform variants
        $variants = array();
        if ($product_type === 'variable') {
            $variants = self::transform_variable_product_variants($product, $currency);
        } else {
            // Simple product - create a single variant
            $variants[] = self::transform_simple_product_variant($product, $currency, true);
        }

        $commerce_product = array(
            'company_id' => $company_id,
            'name' => $product->get_name(),
            'description' => $product->get_description() ?: $product->get_short_description(),
            'valid' => $product->get_status() === 'publish',
            'vat_type' => $vat_type,
            'default' => false,
            'system_product' => false,
            'guide_document_url' => null,
            'currency_code' => $currency,
            'has_variants' => count($variants) > 1,
            'selling_unit_type' => 'UNITS',
            'manage_inventory' => $product->get_manage_stock(),
            'is_serial' => false,
            'variants' => $variants,
            'categories' => $categories,
            'tags' => $tags,
            'media' => self::transform_product_media($product),
            'external_id' => array(
                'platform_id' => 3, // WooCommerce platform ID (assuming 1=Shopify, 2=Other, 3=WooCommerce)
                'external_id' => $product_id,
                'external_id_source_field' => 'id'
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
        $price = floatval($product->get_price()) ?: 0;
        $regular_price = floatval($product->get_regular_price()) ?: $price;
        $sale_price = floatval($product->get_sale_price()) ?: 0;

        // Determine inventory status
        $stock_quantity = $product->get_stock_quantity();
        $inventory_status = 'AVAILABLE';
        if ($stock_quantity !== null) {
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

        $variant = array(
            'id' => 0,
            'uuid' => '',
            'sku' => $product->get_sku() ?: null,
            'name' => $product->get_name(),
            'is_main' => $is_main,
            'system_default' => false,
            'inventory_status' => $inventory_status,
            'continue_selling_out_of_stock' => $product->get_backorders() === 'yes' || $product->get_backorders() === 'notify',
            'item_type' => 'P',
            'pricing' => array(
                array(
                    'uuid' => '',
                    'currency_code' => $currency,
                    'value' => $price,
                    'price' => $price,
                    'start_at' => gmdate('Y-m-d\TH:i:s\Z'),
                    'finish_at' => null,
                )
            ),
            'external_ids' => array(
                array(
                    'platform_id' => 3,
                    'external_id' => $product->get_id(),
                    'external_id_source_field' => 'id'
                )
            ),
            'media' => self::transform_variant_media($product),
            'properties' => array(), // Simple products don't have variant properties
            'created_at' => $product->get_date_created() ? $product->get_date_created()->date('c') : gmdate('c'),
            'updated_at' => $product->get_date_modified() ? $product->get_date_modified()->date('c') : gmdate('c'),
            'deleted_at' => null,
            'is_deleted' => false,
        );

        // Add sale price if exists
        if ($sale_price > 0 && $sale_price < $regular_price) {
            $date_on_sale_from = $product->get_date_on_sale_from();
            $date_on_sale_to = $product->get_date_on_sale_to();
            
            $variant['pricing'][] = array(
                'uuid' => '',
                'currency_code' => $currency,
                'value' => $sale_price,
                'price' => $sale_price,
                'start_at' => $date_on_sale_from ? $date_on_sale_from->date('c') : gmdate('Y-m-d\TH:i:s\Z'),
                'finish_at' => $date_on_sale_to ? $date_on_sale_to->date('c') : null,
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

            $price = floatval($variation->get_price()) ?: 0;
            $regular_price = floatval($variation->get_regular_price()) ?: $price;
            $sale_price = floatval($variation->get_sale_price()) ?: 0;

            // Determine inventory status
            $stock_quantity = $variation->get_stock_quantity();
            $inventory_status = 'AVAILABLE';
            if ($stock_quantity !== null) {
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
            $attributes = $variation->get_variation_attributes();
            foreach ($attributes as $attr_name => $attr_value) {
                // Remove 'attribute_' prefix if present
                $property_name = str_replace('attribute_', '', $attr_name);
                $property_name = str_replace('pa_', '', $property_name); // Remove taxonomy prefix
                $property_name = ucwords(str_replace('-', ' ', $property_name));
                
                $properties[] = array(
                    'property_type_uid' => 0, // Will be created/matched on commerce side
                    'value' => $attr_value,
                    'property_type_name' => $property_name,
                );
            }

            $variant_data = array(
                'id' => 0,
                'uuid' => '',
                'sku' => $variation->get_sku() ?: null,
                'name' => $variation->get_name(),
                'is_main' => $is_first,
                'system_default' => false,
                'inventory_status' => $inventory_status,
                'continue_selling_out_of_stock' => $variation->get_backorders() === 'yes' || $variation->get_backorders() === 'notify',
                'item_type' => 'P',
                'pricing' => array(
                    array(
                        'uuid' => '',
                        'currency_code' => $currency,
                        'value' => $price,
                        'price' => $price,
                        'start_at' => gmdate('Y-m-d\TH:i:s\Z'),
                        'finish_at' => null,
                    )
                ),
                'external_ids' => array(
                    array(
                        'platform_id' => 3,
                        'external_id' => $variation_id,
                        'external_id_source_field' => 'id'
                    )
                ),
                'media' => self::transform_variant_media($variation),
                'properties' => $properties,
                'created_at' => $variation->get_date_created() ? $variation->get_date_created()->date('c') : gmdate('c'),
                'updated_at' => $variation->get_date_modified() ? $variation->get_date_modified()->date('c') : gmdate('c'),
                'deleted_at' => null,
                'is_deleted' => false,
            );

            // Add sale price if exists
            if ($sale_price > 0 && $sale_price < $regular_price) {
                $date_on_sale_from = $variation->get_date_on_sale_from();
                $date_on_sale_to = $variation->get_date_on_sale_to();
                
                $variant_data['pricing'][] = array(
                    'uuid' => '',
                    'currency_code' => $currency,
                    'value' => $sale_price,
                    'price' => $sale_price,
                    'start_at' => $date_on_sale_from ? $date_on_sale_from->date('c') : gmdate('Y-m-d\TH:i:s\Z'),
                    'finish_at' => $date_on_sale_to ? $date_on_sale_to->date('c') : null,
                );
            }

            $variants[] = $variant_data;
            $is_first = false;
        }

        return $variants;
    }

    /**
     * Transform product categories for commerce
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
                    'id' => $term->term_id,
                    'uuid' => $term->slug,
                    'name' => $term->name,
                );
            }
        }

        return $categories;
    }

    /**
     * Transform product tags for commerce
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
                $tags[] = $term->name;
            }
        }

        return $tags;
    }

    /**
     * Transform product media for commerce (returns URLs for media service to download)
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
                $media[] = $image_url;
            }
        }
        
        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $image_id) {
            $image_url = wp_get_attachment_url($image_id);
            if ($image_url) {
                $media[] = $image_url;
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
                $media[] = $image_url;
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
}

