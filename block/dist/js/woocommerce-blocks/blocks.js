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

const isEditor = !!document.querySelector('.block-editor');

const payPlusGateWay = window.wc.wcSettings.getPaymentMethodData(
    "payplus-payment-gateway"
) || {};

let gateways = (payPlusGateWay.gateways || []).slice();

gateways = payPlusGateWay.isSubscriptionOrder
    ? ["payplus-payment-gateway"]
    : gateways;

gateways =
    payPlusGateWay.isSubscriptionOrder && payPlusGateWay.isLoggedIn
        ? [
              "payplus-payment-gateway",
              "payplus-payment-gateway-hostedfields",
          ]
        : gateways;

let customIcons = [];

const w = window.React;
for (let c = 0; c < (payPlusGateWay.customIcons || []).length; c++) {
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
            flexWrap: "wrap",
            width: "100%",
            maxWidth: "100%",
            gap: "5px",
        },
    },
    customIcons
);

let isCustomeIcons = !!(payPlusGateWay.customIcons && payPlusGateWay.customIcons[0] && payPlusGateWay.customIcons[0].length);
const hasSavedTokens =
    payPlusGateWay.hasSavedTokens ? Object.keys(payPlusGateWay.hasSavedTokens).length > 0 : false;
const hideMainPayPlusGateway = payPlusGateWay.hideMainPayPlusGateway;
const hostedFieldsIsMain = payPlusGateWay.hostedFieldsIsMain;

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
                            : null,
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
                t.icon && t.icon.search("PayPlusLogo.svg") > 0 && isCustomeIcons
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
                wObj = {
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
                            l === "payplus-payment-gateway"
                                ? o.showSaveOption
                                : false,
                        features: o.supports,
                    },
                };
            (0, t.registerPaymentMethod)(wObj);
        }
    })();
})();

if (isCheckout || hasOrder) {

    var _checkoutDispatch = wp.data.dispatch(CHECKOUT_STORE_KEY);
    var _paymentDispatch = wp.data.dispatch(PAYMENT_STORE_KEY);

    // Will be assigned from within DOMContentLoaded so resetCheckoutState can re-attach the observer
    var _startObserving = null;

    // True while an iframe/popup payment page is showing or hosted-fields is mid-submission.
    // Used by the cart-total watcher to auto-reset when the total changes under an active payment.
    var _paymentPageActive = false;

    /**
     * Reset the Blocks checkout state machine so the customer can
     * change payment method and click "Place Order" again without reloading.
     */
    function resetCheckoutState() {
        _paymentPageActive = false;

        // Stop any running poll
        _payplusPollDone = true;
        _payplusPollStarted = false;
        if (_payplusPollTimerId) {
            clearInterval(_payplusPollTimerId);
            _payplusPollTimerId = null;
        }

        // Hide & clean up the iframe popup (payment-page flows only)
        var ppIframes = document.querySelectorAll('.pp_iframe');
        ppIframes.forEach(function (el) {
            el.style.display = 'none';
            var iframeChild = el.querySelector('iframe');
            if (iframeChild) iframeChild.remove();
        });

        // Remove overlay
        var overlay = document.getElementById('overlay');
        if (overlay) overlay.remove();

        // Restore body scroll & appearance (also undoes hosted-fields dimming)
        document.body.style.overflow = '';
        document.body.style.backgroundColor = '';
        document.body.style.opacity = '';

        // Hide hosted-fields loader if it was shown
        var hfLoader = document.querySelector('.blocks-payplus_loader_hosted');
        if (hfLoader) hfLoader.style.display = 'none';

        // Re-enable inputs that hosted-fields disabled before submission
        document.querySelectorAll('input:disabled').forEach(function (inp) {
            inp.disabled = false;
        });

        // Reset WC Blocks stores back to idle so the button re-enables
        try { _checkoutDispatch.__internalSetIdle(); } catch (e) {}
        try { _paymentDispatch.__internalSetPaymentIdle(); } catch (e) {}

        // Allow the next Place Order click to re-trigger the observer
        _payplusPollDone = false;

        // Re-attach the observer after a tick so isComplete() is no longer true
        if (_startObserving) {
            setTimeout(function () { _startObserving(); }, 150);
        }
    }

    // -------------------------------------------------------------------
    // Watch for cart-total changes while a payment page / hosted-fields
    // session is active.  When the total changes the open page has stale
    // data so we must tear it down and let the customer re-submit.
    // -------------------------------------------------------------------
    (function () {
        var CART_KEY = window.wc.wcBlocksData.CART_STORE_KEY;
        var cartSelect = wp.data.select(CART_KEY);
        var lastTotal = null;

        try { lastTotal = cartSelect.getCartTotals().total_price; } catch (e) {}

        wp.data.subscribe(function () {
            var currentTotal;
            try { currentTotal = cartSelect.getCartTotals().total_price; } catch (e) { return; }

            if (lastTotal !== null && currentTotal !== lastTotal && _paymentPageActive) {
                console.log('PayPlus: cart total changed while payment page active — resetting');
                lastTotal = currentTotal;
                resetCheckoutState();
                return;
            }
            lastTotal = currentTotal;
        });
    })();

    // -------------------------------------------------------------------
    // Watch for payment-method changes in Blocks checkout.  When the
    // customer picks a different gateway we update chosen_payment_method
    // in the WC session so woocommerce_cart_calculate_fees can decide
    // whether to add the Weight Estimate fee, then invalidate the cart
    // so totals refresh.  Also fires once on initial load so the session
    // is in sync with the auto-selected method after a page reload.
    // -------------------------------------------------------------------
    (function () {
        var CART_KEY = window.wc.wcBlocksData.CART_STORE_KEY;
        var lastMethod = null;
        var synced = false;

        function syncMethod(method) {
            var ajaxUrl = payPlusGateWay.ajax_url || (window.payplus_script && window.payplus_script.ajax_url);
            var nonce = payPlusGateWay.frontNonce || (window.payplus_script && window.payplus_script.frontNonce);
            if (!ajaxUrl || !method) return;

            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'payplus_set_payment_method',
                    _ajax_nonce: nonce,
                    payment_method: method
                },
                success: function () {
                    try {
                        wp.data.dispatch(CART_KEY).invalidateResolutionForStoreSelector('getCartData');
                    } catch (ignore) {}
                }
            });
        }

        wp.data.subscribe(function () {
            var currentMethod;
            try { currentMethod = payment.getActivePaymentMethod(); } catch (e) { return; }
            if (!currentMethod) return;

            if (!synced) {
                synced = true;
                lastMethod = currentMethod;
                syncMethod(currentMethod);
                return;
            }

            if (currentMethod !== lastMethod) {
                lastMethod = currentMethod;
                syncMethod(currentMethod);
            }
        });
    })();

    // -------------------------------------------------------------------
    // Inject the Weight Estimate fee message below the fee row in Blocks
    // checkout, mirroring the woocommerce_cart_totals_fee_html filter
    // used for classic checkout.
    // -------------------------------------------------------------------
    (function () {
        var feeName = payPlusGateWay.weightEstimateFeeName;
        var feeMessage = payPlusGateWay.weightEstimateFeeMessage;
        if (!feeMessage) return;

        var observer = new MutationObserver(function () {
            var feeRows = document.querySelectorAll('.wc-block-components-totals-fees .wc-block-components-totals-item');
            if (!feeRows.length) {
                feeRows = document.querySelectorAll('.wc-block-components-totals-item');
            }
            feeRows.forEach(function (row) {
                var label = row.querySelector('.wc-block-components-totals-item__label');
                if (!label) return;
                if (label.textContent.trim() !== feeName) return;
                if (row.querySelector('.pp-weight-estimate-msg')) return;

                var msg = document.createElement('small');
                msg.className = 'pp-weight-estimate-msg';
                msg.style.cssText = 'display:block;font-size:0.8em;opacity:0.8;margin-top:2px;';
                msg.textContent = feeMessage;
                var desc = row.querySelector('.wc-block-components-totals-item__description');
                if (desc) {
                    desc.appendChild(msg);
                } else {
                    label.parentNode.appendChild(msg);
                }
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    })();

    function addScriptApple() {
        if (isMyScriptLoaded(payPlusGateWay.importApplePayScript)) {
            const script = document.createElement("script");
            script.src = payPlusGateWay.importApplePayScript;
            document.body.append(script);
        }
    }

    function isMyScriptLoaded(url) {
        var scripts = document.getElementsByTagName("script");
        for (var i = scripts.length; i--; ) {
            if (scripts[i].src == url) {
                return false;
            }
        }
        return true;
    }

    // Auto-select hosted fields if hostedFieldsIsMain is true
    if (hostedFieldsIsMain) {
        const { dispatch } = window.wp.data;
        const PAYMENT_STORE_KEY = window.wc.wcBlocksData.PAYMENT_STORE_KEY;
        const OUR_GATEWAY = 'payplus-payment-gateway-hostedfields';

        try {
            dispatch(PAYMENT_STORE_KEY).__internalSetActivePaymentMethod(OUR_GATEWAY);
        } catch (error) {
            // Silently ignore errors
        }
    }

    // Prevent double redirects when both postMessage and polling fire
    var _payplusPollDone = false;
    var _payplusPollTimerId = null;

    function payplusRedirect(url) {
        jQuery('.pp_iframe').hide();
        window.location.href = url;
    }

    // Firefox blocks cross-origin iframe from navigating top window. When PayPlus iframe sends
    // postMessage with redirect URL (or thank-you page loads in iframe), parent performs the redirect.
    window.addEventListener("message", function (e) {
        if (!e.data || e.data.type !== "payplus_redirect" || !e.data.url) {
            return;
        }
        try {
            var u = new URL(e.data.url, window.location.origin);
            if (u.origin === window.location.origin) {
                _payplusPollDone = true;
                payplusRedirect(e.data.url);
            }
        } catch (err) {
            // ignore invalid URL
        }
    });

    var _payplusPollStarted = false;

    function startOrderStatusPoll(result) {
        if (!payPlusGateWay.enableOrderStatusPoll) return;
        if (_payplusPollStarted) return;
        _payplusPollStarted = true;
        if (!result || !result.order_id || !result.order_received_url) return;

        // Clear any previous poll so we start fresh for this order
        if (_payplusPollTimerId) {
            clearInterval(_payplusPollTimerId);
            _payplusPollTimerId = null;
        }
        _payplusPollDone = false;

        var redirectUrl = result.order_received_url;
        var orderKey = '';
        try {
            var u = new URL(redirectUrl, window.location.origin);
            orderKey = u.searchParams.get('key') || '';
        } catch (err) {
            return;
        }
        if (!orderKey) return;

        var attempts = 0;
        var maxAttempts = 45; // 90 seconds (45 * 2s)

        function poll() {
            if (_payplusPollDone || attempts++ > maxAttempts) {
                return;
            }

            jQuery.ajax({
                url: payPlusGateWay.ajax_url || window.payplus_script?.ajax_url,
                type: 'POST',
                data: {
                    action: 'payplus_check_order_redirect',
                    _ajax_nonce: payPlusGateWay.frontNonce || window.payplus_script?.frontNonce,
                    order_id: result.order_id,
                    order_key: orderKey
                },
                dataType: 'json',
                success: function (response) {
                    if (_payplusPollDone) return;

                    if (response && response.success && response.data && response.data.status) {
                        var status = response.data.status;
                        if (status === 'processing' || status === 'completed' ||
                            status === 'wc-processing' || status === 'wc-completed') {
                            _payplusPollDone = true;
                            payplusRedirect(response.data.redirect_url || redirectUrl);
                        }
                    }
                }
            });
        }

        poll();
        _payplusPollTimerId = setInterval(function () {
            if (_payplusPollDone) {
                clearInterval(_payplusPollTimerId);
                _payplusPollTimerId = null;
                return;
            }
            poll();
        }, 2000);
    }

    // Order total display inside hosted fields (blocks checkout)
    const showOrderTotalSetting = (function() {
        try {
            var hfData = window.wc.wcSettings.getPaymentMethodData('payplus-payment-gateway-hostedfields');
            return hfData && hfData.show_order_total;
        } catch(e) { return false; }
    })();

    if (showOrderTotalSetting) {
        const { CART_STORE_KEY } = window.wc.wcBlocksData;
        const cartStore = wp.data.select(CART_STORE_KEY);

        function updateBlocksHostedTotal() {
            var $ppTotal = document.getElementById('ppOrderTotal');
            if (!$ppTotal) return;

            try {
                var cartTotals = cartStore.getCartTotals();
                var totalPrice = parseInt(cartTotals.total_price, 10) || 0;
                var decimals = parseInt(cartTotals.currency_minor_unit, 10) || 2;
                var amount = (totalPrice / Math.pow(10, decimals)).toFixed(decimals);

                var prefix = cartTotals.currency_prefix || '';
                var suffix = cartTotals.currency_suffix || '';
                var formatted = prefix + amount + suffix;

                $ppTotal.querySelector('.pp-total-amount').innerHTML = formatted;
                $ppTotal.style.display = '';
            } catch(e) {}
        }

        wp.data.subscribe(function() {
            var activeMethod = '';
            try { activeMethod = payment.getActivePaymentMethod(); } catch(e) {}
            if (activeMethod === 'payplus-payment-gateway-hostedfields') {
                updateBlocksHostedTotal();
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        // Function to start observing for the target element
        let loopImages = true;
        let WcSettings = window.wc.wcSettings;

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

        function startObserving(event) {
            console.log("observer started");
            _startObserving = startObserving;

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
                const activePaymentMethod = payment.getActivePaymentMethod();
                
                if (
                    activePaymentMethod.search(
                        "payplus-payment-gateway-hostedfields"
                    ) === 0
                ) {
                    const ppIframeElement =
                        document.getElementsByClassName("pp_iframe_h")[0];
                    if (ppIframeElement) {
                        ppIframeElement.style.display = "flex";
                        
                        // Add Place Order button to hosted fields if not already added
                        if (!ppIframeElement.querySelector('.payplus-hosted-place-order')) {
                            const originalPlaceOrderButton = document.querySelector('.wc-block-checkout__actions_row button');
                            const ppLogo = ppIframeElement.querySelector('#ppLogo');
                            
                            // Check if the show_hide_submit_button setting is enabled
                            const hostedFieldsSettings = window.wc.wcSettings.getPaymentMethodData('payplus-payment-gateway-hostedfields');
                            const showSubmitButton = hostedFieldsSettings && hostedFieldsSettings.show_hide_submit_button === 'yes';
                            
                            if (originalPlaceOrderButton && ppLogo && showSubmitButton) {
                                const hostedPlaceOrderButton = document.createElement('button');
                                hostedPlaceOrderButton.className = 'btn btn-primary payplus-hosted-place-order wp-element-button wc-block-components-button wp-element-button contained';
                                hostedPlaceOrderButton.type = 'button';
                                
                                // Create button content wrapper
                                const buttonText = document.createElement('span');
                                buttonText.className = 'button-text';
                                buttonText.textContent = originalPlaceOrderButton.textContent;
                                
                                const buttonLoader = document.createElement('span');
                                buttonLoader.className = 'button-loader';
                                buttonLoader.style.cssText = 'display: none; margin-left: 8px;';
                                buttonLoader.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="animation: spin 1s linear infinite; vertical-align: middle;"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="30 10" /></svg>';
                                
                                hostedPlaceOrderButton.appendChild(buttonText);
                                hostedPlaceOrderButton.appendChild(buttonLoader);
                                
                                hostedPlaceOrderButton.style.cssText = `
                                    margin-top: 15px;
                                    margin-bottom: 15px;
                                    margin-right: auto;
                                    margin-left: auto;
                                    width: 90%;
                                    background-color: rgb(0, 0, 0);
                                    color: white;
                                    border: none;
                                    border-radius: 10px;
                                    padding: 12px 24px;
                                    font-size: 16px;
                                    font-weight: 600;
                                    cursor: pointer;
                                `;
                                
                                // Clone the click behavior from the original button
                                hostedPlaceOrderButton.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    // Show loader immediately on click
                                    hostedPlaceOrderButton.disabled = true;
                                    hostedPlaceOrderButton.style.opacity = '0.7';
                                    const loader = hostedPlaceOrderButton.querySelector('.button-loader');
                                    if (loader) {
                                        loader.style.display = 'inline-block';
                                    }
                                    originalPlaceOrderButton.click();
                                });
                                
                                // Insert the button right before the ppLogo
                                ppLogo.parentNode.insertBefore(hostedPlaceOrderButton, ppLogo);
                            }
                        }
                    }
                } else {
                    // Hide hosted fields iframe when a different payment method is selected
                    const ppIframeElement =
                        document.getElementsByClassName("pp_iframe_h")[0];
                    if (ppIframeElement) {
                        ppIframeElement.style.display = "none";
                    }
                }
                if (hideMainPayPlusGateway) {
                    const parentDiv = document
                        .querySelector(
                            "#radio-control-wc-payment-method-options-payplus-payment-gateway"
                        )
                        ?.closest(
                            ".wc-block-components-radio-control-accordion-option"
                        );
                    if (parentDiv) {
                        parentDiv.style.display = "none";
                    }
                }
                // Show hosted-fields loader as soon as checkout starts processing
                // (before the Store API response arrives) so the user gets
                // immediate visual feedback instead of waiting 5+ seconds.
                if (
                    activePaymentMethod.search("payplus-payment-gateway-hostedfields") === 0 &&
                    store.isProcessing && store.isProcessing()
                ) {
                    var hfLoaderEl = document.querySelector('.blocks-payplus_loader_hosted');
                    if (hfLoaderEl && hfLoaderEl.style.display !== 'block') {
                        hfLoaderEl.style.display = 'block';
                        document.body.style.overflow = 'hidden';
                        document.body.style.backgroundColor = 'white';
                        document.body.style.opacity = '0.7';
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
                            throw new Error(
                                "Payment result is empty, null, or undefined."
                            );
                        }

                        // Process the result here
                        console.log("Payment result:", getPaymentResult);
                        let pp_iframe =
                            document.querySelectorAll(".pp_iframe")[0];
                        pp_iframe.style.width =
                            window.innerWidth <= 768 ? "95%" : "55%";
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
                            getPaymentResult.paymentDetails.errorMessage !==
                            undefined
                                ? getPaymentResult.paymentDetails.errorMessage +
                                  "<br>" +
                                  ((window.payplus_i18n && window.payplus_i18n.click_to_close) 
                                      ? window.payplus_i18n.click_to_close 
                                      : "Click this to close.")
                                : getPaymentResult.message +
                                  "<br>" +
                                  ((window.payplus_i18n && window.payplus_i18n.click_to_close) 
                                      ? window.payplus_i18n.click_to_close 
                                      : "Click this to close.");

                        pp_iframe.addEventListener("click", (e) => {
                            e.preventDefault();
                            pp_iframe.style.display = "none";
                            resetCheckoutState();
                        });
                        console.log(
                            getPaymentResult.paymentDetails.errorMessage
                        );
                        if (
                            getPaymentResult.paymentDetails.errorMessage !==
                            undefined
                        ) {
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
                    if (
                        activePaymentMethod.search(
                            "payplus-payment-gateway-hostedfields"
                        ) === 0
                    ) {
                        _paymentPageActive = true;
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
                        if (!window._ppHfResponseHandlerRegistered) {
                            window._ppHfResponseHandlerRegistered = true;
                            hf.Upon("pp_responseFromServer", (e) => {
                                if (e.detail.errors || e.detail?.data?.error || e.detail?.data?.status === "reject") {
                                    resetCheckoutState();
                                }
                            });
                        }
                        return;
                    }

                    if (
                        activePaymentMethod.search(
                            "payplus-payment-gateway"
                        ) === 0 &&
                        activePaymentMethod.search(
                            "payplus-payment-gateway-pos-emv"
                        ) !== 0
                    ) {
                        const gateWaySettings =
                            window.wc.wcSettings.getPaymentMethodData(
                                activePaymentMethod
                            )[activePaymentMethod + "-settings"];
                        const isIframe =
                            ["samePageIframe", "popupIframe"].indexOf(
                                gateWaySettings.displayMode
                            ) !== -1;
                        const isAnyIframeMode =
                            ["samePageIframe", "popupIframe", "iframe"].indexOf(
                                gateWaySettings.displayMode
                            ) !== -1;
                        if (isAnyIframeMode && payPlusGateWay.importApplePayScript) {
                            addScriptApple();
                        }
                        console.log("isIframe?", isIframe);
                        if (
                            gateways.indexOf(
                                payment.getActivePaymentMethod()
                            ) !== -1 &&
                            payment.getActiveSavedToken().length === 0
                        ) {
                            console.log("isComplete: " + store.isComplete());
                            // Call the function to handle the target element
                            if (isIframe) {
                                var paymentDetails = payment.getPaymentResult().paymentDetails;

                                if (paymentDetails.payplus_iframe_async) {
                                    // Async mode: Store API returned instantly (no PayPlus HTTP call yet).
                                    // Show the iframe container immediately, then fetch the link via AJAX.
                                    startOrderStatusPoll({
                                        order_id: paymentDetails.order_id,
                                        order_received_url: paymentDetails.order_received_url
                                    });
                                    // Show overlay/container right away with a blank src.
                                    startIframe('about:blank', overlay, loader);
                                    // Now fetch the actual PayPlus payment page link.
                                    jQuery.ajax({
                                        type: 'POST',
                                        url: payplus_script.ajax_url,
                                        data: {
                                            action: 'payplus_get_iframe_link',
                                            _ajax_nonce: payplus_script.frontNonce,
                                            order_id: paymentDetails.order_id,
                                            order_key: paymentDetails.order_key,
                                        },
                                        success: function(resp) {
                                            if (resp.success && resp.data && resp.data.payment_page_link) {
                                                var iframe = document.getElementById('pp_iframe');
                                                if (iframe) {
                                                    iframe.src = resp.data.payment_page_link;
                                                }
                                            } else {
                                                var errMsg = (resp.data && resp.data.message)
                                                    ? resp.data.message
                                                    : ((window.payplus_i18n && window.payplus_i18n.payment_page_failed)
                                                        ? window.payplus_i18n.payment_page_failed
                                                        : 'Error: the payment page failed to load.');
                                                alert(errMsg);
                                                resetCheckoutState();
                                            }
                                        },
                                        error: function() {
                                            alert((window.payplus_i18n && window.payplus_i18n.payment_page_failed)
                                                ? window.payplus_i18n.payment_page_failed
                                                : 'Error: the payment page failed to load.');
                                            resetCheckoutState();
                                        }
                                    });
                                } else if (paymentDetails.paymentPageLink && paymentDetails.paymentPageLink.length > 0) {
                                    // Legacy sync path — link already in paymentDetails.
                                    console.log("paymentPageLink", paymentDetails.paymentPageLink);
                                    startIframe(paymentDetails.paymentPageLink, overlay, loader);
                                    if (paymentDetails.order_id && paymentDetails.order_received_url) {
                                        startOrderStatusPoll({
                                            order_id: paymentDetails.order_id,
                                            order_received_url: paymentDetails.order_received_url
                                        });
                                    }
                                } else {
                                    alert(
                                        (window.payplus_i18n && window.payplus_i18n.payment_page_failed)
                                            ? window.payplus_i18n.payment_page_failed
                                            : "Error: the payment page failed to load."
                                    );
                                    resetCheckoutState();
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
        _paymentPageActive = true;
        document.body.appendChild(overlay);
        overlay.appendChild(loader);
        const activePaymentMethod = payment.getActivePaymentMethod();
        const gateWaySettings =
            window.wc.wcSettings.getPaymentMethodData(activePaymentMethod)[
                activePaymentMethod + "-settings"
            ];
        var iframe = document.createElement("iframe");
        iframe.id = "pp_iframe";
        iframe.name = "payplus-iframe";
        iframe.width = "95%";
        iframe.height = "100%";
        iframe.style.border = "0";
        iframe.style.display = "block";
        iframe.style.margin = "auto";
        // allow-top-navigation lets PayPlus's own redirectAfterTransaction navigate the top
        // window to the callback URL after payment. Using unconditional (not -by-user-activation)
        // avoids Chrome "Unsafe attempt" errors and Firefox "prevented redirect" prompts.
        // payplus_redirect_graceful immediately JS-redirects to the clean thank-you URL.
        iframe.setAttribute("sandbox", "allow-scripts allow-same-origin allow-forms allow-popups allow-top-navigation");

        iframe.src = paymentPageLink;
        let pp_iframes = document.querySelectorAll(".pp_iframe");
        let pp_iframe = document
            .querySelector(
                `#radio-control-wc-payment-method-options-${activePaymentMethod}`
            )
            .nextElementSibling.querySelector(".pp_iframe");
        if (
            ["samePageIframe", "popupIframe"].indexOf(
                gateWaySettings.displayMode
            ) !== -1
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
                    pp_iframe.style.width =
                        window.innerWidth <= 768 ? "98%" : (gateWaySettings.iFrameWidth || "40%");
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
                resetCheckoutState();
            });
            pp_iframe.appendChild(iframe);
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
                wcSettings.paymentMethodData["payplus-payment-gateway"]
                    .multiPassIcons
            ).length > 0
        ) {
            // console.log("isMultiPass");
            const multiPassIcons =
                wcSettings.paymentMethodData["payplus-payment-gateway"]
                    .multiPassIcons;

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
                                wcSettings.paymentMethodData[
                                    "payplus-payment-gateway"
                                ].multiPassIcons
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