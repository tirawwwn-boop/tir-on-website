<footer class="site-foot">
    <div class="wrap footer-inner">
        <a class="brand-link" href="<?= esc_url(t_url('home')); ?>">
            <img class="brand-logo" src="<?= esc_url(t_asset('brand/TIR-ON_06_alb.svg')); ?>" width="553" height="144" alt="TIR-ON – Acasă">
        </a>
        <nav aria-label="Navigație subsol"><a href="<?= esc_url(t_url('luna')); ?>">Luna</a><a href="<?= esc_url(t_url('home') . '#cum-functioneaza'); ?>">Cum funcționează</a><a href="<?= esc_url(t_url('home') . '#cadouri'); ?>">Cadouri</a></nav>
        <small>© <?= esc_html(wp_date('Y')); ?> TIR-ON</small>
    </div>
</footer>
<?php wp_footer(); ?>
</body></html>
