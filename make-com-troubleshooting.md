# Make.com Integration Troubleshooting Guide

## Current Error
**Error:** `getaddrinfo EAI_AGAIN meo.efe.mybluehost.me`
**Meaning:** DNS resolution failure - Make.com can't connect to your WordPress site

## Step-by-Step Troubleshooting

### Step 1: Verify WordPress API Endpoints

Your plugin registers these endpoints:
- `POST /wp-json/twintack/v1/grip-design/{id}/monday`
- `POST /wp-json/twintack/v1/grip-design/{id}/customer-feedback`
- `POST /wp-json/twintack/v1/grip-design/{id}/purchase-complete`

**Test with cURL:**
```bash
curl -X GET "https://meo.efe.mybluehost.me/wp-json/wp/v2/"
```

### Step 2: Check API Key Configuration

**Default API Key:** `twintack-monday-2024`

**To set custom API key, add to wp-config.php:**
```php
define('TWINTACK_MONDAY_API_KEY', 'your-secure-api-key-here');
```

### Step 3: Test API Endpoint Manually

Replace `{GRIP_ID}` with an actual grip design ID:

```bash
curl -X POST "https://meo.efe.mybluehost.me/wp-json/twintack/v1/grip-design/{GRIP_ID}/monday" \
  -H "X-API-Key: twintack-monday-2024" \
  -H "Content-Type: application/json" \
  -d '{
    "monday_feedback": "Test from Make.com",
    "monday_item_id": "123456",
    "artwork_status": "pending_review"
  }'
```

### Step 4: Make.com Scenario Setup

**Webhook Trigger Setup:**
1. **Module:** Custom Webhook
2. **URL:** Use the grip design endpoint URL
3. **Method:** POST
4. **Headers:**
   - `Content-Type: application/json`
   - `X-API-Key: twintack-monday-2024`

**Common Make.com Issues:**
1. **DNS Caching:** Make.com might have cached old DNS. Wait 24 hours or contact support.
2. **Rate Limiting:** Your hosting provider might be blocking rapid requests
3. **Firewall Rules:** Bluehost might be blocking Make.com's IP ranges
4. **SSL Certificate Issues:** Ensure your SSL certificate is valid

### Step 5: WordPress Hosting Configuration (Bluehost)

**Check these settings in your hosting panel:**

1. **Firewall Settings:**
   - Allow API requests from external sources
   - Whitelist Make.com IP ranges if possible

2. **Security Plugins:**
   - Temporarily disable security plugins to test
   - Check if Wordfence, Sucuri, or similar plugins are blocking requests

3. **Rate Limiting:**
   - Increase API request limits
   - Check if REST API is being throttled

### Step 6: Alternative Webhook Configuration

If the direct API approach fails, set up a webhook receiver:

Add to your theme's `functions.php`:

```php
// Configure webhook URL for Make.com
add_filter('grip_customer_feedback_webhook_url', function() {
    return 'https://hook.make.com/your-webhook-id-here';
});
```

Then in Make.com:
1. Create a new scenario with "Webhooks > Custom webhook" as trigger
2. Copy the webhook URL to the filter above
3. Use "WordPress > Make an API Call" to update grip designs

### Step 7: Debug Information to Collect

Run the debug script and collect:

1. **WordPress Version & Plugins**
2. **Available API Endpoints**
3. **Sample Grip Design IDs**
4. **API Key Configuration**
5. **Any PHP Errors in WordPress logs**

### Step 8: Bluehost-Specific Fixes

**Enable WordPress API (if disabled):**
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Add to functions.php
add_filter('rest_authentication_errors', function($result) {
    if (!empty($result)) {
        return $result;
    }
    return $result;
});
```

**Check .htaccess for API blocks:**
Look for rules that might block `/wp-json/` requests.

### Step 9: Test Sequence

1. **Test locally first:** If you have a local development environment
2. **Test with Postman/Insomnia:** Verify endpoints work manually
3. **Test with simple webhook:** Before complex Make.com scenarios
4. **Check WordPress error logs:** Look for PHP errors during API calls
5. **Monitor Make.com execution logs:** Check for detailed error messages

### Step 10: Alternative Solutions

If the direct API approach continues to fail:

**Option A: WooCommerce Webhooks**
- Use WooCommerce's built-in webhook system
- Trigger on order completion instead of grip design purchase

**Option B: Database Polling**
- Make.com polls WordPress database directly
- Use "WordPress > Search Posts" module on a schedule

**Option C: Email-Based Integration**
- Send emails on grip design events
- Make.com monitors email for triggers

## Common Error Solutions

### Error: 401 Unauthorized
- Check API key in headers: `X-API-Key: your-key-here`
- Verify key matches WordPress configuration

### Error: 404 Not Found
- Verify grip design ID exists
- Check if post type is `grip_design`
- Ensure endpoints are registered (run debug script)

### Error: 500 Internal Server Error
- Check WordPress error logs
- Verify all plugin dependencies are active
- Test with WP_DEBUG enabled

### DNS/Connection Errors
- Verify domain resolves: `nslookup meo.efe.mybluehost.me`
- Check SSL certificate validity
- Test from different networks
- Contact Bluehost support about API access

## Make.com Scenario Best Practices

1. **Add delays between API calls** (2-3 seconds)
2. **Use error handling modules** for failed requests
3. **Store Make.com webhook URLs securely**
4. **Test scenarios in sandbox mode first**
5. **Monitor execution history for patterns**

## Contact Information

If issues persist:
1. **Bluehost Support:** Check firewall and API access
2. **Make.com Support:** Report DNS resolution issues
3. **WordPress Developer:** For custom integration solutions 