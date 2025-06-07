/**
 * File navigation.js.
 *
 * Handles toggling the navigation menu for small screens and enables TAB key
 * navigation support for dropdown menus.
 */
( function() {
	const siteNavigation = document.getElementById( 'site-navigation' );

	// Return early if the navigation doesn't exist.
	if ( ! siteNavigation ) {
		return;
	}

	const button = siteNavigation.getElementsByTagName( 'button' )[ 0 ];

	// Return early if the button doesn't exist.
	if ( 'undefined' === typeof button ) {
		return;
	}

	const menu = siteNavigation.getElementsByTagName( 'ul' )[ 0 ];

	// Hide menu toggle button if menu is empty and return early.
	if ( 'undefined' === typeof menu ) {
		button.style.display = 'none';
		return;
	}

	if ( ! menu.classList.contains( 'nav-menu' ) ) {
		menu.classList.add( 'nav-menu' );
	}

	// Toggle the .toggled class and the aria-expanded value each time the button is clicked.
	button.addEventListener( 'click', function() {
		siteNavigation.classList.toggle( 'toggled' );

		if ( button.getAttribute( 'aria-expanded' ) === 'true' ) {
			button.setAttribute( 'aria-expanded', 'false' );
		} else {
			button.setAttribute( 'aria-expanded', 'true' );
		}
	} );

	// Remove the .toggled class and set aria-expanded to false when the user clicks outside the navigation.
	document.addEventListener( 'click', function( event ) {
		const isClickInside = siteNavigation.contains( event.target );

		if ( ! isClickInside ) {
			siteNavigation.classList.remove( 'toggled' );
			button.setAttribute( 'aria-expanded', 'false' );
		}
	} );

	// Get all the link elements within the menu.
	const links = menu.getElementsByTagName( 'a' );

	// Get all the link elements with children within the menu.
	const linksWithChildren = menu.querySelectorAll( '.menu-item-has-children > a, .page_item_has_children > a' );

	// Toggle focus each time a menu link is focused or blurred.
	for ( const link of links ) {
		link.addEventListener( 'focus', toggleFocus, true );
		link.addEventListener( 'blur', toggleFocus, true );
	}

	// Toggle focus each time a menu link with children receive a touch event.
	for ( const link of linksWithChildren ) {
		link.addEventListener( 'touchstart', toggleFocus, false );
	}

	/**
	 * Sets or removes .focus class on an element.
	 */
	function toggleFocus(event) {
		if ( event.type === 'focus' || event.type === 'blur' ) {
			let self = this;
			// Move up through the ancestors of the current link until we hit .nav-menu.
			while ( ! self.classList.contains( 'nav-menu' ) ) {
				// On li elements toggle the class .focus.
				if ( 'li' === self.tagName.toLowerCase() ) {
					self.classList.toggle( 'focus' );
				}
				self = self.parentNode;
			}
		}

		if ( event.type === 'touchstart' ) {
			const menuItem = this.parentNode;
			event.preventDefault();
			for ( const link of menuItem.parentNode.children ) {
				if ( menuItem !== link ) {
					link.classList.remove( 'focus' );
				}
			}
			menuItem.classList.toggle( 'focus' );
		}
	}
}() );

document.addEventListener('DOMContentLoaded', function() {
    const header = document.querySelector('.site-header');
    
    if (!header) return;

    // Set header height variable
    const setHeaderHeight = () => {
        const headerHeight = header.offsetHeight;
        document.documentElement.style.setProperty('--header-height', `${headerHeight}px`);
    };

    setHeaderHeight();
    window.addEventListener('resize', setHeaderHeight);
});

document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const mainNav = document.querySelector('.main-navigation');
    const header = document.querySelector('.site-header');

    // Return early if required elements don't exist
    if (!menuToggle || !mainNav || !header) return;

    // Set header height variable
    const setHeaderHeight = () => {
        document.documentElement.style.setProperty('--header-height', `${header.offsetHeight}px`);
    };

    setHeaderHeight();
    window.addEventListener('resize', setHeaderHeight);

    // Toggle menu
    menuToggle.addEventListener('click', () => {
        mainNav.classList.toggle('active');
        menuToggle.classList.toggle('active');
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!mainNav.contains(e.target) && !menuToggle.contains(e.target)) {
            mainNav.classList.remove('active');
            menuToggle.classList.remove('active');
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.site-header');
    if (!header) return;

    const navToggle = header.querySelector('.nav-toggle');
    const headerControls = header.querySelector('.header-controls');
    
    if (!navToggle) return;

    // Color contrast detection function
    function getContrastYIQ(r, g, b) {
        const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return yiq >= 128 ? 'light' : 'dark';
    }

    // Function to update navigation contrast
    function updateNavigationContrast() {
        if (!headerControls) return;

        const rect = headerControls.getBoundingClientRect();
        const x = rect.left + (rect.width / 2);
        const y = rect.top + (rect.height / 2);
        
        const elements = document.elementsFromPoint(x, y);
        let bgColor = 'rgba(0, 0, 0, 0)';
        
        // Find the first non-transparent background, excluding header elements
        for (const element of elements) {
            if (!header.contains(element)) {
                const computedStyle = window.getComputedStyle(element);
                bgColor = computedStyle.backgroundColor;
                
                if (bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== 'transparent') {
                    break;
                }
            }
        }

        const rgb = bgColor.match(/\d+/g);
        if (rgb && rgb.length >= 3) {
            const contrast = getContrastYIQ(
                parseInt(rgb[0]), 
                parseInt(rgb[1]), 
                parseInt(rgb[2])
            );
            header.dataset.contrast = contrast;
        } else {
            header.dataset.contrast = 'light';
        }
    }

    // Toggle navigation
    navToggle.addEventListener('click', () => {
        const currentState = header.dataset.navState || 'closed';
        const newState = currentState === 'closed' ? 'open' : 'closed';
        header.dataset.navState = newState;
        navToggle.setAttribute('aria-expanded', newState === 'open');
        
        if (newState === 'open') {
            header.dataset.contrast = 'dark';
        } else {
            // Force immediate contrast check when closing
            requestAnimationFrame(() => {
                updateNavigationContrast();
            });
        }
    });

    // Initial contrast check
    updateNavigationContrast();
});
