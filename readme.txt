=== PayPlus Payment Gateway ===
Contributors: payplus
Tags: Woocommerce Payment Gateway, Credit Cards, Charges and Refunds, Subscriptions, Tokenization
Requires at least: 3.0.1
Tested up to: 6.7.1
Requires PHP: 7.4
Stable tag: 7.4.5
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

= 7.4.5 - 07-01-2025 = 

- Fix   - If the main VAT settings in Invoice+ are unchecked, the vat-type-exempt will now be sent.
- Tweak - Resolved an issue where the Apple Pay script was loaded multiple times if the payment window was closed and reopened without refreshing.
- Tweak - Refactored and removed unnecessary class calls and queries in the Invoices class for improved efficiency.
- Tweak - Fixed PHP warnings that occurred in the invoice refund parser.
- Added - PayPlus Hash Check button - checks the plugin integrity.
- Fix   - Added check if session exists before using it in payplus_get_products_by_order_id() function.

= 7.4.3 - 01-01-2025 =

- Tweak - Improved the callback function to avoid repeated executions by adding a proper WooCommerce delay and removing redundant executions.
- Tweak - Removed the "-------- Or ---------" separator on express checkout in the product page.
- Tweak - Ensured that refunds for orders paid in 2024 will include a 17% VAT.
- Tweak - Removed expired admin notices.

= 7.4.2 - 30-12-2024 =

- Added - PayPlus Embedded now supports multiple coupons with or without taxes, including "Percentage discount" and mixed types.
- Tweak - Removed $order->payment_complete(); from PayPlus Embedded as it is handled elsewhere.
- Tweak - Adjusted icon positioning in "Design checkout" for right-to-left (RTL) languages.

= 7.4.1 - 29-12-2024 =

- Tweak - Enhanced the callback function and improved the display of local time in the callback log.
- Added - Support for split shipping for multiple customer developers.
- Tweak - Refreshed selection of PayPlus Embedded when a coupon is added, ensuring the form is reselected and displayed correctly. 
- Tweak - Adjusted logo placement when "Design checkout" is selected.
- Tweak - Updated logo sizes on the checkout page for better display.
- Tweak - Enhanced regeneration of the PayPlus Embedded link when the payment link expires.
- Added - Support for multiple coupons and types in PayPlus Embedded, including "Fixed cart discount" and "Fixed product discount" (both checkouts).
- Tweak - Added a "Page expired" message with a reload option in a popup.
- Tweak - Added nonce verification for admin notices.

= 7.3.8 - 25-12-2024 =

- Fix   - Refund invoices were not created if the charge invoice was not created beforehand. This has been corrected.
- Tweak - Adjusted the margin and padding of Express Checkout buttons.

= 7.3.6 - 24-12-2024 =

- Fix   - Only the main PayPlus gateway is now displayed when adding a payment method (to save a credit card token).
- Added - Option to hide the number of payments for the Bit payment method.
- Added - A special notice regarding the VAT change that will occur January 1st has been added for this version.
- Tweak - Added security check to verify the order ID and improved performance of the PayPlus Embedded Subgateway.
- Tweak - Adjusted the size and framing of Express Checkout buttons on the checkout page.
- Tweak - PayPlus Cron and the PayPlus orders check button will now attempt to run at least 4 times before giving up on an order (up from 2 attempts).

= 7.3.5 - 15-12-2024 =

- Tweak - Replaced the headline text with the Invoice+ logo at the top of the column on the All Orders page.
- Tweak - Renamed the getPayPlusPayload function to getHostedPayload to correct its inaccurate naming.

= 7.3.3 - 12-12-2024 =

- Fix - PayPlus Embedded - Resolved an issue where coupons/discounts did not account for taxes on stores with exclusive prices and tax management enabled.
- Tweak - PayPlus Embedded - Improved payload validation before payment, added error messages with reload functionality for payment failures, and implemented minor refactoring to enhance performance by reducing unnecessary checks.
- Tweak - Alertify is now loaded locally to comply with WordPress plugin check requirements.

= 7.3.2 - 10-12-2024 =

* Fixed - Resolved issues with creating refunds for PayPal through Invoice+ after the refactor.
* Logs  - Added logging for the invoice creation process.
* Tweak - Removed "hostedFields" (PayPlus Embedded) from manual payment Invoice+ creation.

= 7.3.1 - 08-12-2024 = 

* Added - Error messages in PayPlus Embedded now appear at the bottom of the form with a fade-out animation and counter, consistent with the payment pages.
* Added - Support for the "paying_vat" setup in PayPlus Embedded.
* Added - Plugin integrity check during activation.
* Added - Option to block the creation of Invoice+ documents for PayPal via plugin settings.
* Added - "Select Your Cards" feature (optional) to display selected credit card logos from the plugin settings. This affects both the PayPlus main gateway and PayPlus Embedded.
* Fixed - Apple Pay script now loads correctly on websites using iframes.
* Fixed - Error messages were not displayed properly in the new Blocks Checkout; this issue has been resolved.
* Tweak - Enhanced translations for error messages in PayPlus Embedded.
* Tweak - Updated translations for cron options.
* Tweak - Improved plugin sanitation and adherence to WordPress standards via Plugin Check.
* Refactored - Enhanced the process for Invoice+ refunds.
* Removed  - Custom icons added via plugin settings using pasted links have been removed and replaced with the "Select Your Cards" feature.

[See changelog for all versions](https://plugins.svn.wordpress.org/payplus-payment-gateway/trunk/CHANGELOG.md).