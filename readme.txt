=== PayPlus Payment Gateway ===
Contributors: payplus
Tags: Woocommerce Payment Gateway, Credit Cards, Charges and Refunds, Subscriptions, Tokenization
Requires at least: 3.0.1
Tested up to: 6.5.3
Requires PHP: 7.2
Stable tag: 7.0.2
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

= 7.0.2 = 
* Change - If Multipass payment is turned on in the payment page it will always show other payment methods (Due to Multipass demand that if there isn't enough balance to cover the order with the voucher customers will always be able to add credit-card payment or else).
* Add    - Payplus data metabox - Show all transactions - not only the last one - including related transactions with the total of all at the bottom.
* Add    - Payplus data metabox - Show method of payment in the displayed data.
* Tweak  - Design changes in settings and metaboxes - logos and colors.
* Tweak  - On all orders display page - if there is only one invoice+ document it will be shown as a link and if more than one the arrow list will be displayed.
* Tweak  - Express Checkout - Small design changes for mobile and desktop.

= 7.0.1 = 
* Fix    - Small but important J5 fix for invoices with split payments.

= 6.6.9 = 
* Add    - Iframe in the same page in WooCommerce Checkout Blocks.
* Add    - Iframe popup in WooCommerce Checkout Blocks.
* Add    - Error handling in WooCommerce Checkout Blocks.
* Add    - Basic Settings Tab - Setup the plugin most important settings and start working immediately. This tab holds the main settings to activate the plugin. These are still available in the regular "Settings" Tab also.
* Fix    - Removed filter: 'acf/settings/remove_wp_meta_box' was supposed to show custom fields on website with ACF, However it caused heavy load times - In future releases a diffrent solution will be offered.
* Add    - New logos!
* Tweak  - Code refactoring - Admin fields and settings were moved to their own files and are loaded with static functions for better readability.
* Fix    - Get meta data for products transaction type and balance_name.
* Change - PayPlus Error Page - No longer uses a short code - it displays a simple text message - users can edit it or create a different page with the same permlink instead.
* Tweak  - Css cache is updated according to the version, will refresh on update, no need to manually refresh.
* Tweak  - Minified all css and js files in use.
* Add    - Auto activate newly joined payment method (bit,google-pay,apple-pay...) in settings from PayPlus support (Happens only once on joining to service).
* Add    - Payplus data metabox inside order page.
* Fix    - Only one payment page per domain.

[See changelog for all versions](https://plugins.svn.wordpress.org/payplus-payment-gateway/trunk/CHANGELOG.md).