<?php

class WCPayplusErrorCodes
{
    public $errors = [];
    public $fieldNames = [];

    public function __construct()
    {
        //Errors translations
        $this->errors['card-holder-vat-id-not-valid'] = esc_html__('ID not valid', 'payplus-payment-gateway');
        $this->errors['missing-name'] = esc_html__('Name is missing', 'payplus-payment-gateway');
        $this->errors['credit-card-number-is-incorrect'] = esc_html__('Credit card number is incorrect', 'payplus-payment-gateway');
        $this->errors['not-authorize-page-expired'] = esc_html__('The payment page has expired, please reload to refresh.', 'payplus-payment-gateway');
        //Messages translations
        $this->fieldNames['card_holder_name'] = esc_html__('Card holder name', 'payplus-payment-gateway');
        // $errors['card-holder-vat-id-not-valid'] = esc_html__('ID not valid', 'payplus-payment-gateway');
        // $errors['card-holder-vat-id-not-valid'] = esc_html__('ID not valid', 'payplus-payment-gateway');
        // $errors['card-holder-vat-id-not-valid'] = esc_html__('ID not valid', 'payplus-payment-gateway');
        // $errors['card-holder-vat-id-not-valid'] = esc_html__('ID not valid', 'payplus-payment-gateway');
        // $errors['card-holder-vat-id-not-valid'] = esc_html__('ID not valid', 'payplus-payment-gateway');
        // $errors['card-holder-vat-id-not-valid'] = esc_html__('ID not valid', 'payplus-payment-gateway');
    }

    public function getTranslation($error = null, $type = 'error')
    {
        switch ($type) {
            case 'error':
                return $this->errors[$error];
                break;
            case 'field':
                return $this->fieldNames[$error];
                break;
        }
    }

    public function getAllTranslations()
    {
        return ["Errors" => $this->errors, "Fields" => $this->fieldNames];
    }
}
