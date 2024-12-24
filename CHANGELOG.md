# Changelog

All notable changes to this project will be documented in this file.

## [7.3.6] = 24-12-2024 - (Sung Jin-woo)

- Fix   - Only the main PayPlus gateway is now displayed when adding a payment method (to save a credit card token).
- Added - Option to hide the number of payments for the Bit payment method.
- Tweak - Added security check to verify the order ID and improved performance of the PayPlus Embedded Subgateway.
- Tweak - Adjusted the size and framing of Express Checkout buttons on the checkout page.
- Tweak - PayPlus Cron and the PayPlus orders check button will now attempt to run at least 4 times before giving up on an order (up from 2 attempts).

## [7.3.5] = 15-12-2024 - (Saiki K)

- Tweak - Replaced the headline text with the Invoice+ logo at the top of the column on the All Orders page.
- Tweak - Renamed the getPayPlusPayload function to getHostedPayload to correct its inaccurate naming.

## [7.3.3] = 12-12-2024 - (Powder)

- Fix - PayPlus Embedded - Resolved an issue where coupons/discounts did not account for taxes on stores with exclusive prices and tax management enabled.
- Tweak - PayPlus Embedded - Improved payload validation before payment, added error messages with reload functionality for payment failures, and implemented minor refactoring to enhance performance by reducing unnecessary checks.
- Tweak - Alertify is now loaded locally to comply with WordPress plugin check requirements.

## [7.3.2] = 10-12-2024 - (Jinx)

- Fixed - Resolved issues with creating refunds for PayPal through Invoice+ after the refactor.
- Logs - Added logging for the invoice creation process.
- Tweak - Removed "hostedFields" (PayPlus Embedded) from manual payment Invoice+ creation.

## [7.3.1] = 08-12-2024 - (Heimerdinger)

- Added - Error messages in PayPlus Embedded now appear at the bottom of the form with a fade-out animation and counter, consistent with the payment pages.
- Added - Support for the "paying_vat" setup in PayPlus Embedded.
- Added - Plugin integrity check during activation.
- Added - Option to block the creation of Invoice+ documents for PayPal via plugin settings.
- Added - "Select Your Cards" feature (optional) to display selected credit card logos from the plugin settings. This affects both the PayPlus main gateway and PayPlus Embedded.
- Fixed - Apple Pay script now loads correctly on websites using iframes.
- Fixed - Error messages were not displayed properly in the new Blocks Checkout; this issue has been resolved.
- Tweak - Enhanced translations for error messages in PayPlus Embedded.
- Tweak - Updated translations for cron options.
- Tweak - Improved plugin sanitation and adherence to WordPress standards via Plugin Check.
- Refactored - Enhanced the process for Invoice+ refunds.
- Removed - Custom icons added via plugin settings using pasted links have been removed and replaced with the "Select Your Cards" feature.

## [7.3.0] = 27-11-2024 - (Ratchet)

- Fix - Resolved - Addressed an issue where Invoice+ documents for orders paid using a PayPal payment plugin (other than PayPlus) were incorrectly labeled as "Other" These documents will now accurately display "PayPal" as the payment method when applicable.

## [7.2.9] = 26-11-2024 - (Ironhide)

- Added - An option to hide the number of payments on Google Pay and Apple Pay payment pages (e.g., to display only a single payment). This option can be configured in each payment method's settings.
- Tweak - Restricted the optional callback in settings to accept only HTTP or HTTPS links.

## [7.2.8] = 25-11-2024 - (PrimeWithFix3 :)

- Fix - Fix - PayPlus Embedded origin for testmode and production.

## [7.2.7] = 25-11-2024 - (PrimeWithFix2 :)

- Fix - Small fix for refund invoices - removed usage of saved payloads.

## [7.2.6] = 24-11-2024 - (PrimeWithFix)

- Fix - Loading iframe issue in redirect and on the next page fixed.

## [7.2.5] = 24-11-2024 - (Prime)

- Add - PayPlus Embedded: An embedded credit card payment form that eliminates the need for a separate payment page during the checkout process. This form is preloaded on the checkout page, allowing customers to securely enter their payment details and complete the transaction seamlessly.

The new feature can be enabled via the admin settings menu under "Subgateways." It can function as a standalone option or alongside existing payment pages. Note that PayPlus Embedded supports credit card payments exclusively.

## [7.2.3] = 18-11-2024 - (Starscream)

- Added - PayPlus cron now processes "cancelled" or "pending" orders that are over 30 minutes old, created today, have a payment_page_uid, and do not have the cron test flag (to avoid retesting already tested orders).
  Orders that were successful and cancelled manually will not be tested or updated via cron.

## [7.2.1] = 14-11-2024 - (Octopus)

- Tweak - The Apple Pay script is now loaded locally from the plugin.

## [7.2.0] - 10-11-2024 - (Optimus)

- Add - Hide "Create document" (Invoice+) option if `payplus_status` is "rejected".
- Add - Display payment status in the PayPlus metabox.
- Add - Logs for payloads in order meta.
- Add - Utilize saved logs for creating refunds (Invoice+), ensuring refund data for products matches the original invoice creation, rather than the current product or site settings.
- Tweak - Express checkout shipping now supports minimum amount rules for displaying free/flat rate and is sorted.
- Tweak - Improved icon resolution.
- Fix - Resolved missing setting error that occurred on some fresh installations.

## [7.1.8] - 28-10-2024 - (Ken)

- Add - Transaction UID handling to the payPlusIpn function.
- Tweak - Callbacks are now consistently received locally and forwarded to the "Callback URL" when defined in plugin settings.

## [7.1.7] - 27-10-2024 (Ryu)

- Fix - Callback response.

## [7.1.6] - 2024-10-13 - (Kaos)

- Fix - Subscription order renewals.
- Fix - Nonce verification on IPN response for certain users.
- Add - Option to force Apple Pay script from admin settings.
- Add - Updated shipping functions for express checkout.
- Tweak - Improved menu translations.

## [7.1.5] - 2024-09-12 - (MonkeyDLuffy)

- Fix - Fixed post function user-agent.
- Add - Added .pot file.

## [7.1.4] - 2024-09-11 - (Robotnik)

- Add - PayPlus orders validator button in the side menu (can be added via plugin advanced settings) and function added—similar to the cron function but manual—for admins only.
- Add - Show/Hide payment sub-gateways in the side menu (setting available in PayPlus advanced features).
- Add - Displaying manual payments (admin-created) in the PayPlus metabox.
- Add - Apple script is now added automatically to all iframes if needed in both checkouts.
- Add - Added support for free shipping minimum amount conditions for express checkout.
- Fix - Adjusted iframe width in both checkouts on mobile view; also fixed the close frame button to stay at the top of the frame.
- Fix - Resolved PHP warning generated from the IP check function, which was missing an `isset` check.
- Fix - When a translation doesn't exist for "API Mode" in admin settings, display it in English.
- Fix - Corrected behavior of product/item VAT sent to IPN or Invoice+ documents.
- Fix - Express Checkout did not display in the last two versions in the classic checkout due to a sanitation error.
- Fix - Fixed duplicate creation of invoice on "cod - cash on delivery" or "bacs - bank transfer" when "issue an automatic tax invoice" is checked in Invoice+.
- Tweak - Display multiple charge invoice documents on the orders page and inside the order page metabox.
- Tweak - Updated token payment error note (for token payments made from the admin).
- Tweak - Updated Alertify.js version.
- Tweak - Error message display on mobile "New Checkout Blocks" was too small.
- Tweak - Improved PayPlus IPN function to eliminate PHP warnings.
- Tweak - Updated some buttons and colors on the orders page.
- Tweak - Sanitation and security fixes according to "Plugin Check" plugin repository requirements.
- Tweak - Block/Disable editing custom fields option (available in PayPlus advanced features).
- Tweak - Fixed styling of the Express Checkout button in classic checkout.
- Change - On subscription orders (orders that contain at least one subscription product), only credit card payments can be used. Now, only the credit card payment method will be displayed and available.

## [7.1.1] - 2024-08-18 - (The Doctor)

- Fix - Resolved an issue where classic checkout fields were not displaying correctly due to multipass icons logic.
- Fix - Corrected missing CSS class on the order admin page.
- Fix - Fixed a redirect issue on the "Thank You" page for users with specific plugins by sanitizing URLs with ampersands.
- Fix - Addressed a bug where JS was not refreshing payment method fields and totals in classic checkout due to a commented line.
- Fix - Fixed an issue where invoices generated for token payments in certain flows were incorrectly labeled as “other” instead of displaying the correct details.
- Add - Added the ability for store managers or admins to make token payments through the edit orders page.
- Add - Introduced a PayPlus cron checker that, if activated, runs every hour. It checks for orders created in the last two hours with a “pending” status and processes IPNs if a payment page request UID is present.
- Add - Added new settings for token payments and the PayPlus cron checker.
- Add - Introduced an option to add custom icons below or instead of the default PayPlus icon in the Checkout Page Options.

## [7.0.9] - 2024-07-15 - (Street Fighter)

- Add - Multipass icons will change with fade in and out on the checkout page.
- Add - Multipass clubs select in plugin settings.
- Add - Brand UID for Sandbox/Development mode.
- Add - Hide products in Invoice+ documents - Option to use "General Product".
- Add - Transaction CC Issuer and Brand name added to PayPlus metabox.
- Change - Invoice+ admin settings language selector in capital letters.
- Change - Design changes for orders: Manual invoice creation tables,manual refunds creation tables and manual payments creaion tables.
- Tweak - Sandbox/Development mode displayed in RED color in plugin settings.
- Fix - Missing nonce in express checkout.
- Fix - Express Checkout activation.
- Fix - Code Refactor for creation of refunds, invoices and receipts.
- Fix - Invoice+ refunds for "General Product" or partial refunds in automatic and manual creation.
- Fix - WP_Filesystem() function check before usage.
- Fix - Corrected redirect link after order refund action via admin (This occured mainly on sites with order edit links like : /wp-admin/post.php?post=167&action=edit...).
- Fix - Callbacks were blocked for some clients because of imporper nonce handling.

## [7.0.8] - 2024-07-15 - (Shinobi)

- Fix - Enable/Disable option in Basic Settings wasn't connected to the correct setting.
- Fix - On bit successful transactions through "uPay" the redirect to thank you page is now corrected for both mobile and desktop.
- Fix - Major security code refactor and updates.
- Add - Admin settings visual changes - In an approach to make the plugin setup easier and clearer.
- Add - Show current API environment mode.
- Add - According to the current API environment mode, display the correct set of keys and hide the other.
- Add - In MULTIPASS method settings - Show warning if transaction type is set for "Authorization" - MULTIPASS only works with "Charge".
- Add - Iframe display of PayPlus FAQ pages plugin settings.

## [7.0.7] - 2024-07-03 - (Dracula)

- Add - PayPlus response json added for express checkout - PayPlus metabox.
- Add - Option to show/hide the PayPlus dedicated metabox on the order page.
- Add - Option to save the PayPlus transaction data to the order note or not... (appears in the metabox)
- Fix - Update order status (on-hold) on callback ipn response for J5 (Approval).
- Fix - In WooCommerce Classic Checkout Page: Show only the selected method description and hide the others.
- Tweak - Express Checkout button design - corrected height of iframe.

## [7.0.6] - 2024-07-01 - (Belmont)

- Fix - Missing options caused debug errors - After update from older versions some website experienced missing options that should have been created automatically. Code now handles the missing options correctly.
- Fix - Fixed auto create payplus error page function.
- Fix - Malformed json received with " inside a string of the json sometimes returns from the PayPlus CRM (in the company name for example), it is now fixed and re-saved as correct json.
- Change - Blocks file name was changed to the new naming convention - part of code refactoring.

## [7.0.2] - 2024-06-30 - (Alucard)

- Change - If Multipass payment is turned on in the payment page it will always show other payment methods (Due to Multipass demand that if there isn't enough balance to cover the order with the voucher customers will always be able to add credit-card payment or else).
- Add - Payplus data metabox - Show all transactions - not only the last one - including related transactions with the total of all at the bottom.
- Add - Payplus data metabox - Show method of payment in the displayed data.
- Tweak - Design changes in settings and metaboxes - logos and colors.
- Tweak - On all orders display page - if there is only one invoice+ document it will be shown as a link and if more than one the arrow list will be displayed.
- Tweak - Express Checkout - Small design changes for mobile and desktop.

## [7.0.1] - 2024-06-27 - (Megaman)

- Fix - Small but important J5 fix for invoices with split payments.

## [6.6.9] - 2024-06-24 - (WonderBoy)

- Add - Iframe in the same page in WooCommerce Checkout Blocks.
- Add - Iframe popup in WooCommerce Checkout Blocks.
- Add - Error handling in WooCommerce Checkout Blocks.
- Add - Basic Settings Tab - Setup the plugin most important settings and start working immediately. This tab holds the main settings to activate the plugin. These are still available in the regular "Settings" Tab also.
- Fix - Removed filter: 'acf/settings/remove_wp_meta_box' was supposed to show custom fields on website with ACF, However it caused heavy load times - In future releases a diffrent solution will be offered.
- Add - New logos!
- Tweak - Code refactoring - Admin fields and settings were moved to their own files and are loaded with static functions for better readability.
- Fix - Get meta data for products transaction type and balance_name.
- Change - PayPlus Error Page - No longer uses a short code - it displays a simple text message - users can edit it or create a different page with the same permlink instead.
- Tweak - Css cache is updated according to the version, will refresh on update, no need to manually refresh.
- Tweak - Minified all css and js files in use.
- Add - Auto activate newly joined payment method (bit,google-pay,apple-pay...) in settings from PayPlus support (Happens only once on joining to service).
- Add - Payplus data metabox inside order page.
- Fix - Only one payment page per domain.

## [6.6.8] - 2024-05-03 - (Eggman)

### Changes

- Add - Display PayPlus Invoice+ charges and refunds in a dedicated metabox in the order page.
- Add - Option to hide the Invoice+ links in the order notes in Invoice+ settings.
- Add - Display PayPlus Invoice+ docs without activation of Invoice+.
- Add - Display PayPlus Invoice+ refunds and invoices in all orders page via show/hide list arrow button.
- Fix - Fire Completed is fired only if not default-woo is selected and not together.
- Add - Hide Delete/Update custom fields - with option in the settings to be cancelled - default is yes.
- Tweak - Location of plugin credit (bottom of the page in plugin settings).
- Tweak - Hide PayPlus loader if "Make Payment" fails because amount is larger than allowed.
- Change - Disable express checkout functions run if not enabled.
- Fix - Check if product variable is a valid product object in express checkout function.
- Add - Prepare plugin support for Secure3d - with saved cards only.
- Fix - Bit payments redirection after successful order purchase from uPay on mobile phones.
- Tweak - Database version update - refactored options check and settings to run only when needed.
- Add - Payplus data metabox inside order page.
- Fix - Only one payment page per domain.

## [6.6.7] - 2024-05-29 - (Knuckles)

### Changes

- Tweak - Multiple refunds can now be done in an automatic way as well as manual.
- Tweak - Some hebrew translations were fixed.
- Tweak - Added description to fire completed configuration option.
- Tweak - Added error message when an error occurs on "Make Payment" in admin orders edit.
- Fix - "Make Payment" button for J5 payment now allows admin to charge up until original J5 charge.
- Fix - Removed short code usage with payplus error page.

## [6.6.5] - 2024-05-26 - (Tails)

### Changes

- Tweak - New apple developer merchantid domain association file.
- Tweak - Show 0 priced products in invoices.
- Tweak - "Get PayPlus Data" button now adds all payplus meta fields to the order meta.
- Fix - Invoices created with "Get PayPlus Data" button will have correct payment method data.

## [6.6.4] - 2024-05-26 - (Sonic)

### Changes

- Fix - Bug preventing save users in admin.
- Fix - Show correct SKU when invoice with more than one variation product exist.
- Add - Mark in red - When Invoice+ is enabled shows the user which fields must be set.

## [6.6.3] - 2024-05-19 - (The New Way)

### Changes

- Add - Check/Get order - ipn data from payplus in Admin orders via button click.
- Add - "Website code" - Added to Invoice+: Add a unique string for each website if you have more than one website connected to our gateway.
- Add - Save credit card checkbox in new WooCommerce Checkout Blocks.
- Tweak - Refactor for meta data to use High Performance Order Storage - HPOS with support for stores without - will be supported for traditional post meta records - for existing orders and stores that have no current support for HPOS.
- Add - Legacy post meta support checkbox - Default is checked - In future releases this will be unchecked. (Plugin users that have been using our gateway up until now will be able to view all data that was stored in the post meta fields) - for more information regarding HPOS go to: https://woocommerce.com/document/high-performance-order-storage/
- Add - Invoice check and update to admin on creation - If an invoice has already been created and for some reason it has not been updated to the admin orders panel, it's link will appear and it's data will be shown without duplicate creation.
- Add - Notice for customer when they update their billing address. (Regarding saved tokens)
- Fix - Create invoice in a non-automatic management interface in different amount than the original order.
- Fix - Save credit cards tokens - during checkout. (Supported on both classic and new checkout blocks).
- Fix - Payments with saved tokens now work on all checkout pages (Redirect, Iframe, Iframe on the same page, Iframe in a pop-up).
- Fix - Save credit card tokens - no duplicates.
- Fix - Removed log warnings for non-existing keys.
- Fix - Save credit card token with brand name. (works only with newly saved card tokens from now on)
- Fix - Correct display information on invoice for card on invoice creation with token payment.
- Fix - Display correct currency on automatic invoice creation.
- Fix - Add/Save Payment methods through "My account -> Payment methods" now saves the token with customer default billing and will work on checkout (When billing information is the same).
- Fix -J5 Invoice creation from admin with partial amount paid - fixed.

## [6.6.2] - 2024-02-01

### Changes

- Add - Support for the new WooCommerce Checkout Blocks.
