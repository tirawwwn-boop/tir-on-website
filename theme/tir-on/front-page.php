<?php get_header(); $product_data = t_product_data(); ?>
<main id="continut">
    <?php require __DIR__ . '/views/hero.php'; ?>
    <div class="qualities">
        <div class="wrap qualities-inner">
            <div><?= t_icon('photo', 23); ?><span>Fotografia ta, în relief</span></div>
            <div><?= t_icon('sun', 25); ?><span>Lumină caldă</span></div>
            <div><?= t_icon('spark', 25); ?><span>Creată special pentru tine</span></div>
        </div>
    </div>
    <section id="luna" class="feature">
        <div class="wrap feature-inner">
            <figure class="feature-visual">
                <img src="<?= esc_url(t_scene('feature')); ?>"<?php if (!get_theme_mod('tiron_image_feature')) : ?> srcset="<?= esc_url(t_asset('images/feature-mobile.webp')); ?> 768w, <?= esc_url(t_scene('feature')); ?> 1400w" sizes="(max-width: 700px) 90vw, 45vw"<?php endif; ?> loading="lazy" decoding="async" alt="Compoziție cu Luna și fotografia de peisaj care inspiră litofanul" width="920" height="720">
                <?php if (!get_theme_mod('tiron_image_feature')) : ?><figcaption>Imagine de concept</figcaption><?php endif; ?>
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
