// const settings = window.wc.wcSettings.getPaymentMethodData(
//   "payplus-payment-gateway",
//   {}
// );

const gateways = [
  "payplus-payment-gateway",
  "payplus-payment-gateway-bit",
  "payplus-payment-gateway-googlepay",
  "payplus-payment-gateway-applepay",
  "payplus-payment-gateway-multipass",
  "payplus-payment-gateway-tavzahav",
  "payplus-payment-gateway-valuecard",
  "payplus-payment-gateway-finitione",
  "payplus-payment-gateway-paypal",
];

// console.log(settings);

// const label =
//   window.wp.htmlEntities.decodeEntities(settings.title) ||
//   window.wp.i18n.__("Pay with Debit or Credit Card", "payplus-payment-gateway");

// console.log(window.wp.htmlEntities.decodeEntities(settings.description || ""));
// // create an element with the title and an img element with the icon in the payment method
// const PaymentMethodLabel = ({ text, icon }) => {
//   return window.wp.element.createElement(
//     "div",
//     { className: "payplus-method" },
//     window.wp.element.createElement("img", {
//       style: {
//         width: "64px",
//         height: "32px",
//         maxHeight: "100%",
//         margin: "0px 10px",
//       },
//       src: settings.icon,
//     }),
//     label,
//     window.wp.element.createElement("div", { className: "pp_iframe" })
//   );
// };
// const Content = () => {
//   return window.wp.element.createElement(
//     "div",
//     null,
//     window.wp.element.createElement(
//       "span",
//       null,
//       window.wp.htmlEntities.decodeEntities(settings.description || "")
//     ),
//     window.wp.element.createElement("div", { className: "pp_iframe" })
//   );
// };

for (let c = 0; c < gateways.length; c++) {
  const gateway = gateways[c];
  console.log(gateway);
  const settings = window.wc.wcSettings.getPaymentMethodData(gateway, {});
  const label =
    window.wp.htmlEntities.decodeEntities(settings.title) ||
    window.wp.i18n.__(
      "Pay with Debit or Credit Card",
      "payplus-payment-gateway"
    );

  const PaymentMethodLabel = ({ text, icon }) => {
    return window.wp.element.createElement(
      "div",
      { className: "payplus-method" },
      window.wp.element.createElement("img", {
        style: {
          width: "64px",
          height: "32px",
          maxHeight: "100%",
          margin: "0px 10px",
        },
        src: settings.icon,
      }),
      label,
      window.wp.element.createElement("div", { className: "pp_iframe" })
    );
  };
  const Content = () => {
    return window.wp.element.createElement(
      "div",
      null,
      window.wp.element.createElement(
        "span",
        null,
        window.wp.htmlEntities.decodeEntities(settings.description || "")
      ),
      window.wp.element.createElement("div", { className: "pp_iframe" })
    );
  };

  const Block_Gateway = {
    name: gateway,
    label: Object(window.wp.element.createElement)(PaymentMethodLabel, null),
    content: Object(window.wp.element.createElement)(Content, null),
    edit: Object(window.wp.element.createElement)(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
      features: settings.supports,
      showSaveOption: settings.showSaveOption,
    },
  };
  window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
}
// const Block_Gateway = {
//   name: "payplus-payment-gateway",
//   label: Object(window.wp.element.createElement)(PaymentMethodLabel, null),
//   content: Object(window.wp.element.createElement)(Content, null),
//   edit: Object(window.wp.element.createElement)(Content, null),
//   canMakePayment: () => true,
//   ariaLabel: label,
//   supports: {
//     features: settings.supports,
//     showSaveOption: settings.showSaveOption,
//   },
// };
// window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
