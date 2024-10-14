jQuery(function ($) {
  console.log("hello!");
  if (
    $("input#payment_method_payplus-payment-gateway-hostedfields").is(
      ":checked"
    )
  ) {
    overlay();
    jQuery(".blocks-payplus_loader_hosted").fadeIn();
    hf.SubmitPayment();
  }
});
