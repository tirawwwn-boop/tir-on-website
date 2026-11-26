<?php
$luna_url = t_url('luna');
$contact_page = get_page_by_path('contact', OBJECT, 'page');
$contact_url = $contact_page && $contact_page->post_status === 'publish' && $contact_page->post_password === '' ? get_permalink($contact_page) : '';
$terms_id = function_exists('wc_terms_and_conditions_page_id') ? wc_terms_and_conditions_page_id() : 0;
$terms_url = $terms_id && get_post_status($terms_id) === 'publish' ? get_permalink($terms_id) : '';
?>
<footer class="site-foot">
    <div class="wrap footer-inner">
        <div class="footer-brand">
            <a class="brand-link" href="<?= esc_url(t_url('home')); ?>">
                <img class="brand-logo" src="<?= esc_url(ts_media_url('logo',t_asset('brand/TIR-ON_06_alb.svg'))); ?>" width="553" height="144" alt="TIR-ON – Acasă">
            </a>
            <?php if (ts_value('footnote')) : ?><p><?= esc_html(ts_value('footnote')) ?></p><?php endif; ?>
            <small>© <?= esc_html(wp_date('Y')); ?> TIR-ON</small>
        </div>
        <nav aria-label="Asistență clienți">
            <h2>Asistență</h2>
            <?php t_footer_links('help', [
                'Contact' => $contact_url,
                'Cum funcționează' => ts_value('hide_cum-functioneaza') ? '' : t_url('home') . '#cum-functioneaza',
                'Alegerea fotografiei' => $luna_url . '#personalizare',
            ]); ?>
            <?php foreach (['email'=>'mailto:', 'phone'=>'tel:'] as $key=>$protocol) if ($value=ts_value($key)) : ?><p><a href="<?=esc_url($protocol.$value)?>"><?=esc_html($value)?></a></p><?php endif; ?>
        </nav>
        <nav aria-label="Livrare">
            <h2>Livrare</h2>
            <?php t_footer_links('delivery', ['Ambalare și livrare' => $luna_url . '#livrare']); ?>
        </nav>
        <nav aria-label="Informații legale">
            <h2>Informații legale</h2>
            <?php t_footer_links('legal', [
                'Confidențialitate' => ts_link_page('privacy',get_privacy_policy_url()),
                'Termeni și condiții' => ts_link_page('terms',$terms_url),
                'Produse personalizate și retur' => $luna_url . '#retur',
            ]); ?>
        </nav>
        <section class="footer-social" aria-label="Rețele sociale">
            <h2>Social</h2>
            <?php t_footer_links('social',array_filter(['Instagram'=>ts_value('instagram'),'TikTok'=>ts_value('tiktok'),'Facebook'=>ts_value('facebook')])); ?>
            <details class="footer-sharing">
                <summary>Distribuie Luna</summary>
                <input type="url" aria-label="Linkul către Luna" readonly value="<?= esc_url($luna_url); ?>">
                <button type="button" data-copy-link hidden>Copiază linkul</button>
                <span role="status"></span>
            </details>
        </section>
    </div>
</footer>
<?php wp_footer(); ?>
</body></html>
