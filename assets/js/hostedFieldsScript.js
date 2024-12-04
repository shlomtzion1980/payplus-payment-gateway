const hf = new PayPlusHostedFieldsDom();
var resp = JSON.parse(payplus_script_hosted.hostedResponse);
let payload;
const pageLang = document.documentElement.lang;
const month = pageLang !== "he-IL" ? "Month" : "חודש";
const year = pageLang !== "he-IL" ? "Year" : "שנה";
const yearMonth =
    pageLang !== "he-IL" ? month + " / " + year : year + " / " + month;
const direction = pageLang !== "he-IL" ? "left" : "right";
const opposite = direction === "right" ? "left" : "right";
var origin = window.location.origin;
let viImage =
    "background-image: url(" +
    origin +
    "/wp-content/plugins/payplus-payment-gateway/assets/images/vi.svg);background-repeat: no-repeat;background-position: " +
    opposite +
    " center";
viImage = "";
const testMode = payplus_script_hosted.testMode;

hf.SetMainFields({
    cc: {
        elmSelector: "#cc",
        wrapperElmSelector: "#cc-wrapper",
        config: {
            placeholder: "1234 1234 1234 1234",
            fontName: "almoni",
        },
    },
    expiryy: {
        elmSelector: "#expiryy",
        wrapperElmSelector: ".expiry-wrapper",
        config: {
            placeholder: year,
            fontName: "almoni",
        },
    },
    expirym: {
        elmSelector: "#expirym",
        wrapperElmSelector: ".expiry-wrapper",
        config: {
            placeholder: month,
            fontName: "almoni",
        },
    },
    expiry: {
        elmSelector: "#expiry",
        wrapperElmSelector: ".expiry-wrapper-full",
        config: {
            placeholder: yearMonth,
            fontName: "almoni",
        },
    },
    cvv: {
        elmSelector: "#cvv",
        wrapperElmSelector: "#cvv-wrapper",
        config: {
            placeholder: "CVV",
            fontName: "almoni",
        },
    },
})
    .AddField("card_holder_id", "#id-number", "#id-number-wrapper")
    .AddField("payments", "#payments", "#payments-wrapper")
    .AddField(
        "card_holder_name",
        "#card-holder-name",
        "#card-holder-name-wrapper"
    )
    .AddField(
        "card_holder_phone",
        ".card-holder-phone",
        ".card-holder-phone-wrapper"
    )
    .AddField(
        "card_holder_phone_prefix",
        ".card-holder-phone-prefix",
        ".card-holder-phone-prefix-wrapper"
    )
    .AddField("customer_name", "[name=customer_name]", ".customer_name-wrapper")
    .AddField("vat_number", "[name=customer_id]", ".customer_id-wrapper")
    .AddField("phone", "[name=phone]", ".phone-wrapper")
    .AddField("email", "[name=email]", ".email-wrapper")
    .AddField("contact_address", "[name=address]", ".address-wrapper")
    .AddField("contact_country", "[name=country]", ".country-wrapper")
    .AddField("custom_invoice_name", "#invoice-name", "#invoice-name-wrapper")
    .AddField("notes", "[name=notes]", ".notes-wrapper")
    .SetRecaptcha("#recaptcha")
    .SetHostedFieldsStyles(
        "::placeholder {color: #A2ADB5;} .hf-inp-name-cc {font-size:1rem !important;text-align: " +
            direction +
            "; background-image: url(" +
            origin +
            "/wp-content/plugins/payplus-payment-gateway/assets/images/lock.svg);background-repeat: no-repeat;background-position: " +
            opposite +
            " center;} .hf-inp-name-cvv {font-size:1rem !important;text-align: " +
            direction +
            "; background-image: url(" +
            origin +
            "/wp-content/plugins/payplus-payment-gateway/assets/images/cvv.svg);background-repeat: no-repeat;background-position: " +
            opposite +
            "} .hf-inp-name-expirym {text-align: " +
            direction +
            "; font-size: 1rem} .hf-inp-name-expiryy {text-align: " +
            direction +
            "; font-size: 1rem; " +
            viImage +
            "} .hf-inp-name-expiry {text-align: " +
            direction +
            "; font-size: 1rem; " +
            viImage +
            ";background-repeat: no-repeat;background-position: " +
            opposite +
            " center;}"
    );

function putHostedFields() {
    var $paymentMethod = jQuery("#payment_method_payplus-payment-gateway");

    // Find the closest parent <li>
    var $topLi = jQuery(".pp_iframe_h");

    // Select the existing div element that you want to move
    var $newDiv = jQuery("body > div.container.hostedFields");

    if ($paymentMethod.length && $topLi.length && $newDiv.length) {
        // Move the existing div to the top <li> of the payment method
        $topLi.append($newDiv);
    }
}

function showElement(element, display) {
    element.style.display = display; // Set display to block
    element.style.opacity = "1"; // Fully visible
    element.style.transition = "opacity 1s"; // Slow transition (1 second)
    setTimeout(() => {
        element.style.opacity = "1"; // Fade in
    }, 10); // Small delay to ensure the transition applies
}

function hideElement(element) {
    element.style.opacity = "0"; // Fade out
    element.style.transition = "opacity 1s"; // Slow transition (1 second)
    setTimeout(() => {
        element.style.display = "none"; // Hide after fade-out
    }, 1000); // Match the transition duration (1s = 1000ms)
}

function showError(message, code) {
    const errorMessageDiv = document.querySelector(".payment-error-message");
    const loaderCountdown = document.querySelector(".loader-countdown");
    const circle = document.querySelector(".progress-ring__circle");
    const errorMessage = document.querySelector(".error-message");
    const errorCode = document.querySelector(".error-code");
    let countdown = 5;
    loaderCountdown.textContent = countdown;

    message = payplus_script_hosted.allErrors.Errors[message] ?? message;
    code = payplus_script_hosted.allErrors.Fields[code] ?? code;

    showElement(errorMessageDiv, "flex");

    let errorCodePrefix;
    let errorMessagePrefix = pageLang !== "en-US" ? "שגיאה: " : "Error: ";
    errorMessage.innerText = errorMessagePrefix + message;

    if (typeof code !== "string") {
        errorCodePrefix = pageLang !== "en-US" ? "קוד שגיאה: " : "Error code: ";
    } else {
        errorCodePrefix = pageLang !== "en-US" ? "שדה: " : "Field: ";
    }

    code !== null
        ? (errorCode.innerText =
              code.toString().length > 0 ? errorCodePrefix + code : code)
        : null;

    const radius = circle.r.baseVal.value;
    const circumference = 2 * Math.PI * radius;

    // Set circle circumference
    circle.style.strokeDasharray = `${circumference}`;
    circle.style.strokeDashoffset = "0";

    const updateLoader = () => {
        // Update countdown number
        loaderCountdown.textContent = countdown;

        // Calculate stroke-dashoffset for the "drain" effect
        const offset = circumference - (countdown / 5) * circumference + 15;
        circle.style.strokeDashoffset = offset;

        // If countdown is complete, hide the error message
        if (countdown === 1) {
            clearInterval(timer);
            // errorMessageDiv.style.display = "none";
            hideElement(errorMessageDiv);
        } else {
            countdown--;
        }
    };

    // Start the countdown
    const timer = setInterval(updateLoader, 1000);
}

jQuery(() => {
    const isCheckout = !document.querySelector(
        'div[data-block-name="woocommerce/checkout"]'
    )
        ? false
        : true;

    if (payplus_script_hosted.isHideLoaderLogo) {
        jQuery(".blocks-loader-text").addClass("no-image");
        if (isCheckout) {
            jQuery(".blocks-loader-text").addClass("blocks");
            // pageLang !== "he-IL"
            //   ? jQuery(".blocks-loader-text").addClass("blocks")
            //   : jQuery(".blocks-loader-text").addClass("blocks-he");
        }
    }

    // Define the async function to handle the response
    async function processResponse(resp) {
        try {
            if (resp.results.status == "success") {
                try {
                    await hf.CreatePaymentPage({
                        hosted_fields_uuid: resp.data.hosted_fields_uuid,
                        page_request_uid: resp.data.page_request_uid,
                        origin: testMode
                            ? "https://restapidev.payplus.co.il"
                            : "https://restapi.payplus.co.il",
                    });
                } catch (error) {
                    alert(error);
                }

                hf.InitPaymentPage.then((data) => {
                    if (isCheckout) {
                        console.log("checkout page (hosted)?", isCheckout);
                        jQuery(document).ready(function () {
                            let inputElement = document.querySelector(
                                "#radio-control-wc-payment-method-options-payplus-payment-gateway-hostedfields"
                            );
                            function waitForInput() {
                                inputElement = document.querySelector(
                                    "#radio-control-wc-payment-method-options-payplus-payment-gateway-hostedfields"
                                );
                                // console.log("input", inputElement);
                                if (inputElement) {
                                    // Find the closest parent div
                                    const topDiv = inputElement.closest("div");

                                    if (topDiv) {
                                        // Create a new div element
                                        const newDiv = document.querySelector(
                                            "body > div.container.hostedFields"
                                        );
                                        var $hostedDiv = jQuery(
                                            "body > div.container.hostedFields"
                                        );
                                        const paymentForm =
                                            document.querySelector(
                                                "#payment-form"
                                            );
                                        newDiv.className = "pp_iframe_h";

                                        // Append the new div to the top div
                                        topDiv.appendChild(newDiv);
                                        newDiv.style.display = "none";
                                        newDiv.style.justifyContent = "center";
                                        newDiv.style.flexDirection = "column";
                                        newDiv.style.alignItems = "center";
                                        paymentForm.style.marginBottom = "4%";
                                        paymentForm.style.width = "99%";
                                        var $checkbox = jQuery(
                                            '<p class="hf-save form-row">' +
                                                '<label for="save_token_checkbox">' +
                                                '<input type="checkbox" name="wc-save-token" id="save_token_checkbox" value="1" style="margin:0 10px 0 10px;"/>' +
                                                " " +
                                                payplus_script_hosted.saveCreditCard +
                                                "</label>" +
                                                "</p>"
                                        );

                                        payplus_script_hosted.isLoggedIn &&
                                        payplus_script_hosted.isSavingCerditCards
                                            ? $hostedDiv.append($checkbox)
                                            : null;

                                        inputElement.addEventListener(
                                            "click",
                                            (event) => {
                                                if (inputElement.checked) {
                                                    newDiv.style.display =
                                                        "flex";
                                                }
                                            }
                                        );
                                        const parent = inputElement.closest(
                                            ".wc-block-components-checkout-step__container"
                                        );

                                        if (parent) {
                                            const closestInputs =
                                                parent.querySelectorAll(
                                                    "input[type='checkbox'], input[type='radio']"
                                                );

                                            closestInputs.forEach((input) => {
                                                if (input !== inputElement) {
                                                    input.addEventListener(
                                                        "click",
                                                        (event) => {
                                                            if (
                                                                input.checked &&
                                                                event.target
                                                                    .id !==
                                                                    "save_token_checkbox"
                                                            ) {
                                                                newDiv.style.display =
                                                                    "none";
                                                            }
                                                        }
                                                    );
                                                }
                                            });
                                        }
                                    } else {
                                        console.log("No parent div found.");
                                    }
                                } else {
                                    // console.log(
                                    //     "Element with the specified ID not found."
                                    // );
                                    setTimeout(function () {
                                        waitForInput();
                                    }, 1000);
                                }
                            }
                            waitForInput();
                        });
                    }

                    // jQuery(".container.hostedFields").show();
                    jQuery("#create-payment-form").hide();
                    jQuery("#submit-payment").attr(
                        "style",
                        "visibility: hidden;height: 0px !important;margin: 0 0 0 0 !important;"
                    );
                    jQuery("#submit-payment").next().hide();
                    //   jQuery("#id-number-wrapper").hide();
                    // jQuery("#payments-wrapper").hide();
                    jQuery("#payment-form").css("display", "flex");
                });
            } else {
                alert(resp.results.message);
            }
        } catch (error) {
            jQuery("#error").append(`<div>Error:</div>`);
            jQuery("#error").append(
                `<pre>${JSON.stringify(resp, null, 2)}</pre>`
            );
        }
    }

    // Call the async function to process the response
    processResponse(resp);
});

hf.Upon("pp_pageExpired", (e) => {
    jQuery("#submit-payment").prop("disabled", true);
    jQuery("#status").val("Page Expired");
});

hf.Upon("pp_noAttemptedRemaining", (e) => {
    alert("No more attempts remaining");
});

hf.Upon("pp_responseFromServer", (e) => {
    let r = "";
    try {
        r = JSON.stringify(e.detail, null, 2);
    } catch (error) {
        r = e.detail;
    }

    let saveToken = jQuery("#save_token_checkbox").is(":checked")
        ? true
        : false;

    if (e.detail?.data?.error || e.detail?.data?.status === "reject") {
        showError(e.detail.data.message, "");
        jQuery(".blocks-payplus_loader_hosted").fadeOut();
        overlay(true);
        isCheckout ? location.reload() : null;
        return;
    }

    if (e.detail.errors) {
        let errorMessage =
            e.detail.errors[0].message === null
                ? e.detail.results.description
                : e.detail.errors[0].message;
        let errorCode =
            e.detail.errors[0].message === null
                ? e.detail.results.error_code
                : e.detail.errors[0].field;

        const ifError = (event) => {
            showError(errorMessage, errorCode);
            jQuery(".blocks-payplus_loader_hosted").fadeOut();
            overlay(true);
            return;
        };

        !["not-authorize-success", "payment-is-still-in-process"].includes(
            errorMessage
        )
            ? ifError()
            : null;
    }

    if (e.detail.data?.status_code === "000") {
        let orderId =
            typeof isCheckout !== "undefined" && isCheckout
                ? wp.data.select("wc/store/checkout").getOrderId()
                : e.detail.data.more_info;
        let token = e.detail.data.token_uid;
        let pageRequestdUid = e.detail.data.page_request_uid;
        jQuery.ajax({
            type: "post",
            dataType: "json",
            url: payplus_script_hosted.ajax_url,
            data: {
                action: "make-hosted-payment",
                order_id: orderId,
                token: token,
                saveToken: saveToken,
                page_request_uid: pageRequestdUid,
                _ajax_nonce: payplus_script_hosted.frontNonce,
            },
            success: function (response) {
                console.log("Hosted payment response: ", response);
                jQuery.ajax({
                    url: payplus_script_hosted.ajax_url,
                    type: "POST",
                    data: {
                        action: "complete_order",
                        order_id: orderId,
                        payment_response: response,
                        _ajax_nonce: payplus_script_hosted.frontNonce,
                    },
                    success: function (final_response) {
                        console.log("final response: ", final_response);
                        if (final_response.success) {
                            // Redirect to the thank you page or complete payment
                            jQuery(window).off("beforeunload");
                            window.location.href =
                                final_response.data.redirect_url;
                        } else {
                            alert(
                                "Order completion failed: " +
                                    final_response.message
                            );
                        }
                    },
                    error: function (xhr, status, error) {
                        alert("Error completing order: " + error);
                    },
                });
            },
            error: function (xhr, status, error) {
                alert("Error making hosted payment: " + error);
            },
        });
    }
});
hf.Upon("pp_submitProcess", (e) => {
    // jQuery(".blocks-payplus_loader_hosted").fadeIn();
    // overlay();
    jQuery("#submit-payment").prop("disabled", e.detail);
});

let $overlay; // Declare outside to store the overlay reference

const overlay = (remove = false) => {
    if (remove) {
        // If remove is true, remove the overlay and restore scrolling
        if ($overlay) {
            $overlay.remove();
            jQuery("body").css({
                overflow: "", // Restore scrolling
            });
            $overlay = null; // Clear the reference
        }
    } else {
        // If remove is false, create and show the overlay
        if (!$overlay) {
            $overlay = jQuery("<div></div>")
                .css({
                    position: "fixed",
                    top: 0,
                    left: 0,
                    width: "100%",
                    height: "100%",
                    backgroundColor: "rgba(255, 255, 255, 0.7)", // milky opacity
                    zIndex: 9999,
                    cursor: "not-allowed",
                })
                .appendTo("body");

            // Prevent scrolling
            jQuery("body").css({
                overflow: "hidden",
            });

            // Disallow clicks on overlay
            $overlay.on("click", function (event) {
                event.stopPropagation();
                event.preventDefault();
            });
        }
    }
};

jQuery(document).ready(function () {
    let $cardHolderNameInput = jQuery("#card-holder-name");
    $cardHolderNameInput.on("blur", function () {
        // Get the input value and trim any extra spaces
        let name = jQuery(this).val().trim();

        // Check if the name contains at least two words
        if (!/^[a-zA-Z]+ [a-zA-Z]+$/.test(name)) {
            // If validation fails, show an error message or add an error class
            jQuery(this).removeClass("validated");
        } else {
            // If validation passes, remove any error indication
            jQuery(this).addClass("validated");
        }
    });
    jQuery("#id-number").on("blur", function () {
        // Get the input value and trim any extra spaces
        let id = jQuery(this).val().trim();

        // Check if the ID contains exactly 9 digits
        if (!/^\d{9}$/.test(id)) {
            // If validation fails, show an error message or add an error class
            jQuery(this).removeClass("validated");
        } else {
            // If validation passes, remove any error indication
            jQuery(this).addClass("validated");
        }
    });
});
