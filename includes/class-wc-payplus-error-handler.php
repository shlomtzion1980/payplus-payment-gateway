<?php

class WCPayPlusErrorCodes
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
        $this->errors['Field not found'] = esc_html__('Field not found', 'payplus-payment-gateway');
        $this->errors['missing-cvv'] = esc_html__('Missing CVV', 'payplus-payment-gateway');
        $this->errors['missing-id'] = esc_html__('Missing ID', 'payplus-payment-gateway');
        $this->errors['recaptcha-confirmation-is-missing'] = esc_html__('Recaptcha confirmation is missing', 'payplus-payment-gateway');
        $this->errors['error-codes-terminal-type-6-code-i-131'] = esc_html__('Credit card number not validated', 'payplus-payment-gateway');
        //Messages translations
        $this->fieldNames['card_holder_name'] = esc_html__('Card holder name', 'payplus-payment-gateway');
        $this->fieldNames['cvv'] = esc_html__('CVV', 'payplus-payment-gateway');
        $this->fieldNames['card_holder_id'] = esc_html__('Card holder ID', 'payplus-payment-gateway');
    }

    public function getTranslation($errorOrField = null, $type = 'error')
    {
        switch ($type) {
            case 'error':
                return $this->errors[$errorOrField];
                break;
            case 'field':
                return $this->fieldNames[$errorOrField];
                break;
        }
    }

    public function getAllTranslations()
    {
        return ["Errors" => $this->errors, "Fields" => $this->fieldNames];
    }
}
