<footer class="site-foot">
    <div class="wrap footer-inner">
        <div><a class="wordmark" href="<?= esc_url(t_url('home')); ?>" aria-label="TIR-ON – Acasă">TIR<span class="wordmark-dash">-</span>ON<span class="wordmark-dot" aria-hidden="true"></span></a><p>Turning Memories ON</p></div>
        <nav aria-label="Navigație subsol"><a href="<?= esc_url(t_url('luna')); ?>">TIR-ON Luna</a><a href="<?= esc_url(t_url('home') . '#cum-functioneaza'); ?>">Cum funcționează</a><a href="<?= esc_url(t_url('home') . '#cadouri'); ?>">Cadouri</a></nav>
        <small>© <?= esc_html(wp_date('Y')); ?> TIR-ON</small>
    </div>
</footer>
<dialog id="tiron-bag" class="bag-dialog" aria-labelledby="bag-title" data-luna-url="<?= esc_url(t_url('luna')); ?>">
    <div class="bag-panel">
        <div class="bag-heading"><h2 id="bag-title">Coșul tău</h2><button class="icon-plain" type="button" data-bag-close aria-label="Închide coșul"><?= t_icon('close'); ?></button></div>
        <div class="bag-content" data-bag-content></div>
        <p class="bag-note">Coș de probă pentru previzualizare. Fotografiile rămân pe dispozitivul tău.</p>
    </div>
</dialog>
<?php wp_footer(); ?>
</body></html>
