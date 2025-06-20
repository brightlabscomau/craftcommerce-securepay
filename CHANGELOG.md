# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-27

### Added
- Initial release of SecurePay for Craft Commerce
- Credit card payment processing via SecurePay API v2
- Sandbox and live environment support
- JavaScript SDK integration for secure payment form
- OAuth 2.0 authentication with SecurePay
- Automatic token management and caching (24-hour cache)
- Comprehensive gateway configuration options
- Complete admin interface for configuration
- Error handling and logging
- Responsive payment forms with customizable styling
- Multi-language support
- Webhook processing support
- Order availability checking
- Payment form validation
- Transaction status tracking

### Configuration Features
- Merchant code, client ID, and client secret configuration
- Sandbox/live environment toggle
- JavaScript SDK styling customization:
  - Background color
  - Font family, size, and color for labels and inputs
  - Allowed card types (Visa, Mastercard, American Express, Diners Club)
  - Card icon display options
- Card payment enable/disable toggle

### Security
- Secure API communication using OAuth 2.0
- PCI DSS compliant payment processing via SecurePay JavaScript SDK
- Tokenized payment processing (no card data stored locally)
- Secure credential management with caching
- Input validation and sanitization

### Technical Implementation
- Extends Craft Commerce BaseGateway following official patterns
- Implements RequestResponseInterface for standardized responses
- Supports purchase operations (immediate capture)
- Payment form extends BasePaymentForm with token validation
- Automatic JavaScript SDK loading and configuration
- IP address detection for fraud prevention
- Amount conversion to cents for API compatibility

### Limitations (Planned for Future Releases)
- Authorization and capture workflows (not yet implemented)
- Full and partial refund support (not yet implemented)
- 3D Secure 2.0 authentication (basic support only)
- Apple Pay support (not yet implemented)
- Dynamic Currency Conversion (not yet implemented)
- Payment sources/stored payment methods (not yet implemented)
- Fraud detection integration (not yet implemented) 