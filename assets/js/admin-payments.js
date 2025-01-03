jQuery(document).ready(function ($) {
  // setFieldReadOnly();
  PayplusdisplayMenuInvoice();
  let changestatus = document.getElementById("payplus-change-status");

  if (changestatus) {
    changestatus.addEventListener("click", (e) => {
      event.preventDefault();
      const status = document.querySelector(".payplus-change-status");
      debugger;
      const rows = status.querySelectorAll(".payplus-row");
      rows.forEach((row) => {
        row.style.display = row.style.display == "flex" ? "none" : "flex";
      });
    });
  }
  $(".do-api-refund-payplus").click(async function (event) {
    event.preventDefault();
    $(this).addClass("button-loading");

    const parentRow = $(this).parents("tr").attr("class").split(" ");
    const orderID = $("#post_ID").val();
    const id = $(this).attr("data-id");
    const transactionUid = $(this).attr("data-transaction-uid");
    const amount = parseFloat($(".sum-" + parentRow[1]).val());
    const method = $(this).attr("data-method");
    const refund = $(this).attr("data-refund");

    if (0 >= parseFloat(amount) || parseFloat(amount) > parseFloat(refund)) {
      $(this).removeClass("button-loading");
      alert(payplus_script_admin.payplus_refund_error);
      return false;
    }
    const data = new FormData();
    data.append("action", "payplus-refund-club-amount");
    data.append("amount", amount);
    data.append("transactionUid", transactionUid);
    data.append("method", method);
    data.append("orderID", orderID);
    data.append("id", id);
    fetch(payplus_script_admin.ajax_url, {
      method: "post",
      headers: {
        Accept: "application/json",
      },
      body: data,
    })
      .then((response) => response.json())
      .then((response) => {
        $(this).removeClass("button-loading");
        location.href = response.urlredirect;
      });
  });

  $(".select-languages-payplus").change(function (event) {
    event.preventDefault();
    let language = $(this).val();
    let html = "";
    if (language) {
      let languageOther = language.split("-");
      language = language.replace(" ", "-");
      html =
        '<tr valign="top">' +
        '<th scope="row" class="titledesc">' +
        '<label for="settings_payplus_page_error_option[' +
        language +
        ']">' +
        languageOther[1] +
        "</label>" +
        "</th>" +
        '<td class="forminp forminp-textarea">' +
        '<textarea name="settings_payplus_page_error_option[' +
        language +
        ']" id="settings_payplus_page_error_option[' +
        language +
        ']" ' +
        '   style="" class="" placeholder=""></textarea>';
      "</td>" + "</tr>";
      $(".form-table").append(html);
    }
  });
  $(".copytoken").click(function (event) {
    event.preventDefault();
    var copyText = $(".copytoken");
    navigator.clipboard.writeText(copyText.text());
  });
  $("#order-payment-payplus-refund").click(function () {
    event.preventDefault();
    let orderId = $(this).attr("data-id");
    $(".payplus_loader_refund").fadeIn();
    $.ajax({
      type: "post",
      dataType: "json",
      url: payplus_script_admin.ajax_url,
      data: {
        action: "payplus-api-payment-refund",
        order_id: orderId,
      },
      success: function (response) {
        $(".payplus_loader_refund").fadeOut();
        if (response.status) {
          location.href = response.urlredirect;
        }
      },
    });
  });

  $("#custom-button-get-pp").click(function () {
    let loader = $("#order_data").find(".payplus_loader_gpp");
    let side = "right";

    // check if page is rtl or ltr and change the direction of the loader
    if ($("body").hasClass("rtl")) {
      side = "left";
    }

    loader.css(side, "5%");

    loader.css({
      position: "absolute",
      top: "5px",
    });
    $("#custom-button-get-pp").fadeOut();
    loader.fadeIn();

    var data = {
      action: "custom_action",
      payment_request_uid: $("#custom-button-get-pp").val(),
      order_id: $("#custom-button-get-pp").data("value"),
    };

    $.post(ajaxurl, data, function (response) {
      loader.fadeOut();
      location.reload();
    });
  });

  $(document).on("click", "#payment-payplus-transaction", function (event) {
    event.preventDefault();
    let orderId = $(this).attr("data-id");
    $(".payplus_loader").fadeIn();
    $.ajax({
      type: "post",
      dataType: "json",
      url: payplus_script_admin.ajax_url,
      data: {
        action: "payment-payplus-transaction-review",
        order_id: orderId,
      },
      success: function (response) {
        $("#payment-payplus-dashboard,.payplus_loader").fadeOut();
        if (response.status) {
          location.href = response.urlredirect;
        }
      },
    });
  });

  $(document).on("click", "#payment-payplus-dashboard", function (event) {
    event.preventDefault();
    let orderId = $(this).attr("data-id");
    let $this = $(this);
    $this.parent(".payment-order-ajax").find(".payplus_loader").fadeIn();
    $.ajax({
      type: "post",
      dataType: "json",
      url: payplus_script_admin.ajax_url,
      data: {
        action: "generate-link-payment",
        order_id: orderId,
      },
      success: function (response) {
        $("#box-payplus-payment").fadeIn();
        $this.parent(".payment-order-ajax").find(".payplus_loader").fadeOut();

        if (response.status) {
          $this.fadeOut();
          $("#box-payplus-payment iframe").attr(
            "src",
            response.payment_response
          );
        } else {
          $("#box-payplus-payment").text(response.payment_response);
        }
      },
    });
  });
  $("#order-payment-payplus").click(function (event) {
    event.preventDefault();
    let orderId = $(this).attr("data-id");
    $(".payplus_loader").fadeIn();
    $.ajax({
      type: "post",
      dataType: "json",
      url: payplus_script_admin.ajax_url,
      data: {
        action: "payplus-api-payment",
        order_id: orderId,
      },
      success: function (response) {
        $(".payplus_loader").fadeOut();
        //if(response.status){
        location.href = response.urlredirect;
        // }
      },
    });
  });

  $("#payplus-token-payment").click(function (event) {
    event.preventDefault();
    let payplusChargeAmount = $(this)
        .closest(".delayed-payment")
        .find("#payplus_charge_amount")
        .val(),
      payplusOrderId = $(this)
        .closest(".delayed-payment")
        .find("#payplus_order_id")
        .val();
    $(this).closest(".delayed-payment").find(".payplus_loader").fadeIn();
    $("#payplus-token-payment").prop("disabled", true);
    $.ajax({
      type: "post",
      dataType: "json",
      url: payplus_script_admin.ajax_url,
      data: {
        action: "payplus-token-payment",
        payplus_charge_amount: payplusChargeAmount,
        payplus_order_id: payplusOrderId,
        payplus_token_payment: true,
      },
      beforeSend: function () {
        const targetNode = document.querySelector(".payplus_error");
        const observer = new MutationObserver((mutationsList, observer) => {
          for (let mutation of mutationsList) {
            if (
              mutation.type === "attributes" &&
              mutation.attributeName === "style"
            ) {
              if (targetNode.style.display !== "none") {
              } else {
                $(".payplus_loader").fadeOut();
              }
            }
          }
        });
        const config = { attributes: true, attributeFilter: ["style"] };
        observer.observe(targetNode, config);
      },
      success: function (response) {
        $(this).closest(".delayed-payment").find(".payplus_loader").fadeOut();
        if (!response.status) {
          $(".payplus_error")
            .html(payplus_script_admin.error_payment)
            .fadeIn(function () {
              setTimeout(function () {
                $("#payplus-token-payment").prop("disabled", false);
                $(".payplus_error").fadeOut("fast");
                $("#payplus_charge_amount").val(
                  $("#payplus_charge_amount").attr("data-amount")
                );
              }, 1000);
            });
        } else {
          location.href = response.urlredirect;
        }
      },
      error: function (xhr, status, error) {
        let errorMessage = xhr.responseText.split("&error=")[1]
          ? xhr.responseText.split("&error=")[1]
          : "Failed, please check the order notes for the failure reason.";
        alert(errorMessage);
        location.reload();
      },
    });
  });
});
function setFieldReadOnly() {
  const arrNameFieldReadOnly = jQuery("#postcustom input[type='text']");

  for (let i = 0; i < arrNameFieldReadOnly.length; i++) {
    const metaName = jQuery(arrNameFieldReadOnly[i]).attr("id");
    const metaValue = metaName.replace("key", "value");
    if (
      jQuery("#" + metaName)
        .val()
        .indexOf("payplus") != -1
    ) {
      const father = metaName.replace("-key", "");
      jQuery("#" + metaName).prop("disabled", true);
      jQuery("#" + metaValue).prop("disabled", true);
      jQuery("#" + father)
        .find(".button")
        .prop("disabled", true);
    }
  }

  const metaName = jQuery("#postcustom input[value='order_validated']").attr(
    "id"
  );

  if (typeof metaName != "undefined") {
    const metaValue = metaName.replace("key", "value");
    const father = metaName.replace("-key", "");
    jQuery("#" + metaName).prop("disabled", true);
    jQuery("#" + metaValue).prop("disabled", true);
    jQuery("#" + father)
      .find(".button")
      .prop("disabled", true);
  }
}
function payPlusSumRefund() {
  const arrRefundAmount = ["refund_amount"];
  let sum = 0;
  const isEmpty = (str) => !str?.length;
  for (let i = 0; i < arrRefundAmount.length; i++) {
    if (!isEmpty(jQuery("#" + arrRefundAmount[i]).val())) {
      sum += parseFloat(jQuery("#" + arrRefundAmount[i]).val());
    }
  }
  return sum.toFixed(2);
}
function PayplusdisplayMenuInvoice() {
  const queryString = window.location.search;
  const urlParams = new URLSearchParams(queryString);
  const section = urlParams.get("section");
  if (
    section == "payplus-invoice" ||
    section == "payplus-payment-gateway-setup-wizard" ||
    section == "payplus-express-checkout" ||
    section == "payplus-error-setting"
  ) {
    jQuery(".wrap.woocommerce")
      .find("h2")
      .before(payplus_script_admin.menu_option);
  }
}
