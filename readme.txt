=== PayPlus Payment Gateway ===
Contributors: payplus
Tags: Woocommerce Payment Gateway, Credit Cards, Charges and Refunds, Subscriptions, Tokenization
Requires at least: 3.0.1
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 7.7.3
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

= 7.7.3 - 27-04-2025 =

- Tweak - Express Checkout payments now display specific coupon names instead of "discount".
- Tweak - Updated nonce usage for improved security.
- Added - New setting to hide the enabled PayPlus Payment Gateway on the classic checkout page using JavaScript (intended for customers using only POS EMV).
- Added - Setting to block automatic Invoice+ document creation for POS gateways.

= 7.7.2 - 14-04-2025 =

- Fix   - Resolved an issue where POS EMV refunds were not processing as expected.
- Tweak - Improved the payment payload generation function while ensuring compatibility with legacy systems.
- Fix   - Addressed a problem where Invoice+ documents displayed incorrect details and amounts when Coupons and PW Gift Cards were used together in a transaction, including cases with zero-amount payment invoices.
- Tweak - Enabled support for generating Invoice+ documents for PW Gift Cards at a later time.
- Added - Support for subscriptions in PayPlus Embedded for logged-in users.
- Tweak - The close iframe button in blocks checkout now consistently appears in black.
- Tweak - Initial refactor of the generatePayloadLink function, now renamed to generatePaymentLink. Both the new and "legacy" payloads are supported. If issues arise with the new payload, you can revert to the "legacy" payload by enabling the "Use legacy payload function" checkbox in the plugin settings.
- Added - Feature: Pay with POS EMV as a subgateway from the checkout page.
- Added - Multiple payments details are now included in Invoice+ documents.
- Tweak - Verified compatibility with WordPress version 6.8.
- Fix   - Resolved an issue with new checkout blocks where "PayPlus Embedded" was unnecessarily hidden based on tokens.
- Tweak - Updated translations for recently added plugin settings.

= 7.7.1 - 30-03-2025 =

- Tweak - Adjusted custom icons (payplus gateway on checkout) sizes for better display.
- Added - Support for percentage-based coupons.
- Fix   - Corrected implementation of Partners mode for certain integrations.
- Tweak - Resolved display issues with PayPlus Embedded.
- Added - Support for Partner coupons and the option for dual delivery warehouses.
- Added - Compatibility with the PW Gift Cards Plugin for PayPlus Embedded and Credit Card payments on "Classic Checkout" (PW Gift Cards are not supported in "Blocks Checkout").
- Tweak - Added an option in Invoice+ settings to choose whether coupons are presented as a discount line or as a product.
- Fix   - Resolved the "invalid-app-name" issue during Invoice+ document creation by using the payload object instead of deprecated database queries.
- Fix   - Resolved all POS EMV Invoices came out with "General Product".

= 7.7.0 - 11-03-2025 =

- Fix   - Subscription orders with the "Mark as paid" option enabled will now correctly be set to "completed" status upon successful renewal.
- Tweak - Disabled cart hash verification for additional testing due to issues on certain payment method pages.

= 7.6.9 - 10-03-2025 =

- Fix   - [Invoice+] - Resolved issue (payments-total-not-equal-to-calculated-total) where J5 transactions with adjusted amounts for items (more or less than the original) did not create an invoice when coupons were used. This is now possible.
- Fix   - [Invoice+] - Edited J5 orders (Items and Total) will create an Invoice+ document that accurately reflects the order details instead of just showing "General Product".
- Tweak - [Invoice+] - Subscription orders renewals Invoice+ docs will show correct payment method instead of "other".

= 7.6.8 - 09-03-2025 =

- Added - Support for payments using EMV POS devices (Admin Only).
- Added - Invoice+: Option to block document creation for "bacs" (Direct bank transfer) and "cod" (Cash on Delivery) in automatic mode.
- Fix   - Resolved PHP warning related to array to string conversion.
- Tweak - ForceInvoice can now run with ReportOnly IPN on Orders Validator.
- Added - Create Invoice+ Auto Doc button: This button will create the document without changing status if it wasn't created for any reason, according to document settings.
- Added - Show Create Invoice+ Auto Doc button via checkbox in Invoice+ settings.
- Added - Bank Wire Transfer method to Invoice+ payment types instead of showing "other".
- Added - Cash On Delivery method to Invoice+ payment types instead of showing "other".
- Added - Cheque/Check method to Invoice+ payment types instead of showing "other".
- Fix   - Resolved issue where the payment method logo for subgateways was not hidden.
- Fix   - Corrected issue where the save payment method checkbox appeared twice in "Blocks Checkout" on some themes.
- Tweak - Empty cart if it exists on ipn_response without nonce but after successful payment.
- Tweak - Subscription renewal payments will now include "payplus_response" and display the data in the metabox.

= 7.6.7 - 06-03-2025 =

- Fix   - Resolved subscription renewal failure caused by a missing cart.

= 7.6.6 - 05-03-2025 =

- Fix   - Resloved status updates issues - orders updated twice on not at all.

= 7.6.5 - 04-03-2025 =

- Fix   - Resolved an issue with an undefined JavaScript variable.

= 7.6.4 - 03-03-2025 =

- Tweak - Enhanced security for IPN responses.

= 7.6.2 - 03-03-2025 =

- Tweak - Enhanced order IPN event to run 2 minutes after the payment page is triggered when using classic checkout.
- Added - Compatibility with "YITH WooCommerce Gift Cards" (free version) in PayPlus Embedded.
- Fix   - Resolved a JS visual bug on the "Orders page" where a variable was defined in the wrong place.

= 7.6.1 - 02-03-2025 =

- Tweak - Express Checkout Initialization now displays the payment page UID for the activated feature.
- Tweak - PayPlus Orders Reports/Validator in "Partners mode" now includes a "Create invoice" option.
- Tweak - Express Checkout buttons and phone field are now centered, with improved validation message colors and translations.
- Tweak - Enhanced IPN Response with improved NONCE and Cart Hash testing even more.

= 7.6.0 - 25-02-2025 =

- Tweak - Updated translations for new express checkout settings.
- Tweak - Added sanitation and validation for express checkout data.
- Fix   - Resolved issue where "Use global default" wasn't working in subgateways (Display mode).
- Fix   - Fixed nonce issue occurring with 3D Secure transactions when redirected to the thank you page - nonce didn't pass.
- Tweak - Enhanced security for nonce exploit issue on the thank you page - with option to disable from settings.

= 7.5.9 - 17-02-2025 =

- Added - Option to require a phone number for Google Pay Express Checkout.
- Tweak - Translations to hebrew.
- Tweak - Implemented wp_cache and transient to minimize several database queries.

= 7.5.8 - 11-02-2025 =

- Tweak - Enhanced PayPlus Cron: Now runs every 30 minutes, manages both Invoice+ and non-Invoice+ cancelled orders, and provides improved logging in both logs and order notes.
- Tweak - Optimized callback feature for improved speed and efficiency.
- Fix   - Resolved an issue where the custom icons length was undefined in JavaScript when no custom icons were selected for the checkout page.
- Tweak - "Make Payment" button for J5 (Approval) orders with a "processing" status will be hidden, even if they are unpaid.
- Fix   - Resolved rounding issue for J5 payment error when order products were removed and the total amount was adjusted.

= 7.5.7 - 09-02-2025 =

- Fix   - Resolved shipping issue with Express Checkout on the product page wasn't working when "Shipping by WooCommerce Via JS" was activated. Now it works in combination with the one of the other options.
- Fix   - Corrected rounding error that prevented charging with error message: "Cannot charge more than the total order amount on J5" on specific issues.
- Tweak - PayPlus Orders Validator: When "Enable Partners Dev Mode" is enabled, orders can be selected by year and month. Additionally, a visual table is available when "Enable display of orders table select in PayPlus Orders Validator" is enabled.
- Tweak - PayPlus Orders Validator: When "Enable Partners Dev Mode" is enabled, added "Actions" with the ability to run reports only, force all, get invoice, and force invoice.
- Tweak - PayPlus Orders Validator: Will not mark orders as cron tested, allowing the cron to run on these orders if activated.
- Add   - Prevention of double deals under the same order number for websites with heavy traffic and callback issues. The "Double check IPN" feature checks if an order already has a "payplus_page_request_uid" before attempting to start a new payment.

= 7.5.5 - 02-02-2025 =

- Fix   - Resolved issue where PayPlus Embedded was stuck on loading for certain templates.
- Tweak - If a callback arrives and the order contains no payplus_response, the callback will run IPN as well.
- Tweak - Fixed/Cleaned PHP warnings of missing array keys in specific cases (warning messages only).
- Tweak - Centered display of multiple icons on PayPlus Embedded and main gateway in mobile view to fit cases with many icons.
- Tweak - Improved logging for PayPlus Embedded.
- Added - For PayPlus Partners Only - PayPlus Orders Validator can now run in report mode only, and by month, year, and much more. (For more information, contact PayPlus and ask about the Partners program.)

= 7.5.4 - 28-01-2025 =

- Tweak - Adjusted the CSS for the popup iframe close button's top position on iPhones.

= 7.5.3 - 27-01-2025 =

- Added - Support for Express Checkout shipping in the classic checkout, consistent with the WooCommerce checkout page.

= 7.5.2 - 20-01-2025 =

- Added - Option to show or hide the "Place Order" button within the PayPlus Embedded form.
- Tweak - Updated the Bit payment method logo.
- Tweak - Adjusted the arrow display position for multiple payments in mobile view for PayPlus Embedded.
- Added - Checkbox for "Partners Dev Mode" with initial support for one filter, more to be added soon.

= 7.5.1 - 14-01-2025 =

- Fix   - Adjusted the inline styling for PayPlus payment logos to ensure correct height and width. 
- Fix   - Resolved an error when adding a payment method due to logging issues.
- Tweak - Added and corrected missing Hebrew translations.

= 7.5.0 - 08-01-2025 =

- Fix - Enhanced the previous version to save payloads more efficiently and cleanly.

= 7.4.8 - 08-01-2025 = 

- Tweak - Improved the invoice refund process to avoid relying solely on the invoice payload, preventing issues with unicode conversion.

= 7.4.6 - 07-01-2025 = 

- Tweak - Added support for the Transaction Type product field in both the PayPlus Embedded and main gateway.
- Tweak - Updated instructions for the Transaction Type product field.
- Added - PayPlus Embedded now supports the Successful Order Status and Payment Completed settings.
- Tweak - Added an indicator message to the Invoice+ VAT Settings to notify users when the WooCommerce taxes feature is enabled.

= 7.4.5 - 06-01-2025 = 

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