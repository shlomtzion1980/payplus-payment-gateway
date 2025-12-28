# PayPlus Express Checkout V2 - Implementation Guide

## Overview

A modern, completely rewritten express checkout system for PayPlus Payment Gateway that supports Apple Pay and Google Pay with full compatibility for both WooCommerce Classic Checkout and Block-based Checkout.

## What Was Created

### 1. Core Files

#### PHP Class (`/includes/class-wc-payplus-express-checkout-v2.php`)
- Modern singleton pattern implementation
- Full Apple Pay and Google Pay support
- WooCommerce integration (Classic & Blocks)
- AJAX handlers for:
  - Order creation
  - Shipping calculation
  - Payment processing
- Apple Pay domain verification
- PayPlus API integration

#### JavaScript (`/assets/js/express-checkout-v2.js`)
- Apple Pay Session API (v10) implementation
- Google Pay API (v2) implementation
- Real-time shipping updates
- Payment authorization
- Error handling
- Loading states

#### CSS (`/assets/css/express-checkout-v2.css`)
- Responsive design
- Mobile-optimized
- RTL support
- Dark mode support
- Accessibility features
- Theme compatibility

#### Blocks Integration (`/includes/blocks/class-wc-payplus-express-checkout-block.php`)
- WooCommerce Blocks support
- Script registration
- Data passing to frontend

## Features

### ✅ Payment Methods
- **Apple Pay** - Full Apple Pay Session API integration
- **Google Pay** - Google Pay API v2 with gateway tokenization

### ✅ Display Locations
- Product pages
- Cart page
- Checkout page (Classic)
- Checkout page (Blocks)

### ✅ Technical Features
- 3D Secure support
- Merchant validation
- Shipping address selection
- Shipping method selection
- Real-time shipping calculation
- Tax calculation
- Multi-currency support

### ✅ User Experience
- Native payment buttons
- Loading indicators
- Error messages
- Smooth animations
- Mobile-responsive
- Accessibility compliant

## Admin Settings

The following settings are now available in WooCommerce → Settings → Payments → Express Checkout:

### Express Checkout V2 Settings

1. **Enable Apple Pay (V2)**
   - Checkbox to enable/disable Apple Pay
   
2. **Apple Merchant Identifier**
   - Your Apple Pay merchant identifier
   - Format: `merchant.com.yourstore`
   
3. **Enable Google Pay (V2)**
   - Checkbox to enable/disable Google Pay
   
4. **Google Merchant ID**
   - Your Google Pay merchant ID (optional)
   - Leave empty for test environment
   
5. **Display Locations**
   - Multi-select: Product Pages, Cart Page, Checkout Page
   
6. **Button Type**
   - Options: Buy, Plain, Check Out, Book, Donate, Subscribe
   
7. **Button Color**
   - Options: Black, White, White with Outline
   
8. **Button Height**
   - Configurable height in pixels (40-64px)

## Setup Instructions

### Step 1: Verify Installation ✅ (DONE)
The files have been created and integrated into your plugin:
- ✅ PHP class file created
- ✅ JavaScript file created
- ✅ CSS file created
- ✅ Blocks integration file created
- ✅ Files included in main plugin
- ✅ Admin settings added

### Step 2: Configure Apple Pay

1. **Get Apple Merchant ID**
   - Go to Apple Developer Portal
   - Create a Merchant ID
   - Example: `merchant.com.yourstore`

2. **Add Domain to Apple Pay**
   - Add your domain to Apple Pay
   - Download the verification file (already included in plugin)

3. **Configure in WordPress**
   - Go to WooCommerce → Settings → Payments → Express Checkout
   - Enable "Apple Pay (V2)"
   - Enter your Apple Merchant Identifier

### Step 3: Configure Google Pay

1. **Get Google Merchant ID** (Optional)
   - Go to Google Pay Business Console
   - Register your business
   - Get your Merchant ID

2. **Configure in WordPress**
   - Go to WooCommerce → Settings → Payments → Express Checkout
   - Enable "Google Pay (V2)"
   - Enter your Google Merchant ID (or leave empty for test)

### Step 4: Configure Display Options

1. **Select Display Locations**
   - Choose where buttons appear:
     - ✓ Product Pages
     - ✓ Cart Page
     - ✓ Checkout Page

2. **Customize Button Appearance**
   - Button Type: Buy, Plain, Check Out, etc.
   - Button Color: Black, White, White with Outline
   - Button Height: 40-64px

### Step 5: Test

1. **Test Mode**
   - Enable test mode in PayPlus settings
   - Test with Apple Pay (requires real device)
   - Test with Google Pay (works in desktop Chrome)

2. **Production**
   - Disable test mode
   - Test real transactions
   - Monitor order creation

## How It Works

### User Flow

1. **User clicks Express Checkout button**
   - Apple Pay or Google Pay button appears
   - User selects payment method

2. **Payment sheet opens**
   - Shows order total
   - Displays line items
   - Requests shipping address (if needed)

3. **Shipping calculation**
   - Real-time shipping method calculation
   - Updates total based on address
   - Shows available shipping options

4. **Payment authorization**
   - User authorizes payment
   - Token sent to PayPlus
   - Order created in WooCommerce

5. **Order completion**
   - Payment processed
   - Order status updated
   - User redirected to thank you page

## Technical Details

### AJAX Endpoints

1. **payplus_express_create_order**
   - Creates order data from cart or product
   - Returns totals, items, shipping requirements

2. **payplus_express_update_shipping**
   - Calculates shipping based on address
   - Returns shipping options with costs

3. **payplus_express_process_payment**
   - Creates WooCommerce order
   - Processes payment with PayPlus
   - Returns redirect URL

### PayPlus API Integration

The system integrates with PayPlus API:
- Endpoint: `/v1/payments/charge`
- Method: POST
- Authentication: API Key + Secret Key
- Supports test and production modes

### Order Metadata

Orders created through Express Checkout V2 include:
- `_payplus_express_checkout` = 'yes'
- `_payplus_express_type` = 'apple_pay' or 'google_pay'
- Transaction ID from PayPlus

## Differences from Old Express Checkout

### New V2 Benefits

1. **Modern Code**
   - Clean, object-oriented PHP
   - ES6 JavaScript with async/await
   - No legacy code carried over

2. **Better API Integration**
   - Latest Apple Pay Session API (v10)
   - Latest Google Pay API (v2)
   - Proper error handling

3. **Improved UX**
   - Faster loading
   - Better error messages
   - Smooth animations
   - Mobile-optimized

4. **Full Blocks Support**
   - Native WooCommerce Blocks integration
   - Works with new checkout experience

5. **Better Compatibility**
   - Works with complex shipping rules
   - Proper tax calculation
   - Multi-currency support
   - Theme-independent

## Troubleshooting

### Apple Pay Not Showing

1. **Check Requirements**
   - Must be on HTTPS
   - Must use Safari or iOS device
   - Must have valid merchant ID

2. **Domain Verification**
   - Ensure domain is verified with Apple
   - Check verification file is accessible

3. **Settings**
   - Verify "Enable Apple Pay (V2)" is checked
   - Verify merchant ID is correct

### Google Pay Not Showing

1. **Check Requirements**
   - Must be on HTTPS
   - Chrome browser recommended

2. **Settings**
   - Verify "Enable Google Pay (V2)" is checked

### Buttons Not Appearing

1. **Check Display Locations**
   - Verify locations are selected in settings
   - Check you're on the correct page type

2. **JavaScript Errors**
   - Open browser console
   - Check for errors
   - Verify scripts are loading

### Payment Failures

1. **Check API Credentials**
   - Verify API Key is correct
   - Verify Secret Key is correct
   - Check test/production mode matches

2. **Check Logs**
   - WooCommerce → Status → Logs
   - Look for PayPlus logs

## Next Steps

### For Testing

1. Enable test mode in PayPlus settings
2. Configure Apple Pay and/or Google Pay
3. Test on product page
4. Test on cart page
5. Test on checkout page
6. Verify order creation
7. Check payment processing

### For Production

1. Get production API credentials
2. Get Apple Merchant ID (production)
3. Get Google Merchant ID (optional)
4. Disable test mode
5. Test thoroughly
6. Monitor first transactions
7. Gather user feedback

## Support

For issues or questions:
1. Check WooCommerce logs
2. Check browser console
3. Verify all settings are correct
4. Contact PayPlus support if API issues

## File Locations Reference

```
wp-content/plugins/payplus-payment-gateway/
├── includes/
│   ├── class-wc-payplus-express-checkout-v2.php
│   └── blocks/
│       └── class-wc-payplus-express-checkout-block.php
├── assets/
│   ├── js/
│   │   ├── express-checkout-v2.js
│   │   └── express-checkout-v2.min.js
│   └── css/
│       ├── express-checkout-v2.css
│       └── express-checkout-v2.min.css
└── payplus-payment-gateway.php (updated to include V2)
```

## Conclusion

Express Checkout V2 is now fully integrated into your PayPlus Payment Gateway plugin with modern code, better UX, and full support for both Apple Pay and Google Pay across Classic and Block-based checkouts.

