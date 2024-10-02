<?php
if (WC()->cart->get_subtotal() === 0) {
    return;
}

// $couponTransient = get_transient('hostedCoupon');
// if (is_array($couponTransient)) {
//     print_r($couponTransient);
//     die;
// }
$options = get_option('woocommerce_payplus-payment-gateway_settings');
$testMode = boolval($options['api_test_mode'] === 'yes');
$url = $testMode ? PAYPLUS_PAYMENT_URL_DEV . 'Transactions/updateMoreInfos' : PAYPLUS_PAYMENT_URL_PRODUCTION . 'Transactions/updateMoreInfos';
$apiKey = $testMode ? $options['dev_api_key'] : $options['api_key'];
$secretKey = $testMode ? $options['dev_secret_key'] : $options['secret_key'];
$paymentPageUid = $testMode ? $options['dev_payment_page_id'] : $options['payment_page_id'];

define('API_KEY', $apiKey);
define('SECRET_KEY', $secretKey);
define('PAYMENT_PAGE_UID', $paymentPageUid);
define('ORIGIN_DOMAIN', site_url());
define('SUCCESS_URL', 'https://www.example.com/success');
define('FAILURE_URL', site_url() . "/error-payment-payplus/");
define('CANCEL_URL', 'https://www.example.com/cancel');

create_order_if_not_exists();

/**
 * PAYPLUS_API_URL_DEV is the URL of the API in the development environment.
 */
define('PAYPLUS_API_URL_DEV', 'https://restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink');

/**
 * PAYPLUS_API_URL_PROD is the URL of the API in the production environment.
 */
define('PAYPLUS_API_URL_PROD', 'https://restapi.payplus.co.il/api/v1.0/PaymentPages/generateLink');

$apiUrl = $testMode ? PAYPLUS_API_URL_DEV : PAYPLUS_API_URL_PROD;

$order_id = WC()->session->get('order_awaiting_payment');
$WC_PayPlus_Gateway = $this->get_main_payplus_gateway();
$discountPrice = 0;
$products = array();
$merchantCountryCode = substr(get_option('woocommerce_default_country'), 0, 2);
WC()->customer->set_shipping_country($merchantCountryCode);
WC()->cart->calculate_totals();
$wc_tax_enabled = wc_tax_enabled();

$cart = WC()->cart->get_cart();
if (count($cart)) {
    foreach ($cart as $cart_item_key => $cart_item) {
        $productId = $cart_item['product_id'];

        if (!empty($cart_item['variation_id'])) {
            $product = new WC_Product_Variable($productId);
            $productData = $product->get_available_variation($cart_item['variation_id']);
            $tax = (WC()->cart->get_total_tax()) ? WC()->cart->get_total_tax() / $cart_item['quantity'] : 0;
            $tax = round($tax, $WC_PayPlus_Gateway->rounding_decimals);
            $priceProductWithTax = round($productData['display_price'] + $tax, ROUNDING_DECIMALS);
            $priceProductWithoutTax = round($productData['display_price'], ROUNDING_DECIMALS);
        } else {
            $product = new WC_Product($productId);
            $priceProductWithTax = round(wc_get_price_including_tax($product), ROUNDING_DECIMALS);
            $priceProductWithoutTax = round(wc_get_price_excluding_tax($product), ROUNDING_DECIMALS);
        }
        $productVat = $product->get_tax_status() === 'taxable' && !$wc_tax_enabled ? 0 : 2;
        $productVat = 0 && $wc_tax_enabled ? 1 : $productVat;
        $products[] = array(
            'title' => $product->get_title(),
            'priceProductWithTax' => $priceProductWithTax,
            'priceProductWithoutTax' => $priceProductWithoutTax,
            'quantity' => $cart_item['quantity'],
            'vat_type' => $productVat,
            'org_product_tax' => $product->get_tax_status(),
        );
    }

    if (WC()->cart->get_total_discount()) {
        $discountPrice = round(floatval(WC()->cart->get_discount_total()), ROUNDING_DECIMALS);
    }
}

$totalAll = WC()->cart->get_totals();
$subTotalAll = WC()->cart->get_subtotal();
$taxGlobal = round(WC()->cart->get_total_tax() - WC()->cart->get_shipping_tax(), ROUNDING_DECIMALS);
$error = $totalAll['total'] == 0;

// echo wp_json_encode(array("error" => $error, "total" => $totalAll['total'], "products" => $products, "total_without_tax" => $subTotalAll, 'discountPrice' => $discountPrice ? $discountPrice : 0, "taxGlobal" => $taxGlobal));

$checkout = WC()->checkout();

// Get posted checkout data
$billing_first_name = $checkout->get_value('billing_first_name');
$billing_last_name  = $checkout->get_value('billing_last_name');
$billing_email      = $checkout->get_value('billing_email');
$shipping_address   = $checkout->get_value('shipping_address_1');
$phone   = $checkout->get_value('billing_phone');


// Building sample request to create a payment page
$data = new stdClass();
$data->payment_page_uid = PAYMENT_PAGE_UID;
$data->refURL_success = SUCCESS_URL;
$data->refURL_failure = FAILURE_URL;
$data->refURL_cancel = CANCEL_URL;
$data->create_token = true;
$data->currency_code = get_woocommerce_currency();
$data->charge_method = intval($this->payplus_payment_gateway_settings->transaction_type);

/**
 * Origin domain is the domain of the page that is requesting the payment page.
 * This is necessary for the hosted fields to be able to communicate with the client website.
 */
$data->refURL_origin = ORIGIN_DOMAIN;
/**
 * Also notice that we set hosted_fields to true.
 */
$data->hosted_fields = true;

$data->customer = new stdClass();
$data->customer->customer_name = "$billing_first_name $billing_last_name";
$data->customer->email = $billing_email;
$data->customer->phone = $phone;
$data->amount = $totalAll['total'];


foreach ($products as $product) {
    $item = new stdClass();
    $item->name = $product['title'];
    $item->quantity = $product['quantity'];
    $item->price = $product['priceProductWithTax'];
    $item->vat_type = $product['vat_type'];
    $data->items[] = $item;
}

$data->more_info = WC()->session->get('order_awaiting_payment');
$order = wc_get_order($order_id);

$shipping_items = $order->get_items('shipping');
// Check if there are shipping items
if (! empty($shipping_items)) {
    foreach ($shipping_items as $shipping_item) {
        // Get the shipping method ID (e.g., 'flat_rate:1')
        $method_id = $shipping_item->get_method_id();

        // Get the shipping method title (e.g., 'Flat Rate')
        $method_title = $shipping_item->get_method_title();
        $shipping_cost = $shipping_item->get_total();

        if ($shipping_cost > 0) {
            $item = new stdClass();
            $item->name = $method_title;
            $item->quantity = 1;
            $item->price = $shipping_cost;
            $item->vat_type = !$wc_tax_enabled ? 0 : 1;
            $data->items[] = $item;
        }
    }
}

$coupons = $order->get_coupon_codes();

if (! empty($coupons)) {
    foreach ($coupons as $coupon_code) {
        // Get the WC_Coupon object
        $coupon = new WC_Coupon($coupon_code);

        // Get the coupon discount amount
        $coupon_value = $coupon->get_amount();
    }
    if ($coupon_value > 0) {
        $item = new stdClass();
        $item->name = "coupon_discount";
        $item->quantity = 1;
        $item->price = -$coupon_value;
        $item->vat_type = !$wc_tax_enabled ? 0 : 1;
        $data->items[] = $item;
    }
}

$totalAmount = 0;
foreach ($data->items as $item) {
    $totalAmount += $item->price * $item->quantity;
}

$data->amount = number_format($totalAmount, 2, '.', '');

$linkRedirect = html_entity_decode(esc_url($this->payplus_gateway->get_return_url($order)));
$data->refURL_success = $linkRedirect;

$payload = wp_json_encode($data);
set_transient('hostedPayload', $payload, 10 * MINUTE_IN_SECONDS);

$auth = json_encode([
    'api_key' => API_KEY,
    'secret_key' => SECRET_KEY
]);
$requestHeaders = [];
$requestHeaders[] = 'Content-Type:application/json';
$requestHeaders[] = 'Authorization: ' . $auth;


$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_POST, true);
$hostedResponse = curl_exec($ch);
curl_close($ch);
// echo '<pre>';
// print_r($hostedResponse);
// print_r($data);
// print_r($totalAll);

// die;

function create_order_if_not_exists()
{
    if (! is_checkout()) {
        return; // Only run on the checkout page
    }

    // Check if an order already exists in the session
    if (WC()->session->get('order_awaiting_payment')) {
        return; // Order already exists, no need to create another
    }

    // Create a new order using the WC_Checkout object
    $checkout = WC()->checkout();

    // Get posted billing and shipping data from the checkout form
    $billing_first_name  = $checkout->get_value('billing_first_name');
    $billing_last_name   = $checkout->get_value('billing_last_name');
    $billing_email       = $checkout->get_value('billing_email');
    $billing_phone       = $checkout->get_value('billing_phone');
    $billing_address_1   = $checkout->get_value('billing_address_1');
    $billing_city        = $checkout->get_value('billing_city');
    $billing_postcode    = $checkout->get_value('billing_postcode');
    $billing_country     = $checkout->get_value('billing_country');

    // Shipping data
    $shipping_first_name = $checkout->get_value('shipping_first_name');
    $shipping_last_name  = $checkout->get_value('shipping_last_name');
    $shipping_address_1  = $checkout->get_value('shipping_address_1');
    $shipping_city       = $checkout->get_value('shipping_city');
    $shipping_postcode   = $checkout->get_value('shipping_postcode');
    $shipping_country    = $checkout->get_value('shipping_country');

    // Get available payment gateways
    $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
    $chosen_payment_method = key($available_gateways); // Select the first available gateway, or set your own logic

    // Populate checkout data with necessary fields
    $data = array(
        // Billing data
        'billing_first_name' => $billing_first_name,
        'billing_last_name'  => $billing_last_name,
        'billing_email'      => $billing_email,
        'billing_phone'      => $billing_phone,
        'billing_address_1'  => $billing_address_1,
        'billing_city'       => $billing_city,
        'billing_postcode'   => $billing_postcode,
        'billing_country'    => $billing_country,

        // Shipping data
        'shipping_first_name' => $shipping_first_name,
        'shipping_last_name'  => $shipping_last_name,
        'shipping_address_1'  => $shipping_address_1,
        'shipping_city'       => $shipping_city,
        'shipping_postcode'   => $shipping_postcode,
        'shipping_country'    => $shipping_country,

        // Payment method
        'payment_method' => $chosen_payment_method, // Set the payment method
    );

    try {
        // Create a new order using the checkout data
        $order_id = $checkout->create_order($data);

        // Set the order awaiting payment in the session
        WC()->session->set('order_awaiting_payment', $order_id);

        $order = wc_get_order($order_id);

        // You can manipulate or save additional order data here
        return $order_id; // Returns the newly created order ID
    } catch (Exception $e) {
        wc_add_notice(__('Error creating order: ') . $e->getMessage(), 'error');
    }
}