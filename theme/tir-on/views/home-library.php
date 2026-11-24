<?php defined('ABSPATH') || exit; ?>

<main id="continut" class="memory-library">
    <div class="library-cabinet">
        <div class="library-shelf library-opening"><?php require __DIR__ . '/hero.php'; ?></div>
        <div class="library-shelf library-transformation"><?php require __DIR__ . '/library-relief.php'; ?></div>
        <div class="library-shelf library-product"><?php require __DIR__ . '/home-feature.php'; ?></div>
        <div class="library-extras"><?php require __DIR__ . '/home-story.php'; ?></div>
    </div>
    <section class="library-closing" aria-labelledby="library-final">
        <div class="wrap">
            <p class="hero-kicker"><span></span>Turning Memories ON</p>
            <h2 id="library-final">Fă loc <em>amintirii tale.</em></h2>
            <p>Alege o fotografie pe care ai vrea să o vezi în fiecare seară.</p>
            <a class="button-gold" href="<?= esc_url(t_url('luna')); ?>"><?= t_icon('spark', 18); ?>Creează-ți Luna</a>
        </div>
    </section>
</main>
