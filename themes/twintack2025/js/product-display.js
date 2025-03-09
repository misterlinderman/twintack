/**
 * TwinTack Product Display Scripts
 * 
 * Handles the horizontal scrolling functionality for product display
 * and filter modal functionality
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // DOM elements for product row
        const productRow = document.querySelector('.products-row');
        const scrollLeftButton = document.querySelector('.scroll-button-left');
        const scrollRightButton = document.querySelector('.scroll-button-right');
        
        // DOM elements for filter modal
        const filterButton = document.getElementById('filter-button');
        const filterModal = document.getElementById('filter-modal');
        const filterModalClose = document.getElementById('filter-modal-close');
        const filterModalBackdrop = document.getElementById('filter-modal-backdrop');
        
        // Enhanced scroll function with smooth behavior and looping
        function scrollProducts(direction) {
            if (!productRow) return;
            
            const scrollAmount = 300; // Adjust scroll amount as needed
            const currentScroll = productRow.scrollLeft;
            
            // Calculate target scroll position
            let targetScroll;
            if (direction === 'right') {
                targetScroll = currentScroll + scrollAmount;
            } else {
                targetScroll = currentScroll - scrollAmount;
            }
            
            // Implement looping behavior
            const maxScroll = productRow.scrollWidth - productRow.clientWidth;
            if (targetScroll < 0) {
                // Loop to the end when scrolling left from the beginning
                targetScroll = maxScroll;
            } else if (targetScroll > maxScroll) {
                // Loop to the beginning when scrolling right from the end
                targetScroll = 0;
            }
            
            // Smooth scroll to the target position
            productRow.scrollTo({
                left: targetScroll,
                behavior: 'smooth'
            });
        }
        
        // Handle scroll buttons
        if (scrollLeftButton && productRow) {
            scrollLeftButton.addEventListener('click', function() {
                scrollProducts('left');
            });
        }
        
        if (scrollRightButton && productRow) {
            scrollRightButton.addEventListener('click', function() {
                scrollProducts('right');
            });
        }
        
        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowRight') {
                scrollProducts('right');
            } else if (e.key === 'ArrowLeft') {
                scrollProducts('left');
            }
        });
        
        // Add touch swiping for mobile devices
        function addTouchSwiping() {
            if (!productRow) return;
            
            let touchStartX = 0;
            let touchEndX = 0;
            
            productRow.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });
            
            productRow.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            }, { passive: true });
            
            function handleSwipe() {
                const minSwipeDistance = 50;
                const swipeDistance = touchEndX - touchStartX;
                
                if (Math.abs(swipeDistance) > minSwipeDistance) {
                    if (swipeDistance > 0) {
                        // Swiped right, scroll left
                        scrollProducts('left');
                    } else {
                        // Swiped left, scroll right
                        scrollProducts('right');
                    }
                }
            }
        }
        
        // Initialize touch swiping
        if (productRow) {
            addTouchSwiping();
        }
        
        // Filter modal functionality
        if (filterButton && filterModal) {
            filterButton.addEventListener('click', function(e) {
                e.preventDefault();
                filterModal.classList.add('active');
                document.body.classList.add('overflow-hidden'); // Prevent scrolling when modal is open
            });
        }
        
        if (filterModalClose && filterModal) {
            filterModalClose.addEventListener('click', function(e) {
                e.preventDefault();
                filterModal.classList.remove('active');
                document.body.classList.remove('overflow-hidden');
            });
        }
        
        if (filterModalBackdrop && filterModal) {
            filterModalBackdrop.addEventListener('click', function(e) {
                e.preventDefault();
                filterModal.classList.remove('active');
                document.body.classList.remove('overflow-hidden');
            });
        }
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && filterModal && filterModal.classList.contains('active')) {
                filterModal.classList.remove('active');
                document.body.classList.remove('overflow-hidden');
            }
        });
        
        // Responsive handling
        function handleResponsive() {
            const isMobile = window.innerWidth < 768;
            
            // Hide scroll buttons on mobile
            if (scrollLeftButton && scrollRightButton) {
                if (isMobile) {
                    scrollLeftButton.style.display = 'none';
                    scrollRightButton.style.display = 'none';
                } else {
                    scrollLeftButton.style.display = 'block';
                    scrollRightButton.style.display = 'block';
                }
            }
        }
        
        // Run on page load
        handleResponsive();
        
        // Run on window resize
        window.addEventListener('resize', handleResponsive);
    });
})(); 