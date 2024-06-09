const { CHECKOUT_STORE_KEY } = window.wc.wcBlocksData;
const { PAYMENT_STORE_KEY } = window.wc.wcBlocksData;

const store = wp.data.select(CHECKOUT_STORE_KEY);
const payment = wp.data.select(PAYMENT_STORE_KEY);

const customerId = store.getCustomerId();
const additinalFields = store.getAdditionalFields();
const orderId = store.getOrderId();
const payPlusGateWay = window.wc.wcSettings.getPaymentMethodData(
  "payplus-payment-gateway"
);
const customerTokens = wcSettings.customerPaymentMethods.cc;
let customerDefaultToken = "";
for (let c = 0; c < customerTokens.length; c++) {
  if (customerTokens[c].is_default) {
    customerDefaultToken = customerTokens[c].tokenId;
  }
}
const gateways = window.wc.wcSettings.getPaymentMethodData(
  "payplus-payment-gateway"
).gateways;
console.log(gateways);
(() => {
  ("use strict");
  const e = window.React,
    t = window.wc.wcBlocksRegistry,
    a = window.wp.i18n,
    p = window.wc.wcSettings,
    n = window.wp.htmlEntities,
    i = gateways,
    s = (e) => (0, n.decodeEntities)(e.description || ""),
    y = (t) => {
      const { PaymentMethodLabel: a } = t.components;
      return (0, e.createElement)(
        "div",
        { className: "payplus-method" },
        (0, e.createElement)(a, {
          text: t.text,
          icon: t.icon
            ? (0, e.createElement)("img", {
                style: {
                  width: "64px",
                  height: "32px",
                  maxHeight: "100%",
                  margin: "0px 10px",
                },
                src: t.icon,
              })
            : "",
        }),
        (0, e.createElement)("div", { className: "pp_iframe" })
      );
    };
  (() => {
    for (let c = 0; c < i.length; c++) {
      const l = i[c],
        o = (0, p.getPaymentMethodData)(l, {}),
        m = (0, a.__)(
          "Pay with Debit or Credit Card",
          "payplus-payment-gateway"
        ),
        r = (0, n.decodeEntities)(o?.title || "") || m,
        w = {
          name: l,
          label: (0, e.createElement)(y, {
            text: r,
            icon: o.icon,
          }),
          content: (0, e.createElement)(s, { description: o.description }),
          edit: (0, e.createElement)(s, { description: o.description }),
          canMakePayment: () => !0,
          ariaLabel: r,
          supports: {
            showSaveOption:
              l === "payplus-payment-gateway" ? o.showSaveOption : false,
            features: o.supports,
          },
        };
      (0, t.registerPaymentMethod)(w);
    }
  })();
})();

if (
  payPlusGateWay.displayMode !== "redirect" &&
  payPlusGateWay.displayMode !== "iframe"
) {
  document.addEventListener("DOMContentLoaded", function () {
    // Function to handle when the target element is found
    function handleTargetElement(targetElement) {
      console.log("observing!");
      var processCheckout = document.querySelector(
        "#wp--skip-link--target > div.entry-content.wp-block-post-content.is-layout-constrained.wp-block-post-content-is-layout-constrained > div > div.wc-block-components-sidebar-layout.wc-block-checkout.is-large > div.wc-block-components-main.wc-block-checkout__main.wp-block-woocommerce-checkout-fields-block > form > div.wc-block-checkout__actions.wp-block-woocommerce-checkout-actions-block > div.wc-block-checkout__actions_row > button"
      );
      // console.log("processCheckout:", processCheckout);
      processCheckout.addEventListener("click", () => {
        console.log("paymentMethod: ", payment.getActivePaymentMethod());
        if (
          gateways.indexOf(payment.getActivePaymentMethod()) !== -1 &&
          payment.getActiveSavedToken().length === 0
        ) {
          function getCheckOutProcess(callback) {
            setTimeout(() => {
              // Simulate a specific message being received
              let pp_iframe = document.querySelectorAll(".pp_iframe")[0];

              var iframe = document.createElement("iframe");
              // Set the attributes for the iframe

              iframe.width = "100%";
              iframe.height = "100%";
              iframe.style.border = "0"; // Optional: remove the border
              // Find the div where the iframe will be appended
              var div = document.getElementById("pp_iframe");

              var xhr = new XMLHttpRequest();
              xhr.open(
                "GET",
                "?wc-api=get_order_meta&order_id=" + orderId,
                true
              );
              let message;
              xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                  if (xhr.status === 200) {
                    var data = JSON.parse(xhr.responseText);
                    if (data.paymentPageLink) {
                      console.log("dataPaymentLink:", data.paymentPageLink);
                      message = data.paymentPageLink;
                      iframe.src = data.paymentPageLink;
                      pp_iframe.style.display = "block";
                      switch (payPlusGateWay.displayMode) {
                        case "samePageIframe":
                          pp_iframe.style.position = "relative";
                          pp_iframe.style.height = payPlusGateWay.iFrameHeight;
                          break;
                        case "popupIframe":
                          pp_iframe.style.width = "60%";
                          pp_iframe.style.height = payPlusGateWay.iFrameHeight;
                          pp_iframe.style.position = "fixed";
                          pp_iframe.style.border = "solid";
                          pp_iframe.style.top = "50%";
                          pp_iframe.style.left = "50%";
                          pp_iframe.style.transform = "translate(-50%, -50%)";
                          pp_iframe.style.zIndex = 100000;
                          break;
                        default:
                          pp_iframe.style.position = "fixed";
                          pp_iframe.style.border = "solid";
                          break;
                      }
                      pp_iframe.style.overflow = "hidden";
                      pp_iframe.appendChild(iframe);
                    }
                  } else {
                    console.error("Error fetching data:", xhr.statusText);
                  }
                }
              };
              console.log("loading iframe...");
              xhr.send();
              callback(message);
            }, 1000); // Simulates a delay of 2 seconds
          }

          function handleMessage(message) {
            if (message) {
              console.log("handlemessage", message);
              clearTimeout(timeoutId);
            }
          }

          // Set up a timeout that should be stopped if the specific message is received
          const timeoutId = setTimeout(() => {
            console.log("setting timeout");
            getCheckOutProcess(handleMessage);
          }, 1000); // Timeout set to 5 seconds
        }
      });
    }

    // Function to start observing for the target element
    function startObserving() {
      console.log("observer started");
      // tokenRadioButtons = document.querySelectorAll(
      //   ".wc-block-components-radio-control__input"
      // );
      // for (let c = 0; c < tokenRadioButtons.length; c++) {
      //   console.log(tokenRadioButtons[c]);
      //   const label = document.querySelector(
      //     `label[for="${tokenRadioButtons[c].id}"]`
      //   );
      //   label.classList.remove(
      //     "wc-block-components-radio-control__option-checked"
      //   );
      //   if (
      //     tokenRadioButtons[c].id.search("-saved-tokens-") &&
      //     tokenRadioButtons[c].value == customerDefaultToken
      //   ) {
      //     const label = document.querySelector(
      //       `label[for="${tokenRadioButtons[c].id}"]`
      //     );
      //     // label.checked = true;
      //     label.classList.add(
      //       "wc-block-components-radio-control__option-checked"
      //     );
      //     tokenRadioButtons[c].checked = true;
      //   }
      // }
      // Set up the MutationObserver
      const observer = new MutationObserver((mutationsList, observer) => {
        mutationsList.forEach((mutation) => {
          // Check if the target element is added to the DOM
          const targetElement = document.querySelector(
            "#wp--skip-link--target > div.entry-content.wp-block-post-content.is-layout-constrained.wp-block-post-content-is-layout-constrained > div > div.wc-block-components-sidebar-layout.wc-block-checkout.is-large > div.wc-block-components-main.wc-block-checkout__main.wp-block-woocommerce-checkout-fields-block > form > div.wc-block-checkout__actions.wp-block-woocommerce-checkout-actions-block > div.wc-block-checkout__actions_row > button"
          );

          let button = document.querySelector(
            "#wp--skip-link--target > div.entry-content.wp-block-post-content.is-layout-constrained.wp-block-post-content-is-layout-constrained > div > div.wc-block-components-sidebar-layout.wc-block-checkout.is-medium > div.wc-block-components-main.wc-block-checkout__main.wp-block-woocommerce-checkout-fields-block > form > div.wc-block-checkout__actions.wp-block-woocommerce-checkout-actions-block > div.wc-block-checkout__actions_row > button > span"
          );
          let form = document.querySelector(
            "#wp--skip-link--target > div.entry-content.wp-block-post-content.is-layout-constrained.wp-block-post-content-is-layout-constrained > div > div.wc-block-components-sidebar-layout.wc-block-checkout.is-large > div.wc-block-components-main.wc-block-checkout__main.wp-block-woocommerce-checkout-fields-block > form > div.wc-block-checkout__actions.wp-block-woocommerce-checkout-actions-block > div.wc-block-checkout__actions_row > button > span"
          );
          if (targetElement) {
            // Call the function to handle the target element
            handleTargetElement(targetElement);
            // Disconnect the observer to stop observing further changes
            observer.disconnect();
          }
        });
      });

      // Start observing the target node for configured mutations
      const targetNode = document.body; // Or any other parent node where the element might be added
      const config = { childList: true, subtree: true };
      observer.observe(targetNode, config);
    }

    // Wait for a few seconds before starting to observe
    setTimeout(startObserving, 1000); // Adjust the time (in milliseconds) as needed
  });
}
