<?php
$ccImage = site_url() . "/wp-content/plugins/payplus-payment-gateway/assets/images/cCards.png";
$ccImageAltText = 'Pay with Debit or Credit Card';

$locale = get_locale();
$rowDirection = $locale !== "he_IL" ? "row" : "row-reverse";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        @font-face {
            font-family: 'AlmoniMLv5AAA';
            src: url('../wp-content/plugins/payplus-payment-gateway/assets/css/fonts/almoni-medium-aaa.woff2') format('woff2'),
                url('../wp-content/plugins/payplus-payment-gateway/assets/css/fonts/almoni-medium-aaa.woff') format('woff'),
                url('../wp-content/plugins/payplus-payment-gateway/assets/css/fonts/almoni-medium-aaa.eot') format('opentype');
            font-weight: normal;
            /* Or bold if necessary */
            font-style: normal;
            /* Or italic if necessary */
        }

        label {
            margin: unset !important;
            border: none !important;
        }

        .fld-frame {
            border: 1px solid #ced4da;
            height: 37px;
            padding: 5px;
            margin: 5px 0;
            background-color: #fff;
            border-radius: 5px;
            width: 100%;
        }

        .iframe-placeholder.cvv-fld {
            flex-direction: <?php echo $rowDirection; ?>
        }

        .col-4 {
            flex: 0 0 auto;
            width: fit-content !important;
            display: block;
            background: #F7F7F7;
            padding: 15px;
            border-radius: 10px;
            border: none;
            position: relative;
            flex-direction: row;
            /* align-content: center; */
            flex-wrap: wrap;
            justify-content: flex-end;
            margin: auto;
            display: flex;
            /* flex-direction: column; */
            background: #F7F7F7;
            /* height: 55vh; */
            flex-wrap: wrap;
            justify-content: space-between;
            font-family: 'AlmoniMLv5AAA';
        }

        #hostedTop {
            display: flex;
            height: fit-content;
            justify-content: space-between;
            border-bottom: 1px solid #E3E6E9 !important;
            width: 100%;
            padding: 20px;

            .topText {
                color: #000000;
                font-size: 16px;
            }

            .creditCards {
                img {
                    max-height: 60px;
                }
            }
        }

        #submit-payment {
            background-color: var(--wp--preset--color--contrast);
            border-radius: 0.33rem;
            border-color: var(--wp--preset--color--contrast);
            border-width: 0;
            color: var(--wp--preset--color--base);
            font-family: inherit;
            font-size: var(--wp--preset--font-size--small);
            font-style: normal;
            font-weight: 500;
            line-height: inherit;
            padding-top: 0.6rem;
            padding-right: 1rem;
            padding-bottom: 0.6rem;
            padding-left: 1rem;
            text-decoration: none;
            margin-top: 15px !important;
            margin-left: auto !important;
            margin-right: auto !important;
            border-radius: 8px;
            width: 90%;
        }

        #ppLogo {
            font-family: "Almoni", sans-serif;
            color: #c5cbcf;
            text-align: center;
            font-size: 12px;
            width: 100%;
            bottom: 5px;
            direction: ltr;
            padding: 1em;

            img {
                height: 17px;
                top: 2px;
            }
        }

        .expiry-wrapper {
            justify-content: space-between;
        }

        .container.hostedFields {
            display: none;
        }

        #payment-form {
            display: none;
            align-items: center;
            flex-direction: column;
            flex-wrap: wrap;
            align-content: flex-start;
        }

        .__payplus_hosted_fields_err_fld {
            color: red;
            border: 1px solid red;
        }

        input[readonly] {
            background-color: #eee;
            outline: 0;
        }

        .blocks-payplus_loader_hosted {
            display: none;
            position: relative;
            z-index: 1000000000000;
        }

        .expiries {
            width: 90%;
            display: flex;
            margin: auto;
            flex-wrap: wrap;
        }

        input:-internal-autofill-selected {
            background-color: transparent !important;
        }

        .expireClass {
            background-color: white;
            width: 50%;
            height: 50px;
            display: flex;
            justify-content: center;
            border: 1px solid #E3E6E9 !important;
            border-radius: 8px;

            @media screen and (max-width: 567px) {
                min-width: 100%;
            }
        }

        .smallCol {
            flex: 0 0 auto;
            width: 30%;
        }

        .iframe-wrapper {
            position: relative;
            width: 100%;
            /* Adjust based on your iframe size */
            /* height: 50%; */
            /* Adjust based on your iframe size */
        }

        .justBorder {
            border: 1px solid #E3E6E9 !important;
        }

        .iframe-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            padding: 10px;
            border-radius: 8px;
            /* justify-content: center; */
            align-items: center;
            background-color: transparent;
            /* border: 1px solid #E3E6E9 !important; */
            /* Semi-transparent background */
            color: #A2ADB5;
            font-size: 18px;
            z-index: 10;
            pointer-events: none;
            /* Allows clicks to pass through to the iframe */
        }

        .pp_iframe_h iframe {
            border: 0;
            max-height: 45px;
            /* min-width: 55px;
            text-align: center; */
        }

        .fld-frame {
            border: none;
            box-sizing: unset;
            outline: unset;
        }

        input {
            outline: unset;
        }

        .pp_iframe_h {
            .form-control {
                width: 100% !important;
                padding: 0.5rem 0.75rem !important;
                font-size: 1rem !important;
                border: 1px solid #E3E6E9 !important;
                border-radius: 8px !important;
                height: 45px;
                margin: auto !important;
            }
        }

        input[type="text" i] {
            font-size: 20px;
        }

        .h-fld-wrapper {
            width: 100%;
        }

        .fld-wrapper {
            width: 90%;
            margin: auto;
            margin-bottom: 0.2em;
        }

        .row {
            @media screen and (max-width: 768px) {
                min-width: 100% !important;
            }
        }

        .pp_iframe_h {
            .row>* {
                padding: unset !important;
            }
        }

        .btn-primary {
            font-size: 21px !important;
            padding: unset !important;
            height: 44px !important;
        }

        .exp {
            width: 40%;
        }

        .seperator {
            padding: 7px;
            width: 40px;
            color: #E3E6E9;
        }

        .form-select {
            height: 45px;
            border: 1px solid #E3E6E9 !important;
            border-radius: 8px !important;
            background-color: white;
        }

        #payments-wrapper {
            display: flex;
            flex-wrap: wrap;
            flex-direction: column-reverse;
        }
    </style>
</head>



<body>
    <div class="container hostedFields">
        <br />
        <div id="payment-form">
            <div class="row">
                <div class="blocks-payplus_loader_hosted">
                    <div class="blocks-loader">
                        <div class="blocks-loader-background">
                            <div class="blocks-loader-text"></div>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div id="hostedTop">
                        <div class="topText"><?php echo esc_html__('Credit card', 'payplus-payment-gateway'); ?></div>
                        <div class="creditCards">
                            <img src="<?php echo esc_url($ccImage); ?>" alt="<?php echo esc_attr($ccImageAltText); ?>" />
                        </div>
                    </div>
                    <div id="card-holder-name-wrapper" class="fld-wrapper">
                        <label><?php echo esc_html__('Name', 'payplus-payment-gateway'); ?></label>
                        <input type="text" id="card-holder-name" class="form-control" value="" />
                    </div>
                    <div id="id-number-wrapper" class="fld-wrapper">
                        <label><?php echo esc_html__('ID number', 'payplus-payment-gateway'); ?></label>
                        <input id="id-number" type="number" class="form-control" value="" />
                    </div>
                    <div class="row">
                        <div class="col-2 expiry-wrapper-full">
                            <label>Expiry date</label>
                            <span id="expiry" class="fld-frame"></span>
                        </div>
                    </div>
                    <div class="expiry-wrapper expiries">
                        <div id="cc-wrapper" class="h-fld-wrapper">
                            <label><?php echo esc_html__('Card number', 'payplus-payment-gateway'); ?></label>
                            <div id="cCard" class="iframe-wrapper">
                                <span id="cc" placeholder="Card Number" class="form-control fld-frame"
                                    data-hosted-fields-identifier="cc"></span>
                            </div>
                        </div>
                        <div class="expireClass">
                            <span id="expirym" class="fld-frame"></span>
                            <span class="seperator"> / </span>
                            <span id="expiryy" class="fld-frame"></span>
                        </div>
                        <div id="cvv-fld" class="expireClass">
                            <div class="iframe-wrapper">
                                <label class="iframe-placeholder cvv-fld">
                                    <img src="../wp-content/plugins/payplus-payment-gateway/assets/images/cvv.svg"
                                        alt="<?php echo esc_attr__('Pay with Debit or Credit Card', 'payplus-payment-gateway'); ?>"
                                        style="top: 1.4px;" />
                                </label>
                                <div class="row" id="cvv-wrapper">
                                    <span id="cvv" class="fld-frame" data-hosted-fields-identifier="main-form"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="payments-wrapper" class="fld-wrapper">
                        <label><?php echo esc_html__('Payments', 'payplus-payment-gateway'); ?></label>
                        <select class="form-select" id="payments" aria-label="Default select example">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </select>
                    </div>
                    <div class="row card-holder-phone-wrapper">
                        <div class="col-12">
                            <label>Phone</label>
                            <div class="row">
                                <div class="col-3">
                                    <select class="form-select card-holder-phone-prefix"
                                        aria-label="Default select example">
                                        <option value="1">+1</option>
                                        <option value="20">+20</option>
                                        <option value="27">+27</option>
                                        <option value="30">+30</option>
                                        <option value="31">+31</option>
                                        <option selected value="972">
                                            +972
                                        </option>
                                    </select>
                                </div>
                                <div class="col-9">
                                    <input type="text" class="form-control card-holder-phone" value="" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="invoice-name-wrapper">
                        <div class="col-12">
                            <label>Invoice name</label>
                            <input type="text" id="invoice-name" class="form-control" value="" />
                        </div>
                    </div>
                    <input type="button" value="<?php echo esc_attr__('Place Order', 'payplus-payment-gateway'); ?>"
                        id="submit-payment" class="btn btn-primary" />
                    <br />
                    <div id="ppLogo">
                        <?php echo esc_html__('Powered by ', 'payplus-payment-gateway'); ?>
                        <img src="../wp-content/plugins/payplus-payment-gateway/assets/images/payplus-logo-new.png"
                            alt="<?php echo esc_attr__('Pay with Debit or Credit Card', 'payplus-payment-gateway'); ?>" />
                    </div>
                </div>
                <div class="col-4" style="display: none">
                    <div class="row">
                        <div class="col-12 wrapper customer_name-wrapper">
                            <label>Customer name</label>
                            <input type="text" name="customer_name" class="form-control" value="" />
                        </div>
                        <div class="col-12 wrapper customer_id-wrapper">
                            <label>Customer id</label>
                            <input type="text" name="customer_id" class="form-control" value="" />
                        </div>
                        <div class="col-12 wrapper phone-wrapper">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="" />
                        </div>
                        <div class="col-12 wrapper email-wrapper">
                            <label>email</label>
                            <input type="email" name="email" class="form-control" value="" />
                        </div>
                        <div class="col-12 wrapper address-wrapper">
                            <label>Address</label>
                            <input type="text" name="address" class="form-control" value="" />
                        </div>
                        <div class="col-12 wrapper country-wrapper">
                            <label>Country</label>
                            <input type="text" name="country" class="form-control" value="" />
                        </div>
                        <div class="col-12 wrapper notes-wrapper">
                            <label>Notes</label>
                            <input type="text" name="notes" class="form-control" value="" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <span id="recaptcha"></span>
</body>

</html>