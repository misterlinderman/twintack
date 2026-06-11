<?php
/**
 * How TwinTack Works — tabbed video section.
 *
 * @package twintack2025
 * @var array $args Template args.
 */

if (!defined('ABSPATH')) {
    exit;
}

$meta   = isset($args['meta']) ? $args['meta'] : array();
$heading = isset($meta['video_tabs_heading']) ? $meta['video_tabs_heading'] : (isset($meta['video_tabs_title']) ? $meta['video_tabs_title'] : '');
$tabs   = isset($meta['video_tabs']) ? array_filter((array) $meta['video_tabs']) : array();

if (empty($tabs)) {
    return;
}

$tab_id_base = 'tt-v2-video-tab-' . get_the_ID();
?>
<section class="tt-v2-video-tabs" aria-labelledby="tt-v2-video-tabs-heading">
    <div class="container">
        <?php if ($heading) : ?>
            <h2 id="tt-v2-video-tabs-heading" class="tt-v2-video-tabs__heading"><?php echo esc_html($heading); ?></h2>
        <?php endif; ?>

        <div class="tt-v2-video-tabs__widget" data-tt-v2-tabs>
            <div class="tt-v2-video-tabs__nav" role="tablist">
                <?php foreach ($tabs as $index => $tab) :
                    $tab_id = $tab_id_base . '-' . $index;
                    $is_active = 0 === $index;
                    ?>
                    <button
                        type="button"
                        role="tab"
                        id="<?php echo esc_attr($tab_id); ?>"
                        class="tt-v2-video-tabs__tab<?php echo $is_active ? ' is-active' : ''; ?>"
                        aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                        aria-controls="<?php echo esc_attr($tab_id . '-panel'); ?>"
                        data-tt-v2-tab="<?php echo esc_attr((string) $index); ?>"
                    >
                        <?php
                        if (class_exists('TwinTack_Marketing_Video_Tabs')) {
                            echo wp_kses(
                                TwinTack_Marketing_Video_Tabs::get_tab_icon_svg((int) $index, isset($tab['label']) ? $tab['label'] : ''),
                                TwinTack_Marketing_Video_Tabs::get_tab_icon_allowed_html()
                            );
                        }
                        ?>
                        <span class="tt-v2-video-tabs__tab-label"><?php echo esc_html($tab['label']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="tt-v2-video-tabs__panels">
                <?php foreach ($tabs as $index => $tab) :
                    $tab_id    = $tab_id_base . '-' . $index;
                    $video_url = isset($tab['video']) ? $tab['video'] : '';
                    $poster    = isset($tab['poster']) ? $tab['poster'] : '';
                    $is_active = 0 === $index;
                    ?>
                    <div
                        role="tabpanel"
                        id="<?php echo esc_attr($tab_id . '-panel'); ?>"
                        class="tt-v2-video-tabs__panel<?php echo $is_active ? ' is-active' : ''; ?>"
                        aria-labelledby="<?php echo esc_attr($tab_id); ?>"
                        <?php echo $is_active ? '' : 'hidden'; ?>
                        data-tt-v2-panel="<?php echo esc_attr((string) $index); ?>"
                    >
                        <div class="tt-v2-video-tabs__panel-inner">
                            <?php if ($video_url) : ?>
                                <div class="tt-v2-video-tabs__media">
                                    <video
                                        class="tt-v2-video-tabs__video"
                                        controls
                                        playsinline
                                        preload="metadata"
                                        <?php echo $poster ? 'poster="' . esc_url($poster) . '"' : ''; ?>
                                        <?php echo $is_active ? '' : 'data-tt-v2-lazy-src="' . esc_url($video_url) . '"'; ?>
                                    >
                                        <?php if ($is_active) : ?>
                                            <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
                                        <?php endif; ?>
                                    </video>
                                </div>
                            <?php endif; ?>

                            <?php
                            $panel_tag    = isset($tab['tag']) ? $tab['tag'] : '';
                            $panel_title  = isset($tab['title']) ? $tab['title'] : '';
                            $panel_body   = isset($tab['body']) ? $tab['body'] : '';
                            $panel_caption = isset($tab['caption']) ? $tab['caption'] : '';
                            if ($panel_tag || $panel_title || $panel_body || $panel_caption) :
                                ?>
                                <div class="tt-v2-video-tabs__copy">
                                    <?php if ($panel_tag) : ?>
                                        <p class="tt-v2-video-tabs__tag"><?php echo esc_html($panel_tag); ?></p>
                                    <?php endif; ?>
                                    <?php if ($panel_title) : ?>
                                        <h3 class="tt-v2-video-tabs__panel-title"><?php echo esc_html($panel_title); ?></h3>
                                    <?php endif; ?>
                                    <?php if ($panel_body) : ?>
                                        <p class="tt-v2-video-tabs__body"><?php echo esc_html($panel_body); ?></p>
                                    <?php elseif ($panel_caption) : ?>
                                        <p class="tt-v2-video-tabs__body"><?php echo esc_html($panel_caption); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
