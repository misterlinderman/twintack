# TwinTack Theme Directory Structure

This document provides a detailed overview of the TwinTack theme's directory structure and the purpose of each component.

## Project Root

The root of the project contains the following key directories:

- `themes/` - WordPress themes
  - `twintack2025/` - Main TwinTack theme (active)
- `plugins/` - WordPress plugins
- `claude notes/` - Reference files from Claude conversations for guiding feature expansions and layout modifications

## Root Directory

The root of the `twintack2025` theme contains the following key files:

- `style.css` - Main theme stylesheet with theme metadata
- `functions.php` - Core theme functionality and initialization
- `index.php` - Main template file
- `header.php` - Global header template
- `footer.php` - Global footer template
- `front-page.php` - Homepage template
- `page.php` - Default page template
- `single.php` - Single post template
- `archive.php` - Archive template
- `404.php` - 404 error page template
- `search.php` - Search results template
- `sidebar.php` - Sidebar template
- `comments.php` - Comments template
- `page-login.php` - Login page template

## Key Directories

### `/inc` - Core Functionality

The `inc` directory contains PHP classes and functions that power the theme's functionality:

- `/inc/core/` - Core theme functionality components
- `/inc/customizer/` - WordPress Customizer settings
- `/inc/hooks/` - Action and filter hooks
- `/inc/utilities/` - Helper functions and utilities
- `/inc/woocommerce/` - WooCommerce integration components
- `/inc/header/` - Header-specific functionality
- `/inc/marquee/` - Marquee component functionality
- `/inc/team/` - Team member functionality

Key files:
- `class-loader.php` - Autoloader for theme classes
- `class-theme-setup.php` - Theme initialization
- `class-header-configuration.php` - Header setup
- `class-menu-configuration.php` - Menu configuration
- `class-variation-display.php` - Product variation display
- `class-category-customizer.php` - Category customization
- `class-twintack-product-forms.php` - Custom product forms
- `class-twintack-role-pricing.php` - Role-based pricing
- `class-twintack-ajax-handlers.php` - AJAX request handlers
- `svg-support.php` - SVG file support
- `template-functions.php` - Template helper functions
- `template-tags.php` - Template tag functions

### `/template-parts` - Reusable Components

The `template-parts` directory contains modular template components:

- `/template-parts/header/` - Header components
- `/template-parts/woocommerce/` - WooCommerce template components
- `/template-parts/marquee/` - Marquee components
- `/template-parts/account/` - Account and login components

Key files:
- `content-flexible.php` - Flexible content template
- `content-team-member.php` - Team member display
- `content-technology.php` - Technology section display
- `content-partners.php` - Partners section display
- `content-twintack.php` - TwinTack-specific content
- `login-form.php` - Login form component
- `register-form.php` - Registration form component
- `user-type-selector.php` - User type selection component

### `/templates` - Page Templates

The `templates` directory contains custom page templates:

- `template-flexible.php` - Flexible content page template
- `template-story.php` - Story page template
- `template-team.php` - Team page template
- `template-how-to.php` - How-to page template
- `template-partners.php` - Partners page template
- `template-technology.php` - Technology page template
- `template-gripform.php` - Grip form page template
- `template-login.php` - Unified login page template
- `category-baseball.php` - Baseball category template
- `category-fishing.php` - Fishing category template

### `/js` - JavaScript Files

The `js` directory contains JavaScript files for client-side functionality:

- Navigation scripts
- Product gallery scripts
- Custom interactive elements

### `/css` - Stylesheets

The `css` directory contains CSS files for styling:

- Main stylesheets
- Component-specific styles
- Responsive styles

### `/dist` - Compiled Assets

The `dist` directory contains compiled and optimized assets:

- Minified JavaScript
- Compiled CSS
- Optimized images

### `/woocommerce` - WooCommerce Templates

The `woocommerce` directory contains custom WooCommerce template overrides for:

- Product pages
- Shop pages
- Cart and checkout
- Account pages

### `/languages` - Translations

The `languages` directory contains translation files for internationalization.

## File Naming Conventions

- **Class Files**: `class-{name}.php`
- **Template Parts**: `content-{type}.php`
- **Page Templates**: `template-{type}.php`
- **Category Templates**: `category-{name}.php`

## Dependency Structure

The theme follows a modular architecture with clear dependencies:

1. `functions.php` loads the class loader and core files
2. The class loader autoloads class files as needed
3. Template files include template parts as needed
4. JavaScript and CSS are enqueued through the WordPress API

## Development Guidelines

When adding new files to the theme:

1. Follow the established naming conventions
2. Place files in the appropriate directories
3. Register new functionality in `functions.php` when necessary
4. Document dependencies and purpose in file headers

---

*This document provides an overview of the TwinTack theme's directory structure. For more detailed information about specific components, please refer to the code comments within each file.* 