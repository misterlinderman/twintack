# Shippo API Integration Setup

## Overview
TwinTack Manual Payments v1.8.0 now includes direct Shippo API integration to automatically send invoiced orders to your Shippo dashboard for fulfillment.

## Setup Instructions

### 1. Get Your Shippo API Token
1. Log into your Shippo dashboard at [apps.goshippo.com](https://apps.goshippo.com)
2. Go to **Settings** → **API**
3. Copy your **Live Token** (for production) or **Test Token** (for testing)

### 2. Configure the API in WordPress
1. In WordPress admin, go to **WooCommerce** → **Shippo API**
2. Paste your API token in the "Shippo API Token" field
3. Click **Save Changes**
4. Click **Test Connection** to verify the integration works

### 3. How It Works

When you create a manual order and click **"Pay Later (Invoice)"**:

1. ✅ Order is set to "invoiced" status in WooCommerce
2. ✅ Order appears in WooCommerce admin lists properly
3. ✅ **NEW:** Order is automatically sent to Shippo API
4. ✅ Order appears in your Shippo dashboard as "PAID" status
5. ✅ Your fulfillment team can immediately process the order
6. ✅ No fulfillment holds despite pending payment

### 4. Order Status Mapping

| WooCommerce Status | Shippo Status | Action |
|-------------------|---------------|---------|
| **Invoiced** | **PAID** | ✅ Ready for immediate fulfillment |
| Processing | PAID | ✅ Ready for fulfillment |
| Completed | SHIPPED | ✅ Marked as shipped |
| Cancelled | CANCELLED | ❌ Cancelled in Shippo |

### 5. Troubleshooting

**Order not appearing in Shippo?**
- Check that API token is configured correctly
- Use the "Test Connection" button in WooCommerce → Shippo API
- Check order meta for `_shippo_object_id` (should be set after sync)

**Connection test failing?**
- Verify you're using the correct API token
- Make sure you're using Live token for production, Test token for testing
- Check that your server can make outbound HTTPS requests

**Need to resync an order?**
- Go to the order edit page in WooCommerce
- Look for "Shippo Fulfillment Status" sidebar
- Click "Force Sync with Shippo"

### 6. Important Notes

- **Invoiced orders are sent to Shippo as "PAID"** - this ensures immediate fulfillment
- **No fulfillment holds** are applied to invoiced orders
- **Orders sync automatically** when status changes to "invoiced"
- **API calls are logged** for debugging purposes

### 7. Support

If you encounter issues:
1. Check the WooCommerce logs for Shippo API errors
2. Verify your API token has the correct permissions
3. Ensure your server allows outbound HTTPS connections to api.goshippo.com

---

**Business Requirement Met:** ✅ Manual invoiced orders now ship immediately while maintaining "Payment Pending" status for tracking purposes.