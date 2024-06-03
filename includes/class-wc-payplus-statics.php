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
    public static function invoice_plus_metabox($order_id, $options = [])
    {
        $refundsJson = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_refunds', true);
        $refundsArray = !empty($refundsJson) ? json_decode($refundsJson, true) : $refundsJson;
        $errorInvoice = WC_PayPlus_Order_Data::get_meta($order_id, "payplus_error_invoice", true);

        $invDoc = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_invoice_originalDocAddress', true);
        $invDocType = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_invoice_type', true);
        $invDocNumber = WC_PayPlus_Order_Data::get_meta($order_id, 'payplus_invoice_numberD', true);
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
                    <div>
                        <?php if ($options['no-headlines'] !== true) { ?><h4><?php echo $chargeText; ?></h4><?php } ?>
                        <a class="invoicePlusButton" style="text-decoration: none;" target="_blank" href="<?php echo $invDoc; ?>"><?php echo $docType; ?> (<?php echo $invDocNumber; ?>)</a>
                    </div>
                    <div>
                        <?php if ($options['no-headlines'] !== true) { ?><h4><?php echo $refundsText; ?></h4><?php } ?>
                        <?php
                        foreach ($refundsArray as $docNumber => $doc) {
                            $docLink = $doc['link'];
                            $docText = __($doc['type'], 'payplus-payment-gateway');
                        ?>
                            <a class="invoicePlusButton" style="text-decoration: none;" target="_blank" href="<?php echo $docLink; ?>"><?php echo "$docText ($docNumber)"; ?></a>
                        <?php
                        }

                        ?>
                    </div>
                </div>
        </div>
    <?php
            } elseif ($errorInvoice) { ?>
        <p class='link-invoice-error'>
            <?php echo $errorInvoice; ?>
        </p><?php
            }
        }
    }
