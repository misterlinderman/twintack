/**
 * Video Modal Functionality for Sport Pages
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('Video modal script loaded');
        
        // Find all play buttons
        const playButtons = $('.sport-how-to-videos .play-button');
        console.log('Found ' + playButtons.length + ' play buttons');
        
        if (playButtons.length === 0) {
            console.log('No play buttons found. Video modal initialization skipped.');
            return;
        }
        
        // Open modal
        playButtons.on('click', function(e) {
            e.preventDefault();
            
            // Get data attributes directly from HTML
            const videoUrl = $(this).attr('data-video-url');
            const videoTitle = $(this).attr('data-video-title');
            
            console.log('Opening video modal:', videoUrl, videoTitle);
            
            if (!videoUrl) {
                console.error('No video URL provided');
                return;
            }
            
            // Process Vimeo URL
            let embeddableUrl = videoUrl;
            
            // Convert regular Vimeo URLs to embed URLs
            if (videoUrl.includes('vimeo.com') && !videoUrl.includes('player.vimeo.com')) {
                // Extract the Vimeo ID
                const vimeoId = videoUrl.match(/vimeo\.com\/([0-9]+)/);
                if (vimeoId && vimeoId[1]) {
                    embeddableUrl = 'https://player.vimeo.com/video/' + vimeoId[1];
                    console.log('Converted to embeddable URL:', embeddableUrl);
                }
            }
            
            // Add parameters to embeddable URL
            embeddableUrl = embeddableUrl + (embeddableUrl.includes('?') ? '&' : '?') + 
                           'autoplay=1&title=0&byline=0&portrait=0';
            
            // Create a new modal directly in the body
            const modalHTML = `
                <div id="temp-video-modal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    z-index: 9999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background-color: rgba(0, 0, 0, 0.75);
                ">
                    <div style="
                        position: relative;
                        width: 90%;
                        max-width: 1000px;
                        margin: 0 auto;
                    ">
                        <div style="
                            background-color: #000;
                            border-radius: 12px;
                            overflow: hidden;
                            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                        ">
                            <button id="temp-close-modal" style="
                                position: absolute;
                                top: -40px;
                                right: 0;
                                background: none;
                                border: none;
                                color: white;
                                cursor: pointer;
                                z-index: 20;
                                padding: 10px;
                            ">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                            <div style="
                                padding: 16px 20px;
                                background-color: #212529;
                            ">
                                <h3 style="
                                    color: white;
                                    margin: 0;
                                    font-size: 18px;
                                ">${videoTitle || 'Video'}</h3>
                            </div>
                            <div style="
                                position: relative;
                                padding-top: 56.25%;
                                background-color: #000;
                                width: 100%;
                            ">
                                <iframe 
                                    src="${embeddableUrl}" 
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" 
                                    frameborder="0" 
                                    allow="autoplay; fullscreen; picture-in-picture" 
                                    allowfullscreen
                                    mozallowfullscreen
                                    webkitallowfullscreen
                                ></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Append to body
            $('body').append(modalHTML);
            
            // Prevent body scrolling
            $('body').css('overflow', 'hidden');
            
            console.log('Created new modal directly in DOM');
            
            // Close on button click
            $('#temp-close-modal').on('click', function() {
                $('#temp-video-modal').remove();
                $('body').css('overflow', '');
                console.log('Modal closed and removed');
            });
            
            // Close on background click
            $('#temp-video-modal').on('click', function(e) {
                if (e.target.id === 'temp-video-modal') {
                    $('#temp-video-modal').remove();
                    $('body').css('overflow', '');
                    console.log('Modal closed and removed via background click');
                }
            });
            
            // Close on ESC key
            $(document).on('keydown.videomodal', function(e) {
                if (e.key === 'Escape') {
                    $('#temp-video-modal').remove();
                    $('body').css('overflow', '');
                    $(document).off('keydown.videomodal');
                    console.log('Modal closed and removed via ESC key');
                }
            });
        });
    });
    
})(jQuery); 