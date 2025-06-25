# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2025-06-25

### Added
- **3D Secure 2.0 Integration**: Full implementation of 3D Secure 2.0 authentication for enhanced security
- **Static Sandbox Credentials**: Pre-configured sandbox credentials for easier testing setup
- **Enhanced Error Reporting**: Improved API exception handling with more detailed error messages
- **Optimised Credential Management**: Single credential retrieval at gateway creation for better performance

### Changed
- **Gateway Constructor**: Now automatically sets sandbox credentials when in sandbox mode
- **Error Handling**: Enhanced error reporting for better debugging and user experience
- **Performance**: Optimised credential caching and retrieval process

### Technical Improvements
- Improved 3D Secure authentication flow implementation
- Enhanced security compliance with latest 3D Secure 2.0 standards
- Better integration with SecurePay's 3D Secure services

## [1.2.1] - 2025-06-23

### Changed
- Converted all American spellings to Australian spellings throughout the plugin (documentation, user-facing strings, and code comments/labels).

## [1.2.0] - 2025-06-23

### Added
- Authorisation and capture workflows for SecurePay gateway
- Complete support for pre-authorisation and capture payment flows

### Changed
- Refactored response handling to use unified SecurePayResponse class
- Removed individual response classes and consolidated all API responses into single response 
- Unified variable naming convention to camelCase throughout the codebase
- Standardised method and property naming for better code consistency

### Technical Improvements
- Improved code readability and maintainability through consistent naming patterns
- Enhanced payment flow support for complex transaction scenarios

## [1.1.0] - 2025-06-23

### Added
- Full and partial refunds are supported only for AUD transactions
- For DCC transactions, a refund is not available via the plugin.
- Only full refunds are supported and available via the SecurePay Merchant Portal for DCC transactions.

## [1.0.0] - 2025-06-20

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
- JavaScript SDK styling customisation:
  - Background colour
  - Font family, size, and colour for labels and inputs
  - Allowed card types (Visa, Mastercard, American Express, Diners Club)
  - Card icon display options
- Card payment enable/disable toggle

### Security
- Secure API communication using OAuth 2.0
- PCI DSS compliant payment processing via SecurePay JavaScript SDK
- Tokenised payment processing (no card data stored locally)
- Secure credential management with caching
- Input validation and sanitisation

### Technical Implementation
- Extends Craft Commerce BaseGateway following official patterns
- Implements RequestResponseInterface for standardized responses
- Supports purchase operations (immediate capture)
- Payment form extends BasePaymentForm with token validation
- Automatic JavaScript SDK loading and configuration
- IP address detection for fraud prevention
- Amount conversion to cents for API compatibility

### Limitations (Planned for Future Releases)
- Apple Pay support (not yet implemented)
- Dynamic Currency Conversion (not yet implemented)
- Payment sources/stored payment methods (not yet implemented)
- Fraud detection integration (not yet implemented)
