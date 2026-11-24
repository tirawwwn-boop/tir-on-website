<?php
require_once __DIR__ . '/home-story-customizer.php';

$tiron_steps = [
    [ 'Alegi fotografia', 'Încarci un cadru drag, cu chipurile bine luminate.' ],
    [ 'Noi o transformăm', 'Dăm formă amintirii tale, detaliu cu detaliu.' ],
    [ 'Tu aprinzi amintirea', 'Primești Luna acasă și redescoperi fotografia în lumină caldă.' ],
];
$tiron_gifts = [
    'Familie'              => 'Părinții în grădină sau trei generații la aceeași masă.',
    'Cupluri'              => 'Un portret în doi din prima vacanță împreună.',
    'Animale de companie'  => 'Privirea pe care ai recunoaște-o oriunde.',
    'Aniversări'           => 'O fotografie din anul în care a început povestea.',
    'Momente speciale'     => 'Prima zi acasă, o revedere sau drumul spre un vârf.',
];
?>
<?php require __DIR__ . '/home-relief.php'; ?>

<section id="cum-functioneaza" class="tl-process" aria-labelledby="tl-process-title">
    <div class="wrap">
        <p class="tl-kicker">Cum funcționează</p>
        <h2 id="tl-process-title" class="tl-title"><?= t_e( tiron_story_text( 'steps_title' ) ) ?></h2>
        <ol class="tl-steps">
            <?php foreach ( $tiron_steps as $tiron_i => $tiron_step ) : ?>
                <li>
                    <span class="tl-step-number" aria-hidden="true"><?= t_e( sprintf( '%02d', $tiron_i + 1 ) ) ?></span>
                    <h3><?= t_e( $tiron_step[0] ) ?></h3>
                    <p><?= t_e( $tiron_step[1] ) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<section id="cadouri" class="tl-gifts" aria-labelledby="tl-gifts-title">
    <div class="wrap tl-gifts-grid">
        <div>
            <p class="tl-kicker">Idei de cadou</p>
            <h2 id="tl-gifts-title" class="tl-title"><?= t_e( tiron_story_text( 'gift_title' ) ) ?></h2>
            <p class="tl-gift-intro"><?= t_e( tiron_story_text( 'gift_text' ) ) ?></p>
        </div>
        <div class="tl-gift-options">
            <?php foreach ( $tiron_gifts as $tiron_occasion => $tiron_idea ) : ?>
                <details name="tiron-cadou"<?= 'Familie' === $tiron_occasion ? ' open' : '' ?>>
                    <summary><?= t_e( $tiron_occasion ) ?><span class="tl-toggle" aria-hidden="true"></span></summary>
                    <p><?= t_e( $tiron_idea ) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="tl-final" aria-labelledby="tl-final-title">
    <div class="wrap tl-final-inner">
        <div class="tl-final-copy">
            <h2 id="tl-final-title" class="tl-title"><?= t_e( tiron_story_text( 'final_title' ) ) ?></h2>
            <p><?= t_e( tiron_story_text( 'final_text' ) ) ?></p>
        </div>
        <a class="button-gold" href="<?= t_e( t_url( 'luna' ) ) ?>">Creează-ți Luna</a>
    </div>
</section>
