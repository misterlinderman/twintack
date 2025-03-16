<?php
/**
 * Template part for displaying how-to videos
 *
 * @package TwinTack2025
 */
?>

<div class="product-how-to-videos">
    <?php
    $product_id = get_the_ID();
    $sport = twintack_get_product_sport($product_id);
    $videos = twintack_get_how_to_videos_by_sport($sport);
    
    if (empty($videos)) {
        return; // Don't show anything if no videos
    }
    ?>
    
    <div class="video-carousel-section py-12">
        <div class="max-w-6xl mx-auto px-4">
            <h2 class="text-2xl font-bold mb-8">How-To Videos</h2>
            
            <div class="video-carousel-container relative">
                <!-- Video Carousel Track -->
                <div class="video-carousel-track overflow-hidden">
                    <div class="video-carousel-slides flex transition-transform duration-500 ease-out" 
                         data-active-slide="0">
                        <?php foreach ($videos as $index => $video) : ?>
                            <div class="video-slide w-full flex-shrink-0 px-2" data-index="<?php echo esc_attr($index); ?>">
                                <!-- Video Thumbnail -->
                                <div class="video-thumbnail aspect-video rounded-lg overflow-hidden mx-auto max-w-2xl">
                                    <?php if (!empty($video['thumbnail'])) : ?>
                                        <img src="<?php echo esc_url($video['thumbnail']); ?>" alt="<?php echo esc_attr($video['title']); ?>" class="w-full h-full object-cover transition-opacity duration-300">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                            <span class="text-gray-400">No thumbnail</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($video['duration'])) : ?>
                                        <span class="video-duration"><?php echo esc_html($video['duration']); ?></span>
                                    <?php endif; ?>
                                    
                                    <!-- Play Button Overlay -->
                                    <a href="#" 
                                       class="play-button" 
                                       data-video-url="<?php echo esc_url($video['vimeo_url']); ?>"
                                       data-video-title="<?php echo esc_attr($video['title']); ?>"
                                       aria-label="Play video">
                                        <svg class="play-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="12" cy="12" r="10" fill="currentColor" fill-opacity="0.7"/>
                                            <path d="M15.5 12L10 15.5V8.5L15.5 12Z" fill="white"/>
                                        </svg>
                                    </a>
                                </div>
                                
                                <!-- Video Info -->
                                <div class="video-info mt-4 max-w-2xl mx-auto">
                                    <h3 class="font-medium text-lg"><?php echo esc_html($video['title']); ?></h3>
                                    <?php if (!empty($video['excerpt'])) : ?>
                                        <p class="text-gray-600 mt-2"><?php echo esc_html($video['excerpt']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Navigation Buttons -->
                <button type="button" class="carousel-prev" aria-label="Previous video">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                
                <button type="button" class="carousel-next" aria-label="Next video">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>

            </div>
        </div>
    </div>
</div>

<!-- Move modal outside the main container -->
<div id="video-modal" class="video-modal fixed inset-0" style="z-index: 99999;">
    <div class="video-modal-overlay absolute inset-0 bg-black bg-opacity-85 backdrop-blur"></div>
    <div class="video-modal-container relative z-10 w-11/12 max-w-4xl mx-auto transform transition-all">
        <div class="video-modal-content bg-black rounded-xl overflow-hidden shadow-2xl">
            <button id="close-video-modal" class="video-modal-close absolute -top-10 right-0" aria-label="Close video">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <div class="video-modal-header p-4 bg-gray-900">
                <h3 id="video-modal-title" class="text-white text-lg font-medium"></h3>
            </div>
            <div id="video-container" class="video-modal-player relative pt-[56.25%]"></div>
        </div>
    </div>
</div>
