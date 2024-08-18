=== PayPlus Payment Gateway ===
Contributors: payplus
Tags: Woocommerce Payment Gateway, Credit Cards, Charges and Refunds, Subscriptions, Tokenization
Requires at least: 3.0.1
Tested up to: 6.6.1
Requires PHP: 7.4
Stable tag: 7.1.0
PlugIn URL: https://www.payplus.co.il/wordpress
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept credit/debit card payments or other methods such as bit, Apple Pay, Google Pay in one page.
Create digitally signed invoices & much more!

== Description ==
PayPlus Payment Gateway for WooCommerce
Accept debit and credit cards on your WooCommerce store in a secure way with the ability to design your own payment page and add high functionalities to it. SSL is not required.

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

= 7.1.0 =
* Fix    - Classic checkout fields didn't update correctly (display issue only) - because of multipass icons logic - fixed. 
* Fix    - CSS for order admin page - was missing a class.
* Fix    - Thank you page redirect - fixed for users with certain plugins (sanitized url with ampersand was corrected to work).
* Fix    - JS Refresh payment method fields and totals in classic checkout wasn't refreshing because of commented line.
* Fix    - Invoice+ auto created for token payment on certain flows was created with "other" instead of correct details.
* Add    - Make token payment for store manager or admin, through edit orders.
* Add    - PayPlus cron checker - If activated, runs every hour and checks for orders created in the last two hours with "pending" status and if they have a payment page request uid - runs ipn.
* Add    - Settings for token payment and for PayPlus cron checker.
* Add    - Option to add custom icons under or instead of the PayPluys default icon (Checkout Page Options).

= 7.0.9 = 
* Add    - Multipass icons will change with fade in and out on the checkout page.
* Add    - Multipass clubs select in plugin settings.
* Add    - Brand UID for Sandbox/Development mode.
* Add    - Hide products in Invoice+ documents - Option to use "General Product".
* Add    - Transaction CC Issuer and Brand name added to PayPlus metabox.
* Change - Invoice+ admin settings language selector in capital letters.
* Change - Design changes for orders: Manual invoice creation tables,manual refunds creation tables and manual payments creation tables.
* Tweak  - Sandbox/Development mode displayed in RED color in plugin settings.
* Fix    - Missing nonce in express checkout.
* Fix    - Express Checkout activation.
* Fix    - Code Refactor for creation of refunds, invoices and receipts.
* Fix    - Invoice+ refunds for "General Product" or partial refunds in automatic and manual creation.
* Fix    - WP_Filesystem() function check before usage.
* Fix    - Corrected redirect link after order refund action via admin (This occured mainly on sites with order edit links like : /wp-admin/post.php?post=167&action=edit...).
* Fix    - Callbacks were blocked for some clients. (Nonce issue)
* Fix    - New user creation on checkout process. (Works with classic checkout fully and with New Blocks Checkout in iFrame popup and in iFrame on the same page) (Nonce issue)
* Fix    - ipn_response() blocked for some because of nonce issue. (Nonce issue)

= 7.0.8 =
* Fix    - Enable/Disable option in Basic Settings wasn't connected to the correct setting.
* Fix    - On bit successful transactions through "uPay" the redirect to thank you page is now corrected for both mobile and desktop.
* Fix    - Major security code refactor and updates.
* Add    - Admin settings visual changes - In an approach to make the plugin setup easier and clearer.
* Add    - Show current API environment mode.
* Add    - According to the current API environment mode, display the correct set of keys and hide the other.
* Add    - In MULTIPASS method settings - Show warning if transaction type is set for "Authorization" - MULTIPASS only works with "Charge".
* Add    - Iframe display of PayPlus FAQ pages plugin settings.

= 7.0.7 = 
* Add    - PayPlus response json added for express checkout - PayPlus metabox.
* Add    - Option to show/hide the PayPlus dedicated metabox on the order page.
* Add    - Option to save the PayPlus transaction data to the order note or not... (appears in the metabox)
* Fix    - Update order status (on-hold) on callback ipn response for J5 (Approval).
* Fix    - In WooCommerce Classic Checkout Page: Show only the selected method description and hide the others.
* Tweak  - Express Checkout button design - corrected height of iframe.

= 7.0.6 = 
* Fix    - Missing options caused debug errors - After update from older versions some website experienced missing options that should have been created automatically. Code now handles the missing options correctly.
* Fix    - Fixed auto create payplus error page function.
* Fix    - Malformed json received with " inside a string of the json sometimes returns from the PayPlus CRM (in the company name for example), it is now fixed and re-saved as correct json.
* Change - Blocks file name was changed to the new naming convention - part of code refactoring.

= 7.0.2 = 
* Change - If Multipass payment is turned on in the payment page it will always show other payment methods (Due to Multipass demand that if there isn't enough balance to cover the order with the voucher customers will always be able to add credit-card payment or else).
* Add    - Payplus data metabox - Show all transactions - not only the last one - including related transactions with the total of all at the bottom.
* Add    - Payplus data metabox - Show method of payment in the displayed data.
* Tweak  - Design changes in settings and metaboxes - logos and colors.
* Tweak  - On all orders display page - if there is only one invoice+ document it will be shown as a link and if more than one the arrow list will be displayed.
* Tweak  - Express Checkout - Small design changes for mobile and desktop.

= 7.0.1 = 
* Fix    - Small but important J5 fix for invoices with split payments.

[See changelog for all versions](https://plugins.svn.wordpress.org/payplus-payment-gateway/trunk/CHANGELOG.md).