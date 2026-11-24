<?php defined('ABSPATH') || exit; ?>
<section class="editorial-hero" aria-labelledby="editorial-title">
    <div class="wrap editorial-hero-inner">
        <div class="editorial-heading">
            <h1 id="editorial-title"><?= t_e(t_home_copy('hero_title')); ?><br>
                <em><?= t_e(t_home_copy('hero_emphasis')); ?></em>
            </h1>
        </div>
        <figure class="editorial-portrait">
            <picture>
                <?php if (!get_theme_mod('tiron_image_hero')) : ?>
                    <source media="(max-width: 760px)" srcset="<?= esc_url(t_concept_asset('hero', true)); ?>">
                <?php endif; ?>
                <img src="<?= esc_url(t_scene('hero')); ?>" alt="Luna aprinsă, cu un portret în lumină caldă"
                    width="1122" height="1402" decoding="async" fetchpriority="high">
            </picture>
            <?php if (!get_theme_mod('tiron_image_hero')) : ?><figcaption>Vizualizare de concept</figcaption><?php endif; ?>
        </figure>
        <div class="editorial-intro">
            <p><?= t_e(t_home_copy('hero_intro')); ?></p>
            <div class="editorial-actions">
                <a class="button-gold" href="<?= esc_url(t_url('luna')); ?>"><?= t_icon('spark', 18); ?>Creează-ți Luna</a>
                <a class="button-outline" href="#transformarea">Vezi transformarea <?= t_icon('down', 17); ?></a>
            </div>
        </div>
    </div>
</section>
