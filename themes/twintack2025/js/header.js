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
    const slides = document.querySelectorAll('.marquee-slide');
    let currentSlide = 0;

    function rotateSlides() {
        slides.forEach(slide => slide.classList.remove('active'));
        slides[currentSlide].classList.add('active');
        currentSlide = (currentSlide + 1) % slides.length;
    }

    if (slides.length > 0) {
        slides[0].classList.add('active');
        setInterval(rotateSlides, 5000);
    }
}); 