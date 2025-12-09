# SecurePay Troubleshooting Guide

This guide helps resolve common issues with the SecurePay payment gateway integration for Craft Commerce.

**Plugin**: `brightlabs/craft-securepay` v1.4.1  
**Support**: [Github Issues](https://github.com/brightlabscomau/craftcommerce-securepay/issues) | Craft Discord/Slack  
**SecurePay Support**: For issues with your SecurePay account, please contact SecurePay directly.

## Quick Checklist

### ✅ **1. Plugin Installation**
- [ ] Plugin is installed via `composer require brightlabs/craft-securepay`.
- [ ] Plugin is enabled in **Settings → Plugins**.
- [ ] No installation errors in `storage/logs/`.

### ✅ **2. Gateway Configuration**
- [ ] Gateway created in **Commerce → System Settings → Gateways**.
- [ ] Gateway is **Enabled** (toggle switched on).
- [ ] All required credentials are filled in and correct:
  - Merchant Code
  - Client ID
  - Client Secret
- [ ] **Sandbox Mode** is set correctly for the environment.
- [ ] **3D Secure 2.0** is configured as needed (optional but recommended for live).

### ✅ **3. Order Requirements**
- [ ] Order has items with a total > $0.
- [ ] Order has an outstanding balance > $0.
- [ ] Order is not already paid or completed.

## Common Issues & Solutions

### ❌ **Issue: "Gateway not appearing in admin"**

**Solution:**
1. Run `composer install` to ensure all dependencies are installed.
2. Clear Craft's caches: `php craft cache/flush-all`.
3. Check that the plugin is enabled in **Settings → Plugins → SecurePay**.
4. Verify gateway registration in the plugin's `Plugin.php` file.

### ❌ **Issue: "Gateway appears in admin but not on checkout"**

**Solutions:**
1. **Check Gateway is Enabled**: Go to **Commerce → System Settings → Gateways** and ensure the "Enabled" toggle is on for your SecurePay gateway.
2. **Verify Credentials**: Make sure the Merchant Code, Client ID, and Client Secret are all filled in correctly. The gateway will not be available if these are missing.
3. **Check Order Balance**: The order must have an outstanding balance greater than $0.
4. **Review Logs**: Check `storage/logs/web.log` for any "SecurePay unavailable" messages, which will give a specific reason.

### ❌ **Issue: "Payment form is blank or I see JavaScript errors in the browser console"**

**Solutions:**
1.  **Check Browser Console:** Open your browser's developer tools (usually F12) and check the "Console" tab for any errors related to `securepay.min.js` or `SecurePay`.
2.  **Firewall/Ad-blockers:** Ensure that no ad-blockers or corporate firewalls are blocking the SecurePay JavaScript from loading (`https://payments-stest.npe.auspost.zone` or `https://payments.auspost.net.au`).
3.  **Gateway Configuration:**
    *   Go to **Commerce → System Settings → Gateways** and open your SecurePay gateway.
    *   Ensure that a **Client ID** and **Merchant Code** are correctly configured, as the JavaScript SDK requires these to initialize.
4.  **Template Conflict:** Make sure your checkout page HTML is valid and there are no other JavaScript errors on the page that might be preventing the SecurePay script from running.

### ❌ **Issue: "Authentication errors after changing API credentials"**

**Solution:**
The plugin caches the authentication token for 24 hours. If you update your Client ID or Client Secret, the old token might still be in use.
1.  Go to **Utilities → Caches**.
2.  Select the "Data Caches" option.
3.  Click "Clear caches" to flush all data caches, which will force the plugin to request a new token with the new credentials.

### ❌ **Issue: "Missing credentials" in logs**

**Solution:**
1. Go to **Commerce → System Settings → Gateways → Your SecurePay Gateway**.
2. Fill in all required fields:
   - Merchant Code
   - Client ID
   - Client Secret
3. Save the gateway.

*Note: If you're in Sandbox Mode, the plugin automatically uses pre-configured test credentials, so you don't need to manually enter them.*

### ❌ **Issue: "Order has no outstanding balance"**

**Solutions:**
1. Ensure the cart has items with a price > $0.
2. Check if the order has already been marked as paid.
3. Use the debug template below to verify `cart.outstandingBalance` is > 0.

### ❌ **Issue: "3D Secure authentication failing"**

**Solutions:**
1. **Check 3D Secure Configuration**: Ensure 3D Secure 2.0 is enabled on your SecurePay merchant account.
2. **Gateway Settings**: Verify that the "3D Secure 2.0" toggle is enabled in your gateway settings.
3. **Test Cards**: Use test cards that support 3D Secure authentication.
4. **Browser Support**: Ensure you're using a supported browser for 3D Secure authentication.
5. **Network Issues**: Check that your server can reach SecurePay's 3D Secure endpoints.

### ❌ **Issue: "Sandbox credentials not working"**

**Solution:**
The plugin automatically uses pre-configured sandbox credentials when Sandbox Mode is enabled:
- **Merchant Code**: `5AR0055`
- **Client ID**: `0oaxb9i8P9vQdXTsn3l5`
- **Client Secret**: `0aBsGU3x1bc-UIF_vDBA2JzjpCPHjoCP7oI6jisp`

If you're still having issues:
1. Ensure Sandbox Mode is enabled in your gateway settings.
2. Clear Craft's caches: `php craft cache/flush-all`.
3. Check that the credentials are being automatically populated in the gateway settings.

---

## Debugging Templates & Tools

### Step 1: Check Logs

Look for availability check logs in `storage/logs/web.log`:

```bash
# Check Craft logs for SecurePay entries
tail -f storage/logs/web.log | grep -i securepay
```

Look for messages like:
- "SecurePay availability check for order ID: X"
- "SecurePay unavailable: Missing credentials"
- "SecurePay unavailable: Gateway is disabled"
- "SecurePay payment error: ..."
- "SecurePay 3D Secure error: ..."

### Step 2: Use the Debug Template

Add this to your main checkout template for detailed debugging information.

```twig
{% if devMode %}
<div class="debug-panel" style="background: #f8f8f8; border: 1px solid #ddd; padding: 15px; margin: 15px 0; font-family: monospace; font-size: 12px;">
    <h3>🔍 SecurePay Debug Information</h3>
    
    {% set cart = craft.commerce.carts.cart %}
    {% set securePayGateways = [] %}
    
    {% for gateway in craft.commerce.gateways.allGateways %}
        {% if gateway.class == 'brightlabs\\securepay\\gateways\\Gateway' %}
            {% set securePayGateways = securePayGateways|merge([gateway]) %}
        {% endif %}
    {% endfor %}
    
    <h4>Order Status:</h4>
    <ul style="list-style-type: disc; padding-left: 20px;">
        <li>Order ID: {{ cart.id ?? 'No cart' }}</li>
        <li>Total: {{ cart.total|currency ?? 'N/A' }}</li>
        <li>Outstanding: {{ cart.outstandingBalance|currency ?? 'N/A' }}</li>
        <li>Is Completed: {{ cart.isCompleted ? 'Yes' : 'No' }}</li>
    </ul>
    
    <h4>SecurePay Gateways Found: {{ securePayGateways|length }}</h4>
    
    {% if securePayGateways|length == 0 %}
        <p style="color: red;">❌ No SecurePay gateways found. Check gateway creation in Commerce → System Settings → Gateways.</p>
    {% else %}
        {% for gateway in securePayGateways %}
            <div style="border-left: 3px solid #007cba; padding-left: 10px; margin: 10px 0;">
                <h5>{{ gateway.name }}</h5>
                <ul style="list-style-type: disc; padding-left: 20px;">
                    <li>Enabled: {{ gateway.enabled ? '✅ Yes' : '❌ No' }}</li>
                    <li>Frontend Enabled: {{ gateway.isFrontendEnabled ? '✅ Yes' : '❌ No' }}</li>
                    <li>Available: {{ gateway.availableForUseWithOrder(cart) ? '✅ Yes' : '❌ No' }}</li>
                    <li>Credentials: {{ (gateway.clientId and gateway.clientSecret and gateway.merchantCode) ? '✅ Complete' : '❌ Missing' }}</li>
                    <li>Environment: {{ gateway.sandboxMode ? 'Sandbox' : 'Live' }}</li>
                    <li>3D Secure: {{ gateway.threeDSecure ? '✅ Enabled' : '❌ Disabled' }}</li>
                    <li>Card Payments: {{ gateway.cardPayments ? '✅ Enabled' : '❌ Disabled' }}</li>
                </ul>
            </div>
        {% endfor %}
    {% endif %}
    
    <h4>All Enabled Frontend Gateways:</h4>
    <ul style="list-style-type: disc; padding-left: 20px;">
        {% for gateway in craft.commerce.gateways.allCustomerEnabledGateways %}
            <li>{{ gateway.name }} - {{ gateway.availableForUseWithOrder(cart) ? '✅ Available' : '❌ Not Available' }}</li>
        {% endfor %}
    </ul>
</div>
{% endif %}
```

### Step 3: Enable Debug Mode

Add to your `.env` file to see the debug panel:
```
CRAFT_DEV_MODE=true
```

## Advanced Debugging

For developers, you can temporarily modify the gateway class to log more details.

In `vendor/brightlabs/craft-securepay/src/gateways/Gateway.php`, you can add logging to the `availableForUseWithOrder` method:

```php
// src/gateways/Gateway.php

public function availableForUseWithOrder(Order $order): bool
{
    Craft::info('SecurePay availability check for order ID: ' . $order->id, __METHOD__);
    
    if (!$this->isFrontendEnabled) {
        Craft::info('SecurePay unavailable: Gateway is disabled on the frontend.', __METHOD__);
        return false;
    }
    
    // ... add more logging around each check
    
    return parent::availableForUseWithOrder($order);
}
```

**Important**: Remember to remove any modifications to vendor files after you are done debugging, as they will be overwritten by Composer updates.

## 3D Secure Troubleshooting

### Common 3D Secure Issues

1. **Authentication Not Triggered**
   - Ensure 3D Secure 2.0 is enabled in your gateway settings
   - Verify 3D Secure is enabled on your SecurePay merchant account
   - Use test cards that support 3D Secure authentication

2. **Authentication Failing**
   - Check browser console for JavaScript errors
   - Ensure your server can reach SecurePay's 3D Secure endpoints
   - Verify SSL certificate is valid and properly configured

3. **User Experience Issues**
   - Test the authentication flow in different browsers
   - Ensure the 3D Secure challenge window displays properly
   - Check that authentication completion redirects correctly

### 3D Secure Testing

When testing 3D Secure 2.0:
- Use test cards that trigger authentication challenges
- Follow the authentication flow completely
- Test both successful and failed authentication scenarios
- Verify that transaction status updates correctly after authentication

---

**Remember:** Payment gateways are configured per-gateway in Commerce settings, not at the plugin level. The plugin provides the gateway type, but each gateway instance needs its own individual configuration. 