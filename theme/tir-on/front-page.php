<?php get_header(); $product_data = t_product_data(); ?>
<main id="continut">
    <?php require __DIR__ . '/views/hero.php'; ?>
    <div class="qualities">
        <div class="wrap qualities-inner">
            <div><?= t_icon('photo', 23); ?><span>Fotografia ta, în relief</span></div>
            <div><?= t_icon('sun', 25); ?><span>Lumină LED alb-caldă</span></div>
            <div><?= t_icon('layers', 25); ?><span>Realizat prin imprimare 3D</span></div>
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
                <p class="section-label"><span></span>Obiectul de lumină</p>
                <h2><?= t_e($product_data['name']); ?></h2>
                <p class="feature-description"><?= t_e(get_theme_mod('tiron_feature_intro', 'Un disc sculptat în lumină, un suport cu linii fluide și o fotografie care înseamnă totul pentru tine. Acesta este Luna.')); ?></p>
                <dl class="feature-spec"><div><dt>Finisaj</dt><dd><?= t_e($product_data['finish']); ?></dd></div><div><dt>Diametru</dt><dd><?= t_e($product_data['diameter']); ?></dd></div></dl>
                <div class="feature-buy"><a class="button-dark" href="<?= esc_url(t_url('luna')); ?>">Descoperă Luna</a><span><?= t_e($product_data['price']); ?></span></div>
            </div>
        </div>
    </section>
    <?php require __DIR__ . '/views/home-story.php'; ?>
</main>
<?php get_footer(); ?>
