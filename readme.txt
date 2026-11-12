=== PayPlus Payment Gateway ===
Contributors: payplus
Tags: Woocommerce Payment Gateway, Credit Cards, Charges and Refunds, Subscriptions, Tokenization
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 8.3.4
PlugIn URL: https://www.payplus.co.il/wordpress
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept credit/debit card payments or other methods such as bit, Apple Pay, Google Pay in one page.
Create digitally signed invoices & much more!

== Description ==
PayPlus Payment Gateway for WooCommerce
Accept debit and credit cards on your WooCommerce store in a secure way with the ability to design your own payment page and add high functionalities to it. SSL is not required.

**Supported PHP Versions:**  
This plugin is compatible with PHP versions from 7.4 up to 8.3.

Before installation: 
You need your account credentials. For that, you have to contact PayPlus and to join the service.

Plugin Disclaimer:
PayPlus does not accept liability for any damage, loss, cost (including legal costs), expenses, indirect losses or consequential damage of any kind which may be suffered or incurred by the user from the use of this service.

It is important to know that this plugin relies on third-party services.
However, the third-party so mentioned is the PayPlus core engine at their servers - the providers of this plugin.

By being a payment processor, just like many of its kind, it must send some transaction details to the third-party server (itself) for token generation and transaction logging statistics and connecting to invoices.

It is this transfer back and forth of data between your WooCommerce and the PayPlus servers that we would like to bring to your attention clearly and plainly.

The main links to PayPlus, its terms and conditions, and privacy policy are as listed:
- Home Page: https://www.payplus.co.il
- Plugin Instruction page: https://www.payplus.co.il/wordpress
- Terms and Conditions: https://www.payplus.co.il/privacy

The above records, the transaction details, are not treated as belonging to PayPlus and are never used for any other purposes.

The external files referenced by this plugin, due to WordPress policy recommendations, are all included in the plugin directory.

== Installation ==

1. In your WordPress Dashboard go to "Plugins" -> "Add Plugin".
2. Search for "payplus-payment-gateway".
3. Install the plugin by pressing the "Install" button.
4. Activate the plugin by pressing the "Activate" button.
5. Open the settings page for WooCommerce and click the "Checkout" tab.
6. Click on the sub tab for "PayPlus Payment Gateway".
7. Configure your PayPlus Gateway settings.

== Frequently Asked Questions ==

= Do this plugin support recurring payments, like subscriptions? =

Yes!

= Does this require an SSL certificate? =

No! You can use our Redirect option and you are free from SSL, However it is still recommended.

= Does this support both production mode and sandbox mode for testing? =

Yes, it does - production and Test (sandbox) mode is driven by the API keys you use with a checkbox in the admin settings to toggle between both.

= Where can I find documentation? =

For help setting up and configuration refer to [documentation](https://www.payplus.co.il/wordpress).

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum. or contact us directly at (https://www.payplus.co.il).

== Screenshots ==

1. The PayPlus Payment page! (Example - This page can be edited for design and more!).
2. Go into Plugins -> Add New Plugin.
3. Search for PayPlus Payment Gateway in the search line and click install.
4. After installation click activate.
5. Select WooCommerce -> Settings -> Payments.
6. Activate the gateway under the "active" tab and select manage.
7. Enter the 3 credentials (in basic settings) you have received from PayPlus (when you signed up with us).
8. Save your settings, now you have an active payment page!

== Changelog ==

== 8.3.4  - 12-11-2026 =

- Feature   - New option "Allow J5 cancel": a "Cancel J5" button on authorized orders cancels the J5 in PayPlus (releases the credit hold) and sets the order to Cancelled.
- Fix       - Saved cards: the card is saved when the PayPlus payment page returns by POST and "Save card" was ticked.

== 8.3.3  - 09-11-2026 =

- Fix       - Invoice+: the automatic document is created only when the order reaches the selected "Order status for issuing an invoice".

== 8.3.2  - 08-11-2026 =

- Fix       - Embedded (Hosted Fields): checkout no longer shows a critical error when returning to a pending order after the session was reset.

== 8.3.1  - 14-10-2026 =

- Tweak     - New advanced option "Prevent duplicate payment records" (on for new installs, off for existing stores) - success note, payment row and saved token are created once.
- Tweak     - New advanced option "Skip repeated payment checks" (on for new installs, off for existing stores) - fewer PayPlus and Invoice+ calls.
- Tweak     - Invoice+: reason logged when a document is not created, with an order note and popup for a PayPlus method without PayPlus payment.
- Tweak     - Subscription support shown only for the main PayPlus, Embedded, Google Pay and Apple Pay gateways.
- Fix       - Blocks checkout shows PayPlus and Embedded for subscription carts when "Accept manual renewals" is off.

== 8.3.0  - 07-10-2026 =

- Tweak     - Improved order locking between the return URL and the PayPlus callback - payment complete runs once per order.
- Tweak     - Shorter payment method change note and more payment-complete log details.
- Tweak     - Error code 4 (terminal type 6) translated: "Declined - the transaction was not approved".

== 8.2.9  - 05-10-2026 =

- Security  - Fixed critical payment bypass: a forged request to the payment return URL could mark an unpaid order as Processing. Orders are now updated only after PayPlus confirms the transaction server-side, the return must match a payment page created for that order, and a transaction of one order can no longer be used to mark another order paid. (Reported by VF Israel / VFC security scan)

- Fix       - The woocommerce_payment_complete hook is now sent on every PayPlus success path (return URL, POST return, double-check, Hosted Fields, saved card) when "Payment Completed" is on, including orders where the customer returned before the callback. Status changes and emails are unchanged; the hook is sent once per order.
- Tweak     - New payplus_payment_complete log showing which process sent woocommerce_payment_complete, or why it was not sent.
- Feature   - Payment page "POST" return mode (redirect, iframe, popup) is now supported: the order is confirmed with PayPlus and updated the same as GET.
- Fix       - The order payment method updates to the method actually used on the payment page (e.g. opened as bit, paid by credit card).
- Fix       - Invoice+: wire / bank transfers are documented as bank transfer with the account number; the order metabox shows "Account" for bank transfers.
- Fix       - Invoice+: PayPal orders are no longer blocked by the PayPlus Authorization setting and are matched by the "do not create" list.

== 8.2.8  - 23-09-2026 =

- Fix       - Invoice+: the payment callback (payplusIpn) no longer creates a document when Invoice Creation Mode is Manual. Automatic callers cannot issue a document in Manual mode; the order-page create action still can.
- Fix       - Hosted Fields: Payment complete, New order, and Processing order emails are sent once. The browser completion and the PayPlus callback no longer both mark the order paid. Other payment methods are unchanged.

== 8.2.7  - 23-09-2026 =

- Fix       - Invoice+: Manual invoice creation mode is now properly respected. Invoices are no longer auto-created when mode is set to Manual in callback/IPN, cron runner, validator, and other automatic paths.

== 8.2.6  - 18-09-2026 =

- Security  - Fixed CVSS 6.5 Broken Access Control vulnerability: merchant PayPlus API secret key is no longer exposed in WooCommerce Blocks frontend payment data or Store API checkout response. (Reported by mamgad via Patchstack)
- Security  - Unauthenticated webhook endpoint now requires manage_woocommerce capability after HMAC verification, preventing gateway settings manipulation via leaked credentials.
- Security  - Callback hash comparison changed from === to hash_equals() for constant-time verification, preventing timing attacks.
- Security  - Debug endpoint callback_response_hash now requires manage_woocommerce capability (only active when WP_DEBUG_LOG is enabled).
- Fix       - Blocks support class now correctly selects dev_secret_key in sandbox mode instead of always using live production secret.

== 8.2.5  - 14-09-2026 =

- Fix       - Hosted Fields (PayPlus Embedded): no longer uses a generic customer or the wrong order id.
- Fix       - Invoice+: Multipass and other club/alternative methods are no longer shown as "Other" on documents.
- Fix       - Invoice+: callback and redirect no longer create a second document or duplicate invoice fields.
- Fix       - PayPlus order custom fields are saved once when callback and success URL run together (no stacked HPOS rows).
- Tweak     - Optional "Prevent duplicate payment complete" setting (default off) to stop double Payment complete notes and New order / Processing emails.
- Fix       - Express Checkout Google Pay is off by default and stays off unless Active page UID Google Pay is set.
- Tweak     - Tested up to WordPress 7.1.
- Feature   - Apple Pay: optional setting to hide the standalone checkout option while keeping Apple Pay available on the main PayPlus payment page.
- Feature   - Partners Dev Mode: optional "Block unpaid $0 orders from session desync" (shown only when Enable partners dev mode is on; default off).
- Fix       - Classic $0 / PW Gift Card thank-you now empties the cart.

== 8.2.4  - 10-08-2026 =

- Feature   - New "Open payment page in website language" option: when enabled, the payment page uses the storefront language; when disabled, previous locale behavior is unchanged.
- Fix       - Blocks: payment method initialize no longer boots all WooCommerce gateways on every page; payment method icons restored.
- Fix       - Blocks checkout: payment payload is built once for the selected method instead of once per registered PayPlus method.
- Fix       - Classic checkout: Place Order button no longer flickers when switching methods unless J5 Weight Estimate refresh is required.
- Fix       - Callback/IPN: more reliable order status updates when multiple success responses arrive for the same payment.
- Tweak     - Ensure WooCommerce is active before loading PayPlus payment gateways.

[See changelog for all versions](https://plugins.svn.wordpress.org/payplus-payment-gateway/trunk/CHANGELOG.md).