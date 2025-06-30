# SecurePay Plugin Installation Guide

## Overview

This guide will help you install and configure the SecurePay payment gateway plugin for Craft Commerce.

**Plugin Package**: `brightlabs/craft-securepay`  
**Version**: 1.3.1
**Developer**: [Brightlabs](https://brightlabs.com.au/)

## Installation Steps

### 1. Configure Composer Repository
Before installing the plugin, you need to add the Bitbucket repository to your `composer.json` file. Add the following to your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://bitbucket.org/bright-labs/craftcommerce-securepay"
    }
  ]
}
```

### 2. Install the Plugin via Composer
From your project's root directory, run the following command:
```bash
composer require brightlabs/craft-securepay
```

### 3. Install the Plugin in Craft
In the Craft control panel, go to **Settings → Plugins**, find "SecurePay for Craft Commerce" and click **Install**.

Alternatively, you can run the following command from your terminal:
```bash
php craft plugin/install securepay
```

### 4. Create a Payment Gateway

1.  Go to **Commerce → System Settings → Gateways**.
2.  Click the "**New Gateway**" button.
3.  Give your gateway a descriptive name (e.g., "Credit Card").
4.  Select **SecurePay** as the gateway type.
5.  Configure the gateway settings.

### 5. Gateway Configuration

#### Required Settings
The following settings are **required** for the gateway to function:
-   **Merchant Code**: Your SecurePay merchant code.
-   **Client ID**: Your SecurePay API client identifier.
-   **Client Secret**: Your SecurePay API secret key.
-   **Sandbox Mode**: Enable for testing, disable for live transactions.

#### Sandbox Mode
When **Sandbox Mode** is enabled, the plugin automatically uses pre-configured test credentials:
- **Merchant Code**: `5AR0055`
- **Client ID**: `0oaxb9i8P9vQdXTsn3l5`
- **Client Secret**: `0aBsGU3x1bc-UIF_vDBA2JzjpCPHjoCP7oI6jisp`

This makes testing much easier as you don't need to manually enter test credentials.

#### Security Features
- **3D Secure 2.0**: Enable this option to activate 3D Secure 2.0 authentication for enhanced security. This requires 3D Secure to be enabled on your SecurePay account.

### 6. Advanced Configuration (Optional)

The plugin offers extensive styling options for the payment form, allowing you to match it to your site's design. These settings are available under the "JavaScript SDK Styling" section in the gateway configuration.

### 7. Testing

1.  Enable **Sandbox Mode** in your gateway settings.
2.  Use SecurePay's official test card numbers to perform test transactions.
    -   **Visa**: `4111111111111111`
    -   **Mastercard**: `5555555555554444`
    -   **Expiry**: Any future date (e.g., `12/2025`)
    -   **CVV**: Any 3-4 digit number (e.g., `123`)

#### 3D Secure Testing
If you have enabled 3D Secure 2.0:
- Test cards may trigger authentication challenges
- Follow the authentication flow as prompted
- Use any test authentication code (e.g., `123456`)
- Test both successful and failed authentication scenarios

### 8. Going Live

1.  Obtain your live API credentials from your SecurePay merchant account.
2.  Update your gateway settings in Craft Commerce with the live credentials.
3.  Disable **Sandbox Mode**.
4.  Ensure your checkout page is served over HTTPS.
5.  Perform a small live transaction to confirm everything is working correctly.

## Support

-   **Plugin Issues**: For bugs or feature requests related to this plugin, please open an issue on the [Bitbucket repository](https://bitbucket.org/bright-labs/craftcommerce-securepay/issues).
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

✅ **Implemented in v1.3.0**
- 3D Secure 2.0 authentication for enhanced security
- Pre-configured sandbox credentials for easier testing
- Enhanced error reporting and debugging
- Optimised credential management

🚧 **Planned for Future Versions**
- Fraud detection integration
- Apple Pay support
- Dynamic Currency Conversion (DCC)
- Stored payment methods

## Security Notes

- All API communications use HTTPS and are authenticated with OAuth 2.0.
- This plugin uses SecurePay's JavaScript SDK to tokenise payment information in the browser, ensuring no sensitive card data ever touches your server. This is essential for PCI DSS SAQ-A compliance.
- 3D Secure 2.0 provides additional authentication layers for high-risk transactions, enhancing security and reducing fraud. 