const price = document.querySelectorAll(".price");
const fullAmount = document.querySelectorAll(".payplus-full-amount");
let table_payment = null;
const allSum = document.getElementById("all-sum");

jQuery(function ($) {
  const globalShipping = $(".global_shipping");
  const shippingwoo = $(".shipping_woo");
  const globalShippingTax = $(".global_shipping_tax");
  const globalShippingTaxRate = $(".global_shipping_tax_rate");
  const enableGooglePay = $(".enable_google_pay");
  const enableApplePay = $(".enable_apple_pay");
  const tokenApplePay = $(".apple_pay_identifier");
  const transactionType = $(
    "#woocommerce_payplus-payment-gateway_transaction_type"
  );
  const checkAmountAuthorization = $(
    "#woocommerce_payplus-payment-gateway_check_amount_authorization"
  );
  const blockIpTransactions = $(
    "#woocommerce_payplus-payment-gateway_block_ip_transactions"
  );
  const blockIpTransactionsHour = $(
    "#woocommerce_payplus-payment-gateway_block_ip_transactions_hour"
  );
  const changeVatInEliat = $(
    "#woocommerce_payplus-payment-gateway_settings\\[change_vat_in_eilat\\]"
  );
  const keywordsEilat = $(
    "#woocommerce_payplus-payment-gateway_settings\\[keywords_eilat\\]"
  );
  const deleteError = $(".payplus-delete-error");
  const createInvoiceManual = $(".create-invoice-manual");
  const invoiceManualList = $(".invoice-manual-list");
  let listHidden = $(".list-hidden");
  //==================invoice not automatic  ======================
  const first_payment = $("#first_payment");
  const subsequent_payments = $("#subsequent_payments");
  const number_of_payments = $("#number_of_payments");
  const orderID = document.getElementById("post_ID");
  if (orderID) {
    payplus_print_payments_all();
    payplus_set_max_total();
  }
  //==================invoice not automatic  ======================
  if (createInvoiceManual && createInvoiceManual.val() === "no") {
    invoiceManualList.parents("tr").fadeOut();
  }
  createInvoiceManual.change(function () {
    if ($(this).val() === "yes") {
      invoiceManualList.closest("tr").fadeIn();
    } else {
      invoiceManualList.closest("tr").fadeOut();
    }
  });
  if (listHidden.length) {
    listHidden = listHidden.val().split(",");
    if (listHidden.length) {
      for (let i = 0; i < listHidden.length; i++) {
        let value = listHidden[i];
        $.each($(".invoice-manual-list").children("option"), function () {
          if (value != "" && value == $(this).val()) {
            $(this).prop("selected", true);
          }
        });
      }
      $(".invoice-manual-list")
        .children("option:first")
        .prop("selected", false);
    }
  }

  deleteError.click(function (event) {
    event.preventDefault();
    $(this).closest("tr").remove();
  });
  /******     Eliat start **remove***/
  if (!changeVatInEliat.prop("checked")) {
    keywordsEilat.closest("tr").fadeOut();
  }
  $(".invoice-manual-list").change(function () {
    let value = $(this).val();
    $(".list-hidden").val(value);
  });
  changeVatInEliat.change(function () {
    if ($(this).prop("checked")) {
      keywordsEilat.closest("tr").fadeIn();
    } else {
      keywordsEilat.closest("tr").fadeOut();
    }
  });
  /******     Eliat End ******/
  /******    block start ******/
  if (!blockIpTransactions.prop("checked")) {
    blockIpTransactionsHour.closest("tr").fadeOut();
  }
  blockIpTransactions.change(function () {
    if (blockIpTransactions.prop("checked")) {
      blockIpTransactionsHour.closest("tr").fadeIn();
    } else {
      blockIpTransactionsHour.closest("tr").fadeOut();
    }
  });
  /******    block  end ******/

  /******    transaction Type  start ******/
  if (transactionType.val() != 2) {
    checkAmountAuthorization.closest("tr").fadeOut();
  }
  transactionType.change(function () {
    if ($(this).val() == 2) {
      checkAmountAuthorization.closest("tr").fadeIn();
    } else {
      checkAmountAuthorization.closest("tr").fadeOut();
    }
  });
  /******    transaction type  end ******/

  if (enableApplePay && enableApplePay.prop("checked") === false) {
    tokenApplePay.parents("tr").fadeOut();
  }

  if (shippingwoo.prop("checked")) {
    globalShipping.closest("tr").fadeOut();
    globalShippingTax.closest("tr").fadeOut();
    globalShippingTaxRate.closest("tr").fadeOut();
  } else {
    if (enableGooglePay.prop("checked") || enableApplePay.prop("checked")) {
      globalShipping.attr("required", "required");
      globalShippingTax.attr("required", "required");
      globalShippingTaxRate.attr("required", "required");
    }
  }
  if (
    shippingwoo &&
    !shippingwoo.prop("checked") &&
    globalShipping &&
    !globalShipping.val()
  ) {
    globalShipping.val(0);
  }
  shippingwoo.change(function () {
    const checked = $(this).prop("checked");
    if (!checked) {
      globalShipping.closest("tr").fadeIn();
      globalShippingTax.closest("tr").fadeIn();
      globalShippingTaxRate.closest("tr").fadeIn();

      if (enableGooglePay.prop("checked") || enableApplePay.prop("checked")) {
        globalShipping.attr("required", "required");
        globalShippingTax.attr("required", "required");
        globalShippingTaxRate.attr("required", "required");
        if (globalShippingTax.val() == "none") {
          globalShippingTaxRate.closest("tr").fadeOut();
        }
      }
    } else {
      globalShipping.closest("tr").fadeOut();
      globalShippingTax.closest("tr").fadeOut();
      globalShippingTaxRate.closest("tr").fadeOut();
      globalShipping.removeAttr("required");
      globalShippingTax.removeAttr("required");
      globalShippingTaxRate.removeAttr("required");
    }
  });
  globalShippingTax.change(function () {
    let value = $(this).val();
    if (value == "none") {
      globalShippingTaxRate.find("option[value=0]").val("0");
      globalShippingTaxRate.parents("tr").fadeOut();
    } else {
      globalShippingTaxRate.find("option[value=0]").val("");
      globalShippingTaxRate.attr("required", "required");
      globalShippingTaxRate.parents("tr").fadeIn();
    }
  });
  enableGooglePay.change(function (event) {
    event.preventDefault();
    const slef = $(this);
    const checked = slef.prop("checked");
    const elementFieldset = enableGooglePay.parents("fieldset");
    $(".error-express-checkout").html("");
    if (checked) {
      elementFieldset.find(".loading-express").fadeIn();
      $.ajax({
        type: "post",
        dataType: "json",
        url: payplus_script_admin.ajax_url,
        data: {
          action: "payplus-express-checkout-initialized",
          method: "google-pay",
        },
        success: function (response) {
          elementFieldset.find(".loading-express").fadeOut();
          if (!response.status) {
            elementFieldset
              .find(".error-express-checkout")
              .html(
                "<b>payplus error : </b>" +
                  response.response_initialized.results.description
              );
            slef.prop("checked", false);
          }
        },
      });
    }
  });
  enableApplePay.change(function () {
    const checked = $(this).prop("checked");
    const elementFieldset = enableApplePay.parents("fieldset");
    const slef = $(this);
    $(".error-express-checkout").html("");
    if (checked) {
      elementFieldset.find(".loading-express").fadeIn();
      $.ajax({
        type: "post",
        dataType: "json",
        url: payplus_script_admin.ajax_url,
        data: {
          action: "payplus-express-checkout-initialized",
          method: "apple-pay",
        },
        success: function (response) {
          elementFieldset.find(".loading-express").fadeOut();
          if (!response.status) {
            let description = response.response_initialized.results.description
              .description
              ? response.response_initialized.results.description.description
              : response.response_initialized.results.description;
            elementFieldset
              .find(".error-express-checkout")
              .html("<b> payplus error : </b>" + description);
            slef.prop("checked", false);
          } else {
            tokenApplePay.parents("tr").fadeIn();
            tokenApplePay.val(
              response.response_initialized.apple_pay_identifier
            );
          }
        },
      });
    } else {
      tokenApplePay.parents("tr").fadeOut();
    }
  });

  $("#woocommerce_payplus-payment-gateway_transaction_type").change(
    function () {
      let value = $(this).val();
      if (value == 2) {
        $(
          "#woocommerce_payplus-payment-gateway_check_amount_authorization"
        ).prop("disabled", false);
      } else {
        $(
          "#woocommerce_payplus-payment-gateway_check_amount_authorization"
        ).prop("disabled", true);
      }
    }
  );
  if ($("#chargeByItems").is(":checked")) $("#totalorder").show();
  else $("#totalorder").hide();

  if ($("div#payplus-options").length) {
    toggle_iframe_height();
    toggle_foreign_invoice();
    toggle_invoice_options();

    // Check if the invoice option is enabled if it is check if the type of document is selected,
    // if not, show an error message and color the border of the select red and the text of the label
    if (
      $("#payplus_invoice_option\\[payplus_invoice_enable\\]").prop("checked")
    ) {
      let ids = [
        "#payplus_invoice_option\\[payplus_invoice_type_document_refund\\]",
        "#payplus_invoice_option\\[payplus_invoice_type_document\\]",
        "#payplus_invoice_option\\[payplus_invoice_status_order\\]",
      ];
      ids.forEach(function (id) {
        if ($(id).val() === "") {
          $(id).css("border-color", "red");
          var parent = $(id).parent().parent();
          var th = parent.children("th");
          th.css("color", "red");
        }
      });

      $("#payplus_invoice_option\\[display_only_invoice_docs\\]")
        .closest("tr")
        .hide();
    }

    $("#payplus_invoice_option\\[payplus_invoice_enable\\]").change(
      function () {
        if ($(this).is(":checked")) {
          $("#payplus_invoice_option\\[display_only_invoice_docs\\]")
            .closest("tr")
            .hide();
        } else {
          $("#payplus_invoice_option\\[display_only_invoice_docs\\]")
            .closest("tr")
            .show();
        }
      }
    );

    $(document).on(
      "change",
      "select#woocommerce_payplus-payment-gateway_display_mode",
      function () {
        toggle_iframe_height();
      }
    );
    $(document).on(
      "change",
      "select#woocommerce_payplus-payment-gateway_settings\\[paying_vat\\]",
      function () {
        toggle_foreign_invoice();
      }
    );
    $(document).on(
      "change",
      "select#woocommerce_payplus-payment-gateway_settings\\[initial_invoice\\]",
      function () {
        toggle_invoice_options();
        toggle_foreign_invoice();
      }
    );

    function toggle_iframe_height() {
      var display_mode = $(
        "select#woocommerce_payplus-payment-gateway_display_mode"
      ).val();
      if (display_mode == "redirect") {
        $("input#woocommerce_payplus-payment-gateway_iframe_height")
          .closest("tr")
          .hide();
        $("input#woocommerce_payplus-payment-gateway_import_applepay_script")
          .closest("tr")
          .hide();
        $("select#woocommerce_payplus-payment-gateway_display_mode")
          .siblings("p.description")
          .show();
      } else {
        if (display_mode === "iframe") {
          $("select#woocommerce_payplus-payment-gateway_display_mode")
            .siblings("p.description")
            .show();
          $("input#woocommerce_payplus-payment-gateway_iframe_height")
            .closest("tr")
            .show();
          $("input#woocommerce_payplus-payment-gateway_import_applepay_script")
            .closest("tr")
            .show();
        } else {
          $("input#woocommerce_payplus-payment-gateway_iframe_height")
            .closest("tr")
            .show();
          $("input#woocommerce_payplus-payment-gateway_import_applepay_script")
            .closest("tr")
            .show();
          $("select#woocommerce_payplus-payment-gateway_display_mode")
            .siblings("p.description")
            .hide();
        }
      }
    }

    function toggle_foreign_invoice() {
      var invoice_option = $(
        "select#woocommerce_payplus-payment-gateway_settings\\[paying_vat\\]"
      ).val();
      if (invoice_option == "2") {
        $(
          "input#woocommerce_payplus-payment-gateway_settings\\[paying_vat_iso_code\\]"
        )
          .closest("tr")
          .show();
        $(
          "input#woocommerce_payplus-payment-gateway_settings\\[foreign_invoices_lang\\]"
        )
          .closest("tr")
          .show();
      } else {
        $(
          "input#woocommerce_payplus-payment-gateway_settings\\[paying_vat_iso_code\\]"
        )
          .closest("tr")
          .hide();
        $(
          "input#woocommerce_payplus-payment-gateway_settings\\[foreign_invoices_lang\\]"
        )
          .closest("tr")
          .hide();
      }
    }

    function toggle_invoice_options() {
      var initial_invoice = $(
        "select#woocommerce_payplus-payment-gateway_settings\\[initial_invoice\\]"
      ).val();
      if (initial_invoice == "1") {
        $("select#woocommerce_payplus-payment-gateway_settings\\[paying_vat\\]")
          .closest("tr")
          .show();
        $(
          "input#woocommerce_payplus-payment-gateway_settings\\[paying_vat_iso_code\\]"
        )
          .closest("tr")
          .show();
        $(
          "input#woocommerce_payplus-payment-gateway_settings\\[foreign_invoices_lang\\]"
        )
          .closest("tr")
          .show();
      } else {
        $("select#woocommerce_payplus-payment-gateway_settings\\[paying_vat\\]")
          .closest("tr")
          .hide();
        $(
          "input#woocommerce_payplus-payment-gateway_settings\\[paying_vat_iso_code\\]"
        )
          .closest("tr")
          .hide();
        $(
          "input#woocommerce_payplus-payment-gateway_settings\\[foreign_invoices_lang\\]"
        )
          .closest("tr")
          .hide();
      }
    }
  }
  //==================invoice not automatic  ======================
  $(document).on("change", "#number_of_payments", function (event) {
    const classs = event.target.classList;
    payplus_set_payments(classs[0]);
  });
  $(document).on("click", ".type-payment", function (event) {
    event.preventDefault();
    const type = this.getAttribute("data-type");
    let rowId = null;

    document
      .querySelectorAll(".select-type-payment")
      .forEach(function (item, idx) {
        if (item.style.display == "block") {
          rowId = document.querySelector(
            ".select-type-payment." + item.classList[1] + " .row_id"
          ).value;
        }
        item.style.display = "none";
      });
    $(".type-payment").removeClass("hover");
    $(this).addClass("hover");
    document.querySelector(".select-type-payment" + "." + type).style.display =
      "block";
    document.querySelector(
      ".select-type-payment" + "." + type + " .row_id"
    ).value = rowId;
  });

  $(document).on("change", ".transaction_type", function (event) {
    event.preventDefault();

    const value = $(this).val();
    if (value == "payments" || value == "credit") {
      document.querySelector(".payplus_payment").style.display = "flex";
      const classs = event.target.classList;
      payplus_set_payments(classs[0]);
    } else {
      subsequent_payments.value = 0;
      number_of_payments.value = 2;
      first_payment.value = 0;
      document.querySelector(".payplus_payment").style.display = "none";
    }
  });
  $(document).on("click", ".payplus-full-amount", function (e) {
    e.preventDefault();
    let sum = e.target.getAttribute("data-sum");
    const classs = e.target.classList;
    const classPrice = classs.item(1);
    document.querySelector("." + classPrice + ".price").value = sum;
    if (document.querySelector(".payplus_payment").style.display == "flex") {
      payplus_set_payments(classPrice);
    }
  });
  $(document).on("input", ".price", function (e) {
    e.preventDefault();
    let m_price = e.target.value;
    let m_price_max = e.target.getAttribute("max");
    const classs = e.target.classList;

    if (m_price) {
      if (
        parseFloat(m_price) < 0 ||
        parseFloat(m_price_max) < parseFloat(m_price)
      ) {
        if (m_price == 0) {
          e.target.value = 0;
        } else {
          e.target.value = m_price_max;
        }
      }
      if (document.querySelector(".payplus_payment").style.display == "flex") {
        payplus_set_payments(classs[0]);
      } else {
        subsequent_payments.val(0);
        number_of_payments.val(2);
        first_payment.val(0);
        document.querySelector(".payplus_payment").style.display = "none";
      }
    } else {
      subsequent_payments.val(0);
      number_of_payments.val(2);
      first_payment.val(0);
    }
  });
  $(document).on("click", ".payplus-payment-button", function (e) {
    e.preventDefault();
    const id = e.target.id;

    this.classList.add("button-loading");
    let data = null;
    let index = 0;
    const orderID = $("#post_ID").val();
    let payments = JSON.parse(
      localStorage.getItem("payplus_payment_" + orderID)
    );
    let collection = document.getElementsByClassName(id);
    let collections = Array.prototype.filter.call(
      collection,
      (collection) =>
        collection.nodeName === "INPUT" || collection.nodeName === "SELECT"
    );
    data = payplus_set_collection(collections);
    if (data["row_id"]) {
      document.querySelector(`.row-payment-${data["row_id"]}`).remove();
    }
    data["order_id"] = orderID;
    data = Object.assign({}, data);
    if (data["price"] > 0 && data["price"] != "") {
      if (data["row_id"]) {
        payments[data["row_id"]] = data;
      } else {
        if (payments) {
          index = payments.length;
          data.row_id = index;
          payments[index] = data;
        } else {
          payments = [];
          data.row_id = index;
          payments[index] = data;
        }
      }

      localStorage.setItem(
        "payplus_payment_" + orderID,
        JSON.stringify(payments)
      );
      payplus_empty_field();
      payplus_print_payments_all();
      payplus_set_max_total();
      document
        .querySelectorAll(".select-type-payment")
        .forEach(function (item, idx) {
          item.style.display = "none";
        });
      jQuery(".type-payment").removeClass("hover");
    } else {
      alert(payplus_script_payment.error_price);
    }

    this.classList.remove("button-loading");
  });

  $(document).on("change", ".select-type-invoice", function (event) {
    event.preventDefault();
    const value = $(this).val();
    if (value == "inv_receipt" || value == "inv_tax_receipt") {
      document.getElementById("all-payment-invoice").style.display = "block";
      document.getElementById("payplus-table-payment").style.display = "table";
      document.getElementById("payplus_sum_payment").style.display = "block";
    } else {
      document.getElementById("all-payment-invoice").style.display = "none";
      document.getElementById("payplus-table-payment").style.display = "none";
      document.getElementById("payplus_sum_payment").style.display = "none";
    }
  });

  $(document).on("click", "#payplus-create-invoice", function (event) {
    event.preventDefault();
    const orderID = $(this).attr("data-id");
    const typeDocument = $("#select-type-invoice-" + orderID).val();
    const self = this;

    if (typeDocument == "") {
      alert(payplus_script_payment.error_payment_select_doc);
      return false;
    }
    if (
      allSum &&
      (typeDocument == "inv_receipt" || typeDocument == "inv_tax_receipt")
    ) {
      if (payplus_get_sum_payments() != allSum.value) {
        alert(payplus_script_payment.error_payment_sum);
        return false;
      }
    }

    $(self).addClass("button-loading");
    $(self).hide();
    $(self).next(".payplus_loader_gpp").fadeIn();
    const payments = payplus_get_storage(orderID);
    $.ajax({
      type: "post",
      dataType: "json",
      url: payplus_script_admin.ajax_url,
      data: {
        action: "payplus-create-invoice",
        order_id: orderID,
        payments: payments,
        typeDocument: typeDocument,
      },
      success: function (response) {
        $(self).removeClass("button-loading");
        $("#payment-payplus-dashboard,.payplus_loader").fadeOut();
        if (response.status) {
          localStorage.removeItem("payplus_payment_" + orderID);

          location.href = response.urlredirect;
        }
      },
    });

    return false;
  });

  $(document).ready(function () {
    $(".toggle-button").on("click", function (e) {
      e.preventDefault();
      var hiddenButtons = $(this).siblings(".hidden-buttons");
      hiddenButtons.toggleClass(
        "invoicePlusButtonHidden invoicePlusButtonVisible"
      );
      if (hiddenButtons.hasClass("invoicePlusButtonVisible")) {
        $(this).toggleClass("flip");
      } else {
        $(this).toggleClass("flip");
      }
    });
  });

  $(document).on("click", "#payplus-create-invoice-refund", function (event) {
    event.preventDefault();
    let orderId = $(this).attr("data-id");
    let typeDocument = $("#select-type-invoice-refund-" + orderId).val();
    let amount = $("#amount-refund-" + orderId).val();
    const self = this;
    if (typeDocument == "") {
      alert("No document type selected");
      return false;
    }
    const data = new FormData();
    data.append("action", "payplus-create-invoice-refund");
    data.append("order_id", orderId);
    data.append("amount", amount);
    data.append("type_document", typeDocument);
    $(self).addClass("button-loading");
    fetch(payplus_script_admin.ajax_url, {
      method: "post",
      headers: {
        Accept: "application/json",
      },
      body: data,
    })
      .then((response) => response.json())
      .then((response) => {
        $(self).removeClass("button-loading");
        if (response.status) {
          location.href = response.urlredirect;
        }
      });
    return false;
  });
  //==================invoice not automatic  ======================
});
//==================invoice not automatic  ======================
function payplus_empty_field() {
  const collections = document.querySelectorAll(
    "#all-payment-invoice input,#all-payment-invoice select"
  );
  const collectionDate = document.querySelectorAll(".create_at");
  const date = payplus_get_date();
  for (let i = 0; i < collections.length; i++) {
    const classs = collections[i].classList;

    if (classs[2] != "method_payment" && classs[2] != "number_of_payments") {
      collections[i].value = "";
    }
  }
  for (let i = 0; i < collectionDate.length; i++) {
    collectionDate[i].value = date;
  }
}
function payplus_set_collection(collection) {
  const data = [];

  for (let i = 0; i < collection.length; i++) {
    const classs = collection[i].classList;
    const elementClass = document.querySelector("." + classs[2]);
    if (elementClass) {
      data[classs[2]] = collection[i].value;
    }
  }
  return data;
}
function payplus_set_max_total(flag = true) {
  const orderID = document.getElementById("post_ID");
  const select_type_invoice = document.querySelector(".select-type-invoice");
  if (orderID) {
    if (price && allSum) {
      let payplus_sum_payment = document.getElementById("payplus_sum_payment");
      let sum = payplus_get_sum_payments().toFixed(2);

      sum = allSum.value - sum;
      if (!sum) {
        sum = 0;
      } else {
        sum = Number(sum.toFixed(2));
      }

      for (let i = 0; i < price.length; i++) {
        price[i].setAttribute("max", sum);
        if (!sum) {
          price[i].setAttribute("min", sum);
        }
        if (flag) {
          price[i].value = sum;
        }
      }
      for (let i = 0; i < fullAmount.length; i++) {
        fullAmount[i].setAttribute("data-sum", sum);
      }

      if (
        select_type_invoice.value == "inv_receipt" ||
        select_type_invoice.value == "inv_tax_receipt"
      ) {
        document.getElementById("all-payment-invoice").style.display = "block";
        payplus_sum_payment.style.display = "block";
      }

      //  document.querySelector('.payplus_payment').style.display='none';
    }
  }
}
function payplus_get_sum_payments(rowId = -1) {
  const orderID = document.getElementById("post_ID");

  let sum = 0;
  if (orderID) {
    const payments = JSON.parse(
      localStorage.getItem("payplus_payment_" + orderID.value)
    );
    if (payments) {
      if (rowId != -1) {
        sum = payments.reduce((accumulator, object) => {
          if (object.row_id != rowId) {
            return accumulator + parseFloat(object.price);
          }
          return accumulator;
        }, 0);
      } else {
        sum = payments.reduce((accumulator, object) => {
          return accumulator + parseFloat(object.price);
        }, 0);
      }
    }
  }
  return sum;
}
function payplus_set_payments(nameClass) {
  const m_payment = parseFloat(number_of_payments.value);
  let m_price = document.querySelector("." + nameClass + ".price");
  m_price = m_price.value;
  if (m_price && m_payment) {
    const onePayment = (m_price / m_payment).toFixed(2);
    first_payment.value = onePayment;
    subsequent_payments.value = (m_price - onePayment).toFixed(2);
  }
}
function payplus_get_date() {
  const date = new Date();
  let day = date.getDate();
  let month = date.getMonth() + 1;

  let year = date.getFullYear();
  if (month < 10) {
    month = "0" + month;
  }
  if (day < 10) {
    day = "0" + day;
  }
  let currentDate = `${year}-${month}-${day}`;

  return currentDate;
}
function payplus_print_payments(data, index) {
  let details = "";
  let detailsAll = [
    "bank_number",
    "account_number",
    "branch_number",
    "check_number",
    "four_digits",
    "brand_name",
    "transaction_type",
    "number_of_payments",
    "first_payment",
    "subsequent_payments",
    "payment_app",
    "transaction_id",
    "payer_account",
    "notes",
  ];
  table_payment = jQuery(".payplus-table-payment tbody");
  const printData = Object.assign({}, data);

  Object.keys(data).forEach(function (key, index) {
    if (detailsAll.includes(key) && data[key] != undefined && data[key] != "") {
      let capitalized = key.charAt(0).toUpperCase() + key.slice(1);
      capitalized = capitalized.replace("_", " ");

      if (
        (key == "first_payment" ||
          key == "subsequent_payments" ||
          key == "number_of_payments") &&
        data["transaction_type"] != "payments" &&
        data["transaction_type"] != "credit"
      ) {
        delete printData[key];
        return;
      }

      printData[key] = "<p><b> " + capitalized + "</b> : " + data[key] + "</p>";
    } else {
      delete printData[key];
    }
  });
  if (payplus_obj_isObjectEmpty(printData)) {
    details = payplus_obj_ToString(printData);
  }
  const pricePayment =
    data && data.price ? Number(parseFloat(data.price).toFixed(2)) : 0;

  let date = data.create_at.split("-");
  date = `${date[2]}-${date[1]}-${date[0]}`;

  let html = `<tr class="row-payment-${data.row_id}">
        <td>
        <a class="link-action" data-id=${
          data.row_id
        } onclick="payplus_delete_element(${data.row_id})">${
    payplus_script_payment.btn_delete
  }</a>
        <a class="link-action" data-id=${
          data.row_id
        } onclick="payplus_edit_element(${data.row_id})">${
    payplus_script_payment.btn_edit
  }</a>
        </td>
         <td><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">
                    ${
                      payplus_script_payment.currency_symbol
                    }</span>${pricePayment}</bdi></span>
                 </td>
                    <td>${details}</td>
                  <td>${data.method_payment.replace("-", " ")}</td>
                 <td>${date}</td>
                  
            </tr>`;
  if (table_payment.html() == "") {
    table_payment.html(html);
  } else {
    table_payment.append(html);
  }
}
function payplus_print_payments_all() {
  const orderID = document.getElementById("post_ID");
  table_payment = jQuery(".payplus-table-payment tbody");
  let payplus_sum_payment = jQuery("#payplus_sum_payment");
  if (orderID && table_payment) {
    const payments = JSON.parse(
      localStorage.getItem("payplus_payment_" + orderID.value)
    );
    if (payments) {
      table_payment.html("");
      if (payments.length) {
        let html = `<strong>${payplus_script_payment.payplus_sum} : ${
          payplus_script_payment.currency_symbol
        }${payplus_get_sum_payments()}  </strong>`;
        for (let index = 0; index < payments.length; index++) {
          payplus_print_payments(payments[index], index);
        }
        payplus_sum_payment.html(html);
        payplus_sum_payment.addClass("payplus_sum_payment");
      } else {
        payplus_sum_payment.html("");
        /* if(  document.getElementById('payplus-table-payment').getAttribute('data-method')!="1"){
                     table_payment.html('');
                }*/
      }
    }
  }
}
function payplus_delete_element(index, flag = false) {
  let flagDelete = true;
  if (!flag) {
    flagDelete = confirm(payplus_script_payment.delete_confim);
  }
  if (flagDelete) {
    document
      .querySelectorAll(".select-type-payment")
      .forEach(function (item, idx) {
        item.style.display = "none";
      });
    jQuery(".type-payment").removeClass("hover");
    const orderID = document.getElementById("post_ID");
    if (orderID) {
      let payments = JSON.parse(
        localStorage.getItem("payplus_payment_" + orderID.value)
      );
      payments = payments.filter((x) => x.row_id != index);
      if (!payments.length) {
        payments = [];
      }
      localStorage.setItem(
        "payplus_payment_" + orderID.value,
        JSON.stringify(payments)
      );
      payplus_print_payments_all();
      payplus_set_max_total(!flag);
    }
  }
}
function payplus_edit_element(index) {
  const orderID = document.getElementById("post_ID");
  if (orderID) {
    let payments = JSON.parse(
      localStorage.getItem("payplus_payment_" + orderID.value)
    );
    document
      .querySelectorAll(".select-type-payment")
      .forEach(function (item, idx) {
        item.style.display = "none";
      });
    if (payments[index]) {
      const objectPayment = payments[index];
      const method_payment = objectPayment.method_payment + "-payment-payplus";
      const elemnetDisplay = document.querySelector(
        ".select-type-payment." + objectPayment.method_payment
      );
      let collection = document.getElementsByClassName(method_payment);
      let collections = Array.prototype.filter.call(
        collection,
        (collection) =>
          collection.nodeName === "INPUT" || collection.nodeName === "SELECT"
      );
      for (let i = 0; i < collections.length; i++) {
        const classs = collections[i].classList;
        document.querySelector("." + method_payment + "." + classs[2]).value =
          objectPayment[classs[2]];
        if (classs[2] == "price") {
          let sum =
            allSum.value - payplus_get_sum_payments(objectPayment.row_id);
          sum = sum.toFixed(2);
          document
            .querySelector("." + method_payment + "." + classs[2])
            .setAttribute("max", sum);
          for (let j = 0; j < price.length; j++) {
            price[j].value = sum;
          }
          document.querySelector("." + method_payment + "." + classs[2]).value =
            objectPayment["price"];
          for (let j = 0; j < fullAmount.length; j++) {
            fullAmount[j].setAttribute("data-sum", sum);
          }
        }
      }
      if (
        objectPayment.transaction_type == "payments" ||
        objectPayment.transaction_type == "credit"
      ) {
        document.querySelector(".payplus_payment").style.display = "flex";
      } else {
        document.querySelector(".payplus_payment").style.display = "none";
      }
      jQuery(".type-payment").removeClass("hover");
      jQuery("." + objectPayment.method_payment + ".type-payment").addClass(
        "hover"
      );
      elemnetDisplay.style.display = "block";
      document.getElementById("all-payment-invoice").style.display = "block";
    }
  }
}

function payplus_get_storage(orderID) {
  const payments = JSON.parse(
    localStorage.getItem("payplus_payment_" + orderID)
  );
  return payments;
}
function payplus_obj_ToString(obj) {
  var str = "";
  for (var p in obj) {
    if (Object.prototype.hasOwnProperty.call(obj, p)) {
      str += obj[p];
    }
  }
  return str;
}
function payplus_obj_isObjectEmpty(obj) {
  return Object.keys(obj).length > 0;
}
//==================invoice not automatic  ======================
