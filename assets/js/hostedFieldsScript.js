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
jQuery(() => {
  // Define the async function to handle the response
  async function processResponse(resp) {
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
          payload = data;
          // jQuery(".hostedFields").prependTo(".woocommerce-checkout-payment");
          // jQuery(".hostedFields").prependTo(".wp-block-woocommerce-checkout");
          var $liElement = jQuery(
            "li.wc_payment_method.payment_method_payplus-payment-gateway-hostedfields"
          );

          if ($liElement.length) {
            // Create a new <div> element
            var newDiv = jQuery(".hostedFields");

            // Append the new div to the <li> element
            $liElement.append(newDiv);
          } else {
            console.log(
              "The <li> element with the specified class was not found."
            );
          }

          // setTimeout(function () {
          const inputElement = document.querySelector(
            "#radio-control-wc-payment-method-options-payplus-payment-gateway-hostedfields"
          );
          console.log(inputElement);
          if (inputElement) {
            // Find the closest parent div
            const topDiv = inputElement.closest("div");

            if (topDiv) {
              // Create a new div element
              const newDiv = document.querySelector(
                "body > div.container.hostedFields"
              );

              // Append the new div to the top div
              topDiv.appendChild(newDiv);
            } else {
              console.log("No parent div found.");
            }
          } else {
            console.log("Element with the specified ID not found.");
          }
          // }, 1000);

          jQuery("#create-payment-form").hide();
          jQuery("#id-number-wrapper").hide();
          jQuery("#payments-wrapper").hide();
          jQuery("#payment-form").css("display", "flex");
        });
      } else {
        alert(resp.results.message);
      }
    } catch (error) {
      jQuery("#error").append(`<div>Error:</div>`);
      jQuery("#error").append(`<pre>${JSON.stringify(resp, null, 2)}</pre>`);
    }
  }

  // Call the async function to process the response
  processResponse(resp);
});

jQuery(() => {
  jQuery("#submit-payment").on("click", () => {
    jQuery(".blocks-payplus_loader_hosted").fadeIn();
    // if (typeof wp !== "undefined") {
    //   console.log(
    //     wp.data.select("wc/store/cart").getCartTotals().total_shipping
    //   );
    //   let totalShipping = wp.data
    //     .select("wc/store/cart")
    //     .getCartTotals().total_shipping;
    //   // jQuery.ajax({
    //   //   type: "post",
    //   //   dataType: "json",
    //   //   url: payplus_script.ajax_url,
    //   //   data: {
    //   //     action: "update-hosted-payment",
    //   //     totalShipping: totalShipping,
    //   //     _ajax_nonce: payplus_script.frontNonce,
    //   //   },
    //   //   success: function (response) {
    //   //     console.log(response);
    //   //   },
    //   // });
    //   // console.log(payload);
    // }

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
  let r = "";
  try {
    r = JSON.stringify(e.detail, null, 2);
  } catch (error) {
    r = e.detail;
  }
  console.log("Payment Response: ", e.detail);
  if (e.detail.errors) {
    jQuery(".blocks-payplus_loader_hosted").fadeOut();

    alert(e.detail.errors[0].message);
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
        page_request_uid: pageRequestdUid,
        _ajax_nonce: payplus_script.frontNonce,
      },
      success: function (response) {
        location.assign(e.detail.url);
      },
    });
  }
});
hf.Upon("pp_submitProcess", (e) => {
  jQuery("#submit-payment").prop("disabled", e.detail);
});
