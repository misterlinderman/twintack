<?php
/**
 * UGC video carousel.
 *
 * @package twintack2025
 * @var array $args Template args.
 */

if (!defined('ABSPATH')) {
    exit;
}

$meta       = isset($args['meta']) ? $args['meta'] : array();
$heading    = isset($meta['ugc_heading']) ? $meta['ugc_heading'] : (isset($meta['ugc_headline']) ? $meta['ugc_headline'] : '');
$subheading = isset($meta['ugc_subheadline']) ? $meta['ugc_subheadline'] : '';
$videos     = isset($meta['ugc_videos']) ? (array) $meta['ugc_videos'] : array();
$allowed    = array(
    'em'     => array(),
    'strong' => array(),
    'span'   => array('class' => array()),
    'br'     => array(),
);

$video_mime_types = array(
    'mp4'  => 'video/mp4',
    'm4v'  => 'video/mp4',
    'webm' => 'video/webm',
    'ogv'  => 'video/ogg',
    'ogg'  => 'video/ogg',
    'mov'  => 'video/quicktime',
);

$playable_videos = array();
foreach ($videos as $video) {
    if (!is_array($video)) {
        continue;
    }
    $url = isset($video['video']) ? $video['video'] : (isset($video['url']) ? $video['url'] : '');
    if ('' === $url) {
        continue;
    }
    $extension = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    $playable_videos[] = array(
        'url'   => $url,
        'thumb' => isset($video['poster']) ? $video['poster'] : (isset($video['thumb']) ? $video['thumb'] : ''),
        'mime'  => isset($video_mime_types[$extension]) ? $video_mime_types[$extension] : '',
    );
}

if ('' === $heading && '' === $subheading && empty($playable_videos)) {
    return;
}
?>
<section class="tt-v2-ugc" aria-labelledby="tt-v2-ugc-heading">
    <div class="container">
        <?php if ($heading) : ?>
            <h2 id="tt-v2-ugc-heading" class="tt-v2-ugc__heading"><?php echo wp_kses($heading, $allowed); ?></h2>
        <?php endif; ?>
        <?php if ($subheading) : ?>
            <p class="tt-v2-ugc__subheading"><?php echo esc_html($subheading); ?></p>
        <?php endif; ?>

        <?php if (!empty($playable_videos)) : ?>
        <div class="tt-v2-ugc__carousel" data-tt-v2-carousel>
            <button type="button" class="tt-v2-carousel__nav tt-v2-carousel__nav--prev" data-tt-v2-prev aria-label="<?php esc_attr_e('Previous', 'twintack2025'); ?>">
                <span aria-hidden="true">&lsaquo;</span>
            </button>

            <div class="tt-v2-ugc__track" data-tt-v2-track>
                <?php foreach ($playable_videos as $index => $video) :
                    $url      = $video['url'];
                    $thumb    = $video['thumb'];
                    $mime     = $video['mime'];
                    $is_file  = '' !== $mime;
                    ?>
                    <article class="tt-v2-ugc__slide" data-tt-v2-slide>
                        <button type="button" class="tt-v2-ugc__video-btn" data-tt-v2-video="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr(sprintf(__('Play video %d with sound', 'twintack2025'), $index + 1)); ?>">
                            <?php if ($is_file) : ?>
                                <video
                                    class="tt-v2-ugc__video"
                                    data-tt-v2-ugc-video
                                    muted
                                    loop
                                    autoplay
                                    playsinline
                                    preload="metadata"
                                    <?php echo $thumb ? 'poster="' . esc_url($thumb) . '"' : ''; ?>
                                >
                                    <source src="<?php echo esc_url($url); ?>" type="<?php echo esc_attr($mime); ?>">
                                </video>
                            <?php elseif ($thumb) : ?>
                                <img src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy">
                            <?php else : ?>
                                <span class="tt-v2-ugc__placeholder"></span>
                            <?php endif; ?>
                            <span class="tt-v2-ugc__play" aria-hidden="true"></span>
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>

            <button type="button" class="tt-v2-carousel__nav tt-v2-carousel__nav--next" data-tt-v2-next aria-label="<?php esc_attr_e('Next', 'twintack2025'); ?>">
                <span aria-hidden="true">&rsaquo;</span>
            </button>
        </div>
        <?php endif; ?>
    </div>
</section>
