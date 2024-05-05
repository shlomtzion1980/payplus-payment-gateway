// jquery wait for document ready
jQuery(document).ready(function ($) {
  var userId = payplus_script_thankyou.userId;
  let orderId = payplus_script_thankyou.orderId;
  let token = payplus_script_thankyou.token;

  //show loader on page load
  $(".payplus_loader").fadeIn();

  $("input[name='saveToken']").on("click", function (e) {
    $(".payplus_loader").fadeIn();
  });

  $("input[name='deleteToken']").on("click", function (e) {
    e.preventDefault();
    $("#newToken").css("display", "none");
    let data = {
      action: "payplus-delete-token",
      userId: userId,
      orderId: orderId,
    };
    $.post(payplus_script_thankyou.ajax_url, data, function (response) {
      // console.log(response);
    });
  });

  // show $(".payplus_loader").fadeIn(); for 2 seconds and then fadeOut add it to a async function that will wait for 2 seconds and then console.log("User ID: ", userId); and then preventDefault() to stop the form from submitting

  setTimeout(async function () {
    let data = {
      action: "payplus-check-tokens",
      userId: userId,
    };
    $.post(payplus_script_thankyou.ajax_url, data, function (response) {
      if (response.indexOf(token) !== -1) {
        $("#newToken").css("display", "none");
      }
      $(".payplus_loader").fadeOut();
    });
  }, 3000);
});
