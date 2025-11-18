# PDF Invoice Wholesale Pricing Fix

## Issue Description
The PDF invoice generation was showing MSRP pricing ($21.99) in line items instead of wholesale pricing ($14.49) for wholesale customers, even though the order total was correct ($33.98). This created confusion for customers who expected to see their actual wholesale unit prices.

## Root Cause
The PDF invoice generator was using WooCommerce's standard pricing methods:
- `$order->get_item_subtotal($item, false, true)` - Gets MSRP unit price
- `$order->get_line_subtotal($item, false, true)` - Gets MSRP line total

These methods don't account for wholesale pricing that may have been applied at the order level.

## Solution Implemented

### 1. Enhanced Bulk Invoice Manager
Added a new public method `get_wholesale_item_pricing()` to the `TwinTack_Bulk_Invoice_Manager` class that:

- **Detects Wholesale Customers**: Checks if the order's customer has a wholesale user role
- **Applies Wholesale Pricing**: Uses the same logic as the CSV export to get wholesale prices
- **Fallback Calculation**: If wholesale pricing isn't found in product meta, calculates it based on order totals
- **Returns Structured Data**: Provides unit price, line total, and wholesale status

### 2. Updated PDF Invoice Generator
Modified the `TwinTack_PDF_Invoice_Generator` class to:

- **Use Wholesale Pricing**: Integrates with the bulk invoice manager to get correct pricing
- **Add Visual Indicators**: Shows "WHOLESALE" badge on wholesale items
- **Maintain Compatibility**: Falls back to original pricing if wholesale logic isn't available

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
// Get pricing (wholesale if applicable)
if ($bulk_manager) {
    $pricing = $bulk_manager->get_wholesale_item_pricing($order, $item);
    $unit_price = $pricing['unit_price'];
    $total_price = $pricing['line_total'];
} else {
    // Fallback to original pricing
    $unit_price = $order->get_item_subtotal($item, false, true);
    $total_price = $order->get_line_subtotal($item, false, true);
}
```

#### Visual Wholesale Indicators
```php
// Add wholesale indicator if applicable
if ($bulk_manager) {
    $pricing = $bulk_manager->get_wholesale_item_pricing($order, $item);
    if ($pricing['is_wholesale']) {
        $html .= ' <span style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;">WHOLESALE</span>';
    }
}
```

## Expected Results

### Before Fix
- **Unit Price**: $21.99 (MSRP)
- **Line Total**: $43.98 (2 × $21.99)
- **Order Total**: $33.98 (correct wholesale total)
- **Confusion**: Line items showed MSRP while total was wholesale

### After Fix
- **Unit Price**: $14.49 (wholesale price)
- **Line Total**: $28.98 (2 × $14.49)
- **Order Total**: $33.98 (matches line total + shipping)
- **Clear Pricing**: Line items match the actual charged amount

## Example Invoice Output

### Wholesale Customer Invoice
```
Item: TT Pro Bat Grip - Gradient - Lemonade WHOLESALE
Qty: 2
Unit Price: $14.49
Total: $28.98

Subtotal: $28.98
Shipping: $5.00
TOTAL: $33.98
```

### Regular Customer Invoice
```
Item: TT Pro Bat Grip - Gradient - Lemonade
Qty: 2
Unit Price: $21.99
Total: $43.98

Subtotal: $43.98
Shipping: $5.00
TOTAL: $48.98
```

## Dependencies
- TwinTack Manual Order Payments plugin
- WooCommerce Wholesale Prices plugin (free or premium)
- Proper wholesale user roles configured
- Products with wholesale pricing set

## Testing
To test the fix:

1. Create a test order for a wholesale customer
2. Ensure the customer has a wholesale user role
3. Generate a PDF invoice for the order
4. Verify that wholesale pricing is shown instead of MSRP
5. Check that "WHOLESALE" indicator appears for wholesale items

## Logging
The fix includes comprehensive logging:
- Wholesale customer detection
- Wholesale price calculations
- Fallback calculations
- Error handling

Check WooCommerce → Status → Logs → twintack-manual-payments for detailed logs.

## Compatibility
- Works with both free and premium versions of WooCommerce Wholesale Prices
- Maintains backward compatibility with existing orders
- No impact on non-wholesale customers
- Preserves existing PDF structure and formatting
- HTML fallback when PDF library not available

## Files Modified
- `plugins/twintack-manual-order-payments/includes/class-bulk-invoice-manager.php`
- `plugins/twintack-manual-order-payments/includes/class-pdf-invoice-generator.php`

## Version
This fix is included in TwinTack Manual Order Payments plugin version 4.5.1+

## Related Fixes
- CSV Export Wholesale Pricing Fix (same underlying logic)
- Both CSV and PDF exports now use consistent wholesale pricing
