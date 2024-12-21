document.addEventListener('DOMContentLoaded', function() {
    // Header scroll effect
    const header = document.querySelector('.site-header');
    const marquee = document.querySelector('.site-marquee');
    const scrollThreshold = marquee ? marquee.offsetHeight : 100;

    window.addEventListener('scroll', () => {
        if (window.scrollY > scrollThreshold) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });

    // Marquee rotation
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
}); 