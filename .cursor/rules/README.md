# TwinTack Cursor Rules Documentation

This directory contains specialized cursor rules for the TwinTack WordPress build. These rules are designed to work together to provide comprehensive guidance for development.

## Rule Files Overview

### `base-cursor-rules-pre-1_0`
**Primary comprehensive rules file**
- WordPress development standards and best practices
- Theme and plugin architecture guidelines
- File structure conventions and naming
- Security, performance, and compatibility requirements
- Integration guidelines for third-party services
- Complete development workflow and testing requirements

### `not-a-live-server.mdc`
**Development environment constraints**
- FTP-only deployment environment
- No local server connectivity limitations
- Theme and plugin directory structure

### `api-integrations.cursor-rules`
**Third-party API integration patterns**
- Monday.com API endpoint specifications
- Make.com webhook integration workflows
- Klaviyo email marketing integration
- Gravity Forms processing patterns
- API security, authentication, and error handling
- Testing and debugging for external integrations

### `data-workflow.cursor-rules`
**Data structures and workflow patterns**
- Grip design post structure and meta fields
- Purchase-based grip creation workflow (CRITICAL)
- Volume pricing system configuration
- User role and access management
- Meta field naming conventions
- Status management and display logic
- WooCommerce integration patterns

### `development-environment.cursor-rules`
**Development and debugging guidelines**
- FTP-only environment debugging strategies
- Debug script creation and management
- WordPress debug configuration
- Logging and monitoring patterns
- Database debugging techniques
- Performance monitoring
- Error recovery procedures

## How These Rules Work Together

### For New Features
1. **Start with base rules** - Follow WordPress standards and architecture
2. **Check data-workflow rules** - Understand existing patterns and structures
3. **Reference API rules** - If feature involves external integrations
4. **Use development rules** - For debugging and testing in FTP environment

### For Bug Fixes
1. **Check development rules** - Use appropriate debugging techniques
2. **Reference data-workflow rules** - Understand data structure and workflow
3. **Review API rules** - If issue involves external integrations
4. **Follow base rules** - Maintain code quality and standards

### For Maintenance
1. **Base rules** - General maintenance and code quality
2. **Development rules** - Monitoring and performance optimization
3. **API rules** - Third-party service maintenance
4. **Data-workflow rules** - Data integrity and validation

## Critical Workflows to Understand

### Purchase-Based Grip Creation
**Reference**: `data-workflow.cursor-rules`
- Form submission → Cart storage → Payment → Post creation
- Never create grip posts before payment
- Always link posts to orders

### API Integration Flow
**Reference**: `api-integrations.cursor-rules`
- Monday.com status updates
- Make.com webhook processing
- Proper error handling and logging

### Amazon Tracking Sync (Shippo → WP-Lister)
**Reference**: `plugins/twintack-amazon-tracking-bridge/README.md`
- Shippo writes tracking to order notes only (no meta keys)
- Bridge plugin intercepts notes via `woocommerce_order_note_added`
- Writes `_wpla_tracking_number` before WP-Lister's completion handler
- Filter fallbacks parse notes at feed-build time as safety net

### Volume Pricing System
**Reference**: `data-workflow.cursor-rules`
- Configurable quantity rules and pricing tiers
- Role-based pricing integration
- Admin interface management

### User Role Management
**Reference**: `base-cursor-rules-pre-1_0` and `data-workflow.cursor-rules`
- Multiple user types: customers, wholesale, affiliates
- Role-based pricing and access
- Unified login system

## Development Best Practices

### Before Starting Work
1. Review relevant rule files
2. Check `claude notes/` directory for context
3. Understand feature in context of overall system
4. Review existing similar implementations

### During Development
1. Follow WordPress coding standards
2. Use proper error handling and logging
3. Implement security best practices
4. Create appropriate debug scripts for FTP environment

### After Development
1. Update relevant documentation
2. Create deployment notes if needed
3. Test thoroughly across user roles
4. Monitor logs for issues

## File Maintenance

### When to Update Rules
- New features that introduce patterns
- Changes to workflow or data structures
- New third-party integrations
- Performance or security improvements

### Rule File Priorities
1. **base-cursor-rules-pre-1_0** - Always applies
2. **not-a-live-server.mdc** - Environment constraint
3. **Specific rule files** - Apply based on feature area

## Common Pitfalls to Avoid

### Data Workflow Issues
- Creating grip posts before payment (use data-workflow rules)
- Incorrect meta field naming (follow conventions)
- Missing order linking

### API Integration Problems
- Not handling API failures gracefully
- Incorrect status code usage
- Missing authentication validation

### Development Environment Issues
- Trying to test WordPress functions locally
- Not creating proper debug scripts
- Forgetting FTP deployment requirements

## Support and Troubleshooting

### For Complex Issues
1. Check `claude notes/` for similar past issues
2. Review relevant rule files for patterns
3. Create debug scripts following development rules
4. Document solutions for future reference

### For New Team Members
1. Start with base rules for overview
2. Review data-workflow rules for system understanding
3. Study API rules for integration patterns
4. Practice with development rules for debugging

---

**Last Updated**: Current Version  
**Rule Files Count**: 5  
**Coverage**: Complete TwinTack WordPress build 