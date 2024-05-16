# Changelog

All notable changes to this project will be documented in this file.

## [6.6.3] - 2024-04-25

### Added

- Check/Get order - ipn data from payplus in Admin orders via button click.
- Create invoice in a non-automatic management interface.
- "Website code" - Added to Invoice+: Add a unique string for each website if you have more than one website connected to our gateway.
- "Save credit card checkbox in new WooCommerce Checkout Blocks.
- Refactor for meta data to use High Performance Order Storage - HPOS with support for stores without - will be supported for traditional post meta records - for existing orders and stores that have no current support for HPOS.
- Added Legacy post meta support checkbox - Default is checked - In future releases this will be unchecked. (Plugin users that have been using our gateway up until now will be able to view all data that was stored in the post meta fields) - for more information regarding HPOS go to: https://woocommerce.com/document/high-performance-order-storage/
- Added invoice check and update to admin on creation - If an invoice has already been created and for some reason it has not been updated to the admin orders panel, it's link will appear and it's data will be shown without duplicate creation.
- Notice for customer when they update their billing address. (Regarding saved tokens)

### Changed

- Refactored codebase to enhance performance - still in progress - massive changes will come!

### Fixed

- Save credit cards tokens - during checkout. (Supported on both classic and new checkout blocks).
- Payments with saved tokens now work on all checkout pages (Redirect, Iframe, Iframe on the same page, Iframe in a pop-up).
- Save credit card tokens - no duplicates.
- Removed log warnings for non-existing keys.
- Save credit card token with brand name. (works only with newly saved card tokens from now on)
- Correct display information on invoice for card on invoice creation with token payment.
- Display correct currency on automatic invoice creation.
- Add/Save Payment methods through "My account -> Payment methods" now saves the token with customer default billing and will work on checkout (When billing information is the same).
- J5 Invoice creation from admin with partial amount paid - fixed.

### In the future

- Code refactor.

## [6.6.2] - 2024-02-01

### Added

- Added support for the new WooCommerce Checkout Blocks.
