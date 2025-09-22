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
    .SetGooglePay("#googlePayButton")
    .SetApplePay("#applePayButton")
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

    message =
        payplus_script_hosted.allErrors.Errors[message] != null
            ? payplus_script_hosted.allErrors.Errors[message]
            : message;
    code =
        payplus_script_hosted.allErrors.Fields[code] != null
            ? payplus_script_hosted.allErrors.Fields[code]
            : code;

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

    const isCheckout = !document.querySelector(
        'div[data-block-name="woocommerce/checkout"]'
    )
        ? false
        : true;
    isCheckout
        ? alert(errorMessage.innerText + "\n" + errorCode.innerText)
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

    if (window.innerWidth < 768) {
        // CSS to inject
        const css = `
            .iframe-wrapper {
                width: 100%;
                max-width: 100%;
                overflow: hidden;
                position: relative;
            }
            .hsted-Flds--r-secure3ds-iframe {
                transform: scale(0.65);
                transform-origin: top left;
                width: 150% !important;
                height: 500px;
                border: none;
                left: 5px !important;
                top: 25% !important;
                ${pageLang === "he-IL" || document.dir === "rtl" ? "right: unset !important;" : ""}
            }
        `;

        // Inject the CSS
        jQuery('<style>').text(css).appendTo('head');

        // Create the observer
        const observer = new MutationObserver(function (mutationsList) {
            jQuery('.hsted-Flds--r-secure3ds-iframe').each(function () {
                const $iframe = jQuery(this);
                if (!$iframe.parent().hasClass('iframe-wrapper')) {
                    $iframe.wrap('<div class="iframe-wrapper"></div>');
                }
            });
        });

        // Start observing the body for added nodes
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        console.log("MutationObserver started");
    }

    const isCheckout = !document.querySelector(
        'div[data-block-name="woocommerce/checkout"]'
    )
        ? false
        : true;

    pageLang === "he-IL" && window.innerWidth < 400
        ? jQuery("#payments-wrapper").css("direction", "ltr")
        : null;

    if (
        isCheckout ||
        (!isCheckout && !payplus_script_hosted.showSubmitButton)
    ) {
        jQuery("#submit-payment").css("visibility", "hidden");
        jQuery("#submit-payment").css("display", "none");
    }
    if (payplus_script_hosted.isHideLoaderLogo) {
        jQuery(".blocks-loader-text").addClass("no-image");
        if (isCheckout) {
            jQuery(".blocks-loader-text").addClass("blocks");
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

                                        if (!jQuery(".hf-save").length) {
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
                                        }

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
                    // jQuery("#submit-payment").attr(
                    //   "style",
                    //   "visibility: hidden;height: 0px !important;margin: 0 0 0 0 !important;"
                    // );
                    // jQuery("#submit-payment").next().hide();
                    //   jQuery("#id-number-wrapper").hide();
                    // jQuery("#payments-wrapper").hide();
                    jQuery("#payment-form").css("display", "flex");
                });
            } else {
                alert(resp.results.message);
                location.reload();
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
    console.log(e);
    // jQuery("#submit-payment").prop("disabled", true);
    jQuery("#status").val("Page Expired");
    jQuery.ajax({
        type: "post",
        dataType: "json",
        url: payplus_script_hosted.ajax_url,
        data: {
            action: "regenerate-hosted-link",
            _ajax_nonce: payplus_script_hosted.frontNonce,
        },
        success: function (response) {
            console.log(response);
        },
        error: function (xhr, status, error) {
            console.log(xhr, status, error);
        },
    });
    const popup = document.createElement("div");
    popup.style.position = "fixed";
    popup.style.top = "50%";
    popup.style.left = "50%";
    popup.style.transform = "translate(-50%, -50%)";
    popup.style.backgroundColor = "#fff";
    popup.style.padding = "20px";
    popup.style.boxShadow = "0 0 10px rgba(0, 0, 0, 0.1)";
    popup.style.zIndex = "10000";
    popup.innerHTML = `
    <p>${pageLang !== "he-IL"
            ? "Page Expired. Please refresh the page and try again."
            : "תוקף הדף פג. אנא רענן/י את הדף ונסה/י שוב."
        }</p>
    <button id="popup-ok-button">${pageLang !== "he-IL" ? "OK" : "אישור"
        }</button>
`;
    document.body.appendChild(popup);

    document.getElementById("popup-ok-button").addEventListener("click", () => {
        document.body.removeChild(popup);
        window.location.href = window.location.href; // Use this method to reload the page
    });
});

hf.Upon("pp_noAttemptedRemaining", (e) => {
    alert("No more attempts remaining");
});

hf.Upon("pp_responseFromServer", (e) => {
    console.log("response from server", e.detail);
    let r = "";
    try {
        r = JSON.stringify(e.detail, null, 2);
    } catch (error) {
        console.log("Error parsing response: ", error);
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
                                "Order completion failed(Please try again): " +
                                (final_response.message ||
                                    final_response.data.message)
                            );
                            // location.reload();
                        }
                    },
                    error: function (xhr, status, error) {
                        alert("Error completing order: " + error);
                        // location.reload();
                    },
                });
            },
            error: function (xhr, status, error) {
                console.log(xhr, status, error);
                alert("Error making hosted payment: " + error);
                // location.reload();
            },
        });
    }
});
// Track which button was clicked
let lastClickedButton = null;
let hasTriggeredPlaceOrder = false; // Flag to prevent duplicate orders

// Add click listeners to track button clicks
jQuery(document).ready(function($) {
    // Function to simulate Place Order process
    function simulatePlaceOrderProcess() {
        if (hasTriggeredPlaceOrder) {
            console.log('🚫 Place order already triggered, skipping duplicate');
            return;
        }
        
        console.log('🔄 Simulating Place Order process for Google Pay...');
        hasTriggeredPlaceOrder = true;
        
        // Find the checkout form and trigger submit - same as the submit button does
        const checkoutForm = $('form[name="checkout"]');
        if (checkoutForm.length) {
            console.log('📝 Triggering checkout form submit...');
            checkoutForm.trigger('submit');
        } else {
            console.log('❌ Checkout form not found');
            hasTriggeredPlaceOrder = false; // Reset flag if form not found
        }
    }
    
    // Track Google Pay button interactions (multiple methods for reliability)
    $(document).on('mousedown touchstart', '#googlePayButton', function(e) {
        console.log('🟡 Google Pay button interaction detected');
        lastClickedButton = 'googlePay';
        
        // Simulate the Place Order process that normally needs to be done first
        simulatePlaceOrderProcess();
    });
    
    // Fallback: detect when user interacts with the iframe
    $(document).on('mouseenter', '#googlePayButton', function() {
        if (!lastClickedButton && !hasTriggeredPlaceOrder) {
            console.log('🟡 Google Pay button hover - preparing for interaction');
            lastClickedButton = 'googlePay';
        }
    });
    
    // Track Apple Pay button clicks  
    $(document).on('click', '#applePayButton', function() {
        console.log('🍎 Apple Pay button clicked');
        lastClickedButton = 'applePay';
    });
    
    // Track Place Order button clicks (regular submit)
    $(document).on('click', '#submit-payment', function() {
        console.log('📋 Place Order button clicked');
        lastClickedButton = 'placeOrder';
        // Do NOT set lastClickedButton or hasTriggeredPlaceOrder here
        // Let WooCommerce handle the normal flow
    });

    $(document).on('click', '#place_order', function() {
        console.log('📋 Place Order button clicked (classic)')
        lastClickedButton = 'placeOrder';
        // Do NOT set lastClickedButton or hasTriggeredPlaceOrder here
        // Let WooCommerce handle the normal flow
    });   
    // Reset flags when payment method changes
    $(document).on('change', 'input[name="payment_method"]', function() {
        console.log('💳 Payment method changed, resetting flags');
        lastClickedButton = null;
        hasTriggeredPlaceOrder = false;
    });
    
    // Reset flags on page unload to handle page refreshes
    $(window).on('beforeunload', function() {
        hasTriggeredPlaceOrder = false;
    });
});

hf.Upon("pp_submitProcess", (e) => {
    console.log('submitting!', e);
    
    // Detect which button triggered the submission
    let shouldSimulatePlaceOrder = false;
    switch(lastClickedButton) {
        case 'googlePay':
            console.log('🟡 Submission triggered by Google Pay button');
            shouldSimulatePlaceOrder = true;
            break;
        case 'applePay':
            console.log('🍎 Submission triggered by Apple Pay button');
            shouldSimulatePlaceOrder = true;
            break;
        case 'placeOrder':
            console.log('📋 Submission triggered by Place Order button');
            shouldSimulatePlaceOrder = false; // Explicitly do NOT simulate for regular Place Order
            break;
        default:
            console.log('❓ Submission triggered by unknown source');
            shouldSimulatePlaceOrder = true;
    }
    if (shouldSimulatePlaceOrder && !hasTriggeredPlaceOrder) {
        // Only hide error message for Google Pay, Apple Pay, or unknown sources
        switch(lastClickedButton) {
            case 'placeOrder':
                // Do nothing, let normal flow happen
                break;
            case 'googlePay':
            case 'applePay':
            default:
                if (lastClickedButton !== 'placeOrder') {
                    console.log('🟡 Handling non-Place Order submission, simulating Place Order process...CHANGING CLASS & HIDING LOADER!');
                    const $errorMsg = jQuery('.payment-error-message');
                    const $loader = jQuery('.blocks-payplus_loader_hosted');
                    $loader.length ? $loader.fadeIn() : null;
                    overlay(true);
                    if ($errorMsg.length) {
                        console.log("GELLELLELELLELELE!")
                        $errorMsg.hide();
                        $errorMsg.removeClass('payment-error-message').addClass('payment-error-message-hidden');
                        // if ($loader.length) {
                        //     $loader.fadeIn();
                        // }
                        setTimeout(function() {
                        //     console.log('🟡 Restoring error message visibility and showing loader after delay...');
                            jQuery('.payment-error-message-hidden').removeClass('payment-error-message-hidden').addClass('payment-error-message');
                        //     if ($loader.length) {
                        //         $loader.fadeOut();
                        //     }
                        }, 5000);
                    }
                }
        }
        // Simulate the Place Order process for Google Pay, Apple Pay, or unknown sources, only once
        const checkoutForm = jQuery('form[name="checkout"]');
        if (checkoutForm.length) {
            console.log('📝 [pp_submitProcess] Triggering checkout form submit for non-Place Order source...');
            checkoutForm.trigger('submit');
            hasTriggeredPlaceOrder = true;
        } else {
            console.log('❌ [pp_submitProcess] Checkout form not found');
        }
    }
    // jQuery(".blocks-payplus_loader_hosted").fadeIn();
    // overlay();
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
