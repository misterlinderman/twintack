# Customer Feedback Webhook Setup for Make.com

## 🎯 Problem: Make.com "Watch Posts" Not Detecting Revisions

The "Watch Posts" and "Watch Posts Updated" modules in Make.com are designed to detect NEW posts, not subsequent updates to the same post with the same status. This causes customer feedback revisions to be ignored.

## ✅ Solution: Direct Webhook Approach

Instead of using "Watch Posts", use a **Custom Webhook** module that receives data directly from WordPress when customer feedback is submitted.

## 🔧 Make.com Scenario Setup

### **Step 1: Replace "Watch Posts" Module**
1. **Delete** the existing "Watch Posts" or "Watch Posts Updated" module
2. **Add** a "Custom Webhook" module as the first trigger
3. **Copy webhook URL** from the Custom Webhook module

### **Step 2: Webhook URL Configuration**
- **Webhook URL**: `https://hook.us2.make.com/gobx7ww7cnrrjr9cbelso4trai2x8t2t`
- **Method**: POST
- **Content-Type**: application/json

### **Step 3: Expected Webhook Data**
```json
{
  "grip_design_id": 1669,
  "grip_design_title": "Palmetto Spartans 250810",
  "customer_action": "request_changes",
  "customer_feedback": "Please make the logo bigger",
  "latest_customer_feedback": "Please make the logo bigger",
  "artwork_status": "customer_requested_changes",
  "customer_name": "Tyler Herring",
  "customer_email": "tyler@twintack.com",
  "team_name": "Palmetto Spartans",
  "quantity": 25,
  "monday_item_id": "7654321098",
  "timestamp": "2024-12-19T21:30:00+00:00",
  "revision_count": 3,
  "webhook_type": "customer_feedback"
}
```

## 🎯 Advantages of This Approach

### **Immediate Triggering**
- ✅ **Every customer feedback** triggers the webhook instantly
- ✅ **No polling delays** - immediate response
- ✅ **Guaranteed delivery** - WordPress sends webhook regardless of status

### **No Revision Issues**
- ✅ **Each feedback is unique** due to timestamp and revision_count
- ✅ **No "already seen" problems** - webhook is always fresh
- ✅ **Multiple revisions work** - customer can request changes multiple times

### **Better Data**
- ✅ **Complete context** - customer action, feedback text, all metadata
- ✅ **Structured payload** - no need to parse WordPress REST API responses
- ✅ **Revision tracking** - includes revision_count for filtering if needed

## 🚀 Implementation Steps

### **In WordPress** (Already Done in v1.6.06)
- ✅ Direct webhook URL hardcoded for customer feedback
- ✅ Webhook sends on every customer feedback submission
- ✅ Complete payload with all necessary data

### **In Make.com** (Your Next Steps)
1. **Create new scenario** or **modify existing one**
2. **Replace "Watch Posts"** with "Custom Webhook" module
3. **Use webhook URL**: `https://hook.us2.make.com/gobx7ww7cnrrjr9cbelso4trai2x8t2t`
4. **Configure Monday.com** steps to use webhook data instead of WordPress API data

## 🧪 Testing
1. **Upload TwinTack Grip Manager v1.6.06**
2. **Submit customer feedback** from My Account page
3. **Check Make.com** - webhook should trigger immediately
4. **Verify Monday.com** gets updated with customer feedback

## 🔄 Fallback Option
If you want to keep the existing "Watch Posts" as backup:
- **Primary**: Custom Webhook (instant, reliable)
- **Secondary**: Watch Posts (slower, as backup)
- Both can run in parallel without conflicts

## 💡 Why This Works Better
- **No WordPress polling** - Make.com doesn't need to check WordPress repeatedly
- **Event-driven** - only triggers when something actually happens
- **Reliable data** - WordPress controls exactly what data is sent
- **No API limits** - direct webhook bypasses WordPress REST API rate limits
