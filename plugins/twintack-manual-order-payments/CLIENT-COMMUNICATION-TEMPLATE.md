# Client Communication Template

## Email/Message to Send to Your Client

---

### Subject: Shippo Sync Issue Identified & Fixed

Hi [Client Name],

I've identified the issue with orders not appearing in Shippo. Here's what happened and how we're fixing it:

#### What Went Wrong
Your WordPress site's automatic task scheduler (WP-Cron) stopped working about 9-10 hours ago. This scheduler is responsible for syncing orders from WooCommerce to Shippo every 4 hours. When it stopped, orders continued processing successfully in WooCommerce (and payments were captured in Stripe), but they weren't being sent to Shippo for fulfillment.

#### What I've Done
1. ✅ Created a diagnostic tool to monitor WP-Cron status
2. ✅ Documented the issue and solutions
3. ✅ Set up a manual sync tool you can use immediately

#### What You Need to Do RIGHT NOW
**Immediate fix** (takes 30 seconds):
1. Log into your WordPress admin
2. Go to **WooCommerce → Shippo Sync**
3. Click the button **"🔄 Simulate Shippo Webhooks"**

This will immediately sync all the orders from the last 9-10 hours to Shippo.

#### Current Status of Your Orders
- ✅ **Stripe Payments:** All successful
- ✅ **WooCommerce Orders:** Processing correctly
- ⚠️ **Shippo:** Not synced (but will be once you click the button above)

The orders are fine - they just need to be manually synced to Shippo this one time.

#### Long-Term Fix Needed
To prevent this from happening again, we need to set up a **real server cron job**. This requires contacting your hosting provider:

**What to ask your host:**
> "Can you please set up a cron job to run every 15 minutes with this command:
> 
> `*/15 * * * * curl https://[yourdomain.com]/wp-cron.php > /dev/null 2>&1`
> 
> This is to ensure our WordPress scheduled tasks run reliably."

Most hosting providers can do this in 5-10 minutes.

#### Temporary Workaround (Until Permanent Fix)
If you can't get the hosting provider to set up the cron job immediately, you can manually sync orders by visiting **WooCommerce → Shippo Sync** and clicking the sync button **2-3 times per day**. I recommend:
- Morning (9 AM)
- Afternoon (2 PM)
- Evening (6 PM)

Set a calendar reminder so you don't forget.

#### Monitoring Tools I've Created
1. **Shippo Sync Page:** WooCommerce → Shippo Sync
   - Shows how many orders need syncing
   - Shows when the last sync ran
   - Has a button to manually trigger sync

2. **Diagnostic Tool:** [yourdomain.com]/wp-content/plugins/twintack-manual-order-payments/test-wp-cron-status.php
   - Shows WP-Cron status
   - Lists all scheduled tasks
   - Highlights any issues

#### What This Costs You
**Immediate impact:**
- Orders from the last 9-10 hours need manual sync (one-time button click)
- No lost orders - all data is intact
- No lost payments - Stripe processed everything correctly

**Ongoing risk (if not fixed):**
- You'll need to manually sync 2-3 times per day
- Potential for delays in order fulfillment
- More manual work for you

**After permanent fix:**
- Everything back to automatic
- Syncs every 4 hours without manual intervention
- Can monitor to ensure it stays working

#### Next Steps
1. **You:** Click the sync button in WooCommerce → Shippo Sync (right now)
2. **You:** Contact hosting provider about setting up the cron job
3. **Me:** I'll monitor for 48 hours to ensure the fix is stable
4. **You:** Check the Shippo Sync page once a day for the next week to verify automation is working

#### Questions?
Let me know if you need any help or have questions. I'm here to support you through this.

The good news: No orders were lost, no payments failed, and we have a clear path to prevent this from happening again.

Best regards,
[Your Name]

---

## Follow-Up Message (24 Hours Later)

Hi [Client Name],

Quick check-in on the Shippo sync issue from yesterday:

1. Did you click the sync button? If so, did all orders appear in Shippo?
2. Were you able to contact your hosting provider about the cron job?
3. Are you still seeing orders in the "Processing" status that haven't synced?

If the hosting provider set up the cron job, we should verify it's working:
- Go to **WooCommerce → Shippo Sync**
- Check that "Automation Status" shows **✅ ENABLED**
- Verify "Next Run" time is within the next 4 hours

Let me know if you need any assistance!

Best,
[Your Name]

---

## If Client Says They Can't Contact Hosting Provider

Hi [Client Name],

No problem - I understand it can be difficult to coordinate with hosting providers. Here are two alternative solutions:

**Option 1: External Cron Service (Free)**
I can set up a free external service (like EasyCron or cron-job.org) to trigger your WP-Cron every 15 minutes. This doesn't require any hosting provider involvement.

- **Pros:** Free, reliable, no hosting provider needed
- **Cons:** Depends on external service (but these are stable services)
- **Time to set up:** 5 minutes

**Option 2: Manual Sync Routine**
Set up a daily routine where you (or a team member) click the sync button 2-3 times per day.

- **Pros:** No technical setup, full control
- **Cons:** Requires manual attention daily
- **Time required:** 30 seconds per day

Which would you prefer? I'm happy to set up either option for you.

Best,
[Your Name]

---

## If Client Has Technical Team

Hi [Client Name],

For your technical team, here are the specific details they'll need to implement the permanent fix:

**Option A: Real Cron Job (Recommended)**
Add this to server crontab:
```bash
*/15 * * * * curl https://[yourdomain.com]/wp-cron.php > /dev/null 2>&1
```

**Option B: Disable WP-Cron + Real Cron**
1. Add to `wp-config.php`:
   ```php
   define('DISABLE_WP_CRON', true);
   ```
2. Add cron job as in Option A

**Verification:**
- Visit: `/wp-content/plugins/twintack-manual-order-payments/test-wp-cron-status.php`
- Check no events show "OVERDUE"
- Verify "Shippo Sync" shows as "ENABLED"

**Documentation:**
- Full guide: `/wp-content/plugins/twintack-manual-order-payments/WP-CRON-SHIPPO-FIX-GUIDE.md`
- Quick ref: `/wp-content/plugins/twintack-manual-order-payments/QUICK-FIX-SHIPPO-SYNC.md`

Let me know if they have any questions!

Best,
[Your Name]

