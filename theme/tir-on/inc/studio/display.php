<?php
defined('ABSPATH') || exit;

add_action('template_redirect', static function () {
    if (!isset($_GET['tiron_studio'])) return;
    nocache_headers();
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    do_action('litespeed_control_set_nocache', 'Brand Studio');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    $version=(string)ts_state()['version'];
    if (!ts_can_edit() || $version!==$_GET['tiron_studio'] || !is_string($_GET['proof']??null)
        || !wp_verify_nonce($_GET['proof'],'ts_preview_'.$version)
        || !(is_front_page() || (is_singular('product') && t_is_luna(get_queried_object_id())))) {
        wp_die('Previzualizare indisponibilă. Redeschide ciorna din Brand Studio.','',['response'=>403]);
    }
    $GLOBALS['tiron_studio_preview']=true;
}, -999);

function ts_media_url($key,$fallback='',$type='image') {
    $id=ts_value($key);
    return $id && ts_public_media($id,$type) ? (wp_get_attachment_url($id) ?: $fallback) : $fallback;
}

function ts_link_page($key,$fallback) {
    $id=ts_value($key);
    return $id && ts_public_page($id) ? get_permalink($id) : $fallback;
}

function ts_cta($target) {
    if ($target==='light') return ts_value('hide_descopera-lumina') ? '' : '#descopera-lumina';
    return t_url('luna').($target==='form' ? '#personalizare' : '');
}

// Resolve legacy Customizer content at render time; preserve its underlying values.
foreach (ts_groups() as [,$fields]) foreach ($fields as $key=>$field) {
    if (!str_starts_with($key,'tiron_')) continue;
    add_filter('theme_mod_'.$key, static function ($old) use ($key,$field) {
        $new=ts_value($key);
        if (!$new || ($field[1]==='image' && !ts_public_media($new,'image'))) return $old;
        return $new;
    });
}

add_filter('pre_get_document_title',static function($title) {
    if (is_front_page()) return ts_value('home_title_seo',$title);
    if (is_singular('product') && t_is_luna(get_queried_object_id())) return ts_value('product_title_seo',$title);
    return $title;
});

add_action('wp_enqueue_scripts',static function () {
    $palette=ts_value('palette'); $type=ts_value('type');
    $vars=['bronze'=>'--gold:#d6ae85;--paper:#f8f1e9;', 'ivory'=>'--gold:#e2c28e;--paper:#fffaf1;'];
    $fonts=['classic'=>'--serif:Georgia,serif;', 'sans'=>'--serif:var(--sans);'];
    $css=($vars[$palette]??'').($fonts[$type]??'');
    if ($css) wp_add_inline_style('tiron-site',':root{'.$css.'}');
}, 25);

function ts_faq(): void {
    $items=[];
    for ($i=1;$i<=6;$i++) if (ts_value('q'.$i) && ts_value('a'.$i)) $items[]=[ts_value('q'.$i),ts_value('a'.$i)];
    if (!$items) return;
    echo '<section class="wrap ts-faq"><h2>Întrebări frecvente</h2>';
    foreach ($items as [$q,$a]) echo '<details><summary>'.esc_html($q).'</summary><p>'.nl2br(esc_html($a)).'</p></details>';
    echo '</section>';
}

add_action('wp_body_open', static function () {
    if (empty($GLOBALS['tiron_studio_preview'])) return;
    $studio=add_query_arg(['page'=>'tiron-commercial','studio'=>is_front_page()?'hero':'luna'],admin_url('admin.php'));
    echo '<aside class="seasonal-note" aria-label="Previzualizare privată">Ciornă · Vizibilă administratorului. <a href="'.esc_url($studio).'">Înapoi la Brand Studio</a></aside>';
});
