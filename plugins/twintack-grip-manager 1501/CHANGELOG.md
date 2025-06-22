# TwinTack Grip Manager Changelog

## Version 1.5.0 - 2024-12-19

### 🎉 Major Features Added

#### Separated Post Status from Artwork Status
- **Post Status** now functions normally (published by default for paid orders)
- **Artwork Status** is a separate field visible to customers in dashboard
- New artwork statuses: `artwork_pending`, `pending_review`, `artwork_approved`, `internal_review`, `in_production`, `shipped`
- Admin interface clearly separates the two status types

#### Monday.com Integration Enhancement
- Added `_grip_mockup_asset_id` field for storing Monday.com asset IDs
- Added `_grip_mockup_asset_url` field for direct asset URLs
- Enhanced admin interface with dedicated Monday.com fields
- Renamed "Design Team Feedback" to "Design Team Message" for clarity

#### Customer Dashboard Improvements
- Mockups from Monday.com now take priority over original artwork in display
- Added "Message from Design Team" section to show Monday.com feedback
- Improved artwork display with primary/secondary layout
- Enhanced status display with new artwork status labels
- Better visual hierarchy between original artwork and design mockups

#### REST API for Make.com Integration
- New endpoint: `POST /wp-json/twintack/v1/grip-design/{id}/monday`
- Secure API key authentication
- Allows updating Monday.com fields and artwork status
- Complete documentation in `MONDAY-API.md`

### 🎨 UI/UX Improvements
- Enhanced CSS styling for status labels with color coding
- Improved admin meta box layout and organization
- Better visual distinction between different content types
- Added secondary button styling for original artwork links
- Improved responsive design for mobile devices

### 🔧 Technical Improvements
- Updated REST API meta field registration
- Enhanced error handling and logging
- Improved admin notices for status changes
- Better field validation and sanitization
- Backward compatibility maintained for existing data

### 📝 Documentation
- Added comprehensive API documentation (`MONDAY-API.md`)
- Updated plugin description
- Added inline code comments for better maintainability

### 🔄 Migration Notes
- Existing grip designs will automatically get `artwork_pending` status
- All existing functionality remains unchanged
- Post status continues to work as before
- No data loss or breaking changes

---

## Previous Versions

### Version 1.4.21
- Purchase-based grip design creation
- Enhanced form support for color specifications
- Order linking and audit trail improvements

### Version 1.3.x
- Initial grip design post type
- Gravity Forms integration
- WooCommerce order integration
- Basic customer dashboard 