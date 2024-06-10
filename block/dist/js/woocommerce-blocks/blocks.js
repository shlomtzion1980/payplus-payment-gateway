const isCheckout = !document.querySelector(
  'div[data-block-name="woocommerce/checkout"]'
)
  ? false
  : true;

if (isCheckout) {
  console.log("checkout page?", isCheckout);

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
          (0, e.createElement)(
            "div",
            { className: "pp_iframe" },
            (0, e.createElement)(
              "button",
              {
                className: "closeFrame",
                id: "closeFrame",
                style: {
                  position: "absolute",
                  top: "10px",
                  right: "20px",
                  border: "none",
                  backgroundColor: "transparent",
                  display: "none",
                },
              },
              "x"
            )
          )
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
            if (
              gateways.indexOf(payment.getActivePaymentMethod()) !== -1 &&
              payment.getActiveSavedToken().length === 0
            ) {
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
      const activePaymentMethod = payment.getActivePaymentMethod();
      const gateWaySettings =
        window.wc.wcSettings.getPaymentMethodData(activePaymentMethod);

      var iframe = document.createElement("iframe");

      // Set the attributes for the iframe
      iframe.width = "100%";
      iframe.height = "100%";
      iframe.style.border = "0";

      iframe.src = paymentPageLink;
      let pp_iframes = document.querySelectorAll(".pp_iframe");
      let pp_iframe = document.querySelectorAll(".pp_iframe")[0];
      if (
        ["samePageIframe", "popupIframe"].indexOf(
          gateWaySettings.displayMode
        ) !== -1
      ) {
        if (activePaymentMethod !== "payplus-payment-gateway") {
          for (let c = 0; c < pp_iframes.length; c++) {
            const grandparent = pp_iframes[c].parentNode.parentNode;
            if (grandparent) {
              // Get the ID of the grandparent
              const grandparentId = grandparent.id;
              // console.log(grandparentId);
              // Check if the ID contains 'payplus-payment-gateway'
              if (grandparentId.includes(activePaymentMethod)) {
                pp_iframe = pp_iframes[c];
                // console.log(
                //   "Found ID containing " + activePaymentMethod + ":",
                //   grandparentId,
                //   pp_iframe
                // );
              } else {
                // console.log("ID does not contain " + activePaymentMethod + ".");
              }
            } else {
              // console.log("Grandparent not found.");
            }
          }
        }

        switch (gateWaySettings.displayMode) {
          case "samePageIframe":
            pp_iframe.style.position = "relative";
            pp_iframe.style.height = payPlusGateWay.iFrameHeight;
            break;
          case "popupIframe":
            pp_iframe.style.width = "60%";
            pp_iframe.style.height = payPlusGateWay.iFrameHeight;
            pp_iframe.style.position = "fixed";
            pp_iframe.style.top = "50%";
            pp_iframe.style.left = "50%";
            pp_iframe.style.transform = "translate(-50%, -50%)";
            pp_iframe.style.zIndex = 100000;
            pp_iframe.style.boxShadow = "10px 10px 10px 10px grey";
            pp_iframe.style.borderRadius = "25px";
            break;
          default:
            break;
        }

        pp_iframe.style.display = "block";
        pp_iframe.style.border = "none";
        pp_iframe.style.overflow = "scroll";
        pp_iframe.style.msOverflowStyle = "none"; // For Internet Explorer 10+
        pp_iframe.style.scrollbarWidth = "none"; // For Firefox
        pp_iframe.firstElementChild.style.display = "block";
        pp_iframe.firstElementChild.style.cursor = "pointer";
        pp_iframe.firstElementChild.addEventListener("click", () => {
          pp_iframe.style.display = "none";
        });
        pp_iframe.appendChild(iframe);
      }
    }
  }
}
