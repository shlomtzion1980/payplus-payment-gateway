=== PayPlus Payment Gateway ===
Contributors: payplus
Tags: Woocommerce Payment Gateway, Credit Cards, Charges and Refunds, Subscriptions, Tokenization
Requires at least: 3.0.1
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 7.3.3
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

= 7.3.2 - 12-12-2024

- Fix - PayPlus Embedded - Resolved an issue where coupons/discounts did not account for taxes on stores with exclusive prices and tax management enabled.
- Tweak - PayPlus Embedded - Improved payload validation before payment, added error messages with reload functionality for payment failures, and implemented minor refactoring to enhance performance by reducing unnecessary checks.

= 7.3.2 - 10-12-2024

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



= 7.3.0 - 27-11-2024 = 

* Fix   - Resolved - Addressed an issue where Invoice+ documents for orders paid using a PayPal payment plugin (other than PayPlus) were incorrectly labeled as "Other" These documents will now accurately display "PayPal" as the payment method when applicable.

= 7.2.9 - 26-11-2024 = 

* Added - An option to hide the number of payments on Google Pay and Apple Pay payment pages (e.g., to display only a single payment). This option can be configured in each payment method's settings.
* Tweak - Restricted the optional callback in settings to accept only HTTP or HTTPS links.

= 7.2.8 - 25-11-2024 = 

* Fix   - PayPlus Embedded origin for testmode and production.

= 7.2.7 - 25-11-2024 = 

* Fix   - Small fix for refund invoices - removed usage of saved payloads.

= 7.2.6 - 24-11-2024 = 

* Fix   - Loading iframe issue in redirect and on the next page fixed.

= 7.2.5 - 24-11-2024 = 

* Added - PayPlus Embedded: An embedded credit card payment form that eliminates the need for a separate payment page during the checkout process. This form is preloaded on the checkout page, allowing customers to securely enter their payment details and complete the transaction seamlessly.

The new feature can be enabled via the admin settings menu under "Subgateways." It can function as a standalone option or alongside existing payment pages. Note that PayPlus Embedded supports credit card payments exclusively.


= 7.2.3 - 18-11-2024 = 

* Added - PayPlus cron now processes "cancelled" or "pending" orders that are over 30 minutes old, created today, have a payment_page_uid, and do not have the cron test flag (to avoid retesting already tested orders).
          Orders that were successful and cancelled manually will not be tested or updated via cron.

= 7.2.1 - 14-11-2024 = 

* Tweak - The Apple Pay script is now loaded locally from the plugin.

= 7.2.0 - 10-11-2024 =

* Add   - Hide "Create document" (Invoice+) option if `payplus_status` is "rejected".
* Add   - Display payment status in the PayPlus metabox.
* Add   - Logs for payloads in order meta.
* Add   - Utilize saved logs for creating refunds (Invoice+), ensuring refund data for products matches the original invoice creation, rather than the current product or site settings.
* Tweak - Express checkout shipping now supports minimum amount rules for displaying free/flat rate and is sorted.
* Tweak - Improved icon resolution.
* Fix   - Resolved missing setting error that occurred on some fresh installations.

= 7.1.8 - 28-10-2024 =

* Add   - Transaction UID handling to the payPlusIpn function.
* Tweak - Callbacks are now consistently received locally and forwarded to the "Callback URL" when defined in plugin settings.

= 7.1.7 - 27-10-2024 =

* Fix   - Callback response.

= 7.1.6 - 13-10-2024 =

* Fix   - Subscription order renewals.
* Fix   - Nonce verification on IPN response for certain users.
* Add   - Option to force Apple Pay script from admin settings.
* Add   - Updated shipping functions for express checkout.
* Tweak - Improved menu translations.

= 7.1.5 - 12-09-2024 =
* Fix - Fixed post function user-agent.
* Add - Added .pot file.

= 7.1.4 - 11-09-2024 =
* Add    - PayPlus orders validator button in the side menu (can be added via plugin advanced settings) and function added—similar to the cron function but manual—for admins only.
* Add    - Show/Hide payment sub-gateways in the side menu (setting available in PayPlus advanced features).
* Add    - Displaying manual payments (admin-created) in the PayPlus metabox.
* Add    - Apple script is now added automatically to all iframes if needed in both checkouts.
* Add    - Added support for free shipping minimum amount conditions for express checkout.
* Fix    - Adjusted iframe width in both checkouts on mobile view; also fixed the close frame button to stay at the top of the frame.
* Fix    - Resolved PHP warning generated from the IP check function, which was missing an `isset` check.
* Fix    - When a translation doesn't exist for "API Mode" in admin settings, display it in English.
* Fix    - Corrected behavior of product/item VAT sent to IPN or Invoice+ documents.
* Fix    - Express Checkout did not display in the last two versions in the classic checkout due to a sanitation error.
* Fix    - Fixed duplicate creation of invoice on "cod - cash on delivery" or "bacs - bank transfer" when "issue an automatic tax invoice" is checked in Invoice+.
* Tweak  - Display multiple charge invoice documents on the orders page and inside the order page metabox.
* Tweak  - Updated token payment error note (for token payments made from the admin).
* Tweak  - Updated Alertify.js version.
* Tweak  - Error message display on mobile "New Checkout Blocks" was too small.
* Tweak  - Improved PayPlus IPN function to eliminate PHP warnings.
* Tweak  - Updated some buttons and colors on the orders page.
* Tweak  - Sanitation and security fixes according to "Plugin Check" plugin repository requirements.
* Tweak  - Block/Disable editing custom fields option (available in PayPlus advanced features).
* Tweak  - Fixed styling of the Express Checkout button in classic checkout.
* Change - On subscription orders (orders that contain at least one subscription product), only credit card payments can be used. Now, only the credit card payment method will be displayed and available.

= 7.1.1 =
* Fix    - Resolved an issue where classic checkout fields were not displaying correctly due to multipass icons logic.
* Fix    - Corrected missing CSS class on the order admin page.
* Fix    - Fixed a redirect issue on the "Thank You" page for users with specific plugins by sanitizing URLs with ampersands.
* Fix    - Addressed a bug where JS was not refreshing payment method fields and totals in classic checkout due to a commented line.
* Fix    - Fixed an issue where invoices generated for token payments in certain flows were incorrectly labeled as “other” instead of displaying the correct details.
* Add    - Added the ability for store managers or admins to make token payments through the edit orders page.
* Add    - Introduced a PayPlus cron checker that, if activated, runs every hour. It checks for orders created in the last two hours with a “pending” status and processes IPNs if a payment page request UID is present.
* Add    - Added new settings for token payments and the PayPlus cron checker.
* Add    - Introduced an option to add custom icons below or instead of the default PayPlus icon in the Checkout Page Options.

[See changelog for all versions](https://plugins.svn.wordpress.org/payplus-payment-gateway/trunk/CHANGELOG.md).