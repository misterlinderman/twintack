# TwinTack Custom Grips

Native WordPress plugin for the custom grip design production workflow.

## Documentation

- **[WORKFLOW.md](WORKFLOW.md)** — Canonical reference for artwork statuses, team/customer UI, and WooCommerce sync

## Dependencies

- WordPress 5.8+
- PHP 7.4+
- WooCommerce
- **TwinTack Grip Manager** — provides the `grip_design` post type and form intake

## Key URLs

| Audience | URL |
|----------|-----|
| Team dashboard | `/team-dashboard/` |
| Customer designs | `/my-account/my-custom-grips/` |

## Plugin Split

| Plugin | Responsibility |
|--------|----------------|
| **twintack-custom-grips** (this plugin) | Team dashboard, customer My Custom Grips, statuses, mockups, messaging, notifications |
| **twintack-grip-manager** | Post type, Gravity Forms intake, purchase-based creation, WooCommerce order bridge |
