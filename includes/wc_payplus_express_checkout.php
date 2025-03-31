<?php
class WC_PayPlus_Express_Checkout extends WC_PayPlus
{
    private $payPlusGateWaySettings;
    public $isExpressCheckout;
    public $isAppleEnabled;
    public $isGoogleEnabled;
    public $paymentPageId;

    /**
     *
     */
    public function __construct()
    {

        $this->payPlusGateWaySettings = get_option('woocommerce_payplus-payment-gateway_settings', []);
        $this->isAppleEnabled = boolval(isset($this->payPlusGateWaySettings['enable_apple_pay']) && $this->payPlusGateWaySettings['enable_apple_pay'] === 'yes');
        $this->isGoogleEnabled = boolval(isset($this->payPlusGateWaySettings['enable_google_pay']) && $this->payPlusGateWaySettings['enable_google_pay'] === 'yes');
        $this->paymentPageId = isset($this->payPlusGateWaySettings['api_test_mode']) && $this->payPlusGateWaySettings['api_test_mode'] === 'yes' ? $this->payPlusGateWaySettings['dev_payment_page_id'] ?? null : $this->payPlusGateWaySettings['payment_page_id'] ?? null;

        add_action('wp_ajax_apple-onvalidate-merchant', [$this, 'ajax_payplus_apple_onvalidate_merchant']);
        add_action('wp_ajax_nopriv_apple-onvalidate-merchant', [$this, 'ajax_payplus_apple_onvalidate_merchant']);
        add_action('wp_ajax_process-payment-oneclick', [$this, 'ajax_payplus_process_payment_oneclick']);
        add_action('wp_ajax_nopriv_process-payment-oneclick', [$this, 'ajax_payplus_process_payment_oneclick']);
        add_action('wp_ajax_payplus-express-checkout-initialized', [$this, 'ajax_payplus_express_checkout_initialized']);
        add_action('wp_ajax_check-customer-vat-oc', [$this, 'ajax_payplus_check_customer_vat_oc']);
        add_action('wp_ajax_nopriv_check-customer-vat-oc', [$this, 'ajax_payplus_check_customer_vat_oc']);
        add_action('wp_ajax_payplus-get-total-cart', [$this, 'ajax_payplus_get_total_cart']);
        add_action('wp_ajax_nopriv_payplus-get-total-cart', [$this, 'ajax_payplus_get_total_cart']);
        add_action('woocommerce_after_add_to_cart_form', [$this, 'payplus_extra_button_on_product_page'], 30);
        add_action('woocommerce_before_checkout_form', [$this, 'payplus_extra_button_on_product_page'], 30);
        add_action('woocommerce_before_cart', [$this, 'payplus_extra_button_on_product_page'], 20);
        add_action('wp_footer', [$this, 'payplus_set_code_footer']);
        add_shortcode('payplus-extra-express-checkout', [$this, 'payplus_extra_button_short_code']);
    }


    /**
     * @return void
     */
    public function payplus_set_code_footer()
    {
        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();
        $enableGooglePay = isset($WC_PayPlus_Gateway->enable_google_pay) ? $WC_PayPlus_Gateway->enable_google_pay : false;
        $enableApplePay = isset($WC_PayPlus_Gateway->enable_apple_pay) ? $WC_PayPlus_Gateway->enable_apple_pay : false;
        if ($this->payplus_check_one_click_visible()) {
?>
            <script>
                function isFacebookApp() {
                    var ua = navigator.userAgent || navigator.vendor || window.opera;
                    return (ua.indexOf("FBAN") > -1) || (ua.indexOf("FBAV") > -1);
                }

                let isGoogleEnable = '<?php echo esc_js($enableGooglePay); ?>';
                let isAppleEnable = '<?php echo esc_js($enableApplePay); ?>';
                let isAppleAvailable = window.ApplePaySession && ApplePaySession?.canMakePayments();
                let removeGooglePay = isFacebookApp();
                let showExpress = (isGoogleEnable == 1 && !removeGooglePay) || (isAppleEnable && isAppleAvailable);
                let expresscheckouts = document.querySelectorAll(".express-checkout");
                if (!showExpress) {
                    expresscheckouts.forEach(e => e.remove());
                } else {
                    if (expresscheckouts.length > 1) {
                        expresscheckouts.forEach((element, index) => {
                            if (index) {
                                expresscheckouts[index].remove();
                            }
                        });
                    }
                }
                if (removeGooglePay) {
                    let googlePayButton = document.getElementById('googlePayButton');
                    if (googlePayButton) {
                        googlePayButton.remove();
                    }

                }
            </script>
        <?php
        }
    }

    /**
     * @return void
     */
    public function ajax_payplus_apple_onvalidate_merchant()
    {
        check_ajax_referer('frontNonce', '_ajax_nonce');
        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();
        $url = $WC_PayPlus_Gateway->api_url . 'ApplePay/PaymentSessionOneClickCheckout';
        $obj = isset($_POST['obj']) ?  WC_PayPlus_Statics::sanitize_object($_POST['obj']) : null; // phpcs:ignore 
        $arr['payment_page_uid'] = $this->paymentPageId;
        $arr['display_name'] = get_bloginfo('name') ? get_bloginfo('name') : site_url();
        $arr['website'] = site_url();
        $arr['identifier_express_checkout'] = $WC_PayPlus_Gateway->token_apple_pay;
        $arr['url_validation'] = $obj['urlValidation'];
        $arr = wp_json_encode($arr);
        $WC_PayPlus_Gateway->payplus_add_log_all('payment_sessionOne_click_checkout', wp_json_encode($arr), 'payload');
        $resp = WC_PayPlus_Statics::payPlusRemote($url, $arr);
        $res = json_decode(wp_remote_retrieve_body($resp));
        $WC_PayPlus_Gateway->payplus_add_log_all('payment_sessionOne_click_checkout', wp_json_encode($res), 'completed');
        if ($res) {
            echo wp_json_encode(array("payment_response" => $res, "status" => true));
            wp_die();
        } else {
            $resError = array('results' => array(
                'description' => __('Cannot process the transaction. Contact your merchant. Error during validate merchant.', 'payplus-payment-gateway'),
                'code' => '-1'
            ));
            echo wp_json_encode(array("payment_response" => $resError, "status" => false));
            wp_die();
        }
    }

    /**
     * @param $order
     * @param $customer
     * @return mixed
     */
    public function create_customer_order($order, $customer)
    {
        $user = get_user_by('email', $customer['email']);
        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();

        $address = array(
            'first_name' => $customer['customer_name'],
            'last_name' => '',
            'email' => $customer['email'],
            'address_1' => $customer['address'],
            'city' => $customer['city'],
            'country' => $customer['country_ISO'],
        );
        if (!empty($customer['phone'])) {
            $address['phone'] = $customer['phone'];
        }
        if (!$user && $WC_PayPlus_Gateway->enable_create_user) {
            $password = wp_generate_password();
            $customerId = wc_create_new_customer($customer['email'], '', $password, ['first_name' => $customer['customer_name']]);
            if (is_wp_error($customerId)) {
                $WC_PayPlus_Gateway->payplus_add_log_all('payplus_error_user', $customerId->get_error_message());
            } else {
                $order->set_customer_id($customerId);
            }
        }
        $order->set_address($address, 'billing');
        $order->set_address($address, 'shipping');
        return $order;
    }

    /**
     * @return void
     */
    public function ajax_payplus_process_payment_oneclick()
    {
        check_ajax_referer('frontNonce', '_ajax_nonce');
        global $post_id;
        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();
        $resError = array('results' => array(
            'description' => __('Server failure, please contact the site administrator', 'payplus-payment-gateway'),
            'code' => '-1'
        ));
        $cart = WC()->cart;
        $discount = $cart->get_cart_discount_total();
        $taxDiscount = $cart->get_cart_discount_tax_total();
        if (!empty($_POST)) {
            $obj = isset($_POST['obj']) ?  WC_PayPlus_Statics::sanitize_object($_POST['obj']) : null; // phpcs:ignore 
            $paymentInfo = isset($obj['cardInfo']['info']) ? $obj['cardInfo']['info'] : null;
            $shipping = $obj['shipping'];
            $methodUrl = $obj['method'] == 'google-pay' ? 'GooglePayProcess' : 'ApplePayProcess';
            $url = $WC_PayPlus_Gateway->api_url . "Transactions/" . $methodUrl;
            $arrJson = array();
            $order = wc_create_order();
            $arrJson['payload_encrypted'] = $obj['token'];
            $order = $this->create_customer_order($order, $obj['contact']);
            $paying_vat = !!($obj['paying_vat']);
            $userID = get_current_user_id();
            if ($userID && empty($order->get_customer_id())) {
                $order->set_customer_id(get_current_user_id());
            }
            $cart = $cart->get_cart();
            if (count($cart)) {
                foreach ($cart as $cart_item_key => $cart_item) {
                    $product_id = $cart_item['product_id'];
                    $quantity = $cart_item['quantity'];
                    if ($cart_item['variation_id']) {
                        $variation_id = $cart_item['variation_id'];
                        $variation = $cart_item['variation'];
                        // Add the variation as a line item
                        $order->add_product(wc_get_product($variation_id), $quantity, array(
                            'variation' => $variation,
                        ));
                    } else {
                        // Add the product as a line item
                        $order->add_product(wc_get_product($product_id), $quantity);
                    }
                }
            }
            if ($shipping != 'shipping--1') {
                $shipping = explode("-", $shipping);
                if ($shipping[1] != 0) {
                    $item = $this->create_shipping_order($order, $shipping[1]);
                    $order->add_item($item);
                } else {

                    $amount = round(floatval($WC_PayPlus_Gateway->global_shipping), ROUNDING_DECIMALS);
                    $is_taxable_settings = ($WC_PayPlus_Gateway->global_shipping_tax == 'taxable' && (get_option('woocommerce_calc_taxes') == 'yes')); // How much the fee should be
                    $tax = $paying_vat && ($is_taxable_settings) ? 'taxable' : 'none';
                    $title = 'Shipping express checkout';
                    $item_fee = new WC_Order_Item_Fee();
                    $item_fee->set_name($title);
                    $item_fee->set_amount($amount);
                    $item_fee->set_tax_status($tax);
                    $item_fee->set_total($amount);
                    $order->add_item($item_fee);
                }
            }
            if ($discount) {
                $item_fee = new WC_Order_Item_Fee();
                $item_fee->set_name("discount");
                $item_fee->set_amount(-1 * $discount);
                $is_taxable_settings = (get_option('woocommerce_calc_taxes') == 'yes');
                $tax = $paying_vat && ($is_taxable_settings) ? 'taxable' : 'none';
                $item_fee->set_tax_status($tax);
                $item_fee->set_total(-1 * $discount);
                $order->add_item($item_fee);
            }
            $order->calculate_totals();
            $order_id = $order->save();
            $payload = $WC_PayPlus_Gateway->generatePaymentLink($order_id);
            WC_PayPlus_Meta_Data::update_meta($order, ['payplus_payload' => $payload]);
            $payload = json_decode($payload, true);

            $arrRemove = array('expiry_datetime', 'hide_other_charge_methods', 'refURL_success', 'refURL_failure', 'refURL_callback', 'charge_default');
            if (count($payload)) {
                foreach ($payload as $key => $value) {
                    if (in_array($key, $arrRemove)) {
                        unset($payload[$key]);
                    }
                }
            }
            $arrJson = array_merge($arrJson, $payload);
            $arrJson['paying_vat'] = isset($obj['paying_vat']) ? $obj['paying_vat'] : $arrJson['paying_vat'];
            $arrJson['paying_vat'] = $arrJson['paying_vat'] === "true" ? true : false;

            $payload = wp_json_encode($arrJson);

            $WC_PayPlus_Gateway->payplus_add_log_all('payplus_process_payment', 'New Payment Process Fired (' . $order_id . ')');
            $WC_PayPlus_Gateway->payplus_add_log_all('payplus_process_payment', '', 'before-payload');
            $WC_PayPlus_Gateway->payplus_add_log_all('payplus_process_payment', wp_json_encode($payload), 'payload');

            $response = WC_PayPlus_Statics::payPlusRemote($url, $payload);

            if (is_wp_error($response)) {
                $WC_PayPlus_Gateway->payplus_add_log_all('payplus_process_payment', 'WS PayPlus Response');
                $WC_PayPlus_Gateway->payplus_add_log_all('payplus_process_payment', wp_json_encode($response), 'error');
            } else {
                $res = json_decode(wp_remote_retrieve_body($response));
                $WC_PayPlus_Gateway->payplus_add_log_all('payplus_process_payment', 'WS PayPlus Response');
                $WC_PayPlus_Gateway->payplus_add_log_all('payplus_process_payment', wp_json_encode($response), 'completed');
                if ($res->results->status === "success") {
                    if ($res->data->transaction->status_code === '000') {
                        $order_id = $res->data->transaction->more_info;
                        $order = wc_get_order($order_id);
                        if ($order) {
                            $data = array();
                            $inData = array_merge($data, (array) $res->data);
                            $WC_PayPlus_Gateway->payplus_add_order_express_checkout($order_id, $inData);
                            $this->updateMetaDataOneClick($order_id, $inData);
                            if (!is_null($paymentInfo)) {
                                !is_null($paymentInfo['cardDetails']) ? WC_PayPlus_Meta_Data::update_meta($order, array('payplus_' . $obj['method'] . 'cardDetails' => $paymentInfo['cardDetails'])) : null;
                                !is_null($paymentInfo['cardNetwork']) ? WC_PayPlus_Meta_Data::update_meta($order, array('payplus_' . $obj['method'] . 'cardNetwork' => $paymentInfo['cardNetwork'])) : null;
                            }
                            WC_PayPlus_Meta_Data::update_meta($order, array('payplus_response' => wp_json_encode($res)));
                            WC_PayPlus_Meta_Data::update_meta($order, array('payplus_' . $obj['method'] => $order->get_total()));
                            if ($order->get_user_id() > 0) {
                                update_user_meta($order->get_user_id(), 'cc_token', $inData['data']->card_information->token);
                                if ($WC_PayPlus_Gateway->create_pp_token && $inData['data']->card_information->token) {
                                    $dataToken = array();
                                    $dataToken['token_uid'] = $inData['data']->card_information->token;
                                    $dataToken['four_digits'] = $inData['data']->card_information->four_digits;
                                    $dataToken['expiry_month'] = $inData['data']->card_information->expiry_month;
                                    $dataToken['expiry_year'] = $inData['data']->card_information->expiry_year;
                                    $dataToken['brand_id'] = $inData['data']->card_information->brand_id;
                                    $WC_PayPlus_Gateway->save_token($dataToken, $userID);
                                }
                            }
                            $saveOrderNote = boolval($this->payPlusGateWaySettings['payplus_data_save_order_note'] === 'yes');
                            if ($saveOrderNote) {
                                $order->add_order_note(sprintf(
                                    '<div style="font-weight:600;">PayPlus Express Checkout Successful</div>
                                    <table style="border-collapse:collapse">
                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Last digits</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Expiry date</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                        <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Voucher ID</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                                        <tr><td style="vertical-align:top;">Token</td><td style="vertical-align:top;"><a style="font-weight: bold;color:#000" class="copytoken" href="#"> %s</a></td></tr>
                                        <tr><td style="vertical-align:top;">Total</td><td style="vertical-align:top;">%s</td></tr>
                                    </table>
                                ',
                                    $inData['transaction']->number,
                                    $inData['data']->card_information->four_digits,
                                    $inData['data']->card_information->expiry_month . '/' . $inData['data']->card_information->expiry_year,
                                    $inData['transaction']->voucher_number,
                                    $inData['data']->card_information->token,
                                    $order->get_total()
                                ));
                            }

                            if ($WC_PayPlus_Gateway->fire_completed) {
                                $order->payment_complete();
                            }

                            if ($WC_PayPlus_Gateway->successful_order_status !== 'default-woo') {
                                $order->update_status($WC_PayPlus_Gateway->successful_order_status);
                            }
                            $return_url = $WC_PayPlus_Gateway->get_return_url($order);
                        }
                        echo wp_json_encode(array("link" => $return_url, "status" => true));
                    } else {
                        echo wp_json_encode(array("payment_response" => $res, "status" => false));
                    }
                } else {
                    if (empty($res)) {
                        $res = $resError;
                    }
                    echo wp_json_encode(array("payment_response" => $res, "status" => false));
                }
                wp_die();
            }
        }
        echo wp_json_encode(array("payment_response" => "", "status" => false));
        wp_die();
    }

    /**
     * @return void
     */
    public function ajax_payplus_express_checkout_initialized()
    {
        check_ajax_referer('frontNonce', '_ajax_nonce');
        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();
        $payplus_payment_gateway_settings = get_option('woocommerce_payplus-payment-gateway_settings');
        $url = $WC_PayPlus_Gateway->api_url . 'Transactions/ExpressCheckoutInitialized';

        $res = array('results' => array(
            'description' => __(
                'You do not have permission to connect Google Pay and Apple Pay. Contact to manage a website',
                'payplus-payment-gateway'
            ),
            'code' => '-1'
        ));
        $resObj = null;
        if (!empty($_POST)) {

            $payload['payment_page_uid'] = $this->paymentPageId;
            $payload['method'] = isset($_POST['method']) ? sanitize_text_field(wp_unslash($_POST['method'])) : null;
            $payload['domain'] = site_url();
            $method = $payload['method'];

            if ($method == 'apple-pay') {
                $result = $this->payplus_add_file_ApplePay();
                if (!$result) {
                    $res = array('results' => array(
                        'description' => __(
                            'Copy file Apple error. Please contact PayPlus to manage your express checkout onboarding.',
                            'payplus-payment-gateway'
                        ),
                        'code' => '-1'
                    ));
                    echo wp_json_encode(array("response_initialized" => $res, "status" => false));
                    wp_die();
                }
            }

            $payload = wp_json_encode($payload);

            $WC_PayPlus_Gateway->payplus_add_log_all('payplus_express_checkout_initialized', '', 'before-payload');
            $WC_PayPlus_Gateway->payplus_add_log_all('payplus_express_checkout_initialized', wp_json_encode($payload), 'payload');
            $response = WC_PayPlus_Statics::payPlusRemote($url, $payload);
            $res = json_decode(wp_remote_retrieve_body($response));
            if (is_wp_error($response)) {
                $WC_PayPlus_Gateway->payplus_add_log_all('payplus_express_checkout_initialized', 'WS PayPlus Response');
                $WC_PayPlus_Gateway->payplus_add_log_all('payplus_express_checkout_initialized', wp_json_encode($response), 'error');
            } else {
                $WC_PayPlus_Gateway->payplus_add_log_all('payplus_express_checkout_initialized', wp_json_encode($res), 'completed');
                if ($res->results->status === "success") {
                    if (property_exists($res->data, 'apple_pay_identifier')) {
                        update_option('payplus_apple_pay_identifier', $res->data->apple_pay_identifier);
                        $resObj = array('apple_pay_identifier' => $res->data->apple_pay_identifier);
                    }

                    if ($method == "google-pay") {
                        $payplus_payment_gateway_settings['enable_google_pay'] = "yes";
                    } else {
                        $payplus_payment_gateway_settings['enable_apple_pay'] = "yes";
                    }
                    update_option('woocommerce_payplus-payment-gateway_settings', $payplus_payment_gateway_settings);
                    $result = $resObj ?? $res;
                    echo wp_json_encode(array("response_initialized" => $result, "status" => true));
                } else {
                    echo wp_json_encode(array("response_initialized" => $res, "status" => false));
                }
                wp_die();
            }
        }
        echo wp_json_encode(array("response_initialized" => $res, "status" => false));
        wp_die();
    }

    /**
     * @return void
     */
    public function ajax_payplus_check_customer_vat_oc()
    {
        check_ajax_referer('frontNonce', '_ajax_nonce');
        $paying_vat = false;
        $obj = isset($_POST['obj']) ?  WC_PayPlus_Statics::sanitize_object($_POST['obj']) : null; // phpcs:ignore 
        global $woocommerce;
        $location = array(
            'country' => $obj['country_iso'],
            'state' => '',
            'city' => $obj['city'],
            'postcode' => (isset($obj['postal_code'])) ? $obj['postal_code'] : '',
        );
        $tax_classs = wc_get_product_tax_class_options();
        if (count($tax_classs)) {
            foreach ($tax_classs as $tax_class => $tax_class_label) {
                $tax_rates = WC_Tax::find_rates(array_merge($location, array('tax_class' => $tax_class)));
                if (!empty($tax_rates)) {
                    $rate_data = reset($tax_rates);
                    $rate = $rate_data['rate'];
                    if ($rate) {
                        $paying_vat = true;
                    }
                }
            }
        }
        echo wp_json_encode(array("paying_vat" => $paying_vat));
        wp_die();
    }

    /**
     * @return void
     * @throws Exception
     */
    public function ajax_payplus_get_total_cart()
    {
        check_ajax_referer('frontNonce', '_ajax_nonce');
        global $woocommerce;
        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();
        $discountPrice = 0;
        $products = array();
        $merchantCountryCode = substr(get_option('woocommerce_default_country'), 0, 2);
        WC()->customer->set_shipping_country($merchantCountryCode);
        WC()->cart->calculate_totals();
        if (!empty($_POST)) {
            if (!empty($_POST['formData']['product_id'])) {
                WC()->cart->empty_cart();
                $formData = isset($_POST['formData']) ? array_map('sanitize_text_field', wp_unslash($_POST['formData'])) : [];
                $productId = $formData['product_id'];
                $variationId = !empty($formData['variation_id']) ? $formData['variation_id'] : 0;
                $quantity = !empty($formData['quantity']) ? $formData['quantity'] : 1;
                if ($variationId) {
                    $product_id = (int) apply_filters('woocommerce_add_to_cart_product_id', $productId);
                    $vid = (int) apply_filters('woocommerce_add_to_cart_product_id', $variationId);
                    $product = new WC_Product_Variable($product_id);
                    $productData = $product->get_available_variation($vid);
                    $attributes = $this->set_attributes_array($formData);
                    WC()->cart->add_to_cart($product_id, $quantity, $vid, $attributes);
                    $tax = (WC()->cart->get_total_tax()) ? (WC()->cart->get_total_tax() - WC()->cart->get_shipping_tax()) / $quantity : 0;
                    $tax = round($tax, $WC_PayPlus_Gateway->rounding_decimals);
                    $priceProductWithTax = round($productData['display_price'] + $tax, ROUNDING_DECIMALS);
                    $priceProductWithoutTax = round($productData['display_price'], ROUNDING_DECIMALS);
                } else {
                    $product = new WC_Product($productId);
                    $priceProductWithTax = round(wc_get_price_including_tax($product), ROUNDING_DECIMALS);
                    $priceProductWithoutTax = round(wc_get_price_excluding_tax($product), ROUNDING_DECIMALS);
                    WC()->cart->add_to_cart($product->get_id(), $quantity);
                }

                $products[] = array(
                    'title' => $product->get_title(),
                    'priceProductWithTax' => $priceProductWithTax,
                    'priceProductWithoutTax' => $priceProductWithoutTax,
                    'quantity' => $quantity,
                );
            } else {
                $cart = WC()->cart->get_cart();
                if (count($cart)) {
                    foreach ($cart as $cart_item_key => $cart_item) {
                        $productId = $cart_item['product_id'];
                        // $product = new WC_Product($productId);
                        if (isset($cart_item['variation_id']) && !empty($cart_item['variation_id'])) {
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
                        $products[] = array(
                            'title' => $product->get_title(),
                            'priceProductWithTax' => $priceProductWithTax,
                            'priceProductWithoutTax' => $priceProductWithoutTax,
                            'quantity' => $cart_item['quantity'],
                        );
                    }
                }
                if (WC()->cart->get_total_discount()) {
                    $discountPrice = round(floatval(WC()->cart->get_discount_total()), ROUNDING_DECIMALS);
                }
            }
            $totalAll = WC()->cart->get_totals();
            $subTotalAll = WC()->cart->get_subtotal();
            $taxGlobal = round(WC()->cart->get_total_tax() - WC()->cart->get_shipping_tax(), ROUNDING_DECIMALS);
            $error = $totalAll['total'] == 0;

            echo wp_json_encode(array("error" => $error, "total" => $totalAll['total'], "products" => $products, "total_without_tax" => $subTotalAll, 'discountPrice' => $discountPrice ? $discountPrice : 0, "taxGlobal" => $taxGlobal));
        }
        wp_die();
    }

    /**
     * @param $datas
     * @return array
     */
    public function set_attributes_array($datas)
    {
        $arrData = array();
        if (count($datas)) {
            foreach ($datas as $key => $value) {
                if (strpos($key, 'attribute_pa') !== false) {
                    $arrData[$key] = $value;
                }
            }
        }
        return $arrData;
    }

    /**
     * @param int $id
     * @return array
     */
    public function get_shipping_by_id($id)
    {
        global $woocommerce;
        $shipping = WC_Shipping_Zones::get_shipping_method($id);
        $objShipping = $shipping->instance_settings;
        $objShipping['rate_id'] = $shipping->id . ":" . $id;
        return $objShipping;
    }

    /**
     * @param int $order_id
     * @param array $response
     * @return void
     */
    public function updateMetaDataOneClick($order_id, $response)
    {
        $insertMeta = array();
        $order = wc_get_order($order_id);
        if (count($response)) {
            foreach ($response as $key => $values) {
                if (is_object($values)) {
                    foreach ($values as $key1 => $value) {
                        if (is_object($value)) {
                            foreach ($value as $key2 => $value2) {
                                $insertMeta['payplus_' . $key2] = wc_clean($value2);
                            }
                        } else {
                            $insertMeta['payplus_' . $key1] = wc_clean($value);
                        }
                    }
                } else {
                    $insertMeta['payplus_' . $key] = wc_clean($value);
                }
            }
        }
        $insertMeta['payplus_transaction_uid'] = $insertMeta['payplus_uid'];
        $order->set_payment_method('payplus-payment-gateway');
        $order->set_payment_method_title('Pay with Debit or Credit Card');
        $insertMeta['payplus_refunded'] = $order->get_total();
        $insertMeta['payplus_type_current'] = 'Express Checkout';
        $insertMeta['payplus_type'] = 'Charge';
        WC_PayPlus_Meta_Data::update_meta($order, $insertMeta);
    }

    /**
     * @param $order
     * @param $id
     * @return WC_Order_Item_Shipping
     * @throws WC_Data_Exception
     */
    public function create_shipping_order($order, $id)
    {
        $country = $order->get_billing_country();
        $calculate_tax_for = array('country' => $country);
        $shipping = $this->get_shipping_by_id($id);
        $item = new WC_Order_Item_Shipping();
        $item->set_method_title($shipping['title']);
        $item->set_method_id($shipping['rate_id']);
        $item->set_total((isset($shipping['cost'])) ? $shipping['cost'] : 0);
        $item->calculate_taxes($calculate_tax_for);
        return $item;
    }

    /**
     * @return bool
     */
    public function payplus_check_product_isnot_one_click()
    {
        global $product;

        // Check if $product is a valid product object
        if (!is_object($product) || !($product instanceof WC_Product)) {
            return false;
        }

        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();
        return $product->get_type() !== "external"
            && $product->get_type() !== "grouped"
            && $product->get_type() !== "subscription"
            && $WC_PayPlus_Gateway->enable_product;
    }

    /**
     * @param bool $visible
     * @return bool
     */
    public function payplus_check_one_click_visible($visible = false)
    {
        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();
        $isCheckout = is_cart() || is_checkout() || $visible;
        $isProduct = is_product() && $this->payplus_check_product_isnot_one_click();
        $isSubscriptionOrder = false;
        if ($isCheckout || $isProduct) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (get_class($cart_item['data']) === "WC_Product_Subscription" || get_class($cart_item['data']) === "WC_Product_Subscription_Variation") {
                    $isSubscriptionOrder = true;
                    break;
                }
            }
        }

        $isGoogleEnable = $WC_PayPlus_Gateway->enable_google_pay;
        $appleAvailable = "<script>document.write(applePayAvailable);</script>";
        $isAppleEnable = $WC_PayPlus_Gateway->enable_apple_pay;
        $flag = ($isGoogleEnable || ($isAppleEnable && $appleAvailable != 'undefined')) && ($isCheckout || $isProduct) && !$isSubscriptionOrder;

        return $flag;
    }

    /**
     * @return array|string|string[]|void
     */
    public function payplus_extra_button_short_code()
    {
        return $this->payplus_extra_button_on_product_page(true);
    }


    public function get_continent_by_country($country_code)
    {
        $continent_map = [
            'Africa' => ['DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CM', 'CV', 'CF', 'TD', 'KM', 'CD', 'DJ', 'EG', 'GQ', 'ER', 'ET', 'GA', 'GM', 'GH', 'GN', 'GW', 'CI', 'KE', 'LS', 'LR', 'LY', 'MG', 'MW', 'ML', 'MR', 'MU', 'YT', 'MA', 'MZ', 'NA', 'NE', 'NG', 'RW', 'ST', 'SN', 'SC', 'SL', 'SO', 'ZA', 'SS', 'SD', 'SZ', 'TZ', 'TG', 'TN', 'UG', 'EH', 'ZM', 'ZW'],
            'Europe' => ['AL', 'AD', 'AT', 'BY', 'BE', 'BA', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FO', 'FI', 'FR', 'DE', 'GI', 'GR', 'GG', 'VA', 'HU', 'IS', 'IE', 'IM', 'IT', 'JE', 'LV', 'LI', 'LT', 'LU', 'MT', 'MD', 'MC', 'ME', 'NL', 'MK', 'NO', 'PL', 'PT', 'RO', 'RU', 'SM', 'RS', 'SK', 'SI', 'ES', 'SE', 'CH', 'UA', 'GB'],
            'Asia' => ['AF', 'AM', 'AZ', 'BH', 'BD', 'BT', 'BN', 'KH', 'CN', 'CY', 'GE', 'IN', 'ID', 'IR', 'IQ', 'IL', 'JP', 'JO', 'KZ', 'KW', 'KG', 'LA', 'LB', 'MY', 'MV', 'MN', 'MM', 'NP', 'OM', 'PK', 'PH', 'QA', 'SA', 'SG', 'KR', 'LK', 'SY', 'TW', 'TJ', 'TH', 'TR', 'TM', 'AE', 'UZ', 'VN', 'YE'],
            'North America' => ['AG', 'BS', 'BB', 'BZ', 'BM', 'VG', 'CA', 'KY', 'CR', 'CU', 'DM', 'DO', 'SV', 'GD', 'GT', 'HT', 'HN', 'JM', 'MX', 'NI', 'PA', 'KN', 'LC', 'VC', 'TT', 'US'],
            'South America' => ['AR', 'BO', 'BR', 'CL', 'CO', 'EC', 'FK', 'GF', 'GY', 'PY', 'PE', 'SR', 'UY', 'VE'],
            'Oceania' => ['AS', 'AU', 'CK', 'FJ', 'PF', 'GU', 'KI', 'MH', 'FM', 'NR', 'NC', 'NZ', 'NU', 'NF', 'MP', 'PW', 'PG', 'PN', 'WS', 'SB', 'TK', 'TO', 'TV', 'VU', 'WF'],
            'Antarctica' => ['AQ']
        ];

        foreach ($continent_map as $continent => $countries) {
            if (in_array($country_code, $countries)) {
                return $continent;
            }
        }

        return 'Unknown'; // If the country code doesn't match any continent
    }

    public function get_continent_full_name($continent_code)
    {
        $continent_map = [
            'AF' => 'Africa',
            'AS' => 'Asia',
            'EU' => 'Europe',
            'NA' => 'North America',
            'SA' => 'South America',
            'OC' => 'Oceania',
            'AN' => 'Antarctica'
        ];

        return isset($continent_map[$continent_code]) ? $continent_map[$continent_code] : 'Unknown';
    }

    /**
     * @param $visible
     * @return array|string|string[]|void
     */
    public function payplus_extra_button_on_product_page($visible = null)
    {
        ob_start();
        global $product;
        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();

        if ($this->payplus_check_one_click_visible($visible)) {

            $shippingWoo = ($WC_PayPlus_Gateway->shipping_woo) ? "true" : "false";
            $globalShipping = round($WC_PayPlus_Gateway->global_shipping, ROUNDING_DECIMALS);
            $globalShippingTax = $WC_PayPlus_Gateway->global_shipping_tax;
            $globalShippingTaxRate = $WC_PayPlus_Gateway->global_shipping_tax_rate;
            $shippingPrice = $this->get_all_shipping_costs();
            $shipping_zones = WC_Shipping_Zones::get_zones();
            $customerCountry = WC()->customer->get_shipping_country();
            $customerContinent = $this->get_continent_by_country($customerCountry);
            $continent_shipping_rates = [];
            $shippingArray = json_decode($shippingPrice, true);

            foreach ($shipping_zones as $zone_data) {
                $zone = new WC_Shipping_Zone($zone_data['zone_id']); // Initialize zone object
                $shipping_methods = $zone->get_shipping_methods();
                // Get the zone locations (countries or regions)
                $zone_locations = $zone->get_zone_locations();
                if ($shippingPrice) {
                    foreach ($shipping_methods as $id => $shipping_method) {
                        if (isset($shipping_method->requires)) {
                            $condition = $shipping_method->requires;
                            $requiredCondition = property_exists($shipping_method, $condition) ? $shipping_method->$condition : false;
                            $shippingPricesArray = json_decode($shippingPrice, true);
                            foreach ($shippingPricesArray as $country => $siPrice) {
                                foreach ($siPrice as $key => $sp) {
                                    if ($sp['id'] === $id && $requiredCondition) {
                                        $shippingPricesArray[$country][$key]['condition'][$condition] = $requiredCondition;
                                    }
                                }
                            }
                        }
                    }
                    if (isset($shippingPricesArray) && is_array($shippingPricesArray)) {
                        $shippingPrice = wp_json_encode($shippingPricesArray);
                    }
                }
            }

            $shippingPrice = ($shippingPrice) ? $shippingPrice : "";
            $shippingPrice = isset($newShippingArray) && is_array($newShippingArray) && !$shippingPrice ? wp_json_encode($newShippingArray) : $shippingPrice;

            if (!empty($shippingPrice) || isset($newShippingArray)) {
                $allShippingArray = json_decode($shippingPrice, true);
                if (isset($newShippingArray) && is_array($newShippingArray) && isset($newShippingArray[$customerCountry]) && !isset($allShippingArray[$customerCountry]) && is_array($allShippingArray)) {
                    $allShippingArray = array_merge($allShippingArray, $newShippingArray);
                }
                if (isset($allShippingArray) && is_array($allShippingArray)) {
                    foreach ($allShippingArray as &$innerArray) {
                        usort($innerArray, function ($a, $b) {
                            return $a['cost_with_tax'] <=> $b['cost_with_tax'];
                        });
                    }
                    unset($innerArray); // Unset reference to avoid issues
                    $shippingPrice = wp_json_encode($allShippingArray);
                }
            }
            foreach (json_decode($shippingPrice, true) as $country => $entries) {
                $array[$country] = array_values(array_unique($entries, SORT_REGULAR));
            }

            $shippingPrice = wp_json_encode($array);

            $productId = ($product) ? $product->get_id() : "";
            $productName = ($product) ? $product->get_title() : "";
            $disabled = ($product && $product->get_type() === "variable") ? "disabled" : "";
            $priceProductWithTax = "";
            $priceProductWithoutTax = "";
            if (is_product()) {
                $priceProductWithTax = round(wc_get_price_including_tax($product), ROUNDING_DECIMALS);
                $priceProductWithoutTax = round(wc_get_price_excluding_tax($product), ROUNDING_DECIMALS);
                echo '<div id="express-checkout" class="express-checkout-product ' . esc_attr($disabled) . '">';
            } else {
                echo '<div id="express-checkout" class="express-checkout ' . esc_attr($disabled) . '">';
            }

        ?>
            <input type="hidden" value="<?php echo esc_attr($priceProductWithTax) ?>" id="payplus_pricewt_product">
            <input type="hidden" value="<?php echo esc_attr($priceProductWithoutTax) ?>" id="payplus_pricewithouttax_product">
            <input type="hidden" value="<?php echo esc_attr($productName) ?>" id="payplus_product_name">
            <input type="hidden" value="<?php echo esc_attr($shippingPrice) ?>" id="payplus_shipping">
            <input type="hidden" value="<?php echo esc_attr(get_woocommerce_currency()) ?>" id="payplus_currency_code">
            <input type="hidden" value="<?php echo esc_attr($shippingWoo) ?>" id="payplus_shipping_woo">
            <?php
            if ($shippingWoo === "false") {
                $globalShippingPriceTax = $globalShipping;
                if ($globalShippingTax == "taxable" && get_option('woocommerce_calc_taxes') == 'yes') {

                    $rate = (floatval($globalShippingTaxRate)) ? round(floatval($globalShippingTaxRate) / 100, ROUNDING_DECIMALS) : 0;
                    $globalShippingPriceTax = $globalShipping * (1 + $rate);
                    $globalShippingPriceTax = ($rate) ? round($globalShippingPriceTax, ROUNDING_DECIMALS) : $globalShipping;
                }
            ?>
                <input type="hidden" value="<?php echo esc_attr($globalShipping) ?>" id="payplus_price_shipping">
                <input type="hidden" value="<?php echo esc_attr($globalShippingPriceTax) ?>" id="payplus_pricewt_shipping">
                <input type="hidden" value="<?php echo esc_attr($globalShipping) ?>" id="payplus_pricewithouttax_shipping">
<?php
            }
            echo '<div class="express-flex" >';
            echo "<div class='line-express-left'>
             <span></span>
            </div>";
            echo '<p class="title-express-checkout"><span style="font-size: 18px">' . esc_html__('Express Checkout', 'payplus-payment-gateway') . '</span></p>';
            echo "<div class='line-express-right'>
                <span></span>
            </div>";
            echo "</div>";
            if ($WC_PayPlus_Gateway->enable_google_pay) {
                $date = new DateTime();
                $current_timestamp = $date->getTimestamp();
                $bi = base64_encode(site_url());
                $requirePhone = $WC_PayPlus_Gateway->get_option('require_phone') === 'yes' ? true : false;
                !$requirePhone ? $required = "" : $required = "required";
                $phoneNumberPlaceHolder = esc_html__('Phone number here:', 'payplus-payment-gateway');
                echo "<div class='express-checkout-buttons'>";
                echo "<input type='text' id='phone-number' name='phone-number' placeholder='" . esc_attr($phoneNumberPlaceHolder) . "' style='display: none;' " . esc_attr($required) . ">";
                echo '<iframe class="' . esc_attr($disabled) . '" allow="payment *" sandbox="allow-forms allow-scripts allow-same-origin allow-popups" allowpaymentrequest id="googlePayButton" src="' . esc_attr($WC_PayPlus_Gateway->payplus_iframe_google_pay_oneclick) . '?var=' . esc_attr($current_timestamp) . '&wb=' . esc_attr($bi) . '" style="width: 100%; height: 50px; display: block;" frameborder="0" data-product-id="' . esc_attr($productId) . '"></iframe>';
                echo '</div>';
            }
            if ($WC_PayPlus_Gateway->enable_apple_pay) {
                echo '<button   lang="en" id="applePayButton" data-product-id="' . esc_attr($productId) . '" onclick="handleApplePayClick(event);" class="apple-pay-button apple-pay-button-with-text apple-pay-button-black-with-text ' . esc_attr($disabled) . '" style="padding: 18px;width:100%; display:none"></button>';
            }
            echo '<div id="error-api-payplus"></div>';
            echo '</div>';
            if (!is_product()) {
                echo '<div class="border-with-word">
                <span style="font-size: 18px;">' . esc_html__('Or', 'payplus-payment-gateway') . '</span>
                </div>';
            }
        }

        $output = str_replace(array("\r", "\n"), '', trim(ob_get_clean()));

        if (is_bool($visible) && $visible) {
            return $output;
        }
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * @return bool|void
     */
    public function payplus_add_file_ApplePay()
    {
        global $wp_filesystem;

        // Initialize the WordPress filesystem
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Setup the filesystem if it's not already initialized
        if (!WP_Filesystem()) {
            return false;
        }

        $sourceFile = PAYPLUS_SRC_FILE_APPLE . '/' . PAYPLUS_APPLE_FILE;
        $destinationFile = PAYPLUS_DEST_FILE_APPLE . '/' . PAYPLUS_APPLE_FILE;

        if (!$wp_filesystem->exists($destinationFile)) {
            if ($wp_filesystem->exists($sourceFile)) {
                if (!$wp_filesystem->is_dir(PAYPLUS_DEST_FILE_APPLE)) {
                    $wp_filesystem->mkdir(PAYPLUS_DEST_FILE_APPLE);
                    $wp_filesystem->chmod(PAYPLUS_DEST_FILE_APPLE, 0777);
                }
                if (!$wp_filesystem->exists($destinationFile)) {
                    if ($wp_filesystem->copy($sourceFile, $destinationFile, true)) {
                        return true;
                    } else {
                        return false;
                    }
                }
            } else {
                return false;
            }
        } else {
            return true;
        }
    }


    /**
     * @param $country_code
     * @return array
     */
    public function get_shipping_costs_by_country($country_code)
    {
        $defined_zones = WC_Shipping_Zones::get_zones();
        $new_array = array();
        if (count($defined_zones)) {
            foreach ($defined_zones as $zone) {
                foreach ($zone['zone_locations'] as $location) {
                    if ('country' === $location->type && $country_code === $location->code) {
                        foreach ($zone['shipping_methods'] as $shipping_method) {

                            $enabled = $shipping_method->enabled == 'yes' ? true : false;
                            $method_id = $shipping_method->id;
                            $instance_id = $shipping_method->instance_id;
                            $rate_id = $method_id . ':' . $instance_id;
                            $shipping_rate = new WC_Shipping_Rate($rate_id, $shipping_method->title, $shipping_method->instance_settings);
                            $item = $shipping_rate->get_cost();

                            if ($enabled && isset($item['title'])) {
                                $shipping_cost = isset($item['cost']) ? $item['cost'] : 0;
                                $shipping_title = $item['title'];

                                $tax_rates = WC_Tax::get_shipping_tax_rates();
                                $shipping_tax = WC_Tax::calc_tax($shipping_cost, $tax_rates);
                                $shipping_price_with_tax = floatval($shipping_cost);

                                if (count($shipping_tax)) {
                                    if (array_key_exists(1, $shipping_tax)) {
                                        $shipping_price_with_tax += floatval($shipping_tax[1]);
                                    }
                                }
                                $new_array[] = array(
                                    "id" => $shipping_method->instance_id,
                                    "title" => $shipping_title,
                                    "cost_without_tax" => strval(round($shipping_cost, ROUNDING_DECIMALS)),
                                    "cost_with_tax" => get_option('woocommerce_calc_taxes') == 'yes' ? strval(round($shipping_price_with_tax, ROUNDING_DECIMALS)) : strval(round($shipping_cost, ROUNDING_DECIMALS)),
                                );
                            }
                        }
                        break;
                    }
                }
            }
        }
        return $new_array;
    }

    /**
     * @return array
     */
    public function get_shipping_costs_for_rest_of_world()
    {
        $new_array = array();
        $shipping_zones = WC_Shipping_Zones::get_zones();
        if (count($shipping_zones)) {
            foreach ($shipping_zones as $zone) {
                if ($zone['formatted_zone_location'] === "Everywhere") {
                    if (isset($zone['formatted_zone_location'])) {
                        foreach ($zone['shipping_methods'] as $shipping_method) {
                            $method_id = $shipping_method->id;
                            $instance_id = $shipping_method->instance_id;
                            $rate_id = $method_id . ':' . $instance_id;
                            $shipping_rate = new WC_Shipping_Rate($rate_id, $shipping_method->title, $shipping_method->instance_settings);
                            $item = $shipping_rate->get_cost();
                            if (isset($item['title'])) {
                                $shipping_cost = isset($item['cost']) ? $item['cost'] : 0;
                                $shipping_title = $item['title'];

                                $tax_rates = WC_Tax::get_shipping_tax_rates();
                                $shipping_tax = WC_Tax::calc_tax($shipping_cost, $tax_rates);
                                $shipping_price_with_tax = floatval($shipping_cost);
                                if (count($shipping_tax)) {
                                    if (array_key_exists(1, $shipping_tax)) {
                                        $shipping_price_with_tax += floatval($shipping_tax[1]);
                                    }
                                }
                                $new_array[] = array(
                                    "id" => $shipping_method->instance_id,
                                    "title" => $shipping_title,
                                    "cost_without_tax" => round($shipping_cost, ROUNDING_DECIMALS),
                                    "cost_with_tax" => get_option('woocommerce_calc_taxes') == 'yes' ? round($shipping_price_with_tax, ROUNDING_DECIMALS) : round($shipping_cost, ROUNDING_DECIMALS),
                                );
                            }
                        }
                    }
                }
            }
        }
        return $new_array;
    }

    /**
     * @return false|string
     */
    public function get_all_shipping_costs()
    {
        $all_country_codes = WC()->countries->get_shipping_countries();
        $all_shipping_costs = array();
        if (count($all_country_codes)) {
            foreach ($all_country_codes as $country_code => $country_name) {
                $shipping_costs = $this->get_shipping_costs_by_country($country_code);

                if (!empty($shipping_costs)) {
                    $all_shipping_costs[$country_code] = $shipping_costs;
                }
            }
        }

        $array_rest_of_world = $this->get_shipping_costs_for_rest_of_world();
        if (!empty($array_rest_of_world)) {
            $all_shipping_costs['all'] = $array_rest_of_world;
        }
        if (!count($all_shipping_costs)) {
            return false;
        }
        return wp_json_encode($all_shipping_costs);
    }
}
new WC_PayPlus_Express_Checkout();
