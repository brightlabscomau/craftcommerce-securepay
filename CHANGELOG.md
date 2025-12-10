# SecurePay for Craft Commerce Changelog

## 1.4.1 - 2025-08-05

### Added
- **Stored Payment Methods**: Full support for creating, managing, and using stored payment methods (payment sources)
- **3D Secure for Stored Cards**: Implemented 3D Secure authentication checks for stored payment method transactions
- **Payment Source Management**: Complete integration with Craft Commerce payment source system for saved cards

### Changed
- **Asset Organization**: Moved all CSS and JavaScript files to dedicated `/assets` directory for better organization
- **Checkout Experience**: Enhanced checkout flow to support both new card payments and stored payment method selection
- **Error Reporting**: Improved error messages and debugging capabilities for better troubleshooting

### Technical Improvements
- Enhanced payment source creation and deletion workflows
- Improved asset bundle handling and performance optimization
- Better integration with Craft Commerce payment source management
- Streamlined asset loading and organization structure

## 1.3.2 - 2025-07-14

### Fixed
- **JavaScript Syntax Error**: Fixed attribute selector escaping in payment form template
- **Form Element Selection**: Improved form element detection and selection logic
- **Hidden Field Classes**: Updated CSS class names for better consistency (`securepayCardToken`, `securepayCardScheme`, `securepayCardCreatedAt`)
- **Form Submission**: Enhanced form submission logic with better element detection
- **Page Reload Timing**: Increased error page reload delay from 1 second to 2 seconds for better user experience

### Technical Improvements
- Better DOM element selection in payment form JavaScript
- Improved form element detection using `closest()` method
- Enhanced error handling without unnecessary try-catch blocks
- More consistent CSS class naming convention

## 1.3.1 - 2025-06-30

### Added
- **Enhanced Credential Validation**: Real-time credential validation with status tracking
- **Frontend/Admin Messages**: Clear error messages shown when credentials are invalid in both frontend and admin panel
- **Final Credential Properties**: Better credential management with `clientIdFinal`, `clientSecretFinal`, and `merchantCodeFinal`

### Changed
- **Gateway Availability**: Gateway automatically hidden from frontend when credentials are invalid
- **JavaScript SDK**: Payment form only loads when credentials are valid in Live mode
- **Composer Repository**: Updated from GitHub repository for plugin distribution

### Security
- Gateway properly hidden from frontend when credentials are invalid in Live mode
- Enhanced credential validation before API operations

## 1.3.0 - 2025-06-25

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

## 1.2.1 - 2025-06-23

### Changed
- Converted all American spellings to Australian spellings throughout the plugin (documentation, user-facing strings, and code comments/labels).

## 1.2.0 - 2025-06-23

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

## 1.1.0 - 2025-06-23

### Added
- Full and partial refunds are supported only for AUD transactions
- For DCC transactions, a refund is not available via the plugin.
- Only full refunds are supported and available via the SecurePay Merchant Portal for DCC transactions.

## 1.0.0 - 2025-06-20

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
