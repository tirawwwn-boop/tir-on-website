<?php
$scenes = [
    ['key' => 'hero', 'mobile' => 'hero-mobile', 'name' => 'Lumină aprinsă', 'alt' => 'Luna iluminată, cu un peisaj montan în relief, pe un suport espresso'],
    ['key' => 'hero-alt', 'mobile' => 'hero-alt-mobile', 'name' => 'În ambient', 'alt' => 'Luna aprinsă într-un decor de interior'],
    ['key' => 'detail', 'mobile' => 'detail-mobile', 'name' => 'Detaliu de design', 'alt' => 'Detaliu al finisajului și al reliefului iluminat'],
];
?>
<section class="hero" aria-label="Luna" aria-roledescription="carusel" data-hero>
    <div class="hero-media">
        <?php foreach ($scenes as $index => $scene) :
            $custom = get_theme_mod('tiron_image_' . $scene['key']);
            $src_attr = $index === 0 ? 'src' : 'data-src';
            $srcset_attr = $index === 0 ? 'srcset' : 'data-srcset';
            ?>
            <picture class="hero-frame<?= $index === 0 ? ' is-active' : ''; ?>" data-frame data-name="<?= esc_attr($scene['name']); ?>" data-concept="<?= $custom ? '0' : '1'; ?>" aria-hidden="<?= $index === 0 ? 'false' : 'true'; ?>">
                <?php if (!$custom) : ?><source media="(max-width: 700px)" <?= $srcset_attr; ?>="<?= esc_url(t_asset('images/' . $scene['mobile'] . '.webp')); ?>"><?php endif; ?>
                <img <?= $src_attr; ?>="<?= esc_url(t_scene($scene['key'])); ?>" alt="<?= esc_attr($custom ? $scene['name'] : $scene['alt']); ?>" width="1672" height="941" decoding="async"<?= $index === 0 ? ' fetchpriority="high"' : ''; ?>>
            </picture>
        <?php endforeach; ?>
    </div>
    <div class="hero-shade"></div>
    <div class="wrap hero-interior">
        <div class="hero-copy">
            <h1><?= t_e(t_home_copy('hero_title')); ?><br><em><?= t_e(t_home_copy('hero_emphasis')); ?></em></h1>
            <p class="hero-intro"><?= t_e(t_home_copy('hero_intro')); ?></p>
            <div class="hero-actions">
                <a class="button-gold" href="<?= esc_url(t_url('luna')); ?>"><?= t_icon('spark', 18); ?>Creează-ți Luna</a>
                <a class="hero-sub" href="#descopera-lumina">Descoperă lumina <?= t_icon('down', 16); ?></a>
            </div>
        </div>
        <div class="hero-base">
            <div class="slider-nav">
                <div class="hero-indicators" role="group" aria-label="Alege imaginea">
                    <?php foreach ($scenes as $i => $scene) : ?><button type="button" data-slide="<?= $i; ?>" aria-label="<?= esc_attr($scene['name']); ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false'; ?>"></button><?php endforeach; ?>
                </div>
                <span class="slider-num"><b data-position>01</b><span>/</span>03</span>
            </div>
            <div class="scene-caption" aria-live="polite"><span class="scene-title">Luna</span><span class="scene-name" data-caption><?= t_e($scenes[0]['name']); ?><?= get_theme_mod('tiron_image_hero') ? '' : ' · Imagine de concept'; ?></span></div>
            <div class="scene-actions"><button type="button" data-prev aria-label="Imaginea precedentă"><?= t_icon('prev', 15); ?></button><button type="button" data-next aria-label="Imaginea următoare"><?= t_icon('next', 15); ?></button></div>
        </div>
    </div>
</section>
