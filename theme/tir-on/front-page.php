<?php get_header(); $product_data = t_product_data(); ?>
<main id="continut">
    <?php require __DIR__ . '/views/hero.php'; ?>
    <div class="qualities">
        <div class="wrap qualities-inner">
            <div><?= t_icon('photo', 23); ?><span>Fotografia ta, în relief</span></div>
            <div><?= t_icon('sun', 25); ?><span>O amintire în lumină</span></div>
            <div><?= t_icon('spark', 25); ?><span>Creat special pentru tine</span></div>
        </div>
    </div>
    <section id="luna" class="feature">
        <div class="wrap feature-inner">
            <figure class="feature-visual">
                <picture>
                    <?php if (!get_theme_mod('tiron_image_feature')) : ?>
                        <source media="(max-width: 760px)" srcset="<?= esc_url(t_concept_asset('feature', true)); ?>">
                    <?php endif; ?>
                    <img src="<?= esc_url(t_scene('feature')); ?>" alt="Luna iluminată într-un living, cu finisaj espresso"
                        width="1122" height="1402" loading="lazy" decoding="async">
                </picture>
                <?php if (!get_theme_mod('tiron_image_feature')) : ?><figcaption>Vizualizare de concept</figcaption><?php endif; ?>
            </figure>
            <div class="feature-copy">
                <h2><?= t_e($product_data['name']); ?></h2>
                <p class="feature-subtitle"><?= t_e(t_home_copy('feature_subtitle')); ?></p>
                <p class="feature-description"><?= t_e(t_home_copy('feature_intro')); ?></p>
                <div class="feature-buy"><a class="button-dark" href="<?= esc_url(t_url('luna')); ?>">Descoperă Luna</a><span><?= t_e($product_data['price']); ?></span></div>
            </div>
        </div>
    </section>
    <?php require __DIR__ . '/views/home-story.php'; ?>
</main>
<?php get_footer(); ?>
