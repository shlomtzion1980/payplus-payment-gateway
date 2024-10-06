// jQuery(document).ready(function ($) {
//   $("button.add_to_cart_button").on("click", function (e) {
//     e.preventDefault();

//     var productId = $(this)[0].dataset.product_id; // Assuming you have a data attribute with product ID
//     var quantity = $(this).siblings(".product-quantity-input").val(); // Get the quantity from an input field

//     $.ajax({
//       type: "POST",
//       url: payplus_script.ajax_url,
//       data: {
//         action: "add_to_cart",
//         product_id: productId,
//         quantity: quantity,
//       },
//       success: function (response) {
//         // if (response.success) {
//         //   // Update cart count or show success message
//         //   alert(
//         //     "Product added! Total items in cart: " + response.data.cart_count
//         //   );
//         // } else {
//         //   alert("Error adding product.");
//         // }
//       },
//     });
//   });
// });
