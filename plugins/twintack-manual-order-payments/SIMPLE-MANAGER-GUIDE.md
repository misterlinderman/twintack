# TwinTack Simple Order Manager - v4.0.0

## 🎯 **Complete Manual Control System**

The Simple Order Manager gives you **full control** over order statuses, Shippo sync, and customer communications without any complex automation getting in the way.

## 📋 **How to Use**

### **1. Edit Any Order**
1. Go to **WooCommerce → Orders**
2. Click on any order to edit it
3. Scroll down to see the **"TwinTack Simple Order Management"** section

### **2. Quick Status Actions**

Three main buttons for common scenarios:

#### **✅ Mark as Paid & Ready to Ship**
- Sets WooCommerce status to `processing`
- Sets Shippo status to `Paid` 
- Marks as ready for fulfillment
- **Use for**: Regular orders where customer has paid

#### **📄 Set to Invoice (Payment Pending)**
- Sets WooCommerce status to `invoiced`
- Sets Shippo status to `Payment Pending`
- Marks as ready for immediate fulfillment
- **Use for**: Orders where you want to ship immediately but payment is pending

#### **📦 Mark as Shipped**
- Sets WooCommerce status to `completed`
- Sets Shippo status to `Shipped`
- **Use for**: Orders that have been shipped

### **3. Shippo Integration**

#### **🚢 Force Sync to Shippo**
- Manually sends the order to Shippo
- Check "Ready for Fulfillment" to override any payment holds
- **Use for**: When you want to ensure order appears in Shippo dashboard

### **4. Customer Communication**

#### **📧 Send Status Email**
- Sends WooCommerce order status email to customer
- **Use for**: Notify customer of order status changes

#### **💳 Send Payment Link**
- Sends payment link email to customer
- **Use for**: Invoice orders where customer needs to pay

## 🔧 **Advanced Control**

### **Ready for Fulfillment Checkbox**
- ✅ **Checked**: Order ships immediately (even if payment pending)
- ❌ **Unchecked**: Order waits for payment before shipping

This gives you granular control over whether Shippo should fulfill the order immediately or wait.

## 📊 **Status Display**

The control panel shows:
- **Current WooCommerce Status**: Processing, Invoiced, Completed, etc.
- **Current Shippo Status**: Paid, Payment Pending, Shipped, etc.

## 🚀 **Typical Workflows**

### **Manual Order (Customer Paying Later)**
1. Create order in WooCommerce admin
2. Click **"📄 Set to Invoice (Payment Pending)"**
3. Click **"🚢 Force Sync to Shippo"** with "Ready for Fulfillment" checked
4. Click **"💳 Send Payment Link"** to email customer
5. **Result**: Order appears in Shippo as ready to ship, customer gets payment link

### **Manual Order (Already Paid)**
1. Create order in WooCommerce admin  
2. Click **"✅ Mark as Paid & Ready to Ship"**
3. Click **"🚢 Force Sync to Shippo"**
4. Click **"📧 Send Status Email"** to notify customer
5. **Result**: Order appears in Shippo as paid and ready to ship

### **Regular Customer Order**
1. Customer completes checkout (goes to Processing automatically)
2. If needed, click **"🚢 Force Sync to Shippo"** 
3. When shipped, click **"📦 Mark as Shipped"**
4. **Result**: Standard e-commerce flow with manual override capability

## 🎛️ **Complete Control**

This system gives you:
- ✅ **No guesswork** - You decide exactly what happens
- ✅ **Immediate feedback** - Success/error messages for every action
- ✅ **Full visibility** - See current status of everything
- ✅ **Override capability** - Force any action when needed
- ✅ **Detailed logging** - All actions logged for debugging

## 🔍 **Debugging**

All actions are logged in:
- **WordPress Debug Log**: Check `/wp-content/debug.log`
- **Order Notes**: Each action adds a note to the order
- **TwinTack Debug Tools**: Use the debug page for additional diagnostics

## 🚨 **Important Notes**

1. **Manual Control**: This system requires manual action - nothing happens automatically
2. **One Order at a Time**: Each order needs individual attention
3. **Immediate Effect**: All actions take effect immediately
4. **No Undo**: Status changes are permanent (but you can change them again)
5. **Email Dependency**: Customer emails require valid email addresses in the order

## 📞 **Support**

If any action fails:
1. Check the error message displayed
2. Look in the WordPress debug log
3. Use the TwinTack Debug Tools page
4. All actions are logged with timestamps for troubleshooting

---

**This system prioritizes reliability and control over automation. Every action is intentional and immediate.**