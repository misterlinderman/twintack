# Custom Grip Introduction Page Template

## Overview
This template provides a dedicated page for introducing customers to the custom grip ordering process. It presents a clear, step-by-step journey that guides users through the entire workflow.

## Files Created
- `page-custom-grip-intro.php` - Main template file
- `css/components/_custom-grip-intro.css` - Styling for the template
- Updated `css/main.css` to include the new CSS file

## How to Use

### 1. Create a New Page
1. Go to WordPress Admin > Pages > Add New
2. Set the page title (e.g., "Custom Grip Process" or "How to Order Custom Grips")
3. In the Page Attributes box, select "Custom Grip Introduction" as the template
4. Add any introductory content in the page editor (optional)
5. Publish the page

### 2. Template Features

#### Process Steps Section
The template includes 5 clear steps:
1. **Create Account** - Links to registration/login
2. **Design Grip** - Links to the custom grip form
3. **Pay Design Fee** - Explains the $50 setup fee
4. **Review Mockups** - Details the art team review process
5. **Purchase Grips** - Final purchase and production timeline

#### Information Cards
Three key information cards highlight:
- **Design Fee**: $50 one-time setup fee
- **Quantity Breaks**: Grips sold in quantities of 25
- **Lead Time**: 2-week production timeline

#### Smart User Detection
- Shows "Create Account" button for non-logged-in users
- Shows "Account Ready" status for logged-in users
- Adjusts call-to-action based on login status

### 3. Customization Options

#### Content Customization
- Edit the page content in WordPress admin to add introductory text
- Modify step descriptions directly in the template file
- Update links and URLs as needed

#### Styling Customization
- All styles are in `_custom-grip-intro.css`
- Uses existing theme CSS variables for consistency
- Fully responsive design included

#### Color Scheme
The template uses the existing theme color variables:
- `--primary`: Orange (#ff4800) for buttons and accents
- `--secondary`: Dark gray (#1a1a1a) for headings
- `--text`: Dark gray (#333333) for body text
- `--light`: White (#ffffff) for backgrounds

### 4. Integration Points

#### WordPress Functions Used
- `is_user_logged_in()` - Detects user login status
- `wp_registration_url()` - Links to registration page
- `esc_url()` - Sanitizes URLs for security
- `the_content()` - Displays page content

#### Theme Integration
- Uses existing header and footer
- Follows theme's CSS variable system
- Maintains consistent typography and spacing
- Responsive design matches theme breakpoints

### 5. SEO and Accessibility

#### SEO Features
- Proper heading hierarchy (H1, H2, H3)
- Semantic HTML structure
- Meta-friendly content structure

#### Accessibility Features
- Proper ARIA labels
- Keyboard navigation support
- High contrast color scheme
- Screen reader friendly structure

### 6. Browser Support
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile responsive design
- Graceful degradation for older browsers

## Maintenance Notes
- Update URLs if custom grip form location changes
- Modify pricing information if fees change
- Update lead times if production schedules change
- Test responsive design after any content changes

## Future Enhancements
Consider adding:
- Progress indicators for logged-in users
- Integration with actual grip design status
- Dynamic content based on user's existing designs
- Video tutorials or examples
- Customer testimonials or examples
