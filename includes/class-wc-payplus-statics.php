<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles and process WC PayPlus Orders Data.
 *
 */
class WC_PayPlus_Statics
{

    /**
     * @return string
     */
    public static function translateInvoiceType($invDocType)
    {
        switch ($invDocType) {
            case 'inv_tax':
                $docType = __('Tax Invoice', 'payplus-payment-gateway');
                break;
            case 'inv_tax_receipt':
                $docType = __('Tax Invoice Receipt ', 'payplus-payment-gateway');
                break;
            case 'inv_receipt':
                $docType = __('Receipt', 'payplus-payment-gateway');
                break;
            case 'inv_don_receipt':
                $docType = __('Donation Reciept', 'payplus-payment-gateway');
                break;
            case 'Refund Invoice':
                $docType = __('Refund Invoice', 'payplus-payment-gateway');
                break;
            case 'Refund Receipt':
                $docType = __('Refund Receipt', 'payplus-payment-gateway');
                break;
            default:
                $docType = __('Invoice', 'payplus-payment-gateway');
        }
        return $docType;
    }

    public static function pp_is_json($string)
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    public static function returnInvDocs($invoicesArray)
    {
        if (is_array($invoicesArray)) {
            foreach ($invoicesArray as $invDocType => $doc) {
                $invDcoNum = array_key_first($doc);
                $invDoc = $doc[$invDcoNum];
                $docType = WC_PayPlus_Statics::translateInvoiceType($invDocType); ?>
                <a class="invoicePlusButton" style="text-decoration: none;" target="_blank"
                    href="<?php echo esc_url($invDoc); ?>"><?php echo esc_html($docType); ?>
                    (<?php echo esc_html($invDcoNum); ?>)</a>
        <?php
            }
        }
    }

    /**
     * @return string HTML content as a string.
     */
    public static function invoicePlusDocsSelect($order_id, $options = [])
    {
        $refundsJson = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_refunds', true);
        $invoicesJson = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_plus_docs', true);
        $refundsArray = !empty($refundsJson) ? json_decode($refundsJson, true) : $refundsJson;
        $invoicesArray = !empty($invoicesJson) ? json_decode($invoicesJson, true) : $invoicesJson;
        $errorInvoice = WC_PayPlus_Meta_Data::get_meta($order_id, "payplus_error_invoice", true);

        $invDoc = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_originalDocAddress', true);
        $invDocType = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_type', true);
        $invDocNumber = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_numberD', true);
        $chargeText = __('Charge', 'payplus-payment-gateway');
        $refundsText = __('Refunds', 'payplus-payment-gateway');
        $docType = WC_PayPlus_Statics::translateInvoiceType($invDocType);


        ?>
        <div class="invoicePlusButtonContainer">
            <?php
            if (strlen($invDoc) > 0 && !is_array($refundsArray)) {
                if (!is_array($invoicesArray) || count($invoicesArray) === 1) {
            ?>
                    <a class="invoicePlusButton" style="text-decoration: none;" target="_blank" href="<?php echo esc_url($invDoc); ?>">
                        <?php echo esc_html($docType); ?> (<?php echo esc_html($invDocNumber); ?>)
                    </a>
                <?php
                } else {
                ?>
                    <button class="toggle-button invoicePlusButtonShow"></button>
                    <div class="hidden-buttons invoicePlusButtonHidden">
                        <?php
                        WC_PayPlus_Statics::returnInvDocs($invoicesArray); ?>
                    </div>
                <?php
                }
            } elseif (strlen($invDoc) > 0 && is_array($refundsArray)) { ?>
                <button class="toggle-button invoicePlusButtonShow"></button>
                <div class="hidden-buttons invoicePlusButtonHidden">

                    <?php if (!is_array($invoicesArray)) {
                        if (isset($options['no-headlines']) && $options['no-headlines'] !== true) { ?><h4>
                                <?php echo esc_html($chargeText); ?></h4><?php } ?>
                        <a class="invoicePlusButton" style="text-decoration: none;" target="_blank"
                            href="<?php echo esc_url($invDoc); ?>"><?php echo esc_html($docType); ?>
                            (<?php echo esc_html($invDocNumber); ?>)</a>
                    <?php
                    } else {
                        WC_PayPlus_Statics::returnInvDocs($invoicesArray);
                    }
                    if (isset($options['no-headlines']) && $options['no-headlines'] !== true) { ?><h4>
                            <?php echo esc_html($refundsText); ?></h4><?php } ?>
                    <?php
                    if (is_array($refundsArray)) {
                        foreach ($refundsArray as $docNumber => $doc) {
                            $docLink = $doc['link'];
                            $docText = WC_PayPlus_Statics::translateInvoiceType($doc['type']);
                    ?><a class="invoicePlusButton" style="text-decoration: none;" target="_blank"
                                href="<?php echo esc_url($docLink); ?>"><?php echo esc_html("$docText ($docNumber)"); ?></a>
                    <?php
                        }
                    }
                    ?>
                </div>
        </div>
        <?php   } elseif (is_array($refundsArray)) {
                if (count($refundsArray) > 1) { ?>
            <button class="toggle-button invoicePlusButtonShow"></button>
            <div class="hidden-buttons invoicePlusButtonHidden">
            <?php
                }
                foreach ($refundsArray as $docNumber => $doc) {
                    $docLink = $doc['link'];
                    $docText = WC_PayPlus_Statics::translateInvoiceType($doc['type']);
            ?><a class="invoicePlusButton" style="text-decoration: none;" target="_blank"
                    href="<?php echo esc_url($docLink); ?>"><?php echo esc_html("$docText ($docNumber)"); ?></a>
            <?php
                }
                if (count($refundsArray) > 1) { ?>
            </div>
        <?php
                }
            } elseif ($errorInvoice) { ?>
        <p class='link-invoice-error'>
            <?php echo esc_html($errorInvoice); ?>
        </p><?php
            }
        }

        public static function getId($nonce)
        {
            if (!wp_verify_nonce($nonce, 'getIdNonce')) {
                return null;
            }
            $order_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['post']) ? intval($_GET['post']) : null);
            return intval($order_id);
        }

        public static function addInlineScript($inLineScript)
        {
            $script = 'document.addEventListener("DOMContentLoaded", function() { 
                ' . $inLineScript . ' 
            });';
            wp_add_inline_script('jquery', $script);
        }

        /**
         *
         * Choose which metabox we are using and call || display the correct function.
         *
         * @param object $post
         * @param string $metabox
         * 
         * @return string
         */
        public static function payPlusOrderMetaBox($post, $metaBox)
        {
            $nonce = wp_create_nonce('getIdNonce');
            $boxType = $metaBox['args']['metaBoxType'];
            $order_id = property_exists($post, 'id') === true ? $post->get_id() : WC_PayPlus_Statics::getId($nonce);
            if (!empty($order_id)) {
                if ($boxType === 'payplusInvoice') {
                    $refundsJson = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_refunds', true);
                    $refundsArray = !empty($refundsJson) ? json_decode($refundsJson, true) : $refundsJson;
                    $invDoc = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_originalDocAddress', true);
                    $invDocType = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_type', true);
                    $invDocNumber = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_numberD', true);
                    $payPlusInvoiceDocs = json_decode(WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_plus_docs', true), true);

                    $chargeText = __('Charge', 'payplus-payment-gateway');
                    $refundsText = __('Refunds', 'payplus-payment-gateway');

                    if (is_array($payPlusInvoiceDocs)) { ?>
                <div>
                    <h4><?php echo esc_html($chargeText); ?></h4>
                    <?php
                        foreach ($payPlusInvoiceDocs as $invDocType => $inv) {
                            $docType = WC_PayPlus_Statics::translateInvoiceType($invDocType);
                            $invDocNumber = array_key_first($inv);
                            $invDocUrl = reset($inv);
                            if (strlen($invDocUrl) > 0) { ?>

                            <a class="link-invoice" style="text-decoration: none;" target="_blank"
                                href="<?php echo esc_url($invDocUrl); ?>"><?php echo esc_html($docType); ?>
                                (<?php echo esc_html($invDocNumber); ?>)</a>

                    <?php
                            }
                        }
                    ?>
                </div>
                <?php
                    } else {
                        if (strlen($invDoc) > 0) {
                            $docType = WC_PayPlus_Statics::translateInvoiceType($invDocType);
                ?>
                    <div>
                        <h4><?php echo esc_html($chargeText); ?></h4>
                        <a class="link-invoice" style="text-decoration: none;" target="_blank"
                            href="<?php echo esc_url($invDoc); ?>"><?php echo esc_html($docType); ?>
                            (<?php echo esc_html($invDocNumber); ?>)</a>
                    </div>
                <?php
                        }
                    }

                    if (is_array($refundsArray)) {
                ?>
                <div>
                    <h4><?php echo esc_html($refundsText); ?></h4>
                    <?php
                        foreach ($refundsArray as $docNumber => $doc) {
                            $docLink = $doc['link'];
                            $docText = WC_PayPlus_Statics::translateInvoiceType($doc['type']);
                    ?>
                        <a class="link-invoice" style="text-decoration: none;" target="_blank"
                            href="<?php echo esc_url($docLink); ?>"><?php echo esc_html("$docText ($docNumber)"); ?></a>
                    <?php
                        }
                    ?>
                </div>
<?php
                    }
                }
                if ($boxType === 'payplus') {
                    $responsePayPlus = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_response', true);
                    $manualOrderPayments = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_order_payments', true) ? json_decode(WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_order_payments', true), true) : false;
                    $responseArray = json_decode($responsePayPlus, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $error_message = json_last_error_msg();
                        $fixedJson = WC_PayPlus_Statics::fixMalformedJson($responsePayPlus);
                        $payPlusFixedJson = ['payplus_response' => $fixedJson];
                        $order = wc_get_order($order_id);
                        WC_PayPlus_Meta_Data::update_meta($order, $payPlusFixedJson);
                        $responseArray = json_decode($fixedJson, true);
                    }
                    if (isset($responseArray) && is_array($responseArray)) {
                        $payPlusType = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_type', true);
                        $totalAmount = $responseArray['amount'] ?? $responseArray['data']['transaction']['amount'];
                        if (!is_null($totalAmount)) {
                            if (!isset($responseArray['related_transactions'])) {
                                $emvMethod = isset($responseArray['data']['data']['card_information']) ? "credit-card" : null;
                                $amount = $responseArray['amount'] ?? $responseArray['data']['transaction']['amount'] ?? null;
                                $method = $responseArray['method'] ?? $responseArray['data']['transaction']['alternative_method_name'] ?? $emvMethod;
                                $brand = $responseArray['brand_name'] ?? $responseArray['data']['data']['card_information']['brand_id'] ?? null;
                                $issuer = $responseArray['issuer_name'] ?? $responseArray['data']['data']['card_information']['clearing_id'] ?? null;
                                $type = $payPlusType ? $payPlusType : $responseArray['type'] ?? $responseArray['data']['transaction']['type'] ?? null;
                                $date = $responseArray['date'] ?? $responseArray['data']['transaction']['date'] ?? null;
                                $number = $responseArray['number'] ?? $responseArray['data']['transaction']['number'] ?? null;
                                $fourDigits = $responseArray['four_digits'] ?? $responseArray['data']['data']['card_information']['four_digits'] ?? null;
                                $expMonth = $responseArray['expiry_month'] ?? $responseArray['data']['data']['card_information']['expiry_month'] ?? null;
                                $expYear = $responseArray['expiry_year'] ?? $responseArray['data']['data']['card_information']['expiry_year'] ?? null;
                                $numOfPayments = $responseArray['number_of_payments'] ?? $responseArray['data']['transaction']['payments']['number_of_payments'] ?? null;
                                $voucherNum = $responseArray['voucher_num'] ?? $responseArray['data']['transaction']['voucher_number'] ?? null;
                                $voucherId = $responseArray['voucher_id'] ?? $responseArray['data']['transaction']['voucher_number'] ?? null;
                                $statusCode = $responseArray['status_code'] ?? $responseArray['data']['transaction']['status_code'] ?? null;
                                $status = $responseArray['status'] ?? $responseArray['data']['transaction']['status'] ?? null;
                                $status = $status === "rejected" ? "<span style='color: red;'>Rejected</span>" : $status;
                                $status = $status === "approved" ? "Approved" : $status;
                                $status = isset($responseArray['data']['transaction']['approval_number']) ? "Approved" : $status;
                                $tokeUid = $responseArray['token_uid'] ?? $responseArray['data']['data']['card_information']['token_number'] ?? null;
                                $j5Charge = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_charged_j5_amount') ?? null;
                                echo wp_kses_post(WC_PayPlus_Statics::createPayPlusDataBox($statusCode, $status, $amount, $method, $brand, $issuer, $type, $number, $fourDigits, $expMonth, $expYear, $numOfPayments, $voucherNum, $voucherId, $tokeUid, $j5Charge, $date));
                            } else {
                                foreach ($responseArray['related_transactions'] as $transaction) {
                                    $amount = $transaction['amount'];
                                    $method = $transaction['method'];
                                    $brand = $transaction['brand_name'];
                                    $issuer = $transaction['issuer_name'] ?? $transaction['clearing_name'];
                                    $type = $transaction['type'];
                                    $number = $transaction['number'];
                                    $date = $transaction['date'];
                                    $fourDigits = $transaction['four_digits'];
                                    $expMonth = $transaction['expiry_month'];
                                    $expYear = $transaction['expiry_year'];
                                    $numOfPayments = $transaction['number_of_payments'] ?? null;
                                    $voucherNum = $transaction['voucher_num'] ?? null;
                                    $voucherId = $transaction['voucher_id'] ?? null;
                                    $tokeUid = $transaction['token_uid'];
                                    $j5Charge = null;
                                    $statusCode = $transaction['status_code'] ?? null;
                                    $status = $transaction['status'] ?? null;
                                    $status = $status === "rejected" ? "<span style='color: red;'>Rejected</span>" : $status;
                                    $status = $status === "approved" ? "Approved" : $status;
                                    echo wp_kses_post(WC_PayPlus_Statics::createPayPlusDataBox($statusCode, $status, $amount, $method, $brand, $issuer, $type, $number, $fourDigits, $expMonth, $expYear, $numOfPayments, $voucherNum, $voucherId, $tokeUid, $j5Charge, $date));
                                    echo '<br><span style="border: 1px solid #000;display: block;width: 100%;"></span></br>';
                                }
                                echo esc_html(__('Total of payments: ', 'payplus-payment-gateway')) . esc_html($totalAmount);
                            }
                        }
                    }
                    if ($manualOrderPayments && is_array($manualOrderPayments)) {
                        foreach ($manualOrderPayments as $manualPayment) {
                            $amount = $manualPayment['price'] ?? null;
                            $method = $manualPayment['method_payment'] ?? null;
                            $brand = $manualPayment['brand_name'] ?? null;
                            $issuer = $manualPayment['issuer_name'] ?? null;
                            $type = $manualPayment['type'] ?? null;
                            $number = $manualPayment['order_id'] ?? null;
                            $fourDigits = $manualPayment['create_at'] ?? null;
                            $expMonth = $manualPayment['expiry_month'] ?? null;
                            $expYear = $manualPayment['expiry_year'] ?? null;
                            $numOfPayments = $manualPayment['number_of_payments'] ?? null;
                            $voucherNum = $manualPayment['order_id'] ?? null;
                            $voucherId = $manualPayment['voucher_id'] ?? null;
                            $tokeUid = $manualPayment['token_uid'] ?? null;
                            $j5Charge = null;
                            $status = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_numberD') ? "Successful" : null;
                            $statusCode = $manualPayment['status_code'] ?? null;
                            echo wp_kses_post(WC_PayPlus_Statics::createPayPlusDataBox($statusCode, $status, $amount, $method, $brand, $issuer, $type, $number, $fourDigits, $expMonth, $expYear, $numOfPayments, $voucherNum, $voucherId, $tokeUid, $j5Charge, "", true));
                            echo '<br><span style="border: 1px solid #000;display: block;width: 100%;"></span></br>';
                        }
                    }
                }
            }
        }

        /**
         *
         * Fix malformed json that contains " (Double Geresh) in the string data.
         *
         * @param string $json
         * 
         * @return string
         */
        public static function fixMalformedJson($json)
        {
            $replacedJson = str_replace('{"', '{#', $json);
            $replacedJson = str_replace('"}', '#}', $replacedJson);
            $replacedJson = str_replace('":"', '#:#', $replacedJson);
            $replacedJson = str_replace('","', '#,#', $replacedJson);
            $replacedJson = str_replace('":', '#:', $replacedJson);
            $replacedJson = str_replace(',"', ',#', $replacedJson);
            $replacedJson = str_replace('"', 'U+2033', $replacedJson);
            $replacedJson = str_replace('#', '"', $replacedJson);
            return $replacedJson;
        }

        /**
         *
         * Create metabox data of PayPlus charges || authorizations and display it.
         *
         * @param float $amount
         * @param string $type
         * @param int $number
         * @param int $fourDigits
         * @param int $expMonth
         * @param int $expYear
         * @param int $numOfPayments
         * @param string $voucherNum
         * @param string $voucherId
         * @param string $tokeUid
         * @param int $j5Charge
         * 
         * @return string
         */
        public static function createPayPlusDataBox($statusCode, $status, $amount, $method, $brand, $issuer, $type, $number, $fourDigits, $expMonth, $expYear, $numOfPayments, $voucherNum, $voucherId, $tokeUid, $j5Charge, $date, $paymentType = false)
        {
            $type_text = ($type == "Approval" || $type == "Check") ? __('Pre-Authorization', 'payplus-payment-gateway') : __('Payment', 'payplus-payment-gateway');
            $type_text = $paymentType ? __('Manual Payment', 'payplus-payment-gateway') : $type_text;
            $expMonthYear = "$expMonth/$expYear";
            $box = sprintf(
                '<div style="font-weight:600;">PayPlus %s %s</div>
                        <table class="payPlusMetaBox" style="border-collapse:collapse">
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Transaction#', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('DateTime', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Method', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Brand', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Issuer', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Type', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Last Digits', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Expiry Date', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Payments', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Voucher #', 'payplus-payment-gateway') . '</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Voucher ID', 'payplus-payment-gateway') . '</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="vertical-align:top;">' . __('Token', 'payplus-payment-gateway') . '</td><td style="vertical-align:top;">%s</td></tr>
                            <tr><td style="vertical-align:top;">' . __('Total:', 'payplus-payment-gateway') . '</td><td style="vertical-align:top;">%s</td></tr>
                            <tr><td style="vertical-align:top;">' . __('Status:', 'payplus-payment-gateway') . '</td><td style="vertical-align:top;">%s</td></tr>
                        </table>
                    ',
                $type_text,
                $status,
                $number,
                $date,
                $method,
                $brand,
                $issuer,
                $type,
                $fourDigits,
                $expMonthYear,
                $numOfPayments,
                $voucherNum,
                $voucherId,
                $tokeUid,
                $j5Charge ? $j5Charge : $amount,
                $status,
            );
            return $box;
        }

        public static function sanitize_postal_code($postal_code)
        {
            // Allow only alphanumeric characters and spaces (common in postal codes)
            return preg_replace('/[^A-Za-z0-9 ]/', '', sanitize_text_field($postal_code));
        }

        public static function sanitize_object($object)
        {
            if (is_object($object)) {
                foreach ($object as $property => $value) {
                    if ($property === 'postal_code') {
                        $object->$property = WC_PayPlus_Statics::sanitize_postal_code($value);
                    } elseif (is_string($value)) {
                        $object->$property = sanitize_text_field($value);
                    } elseif (is_array($value)) {
                        $object->$property = array_map(function ($item) {
                            return is_object($item) ? WC_PayPlus_Statics::sanitize_object($item) : sanitize_text_field($item);
                        }, wp_unslash($value));
                    } elseif (is_object($value)) {
                        $object->$property = WC_PayPlus_Statics::sanitize_object($value);
                    }
                }
            }
            return $object;
        }


        public static function sanitize_recursive($value)
        {
            if (is_array($value)) {
                return array_map([self::class, 'sanitize_recursive'], $value);
            } else {
                return sanitize_text_field($value);
            }
        }

        /**
         * Check if a variable (or array element) is set and equals a specific value.
         *
         * @param mixed $var The variable or array to check.
         * @param mixed $val The value to compare against.
         * @param string|false $string Optional key to access a specific array element.
         * @return bool True if the condition is met, false otherwise.
         */
        public static function ppIsSetAnd($var, $val, $string = false)
        {
            $var = is_string($string) ? $var[$string] : $var;
            return isset($var) && $var === $val;
        }


        /**
         * @param $url
         * @param $payload
         * @param $method
         * @return array|WP_Error
         */
        public static function payplusPost($payload = [], $method = "post")
        {
            $options = get_option('woocommerce_payplus-payment-gateway_settings');
            $testMode = boolval($options['api_test_mode'] === 'yes');
            $url = $testMode === true ? PAYPLUS_PAYMENT_URL_DEV . 'Transactions/updateMoreInfos' : PAYPLUS_PAYMENT_URL_PRODUCTION . 'Transactions/updateMoreInfos';
            $apiKey = $testMode === true ? $options['dev_api_key'] : $options['api_key'];
            $secretKey = $testMode === true ? $options['dev_secret_key'] : $options['secret_key'];

            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : "";
            $args = array(
                'body' => $payload,
                'timeout' => '60',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                    'domain' => home_url(),
                    'User-Agent' => "WordPress $userAgent",
                    'Content-Type' => 'application/json',
                    'Authorization' => '{"api_key":"' . $apiKey . '","secret_key":"' . $secretKey . '"}',
                )
            );
            if ($method == "post") {
                $response = wp_remote_post($url, $args);
            } else {
                $response = wp_remote_get($url, $args);
            }

            return $response;
        }

        public static function getCardsLogos()
        {
            $iconsArray = [
                'visa' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "visa.png",
                'discover' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "discover.png",
                'amex' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "amex.png",
                'max' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "max.png",
                'isracard' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "isracard.png",
                'diners' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "diners.png",
                'maestro' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "maestro.png",
                'mastercard' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "mastercard.png",
                'jcb' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "jcb.png",
            ];

            $options = get_option('woocommerce_payplus-payment-gateway_settings', []);
            $iconsReturn = [];
            if (isset($options['cards'])) {
                foreach ($options['cards'] as $cards) {
                    if (array_key_exists($cards, $iconsArray)) {
                        $iconsReturn[$cards] = $iconsArray[$cards];
                    }
                }
            }
            return $iconsReturn;
        }

        public static function getMultiPassIcons()
        {
            $iconsArray = [
                'buyme' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/buyme.png",
                'rami-levy' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/rami-levy.png",
                'yenot-bitan' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/yenot-bitan.png",
                'hitech-zone' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/hitech-zone.png",
                'jd-club' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/jd-club.png",
                'club-911' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/club-911.png",
                'forever-21' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/forever-21.png",
                'dolce-vita' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/dolce-vita.png",
                'dolce-vita-moadon-shelra' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/dolce-vita-moadon-shelra.png",
                'payis-plus-moadon-mifal-hapayis' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/payis-plus-moadon-mifal-hapayis.png",
                'gold-tsafon' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/gold-tsafon.png",
                'dolce-vita-i-student' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/dolce-vita-i-student.png",
                'yerushalmi' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/yerushalmi.png",
                'dolce-vita-cal' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/dolce-vita-cal.png",
                'extra-bonus-tapuznet' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/extra-bonus-tapuznet.png",
                'bituah-yashir' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/bituah-yashir.png",
                'nofshonit' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/nofshonit.png",
                'ksharim-plus' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/ksharim-plus.png",
                'swagg' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/swagg.png",
                'raayonit' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/raayonit.png",
                'share-spa' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/share-spa.png",
                'gifta' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/gifta.png",
                'mega-lean' => PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "multipass-fading-icons/mega-lean.png",
            ];

            $options = get_option('payplus-payment-gateway-multipass', []);
            $iconsReturn = [];
            if (isset($options['clubs'])) {
                foreach ($options['clubs'] as $club) {
                    if (array_key_exists($club, $iconsArray)) {
                        $iconsReturn[$club] = $iconsArray[$club];
                    }
                }
            }
            return $iconsReturn;
        }

        /**
         * @param $url
         * @param array|string $payload
         * @param $method
         * @return array|WP_Error
         */
        public static function payPlusRemote($url, $payload, $method = "post")
        {
            is_array($payload) ? $payload = wp_json_encode($payload) : $payload;
            $options   = get_option('woocommerce_payplus-payment-gateway_settings');
            $testMode  = boolval($options['api_test_mode'] === 'yes');
            $apiKey    = $testMode === true ? $options['dev_api_key'] : $options['api_key'];
            $secretKey = $testMode === true ? $options['dev_secret_key'] : $options['secret_key'];
            isset($options['enable_dev_mode']) && $options['enable_dev_mode'] === "yes"
                ? $payload = apply_filters('payplus_remote_payload', $payload) : null;
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : "";

            $args = array(
                'body' => $payload,
                'timeout' => '60',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                    'domain' => home_url(),
                    'User-Agent' => "WordPress $userAgent",
                    'Content-Type' => 'application/json',
                    'Authorization' => '{"api_key":"' . $apiKey . '","secret_key":"' . $secretKey . '"}',
                    'X-creationsource' => 'WordPress Source',
                    'X-versionpayplus' => PAYPLUS_VERSION,
                )
            );

            if ($method == "post") {
                $response = wp_remote_post($url, $args);
            } else {
                $response = wp_remote_get($url, $args);
            }

            return $response;
        }

        public static function createUpdateHostedPaymentPageLink($payload, $isPlaceOrder = false)
        {
            $options = get_option('woocommerce_payplus-payment-gateway_settings');
            $testMode = boolval($options['api_test_mode'] === 'yes');
            $apiUrl = $testMode ? 'https://restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink' : 'https://restapi.payplus.co.il/api/v1.0/PaymentPages/generateLink';

            $pageRequestUid = WC()->session->get('page_request_uid');
            $hostedFieldsUUID = WC()->session->get('hostedFieldsUUID');

            if ($pageRequestUid && $hostedFieldsUUID && $isPlaceOrder) {
                $apiUrl = str_replace("/generateLink", "/Update/$pageRequestUid", $apiUrl);
            }

            $hostedResponse = WC_PayPlus_Statics::payPlusRemote($apiUrl, $payload, "post");
            $hostedResponseArray = json_decode(wp_remote_retrieve_body($hostedResponse), true);

            if (isset($hostedResponseArray['data']['page_request_uid'])) {
                $pageRequestUid = $hostedResponseArray['data']['page_request_uid'];
                WC()->session->set('page_request_uid', $pageRequestUid);
            }

            $body = wp_remote_retrieve_body($hostedResponse);
            $bodyArray = json_decode($body, true);

            if (isset($bodyArray['data']['hosted_fields_uuid']) && $bodyArray['data']['hosted_fields_uuid'] !== null) {
                $hostedFieldsUUID = $bodyArray['data']['hosted_fields_uuid'];
                WC()->session->set('hostedFieldsUUID', $hostedFieldsUUID);
            } else {
                $bodyArray['data']['hosted_fields_uuid'] = $hostedFieldsUUID;
            }
            $hostedResponse = wp_json_encode($bodyArray);
            WC()->session->set('hostedResponse', $hostedResponse);

            return $hostedResponse;
        }
    }
