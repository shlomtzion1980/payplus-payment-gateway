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
        { className: "payplus-method", style: { width: "100%" } },
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
    // Function to start observing for the target element
    function startObserving() {
      console.log("observer started");
      const observer = new MutationObserver((mutationsList, observer) => {
        if (store.isComplete()) {
          console.log("isComplete: " + store.isComplete());
          console.log(
            "paymentPageLink",
            payment.getPaymentResult().paymentDetails.paymentPageLink
          );
          // Call the function to handle the target element
          startIframe(
            payment.getPaymentResult().paymentDetails.paymentPageLink
          );
          // Disconnect the observer to stop observing further changes
          observer.disconnect();
        }
      });
      // Start observing the target node for configured mutations
      const targetNode = document.body; // Or any other parent node where the element might be added
      const config = { childList: true, subtree: true };
      observer.observe(targetNode, config);
    }
    // Wait for a few seconds before starting to observe
    setTimeout(startObserving, 1000); // Adjust the time (in milliseconds) as needed
  });

  function startIframe(paymentPageLink) {
    if (
      gateways.indexOf(payment.getActivePaymentMethod()) !== -1 &&
      payment.getActiveSavedToken().length === 0
    ) {
      let pp_iframe = document.querySelectorAll(".pp_iframe")[0];

      var iframe = document.createElement("iframe");

      // Set the attributes for the iframe
      iframe.width = "100%";
      iframe.height = "100%";
      iframe.style.border = "0";

      iframe.src = paymentPageLink;
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
  }
}
