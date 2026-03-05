# TwinTack Brand Guidelines — Test Build

**Document Purpose:** Establish the TwinTack visual identity for the **test build** — a separate project from the production site. Share with Claude Opus when creating or modifying UI, templates, or marketing assets for the test build.

**Last Updated:** February 28, 2025  
**Scope:** Test build only (sports theme color system removed)

---

## 1. Color Palette

### Primary Brand Colors

| Name | Hex | CSS Variable | Usage |
|------|-----|--------------|-------|
| **Primary (Lime)** | `#a9ff00` | `--primary` | CTAs, links, accents, buttons, highlight |
| **Primary Dark** | `#8cc800` | `--primary-dark` | Hover states, emphasis |
| **Secondary (Dark)** | `#1a1a1a` | `--secondary` | Header, footer backgrounds, dark surfaces |
| **White** | `#ffffff` | `--light` | Light backgrounds, text on dark, alternation |
| **Text** | `#333333` | `--text` | Body copy, headings on light backgrounds |
| **Text Light** | `#666666` | `--text-light` | Secondary text, captions |

### Grays

| Name | Hex | CSS Variable | Usage |
|------|-----|--------------|-------|
| **Gray Light** | `#f8f9fa` | `--gray-100` | Light backgrounds, alternation with white |
| **Gray** | `#e9ecef` | `--gray-200` | Borders, dividers, subtle surfaces |
| **Gray Medium** | `#dee2e6` | `--gray-300` | Borders |
| **Gray Neutral** | `#f5f5f5` | `--gray` | Neutral surfaces |

**Alternation:** Use white (`#ffffff`) and light gray (`#f8f9fa`) to alternate sections or backgrounds for visual rhythm.

### Background & Surface (Dark UI)

| Name | Hex | CSS Variable | Usage |
|------|-----|--------------|-------|
| **Background** | `#000000` | `--color-background` | Dark page backgrounds |
| **Surface** | `#1a1a1a` | `--color-surface` | Cards, panels on dark |
| **Accent / Border** | `#333333` | `--color-accent`, `--color-border` | Borders, subtle elements |

### UI Feedback Colors

| Name | Hex | Usage |
|------|-----|-------|
| Success | `#22c55e` | Success states |
| Warning | `#f59e0b` | Warnings |
| Error | `#ef4444` | Errors, validation |

---

## 2. Typography

### Font Stack

| Role | Font | Fallback | CSS Variable | Usage |
|------|------|----------|--------------|-------|
| **Primary** | Saira Condensed | -apple-system, BlinkMacSystemFont, sans-serif | `--font-primary` | Body, headings, navigation, buttons site-wide |
| **Secondary** | Archivo | sans-serif | (direct) | Blog roll, some long-form content |
| **Component** | Inter | -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif | `--font-family` | Modular reset classes (`.tt-reset-typography`) only |

### Font Sources (Google Fonts)

- **Saira Condensed:** `https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@100;200;300;400;500;600;700;800;900&display=swap`
- **Archivo:** `https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,100..900;1,100..900&display=swap`

### Font Weights

| Name | Value | CSS Variable |
|------|-------|--------------|
| Regular | 400 | `--font-weight-regular` |
| Medium | 500 | `--font-weight-medium` |
| Bold | 700 | `--font-weight-bold` |

---

## 3. Text Styling

### Base Typography

| Property | Value | Notes |
|----------|-------|-------|
| Base font size | 16px | `--font-size-base` |
| Line height | 1.6 | `--line-height-base` |
| Body font weight | 400 (regular) | |
| Heading font weight | 700 (bold) | `--font-weight-bold` |
| Heading line height | 1.2 | |

### Heading Scale

| Element | Font Size | Notes |
|---------|-----------|-------|
| h1 | 2.5rem | |
| h2 | 2rem | |
| h3 | 1.75rem | |
| h4 | 1.5rem | |
| h5 | 1.25rem | |
| h6 | 1rem | |

All headings use `--font-primary` (Saira Condensed) and `--font-weight-bold`.

### Text Transform

- **Navigation:** `text-transform: uppercase`
- **Buttons:** `text-transform: uppercase` (common pattern)
- **Body:** default (none)

### Link Styling

- **On light backgrounds:** `color: var(--primary)` (#a9ff00)
- **On dark backgrounds:** `color: var(--primary)` (#a9ff00)
- **Hover:** `color: var(--primary-dark)` (#8cc800)
- **Transition:** `var(--transition-base)` (0.3s ease)

### Button Text

- Font: `var(--font-primary)` (Saira Condensed)
- Weight: 500–600 (medium to semi-bold)
- Often uppercase
- Primary button: `background: var(--primary)`; text: `var(--secondary)` or `#000` (for contrast on lime)
- Hover: `background: var(--primary-dark)`

---

## 4. Layout & Spacing

| Name | Value | CSS Variable |
|------|-------|--------------|
| XS | 0.25rem | `--spacing-xs` |
| SM | 0.5rem | `--spacing-sm` |
| MD | 1rem | `--spacing-md` |
| LG | 2rem | `--spacing-lg` |
| XL | 4rem | `--spacing-xl` |

### Border Radius

| Name | Value | CSS Variable |
|------|-------|--------------|
| Small | 4px | `--border-radius-sm` |
| Medium | 8px | `--border-radius-md` |
| Large | 12px | `--border-radius-lg` |

### Container

- Max width: 1200px  
- Padding: 1.25rem  

---

## 5. Context-Specific Usage

| Context | Primary Colors | Primary Font |
|---------|----------------|---------------|
| **General site** | Lime (#a9ff00), dark (#1a1a1a) | Saira Condensed |
| **Light sections** | White (#ffffff), light gray (#f8f9fa) for alternation | Saira Condensed |
| **Dark UI** (cart, forms, grip intro) | Black, dark gray, lime accent (#a9ff00) | Saira Condensed |
| **Blog** | Standard palette | Archivo |

---

## 6. Quick Reference for Implementation

### CSS Variables (Test Build)

```css
/* Primary palette */
--primary: #a9ff00;
--primary-dark: #8cc800;
--secondary: #1a1a1a;
--text: #333333;
--text-light: #666666;
--light: #ffffff;

/* Grays + alternation */
--gray-100: #f8f9fa;   /* Light gray — alternation with white */
--gray-200: #e9ecef;
--gray-300: #dee2e6;
--gray: #f5f5f5;

/* Dark UI */
--color-background: #000000;
--color-surface: #1a1a1a;
--color-accent: #333333;
--color-border: #333333;
--color-text: #ffffff;
--color-text-muted: #cccccc;
--color-text-dark: #000000;
--color-success: #22c55e;
--color-warning: #f59e0b;
--color-error: #ef4444;

/* Typography */
--font-primary: 'Saira Condensed', -apple-system, BlinkMacSystemFont, sans-serif;
--font-size-base: 16px;
--line-height-base: 1.6;
--font-weight-regular: 400;
--font-weight-medium: 500;
--font-weight-bold: 700;
```

---

## 7. Notes for AI / Claude Opus

- **This document applies to the test build only** — separate from the production TwinTack project.
- **Primary color** is `#a9ff00` (lime) — use for CTAs, links, accents, and highlights across light and dark backgrounds.
- **Sports theme color system is removed** — no baseball/fishing category colors.
- **Brand palette:** Primary lime (#a9ff00), dark (#1a1a1a), grays. Use **white** (#ffffff) and **light gray** (#f8f9fa) for section alternation.
- **Saira Condensed** is the main brand font; use for headings, body, navigation, and buttons.
- **Archivo** is reserved for blog and long-form content.

---

*End of Brand Guidelines*
