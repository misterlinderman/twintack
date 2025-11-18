# CSV Export Wholesale Pricing Fix

## Issue Description
The CSV export functionality in the TwinTack Manual Order Payments plugin was exporting MSRP (Manufacturer's Suggested Retail Price) instead of wholesale pricing for customers with wholesale user roles. This was confusing customers who expected to see their actual wholesale pricing in the exported data.

## Root Cause
The CSV export was using `$item->get_total()` and `$item->get_subtotal()` which should reflect the actual prices charged to customers. However, if wholesale pricing wasn't properly applied during order creation, the export would show MSRP instead of wholesale prices.

## Solution Implemented

### 1. Enhanced CSV Export Logic
Modified the `prepare_order_row()` method in `class-bulk-invoice-manager.php` to:

- **Detect Wholesale Customers**: Check if the order's customer has a wholesale user role
- **Apply Wholesale Pricing**: For wholesale customers, calculate and use wholesale prices instead of regular prices
- **Recalculate Order Totals**: Update subtotal and total columns to reflect wholesale pricing
- **Add Visual Indicators**: Mark items with `[Wholesale]` indicator in the CSV

### 2. New Helper Method
Added `get_wholesale_price_for_product()` method that:

- Uses WooCommerce Wholesale Prices plugin functions to get wholesale pricing
- Supports both free and premium versions of the wholesale plugin
- Handles product variations correctly
- Includes error handling and logging

### 3. Key Features

#### Wholesale Customer Detection
```php
// Check if customer has wholesale role
$customer_id = $order->get_customer_id();
$is_wholesale_customer = false;
$wholesale_role = null;

if ($customer_id) {
    $user = get_user_by('id', $customer_id);
    if ($user) {
        $user_roles = $user->roles;
        foreach ($user_roles as $role) {
            if (strpos($role, 'wholesale') !== false) {
                $is_wholesale_customer = true;
                $wholesale_role = $role;
                break;
            }
        }
    }
}
```

#### Wholesale Price Application
```php
// For wholesale customers, try to get the wholesale price if not already applied
if ($is_wholesale_customer && class_exists('WWP_Wholesale_Prices')) {
    $effective_product_id = $variation_id ? $variation_id : $product_id;
    $wholesale_price = $this->get_wholesale_price_for_product($effective_product_id, $wholesale_role, $quantity);
    
    if ($wholesale_price && $wholesale_price > 0) {
        // Use wholesale price instead of regular price
        $wholesale_line_total = $wholesale_price * $quantity;
        $unit_price = $wholesale_price;
        $line_total = $wholesale_line_total;
    }
}
```

#### Order Total Recalculation
```php
// Recalculate order totals if wholesale pricing was applied
if ($is_wholesale_customer) {
    $wholesale_subtotal = 0;
    foreach ($order->get_items() as $item) {
        $wholesale_price = $this->get_wholesale_price_for_product($effective_product_id, $wholesale_role, $quantity);
        if ($wholesale_price && $wholesale_price > 0) {
            $wholesale_subtotal += $wholesale_price * $quantity;
        } else {
            $wholesale_subtotal += $item->get_total();
        }
    }
    
    if ($wholesale_subtotal > 0) {
        $order_subtotal = $wholesale_subtotal;
        // Apply tax rate to wholesale subtotal
        $tax_rate = $order->get_total_tax() / max($order->get_subtotal(), 1);
        $wholesale_tax = $wholesale_subtotal * $tax_rate;
        $order_total = $wholesale_subtotal + $wholesale_tax + $order->get_shipping_total();
    }
}
```

## CSV Output Changes

### Before Fix
- Items showed MSRP pricing
- Order totals reflected MSRP
- No indication of wholesale status

### After Fix
- Items show wholesale pricing for wholesale customers
- Order totals recalculated with wholesale pricing
- Items marked with `[Wholesale]` indicator
- Maintains regular pricing for non-wholesale customers

## Example CSV Output

### Regular Customer
```
Product Name (×2) - $15.00 each (Total: $30.00)
```

### Wholesale Customer
```
Product Name (×2) - $12.00 each (Total: $24.00) [Wholesale]
```

## Dependencies
- WooCommerce Wholesale Prices plugin (free or premium)
- Proper wholesale user roles configured
- Products with wholesale pricing set

## Testing
To test the fix:

1. Create a test order for a wholesale customer
2. Ensure the customer has a wholesale user role
3. Export the order to CSV
4. Verify that wholesale pricing is shown instead of MSRP
5. Check that `[Wholesale]` indicator appears for wholesale customers

## Logging
The fix includes comprehensive logging:
- Wholesale price calculations
- Order total recalculations
- Error handling for missing wholesale prices

Check WooCommerce → Status → Logs → twintack-manual-payments for detailed logs.

## Compatibility
- Works with both free and premium versions of WooCommerce Wholesale Prices
- Maintains backward compatibility with existing orders
- No impact on non-wholesale customers
- Preserves existing CSV structure and column headers

## Files Modified
- `plugins/twintack-manual-order-payments/includes/class-bulk-invoice-manager.php`

## Version
This fix is included in TwinTack Manual Order Payments plugin version 4.5.1+
