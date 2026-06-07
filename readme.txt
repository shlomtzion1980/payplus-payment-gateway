=== PayPlus Payment Gateway ===
Contributors: payplus
Tags: Woocommerce Payment Gateway, Credit Cards, Charges and Refunds, Subscriptions, Tokenization
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 8.2.0
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

== 8.2.0  - 07-06-2026 =

- Fix       - Cron: when "Exclude manually cancelled orders from cron" checkbox is enabled, orders cancelled by an admin or shop manager are now excluded from cron instead of deleting payment data.
- Fix       - Cron: order status change hook registers only once, preventing duplicate order notes. Works for admins and shop managers via admin, AJAX, or REST.
- Fix       - Cron: minimum order age reduced to 10 minutes for faster processing.
- Tweak     - Settings: cancel checkbox relabeled to "Exclude manually cancelled orders from cron".

== 8.1.9  - 03-06-2026 =

- Fix       - Blocks checkout: validation errors no longer show a persistent PayPlus error popup; WooCommerce native inline notices are used instead.
- Fix       - Blocks checkout: corrected set_payment_details calls to pass arrays instead of strings, preventing PHP foreach warnings.
- Fix       - Hosted Fields: card number field now shows a green checkmark when card brand is detected.
- Fix       - Hosted Fields: lock and CVV icons now use inline data URIs, eliminating CORS errors on local/dev environments.
- Fix       - SEO: noindex meta tag for error page no longer incorrectly applied to other pages.

== 8.1.8  - 19-05-2026 =

- Feature   - Invoice+ Documents: Added POS EMV to the "Do not create documents" exclusion list.
- Fix       - Invoice+ Documents: POS EMV exclusion now works correctly (POS EMV orders are detected via transaction meta).
- Fix       - Invoice+ Documents: Payment method detection now uses alternative_method_name (bit, apple-pay, google-pay) when available.
- Fix       - Invoice+ Documents: Payment query is now HPOS-compatible (queries correct meta table on High-Performance Order Storage sites) - Fix for docs that came out as other instead of payment app.
- Fix       - Payment meta is now fully stored before order save, preventing race conditions with automatic invoice creation hooks.

== 8.1.7  - 05-05-2026 =

- Fix       - Resolved WC_Order_Item_Coupon::offsetGet deprecation notice (since WooCommerce 4.4.0) when coupons are applied at checkout.
- Feature   - Refund fee: Option to automatic 5% or 100 NIS (whichever is lower) refund fee for PayPlus refunds.
- Feature   - POS Only Mode option for dedicated POS terminal setups.
- Fix       - Hide PayPlus Gateway checkbox now works reliably for all checkout types (Classic and Blocks), regardless of Hosted Fields or EMV configuration.
- Fix       - Hosted Fields: resolved duplicate rendering issues on Blocks checkout.
- Fix       - Subscriptions: token now correctly retrieved from subscription or parent order (ON ALL CASES).
- Fix       - Blocks checkout: payment errors now display correctly to frontend users.
- Fix       - Cron job no longer fires at order creation, only at scheduled intervals.
- Tweak     - Optimized user_has_cap filter: now registers only once and only in admin, eliminating ~200ms overhead on every frontend request.

== 8.1.6  - 18-03-2026 =

- Fix       - PayPlus payment gateway now displays correctly in the WooCommerce Blocks checkout page editor (resolved "payment methods not supported" message).
- Feature   - Blocks checkout: closing a payment page (iframe/popup) or re-selecting a payment method no longer requires a full page reload.
- Fix       - Blocks checkout: PayPlus Embedded (hosted fields) no longer requires a full page reload on payment failure or when coupons/gift cards change.
- Fix       - Order status polling now stops correctly when a payment page is closed or the cart total changes (both Classic and Blocks checkout).
- Feature   - J5 Weight Estimate: added a configurable percentage-based cart fee (5%–20%) for Authorization (J5) mode, visible only when a PayPlus gateway is selected.
- Feature   - J5 Weight Estimate: customizable fee name and optional description message displayed below the fee line.
- Feature   - New option to prevent double rendering of the payment page iframe on the receipt page (common with Elementor or other page builders).
- Tweak     - Removed TV power-down effect feature.

== 8.1.5  - 15-03-2026 =

- Feature   - Added VAT selection prompt for partial refunds, allowing admins to choose whether the refunded amount includes VAT or is VAT-exempt.
- Fix       - Resolved an issue where saved payment tokens could override the PayPlus Embedded selection, causing the checkout to revert to a previously saved card instead of using newly entered card details.
- Fix       - Fixed token saving failure when the optional "Name for Invoice" or "Alternative ID/VAT" fields were filled during PayPlus Embedded checkout.
- Feature   - PRUID history tracking: all payment page request UIDs are now stored with timestamps, enabling recovery of orders where the UID changed. The "Get PayPlus Data" button shows a selection popup with a "Try All" option.
- Feature   - Optional order total display inside PayPlus Embedded payment form for both Classic and Blocks checkout, with automatic updates on coupon/shipping changes.
- Tweak     - Reduced checkout order-status polling frequency to prevent excessive server load on slower sites.
- Fix       - Fixed Hebrew character corruption (appearing as raw Unicode escapes) in PayPlus API payloads for certain server configurations.
- Tweak     - PRUID history is now used by the cron job and the Orders Validator for more reliable order status recovery.
- Fix       - Fixed expiry field order in PayPlus Embedded for LTR locales.
- Fix       - The "Include Apple Pay Script" setting now correctly loads the Apple Pay script on Blocks checkout for all iframe display modes.

== 8.1.4  - 10-03-2026 =

- Fix       - Fixed an issue where redirect URLs after payment could be malformed (& converted to &amp;), potentially causing broken thank-you page loads or missing order details.

[See changelog for all versions](https://plugins.svn.wordpress.org/payplus-payment-gateway/trunk/CHANGELOG.md).