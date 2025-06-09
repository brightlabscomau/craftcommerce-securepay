# SecurePay Integration Guide for Craft Commerce

This guide provides comprehensive instructions for integrating SecurePay payment gateway with Craft Commerce, following the [official SecurePay API documentation](https://auspost.com.au/payments/docs/securepay/?javascript#integrating-with-securepay).

**Plugin**: `craftcms/craft-securepay` v1.0.0  
**Developer**: [Brightlabs](https://brightlabs.com.au/)  
**API**: SecurePay API v2 with OAuth 2.0

## Overview

This plugin implements the SecurePay API v2 with the following integration methods:

1. **JavaScript SDK Integration** (Recommended) - For enhanced security and PCI compliance
2. **Direct API Integration** - Traditional server-to-server card processing
3. **3D Secure 2.0** - Enhanced authentication with challenge flows
4. **Fraud Detection** - FraudGuard (default) and ACI ReD Shield support
5. **Apple Pay** - Native Apple Pay integration with domain verification
6. **Dynamic Currency Conversion** - Multi-currency support with real-time rates
7. **Webhook Integration** - Real-time payment status updates

## Integration Architecture

```
Frontend (JavaScript SDK) ←→ SecurePay Servers
         ↓
   Craft Commerce ←→ SecurePay API v2 (REST)
         ↓
   Backend Processing
```

## 1. JavaScript SDK Integration

### Overview
The JavaScript SDK integration follows the SecurePay official documentation for secure client-side tokenization.

### Features
- **PCI Compliance**: Card data never touches your server
- **Enhanced Security**: Client-side tokenization
- **3D Secure 2.0**: Built-in authentication flows
- **Apple Pay**: Native integration
- **Real-time Validation**: Instant card validation

### Implementation

#### 1.1 SDK Loading
```javascript
// The plugin automatically loads the SDK
<script src="https://payments-stest.npe.auspost.zone/v2/ui/securepay.min.js"></script>
```

#### 1.2 Configuration
```javascript
const securePayConfig = {
    clientId: 'your-client-id',
    merchantCode: 'your-merchant-code',
    baseUrl: 'https://payments-stest.npe.auspost.zone', // Sandbox
    threeDSecure: {
        enabled: true,
        challengeIndicator: '01'
    },
    fraud: {
        enabled: true,
        provider: 'fraudguard'
    }
};
```

#### 1.3 Payment Flow
1. Customer enters payment details in secure form
2. JavaScript SDK tokenizes card data client-side
3. Form submits token to Craft Commerce
4. Gateway processes payment with SecurePay API using token
5. Handle response (success, 3DS challenge, or error)
6. Commerce completes order or handles redirect

#### 1.4 Craft Commerce Integration
```twig
{# The plugin automatically renders the payment form #}
{# In your checkout template: #}

{% set cart = craft.commerce.carts.cart %}
{% set gateway = cart.gateway %}

<form method="post">
    {{ csrfInput() }}
    {{ actionInput('commerce/payments/pay') }}
    {{ hiddenInput('gatewayId', gateway.id) }}
    
    {# Gateway renders appropriate form based on settings #}
    {{ gateway.getPaymentFormHtml()|raw }}
    
    <button type="submit">Complete Payment</button>
</form>
```

## 2. REST API Integration

### Authentication
The plugin uses OAuth 2.0 Client Credentials flow:

```php
POST https://welcome.api2.sandbox.auspost.com.au/oauth/token
Authorization: Basic base64(clientId:clientSecret)
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&audience=https://api.payments.auspost.com.au
```

### Payment Creation
```php
POST https://payments-stest.npe.auspost.zone/v2/payments
Authorization: Bearer {access_token}
Content-Type: application/json

{
    "merchant": {
        "code": "your-merchant-code"
    },
    "customer": {
        "customerNumber": "customer-123",
        "firstName": "John",
        "lastName": "Doe",
        "email": "john@example.com"
    },
    "transaction": {
        "reference": "order-123",
        "amount": 2500, // $25.00 in cents
        "currency": "AUD",
        "capture": true
    },
    "payment": {
        "card": {
            "number": "4111111111111111",
            "expiryMonth": "12",
            "expiryYear": "2025",
            "cvv": "123",
            "cardHolderName": "John Doe"
        }
    }
}
```

## 3. Security Features

### 3.1 3D Secure 2.0
Enhanced authentication following EMV 3DS specification:

```json
{
    "threeDSecure": {
        "enabled": true,
        "challengeIndicator": "01",
        "requestorChallengeInd": "01"
    }
}
```

**Challenge Indicators:**
- `01` - No preference
- `02` - No challenge requested
- `03` - Challenge requested (3DS Requestor preference)
- `04` - Challenge requested (Mandate)

### 3.2 Fraud Detection

#### FraudGuard (Default)
```json
{
    "fraud": {
        "enabled": true,
        "provider": "fraudguard"
    }
}
```

#### ACI ReD Shield
```json
{
    "fraud": {
        "enabled": true,
        "provider": "aci",
        "deviceData": "collected-device-fingerprint"
    }
}
```

## 4. Apple Pay Integration

### Prerequisites
1. Apple Developer Account
2. Merchant ID registration
3. Domain verification file
4. Processing certificate

### Domain Verification
1. Download domain verification file from SecurePay portal
2. Upload to `/.well-known/apple-developer-merchantid-domain-association`
3. Ensure file is accessible via HTTPS

### JavaScript Implementation
```javascript
if (window.securePayInstance.canMakeApplePayPayments()) {
    // Show Apple Pay button
    document.getElementById('apple-pay-button').style.display = 'block';
    
    // Handle Apple Pay payment
    applePayButton.addEventListener('click', function() {
        const paymentRequest = {
            countryCode: 'AU',
            currencyCode: 'AUD',
            total: {
                label: 'Your Store',
                amount: '25.00'
            }
        };
        
        window.securePayInstance.processApplePayPayment(paymentRequest);
    });
}
```

## 5. Dynamic Currency Conversion (DCC)

### Overview
Allows customers to pay in their local currency with real-time exchange rates.

### Implementation
```json
{
    "dcc": {
        "enabled": true
    }
}
```

### Response Handling
```javascript
// DCC quote received
function handleDccQuote(quote) {
    if (quote.rates && quote.rates.length > 0) {
        // Display currency options to customer
        quote.rates.forEach(rate => {
            console.log(`Pay ${rate.convertedAmount} ${rate.currency}`);
            console.log(`Exchange rate: ${rate.exchangeRate}`);
            console.log(`Fee: ${rate.fee}`);
        });
    }
}
```

## 6. Error Handling

### Common Error Codes
- `E001` - Invalid card number
- `E002` - Card expired
- `E003` - Insufficient funds
- `E004` - Card declined
- `E005` - Invalid CVV
- `3DS001` - 3D Secure authentication failed
- `FRAUD001` - Transaction flagged by fraud detection

### Error Response Format
```json
{
    "error": {
        "code": "E001",
        "message": "Invalid card number",
        "field": "card.number"
    }
}
```

## 7. Testing

### Test Environment
- **Sandbox URL**: `https://payments-stest.npe.auspost.zone`
- **OAuth URL**: `https://welcome.api2.sandbox.auspost.com.au/oauth/token`

### Test Cards

#### Standard Test Cards
- **Visa**: `4111111111111111`
- **Mastercard**: `5555555555554444`
- **Amex**: `378282246310005`

#### 3D Secure Test Cards
- **Visa (Challenge)**: `4000000000001091`
- **Mastercard (Frictionless)**: `5200000000001096`

#### Error Testing Cards
- **Declined**: `4000000000000002`
- **Insufficient Funds**: `4000000000009995`
- **Expired Card**: `4000000000000069`

### Test Data
- **Expiry**: Any future date (e.g., `12/2025`)
- **CVV**: Any 3-4 digit number (e.g., `123`)
- **Name**: Any name

## 8. Production Deployment

### Checklist
1. ✅ Obtain live API credentials from SecurePay
2. ✅ Update base URLs to production endpoints
3. ✅ Disable sandbox mode
4. ✅ Configure SSL/TLS certificates
5. ✅ Set up domain verification for Apple Pay
6. ✅ Configure fraud detection rules
7. ✅ Test with small transactions
8. ✅ Monitor transaction logs

### Production URLs
- **API Base URL**: `https://payments.auspost.net.au`
- **OAuth URL**: `https://welcome.api2.auspost.com.au/oauth/token`

## 9. Monitoring and Logging

### Transaction Monitoring
- Monitor success/failure rates
- Track 3D Secure authentication rates
- Review fraud detection decisions
- Analyze payment method usage

### Logging Best Practices
```php
// Log successful payments
Craft::info('SecurePay payment successful: ' . $transactionReference, __METHOD__);

// Log errors (don't log sensitive data)
Craft::error('SecurePay payment failed: ' . $errorMessage, __METHOD__);
```

## 10. Support and Resources

### Official Documentation
- [SecurePay API Documentation](https://auspost.com.au/payments/docs/securepay/)
- [JavaScript SDK Reference](https://auspost.com.au/payments/docs/securepay/?javascript#javascript-sdk)
- [3D Secure 2 Guide](https://auspost.com.au/payments/docs/securepay/?javascript#3d-secure-2)

### Support Channels
- **Email**: support@securepay.com.au
- **Phone**: Available in merchant portal
- **API Status**: `GET /v2/health`

### Troubleshooting

#### Common Issues
1. **Authentication Failed**
   - Verify client ID and secret
   - Check token expiration
   - Ensure correct environment URLs

2. **3D Secure Failures**
   - Verify 3DS is enabled on account
   - Check browser compatibility
   - Test with 3DS test cards

3. **Apple Pay Issues**
   - Verify domain verification file
   - Check merchant ID configuration
   - Ensure HTTPS on all pages

4. **Fraud Detection**
   - Review fraud rules configuration
   - Check device fingerprinting
   - Verify customer data completeness

## 11. Security Best Practices

### PCI Compliance
- Use JavaScript SDK for card tokenization
- Never store card data on your servers
- Implement proper access controls
- Regular security audits

### API Security
- Protect API credentials
- Use environment variables for sensitive data
- Implement request signing where available
- Monitor for suspicious activity

### Client-Side Security
- Validate all inputs
- Use HTTPS everywhere
- Implement CSP headers
- Regular dependency updates

---

This integration guide follows the official SecurePay documentation patterns and provides a complete implementation reference for Craft Commerce developers. 