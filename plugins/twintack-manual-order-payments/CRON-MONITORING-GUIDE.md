# WP-Cron Monitoring Guide

## 🎯 Overview

The TwinTack Manual Order Payments plugin now includes **automatic monitoring** of your WP-Cron health to prevent sync failures before they happen.

---

## 📊 Monitoring Features

### 1. Dashboard Widget

**Location:** WordPress Admin → Dashboard (main screen)

**What it shows:**
- ✅ **Overall Health Status** - Green (healthy) or Red (needs attention)
- 🕐 **Last Cron Run** - When WP-Cron last executed
- 🔄 **Real Cron Job Status** - Whether you're using server cron or visitor-triggered cron
- ⚠️ **Overdue Events** - Count of scheduled tasks that are past due
- 📈 **Total Executions** - Lifetime count of cron runs
- 🚢 **Shippo Sync Status** - Current automation status and next run time

**Access:** 
Automatically appears on your WordPress dashboard after plugin update.

---

### 2. Shippo Sync Page

**Location:** WooCommerce → Shippo Sync

**What it shows:**
- Complete cron health status at the top of the page
- Same information as dashboard widget but more prominent
- Color-coded warnings if attention is needed
- Action buttons for manual sync and diagnostics

---

### 3. Admin Notices

**Location:** Appears on Dashboard and Orders pages when needed

**When it shows:**
- Only displays if WP-Cron hasn't run in over 30 minutes
- Dismissible notice with link to Shippo Sync page
- Non-intrusive - won't show on every page

---

## 🔍 What Gets Monitored

### Health Indicators:

1. **Last Run Time**
   - Tracks when wp-cron.php was last executed
   - Updates automatically every time cron runs
   - Shows human-readable format ("5 minutes ago")

2. **Real Cron Detection**
   - Identifies if you're using a real server cron job
   - OR if relying on visitor-triggered WP-Cron
   - Marks as "Active" if cron runs consistently every 15-20 minutes

3. **Overdue Events**
   - Scans all scheduled WordPress tasks
   - Counts how many are past their scheduled time
   - Zero = healthy, Any number = needs attention

4. **Execution Tracking**
   - Counts total lifetime cron executions
   - Helps verify cron is running regularly
   - Useful for trending analysis

---

## ✅ Healthy Status

**Green indicators mean:**
- ✅ Cron ran in the last 15-20 minutes
- ✅ No overdue events
- ✅ Real server cron detected (or consistent execution)
- ✅ All systems operating normally

**What to do:** Nothing! Everything is working.

---

## ⚠️ Warning Status

**Yellow/Red indicators mean:**
- ❌ Cron hasn't run in 30+ minutes
- ❌ Multiple overdue events detected
- ❌ No execution history recorded
- ❌ Potential sync failures imminent

**What to do:**
1. Check your Bluehost cron job is still active
2. Verify the command is correct: `curl https://twintack.com/wp-cron.php > /dev/null 2>&1`
3. Check server error logs for issues
4. Use manual sync as temporary workaround

---

## 📈 Monitoring Best Practices

### Daily Checks:
- Glance at dashboard widget when logging into WordPress
- Verify "Last Run" timestamp is recent (< 20 minutes)
- Check "Overdue Events" shows zero

### Weekly Checks:
- Review "Total Executions" count is growing
- Verify Shippo orders are syncing consistently
- Check for any admin warning notices

### Monthly Checks:
- Review cron job in Bluehost control panel
- Verify settings haven't changed
- Check WordPress updates haven't disabled anything

---

## 🔧 Troubleshooting with Monitoring Data

### Problem: Dashboard shows "Last Run: Never recorded"

**Cause:** This is the first time monitoring is running
**Solution:** 
- Wait 15 minutes for first cron execution
- Check back - should show a timestamp
- If still "Never" after 30 minutes, check Bluehost cron job

---

### Problem: Last run shows "2 hours ago"

**Cause:** Cron job stopped running
**Solution:**
1. Check Bluehost → Cron Job Manager
2. Verify job is still listed and active
3. Click "Run Now" if available
4. Check command syntax is correct

---

### Problem: "5 overdue events" warning

**Cause:** Cron is running but not frequently enough
**Solution:**
1. Change cron interval from `0 * * * *` to `*/15 * * * *`
2. Or events are stuck - click "Spawn WP-Cron" in diagnostic tool
3. Wait 15 minutes and check if number decreases

---

### Problem: "Using visitor-triggered WP-Cron" warning

**Cause:** No real server cron job detected
**Solution:**
- This is okay for low-traffic sites
- But recommended to set up real cron for reliability
- Follow the Bluehost cron job setup instructions

---

## 📊 Understanding the Data

### Last Run Timestamp:
- **Green (< 20 min):** Excellent - cron running on schedule
- **Yellow (20-60 min):** Acceptable - might have skipped one run
- **Red (> 60 min):** Problem - cron not executing

### Overdue Events:
- **0 events:** Perfect - everything on schedule
- **1-3 events:** Minor - might be temporary delay
- **4+ events:** Problem - cron definitely not running

### Total Executions:
- Should increase by ~4 per hour (every 15 minutes)
- Should increase by ~96 per day
- Use for trend analysis - sudden stops indicate issues

---

## 🎓 Advanced Monitoring

### For Developers:

Access health data programmatically:
```php
if (class_exists('TwinTack_Cron_Health_Monitor')) {
    $monitor = TwinTack_Cron_Health_Monitor::get_instance();
    $health = $monitor->get_health_data();
    
    // Returns array with:
    // - is_healthy (bool)
    // - last_run_timestamp (int)
    // - overdue_count (int)
    // - warning_message (string)
    // etc.
}
```

---

## 📱 External Monitoring (Optional)

For extra reliability, consider external monitoring:

### UptimeRobot (Free):
1. Sign up at https://uptimerobot.com
2. Add HTTP monitor for: `https://twintack.com/wp-cron.php`
3. Set check interval: 5 minutes
4. Enable email alerts
5. This both monitors AND triggers cron

### Benefits:
- Email alerts if cron stops
- Doubles as cron trigger
- Independent of your hosting
- Free tier sufficient

---

## 🔔 Alert Thresholds

### Dashboard Widget:
- Shows always (passive monitoring)
- Updates in real-time when viewing

### Admin Notices:
- Only shows if cron hasn't run in 30+ minutes
- Only on Dashboard and Orders pages
- Dismissible but reappears if still failing

### No Email Alerts:
- Plugin doesn't send emails
- Use external service (UptimeRobot) for email alerts
- Or monitor dashboard widget daily

---

## 📝 Monitoring Checklist

**Daily:**
- [ ] Check dashboard widget shows green status
- [ ] Verify "Last Run" is within 20 minutes
- [ ] Check no admin warning notices

**Weekly:**
- [ ] Review "Total Executions" is growing steadily
- [ ] Check Shippo orders syncing to GoShippo
- [ ] Verify no overdue events accumulating

**Monthly:**
- [ ] Verify Bluehost cron job still active
- [ ] Review WordPress error logs
- [ ] Check plugin updates haven't broken anything

**After Any Issue:**
- [ ] Check dashboard widget first
- [ ] Review diagnostic tool for details
- [ ] Check Bluehost cron job settings
- [ ] Verify fix with dashboard widget

---

## 💡 Pro Tips

1. **Bookmark these pages:**
   - WordPress Dashboard (to see widget)
   - WooCommerce → Shippo Sync (detailed status)
   - Diagnostic tool (deep troubleshooting)

2. **Screenshot the healthy state:**
   - Take a screenshot when everything is green
   - Reference later to compare when issues arise

3. **Set a calendar reminder:**
   - Weekly check-in to review dashboard widget
   - Catches problems before they impact customers

4. **Monitor during quiet hours:**
   - Check late night/early morning
   - When traffic is lowest
   - Confirms real cron is working (not visitor-triggered)

---

## 🆘 When to Contact Support

Contact your developer or hosting support if:
- ❌ Dashboard shows "Never recorded" after 1 hour
- ❌ Overdue events stay above 5 even after triggering cron
- ❌ Cron job exists in Bluehost but not executing
- ❌ "Last Run" timestamp doesn't update for 2+ hours
- ❌ Warning notices appear consistently every day

---

## ✅ Summary

**The monitoring system:**
- ✅ Tracks WP-Cron health automatically
- ✅ Shows status on WordPress dashboard
- ✅ Warns you before sync failures happen
- ✅ Requires no manual checks (but recommended weekly)
- ✅ Works with your Bluehost cron job

**Your responsibility:**
1. Glance at dashboard widget periodically
2. Act on warnings when they appear
3. Verify Bluehost cron job stays active

**Result:**
- 🎯 Catch cron failures early
- 🎯 Prevent order sync issues
- 🎯 Peace of mind with visual confirmation

