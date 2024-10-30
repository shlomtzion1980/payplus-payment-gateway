<?php
$ccImage = site_url() . "/wp-content/plugins/payplus-payment-gateway/assets/images/cCards.png";
$ccImageAltText = 'Pay with Debit or Credit Card';

$locale = get_locale();
$rowDirection = $locale !== "he_IL" ? "row" : "row-reverse";
$direction = $locale !== "he_IL" ? "right" : "left";
$opposite = $locale !== "he_IL" ?  "left" : "right";

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
        src: url('<?php echo site_url(); ?>/wp-content/plugins/payplus-payment-gateway/assets/css/fonts/almoni-medium-aaa.woff2') format('woff2'),
            url('<?php echo site_url(); ?>/wp-content/plugins/payplus-payment-gateway/assets/css/fonts/almoni-medium-aaa.woff') format('woff'),
            url('<?php echo site_url(); ?>/wp-content/plugins/payplus-payment-gateway/assets/css/fonts/almoni-medium-aaa.eot') format('opentype');
        font-weight: normal;
        /* Or bold if necessary */
        font-style: normal;
        /* Or italic if necessary */
    }

    label {
        border: none !important;
    }

    .fld-frame {
        border: 1px solid #ced4da;
        height: 37px;
        padding: 5px;
        margin: 5px 0;
        background-color: #fff;
        width: 100%;
    }

    .hf-col-4 {
        flex: 0 0 auto;
        width: fit-content !important;
        display: block;
        background: #F7F7F7;
        padding: 15px;
        border-radius: 10px;
        border: 1px solid #E3E6E9 !important;
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
        min-height: 533px;

        #payments {
            background-color: white !important;
        }

        .hf-row {
            select {
                background-color: white !important;
            }
        }

        select {

            padding-right: 15px !important;
            padding-left: 15px !important;
            padding-top: unset !important;
            padding-bottom: unset !important;
            font-size: 1rem !important;

            @media screen and (min-width: 567px) {
                appearance: none;
                /* Remove default arrow (Webkit) */
                -moz-appearance: none;
                /* Remove default arrow (Firefox) */

                background-image: url('<?php echo site_url(); ?>/wp-content/plugins/payplus-payment-gateway/assets/images/dropdown-arrow.png');
                background-repeat: no-repeat;
                background-position: <?php echo esc_attr(text: $direction) . ' 15px center';
                ?>;
                /* Add custom arrow */
                background-size: 10px;
            }
        }
    }

    #hostedTop {
        display: flex;
        height: fit-content;
        justify-content: space-between;
        border-bottom: 1px solid #E3E6E9 !important;
        width: 100%;
        padding: 15px;
        max-height: 62px;

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

        .hf-image {
            height: 17px;
            top: 2px;
            display: initial;
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
        max-width: 440px;
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
        z-index: 999999;
    }

    .__payplus_hosted_fields_item_fld-wrapper {
        display: flex;
        flex-wrap: wrap;
        align-content: center;
    }

    .expiries {
        width: 90%;
        display: flex;
        margin: auto;
        flex-wrap: wrap;
        justify-content: space-between;
    }

    input:-internal-autofill-selected {
        background-color: transparent !important;
    }

    .col-9 {
        @media screen and (min-width: 467px) {
            margin-left: 3px;
            max-width: 74% !important;
        }
    }

    .expireCvvClass {
        background-color: white;
        width: 50%;
        height: 38px;
        display: flex;
        padding: 0 10px 0 10px;
        align-items: center;
        border: 1px solid #E3E6E9 !important;
        border-top: none !important;


        &.right {
            border-bottom-right-radius: 8px;
            border-left: none !important;
        }

        &.left {
            border-bottom-left-radius: 8px;
        }
    }

    .smallCol {
        flex: 0 0 auto;
        width: 30%;
    }

    .justBorder {
        border: 1px solid #E3E6E9 !important;
    }

    .pp_iframe_h iframe {
        border: 0;
        max-height: 38px;
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
        iframe {
            outline: none;
            border: none;
            /* This also removes any visible border */
        }

        input:focus,
        iframe:focus {
            outline: none !important;
            box-shadow: none !important;
        }

        input::placeholder {
            color: #A2ADB5;

            /* Change to desired color */
            opacity: 1;
            /* Optional, removes default opacity in some browsers */
        }

        .form-control {
            width: 100% !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 1rem !important;
            border: 1px solid #E3E6E9 !important;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            height: 38px;
            margin: auto !important;
            text-align: inherit;
        }

        .forms-control {
            width: 100% !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 1rem !important;
            border: 1px solid #E3E6E9 !important;
            border-radius: 8px !important;
            height: 38px;
            margin: auto !important;
            text-align: inherit;
            background-image: url('<?php echo site_url(); ?>/wp-content/plugins/payplus-payment-gateway/assets/images/vi.svg');
            background-repeat: no-repeat;
            background-position: <?php echo esc_attr(text: $direction) . " 15px center";
            ?>;
        }
    }

    input[type="text"i] {
        font-size: 20px;
    }

    .h-fld-wrapper {
        width: 100%;
    }

    .fld-wrapper {
        width: 90%;
        margin: 0.2em auto 0.2em auto;
    }

    .hf-row,
    .hf-main {
        width: 100%;
        display: flex;

        @media screen and (max-width: 768px) {
            min-width: 100% !important;
        }

        .hf-col-12 {
            width: 100%;
        }

        .hf-col-3 {
            flex: 0 0 auto;
            width: 25%;

            @media screen and (max-width: 568px) {
                width: 35%;
            }
        }

        .hf-col-9 {
            flex: 0 0 auto;
            width: 75%;

            @media screen and (max-width: 568px) {
                width: 65%;
            }

            @media screen and (min-width: 467px) {
                margin-left: 3px;
                max-width: 74% !important;
            }
        }
    }

    .hf-save {
        margin-top: 10px !important;
    }

    .pp_iframe_h {
        .hf-main>* {
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
        display: flex;
        width: 50%;
        color: #E3E6E9;
        height: fit-content;
        justify-content: flex-start;
    }

    .form-select {
        height: 38px;
        border: 1px solid #E3E6E9 !important;
        border-radius: 8px !important;
        background-color: white;
        outline: none;
    }

    #payments-wrapper {
        display: flex;
        flex-wrap: wrap;
        flex-direction: column;
    }

    #payments-wrapper label {
        order: 1;
        /* Label always appears first */
    }

    #payments-wrapper select {
        order: 2;
        /* Select field always appears second */
    }

    .card-holder-phone-prefix {
        width: 100%;
        outline: none;
        text-align: center;
    }
    </style>
</head>



<body>
    <div class="container hostedFields">
        <br />
        <div id="payment-form">
            <div class="hf-main">
                <div class="blocks-payplus_loader_hosted">
                    <div class="blocks-loader">
                        <div class="blocks-loader-background">
                            <div class="blocks-loader-text"></div>
                        </div>
                    </div>
                </div>
                <div class="hf-col-4">
                    <div id="hostedTop">
                        <div class="topText"><?php echo esc_html__('Credit card', 'payplus-payment-gateway'); ?></div>
                        <div class="creditCards">
                            <img class="hf-image" src="<?php echo esc_url($ccImage); ?>"
                                alt="<?php echo esc_attr($ccImageAltText); ?>" />
                        </div>
                    </div>
                    <div id="card-holder-name-wrapper" class="fld-wrapper">
                        <label><?php echo esc_html__('Name', 'payplus-payment-gateway'); ?></label>
                        <input type="text" id="card-holder-name" class="forms-control" value="" />
                    </div>
                    <div id="id-number-wrapper" class="fld-wrapper">
                        <label><?php echo esc_html__('ID number', 'payplus-payment-gateway'); ?></label>
                        <input id="id-number" type="number" class="forms-control" value="" />
                    </div>
                    <div class="fld-wrapper" id="invoice-name-wrapper">
                        <label><?php echo esc_html__('Invoice Name', 'payplus-payment-gateway'); ?></label>
                        <input type="text" id="invoice-name" class="forms-control" value="" />
                    </div>
                    <div class="card-holder-phone-wrapper fld-wrapper">
                        <div class="hf-col-12">
                            <label><?php echo esc_html__('Phone', 'payplus-payment-gateway'); ?></label></label>
                            <div class="hf-row" dir="ltr">
                                <div class="hf-col-3">
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
                                <div class="hf-col-9">
                                    <input type="text" placeholder="999-999-9999" class="form-control card-holder-phone"
                                        value="" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="expiries">
                        <div id="cc-wrapper" class="h-fld-wrapper">
                            <label><?php echo esc_html__('Card number', 'payplus-payment-gateway'); ?></label>
                            <div id="cCard">
                                <span id="cc" placeholder="Card Number" class="form-control fld-frame"
                                    data-hosted-fields-identifier="cc"></span>
                            </div>
                        </div>
                        <div class="expiry-wrapper-full expireCvvClass <?php echo esc_attr(text: $opposite); ?>">
                            <span id="expiry" class="fld-frame"></span>
                        </div>
                        <div class="expiry-wrapper expireCvvClass <?php echo esc_attr(text: $opposite); ?>">
                            <span id="expirym" class="fld-frame"></span>
                            <span class="seperator"> / </span>
                            <span id="expiryy" class="fld-frame"></span>
                        </div>
                        <div id="cvv-fld" class="expireCvvClass <?php echo esc_attr(text: $direction); ?>">
                            <div class="hf-row" id="cvv-wrapper">
                                <span id="cvv" class="fld-frame" data-hosted-fields-identifier="main-form"></span>
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
                    <input type="button" value="<?php echo esc_attr__('Place Order', 'payplus-payment-gateway'); ?>"
                        id="submit-payment" class="btn btn-primary" />
                    <br />
                    <div id="ppLogo">
                        <?php echo esc_html__('Powered by ', 'payplus-payment-gateway'); ?>
                        <img class="hf-image"
                            src="<?php echo esc_url(site_url() . '/wp-content/plugins/payplus-payment-gateway/assets/images/payplus-logo-new.png'); ?>"
                            alt="<?php echo esc_attr__('Pay with Debit or Credit Card', 'payplus-payment-gateway'); ?>" />
                    </div>
                </div>
                <div class="hf-col-4" style="display: none">
                    <div class="hf-row">
                        <div class="hf-col-12 wrapper customer_name-wrapper">
                            <label>Customer name</label>
                            <input type="text" name="customer_name" class="form-control" value="" />
                        </div>
                        <div class="hf-col-12 wrapper customer_id-wrapper">
                            <label>Customer id</label>
                            <input type="text" name="customer_id" class="form-control" value="" />
                        </div>
                        <div class="hf-col-12 wrapper phone-wrapper">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="" />
                        </div>
                        <div class="hf-col-12 wrapper email-wrapper">
                            <label>email</label>
                            <input type="email" name="email" class="form-control" value="" />
                        </div>
                        <div class="hf-col-12 wrapper address-wrapper">
                            <label>Address</label>
                            <input type="text" name="address" class="form-control" value="" />
                        </div>
                        <div class="hf-col-12 wrapper country-wrapper">
                            <label>Country</label>
                            <input type="text" name="country" class="form-control" value="" />
                        </div>
                        <div class="hf-col-12 wrapper notes-wrapper">
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