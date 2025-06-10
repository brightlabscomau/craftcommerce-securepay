# SecurePay Plugin Installation Guide

## Overview

This guide will help you install and configure the SecurePay payment gateway plugin for Craft Commerce.

**Plugin Package**: `craftcms/craft-securepay`  
**Version**: 1.0.0  
**Developer**: [Brightlabs](https://brightlabs.com.au/)

## Installation Steps

### 1. Install the Plugin

```bash
composer require craftcms/craft-securepay
php craft plugin/install securepay
```

### 2. Create a Payment Gateway

1. Go to **Commerce → System Settings → Gateways**
2. Click **New Gateway**
3. Select **SecurePay** as the gateway type
4. Configure the gateway settings:
   - **Name**: Give your gateway a descriptive name
   - **Handle**: Auto-generated handle for the gateway
   - **Client ID**: Your SecurePay client ID
   - **Client Secret**: Your SecurePay client secret
   - **Merchant Code**: Your SecurePay merchant code
   - **Sandbox Mode**: Enable for testing, disable for live transactions

### 3. Gateway Configuration

Configure the following **required** settings:
- **Client ID**: Your SecurePay API client identifier
- **Client Secret**: Your SecurePay API secret key  
- **Merchant Code**: Your SecurePay merchant code
- **Sandbox Mode**: Enable for testing, disable for live transactions

### 4. Advanced Configuration (Optional)

**JavaScript SDK Features:**
- **Always Enabled**: JavaScript SDK is always active for enhanced security and PCI compliance
- **Allowed Card Types**: Select supported card brands
- **Custom Styling**: Configure colors, fonts, and appearance

**Security Features:**
- **3D Secure 2.0**: Enable enhanced authentication
- **Fraud Detection**: Enable fraud screening (FraudGuard or ACI ReD Shield)

**Payment Methods:**
- **Card Payments**: Standard credit/debit cards (enabled by default)
- **Apple Pay**: Native Apple Pay support (requires domain verification)
- **PayPal**: PayPal integration through SecurePay
- **Direct Entry**: Bank transfers and direct debits
- **Dynamic Currency Conversion**: Multi-currency support

### 5. Testing

1. Enable **Sandbox Mode** in your gateway settings
2. Use SecurePay test card numbers:
   - Visa: `4111111111111111`
   - Mastercard: `5555555555554444`
   - Expiry: Any future date
   - CVV: Any 3-4 digit number

### 6. Going Live

1. Apply for merchant approval with SecurePay
2. Obtain your live credentials
3. Update your gateway settings with live credentials
4. Disable **Sandbox Mode**
5. Test with small transactions before full deployment

## SecurePay Account Setup

1. Visit [SecurePay](https://auspost.com.au/payments/)
2. Sign up for an account
3. Complete the verification process
4. Obtain your API credentials:
   - Client ID
   - Client Secret
   - Merchant Code

## Support

- **SecurePay Support**: support@securepay.com.au
- **API Documentation**: [SecurePay API Docs](https://auspost.com.au/payments/docs/securepay/)
- **Plugin Issues**: Report on GitHub repository

## Features Status

✅ **Implemented in v1.0.0**
- Credit/Debit card payments (Visa, Mastercard, Amex, Diners)
- JavaScript SDK integration with custom styling
- Direct API integration
- Sandbox and live environment support
- 3D Secure 2.0 authentication
- Fraud detection (FraudGuard and ACI ReD Shield)
- Apple Pay support (with domain verification)
- Dynamic Currency Conversion
- Authorization and capture workflows
- Full and partial refunds
- Webhook support for real-time updates
- OAuth 2.0 authentication with automatic token management
- Comprehensive gateway configuration
- Responsive payment forms

🚧 **Planned for Future Versions**
- Stored payment methods (PaymentSource support)  
- Recurring payments
- Enhanced webhook event handling
- Multi-merchant support
- Advanced fraud rules configuration

## Security Notes

- All API communications use OAuth 2.0
- Payment data is processed securely through SecurePay
- 3D Secure provides additional authentication
- Fraud detection helps prevent fraudulent transactions
- PCI DSS compliance is maintained through SecurePay's infrastructure 