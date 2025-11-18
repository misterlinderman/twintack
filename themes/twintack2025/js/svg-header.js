(function($) {
    'use strict';

    class SVGHeaderManager {
        constructor() {
            this.headerContainer = $('.twintack-svg-header');
            this.init();
        }

        init() {
            if (!this.headerContainer.length) return;
            
            this.setupParallaxLayers();
            this.setupFloatingLayers();
            this.setupPulseLayers();
            this.bindEvents();
        }

        setupParallaxLayers() {
            this.parallaxLayers = this.headerContainer.find('.animation-parallax');
        }

        setupFloatingLayers() {
            this.floatingLayers = this.headerContainer.find('.animation-floating');
        }

        setupPulseLayers() {
            this.pulseLayers = this.headerContainer.find('.animation-pulse');
        }

        bindEvents() {
            $(window).on('scroll', this.handleScroll.bind(this));
            $(window).on('mousemove', this.handleMouseMove.bind(this));
            $(window).on('resize', this.handleResize.bind(this));
        }

        handleScroll() {
            if (!this.parallaxLayers.length) return;

            const scrollTop = $(window).scrollTop();
            
            this.parallaxLayers.each((index, layer) => {
                const $layer = $(layer);
                const speed = 0.1 * (index + 1);
                const yPos = -(scrollTop * speed);
                
                $layer.css('transform', `translateY(${yPos}px)`);
            });
        }

        handleMouseMove(e) {
            if (!this.floatingLayers.length) return;

            const { clientX, clientY } = e;
            const centerX = window.innerWidth / 2;
            const centerY = window.innerHeight / 2;

            this.floatingLayers.each((index, layer) => {
                const $layer = $(layer);
                const speed = 0.02 * (index + 1);
                const xPos = (clientX - centerX) * speed;
                const yPos = (clientY - centerY) * speed;

                $layer.css('transform', `translate(${xPos}px, ${yPos}px)`);
            });
        }

        handleResize() {
            // Add any resize-specific handling here
            this.handleScroll();
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new SVGHeaderManager();
    });

})(jQuery); 