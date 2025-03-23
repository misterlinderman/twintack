document.addEventListener('DOMContentLoaded', function() {
    const marquee = document.querySelector('.site-marquee');
    
    // Marquee rotation only
    if (!marquee) return;

    const slides = marquee.querySelectorAll('.marquee-slide');
    const dots = marquee.querySelectorAll('.marquee-nav-dot');
    let currentSlide = 0;
    let interval;

    function showSlide(index) {
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        
        slides[index].classList.add('active');
        dots[index].classList.add('active');
        currentSlide = index;
        
        // Check contrast after slide change
        // Small delay to ensure background is fully loaded
        setTimeout(updateDotsContrast, 50);
    }

    function nextSlide() {
        const next = (currentSlide + 1) % slides.length;
        showSlide(next);
    }

    // Add click handlers to navigation dots
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            showSlide(index);
            resetInterval();
        });
    });

    function resetInterval() {
        clearInterval(interval);
        interval = setInterval(nextSlide, 5000);
    }

    // Start the rotation if there are multiple slides
    if (slides.length > 1) {
        resetInterval();
    }
    
    // Color contrast detection function
    function getContrastYIQ(r, g, b) {
        const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return yiq >= 128 ? 'light' : 'dark';
    }
    
    // Function to update dots contrast based on background
    function updateDotsContrast() {
        if (!marquee || !dots.length) return;
        
        const navigation = marquee.querySelector('.marquee-navigation');
        if (!navigation) return;
        
        // Get the current active slide - this ensures we check against the visible background
        const activeSlide = marquee.querySelector('.marquee-slide.active');
        if (!activeSlide) return;
        
        // Get computed background color of the active slide
        const computedStyle = window.getComputedStyle(activeSlide);
        let bgColor = computedStyle.backgroundColor;
        
        // If background color is transparent, try to get it from backgroundImage
        if (bgColor === 'rgba(0, 0, 0, 0)' || bgColor === 'transparent') {
            // For slides with background images, assume dark background
            if (computedStyle.backgroundImage && 
                computedStyle.backgroundImage !== 'none') {
                navigation.dataset.contrast = 'dark';
                return;
            }
            
            // Fallback to parent background
            const parentStyle = window.getComputedStyle(marquee);
            bgColor = parentStyle.backgroundColor;
        }
        
        const rgb = bgColor.match(/\d+/g);
        if (rgb && rgb.length >= 3) {
            const contrast = getContrastYIQ(
                parseInt(rgb[0]), 
                parseInt(rgb[1]), 
                parseInt(rgb[2])
            );
            navigation.dataset.contrast = contrast;
        } else {
            // Default to dark if we can't determine
            navigation.dataset.contrast = 'dark';
        }
        
        console.log('Background color:', bgColor);
        console.log('Contrast mode:', navigation.dataset.contrast);
    }
    
    // Initial contrast check - with slight delay to ensure all elements are rendered
    setTimeout(updateDotsContrast, 100);
    
    // Check contrast on window resize and scroll
    window.addEventListener('resize', updateDotsContrast);
    window.addEventListener('scroll', updateDotsContrast);
    
    // Also check when images load
    window.addEventListener('load', updateDotsContrast);
    
    // Check periodically for first few seconds to catch any delayed rendering
    let checkCount = 0;
    const intervalCheck = setInterval(() => {
        updateDotsContrast();
        checkCount++;
        if (checkCount >= 5) clearInterval(intervalCheck);
    }, 500);
}); 