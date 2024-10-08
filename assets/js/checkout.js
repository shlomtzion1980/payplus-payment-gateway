/* global wc_checkout_params */

jQuery(function ($) {
    // wc_checkout_params is required to continue, ensure the object exists
    if (typeof wc_checkout_params === "undefined") {
        return false;
    }

    $.blockUI.defaults.overlayCSS.cursor = "default";
    let hasSavedCCs = $(".woocommerce-SavedPaymentMethods-token");

    //function to hide other payment methods when subscription order
    function subscriptionOrderHide() {
        // Select all elements with the wc_payment_method class inside .wc_payment_methods.payment_methods.methods
        $(
            ".wc_payment_methods.payment_methods.methods .wc_payment_method"
        ).each(function () {
            // Check if the element has any class that starts with 'payment_method_payplus-payment-gateway-'
            var classes = $(this).attr("class").split(/\s+/);
            classes.forEach(
                function (className) {
                    if (
                        className.startsWith(
                            "payment_method_payplus-payment-gateway-"
                        )
                    ) {
                        $(this).remove();
                    }
                }.bind(this)
            );
        });
    }

    if (payplus_script_checkout.isHostedFields && hasSavedCCs.length === 0) {
        $("input#payment_method_payplus-payment-gateway-hostedfields").prop(
            "checked",
            true
        );

        setTimeout(function () {
            $("button#place_order").hide();
            $("div.container.hostedFields").show();
        }, 1000);
    } else {
        $("input#payment_method_payplus-payment-gateway-hostedfields").prop(
            "checked",
            false
        );
    }
    if (payplus_script_checkout.isHostedFields) {
        $(document).on("change", 'input[name="payment_method"]', function () {
            // Check if the hosted fields radio input is NOT checked
            if (
                !$(
                    "input#payment_method_payplus-payment-gateway-hostedfields"
                ).is(":checked")
            ) {
                $("div.container.hostedFields").show();
                $(".container.hostedFields").hide();
                $("button#place_order").show();
            } else {
                $("div.container.hostedFields").show();
                $("button#place_order").hide();
            }
        });
    }

    var wc_checkout_form = {
        updateTimer: false,
        dirtyInput: false,
        selectedPaymentMethod: false,
        xhr: false,
        $order_review: $("#order_review"),
        $checkout_form: $("form.checkout"),

        init: function () {
            $(document.body).on("update_checkout", this.update_checkout);
            $(document.body).on("init_checkout", this.init_checkout);

            // Payment methods
            this.$checkout_form.on(
                "click",
                'input[name="payment_method"]',
                this.payment_method_selected
            );

            if ($(document.body).hasClass("woocommerce-order-pay")) {
                this.$order_review.on(
                    "click",
                    'input[name="payment_method"]',
                    this.payment_method_selected
                );
                this.$order_review.on("submit", this.submitOrder);
                this.$order_review.attr("novalidate", "novalidate");
            }

            // Prevent HTML5 validation which can conflict.
            this.$checkout_form.attr("novalidate", "novalidate");

            // Form submission
            this.$checkout_form.on("submit", this.submit);

            // Inline validation
            this.$checkout_form.on(
                "input validate change",
                ".input-text, select, input:checkbox",
                this.validate_field
            );

            // Manual trigger
            this.$checkout_form.on("update", this.trigger_update_checkout);

            // Inputs/selects which update totals
            this.$checkout_form.on(
                "change",
                'select.shipping_method, input[name^="shipping_method"], #ship-to-different-address input, .update_totals_on_change select, .update_totals_on_change input[type="radio"], .update_totals_on_change input[type="checkbox"]',
                this.trigger_update_checkout
            ); // eslint-disable-line max-len
            this.$checkout_form.on(
                "change",
                ".address-field select",
                this.input_changed
            );
            this.$checkout_form.on(
                "change",
                ".address-field input.input-text, .update_totals_on_change input.input-text",
                this.maybe_input_changed
            ); // eslint-disable-line max-len
            this.$checkout_form.on(
                "keydown",
                ".address-field input.input-text, .update_totals_on_change input.input-text",
                this.queue_update_checkout
            ); // eslint-disable-line max-len

            // Address fields
            this.$checkout_form.on(
                "change",
                "#ship-to-different-address input",
                this.ship_to_different_address
            );

            // Trigger events
            this.$checkout_form
                .find("#ship-to-different-address input")
                .trigger("change");
            this.init_payment_methods();

            // Update on page load
            if (wc_checkout_params.is_checkout === "1") {
                $(document.body).trigger("init_checkout");
            }
            if (wc_checkout_params.option_guest_checkout === "yes") {
                $("input#createaccount")
                    .on("change", this.toggle_create_account)
                    .trigger("change");
            }
        },
        init_payment_methods: function () {
            var $payment_methods = $(".woocommerce-checkout").find(
                'input[name="payment_method"]'
            );

            // If there is one method, we can hide the radio input
            if (1 === $payment_methods.length) {
                $payment_methods.eq(0).hide();
            }

            // If there was a previously selected method, check that one.
            if (wc_checkout_form.selectedPaymentMethod) {
                $("#" + wc_checkout_form.selectedPaymentMethod).prop(
                    "checked",
                    true
                );
            }

            // If there are none selected, select the first.
            if (0 === $payment_methods.filter(":checked").length) {
                $payment_methods.eq(0).prop("checked", true);
            }

            // Get name of new selected method.
            var checkedPaymentMethod = $payment_methods
                .filter(":checked")
                .eq(0)
                .prop("id");

            if ($payment_methods.length > 1) {
                // Hide open descriptions.
                $('div.payment_box:not(".' + checkedPaymentMethod + '")')
                    .filter(":visible")
                    .slideUp(0);
            }

            // Trigger click event for selected method
            $payment_methods.filter(":checked").eq(0).trigger("click");
        },
        get_payment_method: function () {
            return wc_checkout_form.$checkout_form
                .find('input[name="payment_method"]:checked')
                .val();
        },
        payment_method_selected: function (e) {
            closePayplusIframe(true);
            e.stopPropagation();

            if ($(".payment_methods input.input-radio").length > 1) {
                var target_payment_box = $(
                        "div.payment_box." + $(this).attr("ID")
                    ),
                    is_checked = $(this).is(":checked");

                if (is_checked && !target_payment_box.is(":visible")) {
                    $("div.payment_box").filter(":visible").slideUp(230);

                    if (is_checked) {
                        target_payment_box.slideDown(230);
                    }
                }
            }

            if ($(this).data("order_button_text")) {
                $("#place_order").text($(this).data("order_button_text"));
            } else {
                $("#place_order").text($("#place_order").data("value"));
            }

            var selectedPaymentMethod = $(
                '.woocommerce-checkout input[name="payment_method"]:checked'
            ).attr("id");

            if (
                selectedPaymentMethod !== wc_checkout_form.selectedPaymentMethod
            ) {
                $(document.body).trigger("payment_method_selected");
            }

            wc_checkout_form.selectedPaymentMethod = selectedPaymentMethod;
        },
        toggle_create_account: function () {
            $("div.create-account").hide();

            if ($(this).is(":checked")) {
                // Ensure password is not pre-populated.
                $("#account_password").val("").trigger("change");
                $("div.create-account").slideDown();
            }
        },
        init_checkout: function () {
            $(document.body).trigger("update_checkout");
        },
        maybe_input_changed: function (e) {
            if (wc_checkout_form.dirtyInput) {
                wc_checkout_form.input_changed(e);
            }
        },
        input_changed: function (e) {
            wc_checkout_form.dirtyInput = e.target;
            wc_checkout_form.maybe_update_checkout();
        },
        queue_update_checkout: function (e) {
            var code = e.keyCode || e.which || 0;

            if (code === 9) {
                return true;
            }

            wc_checkout_form.dirtyInput = this;
            wc_checkout_form.reset_update_checkout_timer();
            wc_checkout_form.updateTimer = setTimeout(
                wc_checkout_form.maybe_update_checkout,
                "1000"
            );
        },
        trigger_update_checkout: function () {
            wc_checkout_form.reset_update_checkout_timer();
            wc_checkout_form.dirtyInput = false;
            $(document.body).trigger("update_checkout");
        },
        maybe_update_checkout: function () {
            var update_totals = true;

            if ($(wc_checkout_form.dirtyInput).length) {
                var $required_inputs = $(wc_checkout_form.dirtyInput)
                    .closest("div")
                    .find(".address-field.validate-required");

                if ($required_inputs.length) {
                    $required_inputs.each(function () {
                        if ($(this).find("input.input-text").val() === "") {
                            update_totals = false;
                        }
                    });
                }
            }
            if (update_totals) {
                wc_checkout_form.trigger_update_checkout();
            }
        },
        ship_to_different_address: function () {
            $("div.shipping_address").hide();
            if ($(this).is(":checked")) {
                $("div.shipping_address").slideDown();
            }
        },
        reset_update_checkout_timer: function () {
            clearTimeout(wc_checkout_form.updateTimer);
        },
        is_valid_json: function (raw_json) {
            try {
                var json = JSON.parse(raw_json);

                return json && "object" === typeof json;
            } catch (e) {
                return false;
            }
        },
        validate_field: function (e) {
            var $this = $(this),
                $parent = $this.closest(".form-row"),
                validated = true,
                validate_required = $parent.is(".validate-required"),
                validate_email = $parent.is(".validate-email"),
                validate_phone = $parent.is(".validate-phone"),
                pattern = "",
                event_type = e.type;

            if ("input" === event_type) {
                $parent.removeClass(
                    "woocommerce-invalid woocommerce-invalid-required-field woocommerce-invalid-email woocommerce-invalid-phone woocommerce-validated"
                ); // eslint-disable-line max-len
            }

            if ("validate" === event_type || "change" === event_type) {
                if (validate_required) {
                    if (
                        "checkbox" === $this.attr("type") &&
                        !$this.is(":checked")
                    ) {
                        $parent
                            .removeClass("woocommerce-validated")
                            .addClass(
                                "woocommerce-invalid woocommerce-invalid-required-field"
                            );
                        validated = false;
                    } else if ($this.val() === "") {
                        $parent
                            .removeClass("woocommerce-validated")
                            .addClass(
                                "woocommerce-invalid woocommerce-invalid-required-field"
                            );
                        validated = false;
                    }
                }

                if (validate_email) {
                    if ($this.val()) {
                        /* https://stackoverflow.com/questions/2855865/jquery-validate-e-mail-address-regex */
                        pattern = new RegExp(
                            /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[0-9a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i
                        ); // eslint-disable-line max-len

                        if (!pattern.test($this.val())) {
                            $parent
                                .removeClass("woocommerce-validated")
                                .addClass(
                                    "woocommerce-invalid woocommerce-invalid-email woocommerce-invalid-phone"
                                ); // eslint-disable-line max-len
                            validated = false;
                        }
                    }
                }

                if (validate_phone) {
                    pattern = new RegExp(/[\s\#0-9_\-\+\/\(\)\.]/g);

                    if (0 < $this.val().replace(pattern, "").length) {
                        $parent
                            .removeClass("woocommerce-validated")
                            .addClass(
                                "woocommerce-invalid woocommerce-invalid-phone"
                            );
                        validated = false;
                    }
                }

                if (validated) {
                    $parent
                        .removeClass(
                            "woocommerce-invalid woocommerce-invalid-required-field woocommerce-invalid-email woocommerce-invalid-phone"
                        )
                        .addClass("woocommerce-validated"); // eslint-disable-line max-len
                }
            }
        },
        update_checkout: function (event, args) {
            // Small timeout to prevent multiple requests when several fields update at the same time
            wc_checkout_form.reset_update_checkout_timer();
            wc_checkout_form.updateTimer = setTimeout(
                wc_checkout_form.update_checkout_action,
                "5",
                args
            );
        },
        update_checkout_action: function (args) {
            if (wc_checkout_form.xhr) {
                wc_checkout_form.xhr.abort();
            }

            if ($("form.checkout").length === 0) {
                return;
            }

            args =
                typeof args !== "undefined"
                    ? args
                    : {
                          update_shipping_method: true,
                      };

            var country = $("#billing_country").val(),
                state = $("#billing_state").val(),
                postcode = $(":input#billing_postcode").val(),
                city = $("#billing_city").val(),
                address = $(":input#billing_address_1").val(),
                address_2 = $(":input#billing_address_2").val(),
                s_country = country,
                s_state = state,
                s_postcode = postcode,
                s_city = city,
                s_address = address,
                s_address_2 = address_2,
                $required_inputs = $(wc_checkout_form.$checkout_form).find(
                    ".address-field.validate-required:visible"
                ),
                has_full_address = true;

            if ($required_inputs.length) {
                $required_inputs.each(function () {
                    if ($(this).find(":input").val() === "") {
                        has_full_address = false;
                    }
                });
            }

            if ($("#ship-to-different-address").find("input").is(":checked")) {
                s_country = $("#shipping_country").val();
                s_state = $("#shipping_state").val();
                s_postcode = $(":input#shipping_postcode").val();
                s_city = $("#shipping_city").val();
                s_address = $(":input#shipping_address_1").val();
                s_address_2 = $(":input#shipping_address_2").val();
            }

            var data = {
                security: wc_checkout_params.update_order_review_nonce,
                payment_method: wc_checkout_form.get_payment_method(),
                country: country,
                state: state,
                postcode: postcode,
                city: city,
                address: address,
                address_2: address_2,
                s_country: s_country,
                s_state: s_state,
                s_postcode: s_postcode,
                s_city: s_city,
                s_address: s_address,
                s_address_2: s_address_2,
                has_full_address: has_full_address,
                post_data: $("form.checkout").serialize(),
            };

            if (false !== args.update_shipping_method) {
                var shipping_methods = {};

                // eslint-disable-next-line max-len
                $(
                    'select.shipping_method, input[name^="shipping_method"][type="radio"]:checked, input[name^="shipping_method"][type="hidden"]'
                ).each(function () {
                    shipping_methods[$(this).data("index")] = $(this).val();
                });

                data.shipping_method = shipping_methods;
            }

            $(
                ".woocommerce-checkout-payment, .woocommerce-checkout-review-order-table"
            ).block({
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            });

            wc_checkout_form.xhr = $.ajax({
                type: "POST",
                url: wc_checkout_params.wc_ajax_url
                    .toString()
                    .replace("%%endpoint%%", "update_order_review"),
                data: data,
                success: function (data) {
                    // Reload the page if requested
                    if (data && true === data.reload) {
                        window.location.reload();
                        return;
                    }

                    // Remove any notices added previously
                    $(".woocommerce-NoticeGroup-updateOrderReview").remove();

                    var termsCheckBoxChecked = $("#terms").prop("checked");

                    // Save payment details to a temporary object
                    var paymentDetails = {};
                    $(".payment_box :input").each(function () {
                        var ID = $(this).attr("id");

                        if (ID) {
                            if (
                                $.inArray($(this).attr("type"), [
                                    "checkbox",
                                    "radio",
                                ]) !== -1
                            ) {
                                paymentDetails[ID] = $(this).prop("checked");
                            } else {
                                paymentDetails[ID] = $(this).val();
                            }
                        }
                    });

                    // Always update the fragments
                    let hostedFields = $(".hostedFields").prop("outerHTML");
                    if (data && data.fragments) {
                        $.each(data.fragments, function (key, value) {
                            if (
                                !wc_checkout_form.fragments ||
                                wc_checkout_form.fragments[key] !== value
                            ) {
                                $(key).replaceWith(value);
                                payplus_script_checkout.isSubscriptionOrder
                                    ? subscriptionOrderHide()
                                    : null;
                                if (
                                    !document.querySelector(
                                        "#payplus-checkout-image-div"
                                    )
                                ) {
                                    addCustomIcons();
                                }
                                let loopImages = true;
                                multiPassIcons(loopImages);
                            }
                            $(key).unblock();
                        });
                        wc_checkout_form.fragments = data.fragments;
                        if (payplus_script_checkout.isHostedFields) {
                            putHostedFields();
                        }
                    }
                    var coupons = [];
                    var couponCode;
                    var totalDiscount = 0;
                    let isSubmitting = false;
                    $('#shipping_method input[type="radio"]').on(
                        "change",
                        function () {
                            if (isSubmitting) return; // Prevent multiple submissions
                            isSubmitting = true; // Set flag to true to block further submissions

                            // Recreate and prepend the .container element after fragments update
                            // Get the selected shipping method ID
                            var selectedShippingMethod = $(
                                'input[name="shipping_method[0]"]:checked'
                            ).val();
                            console.log(
                                "Selected shipping method ID: " +
                                    selectedShippingMethod
                            );

                            // Find the label associated with the selected shipping method
                            var label = $(
                                'input[name="shipping_method[0]"]:checked'
                            )
                                .closest("li")
                                .find("label")
                                .text();

                            // Adjust the regex to support both $ and ₪ (or any currency symbol at start or end)
                            var priceMatch = label.match(
                                /(\$|₪)\s*([0-9.,]+)|([0-9.,]+)\s*(\$|₪)/
                            );

                            let shippingPrice = 0;
                            if (priceMatch) {
                                var currency = priceMatch[1] || priceMatch[4]; // Captures the currency symbol
                                shippingPrice = priceMatch[2] || priceMatch[3]; // Captures the price number
                                console.log(
                                    "Shipping price: " +
                                        shippingPrice +
                                        " " +
                                        currency
                                );
                                // Your custom logic with the shipping price and currency
                            }

                            let totalShipping = shippingPrice;
                            jQuery.ajax({
                                type: "post",
                                dataType: "json",
                                url: payplus_script.ajax_url,
                                data: {
                                    action: "update-hosted-payment",
                                    totalShipping: totalShipping,
                                    _ajax_nonce: payplus_script.frontNonce,
                                },
                                success: function (response) {
                                    console.log(response);
                                },
                                complete: function () {
                                    // Reset flag after completion of request
                                    isSubmitting = false;
                                },
                            });
                        }
                    );

                    if (payplus_script_checkout.isHostedFields) {
                        $(document.body).on("updated_checkout", function () {
                            putHostedFields();
                        });
                    }

                    // Recheck the terms and conditions box, if needed
                    if (termsCheckBoxChecked) {
                        $("#terms").prop("checked", true);
                    }

                    function putHostedFields() {
                        var $paymentMethod = jQuery(
                            "#payment_method_payplus-payment-gateway-hostedfields"
                        );

                        // Find the closest parent <li>
                        var $topLi = jQuery(".pp_iframe_h");

                        // Select the existing div element that you want to move
                        var $newDiv = jQuery(
                            "body > div.container.hostedFields"
                        );
                        let $hostedRow = $newDiv.find(".row").first();
                        if (
                            $paymentMethod.length &&
                            $topLi.length &&
                            $newDiv.length
                        ) {
                            if (payplus_script_checkout.hostedFieldsWidth) {
                                $hostedRow.attr("style", function (i, style) {
                                    // Return the width with !important without adding an extra semicolon
                                    return (
                                        "width: " +
                                        payplus_script_checkout.hostedFieldsWidth +
                                        "% !important;" +
                                        (style ? " " + style : "")
                                    );
                                });
                            }
                            $newDiv.css("display", "none");
                            // Move the existing div to the top <li> of the payment method
                            $topLi.append($newDiv);
                        }
                    }

                    // Fill in the payment details if possible without overwriting data if set.
                    if (!$.isEmptyObject(paymentDetails)) {
                        $(".payment_box :input").each(function () {
                            var ID = $(this).attr("id");
                            if (ID) {
                                if (
                                    $.inArray($(this).attr("type"), [
                                        "checkbox",
                                        "radio",
                                    ]) !== -1
                                ) {
                                    $(this)
                                        .prop("checked", paymentDetails[ID])
                                        .trigger("change");
                                } else if (
                                    $.inArray($(this).attr("type"), [
                                        "select",
                                    ]) !== -1
                                ) {
                                    $(this)
                                        .val(paymentDetails[ID])
                                        .trigger("change");
                                } else if (
                                    null !== $(this).val() &&
                                    0 === $(this).val().length
                                ) {
                                    $(this)
                                        .val(paymentDetails[ID])
                                        .trigger("change");
                                }
                            }
                        });
                    }

                    // Check for error
                    if (data && "failure" === data.result) {
                        var $form = $("form.checkout");

                        // Remove notices from all sources
                        $(".woocommerce-error, .woocommerce-message").remove();

                        // Add new errors returned by this event
                        if (data.messages) {
                            $form.prepend(
                                '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview">' +
                                    data.messages +
                                    "</div>"
                            ); // eslint-disable-line max-len
                        } else {
                            $form.prepend(data);
                        }

                        // Lose focus for all fields
                        $form
                            .find(".input-text, select, input:checkbox")
                            .trigger("validate")
                            .trigger("blur");

                        wc_checkout_form.scroll_to_notices();
                    }

                    // Re-init methods
                    wc_checkout_form.init_payment_methods();

                    // Fire updated_checkout event.
                    $(document.body).trigger("updated_checkout", [data]);
                },
            });
        },
        handleUnloadEvent: function (e) {
            // Modern browsers have their own standard generic messages that they will display.
            // Confirm, alert, prompt or custom message are not allowed during the unload event
            // Browsers will display their own standard messages

            // Check if the browser is Internet Explorer
            if (
                navigator.userAgent.indexOf("MSIE") !== -1 ||
                !!document.documentMode
            ) {
                // IE handles unload events differently than modern browsers
                e.preventDefault();
                return undefined;
            }

            return true;
        },
        attachUnloadEventsOnSubmit: function () {
            $(window).on("beforeunload", this.handleUnloadEvent);
        },
        detachUnloadEventsOnSubmit: function () {
            $(window).off("beforeunload", this.handleUnloadEvent);
        },
        blockOnSubmit: function ($form) {
            var isBlocked = $form.data("blockUI.isBlocked");

            if (1 !== isBlocked) {
                $form.block({
                    message: null,
                    overlayCSS: {
                        top: 0,
                        height: "100%",
                        background: "#fff",
                        opacity: 0.6,
                    },
                });
                $(".blockUI.blockOverlay").css("position", "fixed");
            }
        },
        submitOrder: function () {
            wc_checkout_form.blockOnSubmit($(this));
        },
        submit: function () {
            wc_checkout_form.reset_update_checkout_timer();
            var $form = $(this);

            if ($form.is(".processing")) {
                return false;
            }

            // Trigger a handler to let gateways manipulate the checkout if needed
            // eslint-disable-next-line max-len
            if (
                $form.triggerHandler("checkout_place_order") !== false &&
                $form.triggerHandler(
                    "checkout_place_order_" +
                        wc_checkout_form.get_payment_method()
                ) !== false
            ) {
                $form.addClass("processing");

                wc_checkout_form.blockOnSubmit($form);

                // Attach event to block reloading the page when the form has been submitted
                wc_checkout_form.attachUnloadEventsOnSubmit();

                // ajaxSetup is global, but we use it to ensure JSON is valid once returned.
                $.ajaxSetup({
                    dataFilter: function (raw_response, dataType) {
                        // We only want to work with JSON
                        if ("json" !== dataType) {
                            return raw_response;
                        }

                        if (wc_checkout_form.is_valid_json(raw_response)) {
                            return raw_response;
                        } else {
                            // Attempt to fix the malformed JSON
                            var maybe_valid_json =
                                raw_response.match(/{"result.*}/);

                            if (null === maybe_valid_json) {
                                console.log("Unable to fix malformed JSON");
                            } else if (
                                wc_checkout_form.is_valid_json(
                                    maybe_valid_json[0]
                                )
                            ) {
                                console.log("Fixed malformed JSON. Original:");
                                console.log(raw_response);
                                raw_response = maybe_valid_json[0];
                            } else {
                                console.log("Unable to fix malformed JSON");
                            }
                        }

                        return raw_response;
                    },
                });

                $.ajax({
                    type: "POST",
                    url: wc_checkout_params.checkout_url,
                    data: $form.serialize(),
                    dataType: "json",
                    success: function (result) {
                        // Detach the unload handler that prevents a reload / redirect
                        wc_checkout_form.detachUnloadEventsOnSubmit();
                        if (
                            result.payplus_iframe &&
                            "success" === result.result
                        ) {
                            wc_checkout_form.$checkout_form
                                .removeClass("processing")
                                .unblock();
                            if (result.viewMode == "samePageIframe") {
                                openPayplusIframe(
                                    result.payplus_iframe.data.payment_page_link
                                );
                            } else if (result.viewMode == "popupIframe") {
                                openIframePopup(
                                    result.payplus_iframe.data
                                        .payment_page_link,
                                    700
                                );
                            }
                            return true;
                        }
                        try {
                            if (
                                "success" === result.result &&
                                $form.triggerHandler(
                                    "checkout_place_order_success",
                                    result
                                ) !== false
                            ) {
                                if (
                                    -1 ===
                                        result.redirect.indexOf("https://") ||
                                    -1 === result.redirect.indexOf("http://")
                                ) {
                                    window.location = result.redirect;
                                } else {
                                    window.location = decodeURI(
                                        result.redirect
                                    );
                                }
                            } else if ("failure" === result.result) {
                                throw "Result failure";
                            } else {
                                throw "Invalid response";
                            }
                        } catch (err) {
                            // Reload page
                            if (true === result.reload) {
                                window.location.reload();
                                return;
                            }

                            // Trigger update in case we need a fresh nonce
                            if (true === result.refresh) {
                                $(document.body).trigger("update_checkout");
                            }

                            // Add new errors
                            if (result.messages) {
                                wc_checkout_form.submit_error(result.messages);
                            } else {
                                wc_checkout_form.submit_error(
                                    '<div class="woocommerce-error">' +
                                        wc_checkout_params.i18n_checkout_error +
                                        "</div>"
                                ); // eslint-disable-line max-len
                            }
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        // Detach the unload handler that prevents a reload / redirect
                        wc_checkout_form.detachUnloadEventsOnSubmit();

                        wc_checkout_form.submit_error(
                            '<div class="woocommerce-error">' +
                                errorThrown +
                                "</div>"
                        );
                    },
                });
            }

            return false;
        },
        submit_error: function (error_message) {
            $(
                ".woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message"
            ).remove();
            wc_checkout_form.$checkout_form.prepend(
                '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
                    error_message +
                    "</div>"
            ); // eslint-disable-line max-len
            wc_checkout_form.$checkout_form.removeClass("processing").unblock();
            wc_checkout_form.$checkout_form
                .find(".input-text, select, input:checkbox")
                .trigger("validate")
                .trigger("blur");
            wc_checkout_form.scroll_to_notices();
            $(document.body).trigger("checkout_error", [error_message]);
        },
        scroll_to_notices: function () {
            var scrollElement = $(
                ".woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout"
            );

            if (!scrollElement.length) {
                scrollElement = $(".form.checkout");
            }
            $.scroll_to_notices(scrollElement);
        },
    };

    var wc_checkout_coupons = {
        init: function () {
            $(document.body).on("click", "a.showcoupon", this.show_coupon_form);
            $(document.body).on(
                "click",
                ".woocommerce-remove-coupon",
                this.remove_coupon
            );
            $("form.checkout_coupon").hide().on("submit", this.submit);
        },
        show_coupon_form: function () {
            $(".checkout_coupon").slideToggle(400, function () {
                $(".checkout_coupon").find(":input:eq(0)").trigger("focus");
            });
            return false;
        },
        submit: function () {
            var $form = $(this);

            if ($form.is(".processing")) {
                return false;
            }

            $form.addClass("processing").block({
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            });

            var data = {
                security: wc_checkout_params.apply_coupon_nonce,
                coupon_code: $form.find('input[name="coupon_code"]').val(),
            };

            $.ajax({
                type: "POST",
                url: wc_checkout_params.wc_ajax_url
                    .toString()
                    .replace("%%endpoint%%", "apply_coupon"),
                data: data,
                success: function (code) {
                    $(".woocommerce-error, .woocommerce-message").remove();
                    $form.removeClass("processing").unblock();

                    if (code) {
                        $form.before(code);
                        $form.slideUp();

                        $(document.body).trigger("applied_coupon_in_checkout", [
                            data.coupon_code,
                        ]);
                        $(document.body).trigger("update_checkout", {
                            update_shipping_method: false,
                        });
                    }
                },
                dataType: "html",
            });

            return false;
        },
        remove_coupon: function (e) {
            e.preventDefault();

            var container = $(this).parents(
                    ".woocommerce-checkout-review-order"
                ),
                coupon = $(this).data("coupon");

            container.addClass("processing").block({
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            });

            var data = {
                security: wc_checkout_params.remove_coupon_nonce,
                coupon: coupon,
            };

            $.ajax({
                type: "POST",
                url: wc_checkout_params.wc_ajax_url
                    .toString()
                    .replace("%%endpoint%%", "remove_coupon"),
                data: data,
                success: function (code) {
                    $(".woocommerce-error, .woocommerce-message").remove();
                    container.removeClass("processing").unblock();

                    if (code) {
                        $("form.woocommerce-checkout").before(code);
                        $(document.body).trigger("removed_coupon_in_checkout", [
                            data.coupon_code,
                        ]);
                        $(document.body).trigger("update_checkout", {
                            update_shipping_method: false,
                        });
                        $("form.checkout_coupon")
                            .find('input[name="coupon_code"]')
                            .val("");
                    }
                },
                error: function (jqXHR) {
                    if (wc_checkout_params.debug_mode) {
                        /* jshint devel: true */
                        console.log(jqXHR.responseText);
                    }
                },
                dataType: "html",
            });
        },
    };

    var wc_checkout_login_form = {
        init: function () {
            $(document.body).on("click", "a.showlogin", this.show_login_form);
        },
        show_login_form: function () {
            $("form.login, form.woocommerce-form--login").slideToggle();
            return false;
        },
    };

    var wc_terms_toggle = {
        init: function () {
            $(document.body).on(
                "click",
                "a.woocommerce-terms-and-conditions-link",
                this.toggle_terms
            );
        },

        toggle_terms: function () {
            if ($(".woocommerce-terms-and-conditions").length) {
                $(".woocommerce-terms-and-conditions").slideToggle(function () {
                    var link_toggle = $(
                        ".woocommerce-terms-and-conditions-link"
                    );

                    if ($(".woocommerce-terms-and-conditions").is(":visible")) {
                        link_toggle.addClass(
                            "woocommerce-terms-and-conditions-link--open"
                        );
                        link_toggle.removeClass(
                            "woocommerce-terms-and-conditions-link--closed"
                        );
                    } else {
                        link_toggle.removeClass(
                            "woocommerce-terms-and-conditions-link--open"
                        );
                        link_toggle.addClass(
                            "woocommerce-terms-and-conditions-link--closed"
                        );
                    }
                });

                return false;
            }
        },
    };

    wc_checkout_form.init();
    wc_checkout_coupons.init();
    wc_checkout_login_form.init();
    wc_terms_toggle.init();

    $(
        $(window).on("popstate", () => {
            closePayplusIframe(false);
        })
    );

    function closePayplusIframe(force) {
        if (
            $("#pp_iframe").length &&
            ($("#pp_iframe").is(":visible") || force === true)
        ) {
            $("#pp_iframe").fadeOut(() => {
                $(".payplus-option-description-area").show();
                $("#place_order").prop("disabled", false);
            });
        }
    }
    function addScriptApple() {
        if (
            payplus_script_checkout.payplus_import_applepay_script &&
            isMyScriptLoaded(
                payplus_script_checkout.payplus_import_applepay_script
            )
        ) {
            const script = document.createElement("script");
            script.src = payplus_script_checkout.payplus_import_applepay_script;
            document.body.append(script);
        }
    }
    function isMyScriptLoaded(url) {
        var scripts = document.getElementsByTagName("script");
        for (var i = scripts.length; i--; ) {
            if (scripts[i].src == url) return false;
        }
        return true;
    }
    function getIframePayment(src, width, height) {
        let iframe = document.createElement("iframe");
        iframe.id = "pp_iframe";
        iframe.name = "payplus-iframe";
        iframe.src = src;
        iframe.height = height;
        iframe.width = width;
        iframe.setAttribute("style", `border:0px`);
        iframe.setAttribute("allowpaymentrequest", "allowpaymentrequest");
        return iframe;
    }
    function openPayplusIframe(src) {
        $(".alertify").remove();
        const url = new URL(window.location.href);
        url.searchParams.set("payplus-iframe", "1");
        window.history.pushState({}, "", url);
        const ppIframe = document.querySelector(".pp_iframe");
        const height = ppIframe.getAttribute("data-height");
        ppIframe.innerHTML = "";
        ppIframe.append(getIframePayment(src, "100%", height));
        $("#closeFrame").on("click", function (e) {
            e.preventDefault();
            ppIframe.style.display = "none";
        });
        $("#place_order").prop("disabled", true);

        if (payplus_script_checkout.payplus_mobile) {
            $("html, body").animate({
                scrollTop: $(".place-order").offset().top,
            });
        }
        addScriptApple();
    }

    function openIframePopup(src, height) {
        let windowWidth = window.innerWidth;
        if (windowWidth < 568) {
            height = "100%";
        }

        if (!alertify.popupIframePaymentPage) {
            alertify.dialog("popupIframePaymentPage", function factory() {
                return {
                    main: function (src) {
                        this.message = getIframePayment(src, "100%", height);
                        addScriptApple();
                    },
                    setup: function () {
                        return {
                            options: {
                                autoReset: false,
                                overflow: false,
                                maximizable: false,
                                movable: false,
                                frameless: true,
                                transition: "fade",
                            },
                            focus: {
                                element: 0,
                            },
                        };
                    },

                    prepare: function () {
                        this.setContent(this.message);
                    },

                    hooks: {
                        onshow: function () {
                            this.elements.dialog.style.maxWidth = "100%";
                            this.elements.dialog.style.width = "1050px";
                            this.elements.dialog.style.height =
                                windowWidth > 568 ? "82%" : "100%";
                            this.elements.content.style.top = "25px";
                        },
                    },
                };
            });
        }
        alertify.popupIframePaymentPage(src);
    }

    // Add custom icons field if exists under cc method description
    function addCustomIcons() {
        if (payplus_script_checkout.customIcons[0].length > 0) {
            var $newDiv = $("<div></div>", {
                class: "payplus-checkout-image-container", // Optional: Add a class to the div
                id: "payplus-checkout-image-div", // Optional: Add an ID to the div
                style: "display: flex;",
            });
            $.each(
                payplus_script_checkout.customIcons,
                function (index, value) {
                    var $img = $("<img>", {
                        src: value,
                        alt: "Image " + (index + 1), // Optional: Set alt text for accessibility
                        style: "max-width: 100%; max-height:65px;object-fit: contain;", // Optional: Set inline styles
                    });
                    $newDiv.append($img);
                }
            );
            $("div.payment_method_payplus-payment-gateway").prepend($newDiv);
        }
    }

    function modifyCheckoutPaymentFragment(fragmentHtml, liClassToRemove) {
        // Create a temporary div to hold the HTML string
        const tempDiv = document.createElement("div");

        // Set the inner HTML of the temp div to the fragment HTML
        tempDiv.innerHTML = fragmentHtml;

        // Select the <li> elements with the specified class
        const liElements = tempDiv.querySelectorAll(`.${liClassToRemove}`);

        // Loop through the selected <li> elements and remove them
        liElements.forEach((li) => {
            li.remove();
        });

        // Convert the modified contents back to a string
        const modifiedFragmentString = tempDiv.innerHTML;
        // Return the modified string if needed
        return modifiedFragmentString;
    }

    function multiPassIcons(loopImages) {
        /* Check if multipass method is available and if so check for clubs and replace icons! */

        const element = document.querySelector(
            "#payment_method_payplus-payment-gateway-multipass"
        );

        if (
            element &&
            Object.keys(payplus_script_checkout.multiPassIcons).length > 0
        ) {
            console.log("isMultiPass");
            const multiPassIcons = payplus_script_checkout.multiPassIcons;

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
                    image.style.height = "32px";
                    image.style.width = "32px";
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
                            Object.keys(payplus_script_checkout.multiPassIcons)
                                .length > 1
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
});
