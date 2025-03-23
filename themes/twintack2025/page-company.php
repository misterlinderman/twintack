<?php
/**
 * Template Name: Company Page
 * 
 * Template for displaying the TwinTack company page with sections for Our Story,
 * flexible content, and team members.
 *
 * @package twintack2025
 */

get_header();
?>

<div class="page-wrapper company-page">
    <main class="main">
        <div class="container">
            <?php while (have_posts()) : the_post(); ?>
                
                <!-- Page Title Section - Updated to match Contact and Partners pages exactly -->
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>
                
                <!-- Anchor Navigation Below Title -->
                <nav class="anchor-navigation">
                    <ul>
                        <li><a href="#our-story">Our Story</a></li>
                        <li><a href="#technology">Technology</a></li>
                        <li><a href="#our-team">Our Team</a></li>
                    </ul>
                </nav>
                
                <!-- Our Story Section -->
                <section id="our-story" class="our-story-section">
                    <h2 class="section-title">Our Story</h2>
                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>
                </section>

                <!-- Technology Section -->
                <?php get_template_part('template-parts/content', 'company-technology'); ?>
                
                <!-- Display ALL Flexible Content Items -->
                <div class="flexible-content-wrapper">
                    <?php 
                    if (function_exists('have_rows') && have_rows('content_configurations')):
                        // Loop through all flexible content items
                        while (have_rows('content_configurations')): the_row();
                            // Get the current layout
                            $layout = get_row_layout();
                            
                            // Skip if it's a full_width_content with content_id = 'technology' 
                            // since we already display it above with the technology template part
                            if ($layout == 'full_width_content' && get_sub_field('content_id') == 'technology') {
                                continue;
                            }
                            
                            // For all other layouts, include the company-specific flexible content template
                            get_template_part('template-parts/content', 'company-flexible');
                        endwhile;
                    endif;
                    ?>
                </div>
                
                <!-- Team Members Section -->
                <?php get_template_part('template-parts/content', 'company-team'); ?>

            <?php endwhile; ?>
        </div>
    </main>
</div>

<?php
get_footer(); 