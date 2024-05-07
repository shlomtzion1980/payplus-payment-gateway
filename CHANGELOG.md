# Changelog

All notable changes to this project will be documented in this file.

## [6.6.3] - 2024-04-25
### Added
- Check/Get order - ipn data from payplus in Admin orders via button click.
- Create invoice in a non-automatic management interface.
- "Website code" - Added to Invoice+: Add a unique string for each website if you have more than one website connected to our gateway.

### Changed
- Refactored codebase to enhance performance.
- Updated third-party libraries for security and stability.

### Fixed
- Save credit cards tokens - during checkout. (Supported currently only on woocommerce classic checkout).
- Payments with saved tokens work on all kinds of checkout pages (Redirect, Iframe, Iframe on the same page, Iframe in a pop-up). (Supported currently only on woocommerce classic checkout).
- Save credit card tokens - no duplicates.
- Removed log warnings for non-existing keys.
- Save credit card token with brand name. (works only with newly saved card tokens from now on)
- Correct display information on invoice for card on invoice creation with token payment.
- Display correct currency on automatic invoice creation.
- Add/Save Payment methods through "My account -> Payment methods" now saves the token with customer default billing and will work on checkout (When billing information is the same).
- Add/Save Payment methods in receipt page after successfull transaction.

### In the future
- Refactor for meta data to use High Performance Order Storage - HPOS with support for stores without - will be supported for traditional post meta records - for existing orders and stores that have no current support for HPOS.

## [6.6.2] - 2024-02-01
### Added
- Added support for the new WooCommerce Checkout Blocks.


