<?php get_header(); ?>
<main class="wrap content-page" id="continut">
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
<article <?php post_class(); ?>><h1><?php the_title(); ?></h1><?php the_content(); ?></article>
<?php endwhile; else : ?>
<h1>Pagina căutată nu este disponibilă.</h1><a href="<?= esc_url(t_url('home')); ?>">Înapoi la prima pagină</a>
<?php endif; ?>
</main>
<?php get_footer(); ?>
