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
        "; background-image: url(\"data:image/svg+xml,%3Csvg width='14' height='16' viewBox='0 0 14 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M3.66667 7.33398V4.66732C3.66667 3.78326 4.01786 2.93542 4.64298 2.3103C5.2681 1.68517 6.11595 1.33398 7 1.33398C7.88406 1.33398 8.7319 1.68517 9.35702 2.3103C9.98214 2.93542 10.3333 3.78326 10.3333 4.66732V7.33398M2.33333 7.33398H11.6667C12.403 7.33398 13 7.93094 13 8.66732V13.334C13 14.0704 12.403 14.6673 11.6667 14.6673H2.33333C1.59695 14.6673 1 14.0704 1 13.334V8.66732C1 7.93094 1.59695 7.33398 2.33333 7.33398Z' stroke='%23A2ADB5' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E\");background-repeat: no-repeat;background-position: " +
        opposite +
        " center;} .hf-inp-name-cvv {font-size:1rem !important;text-align: " +
        direction +
        "; background-image: url(\"data:image/svg+xml,%3Csvg width='19' height='13' viewBox='0 0 19 13' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M5.14762 7.56069C5.20367 7.56069 5.25968 7.55747 5.31532 7.55061C5.35761 7.54536 5.40645 7.54193 5.44447 7.52124C5.52424 7.4777 5.52511 7.39831 5.51259 7.32024C5.49787 7.22874 5.4176 7.18165 5.32925 7.19309C5.23555 7.20528 5.13572 7.20247 5.05102 7.15637C4.88601 7.06673 4.84389 6.83664 4.91781 6.67435C4.96213 6.57703 5.05168 6.51259 5.15678 6.49462C5.21113 6.48532 5.26806 6.48788 5.32245 6.49495C5.37589 6.50189 5.42747 6.49239 5.46628 6.45157C5.5125 6.40271 5.53278 6.29824 5.50595 6.2371C5.45836 6.12813 5.30545 6.13284 5.20504 6.13284C5.02573 6.13284 4.84293 6.17692 4.69675 6.28382C4.41756 6.48792 4.34338 6.89902 4.49546 7.20271C4.61843 7.44841 4.88195 7.56069 5.14762 7.56069Z' fill='%23A2ADB5'/%3E%3Cpath d='M6.03265 7.43683C6.08149 7.57083 6.26851 7.53469 6.38029 7.53469C6.45197 7.53469 6.51934 7.51085 6.54663 7.43774C6.55255 7.42369 6.55732 7.40924 6.56267 7.39503C6.64414 7.177 6.72561 6.9589 6.80708 6.74092C6.84489 6.63967 6.8827 6.53843 6.92059 6.43714C6.95421 6.34709 6.98805 6.2389 6.87677 6.18103C6.82391 6.15348 6.75144 6.16414 6.69368 6.16414C6.61839 6.16414 6.54567 6.18281 6.51673 6.26191C6.48531 6.34771 6.46661 6.44007 6.44215 6.52798C6.39381 6.70168 6.34463 6.87438 6.30765 7.05093C6.25695 6.80106 6.17428 6.55615 6.10292 6.31165C6.09036 6.2686 6.07913 6.22734 6.04185 6.19727C5.98995 6.15538 5.92249 6.1641 5.8603 6.1641C5.79388 6.1641 5.72498 6.15678 5.6739 6.20896C5.62962 6.25423 5.62344 6.31669 5.64434 6.3739C5.77378 6.72824 5.90325 7.08249 6.03265 7.43683Z' fill='%23A2ADB5'/%3E%3Cpath d='M7.47991 7.43683C7.52892 7.57079 7.71578 7.53469 7.82763 7.53469C7.89932 7.53469 7.96669 7.51085 7.99389 7.43774C7.99982 7.42369 8.00458 7.40924 8.00993 7.39503C8.0914 7.177 8.17287 6.9589 8.25434 6.74092C8.29215 6.63967 8.32996 6.53843 8.36785 6.43714C8.40148 6.34709 8.43531 6.23886 8.32411 6.18103C8.27125 6.15348 8.19878 6.16414 8.14094 6.16414C8.06565 6.16414 7.99302 6.18277 7.96408 6.26191C7.93265 6.34775 7.91395 6.44007 7.88941 6.52798C7.84107 6.70168 7.79181 6.87438 7.755 7.05093C7.70404 6.8011 7.62162 6.55623 7.55027 6.31165C7.53771 6.26856 7.52639 6.22734 7.48912 6.19727C7.43729 6.15538 7.36975 6.1641 7.30756 6.1641C7.24115 6.1641 7.17232 6.15678 7.12124 6.20896C7.07688 6.25431 7.07071 6.3166 7.0916 6.3739C7.22112 6.72824 7.35052 7.08249 7.47991 7.43683Z' fill='%23A2ADB5'/%3E%3Cpath d='M6.39416 11.0394C7.28985 11.0394 8.1229 10.767 8.81428 10.301H16.9497C17.4548 10.301 17.8656 9.89177 17.8656 9.38852V4.4657H10.0812C9.65363 3.77566 9.0365 3.2142 8.30275 2.85367L17.8655 2.85433V2.04271C17.8655 1.5395 17.4547 1.13022 16.9497 1.13022H3.89878C3.3938 1.13022 2.98294 1.5395 2.98294 2.04271V2.85329L4.48618 2.85338C3.05284 3.55739 2.06419 5.0284 2.06419 6.72532C2.06419 7.33494 2.19123 7.92841 2.43604 8.4759L1.84856 9.02001V2.04271C1.84856 0.916331 2.7683 0 3.89878 0H16.9497C18.0803 0 19 0.916372 19 2.04271V9.38856C19 10.5149 18.0803 11.4313 16.9497 11.4313H3.93104L4.7174 10.703C5.24552 10.9243 5.81438 11.0394 6.39416 11.0394Z' fill='%23A2ADB5'/%3E%3Cpath d='M0.237279 11.7196L3.28261 8.89918C2.8516 8.28407 2.59811 7.53648 2.59811 6.72953C2.59811 4.63316 4.30389 2.93359 6.40796 2.93359C8.51204 2.93359 10.2178 4.63312 10.2178 6.72953C10.2178 8.8259 8.51204 10.5253 6.40796 10.5253C5.65191 10.5253 4.94796 10.3051 4.35538 9.92663L1.24881 12.8039C1.10573 12.9364 0.924265 13.002 0.743211 13.002C0.543915 13.002 0.345199 12.9226 0.198971 12.7657C-0.0803843 12.4662 -0.06322 11.9978 0.237279 11.7196ZM6.40796 4.22285C5.02077 4.22285 3.89219 5.34737 3.89219 6.72953C3.89219 8.11165 5.02077 9.23609 6.40796 9.23609C7.79516 9.23609 8.92374 8.11165 8.92374 6.72953C8.92374 5.34737 7.79516 4.22285 6.40796 4.22285Z' fill='%23A2ADB5'/%3E%3C/svg%3E\");background-repeat: no-repeat;background-position: " +
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

function resetPlaceOrderButton() {
    // Reset the place order button state
    jQuery("#submit-payment").prop("disabled", false);
    jQuery("#submit-payment .button-loader").css("display", "none");
    jQuery(".payplus-hosted-place-order").prop("disabled", false).css("opacity", "1");
    jQuery(".payplus-hosted-place-order .button-loader").css("display", "none");
}

function showError(message, code) {
    // Hide loader on the hosted fields place order buttons
    resetPlaceOrderButton();
    
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
    
    // After error countdown completes, ensure button is reset
    setTimeout(() => {
        resetPlaceOrderButton();
    }, 6000); // After the 5-second countdown + 1 second buffer
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
        var processingText = payplus_script_hosted.processingText || 'Processing Payment';
        var isRtl = !!payplus_script_hosted.isRtl;
        var fontFamily = isRtl
            ? "'AlmoniMLv5AAA', Arial, sans-serif"
            : "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif";
        var svgNS = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('xmlns', svgNS);
        svg.setAttribute('viewBox', '0 0 120 28');
        svg.setAttribute('width', '120');
        svg.setAttribute('height', '26');
        svg.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);' +
                            'display:block;overflow:visible;pointer-events:none;';

        var text = document.createElementNS(svgNS, 'text');
        text.setAttribute('x', '50%');
        text.setAttribute('y', '50%');
        text.setAttribute('text-anchor', 'middle');
        text.setAttribute('dominant-baseline', 'middle');
        text.setAttribute('font-family', fontFamily);
        text.setAttribute('font-size', '12');
        text.setAttribute('font-weight', '600');
        text.setAttribute('fill', '#ffffff');
        text.setAttribute('stroke', '#333333');
        text.setAttribute('stroke-width', '2.5');
        text.setAttribute('paint-order', 'stroke fill');
        if (isRtl) { text.setAttribute('direction', 'rtl'); }
        text.textContent = processingText;
        svg.appendChild(text);

        jQuery('.blocks-loader-text')
            .addClass('no-image')
            .css({ overflow: 'visible', background: 'transparent' })
            .append(svg);
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
    resetPlaceOrderButton();
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
    resetPlaceOrderButton();
    alert("No more attempts remaining");
});

hf.Upon("pp_responseFromServer", (e) => {
    // console.log("response from server", e.detail);
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
        let orderId = e.detail.data.more_info;
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
                            resetPlaceOrderButton();
                            alert(
                                "Order completion failed(Please try again): " +
                                (final_response.message ||
                                    final_response.data.message)
                            );
                            location.reload();
                        }
                    },
                    error: function (xhr, status, error) {
                        resetPlaceOrderButton();
                        alert("Error completing order: " + error);
                        location.reload();
                    },
                });
            },
            error: function (xhr, status, error) {
                resetPlaceOrderButton();
                alert("Error making hosted payment: " + error);
                location.reload();
            },
        });
    }
});
hf.Upon("pp_ccTypeChange", (e) => {
    var ccType = e.detail;
    var $cCard = jQuery("#cCard");
    if (ccType && ccType !== "" && ccType !== "unknown") {
        $cCard.addClass("validated");
    } else {
        $cCard.removeClass("validated");
    }
});

hf.Upon("pp_submitProcess", (e) => {
    // Show loader on the hosted fields place order button (classic checkout)
    jQuery("#submit-payment").prop("disabled", true);
    jQuery("#submit-payment .button-loader").css("display", "inline-block");
    
    // Show loader on the hosted fields place order button (blocks checkout)
    jQuery(".payplus-hosted-place-order").prop("disabled", true).css("opacity", "0.7");
    jQuery(".payplus-hosted-place-order .button-loader").css("display", "inline-block");
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
    // Watch for error message display and reset button immediately
    const errorMessageDiv = document.querySelector('.payment-error-message');
    if (errorMessageDiv) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    // Check if error message is now visible
                    if (errorMessageDiv.style.display !== 'none' && 
                        errorMessageDiv.style.display !== '') {
                        resetPlaceOrderButton();
                    }
                }
            });
        });
        
        observer.observe(errorMessageDiv, {
            attributes: true,
            attributeFilter: ['style']
        });
    }
    
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
