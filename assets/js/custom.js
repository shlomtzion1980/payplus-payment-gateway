// jquery wait for document ready
jQuery(document).ready(function ($) {
  /// run ajax request to
  console.log("PayPlus payment gateway plugin is active");
  //   $("#custom-button-save-token").click(function (e) {
  //get #custom-button-save-token value to userId variable
  var userId = $("#user_id").val();
  let orderId = $("#order_id").val();
  //   console.log(userId);
  // value of element by name token
  let token = $("input[name='token']").val();
  $("input[name='deleteToken']").on("click", function (e) {
    e.preventDefault();
    $("#newToken").css("display", "none");
    var d = {
      action: "payplus-delete-token",
      userId: userId,
      orderId: orderId,
    };
    $.post(payplus_script_admin.ajax_url, d, function (response) {
      console.log(response);
    });
  });
  // show $(".payplus_loader").fadeIn(); for 2 seconds and then fadeOut add it to a async function that will wait for 2 seconds and then console.log("User ID: ", userId); and then preventDefault() to stop the form from submitting
  $(".payplus_loader").fadeIn();
  setTimeout(async function () {
    $(".payplus_loader").fadeOut();
    $.post(payplus_script_admin.ajax_url, data, function (response) {
      console.log(response);
      console.log(response.indexOf(token));
      if (response.indexOf(token) !== -1) {
        // console.log("will not save token!");
        $("#newToken").css("display", "none");
      } else {
        // console.log("will save token!");
      }
    });
  }, 3000);

  //   console.log("User ID: ", userId);

  var data = {
    action: "payplus-save-token",
    userId: userId,
  };
});
