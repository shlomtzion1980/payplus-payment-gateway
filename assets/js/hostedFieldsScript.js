const hf = new PayPlusHostedFieldsDom();
var resp = JSON.parse(payplus_script.hostedResponse);
let payload;

hf.SetMainFields({
    cc: {
        elmSelector: "#cc",
        wrapperElmSelector: "#cc-wrapper",
    },
    expiryy: {
        elmSelector: "#expiryy",
        wrapperElmSelector: ".expiry-wrapper",
    },
    expirym: {
        elmSelector: "#expirym",
        wrapperElmSelector: ".expiry-wrapper",
    },
    expiry: {
        elmSelector: "#expiry",
        wrapperElmSelector: ".expiry-wrapper-full",
    },
    cvv: {
        elmSelector: "#cvv",
        wrapperElmSelector: "#cvv-wrapper",
    },
})
    .AddField("card_holder_id", "#id-number", "#id-number-wrapper")
    .AddField("payments", "#payments", "#payments-wrapper")
    .AddField("card_holder_name", "#card-holder-name", "#card-holder-name")
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
    .SetRecaptcha("#recaptcha");

function putHostedFields() {
    var $paymentMethod = jQuery(
        "#payment_method_payplus-payment-gateway-hostedfields"
    );

    // Find the closest parent <li>
    var $topLi = jQuery(".pp_iframe_h");

    // Select the existing div element that you want to move
    var $newDiv = jQuery("body > div.container.hostedFields");

    if ($paymentMethod.length && $topLi.length && $newDiv.length) {
        // Move the existing div to the top <li> of the payment method
        $topLi.append($newDiv);
    }
}

jQuery(() => {
    // Define the async function to handle the response
    async function processResponse(resp) {
        console.log(resp);
        try {
            if (resp.results.status == "success") {
                try {
                    await hf.CreatePaymentPage({
                        hosted_fields_uuid: resp.data.hosted_fields_uuid,
                        page_request_uid: resp.data.page_request_uid,
                        origin: "https://restapidev.payplus.co.il",
                    });
                } catch (error) {
                    alert(error);
                }

                hf.InitPaymentPage.then((data) => {
                    // const inputElement = document.querySelector(
                    //   "#radio-control-wc-payment-method-options-payplus-payment-gateway-hostedfields"
                    // );
                    // console.log("input", inputElement);
                    // if (inputElement) {
                    //   // Find the closest parent div
                    //   const topDiv = inputElement.closest("div");

                    //   if (topDiv) {
                    //     // Create a new div element
                    //     const newDiv = document.querySelector(
                    //       "body > div.container.hostedFields"
                    //     );
                    //     newDiv.className = "pp_iframe_h";

                    //     // Append the new div to the top div
                    //     topDiv.appendChild(newDiv);
                    //   } else {
                    //     console.log("No parent div found.");
                    //   }
                    // } else {
                    //   console.log("Element with the specified ID not found.");
                    // }

                    // jQuery(".container.hostedFields").show();
                    jQuery("#create-payment-form").hide();
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

jQuery(() => {
    var currentLanguage = jQuery("html").attr("lang");
    console.log(currentLanguage);
    currentLanguage === "en-US"
        ? jQuery(".iframe-placeholder").css("direction", "rtl")
        : null;

    var labelClasses = ["month", "year", "cvv-fld", "cCard"];

    jQuery.each(labelClasses, function (index, labelClass) {
        jQuery("#" + labelClass).on("click", function (e) {
            console.log(labelClass);
            jQuery("." + labelClass).html("");
            jQuery("." + labelClass).css("background-color", "transparent");
        });
    });

    jQuery(".seperator").on("click", function () {
        jQuery(".month").html("");
        jQuery(".year").html("");
        document.querySelector("#hosted-fld").style.fontSize = "21px";
    });

    jQuery("input").on("input", function (e) {
        console.log(e.target);
        console.log("Input field value changed (possibly autofilled)");
    });

    jQuery("#submit-payment").on("click", () => {
        jQuery("button#place_order").trigger("click");
        overlay();
        jQuery(".blocks-payplus_loader_hosted").fadeIn();
        hf.SubmitPayment();
    });
});

hf.Upon("pp_pageExpired", (e) => {
    jQuery("#submit-payment").prop("disabled", true);
    jQuery("#status").val("Page Expired");
});

hf.Upon("pp_noAttemptedRemaining", (e) => {
    alert("No more attempts remaining");
});

hf.Upon("pp_responseFromServer", (e) => {
    jQuery(".woocommerce-error").css("display", "none");
    let r = "";
    try {
        r = JSON.stringify(e.detail, null, 2);
    } catch (error) {
        r = e.detail;
    }

    let saveToken = jQuery("#save_token_checkbox").is(":checked")
        ? true
        : false;

    console.log("Payment Response: ", e.detail);
    if (e.detail.errors) {
        jQuery(".blocks-payplus_loader_hosted").fadeOut();
        let errorMessage =
            e.detail.errors[0].message === null
                ? e.detail.results.description
                : e.detail.errors[0].message;

        const ifError = () => {
            alert(errorMessage);
            overlay(true);
        };

        errorMessage !== "not-authorize-success" ? ifError() : null;
    }

    if (e.detail.data?.status_code === "000") {
        let orderId = e.detail.data.more_info;
        let token = e.detail.data.token_uid;
        let pageRequestdUid = e.detail.data.page_request_uid;
        jQuery.ajax({
            type: "post",
            dataType: "json",
            url: payplus_script.ajax_url,
            data: {
                action: "make-hosted-payment",
                order_id: orderId,
                token: token,
                saveToken: saveToken,
                page_request_uid: pageRequestdUid,
                _ajax_nonce: payplus_script.frontNonce,
            },
            success: function (response) {
                const jsonData = e.detail;

                // Create a form dynamically
                const form = document.createElement("form");
                form.method = "POST";
                form.action = e.detail.url;

                // Create a hidden input with JSON data
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "jsonData";
                input.value = JSON.stringify(jsonData);

                form.appendChild(input);
                document.body.appendChild(form);

                // Submit the form
                form.submit();
            },
        });
    }
});
hf.Upon("pp_submitProcess", (e) => {
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
                    top: "51.5vh",
                    left: "50%",
                    width: "65%",
                    height: "76.5vh",
                    border: "solid 0.1px black",
                    borderRadius: "20px",
                    backgroundColor: "rgba(255, 255, 255, 0.95)", // milky opacity
                    zIndex: 9999,
                    cursor: "not-allowed",
                    transform: "translate(-50%, -50%)", // Centers the div
                    boxShadow: "0px 4px 6px rgba(0, 0, 0, 0.1)", // Optional: Adds a shadow for aesthetics
                    display: "flex", // Flexbox for alignment
                    justifyContent: "center", // Centers horizontally
                    alignItems: "flex-start", // Aligns content to the top
                    textAlign: "center", // Centers text inside the div
                    paddingTop: "10%", // Moves the text down from the top to roughly 20%
                })
                .appendTo("body");

            // Add the text inside the div and style it
            var $text = jQuery("<div>Processing your payment.</div>").css({
                fontSize: "30px", // Text size
                color: "grey", // Text color
            });

            $overlay.append($text);
            // Prevent scrolling
            jQuery("body").css({
                overflow: "hidden",
            });
            // Disallow clicks on overlay
            $overlay.on("click", function (event) {
                event.stopPropagation();
                event.preventDefault();
            });
            jQuery(".woocommerce-error").css("display", "none");
        }
    }
};
