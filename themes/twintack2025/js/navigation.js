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
	function toggleFocus() {
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
    const scrollThreshold = 100;
    let lastScroll = 0;

    if (!header) return; // Safety check

    // Get header height and set it as a CSS variable
    const headerHeight = header.offsetHeight;
    document.documentElement.style.setProperty('--header-height', `${headerHeight}px`);

    // Scroll handler
    function handleScroll() {
        const currentScroll = window.pageYOffset;

        // Add/remove scrolled class based on scroll position
        if (currentScroll > scrollThreshold) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }

        // Optional: Hide/show header based on scroll direction
        if (currentScroll > lastScroll && currentScroll > headerHeight) {
            header.style.transform = 'translateY(-100%)'; // Hide on scroll down
        } else {
            header.style.transform = 'translateY(0)'; // Show on scroll up
        }

        lastScroll = currentScroll;
    }

    // Throttle scroll event for better performance
    let ticking = false;
    window.addEventListener('scroll', function() {
        if (!ticking) {
            window.requestAnimationFrame(function() {
                handleScroll();
                ticking = false;
            });
            ticking = true;
        }
    });

    // Recalculate header height on resize
    window.addEventListener('resize', function() {
        const newHeaderHeight = header.offsetHeight;
        document.documentElement.style.setProperty('--header-height', `${newHeaderHeight}px`);
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const mainNav = document.querySelector('.main-navigation');
    const header = document.querySelector('.site-header');

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
    const navToggle = header.querySelector('.nav-toggle');
    
    if (!header || !navToggle) return;

    // Toggle navigation
    navToggle.addEventListener('click', () => {
        const currentState = header.dataset.navState;
        const newState = currentState === 'closed' ? 'open' : 'closed';
        header.dataset.navState = newState;
        navToggle.setAttribute('aria-expanded', newState === 'open');
        
        // Prevent body scroll when nav is open
        document.body.style.overflow = newState === 'open' ? 'hidden' : '';
    });

    // Close navigation when clicking outside
    document.addEventListener('click', (e) => {
        if (!header.contains(e.target) && header.dataset.navState === 'open') {
            header.dataset.navState = 'closed';
            navToggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }
    });

    // Handle escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && header.dataset.navState === 'open') {
            header.dataset.navState = 'closed';
            navToggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }
    });
});
