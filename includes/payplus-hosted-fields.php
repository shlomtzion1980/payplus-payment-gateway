<?php

define('API_KEY', '34a62c67-cd66-4a97-b14e-55177619ef89');
define('SECRET_KEY', 'a489da6d-72b2-463f-afe5-c8c5f7993d0c');
define('PAYMENT_PAGE_UID', 'cb08f952-0ee3-4e46-8054-b8edb7c19093');
define('ORIGIN_DOMAIN', site_url());
define('SUCCESS_URL', 'https://www.example.com/success');
define('FAILURE_URL', 'https://www.example.com/failure');
define('CANCEL_URL', 'https://www.example.com/cancel');

/**
 * PAYPLUS_API_URL_DEV is the URL of the API in the development environment.
 */
define('PAYPLUS_API_URL_DEV', 'https://restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink');

/**
 * PAYPLUS_API_URL_PROD is the URL of the API in the production environment.
 */
define('PAYPLUS_API_URL_PROD', 'https://restapi.payplus.co.il/api/v1.0/PaymentPages/generateLink');

// Building sample request to create a payment page
$data = new stdClass();
$data->payment_page_uid = PAYMENT_PAGE_UID;
$data->refURL_success = SUCCESS_URL;
$data->refURL_failure = FAILURE_URL;
$data->refURL_cancel = CANCEL_URL;
$data->create_token = true;
$data->currency_code = "ILS";
$data->charge_method = 1;

/**
 * Origin domain is the domain of the page that is requesting the payment page.
 * This is necessary for the hosted fields to be able to communicate with the client website.
 */
$data->refURL_origin = ORIGIN_DOMAIN;
/**
 * Also notice that we set hosted_fields to true.
 */
$data->hosted_fields = true;

$data->customer = new stdClass();
$data->customer->customer_name = "John Doe";
$data->customer->email = "johndoe@example.com";
$data->customer->phone = "123456789";
$data->amount = 1.5;

$item = new stdClass();
$item->name = "Item name";
$item->quantity = 3;
$item->price = .5;
$item->vat_type = 2;
$data->items = [$item];

$payload = json_encode($data);
$auth = json_encode([
    'api_key' => API_KEY,
    'secret_key' => SECRET_KEY
]);
$requestHeaders = [];
$requestHeaders[] = 'Content-Type:application/json';
$requestHeaders[] = 'Authorization: ' . $auth;


$ch = curl_init(PAYPLUS_API_URL_DEV);
curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_POST, true);
$response = curl_exec($ch);
curl_close($ch);

/**
 * At this point, normally you would need to do some validation to ensure that the response is valid.
 * For example, you can check that the response is a valid JSON, and that it returns a valid payment page URL.
 * 
 * For the sake of simplicity, we will assume that the response is valid.
 * We will add two headers to the response, to allow the client website to access the response.
 */
// header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
