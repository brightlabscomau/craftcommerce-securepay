# Craft Commerce Integration Guide

This document explains how the SecurePay plugin integrates with Craft Commerce following the [official Craft Commerce payment gateway patterns](https://craftcms.com/docs/commerce/5.x/extend/payment-gateway-types.html).

## Overview

The SecurePay plugin extends the Craft Commerce `BaseGateway` class and implements the `GatewayInterface` to provide a complete payment gateway integration that follows Commerce's expected patterns.

## Gateway Architecture

### Class Structure
```php
namespace craft\securepay\gateways;

class Gateway extends BaseGateway
```

The gateway follows the standard Commerce gateway architecture:

1. **Configuration Management** - Individual gateway settings (no global plugin settings)
2. **Feature Support Declaration** - Methods that return true/false for supported features
3. **Payment Processing** - Core methods for authorize, capture, refund operations
4. **Form Handling** - Payment form generation with JavaScript SDK and direct API support
5. **Response Processing** - Standardized `PaymentResponse` objects
6. **Webhook Support** - Real-time event processing via Commerce webhook system

## Integration Points

### 1. Gateway Registration

The plugin registers the gateway type in the main Plugin class:

```php
Event::on(
    Gateways::class,
    Gateways::EVENT_REGISTER_GATEWAY_TYPES,
    function(RegisterComponentTypesEvent $event) {
        $event->types[] = Gateway::class;
    }
);
```

### 2. Feature Support Methods

Following Commerce patterns, the gateway declares its capabilities:

```php
public function supportsAuthorize(): bool { return true; }
public function supportsPurchase(): bool { return true; }
public function supportsCapture(): bool { return true; }
public function supportsRefund(): bool { return true; }
public function supportsPartialRefund(): bool { return true; }
public function supportsWebhooks(): bool { return true; }
public function supportsPartialPayments(): bool { return true; }
public function supportsPaymentSources(): bool { return false; } // Future version
public function supportsCompleteAuthorize(): bool { return true; }
public function supportsCompletePurchase(): bool { return true; }
```

### 3. Order Availability Check

The gateway implements order-specific availability checking:

```php
public function availableForUseWithOrder(Order $order): bool
{
    // Gateway must be enabled
    if (!$this->enabled) {
        return false;
    }
    
    // Required credentials check
    if (!$this->clientId || !$this->clientSecret || !$this->merchantCode) {
        Craft::warning('SecurePay gateway unavailable: Missing required credentials', __METHOD__);
        return false;
    }

    // Don't allow $0 transactions
    if ($order->getOutstandingBalance() <= 0) {
        return false;
    }

    // Don't allow completed orders
    if ($order->isCompleted) {
        return false;
    }

    return parent::availableForUseWithOrder($order);
}
```

## Payment Flow Integration

### 1. Checkout Page Integration

**Gateway Selection**: Commerce automatically presents available gateways based on `availableForUseWithOrder()` results.

**Configuration**: Store managers can configure multiple SecurePay gateways with different settings (sandbox vs live, different merchant codes, etc.).

### 2. Payment Form Generation

The gateway provides form HTML through `getPaymentFormHtml()`:

```php
public function getPaymentFormHtml(array $params = []): ?string
{
    $defaults = [
        'gateway' => $this,
        'paymentForm' => $this->getPaymentFormModel(),
    ];

    $params = array_merge($defaults, $params);

    $view = Craft::$app->getView();
    $previousMode = $view->getTemplateMode();
    $view->setTemplateMode(View::TEMPLATE_MODE_CP);

    // Register JavaScript SDK if enabled
    if ($this->useJavaScriptSDK) {
        $this->registerJavaScriptSDK($view);
    }
    
    $html = $view->renderTemplate('securepay/payment-form', $params);
    $view->setTemplateMode($previousMode);

    return $html;
}
```

### 3. Form Validation

The payment form model extends Commerce's `CreditCardPaymentForm`:

```php
class SecurePayPaymentForm extends CreditCardPaymentForm
{
    public ?string $token = null;
    public ?string $paymentMethod = null;
    public ?array $dccSelection = null;
    public ?string $deviceFingerprint = null;
    
    public function rules(): array
    {
        $rules = parent::rules();
        
        // If we have a token from the JavaScript SDK, we don't need traditional card validation
        if ($this->token) {
            // Remove required validation for card fields when using token
            $requiredRules = array_filter($rules, function($rule) {
                return !(is_array($rule) && isset($rule[1]) && $rule[1] === 'required');
            });
            
            // Add token validation
            $requiredRules[] = [['token'], 'required'];
            $requiredRules[] = [['token'], 'string'];
            
            return $requiredRules;
        }

        // Traditional validation for direct card entry
        $rules[] = [['paymentMethod'], 'string'];
        $rules[] = [['deviceFingerprint'], 'string'];
        $rules[] = [['dccSelection'], 'safe'];
        
        return $rules;
    }
}
```

### 4. Payment Processing

Following Commerce patterns, payment processing uses standardized methods:

#### Purchase (Immediate Capture)
```php
public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
{
    return $this->createPayment($transaction, $form, true);
}
```

#### Authorize (Capture Later)
```php
public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
{
    return $this->createPayment($transaction, $form, false);
}
```

#### Capture
```php
public function capture(Transaction $transaction, string $reference): RequestResponseInterface
{
    $response = $this->sendRequest('POST', '/v2/payments/preauth/' . $reference . '/capture', $captureData);
    return new PaymentResponse($response);
}
```

## Response Handling

### 1. RequestResponseInterface Implementation

All payment methods return a `RequestResponseInterface` implementation:

```php
class PaymentResponse implements RequestResponseInterface
{
    public function isSuccessful(): bool { ... }
    public function isRedirect(): bool { ... }
    public function getRedirectUrl(): string { ... }
    public function getTransactionReference(): string { ... }
    public function getMessage(): string { ... }
    // ... other required methods
}
```

### 2. Payment Flow Responses

**Successful Payment**: 
- `isSuccessful()` returns `true`
- Customer redirected to order return URL

**Failed Payment**:
- `isSuccessful()` returns `false`
- Customer shown error message and can retry

**3D Secure Redirect**:
- `isRedirect()` returns `true`
- Customer redirected to authentication page
- Returns to completion URL with transaction hash

**Processing State**:
- `isProcessing()` returns `true`
- Transaction marked as pending
- Webhook or completion endpoint updates status

## JavaScript SDK Integration

### 1. SDK Loading and Configuration

Following Commerce frontend patterns:

```javascript
// Automatic SDK loading
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.securePayInstance !== 'undefined') {
        initializeSecurePayForm();
    } else {
        window.addEventListener('securepay-loaded', initializeSecurePayForm);
    }
});
```

### 2. Form Submission Handling

Integrates with Commerce form submission patterns:

```javascript
function handleFormSubmit(event) {
    event.preventDefault();
    
    // Tokenize payment details
    window.securePayInstance.createToken(window.securePayCard).then(function(result) {
        if (result.error) {
            showError(result.error.message);
        } else {
            // Add token to form and submit to Commerce
            document.getElementById('payment-token').value = result.token.id;
            form.submit(); // Submits to /commerce/payments/pay
        }
    });
}
```

## Admin Interface Integration

### 1. Gateway Settings

The gateway provides a configuration interface following Commerce patterns:

```twig
{# plugins/securepay/src/templates/gateway-settings.twig #}
{{ forms.textField({
    label: 'Client ID'|t('commerce'),
    name: 'clientId',
    value: gateway.clientId,
    required: true,
}) }}

{{ forms.selectField({
    label: 'Payment Type'|t('commerce'),
    name: 'paymentType', 
    value: gateway.paymentType,
    options: gateway.getPaymentTypeOptions(),
}) }}
```

### 2. Transaction Management

Commerce automatically provides:
- Transaction listing and details
- Capture functionality for authorized payments
- Refund processing
- Payment status tracking

### 3. Webhook URL Display

The settings template shows the webhook URL for configuration:

```twig
{% if gateway.id %}
    <code>{{ siteUrl }}actions/commerce/webhooks/process-webhook?gateway={{ gateway.id }}</code>
{% endif %}
```

## Webhook Integration

### 1. Webhook Processing

Following Commerce webhook patterns:

```php
public function processWebHook(): WebResponse
{
    $response = Craft::$app->getResponse();
    
    try {
        $request = Craft::$app->getRequest();
        $data = Json::decode($request->getRawBody());
        
        $this->handleWebhookEvent($data);
        
        $response->setStatusCode(200);
        $response->data = 'OK';
    } catch (\Exception $e) {
        $response->setStatusCode(400);
        $response->data = 'Error processing webhook';
    }
    
    return $response;
}
```

### 2. Event Processing

Webhook events update transaction status following Commerce patterns:

```php
private function handleWebhookEvent(array $data): void
{
    switch ($data['eventType']) {
        case 'payment.completed':
            // Update transaction to completed
            break;
        case '3ds.completed':
            // Complete 3D Secure authentication
            break;
        case 'payment.failed':
            // Mark transaction as failed
            break;
    }
}
```

## Completion Flow

### 1. 3D Secure Completion

For 3D Secure payments, customers return to:
```
/commerce/payments/complete-payment?commerceTransactionHash={hash}
```

The gateway implements completion methods:

```php
public function completePurchase(Transaction $transaction): RequestResponseInterface
{
    return $this->getTransactionStatus($transaction);
}

public function completeAuthorize(Transaction $transaction): RequestResponseInterface  
{
    return $this->getTransactionStatus($transaction);
}
```

### 2. Status Verification

Completion methods verify payment status with SecurePay:

```php
private function getTransactionStatus(Transaction $transaction): RequestResponseInterface
{
    $response = $this->sendRequest('GET', '/v2/payments/' . $transaction->reference);
    return new PaymentResponse($response);
}
```

## Configuration Examples

### 1. Multiple Gateway Setup

Store managers can configure multiple SecurePay gateways:

- **Live Gateway**: Production credentials, purchase mode
- **Staging Gateway**: Sandbox credentials, authorize mode  
- **Testing Gateway**: Test credentials, all features enabled

### 2. Feature Toggles

Each gateway can be configured independently:

```php
// Gateway 1: Basic card processing
$gateway1->useJavaScriptSDK = false;
$gateway1->threeDSecure = false;
$gateway1->fraudDetection = false;

// Gateway 2: Full security features
$gateway2->useJavaScriptSDK = true;
$gateway2->threeDSecure = true;
$gateway2->fraudDetection = true;
$gateway2->applePay = true;
```

## Error Handling

### 1. Gateway Errors

Following Commerce error patterns:

```php
try {
    $response = $this->sendRequest('POST', '/v2/payments', $paymentData);
    return new PaymentResponse($response);
} catch (\Exception $e) {
    Craft::error('SecurePay payment error: ' . $e->getMessage(), __METHOD__);
    return new PaymentResponse(['error' => $e->getMessage()]);
}
```

### 2. Form Validation Errors

Commerce handles form validation automatically:

```php
public function rules(): array
{
    $rules = parent::rules();
    $rules[] = [['clientId', 'clientSecret', 'merchantCode'], 'required'];
    return $rules;
}
```

### 3. JavaScript Error Handling

Frontend errors follow Commerce patterns:

```javascript
if (result.error) {
    showError(result.error.message);
    // Re-enable form for retry
} else {
    // Success - submit to Commerce
    form.submit();
}
```

## Best Practices

### 1. Security
- Always validate order availability
- Use HTTPS for all API communications
- Sanitize logged data (never log sensitive payment info)
- Implement proper error handling

### 2. Performance
- Cache access tokens appropriately
- Use connection pooling for API requests
- Implement timeout handling
- Log performance metrics

### 3. User Experience
- Provide clear error messages
- Show processing states
- Handle network failures gracefully
- Support form pre-population

### 4. Testing
- Test all payment flows (purchase, authorize/capture, refund)
- Verify webhook handling
- Test error conditions
- Validate form submissions

## Integration Checklist

- [x] Extends `BaseGateway` class
- [x] Implements `GatewayInterface`
- [x] Registers with Commerce gateway system
- [x] Provides feature support methods
- [x] Implements payment processing methods
- [x] Returns proper `RequestResponseInterface` objects
- [x] Handles order availability checking
- [x] Provides payment form HTML and model
- [x] Supports webhook processing
- [x] Integrates with admin interface
- [x] Follows Commerce URL patterns
- [x] Handles 3D Secure completion flow
- [x] Provides proper error handling
- [x] Supports JavaScript SDK integration
- [x] Implements configuration validation

This integration follows all the patterns described in the [official Craft Commerce payment gateway documentation](https://craftcms.com/docs/commerce/5.x/extend/payment-gateway-types.html) and provides a complete, production-ready payment gateway implementation. 