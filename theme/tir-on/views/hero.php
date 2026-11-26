<?php
$scenes = [
    ['key' => 'hero', 'mobile' => 'hero-mobile', 'name' => 'Lumină aprinsă', 'alt' => 'Luna iluminată, cu un peisaj montan în relief, pe un suport espresso'],
    ['key' => 'hero-alt', 'mobile' => 'hero-alt-mobile', 'name' => 'În ambient', 'alt' => 'Luna aprinsă într-un decor de interior'],
    ['key' => 'detail', 'mobile' => 'detail-mobile', 'name' => 'Detaliu de design', 'alt' => 'Detaliu al finisajului și al reliefului iluminat'],
];
foreach ($scenes as $i=>$scene) $scenes[$i]['name']=ts_value('caption_'.$scene['key'],$scene['name']);
$orders=['main'=>[0],'ambient'=>[0,1],'detail'=>[0,2],'swap'=>[0,2,1]];
$scenes=array_map(static fn($i)=>$scenes[$i],$orders[ts_value('slides')]??[0,1,2]);
$primary=ts_cta(ts_value('primary_target','product'));
$secondary=ts_cta(ts_value('secondary_target','light'));
?>
<section class="hero" aria-label="Luna" aria-roledescription="carusel" data-hero>
    <div class="hero-media">
        <?php foreach ($scenes as $index => $scene) :
            $custom = t_storefront_image($scene['key']);
            $mobile = $custom ? t_storefront_image($scene['mobile']) : null;
            $mobile_srcset = $mobile ? ($mobile['srcset'] ?: $mobile['url']) : '';
            if (!$custom) {
                $mobile_srcset = t_asset('images/' . $scene['mobile'] . '.webp');
            }
            $src_attr = $index === 0 ? 'src' : 'data-src';
            $srcset_attr = $index === 0 ? 'srcset' : 'data-srcset';
            ?>
            <picture class="hero-frame<?= $index === 0 ? ' is-active' : ''; ?>" data-frame data-name="<?= esc_attr($scene['name']); ?>" data-concept="<?= $custom ? '0' : '1'; ?>" aria-hidden="<?= $index === 0 ? 'false' : 'true'; ?>">
                <?php if ($mobile_srcset) : ?><source media="(max-width: 700px)" <?= $srcset_attr; ?>="<?= esc_attr($mobile_srcset); ?>" sizes="100vw"><?php endif; ?>
                <img <?= $src_attr; ?>="<?= esc_url($custom['url'] ?? t_asset('images/' . $scene['key'] . '.webp')); ?>"<?php if (!empty($custom['srcset'])) : ?> <?= $srcset_attr; ?>="<?= esc_attr($custom['srcset']); ?>" sizes="100vw"<?php endif; ?> alt="<?= esc_attr($custom ? ($custom['alt'] ?: $scene['name']) : $scene['alt']); ?>" width="1672" height="941" decoding="async"<?= $index === 0 ? ' fetchpriority="high"' : ''; ?>>
            </picture>
        <?php endforeach; ?>
    </div>
    <div class="hero-shade"></div>
    <div class="wrap hero-interior">
        <div class="hero-copy">
            <h1><?= t_e(t_home_copy('hero_title')); ?><br><em><?= t_e(t_home_copy('hero_emphasis')); ?></em></h1>
            <p class="hero-intro"><?= t_e(t_home_copy('hero_intro')); ?></p>
            <div class="hero-actions">
                <?php if ($primary) : ?><a class="button-gold" href="<?= esc_url($primary); ?>"><?= t_icon('spark', 18); ?><?=t_e(ts_value('primary_label','Creează-ți Luna'))?></a><?php endif; ?>
                <?php if ($secondary) : ?><a class="hero-sub" href="<?=esc_url($secondary)?>"><?=t_e(ts_value('secondary_label','Descoperă lumina'))?> <?= t_icon('down', 16); ?></a><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="wrap hero-base">
            <div class="slider-nav" <?=count($scenes)===1?'hidden':''?>>
                <div class="hero-indicators" role="group" aria-label="Alege imaginea">
                    <?php foreach ($scenes as $i => $scene) : ?><button type="button" data-slide="<?= $i; ?>" aria-label="<?= esc_attr($scene['name']); ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false'; ?>"></button><?php endforeach; ?>
                </div>
                <span class="slider-num"><b data-position>01</b><span>/</span><?=sprintf('%02d',count($scenes))?></span>
            </div>
            <div class="scene-caption" aria-live="polite"><span class="scene-title">Luna</span><span class="scene-name" data-caption><?= t_e($scenes[0]['name']); ?><?= t_storefront_image('hero') ? '' : ' · Imagine de concept'; ?></span></div>
            <div class="scene-actions" <?=count($scenes)===1?'hidden':''?>><button type="button" data-prev aria-label="Imaginea precedentă"><?= t_icon('prev', 15); ?></button><button type="button" data-next aria-label="Imaginea următoare"><?= t_icon('next', 15); ?></button></div>
    </div>
</section>
