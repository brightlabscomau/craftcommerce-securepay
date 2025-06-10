# SecurePay for Craft Commerce

This plugin provides a comprehensive SecurePay payment gateway integration for Craft Commerce, implementing the [official SecurePay API v2](https://auspost.com.au/payments/docs/securepay/) with enhanced security features and following industry best practices.

**Built by**: [Brightlabs](https://brightlabs.com.au/) | **Version**: 1.0.0 | **Package**: `craftcms/craft-securepay`

## 🚀 Features

### Core Payment Processing
- **Credit/Debit Cards**: Accept Visa, Mastercard, Amex, and Diners Club cards
- **JavaScript SDK Integration**: Enhanced security with client-side tokenization (recommended)
- **Direct API Integration**: Server-to-server payment processing
- **Sandbox Testing**: Complete testing environment support

### Security & Authentication
- **3D Secure 2.0**: Enhanced authentication following EMV 3DS specification
- **Fraud Detection**: Support for FraudGuard (default) and ACI ReD Shield
- **PCI Compliance**: Secure payment processing without handling card data
- **OAuth 2.0**: Secure API authentication with automatic token management

### Advanced Features
- **Apple Pay**: Native Apple Pay integration with domain verification
- **Dynamic Currency Conversion**: Multi-currency support with real-time rates
- **Authorization/Capture**: Flexible payment workflows
- **Partial Refunds**: Complete refund management
- **Webhook Support**: Real-time transaction status updates

### Integration Methods
- **Frontend**: JavaScript SDK with UI components and custom styling
- **Backend**: REST API with comprehensive error handling
- **Mobile**: Apple Pay and responsive design
- **Admin**: Complete configuration interface

## 📋 Requirements

- Craft CMS 5.0+
- Craft Commerce 5.0+
- PHP 8.2+
- A SecurePay merchant account
- HTTPS (required for Apple Pay and enhanced security)

## 🔧 Installation

### 1. Install the Plugin

```bash
composer require craftcms/craft-securepay
php craft plugin/install securepay
```

### 2. Create Payment Gateway

1. Go to **Commerce → System Settings → Gateways**
2. Create a new gateway and select "SecurePay"
3. Configure your API credentials and features

*Note: This plugin does not have global settings - each gateway is configured individually.*

## ⚙️ Configuration

### API Credentials

| Setting | Description | Required |
|---------|-------------|----------|
| Client ID | SecurePay API client identifier | ✅ |
| Client Secret | SecurePay API secret key | ✅ |
| Merchant Code | Your SecurePay merchant code | ✅ |

### Environment Settings

| Setting | Description | Required |
|---------|-------------|----------|
| Sandbox Mode | Enable for testing | ✅ |

### JavaScript SDK Styling

*The JavaScript SDK is always enabled for enhanced security and PCI compliance.*

| Setting | Description | Type | Default |
|---------|-------------|------|---------|
| Allowed Card Types | Select which card types are allowed | Multi-select | Visa, Mastercard, Amex, Diners |
| Show Card Icons | Display card brand icons in the payment form | Toggle | ✅ Enabled |
| Background Color | Background color of the payment form fields | Color | #ffffff |
| Label Font Family | Font family for form field labels | Text | Arial, Helvetica, sans-serif |
| Label Font Size | Font size for form field labels | Text | 1.1rem |
| Label Font Color | Color of form field labels | Color | #000080 |
| Input Font Family | Font family for form input fields | Text | Arial, Helvetica, sans-serif |
| Input Font Size | Font size for form input fields | Text | 1.1rem |
| Input Font Color | Color of text in form input fields | Color | #000080 |

### Security Features

| Setting | Description | Default | Dependencies |
|---------|-------------|---------|--------------|
| 3D Secure 2.0 | Enhanced authentication | ❌ Disabled | Account setup required |
| Fraud Detection | Enable fraud detection for transactions | ❌ Disabled | Account feature required |
| Fraud Detection Provider | Choose between FraudGuard (default) or ACI ReD Shield | FraudGuard | Fraud Detection enabled |

### Payment Features

| Setting | Description | Default | Setup Required |
|---------|-------------|---------|----------------|
| Card Payments | Enable credit and debit card payments | ✅ Enabled | Standard |
| PayPal Payments | Enable PayPal as a payment option | ❌ Disabled | Account configuration |
| Apple Pay | Native Apple Pay payments | ❌ Disabled | Domain verification |
| Direct Entry Payments | Enable bank transfers and direct debits | ❌ Disabled | Account feature |
| Dynamic Currency Conversion | Multi-currency support | ❌ Disabled | Account feature |

## 🔐 Security Implementation

### PCI Compliance
This plugin is designed for **PCI SAQ-A** compliance:
- Card data processed client-side only
- No sensitive data stored on your servers
- Secure tokenization via JavaScript SDK
- HTTPS enforcement for all payment pages

### Authentication Flow
```
Client → JavaScript SDK → SecurePay (Tokenization)
   ↓
Server → OAuth 2.0 → SecurePay API (Processing)
```

## 🧪 Testing

### Test Environment
- **Sandbox URL**: `https://payments-stest.npe.auspost.zone`
- **OAuth URL**: `https://welcome.api2.sandbox.auspost.com.au/oauth/token`

### Test Card Numbers

| Card Type | Number | Features |
|-----------|--------|----------|
| Visa | `4111111111111111` | Standard approval |
| Mastercard | `5555555555554444` | Standard approval |
| Visa 3DS | `4000000000001091` | 3D Secure challenge |
| Mastercard 3DS | `5200000000001096` | 3D Secure frictionless |
| Declined | `4000000000000002` | Declined transaction |
| Insufficient Funds | `4000000000009995` | Insufficient funds |

### Test Details
- **Expiry**: Any future date (e.g., `12/2025`)
- **CVV**: Any 3-4 digit number (e.g., `123`)
- **Name**: Any cardholder name

## 🍎 Apple Pay Setup

### Prerequisites
1. Apple Developer Account
2. Merchant ID registration with Apple
3. Domain verification
4. Processing certificate from SecurePay

### Domain Verification Steps
1. Download verification file from SecurePay merchant portal
2. Upload to `/.well-known/apple-developer-merchantid-domain-association`
3. Ensure file is accessible via HTTPS
4. Verify in SecurePay portal

### Browser Support
- Safari on macOS and iOS
- Chrome on supported devices
- Edge on Windows with Windows Hello

## 🛡️ Fraud Detection

### FraudGuard (Default)
- Real-time transaction screening
- Risk scoring and decision making
- Configurable rules and thresholds

### ACI ReD Shield
- Advanced machine learning detection
- Device fingerprinting
- Behavioral analytics
- Requires additional configuration

## 🌍 Dynamic Currency Conversion

### Features
- Real-time exchange rates
- Customer currency selection
- Transparent fee disclosure
- Multi-currency receipts

### Supported Currencies
All major currencies supported by SecurePay's DCC service.

## 📊 Monitoring & Analytics

### Transaction Monitoring
- Real-time payment status
- Success/failure rate tracking
- 3D Secure authentication rates
- Fraud detection outcomes

### Logging
The plugin provides comprehensive logging:
- Payment attempts and outcomes
- API communication (sanitized)
- Error conditions and resolutions
- Security events

## 🔄 API Integration Details

### Supported Operations

| Operation | Method | Endpoint |
|-----------|--------|----------|
| Create Payment | `POST` | `/v2/payments` |
| Capture Payment | `POST` | `/v2/payments/{id}/capture` |
| Refund Payment | `POST` | `/v2/payments/{id}/refund` |
| Get Payment | `GET` | `/v2/payments/{id}` |

### Response Handling
- Comprehensive error code mapping
- 3D Secure flow management
- Fraud detection result processing
- Webhook support (future release)

## 🚀 Production Deployment

### Checklist
- [ ] Obtain live SecurePay credentials
- [ ] Update gateway to live mode
- [ ] Configure production URLs
- [ ] Set up Apple Pay domain verification
- [ ] Configure fraud detection rules
- [ ] Test with small amounts
- [ ] Monitor initial transactions

### Production URLs
- **API**: `https://payments.auspost.net.au`
- **OAuth**: `https://welcome.api2.auspost.com.au/oauth/token`

## 📚 Documentation

### Plugin Documentation
- [Installation Guide](INSTALLATION.md)
- [Integration Guide](INTEGRATION_GUIDE.md)
- [Changelog](CHANGELOG.md)

### Official SecurePay Resources
- [API Documentation](https://auspost.com.au/payments/docs/securepay/)
- [JavaScript SDK Guide](https://auspost.com.au/payments/docs/securepay/?javascript#javascript-sdk)
- [3D Secure Implementation](https://auspost.com.au/payments/docs/securepay/?javascript#3d-secure-2)

## 🆘 Support

### Plugin Support
- GitHub Issues: [Report bugs and feature requests]
- Community: Craft CMS Discord/Slack

### SecurePay Support
- **Email**: support@securepay.com.au
- **Documentation**: [Official API Docs](https://auspost.com.au/payments/docs/securepay/)
- **System Status**: Check `/v2/health` endpoint

## ⚖️ License

This plugin is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## 🏗️ Built With

- [SecurePay API v2](https://auspost.com.au/payments/docs/securepay/)
- [fgct/securepay-api](https://github.com/fgct/securepay-api) - Official PHP SDK
- [Craft Commerce](https://craftcms.com/commerce)
- Modern JavaScript and CSS

## 📈 Roadmap

### Version 1.1 (Planned)
- [ ] Stored payment methods (PaymentSource support)
- [ ] Enhanced webhook event handling
- [ ] Recurring payments
- [ ] Enhanced reporting dashboard

### Version 1.2 (Future)  
- [ ] Multi-merchant support
- [ ] Advanced fraud rules configuration
- [ ] Additional payment methods via SecurePay
- [ ] Enhanced DCC features

---

**Note**: This plugin follows the official SecurePay documentation and integrates with the recommended PHP SDK for optimal compatibility and security. 