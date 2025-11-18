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

<div class="company-page-content">
    <main class="main">
        <?php while (have_posts()) : the_post(); ?>
            
            <!-- Page Title Section with styling matching partners page -->
            <section class="page-title-section">
                <div class="container">
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
                </div>
            </section>
            
            <!-- Our Story Section -->
            <section id="our-story" class="our-story-section">
                <div class="container">
                    <h2 class="section-title">Our Story</h2>
                    <div class="content-wrapper">
                        <?php the_content(); ?>
                    </div>
                </div>
            </section>

            <section id="technology" class="technology-section">
                <div class="container">
                    <h2 class="section-title">Technology</h2>
                </div>
            </section>

            <?php get_template_part('template-parts/content', 'company-technology'); ?>


            
            <!-- Team Members Section -->
            <!--section id="our-team" class="team-section">
                <div class="container">
                    <?php //get_template_part('template-parts/content', 'company-team'); ?>
                </div>
            </section-->

        <?php endwhile; ?>
    </main>
</div>

<style>
/* Company page specific styles */
.company-page-content {
    padding-bottom: 40px;
}

.page-title-section {
    padding: 40px 0 20px;
    background-color: #f8f9fa;
}

.entry-header {
    margin-bottom: 20px;
}

h1.entry-title {
    text-transform: uppercase;
    font-style: italic;
    letter-spacing: -2px;
    font-weight: 900;
    font-size: 4em;
    margin: 0;
    padding: 0;
}

.anchor-navigation {
    margin-bottom: 20px;
}

.anchor-navigation ul {
    display: flex;
    gap: 20px;
}

.anchor-navigation a {
    color: var(--primary);
    font-weight: bold;
}

.company-page-content .section-title {
    font-size: 3rem;
    margin-bottom: 30px;
    text-align: left;
    font-style: italic;
    letter-spacing: -0.05em;
}

.our-story-section, 
.team-section {
    padding: 40px 0;
}

.content-wrapper {
    max-width: 100%;
}

/* Match the homepage styling for technology section */
.technology-section .section-title {
    font-size: 3rem;
    margin-bottom: 30px;
    text-align: left;
    font-style: italic;
    letter-spacing: -0.05em;
}

.technology-section .content-wrapper {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

@media (min-width: 768px) {
    .technology-section .content-wrapper {
        flex-direction: row;
        align-items: center;
        gap: 40px;
    }
}

/* Ensure the full-width-content sections use correct layouts */
.full-width-content.layout-left .content-wrapper,
.technology-section.layout-left .content-wrapper {
    flex-direction: row;
}

@media (min-width: 768px) {
    .full-width-content.layout-left .content-wrapper,
    .technology-section.layout-left .content-wrapper {
        flex-direction: row;
    }
}

.full-width-content.layout-right .content-wrapper,
.technology-section.layout-right .content-wrapper {
    flex-direction: column;
}

@media (min-width: 768px) {
    .full-width-content.layout-right .content-wrapper,
    .technology-section.layout-right .content-wrapper {
        flex-direction: row-reverse;
    }
}

/* Media content styling */
.technology-media {
    flex: 0 0 100%;
}

@media (min-width: 768px) {
    .technology-media {
        flex: 0 0 45%;
    }
}

.technology-image {
    width: 100%;
    height: auto;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.technology-content {
    flex: 0 0 100%;
}

@media (min-width: 768px) {
    .technology-content {
        flex: 0 0 55%;
        padding: 0;
    }
}

.technology-text {
    font-size: 1rem;
    line-height: 1.6;
    color: #444;
}

@media (max-width: 768px) {
    h1.entry-title {
        font-size: 3em;
    }
    
    .anchor-navigation ul {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<?php
get_footer(); 