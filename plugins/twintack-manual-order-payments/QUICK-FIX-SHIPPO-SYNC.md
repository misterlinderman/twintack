# 🚨 QUICK FIX: Shippo Sync Not Working

## Problem
Orders showing as "Processing" in WooCommerce but NOT appearing in Shippo.
Stripe payments are successful.

## Root Cause
**WP-Cron (WordPress automatic tasks) has stopped working.** 
The Shippo sync relies on WP-Cron to run every 4 hours.

---

## ⚡ IMMEDIATE FIX (Do This Now)

### Method 1: WordPress Admin (Easiest)
1. Log into WordPress admin
2. Go to **WooCommerce → Shippo Sync**
3. Click **"🔄 Simulate Shippo Webhooks"**
4. ✅ Done! All orders will sync to Shippo immediately

### Method 2: Direct Link
Visit this URL (replace `yourdomain.com` with your actual domain):
```
https://yourdomain.com/wp-content/plugins/twintack-manual-order-payments/test-wp-cron-status.php
```
Then click **"Force Shippo Sync Now"**

---

## 🔧 PERMANENT FIX (Contact Your Host)

Ask your hosting provider to set up a **real cron job** that runs every 15 minutes:

```bash
*/15 * * * * curl https://yourdomain.com/wp-cron.php > /dev/null 2>&1
```

**Why:** WordPress's built-in cron system relies on site traffic. Real cron jobs are more reliable.

---

## 📅 TEMPORARY WORKAROUND

Until the permanent fix is in place:
- Manually click "Simulate Shippo Webhooks" **2-3 times per day**
- Check **WooCommerce → Shippo Sync** to see if orders need syncing
- Set a calendar reminder to check daily

---

## ✅ VERIFY IT'S WORKING

After the fix:
1. Go to **WooCommerce → Shippo Sync**
2. Check **"Automation Status"** shows **✅ ENABLED**
3. Verify **"Next Run"** shows a future time
4. Create a test order and verify it appears in Shippo within 1-2 hours

---

## 📞 NEED HELP?

- **Diagnostic Tool:** `/test-wp-cron-status.php`
- **Full Guide:** `/WP-CRON-SHIPPO-FIX-GUIDE.md`
- **Hosting Support:** Ask about setting up cron jobs for `wp-cron.php`

