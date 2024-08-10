<?php
// Check if accessed directly outside of WordPress
if (!defined('ABSPATH')) {
    // Define ABSPATH to load WordPress environment if needed
    define('ABSPATH', dirname(__FILE__) . '/../../../');
}

// Load the WordPress environment
require_once(ABSPATH . 'wp-load.php');

// Now you can access WordPress functions
if (!is_user_logged_in()) {
    wp_die('You must be logged in to access this page.');
}
// Example: Get the site name from WordPress options
$site_name = get_option('blogname');

// Display the site name
echo '<h1>Welcome to ' . esc_html($site_name) . '</h1>';
echo '<p>This is a standalone PHP application that can access WordPress functions.</p>';
$class = new WC_PayPlus_Gateway;
echo '<pre>';
print_r($class);
