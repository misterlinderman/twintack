# TwinTack Development Environment Setup Guide

## Prerequisites Already Installed
- ✅ Xcode
- ✅ Git

## Required Installations

### 1. Homebrew (Package Manager)
```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

### 2. Node.js & npm
```bash
# Using Homebrew (recommended)
brew install node

# Or download from https://nodejs.org/ (LTS version)
# Verify installation:
node --version
npm --version
```

### 3. Composer (PHP Dependency Manager)
```bash
brew install composer

# Verify installation:
composer --version
```

### 4. Local WordPress Development Environment

#### Option A: Local by Flywheel (Recommended for beginners)
- Download from: https://localwp.com/
- Includes: PHP, MySQL, Nginx/Apache, SSL
- Easy WordPress site management
- Free

#### Option B: MAMP (Traditional approach)
- Download from: https://www.mamp.info/en/downloads/
- Includes: PHP, MySQL, Apache
- Free version available

#### Option C: Laravel Valet (Lightweight, for advanced users)
```bash
brew install php
brew install mysql
composer global require laravel/valet
valet install
```

### 5. Database Management Tool

#### Option A: TablePlus (Recommended)
- Download from: https://tableplus.com/
- Native macOS app
- Free tier available, paid for advanced features

#### Option B: Sequel Pro (Free)
- Download from: https://www.sequelpro.com/
- Open source, free

### 6. FTP/SFTP Client

#### Option A: Transmit (macOS Native)
- Download from: https://panic.com/transmit/
- Paid, but excellent macOS integration

#### Option B: FileZilla (Free)
- Download from: https://filezilla-project.org/
- Free, cross-platform

#### Option C: Cyberduck (Free)
- Download from: https://cyberduck.io/
- Free, macOS native

### 7. Image Optimization (Optional but recommended)
- **ImageOptim**: https://imageoptim.com/mac (Free)
- **Squash**: https://www.realmacsoftware.com/squash/ (Paid)

## Project Setup After Installation

### 1. Install Node.js Dependencies
```bash
cd "/Users/mattlinder/Dropbox/Development/Websites/TwinTack/Wordpress Files"
npm install

# For theme-specific dependencies
cd themes/twintack2025
npm install
```

### 2. Install PHP Dependencies
```bash
cd themes/twintack2025
composer install
```

### 3. Set Up SASS Compilation
```bash
cd themes/twintack2025

# Watch for changes (development)
npm run watch

# Compile once (production)
npm run compile:css
```

## Development Workflow

### Daily Development Tasks

1. **Start Local Environment** (if using Local/MAMP)
   - Launch Local or MAMP
   - Start services

2. **Watch SASS Files** (in theme directory)
   ```bash
   cd themes/twintack2025
   npm run watch
   ```

3. **Code Quality Checks**
   ```bash
   # PHP linting
   composer lint:php
   composer lint:wpcs
   
   # JavaScript linting
   npm run lint:js
   
   # SASS linting
   npm run lint:scss
   ```

4. **Deploy Changes**
   - Use FTP client to upload modified files
   - Or use VS Code/Cursor SFTP extension for sync

## Recommended VS Code/Cursor Extensions

### Essential Extensions
- **PHP Intelephense** - PHP language support
- **WordPress Snippets** - WordPress code snippets
- **SFTP** - FTP/SFTP file sync (for deployment)
- **SCSS IntelliSense** - SASS/SCSS support
- **ESLint** - JavaScript linting
- **PHP CodeSniffer** - PHP code quality

### Optional Extensions
- **GitLens** - Enhanced Git capabilities
- **Error Lens** - Inline error highlighting
- **Bracket Pair Colorizer** - Code readability
- **Auto Rename Tag** - HTML/XML tag management

## Environment-Specific Configuration

### PHP Version
- Minimum: PHP 7.4+
- Recommended: PHP 8.0+ or 8.1+
- Check with: `php --version`

### MySQL/MariaDB Version
- Minimum: MySQL 5.7+ or MariaDB 10.3+
- Check with: `mysql --version`

### Node.js Version
- Minimum: Node.js 14+
- Recommended: Node.js 18 LTS or 20 LTS
- Check with: `node --version`

## Troubleshooting

### Node.js Issues
```bash
# Clear npm cache
npm cache clean --force

# Reinstall dependencies
rm -rf node_modules package-lock.json
npm install
```

### Composer Issues
```bash
# Update Composer
composer self-update

# Clear Composer cache
composer clear-cache
```

### SASS Compilation Issues
```bash
# Rebuild node-sass
npm rebuild node-sass

# Or switch to Dart Sass (recommended)
npm uninstall node-sass
npm install sass --save-dev
```

## Additional Resources

- **WordPress Coding Standards**: https://developer.wordpress.org/coding-standards/
- **WordPress Developer Handbook**: https://developer.wordpress.org/
- **WooCommerce Developer Docs**: https://woocommerce.com/document/woocommerce-developer-resources/
- **SASS Documentation**: https://sass-lang.com/documentation

## Notes

- This project uses FTP-only deployment (no local server emulation)
- All WordPress testing happens on the live/staging server
- Debug scripts are created as standalone PHP files
- See `.cursor/rules/development-environment.cursor-rules` for detailed debugging patterns



