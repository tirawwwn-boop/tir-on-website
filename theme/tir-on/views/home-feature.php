    <section id="luna" class="feature"<?= t_concept_asset('feature') ? ' data-concepts="v2"' : ''; ?>>
        <div class="wrap feature-inner">
            <figure class="feature-visual">
                <img src="<?= esc_url(t_scene('feature')); ?>" <?php if (!get_theme_mod('tiron_image_feature')) : ?>
                    srcset="<?= esc_url(t_concept_asset('feature', true) ?? t_asset('images/feature-mobile.webp')); ?> <?= t_concept_asset('feature', true) ? '640w' : '768w'; ?>, <?= esc_url(t_scene('feature')); ?> <?= t_concept_asset('feature') ? '1122w' : '1400w'; ?>"
                    sizes="(max-width: 700px) 90vw, 45vw"
                <?php endif; ?> loading="lazy" decoding="async" alt="TIR-ON Luna aprinsă într-un decor de interior" width="920" height="720">
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
