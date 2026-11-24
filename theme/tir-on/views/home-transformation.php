<?php
require_once __DIR__ . '/home-story-customizer.php';

$tiron_comparison = [];
$portrait_concepts = t_concept_asset('relief') && t_concept_asset('relief-unlit');
$has_original = !$portrait_concepts || (bool) wp_get_attachment_image_url(absint(get_theme_mod('tiron_image_original')), 'large');
foreach ( [
    'original' => 'Vârfuri alpine și brazi reflectați într-un lac',
    'relief' => 'Același peisaj redat în relieful litofanului iluminat',
    'relief-unlit' => 'Detaliu al reliefului fără iluminare din spate',
] as $tiron_key => $tiron_alt ) {
    if ('original' === $tiron_key && !$has_original) {
        continue;
    }
    if ($portrait_concepts && 'relief' === $tiron_key) {
        $tiron_alt = 'Portretul redat în litofanul Luna iluminat';
    }
    $tiron_id = absint(get_theme_mod('tiron_image_' . $tiron_key));
    $tiron_src = $tiron_id ? wp_get_attachment_image_url( $tiron_id, 'large' ) : false;
    $portrait = $portrait_concepts && 'original' !== $tiron_key;
    $tiron_srcset = $tiron_src ? wp_get_attachment_image_srcset( $tiron_id, 'large' ) : sprintf(
        $portrait ? '%s 640w, %s 1024w' : '%s 768w, %s 1200w',
        $portrait ? t_concept_asset($tiron_key, true) : t_asset('images/' . $tiron_key . '-mobile.webp'),
        $portrait ? t_concept_asset($tiron_key) : t_asset('images/' . $tiron_key . '.webp')
    );
    $tiron_comparison[ $tiron_key ] = [
        'url'     => $tiron_src ?: t_scene($tiron_key),
        'srcset'  => $tiron_srcset,
        'alt'     => $tiron_src ? ( get_post_meta( $tiron_id, '_wp_attachment_image_alt', true ) ?: $tiron_alt ) : $tiron_alt,
        'concept' => ! $tiron_src,
    ];
}

?>
<section id="transformarea" class="tl-transformation" aria-labelledby="tl-transform-title">
    <div class="wrap tl-transform-grid" data-transformation>
        <div class="tl-transform-copy">
            <p class="tl-kicker">Transformarea</p>
            <h2 class="tl-title" id="tl-transform-title"><?= t_e( tiron_story_text( 'transformation_title' ) ) ?></h2>
            <p class="tl-intro"><?= t_e( tiron_story_text( 'transformation_text' ) ) ?></p>
            <div class="tl-light-modes" role="group" aria-label="Iluminarea litofanului" hidden>
                <button type="button" data-light="off" aria-controls="tl-compare" aria-pressed="false">Lumină stinsă</button>
                <button type="button" data-light="on" aria-controls="tl-compare" aria-pressed="true">Lumină aprinsă</button>
            </div>
        </div>
        <figure class="tl-compare">
            <div id="tl-compare" class="tl-compare-stage" data-compare<?= $portrait_concepts ? ' data-concepts="v2"' : ''; ?>>
                <?php foreach ( [ 'relief-unlit' => 'tl-unlit', 'relief' => 'tl-lit', 'original' => 'tl-compare-original' ] as $tiron_key => $tiron_class ) :
                    if (!isset($tiron_comparison[$tiron_key])) {
                        continue;
                    }
                    $tiron_image = $tiron_comparison[$tiron_key];
                    ?>
                    <img class="<?= t_e( $tiron_class ) ?>"
                        <?php if ( 'relief-unlit' === $tiron_key ) : ?>hidden<?php endif; ?>
                        src="<?= t_e( $tiron_image['url'] ) ?>"
                        <?php if ( $tiron_image['srcset'] ) : ?>
                            srcset="<?= t_e( $tiron_image['srcset'] ) ?>"
                            sizes="(max-width: 760px) calc(100vw - 40px), (max-width: 1280px) 55vw, 700px"
                        <?php endif; ?>
                        alt="<?= t_e( $tiron_image['alt'] ) ?>"
                        width="<?= $portrait_concepts ? 1024 : 1200; ?>" height="<?= $portrait_concepts ? 1280 : 800; ?>" loading="lazy" decoding="async">
                <?php endforeach; ?>
                <?php if ($has_original) : ?>
                <div class="tl-divider" aria-hidden="true"><span class="tl-handle"><i></i><i></i></span></div>
                <input class="tl-compare-range" type="range" min="0" max="100" value="50" disabled aria-label="Compară fotografia cu litofanul iluminat" aria-valuetext="50% fotografie, 50% litofan iluminat">
                <?php endif; ?>
            </div>
            <figcaption>
                <?php if ($has_original) : ?><span>Fotografie</span><?php endif; ?>
                <?php if (in_array(true, array_column($tiron_comparison, 'concept'), true)) : ?><small>Vizualizări de concept</small><?php endif; ?>
                <span aria-live="polite" data-result-label>Litofan iluminat</span>
            </figcaption>
        </figure>
    </div>
</section>
