=== PayPlus Payment Gateway ===
Contributors: payplus
Tags: Woocommerce Payment Gateway, Credit Cards, Charges and Refunds, Subscriptions, Tokenization
Requires at least: 3.0.1
Tested up to: 6.5.3
Requires PHP: 7.2
Stable tag: 6.6.8
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
7. Enter the 3 credentials you have received from PayPlus (when you signed up with us).
8. Save your settings, now you have an active payment page!

== Changelog ==

= 6.6.8 = 
* Add    - Display PayPlus Invoice+ charges and refunds in a dedicated metabox in the order page.
* Add    - Option to hide the Invoice+ links in the order notes in Invoice+ settings.
* Add    - Display PayPlus Invoice+ docs without activation of Invoice+.
* Add    - Display PayPlus Invoice+ refunds and invoices in all orders page via show/hide list arrow button. 
* Fix    - Fire Completed is fired only if not default-woo is selected and not together.
* Add    - Hide Delete/Update custom fields - with option in the settings to be cancelled - default is yes.
* Tweak  - Location of plugin credit (bottom of the page in plugin settings).
* Tweak  - Hide PayPlus loader if "Make Payment" fails because amount is larger than allowed.
* Change - Disable express checkout functions run if not enabled.
* Fix    - Check if product variable is a valid product object in express checkout function.
* Add    - Prepare plugin support for Secure3d - with saved cards only.
* Fix    - Bit payments redirection after successful order purchase from uPay on mobile phones.
* Tweak  - Database version update - refactored options check and settings to run only when needed.


= 6.6.7 =
* Tweak - Multiple refunds can now be done in an automatic way as well as manual.
* Tweak - Some hebrew translations were fixed.
* Tweak - Added description to fire completed configuration option.
* Tweak - Added error message when an error occurs on "Make Payment" in admin orders edit.
* Fix   - "Make Payment" button for J5 payment now allows admin to charge up until original J5 charge. 
* Fix   - Removed short code usage with payplus error page.


= 6.6.5 =
* Tweak - New apple developer merchantid domain association file.
* Tweak - Show 0 priced products in invoices.
* Tweak - "Get PayPlus Data" button now adds all payplus meta fields to the order meta.
* Fix   - Invoices created with "Get PayPlus Data" button will have correct payment method data.

= 6.6.4 =
* Fix - Bug preventing save users in admin.
* Fix - Show correct SKU when invoice with more than one variation product exist.
* Add - Mark in red - When Invoice+ is enabled shows the user which fields must be set.

= 6.6.3 =
* Add - Check/Get order - ipn data from payplus in Admin orders via button click.
* Fix - Create invoice in a non-automatic management interface.
* Add - "Website code" - Added to Invoice+: Add a unique string for each website if you have more than one website connected to our gateway.
* Add - Save credit card checkbox in new WooCommerce Checkout Blocks.
* Add - Refactor for meta data to use High Performance Order Storage - HPOS with support for stores without - will be supported for traditional post meta records - for existing orders and stores that have no current support for HPOS.
* Add - Legacy post meta support checkbox - Default is checked - In future releases this will be unchecked. (Plugin users that have been using our gateway up until now will be able to view all data that was stored in the post meta fields) - for more information regarding HPOS go to: https://woocommerce.com/document/high-performance-order-storage/
* Add - Invoice check and update to admin on creation - If an invoice has already been created and for some reason it has not been updated to the admin orders panel, it's link will appear and it's data will be shown without duplicate creation.
* Add - Notice for customer when they update their billing address. (Regarding saved tokens)

= 6.6.2 =
* Add - Support for the new WooCommerce Checkout Blocks.

== Upgrade Notice ==
= 6.6.5 =
* This update includes some minor patches and a new apple merchantid for domain association. Please update for the latest fixes.