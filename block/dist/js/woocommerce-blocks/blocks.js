const { CHECKOUT_STORE_KEY } = window.wc.wcBlocksData;
const { PAYMENT_STORE_KEY } = window.wc.wcBlocksData;

const store = wp.data.select(CHECKOUT_STORE_KEY);
const payment = wp.data.select(PAYMENT_STORE_KEY);
const hasOrder = store.hasOrder();

const isCheckout = !document.querySelector(
  'div[data-block-name="woocommerce/checkout"]'
)
  ? false
  : true;

if (isCheckout || hasOrder) {
  console.log("checkout page?", isCheckout);
  console.log("has order?", hasOrder);

  const customerId = store.getCustomerId();
  const additionalFields = store.getAdditionalFields();
  const orderId = store.getOrderId();
  const payPlusGateWay = window.wc.wcSettings.getPaymentMethodData(
    "payplus-payment-gateway"
  );

  function addScriptApple() {
    if (isMyScriptLoaded(payPlusGateWay.importApplePayScript)) {
      const script = document.createElement("script");
      script.src = payPlusGateWay.importApplePayScript;
      document.body.append(script);
    }
  }

  function isMyScriptLoaded(url) {
    var scripts = document.getElementsByTagName("script");
    for (var i = scripts.length; i--;) {
      if (scripts[i].src == url) {
        return false;
      }
    }
    return true;
  }

  let gateways = window.wc.wcSettings.getPaymentMethodData(
    "payplus-payment-gateway"
  ).gateways;

  gateways = payPlusGateWay.isSubscriptionOrder
    ? ["payplus-payment-gateway"]
    : gateways;

  gateways = payPlusGateWay.isSubscriptionOrder && payPlusGateWay.isLoggedIn
    ? ["payplus-payment-gateway", "payplus-payment-gateway-hostedfields"]
    : gateways;

  let customIcons = [];

  const w = window.React;
  for (let c = 0; c < payPlusGateWay.customIcons?.length; c++) {
    customIcons[c] = (0, w.createElement)("img", {
      src: payPlusGateWay.customIcons[c],
      style: { maxHeight: "35px", height: "45px" },
    });
  }

  const divCustomIcons = (0, w.createElement)(
    "div",
    {
      className: "payplus-icons",
      style: {
        display: "flex",
        width: "95%",
      },
    },
    customIcons
  );

  let isCustomeIcons = !!payPlusGateWay.customIcons[0]?.length;
  const hasSavedTokens = Object.keys(payPlusGateWay.hasSavedTokens).length > 0;
  const hideMainPayPlusGateway =
    payPlusGateWay.hideMainPayPlusGateway && hasSavedTokens;

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
            icon:
              t.icon !== ""
                ? (0, e.createElement)("img", {
                  style: {
                    width: "64px",
                    height: "32px",
                    maxHeight: "100%",
                    margin: "0px 10px",
                    objectPosition: "center",
                  },
                  src: t.icon,
                })
                : divCustomIcons,
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
                  top: "0px",
                  fontSize: "20px",
                  right: "0px",
                  border: "none",
                  color: "black",
                  backgroundColor: "transparent",
                  display: "none",
                },
              },
              "x"
            )
          ),
          t.icon.search("PayPlusLogo.svg") > 0 && isCustomeIcons
            ? divCustomIcons
            : null
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
            content: (0, e.createElement)(s, {
              description: o.description,
            }),
            edit: (0, e.createElement)(s, {
              description: o.description,
            }),
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

  document.addEventListener("DOMContentLoaded", function () {
    // Function to start observing for the target element
    let loopImages = true;
    let autoPPCCrun = customerId > 0 ? true : false;
    let WcSettings = window.wc.wcSettings;

    if (WcSettings.allSettings?.customerPaymentMethods?.cc !== undefined) {
      autoPPCCrun = false;
    }

    var loader = document.createElement("div");
    loader.class = "blocks-payplus_loader";

    // Add loader content
    const loaderContent = document.createElement("div");
    loaderContent.className = "blocks-payplus_loader";
    const loaderInner = document.createElement("div");
    loaderInner.className = "blocks-loader";
    const loaderBackground = document.createElement("div");
    loaderBackground.className = "blocks-loader-background";
    const loaderText = document.createElement("div");
    loaderText.className = "blocks-loader-text";
    loaderBackground.appendChild(loaderText);
    loaderInner.appendChild(loaderBackground);
    loaderContent.appendChild(loaderInner);
    loader.appendChild(loaderContent);
    console.log("isAutoPPCC: ", payPlusGateWay.isAutoPPCC);
    function startObserving(event) {
      console.log("observer started");

      const overlay = document.createElement("div");
      overlay.style.backgroundColor = "rgba(0, 0, 0, 0.5)";
      overlay.id = "overlay";
      overlay.style.position = "fixed";
      overlay.style.height = "100%";
      overlay.style.width = "100%";
      overlay.style.top = "0";
      overlay.style.zIndex = "5";

      setTimeout(() => {
        let element = document.querySelector(
          "#radio-control-wc-payment-method-options-payplus-payment-gateway-multipass"
        );
        if (loopImages && element) {
          multiPassIcons(loopImages, element);
          loopImages = false;
        }
      }, 3000);

      payPlusCC = document.querySelector(
        "#radio-control-wc-payment-method-options-payplus-payment-gateway"
      );

      const observer = new MutationObserver((mutationsList, observer) => {
        if (hideMainPayPlusGateway) {
          const parentDiv = document
            .querySelector(
              "#radio-control-wc-payment-method-options-payplus-payment-gateway"
            )
            .closest(".wc-block-components-radio-control-accordion-option");
          if (parentDiv) {
            parentDiv.style.display = "none";
          }
        }
        if (store.hasError()) {
          try {
            let getPaymentResult = payment.getPaymentResult();

            if (
              getPaymentResult === null ||
              getPaymentResult === undefined ||
              getPaymentResult === ""
            ) {
              throw new Error("Payment result is empty, null, or undefined.");
            }

            // Process the result here
            console.log("Payment result:", getPaymentResult);
            let pp_iframe = document.querySelectorAll(".pp_iframe")[0];
            pp_iframe.style.width = window.innerWidth <= 768 ? "95%" : "55%";
            pp_iframe.style.height = "200px";
            pp_iframe.style.position = "fixed";
            pp_iframe.style.backgroundColor = "white";
            pp_iframe.style.display = "flex";
            pp_iframe.style.alignItems = "center";
            pp_iframe.style.textAlign = "center";
            pp_iframe.style.justifyContent = "center";
            pp_iframe.style.top = "50%";
            pp_iframe.style.left = "50%";
            pp_iframe.style.transform = "translate(-50%, -50%)";
            pp_iframe.style.zIndex = 100000;
            pp_iframe.style.boxShadow = "10px 10px 10px 10px grey";
            pp_iframe.style.borderRadius = "25px";
            pp_iframe.innerHTML =
              getPaymentResult.paymentDetails.errorMessage !== undefined
                ? getPaymentResult.paymentDetails.errorMessage +
                "<br>" +
                "Click this to close."
                : getPaymentResult.message + "<br>" + "Click this to close.";
            pp_iframe.addEventListener("click", (e) => {
              e.preventDefault();
              pp_iframe.style.display = "none";
              location.reload();
            });
            console.log(getPaymentResult.paymentDetails.errorMessage);
            if (getPaymentResult.paymentDetails.errorMessage !== undefined) {
              alert(getPaymentResult.paymentDetails.errorMessage);
            } else {
              alert(getPaymentResult.message);
            }

            observer.disconnect();
          } catch (error) {
            // Handle the error here
            console.error("An error occurred:", error.message);
          }
        }
        if (store.isComplete()) {
          observer.disconnect();
          const activePaymentMethod = payment.getActivePaymentMethod();

          if (
            activePaymentMethod.search(
              "payplus-payment-gateway-hostedfields"
            ) === 0
          ) {
            hf.SubmitPayment();
            document.body.style.overflow = "hidden";
            document.body.style.backgroundColor = "white";
            document.body.style.opacity = "0.7";
            document.querySelector(
              ".blocks-payplus_loader_hosted"
            ).style.display = "block";
            const inputs = document.querySelectorAll(
              'input[type="radio"], input'
            );
            inputs.forEach((input) => {
              input.disabled = true;
            });
            hf.Upon("pp_responseFromServer", (e) => {
              if (e.detail.errors) {
                location.reload();
              }
            });
            return;
          }

          if (activePaymentMethod.search("payplus-payment-gateway") === 0 && activePaymentMethod.search("payplus-payment-gateway-pos-emv") !== 0) {
            const gateWaySettings =
              window.wc.wcSettings.getPaymentMethodData(activePaymentMethod)[
              activePaymentMethod + "-settings"
              ];
            const isIframe =
              ["samePageIframe", "popupIframe"].indexOf(
                gateWaySettings.displayMode
              ) !== -1;
            console.log("isIframe?", isIframe);
            if (
              gateways.indexOf(payment.getActivePaymentMethod()) !== -1 &&
              payment.getActiveSavedToken().length === 0
            ) {
              console.log("isComplete: " + store.isComplete());
              // Call the function to handle the target element
              if (isIframe) {
                if (
                  payment.getPaymentResult().paymentDetails.paymentPageLink
                    ?.length > 0
                ) {
                  console.log(
                    "paymentPageLink",
                    payment.getPaymentResult().paymentDetails.paymentPageLink
                  );
                  startIframe(
                    payment.getPaymentResult().paymentDetails.paymentPageLink,
                    overlay,
                    loader
                  );
                  // Disconnect the observer to stop observing further changes
                } else {
                  alert("Error: the payment page failed to load.");
                  location.reload();
                }
              }
              observer.disconnect();
            }
          }
        }
      });
      // Start observing the target node for configured mutations
      const targetNode = document.body; // Or any other parent node where the element might be added
      const config = { childList: true, subtree: true };
      observer.observe(targetNode, config);
    }
    // Wait for a few seconds before starting to observe
    setTimeout(startObserving(), 1000); // Adjust the time (in milliseconds) as needed
  });

  function startIframe(paymentPageLink, overlay, loader) {
    document.body.appendChild(overlay);
    overlay.appendChild(loader);
    const activePaymentMethod = payment.getActivePaymentMethod();
    const gateWaySettings =
      window.wc.wcSettings.getPaymentMethodData(activePaymentMethod)[
      activePaymentMethod + "-settings"
      ];
    var iframe = document.createElement("iframe");
    // Set the attributes for the iframe
    iframe.width = "95%";
    iframe.height = "100%";
    iframe.style.border = "0";
    iframe.style.display = "block";
    iframe.style.margin = "auto";

    iframe.src = paymentPageLink;
    let pp_iframes = document.querySelectorAll(".pp_iframe");
    let pp_iframe = document.querySelector(
      `#radio-control-wc-payment-method-options-${activePaymentMethod}`
    ).nextElementSibling.querySelector('.pp_iframe');
    if (
      ["samePageIframe", "popupIframe"].indexOf(gateWaySettings.displayMode) !==
      -1
    ) {
      if (activePaymentMethod !== "payplus-payment-gateway") {
        for (let c = 0; c < pp_iframes.length; c++) {
          const grandparent = pp_iframes[c].parentNode.parentNode;
          if (grandparent) {
            const grandparentId = grandparent.id;
            if (grandparentId.includes(activePaymentMethod)) {
              pp_iframe = pp_iframes[c];
            } else {
            }
          } else {
          }
        }
      }
      gateWaySettings.displayMode =
        window.innerWidth <= 768 &&
          gateWaySettings.displayMode === "samePageIframe"
          ? "popupIframe"
          : gateWaySettings.displayMode;
      switch (gateWaySettings.displayMode) {
        case "samePageIframe":
          pp_iframe.style.position = "relative";
          pp_iframe.style.height = gateWaySettings.iFrameHeight;
          overlay.style.display = "none";
          break;
        case "popupIframe":
          pp_iframe.style.width = window.innerWidth <= 768 ? "98%" : "55%";
          pp_iframe.style.height = gateWaySettings.iFrameHeight;
          pp_iframe.style.position = "fixed";
          pp_iframe.style.top = "50%";
          pp_iframe.style.left = "50%";
          pp_iframe.style.paddingBottom =
            window.innerWidth <= 768 ? "20px" : "10px";
          pp_iframe.style.paddingTop =
            window.innerWidth <= 768 ? "20px" : "10px";
          pp_iframe.style.backgroundColor = "white";
          pp_iframe.style.transform = "translate(-50%, -50%)";
          pp_iframe.style.zIndex = 100000;
          pp_iframe.style.boxShadow = "10px 10px 10px 10px grey";
          pp_iframe.style.borderRadius = "5px";
          document.body.style.overflow = "hidden";
          document.getElementsByClassName(
            "blocks-payplus_loader"
          )[0].style.display = "none";
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
      pp_iframe.firstElementChild.addEventListener("click", (e) => {
        e.preventDefault();
        pp_iframe.style.display = "none";
        var currentUrl = window.location.href;
        var params = new URLSearchParams(currentUrl);
        let isAddressEdit = currentUrl.search("address_edit=yes") !== -1;
        if (!isAddressEdit && payPlusGateWay.isAutoPPCC) {
          if (currentUrl.indexOf("?") === -1) {
            // No query string, add the parameter
            window.location.href = currentUrl + "?address_edit=yes";
          } else {
            // Query string exists, append the parameter with an '&'
            window.location.href = currentUrl + "&address_edit=yes";
          }
        } else {
          location.reload();
        }
      });
      pp_iframe.appendChild(iframe);
      if (payPlusGateWay.importApplePayScript) {
        addScriptApple();
      }
    }
  }

  function multiPassIcons(loopImages, element = null) {
    /* Check if multipass method is available and if so check for clubs and replace icons! */
    if (element === null) {
      element = document.querySelector(
        "#radio-control-wc-payment-method-options-payplus-payment-gateway-multipass"
      );
    }
    const isMultiPass = wcSettings.paymentMethodSortOrder.includes(
      "payplus-payment-gateway-multipass"
    );
    if (
      loopImages &&
      isMultiPass &&
      Object.keys(
        wcSettings.paymentMethodData["payplus-payment-gateway"].multiPassIcons
      ).length > 0
    ) {
      // console.log("isMultiPass");
      const multiPassIcons =
        wcSettings.paymentMethodData["payplus-payment-gateway"].multiPassIcons;

      // Function to find an image by its src attribute
      function findImageBySrc(src) {
        // Find all images within the document
        let images = document.querySelectorAll("img");
        // Loop through images to find the one with the matching src
        for (let img of images) {
          if (img.src.includes(src)) {
            return img;
          }
        }
        return null;
      }

      // Function to replace the image source with fade effect
      function replaceImageSourceWithFade(image, newSrc) {
        if (image && newSrc) {
          image.style.transition = "opacity 0.5s";
          image.style.opacity = 0;

          setTimeout(() => {
            image.src = newSrc;
            image.style.opacity = 1;
          }, 500);
        } else {
          console.log("Image or new source not found.");
        }
      }

      // Example usage
      if (element) {
        // Find the image with the specific src
        let imageToChange = findImageBySrc("multipassLogo.png");
        if (imageToChange) {
          let originalSrc = imageToChange.src;
          let imageIndex = 0;
          const imageKeys = Object.keys(multiPassIcons);
          const sources = imageKeys.map((key) => multiPassIcons[key]);

          function loopReplaceImageSource() {
            const newSrc = sources[imageIndex];
            replaceImageSourceWithFade(imageToChange, newSrc);
            imageIndex = (imageIndex + 1) % sources.length;
            if (
              Object.keys(
                wcSettings.paymentMethodData["payplus-payment-gateway"]
                  .multiPassIcons
              ).length > 1
            ) {
              setTimeout(loopReplaceImageSource, 2000); // Change image every 3 seconds
            }
          }

          loopReplaceImageSource();
          loopImages = false;
        }
      }
    }
    /* finished multipass image replace */
  }

  function autoPPCC(overlay, loader, payPlusCC = null) {
    setTimeout(function () {
      if (payPlusCC === null) {
        payPlusCC = document.querySelector(
          "#radio-control-wc-payment-method-options-payplus-payment-gateway"
        );
      }
      if (payPlusCC) {
        // Automatically click the 'Place Order' button
        var params = new URLSearchParams(window.location.search);
        let isAddressEdit = params.get("address_edit") === "yes";

        const actionsRowDiv = document.querySelector(
          ".wc-block-checkout__actions_row"
        );

        // Find the button inside this div
        const placeOrderButton = actionsRowDiv
          ? actionsRowDiv.querySelector("button")
          : null;

        if (!isAddressEdit && placeOrderButton && payPlusGateWay.isAutoPPCC) {
          payPlusCC.click();
          payPlusCC.scrollIntoView({
            behavior: "smooth",
            block: "start",
          });
          document.body.appendChild(overlay);
          overlay.appendChild(loader);
          placeOrderButton.click();
        } else {
          placeOrderButton?.addEventListener("click", function () {
            const activePaymentMethod = payment.getActivePaymentMethod();
            if (activePaymentMethod.search("payplus-payment-gateway") === 0) {
              document.body.appendChild(overlay);
              overlay.appendChild(loader);
            }
          });
        }
      }
    }, 1000);
  }

  const putOverlay = (remove = false) => {
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
}
