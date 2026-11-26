<?php
require_once __DIR__ . '/home-story-customizer.php';

$tiron_comparison = [];
$tiron_configured_images = [];
foreach ( [
    'original' => [ 'Vârfuri alpine și brazi reflectați într-un lac', 'Fotografia de referință' ],
    'relief' => [ 'Același peisaj redat în relieful litofanului iluminat', 'Fotografia redată în litofanul iluminat' ],
    'relief-unlit' => [ 'Același peisaj în relief, fără iluminare din spate', 'Litofanul fără iluminare din spate' ],
] as $tiron_key => [ $tiron_alt, $tiron_custom_alt ] ) {
    $tiron_id = (int) get_theme_mod( 'tiron_image_' . $tiron_key, 0 );
    $tiron_src = $tiron_id ? wp_get_attachment_image_url( $tiron_id, 'large' ) : false;
    $tiron_comparison[ $tiron_key ] = [
        'url'    => t_asset( 'images/' . $tiron_key . '.webp' ),
        'srcset' => sprintf(
            '%s 768w, %s 1200w',
            t_asset( 'images/' . $tiron_key . '-mobile.webp' ),
            t_asset( 'images/' . $tiron_key . '.webp' )
        ),
        'alt'    => $tiron_alt,
    ];
    if ( $tiron_src ) {
        $tiron_configured_images[ $tiron_key ] = [
            'url'    => $tiron_src,
            'srcset' => wp_get_attachment_image_srcset( $tiron_id, 'large' ),
            'alt'    => get_post_meta( $tiron_id, '_wp_attachment_image_alt', true ) ?: $tiron_custom_alt,
        ];
    }
}

// Keep the matched concept set together until all three replacement images are available.
$tiron_using_concept = count( $tiron_configured_images ) !== count( $tiron_comparison );
if ( ! $tiron_using_concept ) {
    $tiron_comparison = $tiron_configured_images;
}

$tiron_steps = [
    [ 'Alegi fotografia', 'Încarci un cadru drag, cu subiectul bine luminat.' ],
    [ 'Îi dăm formă', 'Creăm Luna după fotografia ta, detaliu cu detaliu.' ],
    [ 'Aprinzi amintirea', 'Primești Luna acasă și redescoperi fotografia în lumină caldă.' ],
];
$tiron_gifts = [
    'Familie'              => 'O fotografie cu părinții în grădină sau cu trei generații la aceeași masă. Alege un cadru în care chipurile se văd clar.',
    'Cupluri'              => 'O privire din prima vacanță împreună. Un portret în doi, cu un fundal simplu, păstrează gestul care vă leagă.',
    'Animale de companie'  => 'Privirea pe care ai recunoaște-o oriunde. Alege un portret de aproape, cu ochii în lumină și blana bine conturată.',
    'Aniversări'           => 'O fotografie din anul în care a început povestea. Dacă alegi o imagine dintr-un album, scaneaz-o la rezoluție bună.',
    'Momente speciale'     => 'Prima zi acasă, o revedere sau drumul spre un vârf. Caută acel cadru care spune o poveste chiar și fără cuvinte.',
];
?>
<?php if (!ts_value('hide_descopera-lumina')) : ?>
<section id="descopera-lumina" class="tl-transformation" aria-labelledby="tl-transform-title">
    <div id="transformarea" class="wrap tl-transform-grid" data-transformation>
        <div class="tl-transform-copy">
            <p class="tl-kicker">Efectul luminii</p>
            <h2 class="tl-title" id="tl-transform-title"><?= t_e( tiron_story_text( 'transformation_title' ) ) ?></h2>
            <p class="tl-intro"><?= t_e( tiron_story_text( 'transformation_text' ) ) ?></p>
            <div class="tl-light-modes" role="group" aria-label="Iluminarea litofanului" hidden>
                <button type="button" data-light="off" aria-controls="tl-compare" aria-pressed="false">Lumină stinsă</button>
                <button type="button" data-light="on" aria-controls="tl-compare" aria-pressed="true">Lumină aprinsă</button>
            </div>
        </div>
        <figure class="tl-compare">
            <div id="tl-compare" class="tl-compare-stage" data-compare>
                <?php foreach ( [ 'relief-unlit' => 'tl-unlit', 'relief' => 'tl-lit', 'original' => 'tl-compare-original' ] as $tiron_key => $tiron_class ) :
                    $tiron_image = $tiron_comparison[ $tiron_key ];
                    ?>
                    <img class="<?= t_e( $tiron_class ) ?>"
                        <?php if ( 'relief-unlit' === $tiron_key ) : ?>hidden<?php endif; ?>
                        src="<?= t_e( $tiron_image['url'] ) ?>"
                        <?php if ( $tiron_image['srcset'] ) : ?>
                            srcset="<?= t_e( $tiron_image['srcset'] ) ?>"
                            sizes="(max-width: 760px) calc(100vw - 40px), (max-width: 1280px) 55vw, 700px"
                        <?php endif; ?>
                        alt="<?= t_e( $tiron_image['alt'] ) ?>"
                        width="1200" height="800" loading="lazy" decoding="async">
                <?php endforeach; ?>
                <div class="tl-divider" aria-hidden="true"><span class="tl-handle"><i></i><i></i></span></div>
                <input class="tl-compare-range" type="range" min="0" max="100" value="50" disabled aria-label="Compară fotografia cu litofanul" aria-valuetext="50% fotografie, 50% litofan iluminat">
            </div>
            <figcaption><span>Fotografie</span><?php if ( $tiron_using_concept ) : ?><small>Vizualizare de concept</small><?php endif; ?><span data-result-label>Litofan iluminat</span></figcaption>
        </figure>
    </div>
</section>
<?php endif; ?>

<?php if (!ts_value('hide_cum-functioneaza')) : ?>
<section id="cum-functioneaza" class="tl-process" aria-labelledby="tl-process-title">
    <div class="wrap">
        <p class="tl-kicker">Cum funcționează</p>
        <h2 id="tl-process-title" class="tl-title"><?= t_e( tiron_story_text( 'steps_title' ) ) ?></h2>
        <ol class="tl-steps">
            <?php foreach ( $tiron_steps as $tiron_i => $tiron_step ) : ?>
                <li>
                    <span class="tl-step-number" aria-hidden="true"><?= t_e( sprintf( '%02d', $tiron_i + 1 ) ) ?></span>
                    <h3><?= t_e( ts_value('step_title_'.($tiron_i+1),$tiron_step[0]) ) ?></h3>
                    <p><?= t_e( ts_value('step_copy_'.($tiron_i+1),$tiron_step[1]) ) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>

<?php if (!ts_value('hide_cadouri')) : ?>
<section id="cadouri" class="tl-gifts" aria-labelledby="tl-gifts-title">
    <div class="wrap tl-gifts-grid">
        <div>
            <p class="tl-kicker">Idei de cadou</p>
            <h2 id="tl-gifts-title" class="tl-title"><?= t_e( tiron_story_text( 'gift_title' ) ) ?></h2>
        </div>
        <div class="tl-gift-options">
            <?php foreach ( array_keys($tiron_gifts) as $gift_index=>$tiron_occasion ) : $tiron_idea=$tiron_gifts[$tiron_occasion]; ?>
                <details name="tiron-cadou"<?= 'Familie' === $tiron_occasion ? ' open' : '' ?>>
                    <summary><?= t_e(ts_value('gift_title_'.($gift_index+1),$tiron_occasion)) ?><span class="tl-toggle" aria-hidden="true"></span></summary>
                    <p><?= t_e(ts_value('gift_copy_'.($gift_index+1),$tiron_idea)) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!ts_value('hide_final')) : ?>
<section class="tl-final" aria-labelledby="tl-final-title">
    <div class="wrap tl-final-inner">
        <h2 id="tl-final-title" class="tl-title"><?= t_e( tiron_story_text( 'final_title' ) ) ?></h2>
        <a class="tl-create" href="<?= t_e( t_url( 'luna' ) ) ?>">Creează-ți Luna</a>
    </div>
</section>
<?php endif; ?>
