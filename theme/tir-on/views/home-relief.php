<?php defined('ABSPATH') || exit; ?>
<section id="transformarea" class="tl-transformation" aria-labelledby="tl-transform-title" data-relief>
    <div class="wrap tl-transform-grid">
        <div class="tl-transform-copy">
            <p class="tl-kicker">Transformarea</p>
            <h2 class="tl-title" id="tl-transform-title"><?= t_e(tiron_story_text('transformation_title')); ?></h2>
            <p class="tl-intro"><?= t_e(tiron_story_text('transformation_text')); ?></p>
            <div class="relief-controls" role="group" aria-label="Nivel de detaliu" hidden>
                <button type="button" data-relief-view="full" aria-controls="relief-image" aria-pressed="true">Vedere completă</button>
                <button type="button" data-relief-view="detail" aria-controls="relief-image" aria-pressed="false">Detaliu de relief</button>
            </div>
        </div>
        <figure class="relief-figure">
            <picture class="relief-image" id="relief-image" data-relief-image>
                <?php if (!get_theme_mod('tiron_image_relief-unlit')) : ?>
                    <source media="(max-width: 760px)" srcset="<?= esc_url(t_concept_asset('relief-unlit', true)); ?>">
                <?php endif; ?>
                <img src="<?= esc_url(t_scene('relief-unlit')); ?>" alt="Portretul în relief pe Luna, fără iluminare din spate"
                    width="1122" height="1402" loading="lazy" decoding="async">
            </picture>
            <figcaption>
                <span data-relief-caption aria-live="polite">Luna stinsă</span>
                <?php if (!get_theme_mod('tiron_image_relief-unlit')) : ?><span>Vizualizare de concept</span><?php endif; ?>
            </figcaption>
        </figure>
    </div>
</section>
