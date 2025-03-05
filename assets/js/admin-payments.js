jQuery(document).ready(function ($) {
    // setFieldReadOnly();
    payplusMenusDisplay();
    let changestatus = document.getElementById("payplus-change-status");

    if (changestatus) {
        changestatus.addEventListener("click", (e) => {
            event.preventDefault();
            const status = document.querySelector(".payplus-change-status");
            debugger;
            const rows = status.querySelectorAll(".payplus-row");
            rows.forEach((row) => {
                row.style.display =
                    row.style.display == "flex" ? "none" : "flex";
            });
        });
    }

    $(".do-api-refund-payplus").click(async function (event) {
        event.preventDefault();
        $(this).addClass("button-loading");

        const parentRow = $(this).parents("tr").attr("class").split(" ");
        const orderID = $("#post_ID").val();
        const id = $(this).attr("data-id");
        const transactionUid = $(this).attr("data-transaction-uid");
        const amount = parseFloat($(".sum-" + parentRow[1]).val());
        const method = $(this).attr("data-method");
        const refund = $(this).attr("data-refund");

        if (
            0 >= parseFloat(amount) ||
            parseFloat(amount) > parseFloat(refund)
        ) {
            $(this).removeClass("button-loading");
            alert(payplus_script_admin.payplus_refund_error);
            return false;
        }

        const data = new FormData();
        data.append("action", "payplus-refund-club-amount");
        data.append("amount", amount);
        data.append("transactionUid", transactionUid);
        data.append("method", method);
        data.append("orderID", orderID);
        data.append(
            "_ajax_nonce",
            payplus_script_admin.payplus_refund_club_amount
        );
        data.append("id", id);
        fetch(payplus_script_admin.ajax_url, {
            method: "post",
            headers: {
                Accept: "application/json",
            },
            body: data,
        })
            .then((response) => response.json())
            .then((response) => {
                $(this).removeClass("button-loading");
                location.href = response.urlredirect;
            });
    });

    $(".select-languages-payplus").change(function (event) {
        event.preventDefault();
        let language = $(this).val();
        let html = "";
        if (language) {
            let languageOther = language.split("-");
            language = language.replace(" ", "-");
            html =
                '<tr valign="top">' +
                '<th scope="row" class="titledesc">' +
                '<label for="settings_payplus_page_error_option[' +
                language +
                ']">' +
                languageOther[1] +
                "</label>" +
                "</th>" +
                '<td class="forminp forminp-textarea">' +
                '<textarea name="settings_payplus_page_error_option[' +
                language +
                ']" id="settings_payplus_page_error_option[' +
                language +
                ']" ' +
                '   style="" class="" placeholder=""></textarea>';
            "</td>" + "</tr>";
            $(".form-table").append(html);
        }
    });
    $(".copytoken").click(function (event) {
        event.preventDefault();
        var copyText = $(".copytoken");
        navigator.clipboard.writeText(copyText.text());
    });

    $("#makeTokenPayment").click(function (event) {
        event.preventDefault();
        let token = $("#ccToken").val();
        let tokenId = $("#ccToken option:selected").attr("id");

        if (
            confirm(
                payplus_script_admin.tokenPaymentConfirmMessage + tokenId + "?"
            )
        ) {
            let orderId = $(this).attr("data-id");
            let typeDocument = $(".select-type-invoice").val();
            // let sum = $(".token-payment-payplus.input-change.price").val();

            $(".payplus_loader").fadeIn();
            $.ajax({
                type: "post",
                dataType: "json",
                url: payplus_script_admin.ajax_url,
                data: {
                    action: "make-token-payment",
                    order_id: orderId,
                    token: token,
                    // paymentSum: sum,
                    typeDocument: typeDocument,
                    _ajax_nonce: payplus_script_admin.payplusTokenPayment,
                },
                success: function (response) {
                    console.log(response);
                    $(".payplus_loader").fadeOut();
                    if (response === 0) {
                        location.reload();
                    }
                },
            });
        }
    });

    $("#custom-button-get-pp").click(function () {
        let loader = $("#order_data").find(".payplus_loader_gpp");
        let side = "right";

        // check if page is rtl or ltr and change the direction of the loader
        if ($("body").hasClass("rtl")) {
            side = "left";
        }

        loader.css(side, "5%");

        loader.css({
            position: "absolute",
            top: "5px",
        });
        $("#custom-button-get-pp").fadeOut();
        $("#get-invoice-plus-data").fadeOut();
        loader.fadeIn();

        var data = {
            action: "payplus_ipn",
            payment_request_uid: $("#custom-button-get-pp").val(),
            order_id: $("#custom-button-get-pp").data("value"),
            _ajax_nonce: payplus_script_admin.payplusCustomAction,
        };

        $.post(ajaxurl, data, function (response) {
            loader.fadeOut();
            location.reload();
        });
    });

    $("#get-invoice-plus-data").click(function () {
        let loader = $("#order_data").find(".payplus_loader_gpp");
        let side = "right";

        // check if page is rtl or ltr and change the direction of the loader
        if ($("body").hasClass("rtl")) {
            side = "left";
        }

        loader.css(side, "5%");

        loader.css({
            position: "absolute",
            top: "5px",
        });
        $("#custom-button-get-pp").fadeOut();
        $("#get-invoice-plus-data").fadeOut();
        loader.fadeIn();

        var data = {
            action: "invoice_plus_search",
            transaction_uuid: $("#get-invoice-plus-data").val(),
            order_id: $("#get-invoice-plus-data").data("value"),
            get_invoice: true,
            _ajax_nonce: payplus_script_admin.payplusCustomAction,
        };

        $.post(ajaxurl, data, function (response) {
            loader.fadeOut();
            location.reload();
        });
    });

    $(document).on("click", "#payment-payplus-transaction", function (event) {
        event.preventDefault();
        let orderId = $(this).attr("data-id");
        $(".payplus_loader").fadeIn();
        $.ajax({
            type: "post",
            dataType: "json",
            url: payplus_script_admin.ajax_url,
            data: {
                action: "payment-payplus-transaction-review",
                order_id: orderId,
                _ajax_nonce: payplus_script_admin.payplusTransactionReview,
            },
            success: function (response) {
                $("#payment-payplus-dashboard,.payplus_loader").fadeOut();
                if (response.status) {
                    location.href = response.urlredirect;
                }
            },
        });
    });

    $(document).on(
        "click",
        "#payment-payplus-dashboard, #payment-payplus-dashboard-emv",
        function (event) {
            event.preventDefault();
            let orderId = $(this).attr("data-id");
            let $this = $(this);
            $this
                .parent(".payment-order-ajax")
                .find(".payplus_loader")
                .fadeIn();
            $.ajax({
                type: "post",
                dataType: "json",
                url: payplus_script_admin.ajax_url,
                data: {
                    action: "generate-link-payment",
                    order_id: orderId,
                    button: $this.attr("id"),
                    _ajax_nonce:
                        payplus_script_admin.payplusGenerateLinkPayment,
                },
                success: function (response) {
                    console.log(response);
                    if ($this.attr("id") === "payment-payplus-dashboard-emv") {
                        //for device transaction.
                        const parsedResponse = JSON.parse(response.body);
                        if (
                            parsedResponse?.results?.status === "success" &&
                            parsedResponse?.results?.code === 0
                        ) {
                            location.reload();
                        } else if (
                            parsedResponse?.results?.status === "error" &&
                            parsedResponse?.results?.code === 1
                        ) {
                            alert(parsedResponse?.results?.description);
                            $this
                                .parent(".payment-order-ajax")
                                .find(".payplus_loader")
                                .fadeOut();
                        }
                        //for device transaction.
                    } else {
                        $("#box-payplus-payment").fadeIn();
                        $this
                            .parent(".payment-order-ajax")
                            .find(".payplus_loader")
                            .fadeOut();

                        if (response.status) {
                            $this.fadeOut();
                            $("#box-payplus-payment iframe").attr(
                                "src",
                                response.payment_response
                            );
                        } else {
                            $("#box-payplus-payment").text(
                                response.payment_response
                            );
                        }
                    }
                },
            });
        }
    );
    $("#order-payment-payplus").click(function (event) {
        event.preventDefault();
        let orderId = $(this).attr("data-id");
        $(".payplus_loader").fadeIn();
        $.ajax({
            type: "post",
            dataType: "json",
            url: payplus_script_admin.ajax_url,
            data: {
                action: "payplus-api-payment",
                order_id: orderId,
                _ajax_nonce: payplus_script_admin.payplusApiPayment,
            },
            success: function (response) {
                $(".payplus_loader").fadeOut();
                //if(response.status){
                location.href = response.urlredirect;
                // }
            },
        });
    });

    $("#payplus-token-payment").click(function (event) {
        event.preventDefault();
        let payplusChargeAmount = $(this)
                .closest(".delayed-payment")
                .find("#payplus_charge_amount")
                .val(),
            payplusOrderId = $(this)
                .closest(".delayed-payment")
                .find("#payplus_order_id")
                .val();
        $(this).closest(".delayed-payment").find(".payplus_loader").fadeIn();
        $("#payplus-token-payment").prop("disabled", true);
        $.ajax({
            type: "post",
            dataType: "json",
            url: payplus_script_admin.ajax_url,
            data: {
                action: "payplus-token-payment",
                payplus_charge_amount: payplusChargeAmount,
                payplus_order_id: payplusOrderId,
                payplus_token_payment: true,
                _ajax_nonce: payplus_script_admin.payplusTokenPayment,
            },
            beforeSend: function () {
                const targetNode = document.querySelector(".payplus_error");
                const observer = new MutationObserver(
                    (mutationsList, observer) => {
                        for (let mutation of mutationsList) {
                            if (
                                mutation.type === "attributes" &&
                                mutation.attributeName === "style"
                            ) {
                                if (targetNode.style.display !== "none") {
                                } else {
                                    $(".payplus_loader").fadeOut();
                                }
                            }
                        }
                    }
                );
                const config = { attributes: true, attributeFilter: ["style"] };
                observer.observe(targetNode, config);
            },
            success: function (response) {
                $(this)
                    .closest(".delayed-payment")
                    .find(".payplus_loader")
                    .fadeOut();
                if (!response.status) {
                    $(".payplus_error")
                        .html(payplus_script_admin.error_payment)
                        .fadeIn(function () {
                            setTimeout(function () {
                                $("#payplus-token-payment").prop(
                                    "disabled",
                                    false
                                );
                                $(".payplus_error").fadeOut("fast");
                                $("#payplus_charge_amount").val(
                                    $("#payplus_charge_amount").attr(
                                        "data-amount"
                                    )
                                );
                            }, 1000);
                        });
                } else {
                    location.href = response.urlredirect;
                }
            },
            error: function (xhr, status, error) {
                let errorMessage = xhr.responseText.split("&error=")[1]
                    ? xhr.responseText.split("&error=")[1]
                    : "Failed, please check the order notes for the failure reason.";
                alert(errorMessage);
                // location.reload();
            },
        });
    });
});
function setFieldReadOnly() {
    const arrNameFieldReadOnly = jQuery("#postcustom input[type='text']");

    for (let i = 0; i < arrNameFieldReadOnly.length; i++) {
        const metaName = jQuery(arrNameFieldReadOnly[i]).attr("id");
        const metaValue = metaName.replace("key", "value");
        if (
            jQuery("#" + metaName)
                .val()
                .indexOf("payplus") != -1
        ) {
            const father = metaName.replace("-key", "");
            jQuery("#" + metaName).prop("disabled", true);
            jQuery("#" + metaValue).prop("disabled", true);
            jQuery("#" + father)
                .find(".button")
                .prop("disabled", true);
        }
    }

    const metaName = jQuery("#postcustom input[value='order_validated']").attr(
        "id"
    );

    if (typeof metaName != "undefined") {
        const metaValue = metaName.replace("key", "value");
        const father = metaName.replace("-key", "");
        jQuery("#" + metaName).prop("disabled", true);
        jQuery("#" + metaValue).prop("disabled", true);
        jQuery("#" + father)
            .find(".button")
            .prop("disabled", true);
    }
}
function payPlusSumRefund() {
    const arrRefundAmount = ["refund_amount"];
    let sum = 0;
    const isEmpty = (str) => !str?.length;
    for (let i = 0; i < arrRefundAmount.length; i++) {
        if (!isEmpty(jQuery("#" + arrRefundAmount[i]).val())) {
            sum += parseFloat(jQuery("#" + arrRefundAmount[i]).val());
        }
    }
    return sum.toFixed(2);
}

var $saveButton = jQuery('button[name="save"]');
var $specificDiv = jQuery("#settingsContainer");

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);
const section = urlParams.get("section");
let currentLanguage = payplus_script_admin.currentLanguage.substring(0, 2);

if (currentLanguage !== "en" && currentLanguage !== "he") {
    currentLanguage = "en";
}

if (
    section == "payplus-invoice" ||
    section == "payplus-payment-gateway-setup-wizard" ||
    section == "payplus-express-checkout" ||
    section == "payplus-error-setting" ||
    section == "payplus-payment-gateway"
) {
    jQuery(window).on("scroll", function () {
        var offset = $specificDiv.offset();
        var scrollTop = jQuery(window).scrollTop();
        let sideAmount = "2%";
        if (
            jQuery(".right-tab-section-payplus").length &&
            jQuery(window).width() > 1400 &&
            jQuery(".right-tab-section-payplus").css("display") !== "none"
        ) {
            sideAmount = "37.5%";
        }
        let side = "right";
        if (jQuery("body").hasClass("rtl")) {
            side = "left";
        }

        if (scrollTop >= offset?.top) {
            $saveButton.css({
                position: "fixed",
                top: "80%",
            });
            $saveButton.css(side, sideAmount);
        } else {
            $saveButton.css({
                position: "fixed",
                top: "80%",
            });
            $saveButton.css(side, sideAmount);
        }
    });

    function showDevs() {
        jQuery("#woocommerce_payplus-payment-gateway_dev_api_key")
            .closest("tr")
            .show();
        jQuery("#woocommerce_payplus-payment-gateway_dev_secret_key")
            .closest("tr")
            .show();
        jQuery("#woocommerce_payplus-payment-gateway_dev_payment_page_id")
            .closest("tr")
            .show();
        jQuery("#woocommerce_payplus-payment-gateway_dev_device_uid")
            .closest("tr")
            .show();
        jQuery("#woocommerce_payplus-payment-gateway_dev_secret_key")
            .closest("tr")
            .find("label")
            .css({ color: "#34aa54" });
        jQuery("#woocommerce_payplus-payment-gateway_dev_api_key")
            .closest("tr")
            .find("label")
            .css({ color: "#34aa54" });
        jQuery("#woocommerce_payplus-payment-gateway_dev_payment_page_id")
            .closest("tr")
            .find("label")
            .css({ color: "#34aa54" });
        jQuery("#woocommerce_payplus-payment-gateway_dev_device_uid")
            .closest("tr")
            .find("label")
            .css({ color: "#34aa54" });
        jQuery("#woocommerce_payplus-payment-gateway_api_key")
            .closest("tr")
            .hide();
        jQuery("#woocommerce_payplus-payment-gateway_secret_key")
            .closest("tr")
            .hide();
        jQuery("#woocommerce_payplus-payment-gateway_payment_page_id")
            .closest("tr")
            .hide();
        jQuery("#woocommerce_payplus-payment-gateway_device_uid")
            .closest("tr")
            .hide();
    }

    function showProds() {
        jQuery("#woocommerce_payplus-payment-gateway_dev_api_key")
            .closest("tr")
            .hide();
        jQuery("#woocommerce_payplus-payment-gateway_dev_secret_key")
            .closest("tr")
            .hide();
        jQuery("#woocommerce_payplus-payment-gateway_dev_payment_page_id")
            .closest("tr")
            .hide();
        jQuery("#woocommerce_payplus-payment-gateway_dev_device_uid")
            .closest("tr")
            .hide();
        jQuery("#woocommerce_payplus-payment-gateway_api_key")
            .closest("tr")
            .show();
        jQuery("#woocommerce_payplus-payment-gateway_secret_key")
            .closest("tr")
            .show();
        jQuery("#woocommerce_payplus-payment-gateway_payment_page_id")
            .closest("tr")
            .show();
        jQuery("#woocommerce_payplus-payment-gateway_device_uid")
            .closest("tr")
            .show();
        jQuery("#woocommerce_payplus-payment-gateway_secret_key")
            .closest("tr")
            .find("label")
            .css({ color: "#34aa54" });
        jQuery("#woocommerce_payplus-payment-gateway_api_key")
            .closest("tr")
            .find("label")
            .css({ color: "#34aa54" });
        jQuery("#woocommerce_payplus-payment-gateway_payment_page_id")
            .closest("tr")
            .find("label")
            .css({ color: "#34aa54" });
        jQuery("#woocommerce_payplus-payment-gateway_device_uid")
            .closest("tr")
            .find("label")
            .css({ color: "#34aa54" });
    }

    var brandUidParentTr = jQuery(
        "#payplus_invoice_option\\[payplus_invoice_brand_uid\\]"
    ).closest("tr");
    var brandUidDevParentTr = jQuery(
        "#payplus_invoice_option\\[payplus_invoice_brand_uid_sandbox\\]"
    ).closest("tr");

    if (payplus_script_admin.testMode === "yes") {
        brandUidParentTr.hide();
    } else {
        brandUidDevParentTr.hide();
    }

    jQuery("#woocommerce_payplus-payment-gateway_api_test_mode").change(
        function () {
            if (
                jQuery(
                    "#woocommerce_payplus-payment-gateway_api_test_mode"
                ).val() === "yes"
            ) {
                showDevs();
            } else if (
                jQuery(
                    "#woocommerce_payplus-payment-gateway_api_test_mode"
                ).val() === "no"
            ) {
                showProds();
            }
        }
    );

    let currentMode;
    let modeMessage = [];
    // display  Add Apple Pay Script to iframe button
    // const isApplePayEnabled = payplus_script_admin.isApplePayEnabled;

    // isApplePayEnabled
    //   ? jQuery("#woocommerce_payplus-payment-gateway_import_applepay_script")
    //       .closest("tr")
    //       .fadeIn()
    //   : jQuery("#woocommerce_payplus-payment-gateway_import_applepay_script")
    //       .closest("tr")
    //       .fadeOut();
    // display allow amount change button
    Number(
        jQuery("#woocommerce_payplus-payment-gateway_transaction_type").val()
    ) !== 2
        ? jQuery(
              "#woocommerce_payplus-payment-gateway_check_amount_authorization"
          )
              .closest("tr")
              .fadeOut()
        : jQuery(
              "#woocommerce_payplus-payment-gateway_check_amount_authorization"
          )
              .closest("tr")
              .fadeIn();
    //display API mode
    if (
        jQuery("#woocommerce_payplus-payment-gateway_api_test_mode").val() ===
        "yes"
    ) {
        modeMessage["en"] = "Current Mode: Sandbox(Development) mode";
        modeMessage["he"] = "מצב נוכחי: מצב ארגז חול(פיתוח)";
        currentMode = jQuery(
            "<tr><td style='color: red;' id='currentMode'>" +
                modeMessage[currentLanguage] +
                "</td></tr></tr>"
        );
        showDevs();
    } else {
        if (
            jQuery(
                "#woocommerce_payplus-payment-gateway_api_test_mode"
            ).val() === "no"
        ) {
            modeMessage["en"] = "Current Mode: Production mode";
            modeMessage["he"] = "מצב נוכחי: מצב ייצור";
            currentMode = jQuery(
                "<tr><td id='currentMode'>" +
                    modeMessage[currentLanguage] +
                    "</td></tr></tr>"
            );
            showProds();
        }
    }
    var $firstInputWithId = jQuery("#mainform input[id]").first();
    $firstInputWithId.closest("tr").before(currentMode);
}

/**
 * Function to create, update, and set the designed admin settings.
 *
 * @param {string} paramName - Description of the parameter.
 * @returns {void} Description of the return value.
 */
function payplusMenusDisplay() {
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    const section = urlParams.get("section");
    const transactionType = payplus_script_admin.payplusTransactionType;
    const $checkAmountAuthorization = jQuery(
        "#woocommerce_payplus-payment-gateway_check_amount_authorization"
    );
    const $transactionType = jQuery(
        "#woocommerce_payplus-payment-gateway_transaction_type"
    );
    $transactionType.change(function (e) {
        Number(e.target.value) === 2
            ? $checkAmountAuthorization.closest("tr").fadeIn()
            : $checkAmountAuthorization.closest("tr").fadeOut();
    });

    if (
        section === "payplus-payment-gateway-multipass" &&
        Number(transactionType) === 2
    ) {
        let message = [];
        message["en"] =
            "Authorization Mode On - MULTIPASS will not be displayed on the PayPlus payment page!";
        message["he"] =
            "מצב תפיסת מסגרת מופעל - מולטיפס לא יופיע בעמוד החיוב של פייפלוס!";

        let transactionTypeMessage;
        var $firstInputWithId = jQuery("#mainform input[id]").first();

        transactionTypeMessage = jQuery(
            "<tr><td id='warningMessage'>" +
                message[currentLanguage] +
                "</td></tr></tr>"
        );
        $firstInputWithId.closest("tr").before(transactionTypeMessage);
    }
    const $selectCards = document.querySelector(
        "#woocommerce_payplus-payment-gateway_settings\\[cards\\]"
    )?.parentElement?.parentElement?.parentElement?.parentElement;
    const $checkOutPageOptions = document.querySelector(
        "#woocommerce_payplus-payment-gateway_hide_icon"
    )?.parentElement?.parentElement?.parentElement?.parentElement
        ?.parentElement;

    $checkOutPageOptions?.append($selectCards);
    if (
        section == "payplus-invoice" ||
        section == "payplus-payment-gateway-setup-wizard" ||
        section == "payplus-express-checkout" ||
        section == "payplus-error-setting" ||
        section == "payplus-payment-gateway-hostedfields" ||
        section == "payplus-payment-gateway-multipass"
    ) {
        //don't display on multipass but use the other display settings ...:)
        //this displays the top 3 payplus settings invoice settings and express checkout tabs - above the subgateways...
        //next if is not to display twice because we already loaded it.
        if (
            section !== "payplus-payment-gateway-multipass" &&
            section !== "payplus-payment-gateway-hostedfields"
        ) {
            jQuery(".wrap.woocommerce")
                .find("h1")
                .before(payplus_script_admin.menu_option);
        }

        const formTables = jQuery(".wrap.woocommerce").find(".form-table");
        formTables.each(function () {
            if (this.innerHTML.trim().length === 0) {
                this.style.display = "none";
            }
        });

        let classes = [
            ".payplus-api",
            ".payplus-languages-class",
            ".payplus-documents",
            ".payplus-vat",
            ".payplus-display",
            ".payplus-notifications",
        ];
        let headLines = [];

        headLines[".payplus-api"] =
            currentLanguage === "he" ? "הגדרות API" : "Api Settings";
        headLines[".payplus-languages-class"] =
            currentLanguage === "he" ? "הגדרות שפה" : "Language Settings";
        headLines[".payplus-documents"] =
            currentLanguage === "he" ? "הגדרות מסמכים" : "Document Settings";
        headLines[".payplus-vat"] =
            currentLanguage === "he" ? 'הגדרות מע"מ' : "VAT Settings";
        headLines[".payplus-display"] =
            currentLanguage === "he" ? "הגדרות תצוגה" : "Display Settings";
        headLines[".payplus-notifications"] =
            currentLanguage === "he" ? "התראות" : "Notifications";

        let tables = {};
        //Create settingsContainer
        let translated = [];

        translated["iframeHeadline"] = [];
        translated["iframeHeadline"]["he"] = "פייפלוס שאלות ותשובות";
        translated["iframeHeadline"]["en"] = "PayPlus FAQ";

        const iframeHeadline = translated["iframeHeadline"][currentLanguage];

        const iframes = [];
        iframes["payplus-invoice"] =
            '<iframe height="97%" width="80%" src="https://www.payplus.co.il/faq/%D7%97%D7%A9%D7%91%D7%95%D7%A0%D7%99%D7%AA-/%D7%94%D7%AA%D7%9E%D7%9E%D7%A9%D7%A7%D7%95%D7%AA-%D7%9C%D7%97%D7%A0%D7%95%D7%99%D7%95%D7%AA-%D7%90%D7%99%D7%A0%D7%98%D7%A8%D7%A0%D7%98%D7%99%D7%95%D7%AA/%D7%97%D7%99%D7%91%D7%95%D7%A8-%D7%97%D7%A9%D7%91%D7%95%D7%A0%D7%99%D7%AA--%D7%9C%D7%97%D7%A0%D7%95%D7%AA-WooCommerce"></iframe>';
        iframes["payplus-faq"] =
            '<iframe height="97%" width="80%" src="https://www.payplus.co.il/faq/"></iframe>';

        let iframeToShow =
            section === "payplus-invoice"
                ? iframes["payplus-invoice"]
                : iframes["payplus-faq"];
        let $settingsContainer = jQuery(
            '<div id="settingsContainer"><div class="tab-section-payplus" id="tab-payplus-gateway"></div><div class="right-tab-section-payplus hideIt"><h2>' +
                iframeHeadline +
                "</h2></div>"
        );
        //Add all existing tables to .tab-section-payplus
        $settingsContainer
            .find(".tab-section-payplus")
            .append(jQuery(".form-table"));
        jQuery("#mainform").children().last().before($settingsContainer);
        jQuery(".right-tab-section-payplus").css({
            padding: "0 0 5% 0",
            display: "block",
            textAlign: "center",
        });

        if (section === "payplus-invoice") {
            let generalSettings =
                currentLanguage === "he" ? "הגדרות כלליות" : "General Settings";
            var headline = "<h2>" + generalSettings + "</h2>";
            jQuery(".tab-section-payplus").prepend(headline);
        }

        if ($settingsContainer.height() < 300) {
            jQuery(".right-tab-section-payplus").css("display", "none");
        }

        if (jQuery.inArray(section, ["payplus-invoice"]) >= 0) {
            for (let i = 0; i < classes.length; i++) {
                let $thead = jQuery("<thead></thead>");
                let $headerRow = jQuery("<tr></tr>");
                let $tbody = jQuery("<tbody></tbody>");
                $tbody.css("width", "100%");
                $thead.append($headerRow);

                let $movableElements = jQuery(classes[i]).parent().parent();
                $movableElements.each(function (e) {
                    if (
                        $movableElements[e].tagName.toLowerCase() === "fieldset"
                    ) {
                        $movableElements[e] = jQuery($movableElements[e])
                            .parent()
                            .parent()[0];
                    }
                });

                $movableElements.detach();
                let $table = jQuery("<table></table>").addClass("form-table");
                $table.css("margin-top", "10px");

                // if (jQuery(window).width() > 1400) {
                //   jQuery(".form-table").css("width", "60%");
                // }
                $table.append($thead);
                $table.append($tbody);

                $movableElements.appendTo($table);
                if (classes[i] !== ".payplus-api") {
                    //add the fixed tables to .tab-section-payplus
                    $settingsContainer
                        .find(".tab-section-payplus")
                        .append("<h2>" + headLines[classes[i]] + "</h2>");
                    $settingsContainer
                        .find(".tab-section-payplus")
                        .append($table);
                }
            }
            jQuery("h2").css("color", "#34aa54");
        }
    }
}
