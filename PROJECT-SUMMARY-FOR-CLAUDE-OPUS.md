# TwinTack WordPress Project — State Summary for Claude Opus

**Document Purpose:** Establish the current state of the TwinTack WordPress project for future planning and development decisions. Share this document with Claude Opus in the browser to provide full context.

**Last Updated:** February 28, 2025

---

## 1. Project Overview

**TwinTack** is a WordPress-based e-commerce site for dual-purpose sports equipment, focusing on baseball and fishing gear. The brand sells innovative products that serve both sports. The site uses a custom theme and several custom plugins to support grip customization, wholesale/affiliate programs, manual order payments, and marketing features.

### Key Business Characteristics

- **Dual-category focus:** Baseball and fishing product lines
- **Custom grip business:** Customers design custom grips via forms; designs go through artwork → approval → production
- **User types:** Regular customers, wholesale buyers, affiliate partners
- **Third-party workflows:** Monday.com (design team), Make.com (automation), Klaviyo (email), Shippo (shipping), Stripe (payments)

---

## 2. Technology Stack

| Component | Version/Details |
|-----------|-----------------|
| **WordPress** | 5.8+ |
| **WooCommerce** | 10.2.2 |
| **Gravity Forms** | 2.9.18 |
| **Theme** | twintack2025 v1.0.0 |
| **PHP** | 7.4+ |
| **Frontend** | Bootstrap 5.3.2, SASS, jQuery |
| **Deployment** | FTP-only (no direct local server access) |

---

## 3. Custom Theme: twintack2025

**Path:** `themes/twintack2025/`

### Responsibilities

- Dual-category product display (baseball/fishing)
- Unified login system for customers, wholesale, affiliates
- Grip configurator page enhancements
- Role-based pricing integration
- Team member showcase, marquee, and custom templates
- Mail header enforcement (currently forces `support@twintack.com` for all emails — see pending email reorganization below)

### Key Files

- `functions.php` — Main loader, mail filters, class includes
- `inc/class-loader.php` — Class autoloading
- `/inc/` — Theme classes (header, marquee, team, role pricing, etc.)
- `/templates/` — Page templates
- `/template-parts/` — Reusable components
- `/woocommerce/` — WooCommerce overrides

### Documentation

- `themes/twintack2025/docs/` — PROJECT_OVERVIEW.md, FEATURES.md, DEVELOPMENT_GUIDE.md, DIRECTORY_STRUCTURE.md, and specialized guides

---

## 4. Custom Plugins

### TwinTack Grip Manager — v1.7.01

**Path:** `plugins/twintack-grip-manager/`

**Purpose:** Core grip design and order workflow.

**Features:**
- Custom post type `grip-design` for customer grip orders
- **Purchase-based creation:** Grip posts only created after payment (never before)
- Separate WordPress post status vs. artwork status
- Monday.com integration (asset IDs, mockup URLs)
- Customer dashboard display in WooCommerce My Account
- Customer feedback system (Approve / Request Changes)
- Volume pricing configuration
- Gravity Forms importer
- REST API for Make.com webhooks and Monday.com updates
- Email notifications (artwork ready, production started)

**Dependencies:** WooCommerce, Gravity Forms

**Important:** All grip posts must be linked to orders via `_grip_order_id`. Data workflow: Form → Cart → Payment → Post creation.

---

### TwinTack Manual Order Payments — v4.6.1

**Path:** `plugins/twintack-manual-order-payments/`

**Purpose:** Enables Stripe and other gateways for manually created orders.

**Features:**
- Stripe Checkout Sessions for customer self-service payments
- PDF invoice generation with wholesale pricing
- Shippo integration for shipping labels and sync
- Bulk invoice features
- CSV-based wholesale pricing
- WP-Cron-based Shippo sync
- HPOS (High-Performance Order Storage) compatible

**Integration:** Works with Grip Manager for invoice-based orders and grip design workflows.

---

### TwinTack Custom Grips — v1.2.0

**Path:** `plugins/twintack-custom-grips/`

**Purpose:** Frontend team dashboard for grip design management.

**Features:**
- Art and production team workflows
- Threaded messaging, mockup uploads
- Customer communication from the frontend
- Notifications (design messages, status updates)

**Note:** Uses `support@twintack.com` for some notifications; subject to email reorganization.

---

### TwinTack Marketing — v1.1.0

**Path:** `plugins/twintack-marketing/`

**Purpose:** Content marketing and promotional features.

**Features:**
- Product page video content (YouTube, Vimeo, embed)
- Website-wide announcement bar
- Product page color scheme toggle
- Featured products management and carousel
- Banner blocks system
- Landing page template support

---

### TwinTack Security Suite — v1.0.0

**Path:** `plugins/twintack-security/`

**Purpose:** Security logging, rate limiting, and admin notifications.

**Features:**
- Failed login and suspicious activity logging
- Admin notifications via `admin_email`
- reCAPTCHA integration considerations

---

### TwinTack Admin Console Fixes — v1.0.9

**Path:** `plugins/twintack-admin-console-fixes/`

**Purpose:** Admin UI fixes, Noun Project API integration, and console improvements.

---

### TwinTack Enhanced Shop Filters — v1.0.2

**Path:** `plugins/twintack-enhanced-shop-filters/`

**Purpose:** Enhanced filtering for shop/product archives.

---

### TwinTack How-To Videos — v1.1.0

**Path:** `plugins/twintack-how-to-videos/`

**Purpose:** How-to video content management and display.

---

### Third-Party / Supporting Plugins

- **WooCommerce** (10.2.2)
- **Gravity Forms** (2.9.18)
- **Invoice Gateway for WooCommerce** (1.1.4.3)
- **Advanced Google reCAPTCHA** (1.31)
- **Amazon for WooCommerce** (1.2.6)
- **Woo Variation Swatches** (and Pro)
- **twintack-order-debugging-suite**, **twintack-gravity-forms-diagnostic**, **twintack-product-save-bypass**, **twintack-registration-debug** — Diagnostic/development tools

---

## 5. Critical Data Structures

### Grip Design Post (`grip-design` CPT)

**Meta field prefix:** `_grip_` or `_twintack_`

**Workflow:** Form submission → Cart storage → Payment → Post creation (publish, linked to order)

**Key meta fields:**
- `_grip_order_id` — WooCommerce order ID
- `_grip_artwork_status` — Internal workflow status
- `_grip_customer_email`, `_grip_customer_name`, `_grip_team_name`
- `_grip_artwork_url`, `_grip_mockup_url`
- `_grip_monday_feedback` — Monday.com updates
- `_grip_customer_feedback`, `_grip_latest_customer_action` — Customer approval flow

---

## 6. Third-Party Integrations

| Service | Purpose |
|---------|---------|
| **Monday.com** | Design mockups, asset storage, design team workflow |
| **Make.com** | Webhooks for status updates, notifications, automation |
| **Klaviyo** | Email marketing |
| **Gravity Forms** | Contact forms, grip configurator forms |
| **Shippo** | Shipping labels, order sync |
| **Stripe** | Payments for manual orders |

---

## 7. Development Environment

- **Deployment:** FTP-only; no direct SSH or local WordPress server
- **Local tools:** Node.js, Composer, SASS for theme build
- **Debugging:** Standalone PHP debug scripts, WP_DEBUG, `error_log()`
- **Rules:** `.cursorrules` and `.cursor/rules/` — see `api-integrations.cursor-rules`, `data-workflow.cursor-rules`, `development-environment.cursor-rules`

---

## 8. Pending / In-Progress Work

### Email Notification Reorganization

**Document:** `claude notes/EMAIL-NOTIFICATION-REORGANIZATION-GUIDE.md`

**Goal:** Route order-related emails to `orders@twintack.com`, keep `support@twintack.com` for support. For customer-facing order emails, use “do not reply” with Reply-To: support@.

**Required changes (summary):**
1. Theme `functions.php`: Make mail filters conditional so WooCommerce emails use WC settings (orders@), not theme override
2. Add `woocommerce_email_headers` filter for Reply-To: support@ on customer emails
3. WooCommerce admin: Set From = orders@, New order/Cancelled/Failed recipients = orders@
4. **twintack-custom-grips:** Update From/Reply-To by recipient (customer vs. team)
5. **twintack-grip-manager:** Optional Reply-To: support@ for customer emails

**Current state:** Theme forces all emails to `support@twintack.com`. WooCommerce and other plugins are overridden.

---

## 9. Brand Guidelines

See **[BRAND-GUIDELINES-FOR-CLAUDE-OPUS.md](./BRAND-GUIDELINES-FOR-CLAUDE-OPUS.md)** for test build branding:
- Color palette (primary lime #a9ff00, dark, grays, white/light gray for alternation; sports theme colors removed)
- Fonts (Saira Condensed primary, Archivo secondary)
- Text styling (heading scale, weights, link styles)

---

## 10. Documentation Locations

| Location | Contents |
|----------|----------|
| `README.md` | High-level project overview |
| `themes/twintack2025/docs/` | Theme docs, features, development guide |
| `.cursor/rules/` | Cursor rules (API, data workflow, environment) |
| `.cursorrules` | Project-wide standards |
| `claude notes/` | Reference materials from past development sessions |
| `plugins/[plugin-name]/` | Plugin-specific README, CHANGELOG, guides |
| `BRAND-GUIDELINES-FOR-CLAUDE-OPUS.md` | Colors, fonts, typography |

---

## 11. Known Constraints and Conventions

1. **Purchase-based grip creation:** Never create grip posts before payment.
2. **Meta prefixes:** Use `_grip_` or `_twintack_` for custom fields.
3. **Function prefix:** Use `twintack_` for all custom functions.
4. **Security:** Sanitize input, escape output, use nonces, capability checks.
5. **FTP deployment:** No local WordPress; debug via scripts and logs.
6. **WordPress/WooCommerce:** Follow WP coding standards; use hooks, no core modifications.

---

## 12. Recommended Next Steps for Planning

1. **Email reorganization** — Implement changes in `EMAIL-NOTIFICATION-REORGANIZATION-GUIDE.md`.
2. **Documentation audit** — Ensure `CHANGELOG.md`, plugin READMEs, and theme docs reflect recent changes.
3. **Marketing initiative** — Review `MARKETING-INITIATIVE-PROGRESS.md` for remaining items and refinements.
4. **Integrations health** — Verify Monday.com, Make.com, Shippo, and Stripe flows.
5. **Performance** — Review caching, transients, and DB query patterns.
6. **Security** — Confirm reCAPTCHA, rate limiting, and API key handling.

---

## 13. Quick Reference — File Paths

```
Wordpress Files/
├── themes/twintack2025/          # Main theme
├── plugins/
│   ├── twintack-grip-manager/   # Grip design workflow
│   ├── twintack-manual-order-payments/
│   ├── twintack-custom-grips/
│   ├── twintack-marketing/
│   ├── twintack-security/
│   ├── twintack-admin-console-fixes/
│   └── [others]
├── claude notes/                 # Reference materials
├── .cursorrules
├── .cursor/rules/
├── DEVELOPMENT-SETUP-GUIDE.md
├── grip-design-post-structure.md
└── PROJECT-SUMMARY-FOR-CLAUDE-OPUS.md  # This document
```

---

*End of Project Summary*
