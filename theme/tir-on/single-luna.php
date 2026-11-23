<?php
get_header();
while (have_posts()) {
    the_post();
    $product_data = t_product_data();
    require __DIR__ . '/views/product.php';
}
get_footer();
