# Changelog

All notable changes to this project will be documented in this file.

## [7.8.2] - 06-07-2025 - (Sylvester)
- Fix   - Resolved an issue where, on classic checkout, standard payment pages would always open via redirect on subsequent accesses, disregarding the configured page settings.

## [7.8.1] - 06-07-2025 - (Rocky)

- Added - Option to: Auto-adjust iframe height for screen size and zoom (overrides the Iframe Height setting above) - Only for classic checkout!

## [7.8.0] - 29-06-2025 - (MeDew)

- Added - Do not create invoices+ documents option for Stripe payment gateway.

## [7.7.9] - 11-06-2025 - (Metro)

- Added - Separate VAT configuration options for international customers.

## [7.7.8] - 27-05-2025 - (duckPOS)

- Tweak - Improved the `payPlusRemote()` function to better handle `$payload` issues.
- Tweak - Removed outdated logs and deprecated API calls.
- Tweak - Optimized payment page logic to reuse existing pages when possible, reducing unnecessary API requests.
- Tweak - Corrected inaccurate status reporting in "Orders Reports/Validator".
- Tweak - Streamlined `callback_response` by removing redundant functions and passing data directly instead of using SQL.
- Added - When "Update status in IPN" is enabled, the callback function will skip status updates and related checks.
- Added - Disallow voucher payment for shipping - Enforce a minimum amount for non-voucher payments: voucher payments can no longer be used to pay for shipping, preventing customers from covering delivery costs with vouchers.

## [7.7.7] - 20-05-2025 - (EuroVision)

- Tweak - Improved the handling of payplus_page_request_uid by payPlusIpn function.

## [7.7.6] - 18-05-2025 - (Bundy)

- Added - Invoice+: The option to "Do not create documents for zero-total orders" in Invoice+ settings (Consult your accountant regarding the use of this feature).
- Fix - Invoice+: Resolved issue where invoices for zero-total orders (using gift cards) with shipping costs incorrectly displayed the shipping amount instead of zero.

## [7.7.5] - 11-05-2025 - (HoneyBody)

- Added - Support for Wire Transfers as a PayPlus subgateway.
- Added - Dedicated admin settings menus for POS EMV and Wire Transfers.

## [7.7.4] - 05-05-2025 - (Kaiju)

- Added - Introduced a dedicated metabox to consolidate PayPlus action buttons.
- Tweak - Refreshed the visual design of PayPlus action buttons.
- Tweak - The "Yes/No" button in PayPlus Orders Reports/Validator is now hidden if no orders are found.
- Fix - Addressed a null reference error for `$objectProducts` that appeared in debug logs.

## [7.7.3] - 27-04-2025 - (Sharks)

- Tweak - Express Checkout now displays specific coupon names instead of a generic "discount" label (when applicable).
- Tweak - Enhanced security through updated nonce implementation.
- Added - Option to hide the main PayPlus gateway on the classic checkout page (useful for merchants primarily using POS EMV).
- Added - Setting to prevent automatic Invoice+ document creation for other POS gateways transactions (when using POS Override).
- Added - EMV POS payment option is now hidden during standard customer checkout even when activated.
- Added - Support for including the "Brand" field in EMV POS documents.
- Tweak - Improved error handling for EMV POS transactions.

## [7.7.2] - 14-04-2025 - (LifeLine)

- Fix - Resolved an issue where POS EMV refunds were not processing as expected.
- Tweak - Improved the payment payload generation function while ensuring compatibility with legacy systems.
- Fix - Addressed a problem where Invoice+ documents displayed incorrect details and amounts when Coupons and PW Gift Cards were used together in a transaction, including cases with zero-amount payment invoices.
- Tweak - Enabled support for generating Invoice+ documents for PW Gift Cards at a later time.
- Added - Support for subscriptions in PayPlus Embedded for logged-in users.
- Tweak - The close iframe button in blocks checkout now consistently appears in black.
- Tweak - Initial refactor of the generatePayloadLink function, now renamed to generatePaymentLink. Both the new and "legacy" payloads are supported. If issues arise with the new payload, you can revert to the "legacy" payload by enabling the "Use legacy payload function" checkbox in the plugin settings.
- Added - Feature: Pay with POS EMV as a subgateway from the checkout page.
- Added - Multiple payments details are now included in Invoice+ documents.
- Tweak - Verified compatibility with WordPress version 6.8.
- Fix - Resolved an issue with new checkout blocks where "PayPlus Embedded" was unnecessarily hidden based on tokens.
- Tweak - Updated translations for recently added plugin settings.

## [7.7.1] - 30-03-2025 - (Ippo)

- Tweak - Adjusted custom icons (payplus gateway on checkout) sizes for better display.
- Added - Support for percentage-based coupons.
- Fix - Corrected implementation of Partners mode for certain integrations.
- Tweak - Resolved display issues with PayPlus Embedded.
- Added - Support for Partner coupons and the option for dual delivery warehouses.
- Added - Compatibility with the PW Gift Cards Plugin for PayPlus Embedded and Credit Card payments on "Classic Checkout" (PW Gift Cards are not supported in "Blocks Checkout").
- Tweak - Added an option in Invoice+ settings to choose whether coupons are presented as a discount line or as a product.
- Fix - Resolved the "invalid-app-name" issue during Invoice+ document creation by using the payload object instead of deprecated database queries.
- Fix - Resolved all POS EMV Invoices came out with "General Product".

## [7.7.0] - 11-03-2025 - (Gaara)

- Fix - Subscription orders with the "Mark as paid" option enabled will now correctly be set to "completed" status upon successful renewal.
- Tweak - Disabled cart hash verification for additional testing due to issues on certain payment method pages.

## [7.6.9] - 10-03-2025 - (Zabuza)

- Fix - [Invoice+] - Resolved issue (payments-total-not-equal-to-calculated-total) where J5 transactions with adjusted amounts for items (more or less than the original) did not create an invoice when coupons were used. This is now possible.
- Fix - [Invoice+] - Edited J5 orders (Items and Total) will create an Invoice+ document that accurately reflects the order details instead of just showing "General Product".
- Tweak - [Invoice+] - Subscription orders renewals Invoice+ docs will show correct payment method instead of "other".

## [7.6.8] - 09-03-2025 - (Kakashi)

- Added - Support for payments using EMV POS devices (Admin Only).
- Added - Invoice+: Option to block document creation for "bacs" (Direct bank transfer) and "cod" (Cash on Delivery) in automatic mode.
- Fix - Resolved PHP warning related to array to string conversion.
- Tweak - ForceInvoice can now run with ReportOnly IPN on Orders Validator.
- Added - Create Invoice+ Auto Doc button: This button will create the document without changing status if it wasn't created for any reason, according to document settings.
- Added - Show Create Invoice+ Auto Doc button via checkbox in Invoice+ settings.
- Added - Bank Wire Transfer method to Invoice+ payment types instead of showing "other".
- Added - Cash On Delivery method to Invoice+ payment types instead of showing "other".
- Added - Cheque/Check method to Invoice+ payment types instead of showing "other".
- Fix - Resolved issue where the payment method logo for subgateways was not hidden.
- Fix - Corrected issue where the save payment method checkbox appeared twice in "Blocks Checkout" on some themes.
- Tweak - Empty cart if it exists on ipn_response without nonce but after successful payment.
- Tweak - Subscription renewal payments will now include "payplus_response" and display the data in the metabox.

## [7.6.7] - 06-03-2025 - (Haku)

- Fix - Resolved subscription renewal failure caused by a missing cart.

## [7.6.6] - 05-03-2025 - (Dumb)

- Fix - Resloved status updates issues - orders updated twice on not at all.

## [7.6.5] - 04-03-2025 - (Snarl)

- Fix - Resolved an issue with an undefined JavaScript variable.

## [7.6.4] - 03-03-2025 - (Swoop)

- Tweak - Enhanced security for IPN responses.

## [7.6.2] - 03-03-2025 - (Slag)

- Tweak - Enhanced order IPN event to run 2 minutes after the payment page is triggered when using classic checkout.
- Added - Compatibility with "YITH WooCommerce Gift Cards" (free version) in PayPlus Embedded.
- Fix - Resolved a JS visual bug on the "Orders page" where a variable was defined in the wrong place.

## [7.6.1] - 02-03-2025 - (Fang)

- Tweak - Express Checkout Initialization now displays the payment page UID for the activated feature.
- Tweak - PayPlus Orders Reports/Validator in "Partners mode" now includes a "Create invoice" option.
- Tweak - Express Checkout buttons and phone field are now centered, with improved validation message colors and translations.
- Tweak - Enhanced IPN Response with improved NONCE and Cart Hash testing even more.

## [7.6.0] - 25-02-2025 - (Liebe)

- Tweak - Updated translations for new express checkout settings.
- Tweak - Added sanitation and validation for express checkout data.
- Fix - Resolved issue where "Use global default" wasn't working in subgateways (Display mode).
- Fix - Fixed nonce issue occurring with 3D Secure transactions when redirected to the thank you page - nonce didn't pass.
- Tweak - Enhanced security for nonce exploit issue on the thank you page - with option to disable from settings.

## [7.5.9] - 17-02-2025 - (Zora)

- Added - Option to require a phone number for Google Pay Express Checkout.
- Tweak - Translations to hebrew.
- Tweak - Implemented wp_cache and transient to minimize several database queries.

## [7.5.8] - 11-02-2025 - (Noelle)

- Tweak - Enhanced PayPlus Cron: Now runs every 30 minutes, manages both Invoice+ and non-Invoice+ cancelled orders, and provides improved logging in both logs and order notes.
- Tweak - Optimized callback feature for improved speed and efficiency.
- Fix - Resolved an issue where the custom icons length was undefined in JavaScript when no custom icons were selected for the checkout page.
- Tweak - "Make Payment" button for J5 (Approval) orders with a "processing" status will be hidden, even if they are unpaid.
- Fix - Resolved rounding issue for J5 payment error when order products were removed and the total amount was adjusted.

## [7.5.7] - 09-02-2025 - (Agrippa)

- Fix - Resolved shipping issue with Express Checkout on the product page wasn't working when "Shipping by WooCommerce Via JS" was activated. Now it works in combination with the one of the other options.
- Fix - Corrected rounding error that prevented charging with error message: "Cannot charge more than the total order amount on J5" on specific issues.
- Tweak - PayPlus Orders Validator: When "Enable Partners Dev Mode" is enabled, orders can be selected by year and month. Additionally, a visual table is available when "Enable display of orders table select in PayPlus Orders Validator" is enabled.
- Tweak - PayPlus Orders Validator: When "Enable Partners Dev Mode" is enabled, added "Actions" with the ability to run reports only, force all, get invoice, and force invoice.
- Tweak - PayPlus Orders Validator: Will not mark orders as cron tested, allowing the cron to run on these orders if activated.
- Add - Prevention of double deals under the same order number for websites with heavy traffic and callback issues. The "Double check IPN" feature checks if an order already has a "payplus_page_request_uid" before attempting to start a new payment.

## [7.5.5] - 02-02-2025 - (Greed)

- Fix - Resolved issue where PayPlus Embedded was stuck on loading for certain templates.
- Tweak - If a callback arrives and the order contains no payplus_response, the callback will run IPN as well.
- Tweak - Fixed/Cleaned PHP warnings of missing array keys in specific cases (warning messages only).
- Tweak - Centered display of multiple icons on PayPlus Embedded and main gateway in mobile view to fit cases with many icons.
- Tweak - Improved logging for PayPlus Embedded.
- Added - For PayPlus Partners Only - PayPlus Orders Validator can now run in report mode only, and by month, year, and much more. (For more information, contact PayPlus and ask about the Partners program.)

## [7.5.4] - 28-01-2025 - (Asta)

- Tweak - Adjusted the CSS for the popup iframe close button's top position on iPhones.

## [7.5.3] - 27-01-2025 - (Beer)

- Added - Support for Express Checkout shipping in the classic checkout, consistent with the WooCommerce checkout page.

## [7.5.2] - 20-01-2025 - (Beru)

- Added - Option to show or hide the "Place Order" button within the PayPlus Embedded form.
- Tweak - Updated the Bit payment method logo.
- Tweak - Adjusted the arrow display position for multiple payments in mobile view for PayPlus Embedded.
- Added - Checkbox for "Partners Dev Mode" with initial support for one filter, more to be added soon.

## [7.5.1] - 14-01-2025 - (Talula)

- Fix - Adjusted the inline styling for PayPlus payment logos to ensure correct height and width.
- Fix - Resolved an error when adding a payment method due to logging issues.
- Tweak - Added and corrected missing Hebrew translations.

## [7.5.0] - 08-01-2025 - (Zepplin)

- Fix - Enhanced the previous version to save payloads more efficiently and cleanly.

## [7.4.8] - 08-01-2025 - (Led)

- Tweak - Improved the invoice refund process to avoid relying solely on the invoice payload, preventing issues with unicode conversion.

## [7.4.6] - 07-01-2025 - (Portgas D. Ace)

- Tweak - Added support for the Transaction Type product field in both the PayPlus Embedded and main gateway.
- Tweak - Updated instructions for the Transaction Type product field.
- Added - PayPlus Embedded now supports the Successful Order Status and Payment Completed settings.
- Tweak - Added an indicator message to the Invoice+ VAT Settings to notify users when the WooCommerce taxes feature is enabled.

## [7.4.5] - 06-01-2025 - (Gol D. Roger)

- Fix - If the main VAT settings in Invoice+ are unchecked, the vat-type-exempt will now be sent.
- Tweak - Resolved an issue where the Apple Pay script was loaded multiple times if the payment window was closed and reopened without refreshing.
- Tweak - Refactored and removed unnecessary class calls and queries in the Invoices class for improved efficiency.
- Tweak - Fixed PHP warnings that occurred in the invoice refund parser.
- Added - PayPlus Hash Check button - checks the plugin integrity.
- Fix - Added check if session exists before using it in payplus_get_products_by_order_id() function.

## [7.4.3] - 01-01-2025 - (Jungle.P)

- Tweak - Improved the callback function to avoid repeated executions by adding a proper WooCommerce delay and removing redundant executions.
- Tweak - Removed the "-------- Or ---------" separator on express checkout in the product page.
- Tweak - Ensured that refunds for orders paid in 2024 will include a 17% VAT.
- Tweak - Removed expired admin notices.

## [7.4.2] - 30-12-2024 - (Wheeljack)

- Added - PayPlus Embedded now supports multiple coupons with or without taxes, including "Percentage discount" and mixed types.
- Tweak - Removed $order->payment_complete(); from PayPlus Embedded as it is handled elsewhere.
- Tweak - Adjusted icon positioning in "Design checkout" for right-to-left (RTL) languages.

## [7.4.1] = 29-12-2024 - (Igris)

- Tweak - Enhanced the callback function and improved the display of local time in the callback log.
- Added - Support for split shipping for multiple customer developers.
- Tweak - Refreshed selection of PayPlus Embedded when a coupon is added, ensuring the form is reselected and displayed correctly.
- Tweak - Adjusted logo placement when "Design checkout" is selected.
- Tweak - Updated logo sizes on the checkout page for better display.
- Tweak - Enhanced regeneration of the PayPlus Embedded link when the payment link expires.
- Added - Support for multiple coupons and types in PayPlus Embedded, including "Fixed cart discount" and "Fixed product discount" (both checkouts).
- Tweak - Added a "Page expired" message with a reload option in a popup.
- Tweak - Added nonce verification for admin notices.

## [7.3.8] = 25-12-2024 - (Nico-Robin)

- Fix - Refund invoices were not created if the charge invoice was not created beforehand. This has been corrected.
- Tweak - Adjusted the margin and padding of Express Checkout buttons.

## [7.3.6] = 24-12-2024 - (Sung Jin-woo)

- Fix - Only the main PayPlus gateway is now displayed when adding a payment method (to save a credit card token).
- Added - Option to hide the number of payments for the Bit payment method.
- Added - A special notice regarding the VAT change that will occur January 1st has been added for this version.
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
