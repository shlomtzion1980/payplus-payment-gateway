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
            if (strlen($invDoc) > 0 || is_array($refundsArray)) { ?>
                <button class="toggle-button invoicePlusButtonShow"></button>
                <div class="hidden-buttons invoicePlusButtonHidden">

                    <?php if (isset($options['no-headlines']) && $options['no-headlines'] !== true) { ?><h4><?php echo $chargeText; ?></h4><?php } ?>
                    <a class="invoicePlusButton" style="text-decoration: none;" target="_blank" href="<?php echo $invDoc; ?>"><?php echo $docType; ?> (<?php echo $invDocNumber; ?>)</a>

                    <?php if (isset($options['no-headlines']) && $options['no-headlines'] !== true) { ?><h4><?php echo $refundsText; ?></h4><?php } ?>
                    <?php
                    if (is_array($refundsArray)) {
                        foreach ($refundsArray as $docNumber => $doc) {
                            $docLink = $doc['link'];
                            $docText = __($doc['type'], 'payplus-payment-gateway');
                    ?>
                            <a class="invoicePlusButton" style="text-decoration: none;" target="_blank" href="<?php echo $docLink; ?>"><?php echo "$docText ($docNumber)"; ?></a>
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
                    $type = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_type', true);
                    $number = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_number', true);
                    $fourDigits = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_four_digits', true);
                    $expMonth = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_expiry_month', true);
                    $expYear = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_expiry_year', true);
                    $voucherNum = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_voucher_num', true);
                    $voucherId = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_voucher_id', true);
                    $tokeUid = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_token_uid', true);
                    $j5Charge = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_charged_j5_amount', true);
                    $amount = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_credit-card', true);
                    $responsePayPlus = WC_PayPlus_Meta_Data::get_meta($order_id, 'payplus_response', true);
                    $responseArray = json_decode($responsePayPlus, true);
                    if (!$amount && is_array($responseArray)) {
                        $amount = $responseArray['amount'];
                    }

                    $expMonthYear = "$expMonth/$expYear";
                    $box = sprintf(
                        __(
                            '
                    <div style="font-weight:600;">PayPlus ' . (($type == "Approval" || $type == "Check") ? 'Pre-Authorization' : 'Payment') . ' Successful</div>
                        <table style="border-collapse:collapse">
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Transaction#</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Last digits</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Expiry date</td><td style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Voucher #</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="border-bottom:1px solid #000;vertical-align:top;">Voucher ID</td><td  style="border-bottom:1px solid #000;vertical-align:top;">%s</td></tr>
                            <tr><td style="vertical-align:top;">Token</td><td style="vertical-align:top;">%s</td></tr>
                            <tr><td style="vertical-align:top;">Total</td><td style="vertical-align:top;">%s</td></tr>
                        </table>
                    ',
                            'payplus-payment-gateway'
                        ),
                        $number,
                        $fourDigits,
                        $expMonthYear,
                        $voucherNum,
                        $voucherId,
                        $tokeUid,
                        $j5Charge ? $j5Charge : $amount
                    );
                    if ($responsePayPlus) {
                        echo $box;
                    }
                }
            }
        }
    }
