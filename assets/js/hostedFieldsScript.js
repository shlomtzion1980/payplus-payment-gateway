const hf = new PayPlusHostedFieldsDom();
var resp = JSON.parse(payplus_script.hostedResponse);
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
  console.log("hfbefore: ", hf);
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
          console.log("data: ", data);
          console.log("hfafter: ", hf);
          console.log(jQuery("#payment-form"));
          jQuery("#create-payment-form").hide();
          jQuery("#payment-form").show();
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
  jQuery("#status").val(r);
});
hf.Upon("pp_submitProcess", (e) => {
  jQuery("#submit-payment").prop("disabled", e.detail);
});
