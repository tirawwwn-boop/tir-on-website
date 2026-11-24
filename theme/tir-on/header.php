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
        <a class="wordmark" href="<?= esc_url(t_url('home')); ?>" aria-label="TIR-ON – Acasă">TIR<span class="wordmark-dash">-</span>ON<span class="wordmark-dot" aria-hidden="true"></span></a>
        <nav id="site-navigation" class="head-nav" aria-label="Navigație principală">
            <a href="<?= esc_url(t_url('luna')); ?>"<?= function_exists('is_product') && is_product() ? ' aria-current="page"' : ''; ?>>TIR-ON Luna</a>
            <?php foreach (['transformarea' => 'Transformarea', 'cum-functioneaza' => 'Cum funcționează', 'cadouri' => 'Cadouri'] as $anchor => $label) : ?>
                <a href="<?= esc_url(t_url('home') . '#' . $anchor); ?>"><?= t_e($label); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="head-actions">
            <a class="bag-trigger" href="<?= esc_url(t_url('cart')); ?>" aria-label="Deschide coșul"<?= function_exists('is_cart') && is_cart() ? ' aria-current="page"' : ''; ?>>
                <?= t_icon('bag', 19); ?><span class="bag-text">Coș</span><?= t_cart_count(); ?>
            </a>
            <button class="menu-trigger" type="button" aria-expanded="false" aria-controls="site-navigation" aria-label="Deschide meniul"><?= t_icon('menu'); ?></button>
        </div>
    </div>
</header>
