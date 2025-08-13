# TwinTack Grip Products Housekeeping Guide

## ✅ **Issue Fixed: Digital Products in Shippo**

The problem where digital deposit products were being sent to Shippo has been **RESOLVED**. 

### What was changed:
- Updated `class-shippo-integration.php` to filter out virtual/digital products
- Updated `class-shippo-api-client.php` to skip virtual/digital products  
- Added logic to skip entire orders if they contain only digital products
- Added detailed logging for transparency

### Why this occurred:
The Shippo integration was including ALL order items without checking if they were physical products that actually need shipping.

---

## 📦 **Grip Design Products - Keep These**

The grip design functionality uses exactly **2 products** that should be retained:

### 1. Custom Grip Design Deposit
- **Type**: Virtual/Digital Product
- **SKU**: `grip-design-deposit`
- **Purpose**: Initial design submission and $50 deposit payment
- **Price**: $50.00
- **Created**: Automatically by grip form handler if not exists
- **Shipping**: ❌ No (Virtual product - correctly excluded from Shippo now)

### 2. Custom Grip Product  
- **Type**: Physical Product
- **Product ID**: `1196`
- **Purpose**: Final purchase of actual grips after design approval
- **Price**: Variable pricing based on quantity
- **Shipping**: ✅ Yes (Physical product - will go to Shippo)

---

## 🗑️ **Products to Remove (Non-Grip Related)**

Based on the screenshots provided, these custom products appear to be unused by the grip system and can be safely removed:

### Candidates for Removal:
- **Custom Grip Product** (if duplicate of ID 1196)
- **Custom Design Fee** 
- **TT Pro Fishing Grip - Custom** (Draft)
- **TT Pro Bat Grip - Custom** (Private)
- **Custom Bat Grip** (Draft)

### ⚠️ **Before Removing Products:**

1. **Check for existing orders** containing these products
2. **Verify no other functionality** references these products  
3. **Backup your database** before deletion
4. **Test grip functionality** after removal

### SQL Query to Check Product Usage:
```sql
-- Check if products have been ordered
SELECT DISTINCT woi.product_id, p.post_title
FROM wp_woocommerce_order_items woi
JOIN wp_posts p ON woi.product_id = p.ID  
WHERE woi.product_id IN (SELECT ID FROM wp_posts WHERE post_title LIKE '%Custom%' AND post_type = 'product')
AND woi.order_item_type = 'line_item';
```

---

## 🔍 **Verification Steps**

After housekeeping, verify the grip system works correctly:

1. **Test Design Deposit Flow:**
   - Submit grip design form
   - Verify `grip-design-deposit` product gets added to cart
   - Complete purchase
   - Check that order does NOT appear in Shippo

2. **Test Final Grip Purchase:**
   - Approve a grip design  
   - Purchase final grips (Product ID 1196)
   - Verify order DOES appear in Shippo

3. **Check Logs:**
   - Monitor `/wp-content/debug.log` for Shippo filtering messages
   - Look for: "Skipping virtual/digital product" entries

---

## 📋 **Summary**

✅ **FIXED**: Digital deposit products no longer sent to Shippo  
✅ **IDENTIFIED**: 2 essential grip products to keep  
⚠️ **RECOMMENDED**: Remove 5 unused custom products after verification  
🔍 **NEXT**: Test the system and verify logs show proper filtering

The grip design workflow will now properly handle digital vs physical products in the Shippo integration.
