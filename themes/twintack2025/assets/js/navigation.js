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