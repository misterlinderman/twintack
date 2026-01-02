# WP-Cron & Shippo Sync Fix Guide

## 🔴 PROBLEM IDENTIFIED

**Your Shippo sync has stopped working because WordPress Cron (WP-Cron) is not running.**

### Evidence:
- WordPress admin shows: "Automatic update overdue by 9 hours. There may be a problem with WP-Cron."
- Orders are showing as "Processing" in WooCommerce
- Orders are NOT appearing in Shippo
- Stripe payments are confirmed successfully

### Root Cause:
The `twintack-manual-order-payments` plugin relies on WP-Cron to automatically sync orders to Shippo every 4 hours. When WP-Cron stops working, orders don't get synced.

---

## ✅ IMMEDIATE FIXES (Choose One)

### Option 1: Manual Sync via WordPress Admin (EASIEST)
1. Log into WordPress admin
2. Go to **WooCommerce → Shippo Sync**
3. Click the button **"🔄 Simulate Shippo Webhooks"**
4. This will immediately sync all eligible orders to Shippo

**When to use:** This is the quickest fix right now to clear the backlog.

---

### Option 2: Use Diagnostic Tool
1. Visit this URL: `https://yourdomain.com/wp-content/plugins/twintack-manual-order-payments/test-wp-cron-status.php`
2. This page will show:
   - WP-Cron status
   - Number of orders waiting to sync
   - Scheduled tasks
3. Click **"Force Shippo Sync Now"** button
4. Review the diagnostic information

**When to use:** If you want detailed information about what's wrong.

---

### Option 3: Command Line (If You Have SSH Access)
```bash
# Trigger WP-Cron manually
curl https://yourdomain.com/wp-cron.php

# Check scheduled events
wp cron event list --fields=hook,next_run_relative
```

**When to use:** If you have SSH/terminal access to the server.

---

## 🛠️ LONG-TERM SOLUTIONS

### Why Does WP-Cron Fail?

WordPress's "cron" system isn't a real cron job. It relies on **website traffic** to trigger scheduled tasks:
- Visitor comes to site → WordPress checks if cron is due → Runs scheduled tasks
- **No visitors = No cron runs**

Other causes:
- Low traffic websites
- Aggressive caching (caching plugins prevent WP-Cron from triggering)
- Server configuration (some hosts disable WP-Cron)
- PHP timeouts or memory limits

---

### Solution A: Real Server Cron Job (RECOMMENDED)

Set up a real server cron job that triggers WP-Cron every 15 minutes.

#### If You Have cPanel Access:
1. Log into cPanel
2. Go to **Advanced → Cron Jobs**
3. Add new cron job:
   - **Minute:** `*/15` (every 15 minutes)
   - **Hour:** `*`
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`
   - **Command:** 
     ```bash
     curl https://yourdomain.com/wp-cron.php > /dev/null 2>&1
     ```
     OR
     ```bash
     wget -q -O - https://yourdomain.com/wp-cron.php > /dev/null 2>&1
     ```

4. Save the cron job

#### If You Have SSH Access:
1. SSH into your server
2. Edit your crontab:
   ```bash
   crontab -e
   ```
3. Add this line:
   ```bash
   */15 * * * * curl https://yourdomain.com/wp-cron.php > /dev/null 2>&1
   ```
4. Save and exit

#### If You Have NO Server Access (FTP Only):
Contact your hosting provider and ask them to:
- Add a crontab entry to run `wp-cron.php` every 15 minutes
- Check if WP-Cron is blocked by server configuration
- Verify if `DISABLE_WP_CRON` is set in `wp-config.php`

---

### Solution B: Disable WP-Cron & Use Real Cron

This is the most reliable approach for production sites.

1. **Edit `wp-config.php`** and add this line (before `/* That's all, stop editing! */`):
   ```php
   define('DISABLE_WP_CRON', true);
   ```

2. **Set up real cron job** (see Solution A above for how to do this)

3. **Why this is better:**
   - More reliable (doesn't depend on site traffic)
   - Better performance (doesn't slow down page loads)
   - Precise timing (runs exactly when scheduled)

---

### Solution C: Use a Cron Service

If you can't set up server cron jobs, use an external service:

#### Free Services:
- **EasyCron** (https://www.easycron.com/): Free plan allows 1 cron job
- **cron-job.org** (https://cron-job.org/): Free cron job service
- **UptimeRobot** (https://uptimerobot.com/): Monitor URL + trigger cron

#### Setup:
1. Sign up for the service
2. Create a new cron job
3. Set URL: `https://yourdomain.com/wp-cron.php`
4. Set interval: Every 15 minutes
5. The service will call your WP-Cron URL on schedule

---

## 🔍 TESTING & VERIFICATION

### After Implementing a Fix:

1. **Check WP-Cron is running:**
   - Visit the diagnostic tool: `test-wp-cron-status.php`
   - Verify "Next Scheduled Run" is showing a future time
   - Check no events are "OVERDUE"

2. **Check Shippo Sync:**
   - Go to **WooCommerce → Shippo Sync**
   - Verify "Automation Status" shows **✅ ENABLED**
   - Check "Next Run" time is reasonable

3. **Test with a new order:**
   - Create a test order in WooCommerce
   - Mark it as "Processing"
   - Wait 5-10 minutes
   - Check if it appears in Shippo

4. **Monitor logs:**
   - Go to **WooCommerce → Status → Logs**
   - Select log: `twintack-manual-payments`
   - Look for: `"Automated Shippo Sync: Starting scheduled sync"`

---

## 📋 TROUBLESHOOTING

### Orders Still Not Syncing?

**Check order status:**
- Orders must be in **"Processing"** status to sync
- Orders must have **physical products** (virtual/downloadable products are skipped)

**Check Shippo Order ID:**
- Edit the order in WooCommerce admin
- Scroll to "Custom Fields"
- Look for `_shippo_order_id` - this must exist for sync to work

**Check age filter:**
- Go to **WooCommerce → Shippo Sync**
- Check "Minimum Order Age for Sync" setting
- Default is 24 hours - orders younger than this won't sync
- Change to "Immediate (0 hours)" for same-day orders

**Check order source:**
- Amazon orders may have special handling
- Google orders may have special handling
- Check order notes for any skip reasons

---

## 📞 SUPPORT CONTACTS

### If You Need Help:

1. **Hosting Provider:**
   - Can help with: Setting up cron jobs, checking server configuration
   - Can verify: If WP-Cron is blocked at server level

2. **Shippo Support:**
   - Can verify: If orders are actually in Shippo system
   - Can check: API credentials and webhook configuration

3. **WordPress Developer:**
   - Can help with: Custom cron configuration
   - Can debug: Plugin conflicts or errors

---

## 🚨 EMERGENCY WORKAROUND

**If nothing else works and you need orders in Shippo RIGHT NOW:**

1. Go to **WooCommerce → Shippo Sync**
2. Click **"🔄 Simulate Shippo Webhooks"**
3. This manually processes all eligible orders
4. **Repeat this 2-3 times per day** until the cron issue is fixed

**This is NOT a long-term solution**, but it will keep your orders flowing.

---

## 📊 MONITORING

### Set Up Monitoring to Catch This Early:

1. **Check WP-Cron Status Daily:**
   - Bookmark: `yourdomain.com/wp-content/plugins/twintack-manual-order-payments/test-wp-cron-status.php`
   - Look for any "OVERDUE" events

2. **Check Order Sync Status:**
   - Go to **WooCommerce → Shippo Sync** daily
   - Verify "Next Run" time is updating
   - Check "Eligible for Sync" count isn't growing

3. **Check Shippo Dashboard:**
   - Log into Shippo
   - Verify new orders are appearing
   - Compare with WooCommerce order count

---

## 📝 SUMMARY FOR YOUR CLIENT

**What happened:**
- WordPress's automatic task scheduler (WP-Cron) stopped working
- This prevented orders from automatically syncing to Shippo
- All Stripe payments processed successfully, but orders didn't reach Shippo

**Immediate fix:**
- Go to **WooCommerce → Shippo Sync** and click **"🔄 Simulate Shippo Webhooks"**
- This will sync all orders immediately

**Long-term fix:**
- Contact hosting provider to set up a real cron job
- OR use an external cron service (EasyCron, cron-job.org)
- This ensures WP-Cron runs reliably every 15 minutes

**Prevention:**
- Monitor the Shippo Sync page daily
- Set up alerts if order count grows without syncing
- Consider upgrading to dedicated hosting with better cron support

