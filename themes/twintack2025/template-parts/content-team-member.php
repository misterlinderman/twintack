<?php
/**
 * Template part for displaying team members
 *
 * @package Twintack2025
 */

// Get ACF fields
$position = get_field('position');
$email = get_field('email');
$phone = get_field('phone');
$linkedin = get_field('linkedin_url');
?>

<div class="team-member">
    <div class="team-member__inner">
        <?php if (has_post_thumbnail()) : ?>
            <div class="team-member__media">
                <?php the_post_thumbnail('medium_large', ['class' => 'team-member__image']); ?>
            </div>
        <?php endif; ?>

        <div class="team-member__content">
            <?php if (get_the_title()) : ?>
                <h3 class="team-member__title"><?php echo esc_html(get_the_title()); ?></h3>
            <?php endif; ?>

            <?php if ($position) : ?>
                <div class="team-member__position">
                    <?php echo esc_html($position); ?>
                </div>
            <?php endif; ?>

            <?php if (get_the_content()) : ?>
                <div class="team-member__text">
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>

            <?php if ($email || $phone || $linkedin) : ?>
                <div class="team-member__links">
                    <?php if ($email) : ?>
                        <a href="mailto:<?php echo esc_attr($email); ?>" class="team-member__link team-member__link--email">
                            <span class="icon icon--email" aria-hidden="true"></span>
                            <span class="screen-reader-text"><?php esc_html_e('Email', 'twintack2025'); ?></span>
                            <?php echo esc_html($email); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($phone) : ?>
                        <a href="tel:<?php echo esc_attr($phone); ?>" class="team-member__link team-member__link--phone">
                            <span class="icon icon--phone" aria-hidden="true"></span>
                            <span class="screen-reader-text"><?php esc_html_e('Phone', 'twintack2025'); ?></span>
                            <?php echo esc_html($phone); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($linkedin) : ?>
                        <a href="<?php echo esc_url($linkedin); ?>" class="team-member__link team-member__link--linkedin" target="_blank" rel="noopener">
                            <span class="icon icon--linkedin" aria-hidden="true"></span>
                            <span class="screen-reader-text"><?php esc_html_e('LinkedIn Profile', 'twintack2025'); ?></span>
                            <?php esc_html_e('LinkedIn', 'twintack2025'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
