# TwinTack Theme Development Guide

This document provides guidelines and best practices for developing and modifying the TwinTack WordPress theme.

## Development Environment

### Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.3+
- Node.js 14+ (for build tools)
- Composer (for PHP dependencies)

### Local Setup

1. Clone the repository
2. Install PHP dependencies: `composer install`
3. Install Node.js dependencies: `npm install`
4. Run build process: `npm run compile:css`

## Coding Standards

The TwinTack theme follows WordPress coding standards with some project-specific guidelines:

### PHP

- Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use class-based architecture for major components
- Prefix functions with `twintack_` to avoid conflicts
- Use meaningful function and variable names
- Document code with PHPDoc comments

### CSS/SASS

- Follow [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
- Use SASS for preprocessing
- Organize styles in a modular way
- Use BEM naming convention for classes
- Maintain responsive design principles

### JavaScript

- Follow [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- Use ES6+ features where appropriate
- Properly enqueue scripts through WordPress
- Document functions with JSDoc comments

## Theme Structure Guidelines

### Adding New Templates

1. Place page templates in the `/templates` directory with the naming convention `template-{name}.php`
2. Register the template in the theme setup if necessary
3. Document the template's purpose in the file header

### Adding New Components

1. Place reusable components in the `/template-parts` directory
2. Follow the naming convention `content-{type}.php`
3. Keep components modular and focused on a single responsibility

### Adding New Functionality

1. Place PHP classes in the appropriate subdirectory of `/inc`
2. Follow the naming convention `class-{name}.php`
3. Register hooks and filters in the class constructor
4. Document the class purpose and methods

## WooCommerce Customization

### Template Overrides

1. Place WooCommerce template overrides in the `/woocommerce` directory
2. Maintain the same directory structure as the original WooCommerce templates
3. Document changes from the original templates

### Custom Product Features

1. Use hooks rather than direct template modifications when possible
2. Register custom product fields through appropriate WooCommerce hooks
3. Document custom fields and their purpose

## Theme Customization

### Customizer Settings

1. Add new customizer settings in the appropriate file in `/inc/customizer`
2. Use sanitization callbacks for all settings
3. Group related settings in sections
4. Document the purpose of each setting

### Category-Specific Features

1. Extend the `class-category-customizer.php` for new category settings
2. Create category-specific templates as needed
3. Document category-specific features

## Performance Considerations

1. Optimize images before adding them to the theme
2. Minimize database queries in templates
3. Use transients for caching where appropriate
4. Enqueue only the necessary scripts and styles
5. Use conditional loading for scripts and styles

## Testing

Before submitting changes:

1. Test on multiple browsers (Chrome, Firefox, Safari, Edge)
2. Test on multiple devices (desktop, tablet, mobile)
3. Validate HTML and CSS
4. Check for PHP warnings and errors
5. Ensure WooCommerce compatibility

## Version Control

1. Use meaningful commit messages
2. Group related changes in a single commit
3. Update version numbers according to [Semantic Versioning](https://semver.org/)
4. Update the FEATURES.md document with any feature changes

### Common Git Commands

Here are some useful Git commands for managing your work:

#### Reset Working Directory
To completely reset your working directory to the last commit and remove all changes:
```bash
# Reset tracked files to last commit
git reset --hard HEAD

# Remove all untracked files and directories
git clean -fd
```

## Documentation

1. Update documentation when adding or modifying features
2. Document functions, classes, and methods with appropriate comments
3. Keep the README.md file up to date
4. Document any non-obvious code with inline comments

## Using Claude Notes for Development

The project includes a `claude notes` directory at the root level that contains valuable reference materials from AI conversations. These notes should be used as follows:

1. **Before starting development**:
   - Check the Claude notes directory for relevant discussions about the feature you're working on
   - Review design decisions and implementation guidance documented in these notes

2. **During development**:
   - Reference the notes for specific implementation details
   - Follow the patterns and approaches discussed in the notes for consistency

3. **After completing development**:
   - Consider adding new notes if you've had significant conversations about the feature
   - Update existing notes if your implementation differs from what was originally discussed

4. **For troubleshooting**:
   - Check if similar issues were addressed in previous conversations
   - Use the solutions documented in the notes as a starting point

5. **For feature extensions**:
   - Review notes about the original feature implementation
   - Ensure extensions align with the original design intent

For more detailed information about the Claude notes, refer to the [Claude Notes Reference](CLAUDE_NOTES.md) document.

---

*This document provides guidelines for developing and modifying the TwinTack theme. Following these guidelines will help maintain code quality and consistency throughout the project.* 