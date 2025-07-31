# SecurePay for Craft Commerce

This plugin provides a SecurePay payment gateway integration for Craft Commerce, implementing the [official SecurePay API v2](https://securepay-docs.readme.io/docs/getting-started-with-securepay-api) using the recommended JavaScript SDK for enhanced security.

**Built by**: [Brightlabs](https://brightlabs.com.au/) | **Version**: 1.3.0 | **Package**: `brightlabs/craft-securepay`

## 🚀 Features

### Core Payment Processing
- **Credit/Debit Cards**: Accept Visa, Mastercard, Amex, and Diners Club cards through a secure, tokenised process.
- **JavaScript SDK Integration**: Enhanced security with client-side tokenization. The plugin handles all SDK configuration and rendering automatically.
- **Purchase Transactions**: Supports immediate capture of funds (purchase).
- **Authorisation and Capture Workflows**: Supports authorise now, capture later workflows for delayed payment processing.
- **Full and Partial Refund**: Supports full and partial refunds only for AUD transactions.
- **Sandbox Testing**: Complete support for SecurePay's sandbox environment with pre-configured test credentials.

### Security & Authentication
- **PCI DSS SAQ-A Compliant**: Designed for the highest level of PCI compliance by ensuring no sensitive card data ever touches your server.
- **OAuth 2.0**: Secure API authentication with automatic token management and 24-hour caching for improved performance.
- **3D Secure 2.0**: Full implementation of 3D Secure 2.0 authentication for enhanced security and fraud prevention.

### Admin & Configuration Features
- **Full Admin Configuration**: All settings are managed within the Craft Commerce gateway settings.
- **Extensive Styling Options**: Customise the look and feel of the payment form directly from the gateway settings, including colours, fonts, and more.
- **Card Type Configuration**: Easily select which card types are allowed for payment.
- **3D Secure Toggle**: Enable or disable 3D Secure 2.0 authentication per gateway.

## 📋 Requirements

- Craft CMS 5.0+
- Craft Commerce 5.0+
- PHP 8.2+
- A SecurePay merchant account
- A valid SSL certificate (HTTPS) is required on your checkout pages.

## 🔧 Installation

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

### 2. Install the Plugin
From your project's root directory, run the following commands:
```bash
composer require brightlabs/craft-securepay
php craft plugin/install securepay
```

### 3. Create a Payment Gateway

1.  In the Craft control panel, go to **Commerce → System Settings → Gateways**.
2.  Click the "**New Gateway**" button.
3.  Give your gateway a name (e.g., "Credit Card (SecurePay)") and select "**SecurePay**" from the Gateway Type dropdown.
4.  Configure your API credentials and styling options.

*Note: This plugin does not have global settings - each gateway is configured individually.*

## ⚙️ Configuration

### API Credentials

| Setting | Description | Required |
|---|---|:---:|
| Merchant Code | Your SecurePay merchant code | ✅ |
| Client ID | SecurePay API client identifier | ✅ |
| Client Secret | SecurePay API secret key | ✅ |

### Environment Settings

| Setting | Description | Required |
|---|---|:---:|
| Sandbox Mode | Enable for testing, disable for live transactions | ✅ |

*Note: When Sandbox Mode is enabled, the plugin automatically uses pre-configured test credentials for easier setup.*

### JavaScript SDK Styling

*The JavaScript SDK is always used for enhanced security and PCI compliance.*

| Setting | Description | Type | Default |
|---|---|---|---|
| Allowed Card Types | Select which card types are allowed | Multi-select | Visa, Mastercard, Amex, Diners |
| Show Card Icons | Display card brand icons in the payment form | Toggle | ✅ Enabled |
| Background Colour | Background colour of the payment form fields | Colour | `#ffffff` |
| Label Font Family | Font family for form field labels | Text | `Arial, Helvetica, sans-serif` |
| Label Font Size | Font size for form field labels | Text | `1.1rem` |
| Label Font Colour | Colour of form field labels | Colour | `#000080` |
| Input Font Family | Font family for form input fields | Text | `Arial, Helvetica, sans-serif` |
| Input Font Size | Font size for form input fields | Text | `1.1rem` |
| Input Font Colour | Colour of text in form input fields | Colour | `#000080` |

### Security Features

| Setting | Description | Default |
|---|---|:---:|
| 3D Secure 2.0 | Enable 3D Secure 2.0 authentication for enhanced security | ❌ Disabled |

### Payment Features

| Setting | Description | Default |
|---|---|:---:|
| Card Payments | Enable credit and debit card payments | ✅ Enabled |

## 🔐 Security Implementation

### PCI Compliance
This plugin is designed for **PCI SAQ-A** compliance:
- Card data is processed on the client-side only via the SecurePay-hosted iframe.
- No sensitive card data is ever transmitted to or stored on your servers.
- Secure tokenization is handled entirely by the JavaScript SDK.

### 3D Secure 2.0
The plugin includes full 3D Secure 2.0 implementation:
- Enhanced authentication flow for high-risk transactions
- Automatic challenge flow when required by the card issuer
- Seamless integration with the payment process
- Configurable per gateway instance

### Authentication Flow
```
Client Browser → SecurePay JS SDK (Tokenization)
       ↓ (Token)
Your Server → Craft Commerce → SecurePay API (Payment Processing)
       ↓ (3D Secure if required)
3D Secure Challenge → Card Issuer → Customer Authentication
```

## 🧪 Testing

### Test Environment
To use the test environment, simply enable **Sandbox Mode** in the gateway settings. The plugin will automatically use the correct sandbox URLs and pre-configured test credentials.

### Test Card Numbers

| Card Type | Number |
|---|---|
| Visa | `4111111111111111` |
| Mastercard | `5555555555554444` |
| Amex | `378282246310005` |
| Declined | `4000000000000002` |
| Insufficient Funds | `4000000000009995` |

### Test Details
- **Expiry**: Any future date (e.g., `12/2025`)
- **CVV**: Any 3-4 digit number (e.g., `123`)
- **Name**: Any cardholder name

### 3D Secure Testing
When 3D Secure 2.0 is enabled, test cards may trigger authentication challenges:

- Follow the authentication flow as prompted
- Card Holder Name: Test Card
- Expiry date (YYMM): value must be 2508
- Card: VISA:4100000000000100, MasterCard:5100000000000107
- Test both successful and failed authentication scenarios

### Browser Support
- Safari on macOS and iOS
- Chrome on supported devices
- Edge on Windows with Windows Hello

### Supported Currencies
- `AUD` (Currently hardcoded)

## 📊 Monitoring & Logging
The plugin provides logging for key events and errors, which can be found in `storage/logs/web.log`. Look for entries prefixed with `[securepay]`.

## 🔄 API Integration Details

### Supported Operations

| Operation | Method | Endpoint | Status |
|---|---|---|:---:|
| Create Payment | `POST` | `/v2/payments` | ✅ Implemented |
| Refund Payment | `POST` | `/v2/payments/{id}/refund` | ✅ Implemented |
| Authorise Payment | `POST` | `/v2/payments/preauth` | ✅ Implemented |
| Capture Payment | `POST` | `/v2/payments/{id}/capture` | ✅ Implemented |
| 3D Secure Authentication | `POST` | `/v2/payments` | ✅ Implemented |

## 📚 Documentation

- [Installation Guide](INSTALLATION.md)
- [Integration Guide](INTEGRATION_GUIDE.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [Changelog](CHANGELOG.md)

## 🆘 Support

For issues with the plugin itself, please open an issue on the [Bitbucket repository](https://bitbucket.org/bright-labs/craftcommerce-securepay/issues). For problems related to your SecurePay account, please contact SecurePay support directly.

## ⚖️ License

This plugin is licensed under the MIT License.

## 📈 Roadmap

### Future Releases
- [ ] Enhanced 3D Secure 2.0 JavaScript Integration with unlimited validation requests
- [ ] Adding optional merchantRiskData to 3D Secure 2.0 JavaScript Integration
- [ ] Improved JavaScript SDK implementation and error handling
- [ ] Fraud Detection Features (FraudGuard)
- [ ] Apple Pay Support
- [ ] PayPal Payments
- [ ] Direct Entry Payments
- [ ] Scheduled Payments
- [ ] Dynamic Currency Conversion (DCC)
- [ ] Stored Payment Methods (Payment Sources)

---

**Note**: This plugin follows the official SecurePay documentation and integrates with the recommended `fgct/securepay-api` PHP library for optimal compatibility and security. 