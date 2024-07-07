<?php
$settings[$section][] = array(
    'name' => __('Invoice Creation: Manual', 'payplus-payment-gateway'),
    'id' => 'payplus_invoice_option[create-invoice-manual]',
    'type' => 'select',
    'options' => [0 => __('Automatic', 'payplus-payment-gateway'), 1 => __('Manual', 'payplus-payment-gateway')],
    'class' => 'create-invoice-manual',
    'desc' => __('Invoice creation: Automatic(Default) or Manual', 'payplus-payment-gateway'),
    'default' => 'no',
    'desc_tip' => true
);
