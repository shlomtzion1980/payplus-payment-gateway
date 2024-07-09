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
     * @return bool
     */
    public static function invoicePlusDocsSelect($order_id, $options = [])
    {
        $refundsJson = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_refunds', true);
        $refundsArray = !empty($refundsJson) ? json_decode($refundsJson, true) : $refundsJson;
        $errorInvoice = WC_PayPlus_Meta_Data::get_meta($order_id, "payplus_error_invoice", true);

        $invDoc = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_originalDocAddress', true);
        $invDocType = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_type', true);
        $invDocNumber = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_numberD', true);
        $chargeText = __('Charge', 'payplus-payment-gateway');
        $refundsText = __('Refunds', 'payplus-payment-gateway');

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
            default:
                $docType = __('Invoice', 'payplus-payment-gateway');
        }

?>
        <div class="invoicePlusButtonContainer">
            <?php
            if (strlen($invDoc) > 0 && !is_array($refundsArray)) {
            ?>
                <a class="invoicePlusButton" style="text-decoration: none;" target="_blank" href="<?php echo esc_url($invDoc); ?>">
                    <?php echo esc_html($docType); ?> (<?php echo esc_html($invDocNumber); ?>)
                </a>
            <?php
            } elseif (strlen($invDoc) > 0 && is_array($refundsArray)) { ?>
                <button class="toggle-button invoicePlusButtonShow"></button>
                <div class="hidden-buttons invoicePlusButtonHidden">

                    <?php if (isset($options['no-headlines']) && $options['no-headlines'] !== true) { ?><h4>
                            <?php echo esc_html($chargeText); ?></h4><?php } ?>
                    <a class="invoicePlusButton" style="text-decoration: none;" target="_blank" href="<?php echo esc_url($invDoc); ?>"><?php echo esc_html($docType); ?> (<?php echo esc_html($invDocNumber); ?>)</a>

                    <?php if (isset($options['no-headlines']) && $options['no-headlines'] !== true) { ?><h4>
                            <?php echo esc_html($refundsText); ?></h4><?php } ?>
                    <?php
                    if (is_array($refundsArray)) {
                        foreach ($refundsArray as $docNumber => $doc) {
                            $docLink = $doc['link'];
                            $docText = __($doc['type'], 'payplus-payment-gateway');
                    ?><a class="invoicePlusButton" style="text-decoration: none;" target="_blank" href="<?php echo esc_url($docLink); ?>"><?php echo esc_html("$docText ($docNumber)"); ?></a>
                    <?php
                        }
                    }
                    ?>
                </div>
        </div>
    <?php
            } elseif ($errorInvoice) { ?>
        <p class='link-invoice-error'>
            <?php echo esc_html($errorInvoice); ?>
        </p><?php
            }
        }

        public static function getId()
        {
            $order_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['post']) ? intval($_GET['post']) : null);
            return intval($order_id);
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
            $boxType = $metaBox['args']['metaBoxType'];
            $order_id = property_exists($post, 'id') === true ? $post->get_id() : WC_PayPlus_Statics::getId();
            if (!empty($order_id)) {
                if ($boxType === 'payplusInvoice') {
                    $refundsJson = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_refunds', true);
                    $refundsArray = !empty($refundsJson) ? json_decode($refundsJson, true) : $refundsJson;
                    $invDoc = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_originalDocAddress', true);
                    $invDocType = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_type', true);
                    $invDocNumber = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_invoice_numberD', true);
                    $chargeText = __('Charge', 'payplus-payment-gateway');
                    $refundsText = __('Refunds', 'payplus-payment-gateway');

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
                        default:
                            $docType = __('Invoice', 'payplus-payment-gateway');
                    }
                    if (strlen($invDoc) > 0) { ?>
                <div>
                    <h4><?php echo esc_html($chargeText); ?></h4>
                    <a class="link-invoice" style="text-decoration: none;" target="_blank" href="<?php echo esc_url($invDoc); ?>"><?php echo esc_html($docType); ?> (<?php echo esc_html($invDocNumber); ?>)</a>
                </div>
            <?php
                    }
                    if (is_array($refundsArray)) {
            ?>
                <div>
                    <h4><?php echo esc_html($refundsText); ?></h4>
                    <?php
                        foreach ($refundsArray as $docNumber => $doc) {
                            $docLink = $doc['link'];
                            $docText = __($doc['type'], 'payplus-payment-gateway');
                    ?>
                        <a class="link-invoice" style="text-decoration: none;" target="_blank" href="<?php echo esc_url($docLink); ?>"><?php echo esc_html("$docText ($docNumber)"); ?></a>
                    <?php
                        }
                    ?>
                </div>
<?php
                    }
                }
                if ($boxType === 'payplus') {
                    $responsePayPlus = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_response', true);
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
                                $amount = $responseArray['amount'] ?? $responseArray['data']['transaction']['amount'] ?? null;
                                $method = $responseArray['method'] ?? $responseArray['data']['transaction']['alternative_method_name'] ?? null;
                                $type = $payPlusType ? $payPlusType : $responseArray['type'] ?? $responseArray['data']['transaction']['type'] ?? null;
                                $number = $responseArray['number'] ?? $responseArray['data']['transaction']['number'] ?? null;
                                $fourDigits = $responseArray['four_digits'] ?? $responseArray['data']['data']['card_information']['four_digits'] ?? null;
                                $expMonth = $responseArray['expiry_month'] ?? $responseArray['data']['data']['card_information']['expiry_month'] ?? null;
                                $expYear = $responseArray['expiry_year'] ?? $responseArray['data']['data']['card_information']['expiry_year'] ?? null;
                                $numOfPayments = $responseArray['number_of_payments'] ?? $responseArray['data']['transaction']['payments']['number_of_payments'] ?? null;
                                $voucherNum = $responseArray['voucher_num'] ?? $responseArray['data']['transaction']['voucher_number'] ?? null;
                                $voucherId = $responseArray['voucher_id'] ?? $responseArray['data']['transaction']['voucher_number'] ?? null;
                                $tokeUid = $responseArray['token_uid'] ?? $responseArray['data']['data']['card_information']['token_number'] ?? null;
                                $j5Charge = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_charged_j5_amount') ?? null;
                                echo wp_kses_post(WC_PayPlus_Statics::createPayPlusDataBox($amount, $method, $type, $number, $fourDigits, $expMonth, $expYear, $numOfPayments, $voucherNum, $voucherId, $tokeUid, $j5Charge));
                            } else {
                                foreach ($responseArray['related_transactions'] as $transaction) {
                                    $amount = $transaction['amount'];
                                    $method = $transaction['method'];
                                    $type = $transaction['type'];
                                    $number = $transaction['number'];
                                    $fourDigits = $transaction['four_digits'];
                                    $expMonth = $transaction['expiry_month'];
                                    $expYear = $transaction['expiry_year'];
                                    $numOfPayments = $transaction['number_of_payments'] ?? null;
                                    $voucherNum = $transaction['voucher_num'] ?? null;
                                    $voucherId = $transaction['voucher_id'] ?? null;
                                    $tokeUid = $transaction['token_uid'];
                                    $j5Charge = null;
                                    echo wp_kses_post(WC_PayPlus_Statics::createPayPlusDataBox($amount, $method, $type, $number, $fourDigits, $expMonth, $expYear, $numOfPayments, $voucherNum, $voucherId, $tokeUid, $j5Charge));
                                    echo '<br><span style="border: 1px solid #000;display: block;width: 100%;"></span></br>';
                                }
                                echo esc_html(__('Total of payments: ', 'payplus-payment-gateway')) . esc_html($totalAmount);
                            }
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
        public static function createPayPlusDataBox($amount, $method, $type, $number, $fourDigits, $expMonth, $expYear, $numOfPayments, $voucherNum, $voucherId, $tokeUid, $j5Charge)
        {
            $expMonthYear = "$expMonth/$expYear";
            $box = sprintf(
                __(
                    '
                    <div style="font-weight:600;">PayPlus ' . (($type == "Approval" || $type == "Check") ? 'Pre-Authorization' : 'Payment') . ' Successful</div>
                        <table style="border-collapse:collapse">
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Transaction#', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Method', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Type', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Last Digits', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Expiry Date', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Payments', 'payplus-payment-gateway') . '</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Voucher #', 'payplus-payment-gateway') . '</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">' . __('Voucher ID', 'payplus-payment-gateway') . '</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="vertical-align:top;">' . __('Token', 'payplus-payment-gateway') . '</td><td style="vertical-align:top;">%s</td></tr>
                            <tr><td style="vertical-align:top;">' . __('Total:', 'payplus-payment-gateway') . '</td><td style="vertical-align:top;">%s</td></tr>
                        </table>
                    ',
                    'payplus-payment-gateway'
                ),
                $number,
                $method,
                $type,
                $fourDigits,
                $expMonthYear,
                $numOfPayments,
                $voucherNum,
                $voucherId,
                $tokeUid,
                $j5Charge ? $j5Charge : $amount
            );
            return $box;
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
            $secretKey = $testMode === true ? $options['dev_api_secret'] : $options['secret_key'];

            $args = array(
                'body' => $payload,
                'timeout' => '60',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                    'domain' => home_url(),
                    'User-Agent' => 'WordPress ' . $_SERVER['HTTP_USER_AGENT'],
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
    }
