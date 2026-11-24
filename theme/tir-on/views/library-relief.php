<?php
defined('ABSPATH') || exit;

// Each scene stands on its own until a matched photograph and lithophane are available.
$relief_id = absint(get_theme_mod('tiron_image_relief-unlit'));
$relief_custom = $relief_id ? wp_get_attachment_image_url($relief_id, 'large') : false;
$relief_concept = t_concept_asset('relief-unlit');
$relief_mobile = t_concept_asset('relief-unlit', true)
    ?? ($relief_concept ?: t_asset('images/relief-unlit-mobile.webp'));
$relief_alt = $relief_custom ? get_post_meta($relief_id, '_wp_attachment_image_alt', true) : '';
?>
<section id="transformarea" class="tl-transformation" aria-labelledby="tl-transform-title">
    <div class="wrap tl-transform-grid">
        <div class="tl-transform-copy">
            <p class="tl-kicker">Transformarea</p>
            <h2 class="tl-title" id="tl-transform-title"><?= t_e(tiron_story_text('transformation_title')); ?></h2>
            <p class="tl-intro"><?= t_e(tiron_story_text('transformation_text')); ?></p>
        </div>
        <figure class="library-relief"<?= $relief_concept && !$relief_custom ? ' data-portrait' : ''; ?>>
            <picture>
                <?php if (!$relief_custom) : ?>
                    <source media="(max-width: 700px)" srcset="<?= esc_url($relief_mobile); ?>">
                <?php endif; ?>
                <img src="<?= esc_url($relief_custom ?: t_scene('relief-unlit')); ?>"
                    alt="<?= esc_attr($relief_alt ?: 'Relieful alb al litofanului Luna, fără iluminare din spate'); ?>"
                    width="<?= $relief_concept ? 1024 : 1200; ?>" height="<?= $relief_concept ? 1280 : 800; ?>"
                    loading="lazy" decoding="async">
            </picture>
            <figcaption>Relieful fără iluminare<?php if (!$relief_custom) : ?> <span>Vizualizare de concept</span><?php endif; ?></figcaption>
        </figure>
    </div>
</section>
