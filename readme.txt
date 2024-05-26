    === PayPlus Payment Gateway ===
    Contributors: payplus
    Tags: Woocommerce Payment Gateway, Credit Cards, Charges and Refunds, Subscriptions, Tokenization
    Requires at least: 3.0.1
    Tested up to: 6.5.3
    Requires PHP: 7.2
    Stable tag: 6.6.5
    PlugIn URL: https://www.payplus.co.il/wordpress
    License: GPLv2 or later
    License URI: http://www.gnu.org/licenses/gpl-2.0.html

    PayPlus.co.il Payment Gateway for WooCommerce extends the functionality of WooCommerce to accept payments from credit/debit cards and choose another alternative method such bit, Apple Pay, Google Pay on a single payment page. With PayPlus Gateway, You can choose a dedicated domain for your own payment page and a lot of different and other settings that will raise your conversions.

    == Description ==
    PayPlus Payment Gateway for WooCommerce
    Makes your website accept debit and credit cards on your WooCommerce store in a safe way and design your own payment page with high functionalities. SSL is not required.

    Before you install this plugin:
    To receive your account credentials you have to contact first PayPlus and to join the service before installing this Plugin

    Plugin Disclaimer:
    PayPlus does not accept liability for any damage, loss, cost (including legal costs), expenses, indirect losses or consequential damage of any kind which may be suffered or incurred by the user from the use of this service.

    Before installation, it is important to know that this plugin relies on third-party services.
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

    == Changelog ==
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