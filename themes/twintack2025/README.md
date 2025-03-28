# TwinTack WordPress Theme

[![Build Status](https://travis-ci.org/Automattic/_s.svg?branch=master)](https://travis-ci.org/Automattic/_s)

TwinTack is a specialized WordPress theme designed for e-commerce websites focusing on dual-purpose sports equipment, particularly baseball and fishing gear. Built on the Underscores starter theme, it provides a robust foundation for modern e-commerce with optimized performance and user experience.

## Features

* WooCommerce integration with custom product layouts
* Responsive design optimized for all devices
* Custom product filtering and search capabilities
* Unified login system for enhanced user experience
* Modern grid-based layout system
* Optimized performance and SEO features

## Requirements

* WordPress 6.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher
* WooCommerce 7.0 or higher (recommended)

## Installation

### Quick Start

1. Clone or download this repository
2. Install dependencies:
   ```sh
   $ composer install
   $ npm install
   ```
3. Build assets:
   ```sh
   $ npm run compile:css
   $ npm run compile:rtl
   ```

### Development

For development guidelines and best practices, please refer to the [Development Guide](./docs/DEVELOPMENT_GUIDE.md).

## Available CLI Commands

* `composer lint:wpcs` : Check PHP files against WordPress Coding Standards
* `composer lint:php` : Check PHP files for syntax errors
* `composer make-pot` : Generate .pot file for translations
* `npm run compile:css` : Compile SASS files to CSS
* `npm run compile:rtl` : Generate RTL stylesheet
* `npm run watch` : Watch SASS files for changes
* `npm run lint:scss` : Check SASS files against CSS Coding Standards
* `npm run lint:js` : Check JavaScript files against JS Coding Standards
* `npm run bundle` : Generate distribution .zip archive

## Documentation

Comprehensive documentation is available in the [docs](./docs) directory:

* [Project Overview](./docs/PROJECT_OVERVIEW.md)
* [Directory Structure](./docs/DIRECTORY_STRUCTURE.md)
* [Features](./docs/FEATURES.md)
* [Development Guide](./docs/DEVELOPMENT_GUIDE.md)
* [Unified Login System](./docs/UNIFIED_LOGIN_SYSTEM.md)

## Credits

* Based on Underscores https://underscores.me/, (C) 2012-2024 Automattic, Inc., [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)
* normalize.css https://necolas.github.io/normalize.css/, (C) 2012-2024 Nicolas Gallagher and Jonathan Neal, [MIT](https://opensource.org/licenses/MIT)
