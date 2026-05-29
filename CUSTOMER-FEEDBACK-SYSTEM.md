# Customer Feedback System

> **This file has been superseded.** The canonical workflow documentation is maintained in the Custom Grips plugin:
>
> **[`plugins/twintack-custom-grips/WORKFLOW.md`](plugins/twintack-custom-grips/WORKFLOW.md)**
>
> Grip Manager–specific details (form intake, legacy API): [`plugins/twintack-grip-manager/CUSTOMER-FEEDBACK-SYSTEM.md`](plugins/twintack-grip-manager/CUSTOMER-FEEDBACK-SYSTEM.md)

---

## Quick Reference

Production workflow is **native WordPress** via **TwinTack Custom Grips**. Monday.com and Make.com are no longer used.

### Artwork status flow

```
artwork_pending → pending_review → customer_approved → in_production → shipped
                      ↕
            customer_requested_changes
```

- **Customer approves** → stays `customer_approved` until final purchase
- **Customer purchases** → WooCommerce Processing → `in_production`
- **Order ships** → WooCommerce Completed → `shipped`
- **Production Ready** (`approved_for_production`) was removed in Custom Grips v1.2.4

### Where things live

| Feature | Plugin |
|---------|--------|
| Team dashboard, mockups, messaging | `twintack-custom-grips` |
| My Custom Grips (customer) | `twintack-custom-grips` |
| Form intake, post creation, WC order bridge | `twintack-grip-manager` |
