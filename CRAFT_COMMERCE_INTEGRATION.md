# Craft Commerce Integration Guide

This document explains how the SecurePay plugin integrates with Craft Commerce following the [official Craft Commerce payment gateway patterns](https://craftcms.com/docs/commerce/5.x/extend/payment-gateway-types.html).

## Overview

The SecurePay plugin extends the Craft Commerce `BaseGateway` class and implements the `GatewayInterface` to provide a complete payment gateway integration that follows Commerce's expected patterns.

## Gateway Architecture

### Class Structure
```php
namespace brightlabs\securepay\gateways;

class Gateway extends BaseGateway
```

The gateway follows the standard Commerce gateway architecture:

1. **Configuration Management** - Individual gateway settings (no global plugin settings)
2. **Feature Support Declaration** - Methods that return true/false for supported features
3. **Payment Processing** - Core methods for purchase operations (authorise/capture)
4. **Form Handling** - Payment form generation with JavaScript SDK integration
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
public function supportsPaymentSources(): bool { return false; } // Not implemented
public function supportsCompleteAuthorize(): bool { return true; }
public function supportsCompletePurchase(): bool { return true; }
```

### 3. Order Availability Check

The gateway implements order-specific availability checking:

```php
public function availableForUseWithOrder(Order $order): bool
{
    // Gateway must be enabled
    if (!$this->isFrontendEnabled) {
        return false;
    }
    
    // Required credentials check
    if (!$this->clientId || !$this->clientSecret || !$this->merchantCode) {
        Craft::info('SecurePay unavailable: Missing credentials', __METHOD__);
        return false;
    }

    // Don't allow $0 transactions
    if ($order->getOutstandingBalance() <= 0) {
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

    // Always register JavaScript SDK
    $this->registerJavaScriptSDK($view);
    
    $html = $view->renderTemplate('securepay/payment-form', $params);
    $view->setTemplateMode($previousMode);

    return $html;
}
```

### 3. Form Validation

The payment form model extends Commerce's `BasePaymentForm`:

```php
class SecurePayPaymentForm extends BasePaymentForm
{
    public ?string $token = null;
    public ?string $createdAt = null;
    public ?string $scheme = null;
    
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['token','createdAt','scheme'], 'required'];
        return $rules;
    }
    
    public function isTokenisedPayment(): bool
    {
        return !empty($this->token);
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

#### Authorise (Capture Later)
```php
public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
{
    return new PaymentResponse(['error' => 'Authorise not supported']);
}
```

#### Capture
```php
public function capture(Transaction $transaction, string $reference): RequestResponseInterface
{
    return new PaymentResponse(['error' => 'Capture not supported']);
}
```

## Response Handling

### 1. RequestResponseInterface Implementation

All payment methods return a `RequestResponseInterface` implementation:

```php
class PaymentResponse implements RequestResponseInterface
{
    public function isSuccessful(): bool 
    { 
        if (!isset($this->data['error']) && isset($this->data['status'])) {
            return $this->data['status'] === 'paid';
        }
        return false;
    }
    
    public function isProcessing(): bool { return true; }
    public function isRedirect(): bool { return false; }
    public function getTransactionReference(): string { return $this->data['bankTransactionId'] ?? ''; }
    public function getMessage(): string { return $this->data['gatewayResponseMessage'] ?? ''; }
    // ... other required methods
}
```

### 2. Payment Flow Responses

**Successful Payment**: 
- `isSuccessful()` returns `true` when status is 'paid'
- Customer redirected to order return URL

**Failed Payment**:
- `isSuccessful()` returns `false`
- Customer shown error message and can retry

**Processing State**:
- `isProcessing()` returns `true`
- Transaction marked as pending
- Webhook or completion endpoint updates status

## JavaScript SDK Integration

### 1. SDK Loading and Configuration

Following Commerce frontend patterns:

```php
private function registerJavaScriptSDK($view): void
{
    $baseUrl = $this->sandboxMode 
        ? Endpoint::ENDPOINT_API_SANDBOX
        : Endpoint::ENDPOINT_API_LIVE;
        
    $jsUrl = $this->sandboxMode 
        ? Endpoint::URL_SANDBOX_SCRIPT
        : Endpoint::URL_LIVE_SCRIPT;

    // Register the SecurePay JavaScript SDK
    $view->registerScript('', View::POS_END, [
        'src' => $jsUrl,
        'id'  => 'securepay-ui-js'
    ]); 
}
```

### 2. Form Submission Handling

The payment form template provides the UI component container:

```twig
{# plugins/securepay/src/templates/payment-form.twig #}
<div class="securepay-payment-form" id="securepay-payment-form">
    <div id="securepay-card-component" class="securepay-card-component">
        {# This will be populated by the SecurePay JavaScript SDK #}
    </div>
    <input type="hidden" name="scheme" id="cartScheme" value="">
    <input type="hidden" name="createdAt" id="cartCreatedAt" value="">
    <input type="hidden" name="token" id="cartToken" value="">
</div>
```

## Admin Interface Integration

### 1. Gateway Settings

The gateway provides a comprehensive configuration interface following Commerce patterns:

```twig
{# plugins/securepay/src/templates/gateway-settings.twig #}
{{ forms.textField({
    label: 'Merchant Code'|t('commerce'),
    name: 'merchantCode',
    value: gateway.merchantCode,
    required: true,
}) }}

{{ forms.textField({
    label: 'Client ID'|t('commerce'),
    name: 'clientId',
    value: gateway.clientId,
    required: true,
}) }}

{{ forms.passwordField({
    label: 'Client Secret'|t('commerce'),
    name: 'clientSecret',
    value: gateway.clientSecret,
    required: true,
}) }}

{{ forms.lightswitchField({
    label: 'Sandbox Mode'|t('commerce'),
    name: 'sandboxMode',
    on: gateway.sandboxMode,
}) }}
```

### 2. JavaScript SDK Styling Options

The gateway provides extensive styling customization:

```twig
{{ forms.colorField({
    label: 'Background Colour'|t('commerce'),
    name: 'backgroundColour',
    value: gateway.backgroundColour,
}) }}

{{ forms.textField({
    label: 'Label Font Family'|t('commerce'),
    name: 'labelFontFamily',
    value: gateway.labelFontFamily,
}) }}

{{ forms.checkboxSelectField({
    label: 'Allowed Card Types'|t('commerce'),
    name: 'allowedCardTypes',
    values: gateway.allowedCardTypes,
    options: [
        { label: 'Visa', value: 'visa' },
        { label: 'Mastercard', value: 'mastercard' },
        { label: 'American Express', value: 'amex' },
        { label: 'Diners Club', value: 'diners' }
    ],
}) }}
```

### 3. Transaction Management

Commerce automatically provides:
- Transaction listing and details
- Payment status tracking

## Webhook Integration

### 1. Webhook Processing

Following Commerce webhook patterns:

```php
public function processWebHook(): WebResponse
{
    $response = Craft::$app->getResponse();
    
    try {
        $request = Craft::$app->getRequest();
        $body = $request->getRawBody();
        $data = Json::decode($body);

        // Process the webhook data
        $this->handleWebhookEvent($data);

        $response->setStatusCode(200);
        $response->data = 'OK';
    } catch (\Exception $e) {
        Craft::error('SecurePay webhook error: ' . $e->getMessage(), __METHOD__);
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
    // Implementation for processing webhook events
    // Updates transaction status based on SecurePay events
}
```

## Payment Processing Implementation

### 1. Core Payment Creation

The gateway implements payment processing using SecurePay's API:

```php
private function createPayment(Transaction $transaction, BasePaymentForm $form, bool $capture): RequestResponseInterface
{
    try {
        // Get credential and SecurePay Authentication
        $this->getCredential();
        
        // Get order and payment data
        $order = $transaction->getOrder();
        
        $paymentData = [
            'merchantCode' => $this->credential->getMerchantCode(),
            'token' => $this->cardTokenise,
            'ip' => $this->getOrderIp($order),
            'amount' => $this->convertAmount($transaction->paymentAmount),
            'currency' => 'AUD',
        ];

        if ($order->id) {
            $paymentData['orderId'] = (string) $order->id;
        }

        // Create payment request
        $createPaymentRequest = new CreatePaymentRequest($this->credential->isLive(), $this->credential, $paymentData);
        $create_payment_result = $createPaymentRequest->execute()->toArray();
        
        return new PaymentResponse($create_payment_result);
    } catch (\Exception $e) {
        Craft::error('SecurePay payment error: ' . $e->getMessage(), __METHOD__);
        return new PaymentResponse(['error' => $e->getMessage()]);
    }
}
```

### 2. Authentication and Credential Management

The gateway implements token-based authentication with caching:

```php
public function getCredential()
{
    if ($this->credential === null) {
        $cache = Craft::$app->getCache();
        $cache_key = "securepay_token2_" . (!$this->sandboxMode ? 'live' : 'test'). '_' . md5($this->merchantCode . $this->clientId . $this->clientSecret);
        $token = $cache->getOrSet($cache_key, function() {
            $request = new ClientCredentialsRequest(!$this->sandboxMode, $this->clientId, $this->clientSecret);
            $response = $request->execute();
            return $response->getAccessToken();
        }, 86400); // 1 day cache
        
        $this->credential = new Credential(!$this->sandboxMode, $this->merchantCode, $this->clientId, $this->clientSecret, $token);
    }
}
```

## Configuration Examples

### 1. Multiple Gateway Setup

Store managers can configure multiple SecurePay gateways:

- **Live Gateway**: Production credentials, purchase mode
- **Staging Gateway**: Sandbox credentials, purchase mode  
- **Testing Gateway**: Test credentials, all features enabled

### 2. Feature Toggles

Each gateway can be configured independently:

```php
// Gateway 1: Basic card processing
$gateway1->cardPayments = true;
$gateway1->showCardIcons = true;

// Gateway 2: Custom styling
$gateway2->backgroundColour = '#f5f5f5';
$gateway2->labelFontFamily = 'Roboto, sans-serif';
$gateway2->allowedCardTypes = ['visa', 'mastercard'];
```

## Error Handling

### 1. Gateway Errors

Following Commerce error patterns:

```php
try {
    $create_payment_result = $createPaymentRequest->execute()->toArray();
    return new PaymentResponse($create_payment_result);
} catch (\Exception $e) {
    Craft::error('SecurePay payment error: ' . $e->getMessage(), __METHOD__);
    return new PaymentResponse(['error' => $e->getMessage()]);
}
```

### 2. Form Validation Errors

Commerce handles form validation automatically:

```php
protected function defineRules(): array
{
    $rules = parent::defineRules();
    $rules[] = [['token','createdAt','scheme'], 'required'];
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
- Cache access tokens appropriately (24-hour cache implemented)
- Use connection pooling for API requests
- Implement timeout handling
- Log performance metrics

### 3. User Experience
- Provide clear error messages
- Show processing states
- Handle network failures gracefully
- Support form pre-population

### 4. Testing
- Test purchase flow (primary supported operation)
- Verify webhook handling
- Test error conditions
- Validate form submissions

## Integration Checklist

- [x] Extends `BaseGateway` class
- [x] Implements `GatewayInterface`
- [x] Registers with Commerce gateway system
- [x] Provides feature support methods
- [x] Implements purchase processing method
- [x] Returns proper `RequestResponseInterface` objects
- [x] Handles order availability checking
- [x] Provides payment form HTML and model
- [x] Supports webhook processing
- [x] Integrates with admin interface
- [x] Follows Commerce URL patterns
- [x] Provides proper error handling
- [x] Supports JavaScript SDK integration
- [x] Implements configuration validation
- [x] Implements refund operations
- [X] Implements authorise/capture operations
- [ ] Implements payment sources (planned)

## Current Limitations

1. **Payment Sources**: Not yet implemented (stored payment methods)
2. **3D Secure**: Basic support through completion methods

## Future Enhancements

1. **Payment Sources**: Support for storing payment methods
2. **Enhanced 3D Secure**: Improved 3D Secure authentication flow
3. **Fraud Detection**: Integration with SecurePay's fraud detection features

This integration follows all the patterns described in the [official Craft Commerce payment gateway documentation](https://craftcms.com/docs/commerce/5.x/extend/payment-gateway-types.html) and provides a solid foundation for SecurePay payment processing with room for future enhancements. 