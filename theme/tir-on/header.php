<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#181613">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#continut">Sari la conținut</a>
<header class="site-head">
    <div class="wrap head-inner">
        <a class="brand-link" href="<?= esc_url(t_url('home')); ?>">
            <img class="brand-logo" src="<?= esc_url(t_asset('brand/TIR-ON_06_alb.svg')); ?>" width="553" height="144" alt="TIR-ON – Acasă">
            <span class="brand-tagline">Turning Memories ON</span>
        </a>
        <div class="head-actions">
            <a class="bag-trigger" href="<?= esc_url(t_url('cart')); ?>" aria-label="Deschide coșul"<?= function_exists('is_cart') && is_cart() ? ' aria-current="page"' : ''; ?>>
                <?= t_icon('bag', 19); ?><span class="bag-text">Coș</span><?= t_cart_count(); ?>
            </a>
            <button class="menu-trigger" type="button" aria-expanded="false" aria-controls="site-navigation" aria-label="Deschide meniul" hidden>
                <span class="menu-open-label"><?= t_icon('menu', 18); ?> Meniu</span>
                <span class="menu-close-label"><?= t_icon('close', 18); ?> Închide</span>
            </button>
        </div>
        <nav id="site-navigation" class="head-nav" aria-label="Navigație principală">
            <a href="<?= esc_url(t_url('luna')); ?>"<?= function_exists('is_product') && is_product() ? ' aria-current="page"' : ''; ?>>Luna</a>
            <?php foreach (['descopera-lumina' => 'Descoperă lumina', 'cum-functioneaza' => 'Cum funcționează', 'cadouri' => 'Cadouri'] as $anchor => $label) : ?>
                <a href="<?= esc_url(t_url('home') . '#' . $anchor); ?>"><?= t_e($label); ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
