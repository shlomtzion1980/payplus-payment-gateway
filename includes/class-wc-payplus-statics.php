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
            ?><a class="invoicePlusButton" style="text-decoration: none;" target="_blank" href="<?php echo $invDoc; ?>"><?php echo $docType; ?> (<?php echo $invDocNumber; ?>)</a>
            <?php
            } elseif (strlen($invDoc) > 0 && is_array($refundsArray)) { ?>
                <button class="toggle-button invoicePlusButtonShow"></button>
                <div class="hidden-buttons invoicePlusButtonHidden">

                    <?php if (isset($options['no-headlines']) && $options['no-headlines'] !== true) { ?><h4>
                            <?php echo $chargeText; ?></h4><?php } ?>
                    <a class="invoicePlusButton" style="text-decoration: none;" target="_blank" href="<?php echo $invDoc; ?>"><?php echo $docType; ?> (<?php echo $invDocNumber; ?>)</a>

                    <?php if (isset($options['no-headlines']) && $options['no-headlines'] !== true) { ?><h4>
                            <?php echo $refundsText; ?></h4><?php } ?>
                    <?php
                    if (is_array($refundsArray)) {
                        foreach ($refundsArray as $docNumber => $doc) {
                            $docLink = $doc['link'];
                            $docText = __($doc['type'], 'payplus-payment-gateway');
                    ?><a class="invoicePlusButton" style="text-decoration: none;" target="_blank" href="<?php echo $docLink; ?>"><?php echo "$docText ($docNumber)"; ?></a>
                    <?php
                        }
                    }
                    ?>
                </div>
        </div>
    <?php
            } elseif ($errorInvoice) { ?>
        <p class='link-invoice-error'>
            <?php echo $errorInvoice; ?>
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
                    <h4><?php echo $chargeText; ?></h4>
                    <a class="link-invoice" style="text-decoration: none;" target="_blank" href="<?php echo $invDoc; ?>"><?php echo $docType; ?> (<?php echo $invDocNumber; ?>)</a>
                </div>
            <?php
                    }
                    if (is_array($refundsArray)) {
            ?>
                <div>
                    <h4><?php echo $refundsText; ?></h4>
                    <?php
                        foreach ($refundsArray as $docNumber => $doc) {
                            $docLink = $doc['link'];
                            $docText = __($doc['type'], 'payplus-payment-gateway');
                    ?>
                        <a class="link-invoice" style="text-decoration: none;" target="_blank" href="<?php echo $docLink; ?>"><?php echo "$docText ($docNumber)"; ?></a>
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
                    if (isset($responseArray)) {
                        $payPlusType = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_type', true);
                        $totalAmount = $responseArray['amount'];
                        if (!is_null($totalAmount)) {
                            if (!isset($responseArray['related_transactions'])) {
                                $amount = $responseArray['amount'];
                                $method = $responseArray['method'];
                                $type = $payPlusType ? $payPlusType : $responseArray['type'];
                                $number = $responseArray['number'];
                                $fourDigits = $responseArray['four_digits'];
                                $expMonth = $responseArray['expiry_month'];
                                $expYear = $responseArray['expiry_year'];
                                $numOfPayments = isset($responseArray['number_of_payments']) ? $responseArray['number_of_payments'] : "";
                                $voucherNum = isset($responseArray['voucher_num']) ? $responseArray['voucher_num'] : "";
                                $voucherId = isset($responseArray['voucher_id']) ? $responseArray['voucher_id'] : "";
                                $tokeUid = $responseArray['token_uid'];
                                $j5Charge = isset($responseArray['charged_j5_amount']) ? $responseArray['charged_j5_amount'] : "";
                                echo WC_PayPlus_Statics::createPayPlusDataBox($amount, $method, $type, $number, $fourDigits, $expMonth, $expYear, $numOfPayments, $voucherNum, $voucherId, $tokeUid, $j5Charge);
                            } else {
                                foreach ($responseArray['related_transactions'] as $transaction) {
                                    $amount = $transaction['amount'];
                                    $method = $transaction['method'];
                                    $type = $transaction['type'];
                                    $number = $transaction['number'];
                                    $fourDigits = $transaction['four_digits'];
                                    $expMonth = $transaction['expiry_month'];
                                    $expYear = $transaction['expiry_year'];
                                    $numOfPayments = isset($transaction['number_of_payments']) ? $transaction['number_of_payments'] : "";
                                    $voucherNum = isset($transaction['voucher_num']) ? $transaction['voucher_num'] : "";
                                    $voucherId = isset($transaction['voucher_id']) ? $transaction['voucher_id'] : "";
                                    $tokeUid = $transaction['token_uid'];
                                    $j5Charge = isset($transaction['charged_j5_amount']) ? $transaction['charged_j5_amount'] : "";
                                    echo WC_PayPlus_Statics::createPayPlusDataBox($amount, $method, $type, $number, $fourDigits, $expMonth, $expYear, $numOfPayments, $voucherNum, $voucherId, $tokeUid, $j5Charge);
                                    echo '<br><span style="border: 1px solid #000;display: block;width: 100%;"></span></br>';
                                }
                                echo __('Total of payments: ', 'payplus-payment-gateway') . $totalAmount;
                            }
                        }
                    }
                }
            }
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
    }
