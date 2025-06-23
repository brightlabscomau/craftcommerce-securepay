# SecurePay Plugin Installation Guide

## Overview

This guide will help you install and configure the SecurePay payment gateway plugin for Craft Commerce.

**Plugin Package**: `brightlabs/craft-securepay`  
**Version**: 1.2.0  
**Developer**: [Brightlabs](https://brightlabs.com.au/)

## Installation Steps

### 1. Install the Plugin via Composer
From your project's root directory, run the following command:
```bash
composer require brightlabs/craft-securepay
```

### 2. Install the Plugin in Craft
In the Craft control panel, go to **Settings → Plugins**, find "SecurePay for Craft Commerce" and click **Install**.

Alternatively, you can run the following command from your terminal:
```bash
php craft plugin/install securepay
```

### 3. Create a Payment Gateway

1.  Go to **Commerce → System Settings → Gateways**.
2.  Click the "**New Gateway**" button.
3.  Give your gateway a descriptive name (e.g., "Credit Card").
4.  Select **SecurePay** as the gateway type.
5.  Configure the gateway settings.

### 4. Gateway Configuration

The following settings are **required** for the gateway to function:
-   **Merchant Code**: Your SecurePay merchant code.
-   **Client ID**: Your SecurePay API client identifier.
-   **Client Secret**: Your SecurePay API secret key.
-   **Sandbox Mode**: Enable for testing, disable for live transactions.

### 5. Advanced Configuration (Optional)

The plugin offers extensive styling options for the payment form, allowing you to match it to your site's design. These settings are available under the "JavaScript SDK Styling" section in the gateway configuration.

### 6. Testing

1.  Enable **Sandbox Mode** in your gateway settings.
2.  Use SecurePay's official test card numbers to perform test transactions.
    -   **Visa**: `4111111111111111`
    -   **Mastercard**: `5555555555554444`
    -   **Expiry**: Any future date (e.g., `12/2025`)
    -   **CVV**: Any 3-4 digit number (e.g., `123`)

### 7. Going Live

1.  Obtain your live API credentials from your SecurePay merchant account.
2.  Update your gateway settings in Craft Commerce with the live credentials.
3.  Disable **Sandbox Mode**.
4.  Ensure your checkout page is served over HTTPS.
5.  Perform a small live transaction to confirm everything is working correctly.

## Support

-   **Plugin Issues**: For bugs or feature requests related to this plugin, please open an issue on the [GitHub repository](https://github.com/brightlabs/craft-securepay/issues).
-   **SecurePay Account Support**: For issues with your SecurePay account, API credentials, or the SecurePay service itself, please contact SecurePay support directly.

## Features Status

✅ **Implemented in v1.0.0**
- Credit/Debit card payments (Visa, Mastercard, Amex, Diners)
- Secure, token-based payments via JavaScript SDK integration
- Extensive payment form styling options
- Sandbox and live environment support
- OAuth 2.0 authentication with automatic token caching
- Full configuration within the Craft Commerce admin panel

✅ **Implemented in v1.1.0**
- Full and partial refunds are supported only for AUD transactions
- For DCC transactions, a refund is not available via the plugin.
- Only full refunds are supported and available via the SecurePay Merchant Portal for DCC transactions.

✅ **Implemented in v1.2.0**
- Authorisation and capture workflows for SecurePay gateway
- Refactored response handling to use unified SecurePayResponse class
- Removed individual response classes and consolidated all API responses into single response
- Technical Improvements

🚧 **Planned for Future Versions**
- 3D Secure 2.0 authentication
- Fraud detection integration
- Apple Pay support
- Dynamic Currency Conversion (DCC)
- Stored payment methods

## Security Notes

- All API communications use HTTPS and are authenticated with OAuth 2.0.
- This plugin uses SecurePay's JavaScript SDK to tokenize payment information in the browser, ensuring no sensitive card data ever touches your server. This is essential for PCI DSS SAQ-A compliance. 