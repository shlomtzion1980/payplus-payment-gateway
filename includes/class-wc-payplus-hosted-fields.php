<?php
defined('ABSPATH') or die('Hey, You can\'t access this file!'); // Exit if accessed directly

class WC_PayPlus_HostedFields extends WC_PayPlus
{
    private $order_id;
    private $order;
    private $initiated = false;
    protected static $instance = null;
    public $options;
    public $testMode;
    public $url;
    public $apiKey;
    public $secretKey;
    public $paymentPageUid;
    public $apiUrl;
    public $vat4All;
    public $payPlusGateway;
    public $isHideLoaderLogo;
    public $isHostedStarted;
    public $isPlaceOrder;
    public $showSubmitButton;
    public $pwGiftCardData;

    /**
     * Static holder for WP GiftCards session data.
     * Populated by the pwgc_redeeming_session_data filter (see modify_gift_card_session_data).
     * Was $this->pwGiftCardData on the former WC_PayPlus_Embedded class — kept as a static
     * so the plugin-init hook path does not have to instantiate this class (whose
     * constructor is the heavy page-load setup for Hosted Fields).
     */
    public static $pwGiftCardDataStatic = null;

    /**
     * Register the plugin-init hooks previously owned by WC_PayPlus_Embedded.
     *
     * Called once at plugin bootstrap (see payplus-payment-gateway.php). Must NOT be
     * called from the page-load setup path — that path uses the heavy constructor
     * directly (new WC_PayPlus_HostedFields) to render the template + enqueue assets.
     */
    public static function register_hooks()
    {
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'on_woocommerce_checkout_order_processed'], 25, 3);
        add_filter('pwgc_redeeming_session_data', [__CLASS__, 'capture_gift_card_session_data'], 10, 2);
    }

    /**
     * Hook callback: woocommerce_checkout_order_processed (priority 25).
     *
     * When a Hosted Fields order is created, run the strict Update against the same
     * PayPlus payment page the browser's hosted-fields DOM is bound to. This is what
     * guarantees the real order data (real customer + real order id in more_info)
     * reaches PayPlus BEFORE the charge is authorized. Only fires for Hosted Fields
     * payment method — all other gateways are ignored.
     */
    public static function on_woocommerce_checkout_order_processed($order_id, $posted_data, $order)
    {
        if (strpos($order->get_payment_method(), 'payplus-payment-gateway-hostedfields') !== 0) {
            return;
        }
        WC()->session->set('order_awaiting_payment', $order_id);
        self::update_hosted_page_for_order($order_id, $order);
    }

    /**
     * Filter callback: pwgc_redeeming_session_data.
     *
     * Cache WP GiftCards session data for later use by update_hosted_page_for_order()
     * when it builds the Update payload. Kept as a static so the plugin-init flow
     * doesn't need an instance of WC_PayPlus_HostedFields (whose constructor is heavy).
     *
     * Renamed from modify_gift_card_session_data (its name on the former
     * WC_PayPlus_Embedded class) to avoid shadowing WC_PayPlus's same-named instance
     * method which has a different signature. Filter callbacks are wired by name,
     * so the rename has no runtime effect.
     */
    public static function capture_gift_card_session_data($session_data, $gift_card_number)
    {
        self::$pwGiftCardDataStatic = $session_data;
        return $session_data;
    }

    /**
     * Strict Update path — builds the real-order payload and calls PayPlus
     * /Update/{page_request_uid} for the SAME page the browser is bound to.
     *
     * If the Update fails for any reason, this refuses to fall back to generateLink
     * (which would create a new page the browser cannot reach and let the old
     * placeholder page be charged) and instead flags the session so
     * WC_PayPlus_Gateway_HostedFields::process_payment refuses to authorize the
     * charge. The customer is then asked to refresh and start with a new setup page.
     *
     * Previously lived on WC_PayPlus_Embedded::hostedFieldsData($order_id) — merged
     * here so the whole Hosted Fields feature lives on one class.
     *
     * @param int      $order_id Real order id (numeric).
     * @param WC_Order $order    WC order object for that id.
     */
    public static function update_hosted_page_for_order($order_id, $order)
    {
        $payplus_gateway = WC_PayPlus::get_instance()->get_main_payplus_gateway();
        $settings = get_option('woocommerce_payplus-payment-gateway_settings');
        $testMode = boolval(isset($settings['api_test_mode']) && $settings['api_test_mode'] === 'yes');
        $paymentPageUid = $testMode ? $settings['dev_payment_page_id'] : $settings['payment_page_id'];
        $vat4All = isset($settings['paying_vat_all_order']) ? boolval($settings['paying_vat_all_order'] === 'yes') : false;
        $pwGiftCardData = self::$pwGiftCardDataStatic;

        if (!is_int($order_id) || !$order) {
            $payplus_gateway->payplus_add_log_all(
                'hosted-fields-data',
                'HostedFields update ABORTED — non-numeric order_id or missing order object.'
            );
            return;
        }

        // Lock before the Update API so a parallel checkout render cannot generateLink.
        self::begin_hosted_charge_lock($order_id);

        $payplus_gateway->payplus_add_log_all(
            'hosted-fields-data',
            'PayPlus Hosted Fields update for order #: (' . $order_id . ')'
        );

        $products = [];
        $merchantCountryCode = substr(get_option('woocommerce_default_country'), 0, 2);
        WC()->customer->set_shipping_country($merchantCountryCode);
        WC()->cart->calculate_totals();
        $wc_tax_enabled = wc_tax_enabled();

        if (isset($pwGiftCardData) && $pwGiftCardData && !empty($pwGiftCardData['gift_cards']) && is_array($pwGiftCardData['gift_cards'])) {
            foreach ($pwGiftCardData['gift_cards'] as $giftCardId => $giftCard) {
                $priceGift = number_format(-1 * ($giftCard), 2, '.', '');
                $products[] = [
                    'title' => __('PW Gift Card', 'payplus-payment-gateway'),
                    'barcode' => $giftCardId,
                    'quantity' => 1,
                    'priceProductWithTax' => $priceGift,
                ];
            }
        }
        $objectProducts = $payplus_gateway->payplus_get_products_by_order_id($order_id);
        foreach ($objectProducts->productsItems as $item) {
            $product = json_decode($item, true);
            $productId = isset($product['barcode']) ? $product['barcode'] : str_replace(' ', '', $product['name']);
            $products[] = [
                'title' => $product['name'],
                'priceProductWithTax' => number_format($product['price'], 2, '.', ''),
                'barcode' => $productId,
                'quantity' => $product['quantity'],
                'vat_type' => isset($product['vat_type']) ? $product['vat_type'] : 0,
            ];
        }

        $data = new stdClass();
        $data->payment_page_uid = $paymentPageUid;
        $data->refURL_success = site_url() . '?wc-api=payplus_gateway&hostedFields=true';
        $_wpnonce = wp_create_nonce('PayPlusGateWayNonce');
        $data->refURL_callback = get_site_url(null, '/?wc-api=callback_response&_wpnonce=' . $_wpnonce);
        $data->refURL_failure = site_url() . '/error-payment-payplus/';
        $data->refURL_cancel = site_url() . '/cancel-payment-payplus/';
        $data->create_token = true;
        $data->currency_code = get_woocommerce_currency();
        $data->charge_method = intval($payplus_gateway->settings['transaction_type']);
        $data->refURL_origin = site_url();
        $data->hosted_fields = true;

        $payPlusInvoice = new PayplusInvoice;
        $customer = $payPlusInvoice->payplus_get_client_by_order_id($order_id);
        $data->customer = new stdClass();

        // Use real billing name for customer_name so tokens are saved with the correct
        // billing identity ($customer['name'] may contain the invoice-name override).
        $billingName = '';
        if ($payplus_gateway->exist_company && !empty($order->get_billing_company())) {
            $billingName = $order->get_billing_company();
        } else {
            if (!empty($order->get_billing_first_name()) || !empty($order->get_billing_last_name())) {
                $billingName = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            }
            if (!$billingName) {
                $billingName = $order->get_billing_company();
            } elseif ($order->get_billing_company()) {
                $billingName .= ' (' . $order->get_billing_company() . ')';
            }
        }
        $data->customer->customer_name = !empty($billingName) ? $billingName : $customer['name'];
        $data->customer->email = $customer['email'];
        $data->customer->phone = $customer['phone'];
        $data->customer->address = $customer['street_name'];
        $data->customer->city = $customer['city'];
        $data->customer->postal_code = $customer['postal_code'];
        $data->customer->country_iso = $customer['country_iso'];
        $data->customer->customer_external_number = $order->get_customer_id();

        $customer_invoice_name = WC_PayPlus_Meta_Data::get_meta($order_id, '_billing_customer_invoice_name');
        if (!empty($customer_invoice_name)) {
            $data->customer->customer_name_invoice = $customer_invoice_name;
        }

        $customer_other_id = WC_PayPlus_Meta_Data::get_meta($order_id, '_billing_customer_other_id');
        if (!empty($customer_other_id)) {
            $data->customer->vat_number = $customer_other_id;
        } elseif ($payplus_gateway->vat_number_field && $order->get_meta($payplus_gateway->vat_number_field)) {
            $data->customer->vat_number = $order->get_meta($payplus_gateway->vat_number_field);
        }

        $payingVat = isset($payplus_gateway->settings['paying_vat']) && in_array($payplus_gateway->settings['paying_vat'], [0, 1, 2]) ? $payplus_gateway->settings['paying_vat'] : false;
        if ($payingVat) {
            $payingVat = $payingVat === '0' ? true : false;
            $payingVat = $payingVat === '1' ? false : true;
            $payingVat = $payingVat === '2' ? ($customer['country_iso'] !== trim(strtolower($payplus_gateway->settings['paying_vat_iso_code'])) ? false : true) : $payingVat;
            $data->paying_vat = $payingVat;
        }

        foreach ($products as $product) {
            $item = new stdClass();
            $item->name = $product['title'];
            $item->quantity = $product['quantity'];
            $item->barcode = $product['barcode'];
            $item->price = $product['priceProductWithTax'];
            if (isset($product['vat_type'])) {
                $item->vat_type = $product['vat_type'];
            }
            $data->items[] = $item;
        }

        $data->more_info = $order_id;
        $totalAmount = 0;
        foreach ($data->items as $item) {
            $totalAmount += $item->price * $item->quantity;
        }
        $data->amount = number_format($totalAmount, 2, '.', '');

        $payload = wp_json_encode($data, JSON_UNESCAPED_UNICODE);

        // Capture the exact page_request_uid + hostedFieldsUUID the browser is bound to
        // (set into session by the initial setup-page create at page load).
        // We MUST update THAT page — creating a new page here would leave the browser
        // bound to the old (placeholder) page, and any subsequent charge would hit
        // that stale page with placeholder customer data + random-hash more_info.
        $boundPageRequestUid = WC()->session->get('page_request_uid');
        $boundHostedFieldsUUID = WC()->session->get('hostedFieldsUUID');

        if (empty($boundPageRequestUid) || empty($boundHostedFieldsUUID)) {
            $payplus_gateway->payplus_add_log_all(
                'hosted-fields-data',
                "HostedFields Update ABORTED for Order #$order_id – no bound page_request_uid/hostedFieldsUUID in session. Customer must refresh."
            );
            WC()->session->set('payplus_hosted_update_failed', true);
            WC()->session->set('payplus_hosted_updated_for_order', 0);
            self::release_hosted_charge_lock();
            return;
        }

        // Strict Update. No fallback to generateLink — that would create a new page
        // that the browser cannot reach and lets the old (placeholder) page get charged.
        $hostedResponse = WC_PayPlus_Statics::createUpdateHostedPaymentPageLink($payload, true, true);
        $hostedResponseArray = json_decode($hostedResponse, true);

        $returnedPageRequestUid = isset($hostedResponseArray['data']['page_request_uid'])
            ? $hostedResponseArray['data']['page_request_uid']
            : null;

        $updateOk = $returnedPageRequestUid === $boundPageRequestUid
            && (!isset($hostedResponseArray['results']['status']) || $hostedResponseArray['results']['status'] !== 'error');

        if (!$updateOk) {
            $payplus_gateway->payplus_add_log_all(
                'hosted-fields-data',
                "HostedFields Update FAILED for Order #$order_id – aborting charge. Bound PRUID: $boundPageRequestUid | Returned PRUID: " . ($returnedPageRequestUid ?: 'null') . " | Response: $hostedResponse"
            );
            WC()->session->set('page_request_uid', false);
            WC()->session->__unset('hostedFieldsUUID');
            WC()->session->set('hostedPayload', false);
            WC()->session->set('hostedResponse', false);
            WC()->session->set('payplus_hosted_update_failed', true);
            WC()->session->set('payplus_hosted_updated_for_order', 0);
            self::release_hosted_charge_lock();
            return;
        }

        // Update succeeded on the SAME page the browser is bound to.
        WC()->session->set('payplus_hosted_update_failed', false);
        WC()->session->set('payplus_hosted_updated_for_order', $order_id);

        $pageRequestUid = $hostedResponseArray['data']['page_request_uid'];
        // Meta key names are preserved verbatim from the former Embedded class so
        // historical order reads (e.g. wc_payplus_subgateways.php::getHostedPayload)
        // keep working against existing orders in the database.
        WC_PayPlus_Meta_Data::update_meta($order, ['payplus_page_request_uid' => $pageRequestUid]);
        WC_PayPlus_Meta_Data::append_pruid_history($order, $pageRequestUid, 'embedded');
        WC_PayPlus_Meta_Data::update_meta($order, ['payplus_embedded_payload' => $payload]);
        WC_PayPlus_Meta_Data::update_meta($order, ['payplus_embedded_update_page_response' => $hostedResponse]);
        WC()->session->set('hostedPayload', $payload);
        WC()->session->set('hostedResponse', $hostedResponse);
    }

    /**
     * Real Woo order id locked for Hosted Fields charge, or 0.
     *
     * @return int
     */
    public static function hosted_charge_lock_order_id()
    {
        if (!function_exists('WC') || !WC()->session) {
            return 0;
        }
        $id = 0;
        foreach (['payplus_hosted_charge_lock', 'payplus_hosted_updated_for_order'] as $key) {
            $value = WC()->session->get($key);
            if (is_numeric($value) && (int) $value > 0) {
                $id = (int) $value;
                break;
            }
        }
        if ($id > 0) {
            $order = wc_get_order($id);
            if ($order && $order->is_paid()) {
                self::reset_hosted_fields_session();
                return 0;
            }
        }
        return $id;
    }

    /**
     * @param int $order_id
     * @return void
     */
    public static function begin_hosted_charge_lock($order_id)
    {
        if (!function_exists('WC') || !WC()->session || !is_numeric($order_id) || (int) $order_id <= 0) {
            return;
        }
        WC()->session->set('payplus_hosted_charge_lock', (int) $order_id);
    }

    /**
     * @return void
     */
    public static function release_hosted_charge_lock()
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }
        WC()->session->set('payplus_hosted_charge_lock', 0);
        WC()->session->set('payplus_hosted_updated_for_order', 0);
        WC()->session->set('payplus_hosted_update_failed', false);
    }

    /**
     * Clear Hosted Fields session so the next checkout can create a new setup page.
     *
     * @return void
     */
    public static function reset_hosted_fields_session()
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }
        self::release_hosted_charge_lock();
        WC()->session->set('hostedTimeStamp', false);
        WC()->session->set('hostedPayload', false);
        WC()->session->set('page_request_uid', false);
        WC()->session->set('hostedResponse', false);
        WC()->session->__unset('hostedFieldsUUID');
        WC()->session->set('hostedStarted', false);
        WC()->session->set('randomHash', bin2hex(random_bytes(16)));
        WC()->session->__unset('payplus_verified_order');
    }

    /**
     *
     */
    public function __construct($order_id = "000", $order = null, $isPlaceOrder = false, $pwGiftCardData = false)
    {
        $this->payPlusGateway = $this->get_main_payplus_gateway();
        $this->isHideLoaderLogo = boolval(isset($this->payPlusGateway->hostedFieldsOptions['hide_loader_logo']) && $this->payPlusGateway->hostedFieldsOptions['hide_loader_logo'] === 'yes');
        $this->vat4All = isset($this->payPlusGateway->settings['paying_vat_all_order']) ? boolval($this->payPlusGateway->settings['paying_vat_all_order'] === "yes") : false;
        $this->testMode = boolval($this->payPlusGateway->settings['api_test_mode'] === 'yes');
        $this->url = $this->testMode ? PAYPLUS_PAYMENT_URL_DEV . 'Transactions/updateMoreInfos' : PAYPLUS_PAYMENT_URL_PRODUCTION . 'Transactions/updateMoreInfos';
        $this->apiKey = $this->testMode ? $this->payPlusGateway->settings['dev_api_key'] : $this->payPlusGateway->settings['api_key'];
        $this->secretKey = $this->testMode ? $this->payPlusGateway->settings['dev_secret_key'] : $this->payPlusGateway->settings['secret_key'];
        $this->paymentPageUid = $this->testMode ? $this->payPlusGateway->settings['dev_payment_page_id'] : $this->payPlusGateway->settings['payment_page_id'];
        $this->order_id = $order_id;
        $this->order = $order;
        $this->isPlaceOrder = $isPlaceOrder;
        $this->showSubmitButton = isset($this->payPlusGateway->hostedFieldsOptions['show_hide_submit_button']) && $this->payPlusGateway->hostedFieldsOptions['show_hide_submit_button'] === 'yes';
        if ($pwGiftCardData) {
            $this->pwGiftCardData = $pwGiftCardData;
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constants used by external hosted fields script
        define('API_KEY', $this->apiKey);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constants used by external hosted fields script
        define('SECRET_KEY', $this->secretKey);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constants used by external hosted fields script
        define('PAYMENT_PAGE_UID', $this->paymentPageUid);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constants used by external hosted fields script
        define('ORIGIN_DOMAIN', site_url());
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constants used by external hosted fields script
        define('SUCCESS_URL', site_url() . '?wc-api=payplus_gateway&hostedFields=true');
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constants used by external hosted fields script
        define('FAILURE_URL', site_url() . "/error-payment-payplus/");
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constants used by external hosted fields script
        define('CANCEL_URL', site_url() . "/cancel-payment-payplus/");

        /**
         * PAYPLUS_API_URL_DEV is the URL of the API in the development environment.
         */
        define('PAYPLUS_API_URL_DEV', 'https://restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink');

        /**
         * PAYPLUS_API_URL_PROD is the URL of the API in the production environment.
         */
        define('PAYPLUS_API_URL_PROD', 'https://restapi.payplus.co.il/api/v1.0/PaymentPages/generateLink');

        $this->apiUrl = $this->testMode ? PAYPLUS_API_URL_DEV : PAYPLUS_API_URL_PROD;

        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        if ((WC()->cart->get_subtotal() <= 0 || empty($available_gateways)) && !self::hosted_charge_lock_order_id()) {
            WC()->session->set('hostedTimeStamp', false);
            WC()->session->set('hostedPayload', false);
            WC()->session->set('page_request_uid', false);
            WC()->session->set('hostedResponse', false);
            WC()->session->__unset('order_awaiting_payment');
            WC()->session->__unset('hostedFieldsUUID');
            WC()->session->set('hostedStarted', false);
            WC()->session->set('randomHash', bin2hex(random_bytes(16)));
            return;
        }

        WC()->session->set('hostedStarted', false);
        $this->checkHostedTime() ? $hostedResponse = $this->hostedFieldsData($this->order_id) : $hostedResponse = $this->emptyResponse();
        $hostedResponse = !empty($hostedResponse) ? $hostedResponse : $hostedResponse = $this->emptyResponse();
        $hostedResponseArray = json_decode($hostedResponse, true);
        if(isset($hostedResponseArray['results']['status'])){
            $hostedResponseArray['results']['status'] === "error" ? $this->updateOrderId() : null;
        }

        if (isset($hostedResponse) && $hostedResponse && isset(json_decode($hostedResponse, true)['results']['status']) && json_decode($hostedResponse, true)['results']['status'] === "success") {
            $script_version = filemtime(plugin_dir_path(__DIR__) . 'assets/js/hostedFieldsScript.min.js');
            $template_path = plugin_dir_path(__DIR__) . 'templates/hostedFields.php';

            require_once PAYPLUS_PLUGIN_DIR . '/includes/class-wc-payplus-error-handler.php';
            $payPlusErrors = new WCPayPlusErrorCodes();

            if (file_exists($template_path)) {
                // Force load translations before template renders
                // We need to load early here because the template is rendered in the constructor
                // Suppress the "too early" warning since this is intentional and necessary
                if (!is_textdomain_loaded('payplus-payment-gateway')) {
                    $mofile = WP_LANG_DIR . '/plugins/payplus-payment-gateway-' . determine_locale() . '.mo';
                    if (file_exists($mofile)) {
                        load_textdomain('payplus-payment-gateway', $mofile);
                    } else {
                        // Fallback to plugin's own languages directory
                        $mofile = PAYPLUS_PLUGIN_DIR . '/languages/payplus-payment-gateway-' . determine_locale() . '.mo';
                        if (file_exists($mofile)) {
                            load_textdomain('payplus-payment-gateway', $mofile);
                        }
                    }
                }
                
                wp_enqueue_style('hosted-css', PAYPLUS_PLUGIN_URL . 'assets/css/hostedFields.css', [], $script_version);
                include $template_path;
            }
            wp_enqueue_script('payplus-hosted-fields-js', PAYPLUS_PLUGIN_URL . 'assets/js/payplus-hosted-fields/dist/payplus-hosted-fields.min.js', array('jquery'), '1.0', true);
            wp_register_script('payplus-hosted', PAYPLUS_PLUGIN_URL . 'assets/js/hostedFieldsScript.min.js', array('jquery'), $script_version, true);
            wp_localize_script(
                'payplus-hosted',
                'payplus_script_hosted',
                [
                    "hostedResponse" => $hostedResponse,
                    "isHideLoaderLogo" => $this->isHideLoaderLogo,
                    "processingText" => __('Processing Payment', 'payplus-payment-gateway'),
                    "isRtl" => is_rtl(),
                    "isLoggedIn" => boolval(get_current_user_id() > 0),
                    "isSavingCerditCards" => boolval(isset($this->payPlusGateway->settings['create_pp_token']) && $this->payPlusGateway->settings['create_pp_token'] === 'yes'),
                    'ajax_url' => admin_url('admin-ajax.php'),
                    "saveCreditCard" => __("Save credit card in my account", "payplus-payment-gateway"),
                    'testMode' => $this->testMode,
                    "showSubmitButton" => $this->showSubmitButton,
                    'allErrors' => $payPlusErrors->getAllTranslations(),
                    'frontNonce' => wp_create_nonce('frontNonce'),
                    'payPlusLogo' => PAYPLUS_PLUGIN_URL . 'assets/images/PayPlusLogo.svg',
                ]
            );
            wp_enqueue_script('payplus-hosted');
        }
    }

    public function emptyResponse()
    {
        $this->order_id = WC()->session->get('order_awaiting_payment');
        return $this->hostedFieldsData($this->order_id);
    }

    /**
     * @return null
     */
    public static function get_instance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }
        return static::$instance;
    }

    public function isInitiated()
    {
        if (!$this->initiated) {
            $this->initiated = true;
            parent::__construct();
        }
    }

    public function updateOrderId($randomHash = null)
    {
        $randomHash = $randomHash ?? bin2hex(random_bytes(16));
        WC()->session->set('order_awaiting_payment', $randomHash);
        $order_id = $randomHash;
        return $order_id;
    }

    public function hostedFieldsData($order_id)
    {
        $awaiting = WC()->session->get('order_awaiting_payment');
        if (!empty($awaiting)) {
            $order_id = is_numeric($awaiting) ? intval($awaiting) : $awaiting;
        }

        if ($order_id !== "000" && is_int($order_id)) {
            $order = wc_get_order($order_id);
            if (!$order && !empty(WC()->session->get('hostedPayload'))) {
                WC()->session->set('randomHash', $order_id = bin2hex(random_bytes(16)));
                $payload = json_decode(WC()->session->get('hostedPayload'), true);
                $payload['more_info'] = $order_id;
                // WC()->session->set('order_awaiting_payment', $order_id);
            }
            
            // Double check IPN if enabled and page request UID exists (for regular checkout pages)
            // Use session flag to prevent multiple checks for the same order in the same session
            $session_key = 'payplus_ipn_checked_' . $order_id;
            $already_checked = WC()->session && WC()->session->get($session_key);
            
            if ($order && !$already_checked && isset($this->payPlusGateway->enableDoubleCheckIfPruidExists) && $this->payPlusGateway->enableDoubleCheckIfPruidExists) {
                $pruid_history = WC_PayPlus_Meta_Data::get_pruid_history($order_id);
                
                if (!empty($pruid_history)) {
                    if (WC()->session) {
                        WC()->session->set($session_key, true);
                    }
                    
                    $PayPlusAdminPayments = new WC_PayPlus_Admin_Payments;
                    $_wpnonce = wp_create_nonce('_wp_payplusIpn');

                    foreach (array_reverse($pruid_history) as $entry) {
                        $uid = $entry['uid'];
                        $this->payPlusGateway->payplus_add_log_all('payplus_double_check', 'Hosted Fields Regular Checkout Order ID: ' . $order_id . ' | PRUID: ' . $uid . ' | Source: ' . ($entry['source'] ?? ''));
                        $status = $PayPlusAdminPayments->payplusIpn(
                            $order_id, $_wpnonce,
                            false, true, true, false, false, false, true, false,
                            $uid
                        );
                        $this->payPlusGateway->payplus_add_log_all('payplus_double_check', 'Hosted Fields Regular Checkout Order ID: ' . $order_id . ' | PRUID: ' . $uid . ' | Response Status: ' . ($status ? $status : 'null/empty'));
                        
                        if ($status === "processing" || $status === "on-hold" || $status === "approved") {
                            $this->payPlusGateway->payplus_add_log_all('payplus_double_check', 'Hosted Fields Regular Checkout Order ID: ' . $order_id . ' | PRUID: ' . $uid . ' | Status approved - Redirecting');
                            $redirect_url = $order->get_checkout_order_received_url();
                            wp_safe_redirect($redirect_url);
                            exit;
                        }
                    }
                    $this->payPlusGateway->payplus_add_log_all('payplus_double_check', 'Hosted Fields Regular Checkout Order ID: ' . $order_id . ' | No approved PRUID found - Continuing');
                } else {
                    $this->payPlusGateway->payplus_add_log_all('payplus_double_check', 'Hosted Fields Regular Checkout Order ID: ' . $order_id . ' | No PRUID history found - Skipping');
                }
            } elseif ($already_checked) {
                $this->payPlusGateway->payplus_add_log_all('payplus_double_check', 'Hosted Fields Regular Checkout Order ID: ' . $order_id . ' | Already checked in this session - Skipping');
            }
        }

        // $this->payPlusGateway->payplus_add_log_all("hosted-fields-data", 'HostedFields-hostedFieldsData(1): (' . $order_id . ')');
        $discountPrice = 0;
        $products = array();
        $merchantCountryCode = substr(get_option('woocommerce_default_country'), 0, 2);
        WC()->customer->set_shipping_country($merchantCountryCode);
        WC()->cart->calculate_totals();
        $cart = WC()->cart->get_cart();

        $wc_tax_enabled = wc_tax_enabled();
        $isTaxIncluded = wc_prices_include_tax();

        if (isset($order) && $order) {
            $products = [];
            if (isset($this->pwGiftCardData) && $this->pwGiftCardData && is_array($this->pwGiftCardData['gift_cards'])) {
                foreach ($this->pwGiftCardData['gift_cards'] as $giftCardId => $giftCard) {
                    $priceGift = 0;
                    $productPrice = -1 * ($giftCard);
                    $priceGift += number_format($productPrice, 2, '.', '');

                    $giftCards = [
                        'title' => __('PW Gift Card', 'payplus-payment-gateway'),
                        'barcode' => $giftCardId,
                        'quantity' => 1,
                        'priceProductWithTax' => $priceGift,
                    ];

                    $products[] = $giftCards;
                }
            }
            $objectProducts = $this->payPlusGateway->payplus_get_products_by_order_id($order_id);
            foreach ($objectProducts->productsItems as $item) {
                $product = json_decode($item, true);
                $productId = isset($product['barcode']) ? $product['barcode'] : str_replace(' ', '', $product['name']);
                $product_name = $product['name'];
                $product_quantity = $product['quantity'];
                $product_total = $product['price'];
                $productVat = isset($product['vat_type']) ? $product['vat_type'] : 0;

                $products[] = array(
                    'title' => $product_name,
                    'priceProductWithTax' => number_format($product_total, 2, '.', ''),
                    'barcode' => $productId,
                    'quantity' => $product_quantity,
                    'vat_type' => $productVat,
                );
            }
        } elseif (count($cart)) {
            foreach ($cart as $cart_item_key => $cart_item) {
                $productId = $cart_item['product_id'];

                if (isset($cart_item['variation_id']) && !empty($cart_item['variation_id'])) {
                    $product = new WC_Product_Variable($productId);
                    $productData = $product->get_available_variation($cart_item['variation_id']);
                    $tax = (WC()->cart->get_total_tax()) ? WC()->cart->get_total_tax() / $cart_item['quantity'] : 0;
                    $tax = number_format($tax, 2, '.', '');
                    $priceProductWithTax = number_format($productData['display_price'] + $tax, 2, '.', '');
                    $priceProductWithoutTax = number_format($productData['display_price'], 2, '.', '');
                } else {
                    $product = new WC_Product($productId);
                    $priceProductWithTax = number_format(wc_get_price_including_tax($product), 2, '.', '');
                    $priceProductWithoutTax = number_format(wc_get_price_excluding_tax($product), 2, '.', '');
                }

                $productVat = 0;

                if ($wc_tax_enabled) {
                    $productVat = $isTaxIncluded && $product->get_tax_status() === 'taxable' ? 0 : 1;
                    $productVat = $product->get_tax_status() === 'none' ? 2 : $productVat;
                    $productVat = $this->vat4All ? 0 : $productVat;
                }

                $products[] = array(
                    'title' => $product->get_name(),
                    'priceProductWithTax' => $priceProductWithTax,
                    'priceProductWithoutTax' => $priceProductWithoutTax,
                    'barcode' => ($product->get_sku()) ? (string) $product->get_sku() : (string) $productId,
                    'quantity' => $cart_item['quantity'],
                    'vat_type' => $productVat,
                    'org_product_tax' => $product->get_tax_status(),
                );
            }

            if (WC()->cart->get_total_discount()) {
                $discountPrice = number_format(floatval(WC()->cart->get_discount_total()), 2, '.', '');
            }
        }

        // this will be the create initial order data function that calls the curl to create at it's end.
        $checkout = WC()->checkout();

        // Best-effort prefill: prefer posted checkout data → WC customer session →
        // logged-in user meta. Placeholders are only used as a last resort. Note that
        // the real order data is guaranteed to overwrite this via the Update flow in
        // WC_PayPlus_HostedFields::on_woocommerce_checkout_order_processed before the
        // charge is authorized (process_payment refuses to complete otherwise).
        $wc_customer = WC()->customer;
        $current_user = is_user_logged_in() ? wp_get_current_user() : null;

        $billing_first_name = $checkout->get_value('billing_first_name');
        if (empty($billing_first_name) && $wc_customer) {
            $billing_first_name = $wc_customer->get_billing_first_name();
        }
        if (empty($billing_first_name) && $current_user) {
            $billing_first_name = $current_user->first_name ?: get_user_meta($current_user->ID, 'billing_first_name', true);
        }
        $billing_first_name = !empty($billing_first_name) ? $billing_first_name : "general-first-name";

        $billing_last_name = $checkout->get_value('billing_last_name');
        if (empty($billing_last_name) && $wc_customer) {
            $billing_last_name = $wc_customer->get_billing_last_name();
        }
        if (empty($billing_last_name) && $current_user) {
            $billing_last_name = $current_user->last_name ?: get_user_meta($current_user->ID, 'billing_last_name', true);
        }
        $billing_last_name = !empty($billing_last_name) ? $billing_last_name : "general-last-name";

        $billing_email = $checkout->get_value('billing_email');
        if (empty($billing_email) && $wc_customer) {
            $billing_email = $wc_customer->get_billing_email();
        }
        if (empty($billing_email) && $current_user && !empty($current_user->user_email)) {
            $billing_email = $current_user->user_email;
        }
        $billing_email = !empty($billing_email) ? $billing_email : "general@payplus.co.il";

        $shipping_address = $checkout->get_value('shipping_address_1');
        if (empty($shipping_address) && $wc_customer) {
            $shipping_address = $wc_customer->get_shipping_address_1() ?: $wc_customer->get_billing_address_1();
        }
        $shipping_address = !empty($shipping_address) ? $shipping_address : "general-shipping-address";

        $phone = $checkout->get_value('billing_phone');
        if (empty($phone) && $wc_customer) {
            $phone = $wc_customer->get_billing_phone();
        }
        if (empty($phone) && $current_user) {
            $phone = get_user_meta($current_user->ID, 'billing_phone', true);
        }
        $phone = !empty($phone) ? $phone : "050-0000000";

        // Building sample request to create a payment page
        $data = new stdClass();
        $data->payment_page_uid = PAYMENT_PAGE_UID;
        $data->refURL_success = SUCCESS_URL;
        $_wpnonce = wp_create_nonce('PayPlusGateWayNonce');
        $data->refURL_callback = get_site_url(null, '/?wc-api=callback_response&_wpnonce=' . $_wpnonce);
        $data->refURL_failure = FAILURE_URL;
        $data->refURL_cancel = CANCEL_URL;
        $data->create_token = true;
        $data->currency_code = get_woocommerce_currency();
        $data->charge_method = intval($this->payPlusGateway->settings['transaction_type']);
        /**
         * Origin domain is the domain of the page that is requesting the payment page.
         * This is necessary for the hosted fields to be able to communicate with the client website.
         */
        $data->refURL_origin = ORIGIN_DOMAIN;
        /**
         * Also notice that we set hosted_fields to true.
         */
        $data->hosted_fields = true;

        if (is_int($order_id)) {
            $payPlusInvoice = new PayplusInvoice;
            $customer = $payPlusInvoice->payplus_get_client_by_order_id($order_id);
            $data->customer = new stdClass();
            // Use real billing name for customer_name (matching main gateway behavior)
            // so tokens are saved with the correct billing identity.
            // $customer['name'] may contain the invoice name override which breaks token reuse.
            $billingName = '';
            if ($this->payPlusGateway->exist_company && !empty($order->get_billing_company())) {
                $billingName = $order->get_billing_company();
            } else {
                if (!empty($order->get_billing_first_name()) || !empty($order->get_billing_last_name())) {
                    $billingName = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                }
                if (!$billingName) {
                    $billingName = $order->get_billing_company();
                } elseif ($order->get_billing_company()) {
                    $billingName .= ' (' . $order->get_billing_company() . ')';
                }
            }
            $data->customer->customer_name = !empty($billingName) ? $billingName : $customer['name'];
            $data->customer->email = $customer['email'];
            $data->customer->phone = $customer['phone'];
            $data->customer->address = $customer['street_name'];
            $data->customer->city = $customer['city'];
            $data->customer->postal_code = $customer['postal_code'];
            $data->customer->country_iso = $customer['country_iso'];
            $data->customer->customer_external_number = $order->get_customer_id();
            
            $customer_invoice_name = WC_PayPlus_Meta_Data::get_meta($order_id, '_billing_customer_invoice_name');
            if (!empty($customer_invoice_name)) {
                $data->customer->customer_name_invoice = $customer_invoice_name;
            }

            // Pass vat_number matching main gateway logic so tokens are saved with consistent identity
            $customer_other_id = WC_PayPlus_Meta_Data::get_meta($order_id, '_billing_customer_other_id');
            if (!empty($customer_other_id)) {
                $data->customer->vat_number = $customer_other_id;
            } elseif ($this->payPlusGateway->vat_number_field && $order->get_meta($this->payPlusGateway->vat_number_field)) {
                $data->customer->vat_number = $order->get_meta($this->payPlusGateway->vat_number_field);
            }

            $payingVat = isset($this->payPlusGateway->settings['paying_vat']) && in_array($this->payPlusGateway->settings['paying_vat'], [0, 1, 2]) ? $this->payPlusGateway->settings['paying_vat'] : false;
            if ($payingVat) {
                $payingVat = $payingVat === "0" ? true : false;
                $payingVat = $payingVat === "1" ? false : true;
                $payingVat = $payingVat === "2" ? ($customer['country_iso'] !== trim(strtolower($this->payPlusGateway->settings['paying_vat_iso_code'])) ? false : true) : $payingVat;
                $data->paying_vat = $payingVat;
            }
        } else {
            $data->customer = new stdClass();
            $data->customer->customer_name = "$billing_first_name $billing_last_name";
            $data->customer->email = $billing_email;
            $data->customer->phone = $phone;
        }

        foreach ($products as $product) {
            $item = new stdClass();
            $item->name = $product['title'];
            $item->quantity = $product['quantity'];
            $item->barcode = $product['barcode'];
            $item->price = $product['priceProductWithTax'];
            isset($product['vat_type']) ? $item->vat_type = $product['vat_type'] : $payingVat;
            $data->items[] = $item;
        }

        $randomHash = WC()->session->get('randomHash') ? WC()->session->get('randomHash') : bin2hex(random_bytes(16));
        WC()->session->set('randomHash', $randomHash);

        $order_id = $order_id === "000" ? $this->updateOrderId($randomHash) : $order_id;
        $data->more_info = $order_id;

        // if ($order_id !== "000" && isset($order) && $order) {
        //     WC()->session->set('order_awaiting_payment', $order_id);
        // }

        $totalAmount = 0;
        foreach ($data->items as $item) {
            $totalAmount += $item->price * $item->quantity;
        }

        $hostedResponse = WC()->session->get('hostedPayload');
        $hostedResponseArray = !empty($hostedResponse) ? json_decode($hostedResponse, true) : '{}';

        $data->amount = number_format($totalAmount, 2, '.', '');
        $firstMessage = $order_id === "000" ? "-=#* 1st field generated *%=- - " : "";

        $payload = wp_json_encode($data, JSON_UNESCAPED_UNICODE);
        if (is_int($data->more_info) && $data->more_info === $order_id) {
            WC_PayPlus_Meta_Data::update_meta($order, ['payplus_hosted_page_request_uid' => $hostedResponseArray['payment_page_uid'], 'payplus_payload' => $payload]);
            WC_PayPlus_Meta_Data::append_pruid_history($order, $hostedResponseArray['payment_page_uid'], 'hosted_fields');
        }

        // $this->payPlusGateway->payplus_add_log_all("hosted-fields-data", "HostedFields-hostedFieldsData-Class Payload: \n$payload");

        WC()->session->set('hostedPayload', $payload);

        $order = wc_get_order($order_id);
        $hostedFieldsUUID = WC()->session->get('hostedFieldsUUID');

        if (!$this->isPlaceOrder) {
            if (self::hosted_charge_lock_order_id()) {
                $this->payPlusGateway->payplus_add_log_all(
                    'hosted-fields-data',
                    'Create skipped — charge already locked for order ' . self::hosted_charge_lock_order_id()
                );
                $existing = WC()->session->get('hostedResponse');
                return !empty($existing) ? $existing : $hostedResponse;
            }
            $this->payPlusGateway->payplus_add_log_all("hosted-fields-data", "Create for new Order: ($order_id) - \n$payload\nhostedFieldsUUID: $hostedFieldsUUID");
            $hostedResponse = WC_PayPlus_Statics::createUpdateHostedPaymentPageLink($payload, false);
        }

        $hostedResponseArray = json_decode($hostedResponse, true);

        if (isset($hostedResponseArray['results']['status']) && $hostedResponseArray['results']['status'] === "error") {
            WC()->session->set('page_request_uid', false);
            $hostedResponse = WC_PayPlus_Statics::createUpdateHostedPaymentPageLink($payload, $this->isPlaceOrder);
        }

        return $hostedResponse;
    }

    public function checkHostedTime()
    {
        $savedTimestamp = WC()->session->get('hostedTimeStamp');
        if (self::hosted_charge_lock_order_id()) {
            return true;
        }

        if (!$savedTimestamp) {
            WC()->session->__unset('hostedPayload');
            WC()->session->set('page_request_uid', false);
            WC()->session->set('hostedResponse', false);
            $randomHash = bin2hex(random_bytes(16));
            WC()->session->set('order_awaiting_payment', $randomHash);
            WC()->session->__unset('hostedFieldsUUID');
            WC()->session->set('hostedStarted', false);
            WC()->session->set('randomHash', $randomHash);
            // First run or if no timestamp is saved, save the current time
            $savedTimestamp = time(); // Store this in the database or file
            WC()->session->set('hostedTimeStamp', $savedTimestamp);
            $this->payplus_gateway->payplus_add_log_all("hosted-fields-data", "HostedFields timestamp started: $savedTimestamp");
        }

        $currentTimestamp = time();

        $timeLimit = 30 * 60; // 30 minutes

        if (($currentTimestamp - $savedTimestamp) <= $timeLimit) {
            return true;
        } elseif (self::hosted_charge_lock_order_id()) {
            return true;
        } else {
            $this->payplus_gateway->payplus_add_log_all("hosted-fields-data", "HostedFields timestamp ended: $currentTimestamp");
            WC()->session->set('hostedTimeStamp', false);
            WC()->session->__unset('hostedPayload');
            WC()->session->set('page_request_uid', false);
            WC()->session->set('hostedResponse', false);
            $randomHash = bin2hex(random_bytes(16));
            WC()->session->set('order_awaiting_payment', $randomHash);
            WC()->session->__unset('hostedFieldsUUID');
            WC()->session->set('hostedStarted', false);
            WC()->session->set('randomHash', $randomHash);
            return false;
        }
    }
}

// Backward compatibility: WC_PayPlus_Embedded used to be a thin subclass of
// WC_PayPlus_HostedFields that only registered woocommerce_checkout_order_processed
// and re-implemented hostedFieldsData for the strict Update path. Both responsibilities
// now live on WC_PayPlus_HostedFields directly (register_hooks() + update_hosted_page_for_order()).
// The alias keeps any lingering `new WC_PayPlus_Embedded()` / `instanceof WC_PayPlus_Embedded`
// references working — the old subclass never added any state of its own.
if (!class_exists('WC_PayPlus_Embedded', false)) {
    class_alias('WC_PayPlus_HostedFields', 'WC_PayPlus_Embedded');
}
