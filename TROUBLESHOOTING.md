# SecurePay Troubleshooting Guide

This guide helps resolve common issues with the SecurePay payment gateway integration for Craft Commerce.

**Plugin**: `craftcms/craft-securepay` v1.0.0  
**Support**: GitHub Issues | Craft Discord/Slack  
**SecurePay Support**: support@securepay.com.au

## Quick Checklist

### ✅ **1. Plugin Installation**
- [ ] Plugin is installed via composer
- [ ] Plugin is enabled in Settings → Plugins
- [ ] No installation errors in logs

### ✅ **2. Gateway Configuration**
- [ ] Gateway created in Commerce → System Settings → Gateways
- [ ] Gateway is enabled (toggle switched on)
- [ ] All required credentials are filled in:
  - Client ID
  - Client Secret  
  - Merchant Code

### ✅ **3. Order Requirements**
- [ ] Order has items with a total > $0
- [ ] Order has an outstanding balance > $0
- [ ] Order is not already paid

## Step-by-Step Diagnostic

### Step 1: Verify Plugin Registration

Check if the plugin is properly installed and registered:

```bash
# Check plugin status
php craft plugin/list
# Should show: securepay (enabled)

# Check available gateway types
php craft help gateways
# Should list SecurePay as an available type
```

### Step 2: Check Commerce Gateway Settings

1. Go to **Admin → Commerce → System Settings → Gateways**
2. Verify you have a SecurePay gateway created
3. Click on your gateway and check:
   - ✅ **Enabled** toggle is ON
   - ✅ **Client ID** is filled
   - ✅ **Client Secret** is filled  
   - ✅ **Merchant Code** is filled
   - ✅ **Payment Type** is set (Purchase or Authorize)

### Step 3: Check Order Status

In your checkout template, add debugging:

```twig
{# Debug order information #}
<div style="background: #f0f0f0; padding: 10px; margin: 10px 0;">
    <h4>Debug Info:</h4>
    <p><strong>Order Total:</strong> {{ cart.total|currency }}</p>
    <p><strong>Outstanding Balance:</strong> {{ cart.outstandingBalance|currency }}</p>
    <p><strong>Order ID:</strong> {{ cart.id }}</p>
    <p><strong>Available Gateways:</strong></p>
    <ul>
        {% for gateway in craft.commerce.gateways.allCustomerEnabledGateways %}
            <li>{{ gateway.name }} ({{ gateway.handle }}) 
                {% if gateway.availableForUseWithOrder(cart) %}
                    ✅ Available
                {% else %}
                    ❌ Not Available
                {% endif %}
            </li>
        {% endfor %}
    </ul>
</div>
```

### Step 4: Check Logs

Look for availability check logs:

```bash
# Check Craft logs
tail -f storage/logs/web.log | grep -i securepay

# Or check the log files directly
less storage/logs/web.log
```

Look for messages like:
- "SecurePay availability check for order ID: X"
- "SecurePay unavailable: Missing credentials"
- "SecurePay unavailable: Gateway is disabled"

### Step 5: Test Gateway Availability Manually

Create a test template to check gateway availability:

```twig
{# test-gateway.twig #}
{% set cart = craft.commerce.carts.cart %}
{% set gateways = craft.commerce.gateways.allCustomerEnabledGateways %}

<h2>Gateway Availability Test</h2>

<h3>Order Information:</h3>
<ul>
    <li>Order ID: {{ cart.id }}</li>
    <li>Total: {{ cart.total|currency }}</li>
    <li>Outstanding Balance: {{ cart.outstandingBalance|currency }}</li>
    <li>Is Completed: {{ cart.isCompleted ? 'Yes' : 'No' }}</li>
</ul>

<h3>All Gateways:</h3>
{% for gateway in gateways %}
    <div style="border: 1px solid #ccc; margin: 10px 0; padding: 10px;">
        <h4>{{ gateway.name }} ({{ gateway.class|split('\\')|last }})</h4>
        <ul>
            <li>Handle: {{ gateway.handle }}</li>
            <li>Enabled: {{ gateway.enabled ? 'Yes' : 'No' }}</li>
            <li>Available for Order: {{ gateway.availableForUseWithOrder(cart) ? 'Yes' : 'No' }}</li>
            {% if gateway.class == 'craft\\securepay\\gateways\\Gateway' %}
                <li>Client ID: {{ gateway.clientId ? 'Set' : 'Missing' }}</li>
                <li>Client Secret: {{ gateway.clientSecret ? 'Set' : 'Missing' }}</li>
                <li>Merchant Code: {{ gateway.merchantCode ? 'Set' : 'Missing' }}</li>
                <li>Sandbox Mode: {{ gateway.sandboxMode ? 'Yes' : 'No' }}</li>
            {% endif %}
        </ul>
    </div>
{% endfor %}
```

Access this at: `yoursite.test/test-gateway`

## Common Issues & Solutions

### ❌ **Issue: "Gateway not appearing in admin"**

**Solution:**
1. Clear caches: `php craft cache/flush-all`
2. Check plugin is enabled: Settings → Plugins → SecurePay
3. Verify gateway registration in `Plugin.php`

### ❌ **Issue: "Gateway appears in admin but not on checkout"**

**Solutions:**
1. **Check gateway is enabled** in Commerce settings
2. **Verify credentials** are filled in completely  
3. **Check order has balance** > $0
4. **Review logs** for availability check failures

### ❌ **Issue: "Missing credentials" in logs**

**Solution:**
1. Go to Commerce → Gateways → Your SecurePay Gateway
2. Fill in all required fields:
   - Client ID
   - Client Secret
   - Merchant Code
3. Save the gateway

### ❌ **Issue: "Order has no outstanding balance"**

**Solutions:**
1. Ensure cart has items with price > $0
2. Check if order is already marked as paid
3. Verify `cart.outstandingBalance` is > 0

### ❌ **Issue: "Gateway is disabled"**

**Solution:**
1. Go to Commerce → Gateways → Your SecurePay Gateway
2. Toggle **Enabled** switch to ON
3. Save the gateway

### ❌ **Issue: "Parent gateway check failed"**

**Solutions:**
1. Check if gateway handle conflicts with existing gateways
2. Verify gateway extends `BaseGateway` correctly
3. Check for any validation errors in gateway configuration

## Debug Template Code

Add this to your checkout template for detailed debugging:

```twig
{% if devMode %}
<div class="debug-panel" style="background: #f8f8f8; border: 1px solid #ddd; padding: 15px; margin: 15px 0;">
    <h3>🔍 SecurePay Debug Information</h3>
    
    {% set cart = craft.commerce.carts.cart %}
    {% set securePayGateways = [] %}
    
    {% for gateway in craft.commerce.gateways.allCustomerEnabledGateways %}
        {% if gateway.class == 'craft\\securepay\\gateways\\Gateway' %}
            {% set securePayGateways = securePayGateways|merge([gateway]) %}
        {% endif %}
    {% endfor %}
    
    <h4>Order Status:</h4>
    <ul>
        <li>Order ID: {{ cart.id ?? 'No cart' }}</li>
        <li>Total: {{ cart.total|currency ?? 'N/A' }}</li>
        <li>Outstanding: {{ cart.outstandingBalance|currency ?? 'N/A' }}</li>
        <li>Completed: {{ cart.isCompleted ? 'Yes' : 'No' }}</li>
    </ul>
    
    <h4>SecurePay Gateways Found: {{ securePayGateways|length }}</h4>
    
    {% if securePayGateways|length == 0 %}
        <p style="color: red;">❌ No SecurePay gateways found. Check gateway creation in Commerce → System Settings → Gateways.</p>
    {% else %}
        {% for gateway in securePayGateways %}
            <div style="border-left: 3px solid #007cba; padding-left: 10px; margin: 10px 0;">
                <h5>{{ gateway.name }}</h5>
                <ul>
                    <li>Enabled: {{ gateway.enabled ? '✅ Yes' : '❌ No' }}</li>
                    <li>Available: {{ gateway.availableForUseWithOrder(cart) ? '✅ Yes' : '❌ No' }}</li>
                    <li>Credentials: {{ (gateway.clientId and gateway.clientSecret and gateway.merchantCode) ? '✅ Complete' : '❌ Missing' }}</li>
                    <li>Environment: {{ gateway.sandboxMode ? 'Sandbox' : 'Live' }}</li>
                </ul>
            </div>
        {% endfor %}
    {% endif %}
    
    <h4>All Available Gateways:</h4>
    <ul>
        {% for gateway in craft.commerce.gateways.allCustomerEnabledGateways %}
            <li>{{ gateway.name }} - {{ gateway.availableForUseWithOrder(cart) ? '✅' : '❌' }}</li>
        {% endfor %}
    </ul>
</div>
{% endif %}
```

## Enable Debug Mode

Add to your `.env` file:
```
CRAFT_DEV_MODE=true
CRAFT_ENVIRONMENT=dev
```

## Contact Support

If none of these solutions work:

1. **Check Craft logs** for any PHP errors
2. **Test with a fresh cart** (empty current cart and add items again)
3. **Try creating a new gateway** with different credentials
4. **Contact Brightlabs** with:
   - Debug template output
   - Relevant log entries
   - Gateway configuration screenshots

## Advanced Debugging

For developers, add this to the Gateway class temporarily:

```php
public function availableForUseWithOrder(Order $order): bool
{
    // Force availability for debugging
    Craft::warning('SecurePay DEBUG: Forcing gateway availability', __METHOD__);
    return true;
}
```

This will make the gateway always appear (remove after testing).

---

**Remember:** Payment gateways are configured per-gateway in Commerce settings, not at the plugin level. The plugin provides the gateway type, but each gateway instance needs individual configuration. 