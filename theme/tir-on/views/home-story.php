<?php
require_once __DIR__ . '/home-story-customizer.php';

$tiron_steps = [
    [ 'Alegi fotografia', 'Încarci imaginea pe care vrei să o păstrezi în lumină. Alege o fotografie clară, cu subiectul bine conturat.' ],
    [ 'Îi dăm formă', 'Transformăm fotografia într-un relief circular și îl imprimăm 3D, apoi îl așezăm pe suportul Luna.' ],
    [ 'Aprinzi amintirea', 'Primești Luna acasă. O conectezi, apeși butonul și îți redescoperi fotografia în lumină caldă.' ],
];
$tiron_gifts = [
    'Familie'              => 'O fotografie cu părinții în grădină sau cu trei generații la aceeași masă. Alege un cadru în care chipurile se văd clar.',
    'Cupluri'              => 'O privire din prima vacanță împreună. Un portret în doi, cu un fundal simplu, păstrează gestul care vă leagă.',
    'Animale de companie'  => 'Privirea pe care ai recunoaște-o oriunde. Alege un portret de aproape, cu ochii în lumină și blana bine conturată.',
    'Aniversări'           => 'O fotografie din anul în care a început povestea. Dacă alegi o imagine dintr-un album, scaneaz-o la rezoluție bună.',
    'Momente speciale'     => 'Prima zi acasă, o revedere sau drumul spre un vârf. Caută acel cadru care spune o poveste chiar și fără cuvinte.',
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
        <h2 id="tl-final-title" class="tl-title"><?= t_e( tiron_story_text( 'final_title' ) ) ?></h2>
        <a class="button-gold" href="<?= t_e( t_url( 'luna' ) ) ?>">Creează-ți Luna</a>
    </div>
</section>
