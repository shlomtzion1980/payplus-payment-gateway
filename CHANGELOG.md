# Changelog

All notable changes to this project will be documented in this file.

##  [7.0.9] - 2024-07-15 - (Street Fighter)

* Fix    - Missing nonce in express checkout.
* Fix    - Express checkout activation.
* Add    - Multipass icons will change with fade in and out on the checkout page.
* Add    - Multipass select clubs settings in plugin settings.
* Add    - Brand UID for Sandbox/Development mode.


##  [7.0.8] - 2024-07-15 - (Shinobi)

* Fix    - Enable/Disable option in Basic Settings wasn't connected to the correct setting.
* Fix    - On bit successful transactions through "uPay" the redirect to thank you page is now corrected for both mobile and desktop.
* Fix    - Major security code refactor and updates.
* Add    - Admin settings visual changes - In an approach to make the plugin setup easier and clearer.
* Add    - Show current API environment mode.
* Add    - According to the current API environment mode, display the correct set of keys and hide the other.
* Add    - In MULTIPASS method settings - Show warning if transaction type is set for "Authorization" - MULTIPASS only works with "Charge".
* Add    - Iframe display of PayPlus FAQ pages plugin settings.

##  [7.0.7] - 2024-07-03 - (Dracula)

* Add    - PayPlus response json added for express checkout - PayPlus metabox.
* Add    - Option to show/hide the PayPlus dedicated metabox on the order page.
* Add    - Option to save the PayPlus transaction data to the order note or not... (appears in the metabox)
* Fix    - Update order status (on-hold) on callback ipn response for J5 (Approval).
* Fix    - In WooCommerce Classic Checkout Page: Show only the selected method description and hide the others.
* Tweak  - Express Checkout button design - corrected height of iframe.

##  [7.0.6] - 2024-07-01 - (Belmont)

* Fix    - Missing options caused debug errors - After update from older versions some website experienced missing options that should have been created automatically. Code now handles the missing options correctly.
* Fix    - Fixed auto create payplus error page function.
* Fix    - Malformed json received with " inside a string of the json sometimes returns from the PayPlus CRM (in the company name for example), it is now fixed and re-saved as correct json.
* Change - Blocks file name was changed to the new naming convention - part of code refactoring.

##  [7.0.2] - 2024-06-30 - (Alucard)

* Change - If Multipass payment is turned on in the payment page it will always show other payment methods (Due to Multipass demand that if there isn't enough balance to cover the order with the voucher customers will always be able to add credit-card payment or else).
* Add    - Payplus data metabox - Show all transactions - not only the last one - including related transactions with the total of all at the bottom.
* Add    - Payplus data metabox - Show method of payment in the displayed data.
* Tweak  - Design changes in settings and metaboxes - logos and colors.
* Tweak  - On all orders display page - if there is only one invoice+ document it will be shown as a link and if more than one the arrow list will be displayed.
* Tweak  - Express Checkout - Small design changes for mobile and desktop.

##  [7.0.1] - 2024-06-27 - (Megaman)

* Fix    - Small but important J5 fix for invoices with split payments.

##  [6.6.9] - 2024-06-24 - (WonderBoy)

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

##  [6.6.8] - 2024-05-03 - (Eggman)

### Changes 

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
* Add    - Payplus data metabox inside order page.
* Fix    - Only one payment page per domain.

##  [6.6.7] - 2024-05-29 - (Knuckles)

### Changes

* Tweak - Multiple refunds can now be done in an automatic way as well as manual.
* Tweak - Some hebrew translations were fixed.
* Tweak - Added description to fire completed configuration option.
* Tweak - Added error message when an error occurs on "Make Payment" in admin orders edit.
* Fix   - "Make Payment" button for J5 payment now allows admin to charge up until original J5 charge. 
* Fix   - Removed short code usage with payplus error page.

##  [6.6.5] - 2024-05-26 - (Tails)

### Changes

* Tweak - New apple developer merchantid domain association file.
* Tweak - Show 0 priced products in invoices.
* Tweak - "Get PayPlus Data" button now adds all payplus meta fields to the order meta.
* Fix   - Invoices created with "Get PayPlus Data" button will have correct payment method data.

##  [6.6.4] - 2024-05-26 - (Sonic)

### Changes

* Fix - Bug preventing save users in admin.
* Fix - Show correct SKU when invoice with more than one variation product exist.
* Add - Mark in red - When Invoice+ is enabled shows the user which fields must be set.

## [6.6.3] - 2024-05-19 - (The New Way)

### Changes

* Add   - Check/Get order - ipn data from payplus in Admin orders via button click.
* Add   - "Website code" - Added to Invoice+: Add a unique string for each website if you have more than one website connected to our gateway.
* Add   - Save credit card checkbox in new WooCommerce Checkout Blocks.
* Tweak - Refactor for meta data to use High Performance Order Storage - HPOS with support for stores without - will be supported for traditional post meta records - for existing orders and stores that have no current support for HPOS.
* Add   - Legacy post meta support checkbox - Default is checked - In future releases this will be unchecked. (Plugin users that have been using our gateway up until now will be able to view all data that was stored in the post meta fields) - for more information regarding HPOS go to: https://woocommerce.com/document/high-performance-order-storage/
* Add   - Invoice check and update to admin on creation - If an invoice has already been created and for some reason it has not been updated to the admin orders panel, it's link will appear and it's data will be shown without duplicate creation.
* Add   - Notice for customer when they update their billing address. (Regarding saved tokens)
* Fix - Create invoice in a non-automatic management interface in different amount than the original order.
* Fix - Save credit cards tokens - during checkout. (Supported on both classic and new checkout blocks).
* Fix - Payments with saved tokens now work on all checkout pages (Redirect, Iframe, Iframe on the same page, Iframe in a pop-up).
* Fix - Save credit card tokens - no duplicates.
* Fix - Removed log warnings for non-existing keys.
* Fix - Save credit card token with brand name. (works only with newly saved card tokens from now on)
* Fix - Correct display information on invoice for card on invoice creation with token payment.
* Fix - Display correct currency on automatic invoice creation.
* Fix - Add/Save Payment methods through "My account -> Payment methods" now saves the token with customer default billing and will work on checkout (When billing information is the same).
* Fix -J5 Invoice creation from admin with partial amount paid - fixed.

## [6.6.2] - 2024-02-01

### Changes

* Add - Support for the new WooCommerce Checkout Blocks.
