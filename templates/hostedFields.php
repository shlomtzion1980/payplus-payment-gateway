<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        label {
            margin: unset !important;
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

        .col-4 {
            flex: 0 0 auto;
            width: fit-content !important;
            display: block;
            background-color: #e9e9e9;
            padding: 15px;
            border-radius: 10px;
            border: solid 0.5px;
            position: relative;
            flex-direction: row;
            align-content: center;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin: auto;
            /* @media screen and (max-width: 567px) {
          width: 100% !important;
        } */
        }

        #hostedTop {
            display: flex;
            height: 50px;
            justify-content: space-between;

            .topText {
                color: #c5cbcf;
            }

            .creditCards {
                img {
                    height: 35px;
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
            margin-top: 10px !important;
        }

        #ppLogo {
            font-family: "Almoni", sans-serif;
            color: #c5cbcf;
            position: absolute;
            right: 10px;
            bottom: 5px;

            img {
                height: 20px;
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
                        <div class="topText"><?php echo __('Credit card', 'payplus-payment-gateway'); ?></div>
                        <div class="creditCards">
                            <img
                                src="../wp-content/plugins/payplus-payment-gateway/assets/images/visa.png"
                                alt="&nbsp;&nbsp;Pay with Debit or Credit Card" />
                            <img
                                src="../wp-content/plugins/payplus-payment-gateway/assets/images/mastercard.png"
                                alt="&nbsp;&nbsp;Pay with Debit or Credit Card" />
                            <img
                                src="../wp-content/plugins/payplus-payment-gateway/assets/images/amex.png"
                                alt="&nbsp;&nbsp;Pay with Debit or Credit Card" />
                            <img
                                src="../wp-content/plugins/payplus-payment-gateway/assets/images/diners.png"
                                alt="&nbsp;&nbsp;Pay with Debit or Credit Card" />
                        </div>
                    </div>
                    <div class="row" id="id-number-wrapper">
                        <div class="col-12">
                            <label><?php echo __('ID number', 'payplus-payment-gateway'); ?></label>
                            <input
                                id="id-number"
                                type="number"
                                class="form-control"
                                value="" />
                        </div>
                    </div>
                    <div class="row" id="payments-wrapper">
                        <div class="col-12">
                            <label>Number of payments</label>
                            <select
                                class="form-select"
                                id="payments"
                                aria-label="Default select example">
                                <option value="1">One</option>
                                <option value="2">Two</option>
                                <option value="3">Three</option>
                            </select>
                        </div>
                    </div>
                    <div class="row" id="cc-wrapper">
                        <div class="col-12">
                            <label><?php echo __('Card number', 'payplus-payment-gateway'); ?></label>
                            <span
                                id="cc"
                                placeholder="Card Number"
                                class="form-control fld-frame"
                                data-hosted-fields-identifier="cc"></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-2 expiry-wrapper-full">
                            <label>Expiry date</label>
                            <span id="expiry" class="fld-frame"></span>
                        </div>
                    </div>
                    <div class="row expiry-wrapper">
                        <div class="col-2">
                            <label><?php echo __('Month', 'payplus-payment-gateway'); ?></label>
                            <span id="expirym" class="fld-frame"></span>
                        </div>
                        <div class="col-2">
                            <label><?php echo __('Year', 'payplus-payment-gateway'); ?></label>
                            <span id="expiryy" class="fld-frame"></span>
                        </div>
                        <div class="col-3">
                            <label><?php echo __('CVV', 'payplus-payment-gateway'); ?></label>
                            <span
                                id="cvv"
                                class="fld-frame"
                                data-hosted-fields-identifier="main-form"></span>
                        </div>
                    </div>
                    <div class="row" id="cvv-wrapper"></div>
                    <div class="row" id="card-holder-name-wrapper">
                        <div class="col-12">
                            <label><?php echo __('Name', 'payplus-payment-gateway'); ?></label>
                            <input
                                type="text"
                                id="card-holder-name"
                                class="form-control"
                                value="" />
                        </div>
                    </div>
                    <div class="row card-holder-phone-wrapper">
                        <div class="col-12">
                            <label>Phone</label>
                            <div class="row">
                                <div class="col-3">
                                    <select
                                        class="form-select card-holder-phone-prefix"
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
                                    <input
                                        type="text"
                                        class="form-control card-holder-phone"
                                        value="" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="invoice-name-wrapper">
                        <div class="col-12">
                            <label>Invoice name</label>
                            <input
                                type="text"
                                id="invoice-name"
                                class="form-control"
                                value="" />
                        </div>
                    </div>
                    <br />
                    <div id="ppLogo">
                        <?php echo __('Powered By:', 'payplus-payment-gateway'); ?>
                        <img
                            src="../wp-content/plugins/payplus-payment-gateway/assets/images/payplus-logo-new.png"
                            alt="&nbsp;&nbsp;Pay with Debit or Credit Card" />
                    </div>
                </div>
                <div class="col-4" style="display: none">
                    <div class="row">
                        <div class="col-12 wrapper customer_name-wrapper">
                            <label>Customer name</label>
                            <input
                                type="text"
                                name="customer_name"
                                class="form-control"
                                value="" />
                        </div>
                        <div class="col-12 wrapper customer_id-wrapper">
                            <label>Customer id</label>
                            <input
                                type="text"
                                name="customer_id"
                                class="form-control"
                                value="" />
                        </div>
                        <div class="col-12 wrapper phone-wrapper">
                            <label>Phone</label>
                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                value="" />
                        </div>
                        <div class="col-12 wrapper email-wrapper">
                            <label>email</label>
                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="" />
                        </div>
                        <div class="col-12 wrapper address-wrapper">
                            <label>Address</label>
                            <input
                                type="text"
                                name="address"
                                class="form-control"
                                value="" />
                        </div>
                        <div class="col-12 wrapper country-wrapper">
                            <label>Country</label>
                            <input
                                type="text"
                                name="country"
                                class="form-control"
                                value="" />
                        </div>
                        <div class="col-12 wrapper notes-wrapper">
                            <label>Notes</label>
                            <input
                                type="text"
                                name="notes"
                                class="form-control"
                                value="" />
                        </div>
                    </div>
                </div>
            </div>
            <input
                type="button"
                value="<?php echo __('Place Order', 'payplus-payment-gateway'); ?>"
                id="submit-payment"
                class="btn btn-primary" />
        </div>
    </div>
    <span id="recaptcha"></span>
    <!-- <script src="node_modules/jquery/dist/jquery.min.js"></script> -->
    <!-- <script src="https://wordpress.test/wp-content/plugins/payplus-payment-gateway/assets/js/payplus-hosted-fields/dist/payplus-hosted-fields.min.js"></script>
    <script src="https://wordpress.test/wp-content/plugins/payplus-payment-gateway/assets/js/hostedFieldsScript.js?ver=1.0"></script> -->
</body>

</html>