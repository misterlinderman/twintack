<?php
/**
 * Template part for displaying the product carousel on sport pages
 *
 * @package TwinTack2025
 */

// Define the product images
$product_images = array(
    array(
        'url' => 'https://meo.efe.mybluehost.me/wp-content/uploads/2025/02/BG1S-09006-Splatter-Black-Mint-rs1.png',
        'title' => 'Splatter Black Mint',
    ),
    array(
        'url' => 'https://meo.efe.mybluehost.me/wp-content/uploads/2025/02/BG1S-03018-Gradient-Flamethrower-rs1.png',
        'title' => 'Gradient Flamethrower',
    ),
);

// Get the arrow SVG path
$arrow_svg_path = get_template_directory_uri() . '/grip-carousel-arrow-1.svg';
?>

<div class="sport-product-carousel">
    <div class="container">
        <h2 class="section-title">Featured Products</h2>
        
        <div class="product-carousel-wrapper">
            <!-- Carousel controls -->
            <button class="carousel-control carousel-prev" aria-label="Previous product">
                <img src="<?php echo esc_url($arrow_svg_path); ?>" alt="Previous" class="carousel-arrow prev-arrow">
            </button>
            
            <div class="carousel-container">
                <div class="carousel-track">
                    <?php foreach ($product_images as $index => $image) : ?>
                        <div class="carousel-slide" data-index="<?php echo esc_attr($index); ?>">
                            <div class="product-card">
                                <div class="product-image">
                                    <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['title']); ?>">
                                </div>
                                <div class="product-details">
                                    <h3 class="product-title"><?php echo esc_html($image['title']); ?></h3>
                                    <a href="#" class="product-link">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button class="carousel-control carousel-next" aria-label="Next product">
                <img src="<?php echo esc_url($arrow_svg_path); ?>" alt="Next" class="carousel-arrow next-arrow">
            </button>
        </div>
    </div>
</div>

<style>
/* Sport Product Carousel Styles */
.sport-product-carousel {
    padding: 40px 0;
    background-color: #f8f9fa;
    overflow: hidden;
}

.section-title {
    font-size: 28px;
    margin-bottom: 24px;
    text-align: center;
}

.product-carousel-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 60px;
}

.carousel-container {
    width: 100%;
    overflow: hidden;
}

.carousel-container::-webkit-scrollbar {
    display: none;
}

.carousel-track {
    display: flex;
    gap: 20px;
    transition: transform 0.5s ease;
    padding: 20px 0;
}

.carousel-slide {
    flex: 0 0 calc(33.333% - 20px);
    min-width: 280px;
}

.product-card {
    background-color: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.product-image img {
    width: 100%;
    height: auto;
    display: block;
}

.product-details {
    padding: 20px;
    text-align: center;
}

.product-title {
    font-size: 18px;
    margin: 0 0 10px;
}

.product-link {
    display: inline-block;
    color: #3498db;
    text-decoration: none;
    font-weight: 600;
    margin-top: 10px;
    transition: color 0.3s ease;
}

.product-link:hover {
    color: #2980b9;
}

.carousel-control {
    background: none;
    border: none;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
}

.carousel-prev {
    left: 10px;
}

.carousel-next {
    right: 10px;
}

.carousel-arrow {
    width: 24px;
    height: 24px;
}

.next-arrow {
    transform: scaleX(-1);
}

/* Responsive styles */
@media (max-width: 768px) {
    .carousel-slide {
        flex: 0 0 calc(50% - 20px);
    }
}

@media (max-width: 576px) {
    .carousel-slide {
        flex: 0 0 calc(100% - 20px);
    }
    
    .product-carousel-wrapper {
        padding: 0 40px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const track = document.querySelector('.carousel-track');
    const slides = document.querySelectorAll('.carousel-slide');
    const prevButton = document.querySelector('.carousel-prev');
    const nextButton = document.querySelector('.carousel-next');
    
    if (!track || slides.length === 0) return;
    
    let currentIndex = 0;
    let allSlides = Array.from(slides); // Create array from NodeList
    
    // Duplicate slides before measuring
    duplicateSlides();
    
    // Get all slides after duplication
    const allSlidesAfterDuplication = document.querySelectorAll('.carousel-slide');
    const slideWidth = allSlidesAfterDuplication[0].offsetWidth + 20; // slide width + gap
    
    function updateSlidePosition() {
        track.style.transform = `translateX(-${currentIndex * slideWidth}px)`;
        
        // Always enable both buttons - we have enough duplicated slides
        prevButton.disabled = currentIndex === 0;
        prevButton.style.opacity = currentIndex === 0 ? '0.5' : '1';
        
        // Use the total number of slides for maximum scrolling
        const visibleSlides = Math.floor(track.parentElement.offsetWidth / slideWidth);
        const maxScrollableIndex = allSlidesAfterDuplication.length - visibleSlides;
        nextButton.disabled = currentIndex >= maxScrollableIndex;
        nextButton.style.opacity = currentIndex >= maxScrollableIndex ? '0.5' : '1';
        
        console.log('Current index:', currentIndex, 'Max index:', maxScrollableIndex);
    }
    
    prevButton.addEventListener('click', function() {
        if (currentIndex > 0) {
            currentIndex--;
            updateSlidePosition();
        }
    });
    
    nextButton.addEventListener('click', function() {
        const visibleSlides = Math.floor(track.parentElement.offsetWidth / slideWidth);
        const maxScrollableIndex = allSlidesAfterDuplication.length - visibleSlides;
        
        if (currentIndex < maxScrollableIndex) {
            currentIndex++;
            updateSlidePosition();
        }
    });
    
    // Duplicate the slides to create an infinite loop
    function duplicateSlides() {
        // Always duplicate at least 3 copies of each slide to ensure enough content
        for (let i = 0; i < 3; i++) {
            allSlides.forEach(slide => {
                const clone = slide.cloneNode(true);
                track.appendChild(clone);
            });
        }
        
        // Update allSlides array after duplication
        allSlides = Array.from(document.querySelectorAll('.carousel-slide'));
    }
    
    // Initialize
    updateSlidePosition();
    
    // Recalculate on window resize
    window.addEventListener('resize', function() {
        // Get the new slide width
        const newSlideWidth = allSlides[0].offsetWidth + 20;
        
        // Update the slideWidth variable
        const slideWidthChange = newSlideWidth / slideWidth;
        
        // Adjust current index proportionally to maintain position
        currentIndex = Math.round(currentIndex * slideWidthChange);
        
        // Update position with new dimensions
        updateSlidePosition();
    });
});
</script> 