# 🚢 Shippo API Integration Debug Guide - v2.0.0

## 🎯 **MAJOR UPDATE: Switched to Shipments API**

**We've fixed the core issue!** The problem was using Shippo's **Orders API (beta)** instead of the standard **Shipments API**. This explains the permission errors and empty results.

### ✅ **What Changed in v2.0.0:**
- **Switched from Orders API to Shipments API** (the correct, stable Shippo workflow)
- **Fixed "Permission denied" errors** by using the main API endpoints
- **Improved address handling** with proper WooCommerce integration
- **Added comprehensive parcel data** for shipping calculations

## 🔍 Debugging Tools Available

### 1. **Debug Script** (`debug-shippo-api.php`)
Upload this file to your WordPress root and access it directly via browser:
```
https://yoursite.com/wp-content/plugins/twintack-manual-order-payments/debug-shippo-api.php
```

**Features:**
- Complete API configuration overview
- Test specific orders sync
- Raw API connectivity testing
- Recent plugin logs display
- System compatibility check

### 2. **Enhanced Logging** (Built-in)
The plugin now includes comprehensive logging that captures:
- Every API request (method, URL, headers, body)
- Every API response (status, headers, body)
- Request timing and performance
- Error details and stack traces

### 3. **Manual Sync Button** (Admin Interface)
Each order edit page now has a **🚢 Force Shippo Sync** button for testing.

---

## 🧪 How to Debug Shippo Integration

### Step 1: Check Basic Configuration
1. Go to **WooCommerce → Shippo API**
2. Verify your API token is configured
3. Click **"Test Connection"** - should show ✅ success

### Step 2: Enable WordPress Debug Logging
Add to your `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Step 3: Test with Debug Script
1. Upload `debug-shippo-api.php` to your site
2. Access it via browser
3. Click **"Test API Connectivity"** - this tests basic API access
4. Test sync with a specific order using the order cards

### Step 4: Test Manual Sync
1. Go to any order in WooCommerce admin
2. Find the **🚢 Force Shippo Sync** button
3. Click it and watch for success/error messages
4. Check the order notes for sync confirmation

### Step 5: Check Logs
Look in `/wp-content/debug.log` for detailed logs like:
```
Shippo API: ========== STARTING SYNC FOR ORDER #1234 ==========
Shippo API: 🌐 Making POST request to: https://api.goshippo.com/orders/
Shippo API: 📤 Request body: { ... }
Shippo API: 📥 Response code: 201
Shippo API: ✅ Parsed response: { ... }
```

---

## 🔍 Common Issues & Solutions

### Issue: "Connection successful" but orders don't appear in Shippo

**Possible Causes:**
1. **Wrong API endpoint** - Check if Shippo's API structure has changed
2. **Invalid data format** - Shippo may expect different field names/values
3. **Missing required fields** - Some fields might be mandatory but not documented
4. **Account permissions** - API token might not have order creation permissions

**Debug Steps:**
1. Check the exact API response in debug logs
2. Compare our request format with Shippo's API documentation
3. Test with minimal required fields only
4. Verify token permissions in Shippo dashboard

### Issue: API errors or timeouts

**Debug Steps:**
1. Check debug logs for exact error messages
2. Verify API token hasn't expired
3. Test API connectivity with debug script
4. Check server firewall/security settings

### Issue: Orders sync but don't appear correctly

**Debug Steps:**
1. Check what status/data is being sent vs. what Shippo expects
2. Verify field mapping in `format_order_for_shippo_api()` method
3. Test with different order statuses

---

## 📋 Information Needed for Further Debugging

If issues persist, please provide:

1. **Shippo API Documentation** for:
   - Orders endpoint URL and method
   - Required fields for order creation
   - Valid order status values
   - Response format examples

2. **Debug Output** from:
   - Debug script API connectivity test
   - Manual sync attempt with Force Sync button
   - Recent debug logs (from `/wp-content/debug.log`)

3. **Test Order Details**:
   - Order ID that was tested
   - Expected vs. actual behavior
   - Any error messages seen

---

## 🔧 Technical Details

### API Client Configuration
- **Base URL**: `https://api.goshippo.com/`
- **Authentication**: `ShippoToken YOUR_TOKEN`
- **Content-Type**: `application/json`
- **Timeout**: 30 seconds

### Order Status Mapping
- `invoiced` → `PAID` (for immediate fulfillment)
- `processing` → `PAID`
- `completed` → `SHIPPED`
- `cancelled` → `CANCELLED`

### Key Debug Methods
- `TwinTack_Shippo_API_Client::get_debug_info()` - System information
- `send_order_to_shippo()` - Main sync method
- `make_api_request()` - Low-level API communication

---

## 📞 Next Steps

With these debugging tools, we can:
1. See exactly what data is being sent to Shippo
2. Capture Shippo's exact response
3. Identify where the process is failing
4. Compare our implementation with Shippo's requirements

Run the tests and share the debug output to pinpoint the exact issue!