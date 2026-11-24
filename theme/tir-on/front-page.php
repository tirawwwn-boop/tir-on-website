<?php
get_header();
$product_data = t_product_data();
if (t_is_library_home()) {
    require __DIR__ . '/views/home-library.php';
    get_footer();
    return;
}
?>
<main id="continut">
    <?php require __DIR__ . '/views/hero.php'; ?>
    <div class="qualities">
        <div class="wrap qualities-inner">
            <div><?= t_icon('photo', 23); ?><span>Fotografia ta, în relief</span></div>
            <div><?= t_icon('sun', 25); ?><span>Lumină LED alb-caldă</span></div>
            <div><?= t_icon('layers', 25); ?><span>Realizat prin imprimare 3D</span></div>
        </div>
    </div>
    <?php require __DIR__ . '/views/home-feature.php'; ?>
    <?php require __DIR__ . '/views/home-transformation.php'; ?>
    <?php require __DIR__ . '/views/home-story.php'; ?>
</main>
<?php get_footer(); ?>
