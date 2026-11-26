<?php
defined('ABSPATH') || exit;

function ts_admin_url($tab='brand') {
    return add_query_arg(['page'=>'tiron-commercial','studio'=>$tab],admin_url('admin.php'));
}
add_action('admin_enqueue_scripts',static function () {
    if (($_GET['page']??'')!=='tiron-commercial' || !isset($_GET['studio']) || !ts_can_edit()) return;
    wp_enqueue_media();
    wp_enqueue_script('tiron-studio',t_asset('studio.js'),['media-editor'],'7',true);
    wp_enqueue_style('tiron-studio',t_asset('studio.css'),[],'7');
});

function ts_field($key,$field,$value) {
    [$label,$type]=$field;
    $attr='id="ts-'.esc_attr($key).'" name="content['.esc_attr($key).']"';
    echo '<div class="ts-field"><label for="ts-'.esc_attr($key).'">'.esc_html($label).'</label>';
    if (in_array($type,['image','video','gallery'],true)) {
        echo '<input type="hidden" '.$attr.' value="'.esc_attr($value).'"><div class="ts-media" data-kind="'.esc_attr($type).'"><div class="ts-selected">';
        foreach (array_filter(explode(',',(string)$value)) as $id) {
            if (!ts_public_media($id,$type)) continue;
            if ($type==='video') echo esc_html(get_the_title($id));
            else echo wp_get_attachment_image((int)$id,'thumbnail');
        }
        if (!$value) {
            $default='';
            if ($key==='logo') $default=t_asset('brand/TIR-ON_06_alb.svg');
            elseif ($key==='icon') $default=get_site_icon_url(32);
            elseif (str_starts_with($key,'tiron_image_')) $default=t_scene(substr($key,12));
            if ($default) echo '<img class="ts-inherited" src="'.esc_url($default).'" alt="Imagine din configurația existentă">';
        }
        echo '</div><button aria-label="'.esc_attr('Alege din Media · '.$label).'" class="button ts-pick" type="button">Alege din Media</button> <button class="button ts-clear" type="button" '.(!$value?'hidden':'').'>Elimină</button></div>';
    } elseif ($type==='page') {
        echo '<select '.$attr.'><option value="">Pagina magazinului</option>';
        foreach (get_pages(['post_status'=>'publish']) as $page) if (!$page->post_password)
            echo '<option value="'.(int)$page->ID.'" '.selected($value,$page->ID,false).'>'.esc_html($page->post_title).'</option>';
        echo '</select>';
    } elseif ($type==='select') {
        echo '<select '.$attr.'>';
        foreach ($field[2] as $v=>$text) echo '<option value="'.esc_attr($v).'" '.selected($value,$v,false).'>'.esc_html($text).'</option>';
        echo '</select>';
    } elseif ($type==='check') echo '<input type="checkbox" '.$attr.' value="1" '.checked($value,'1',false).'>';
    elseif ($type==='area') echo '<textarea '.$attr.' rows="3">'.esc_textarea($value).'</textarea>';
    else {
        $hint='';
        if (str_starts_with($key,'tiron_')) {
            $copy=t_home_copy_fields()+tiron_story_fields();
            $hint=get_theme_mod($key,$copy[substr($key,6)][1]??'');
        }
        echo '<input type="'.esc_attr($type).'" '.$attr.' value="'.esc_attr($value).'" placeholder="'.esc_attr($hint).'">';
    }
    echo '</div>';
}

function ts_editor(): void {
    if (!ts_can_edit()) wp_die('Acces restricționat.','',['response'=>403]);
    $groups=ts_groups();
    $tab=is_string($_GET['studio']??null) ? sanitize_key(wp_unslash($_GET['studio'])) : 'brand';
    if (!isset($groups[$tab])) $tab='brand';
    $s=ts_state();
    $preview= ['tiron_studio'=>(string)$s['version'],'proof'=>wp_create_nonce('ts_preview_'.$s['version'])];
    ?>
    <main class="wrap ts-editor">
    <a href="<?=esc_url(admin_url('admin.php?page=tiron-commercial'))?>">← Magazin TIR-ON</a>
    <header class="ts-head"><div><small>WooCommerce · Magazin TIR-ON</small><h1>Brand Studio</h1><p>Gestionează conținutul magazinului.</p></div>
    <span><?= $s['draft']!==$s['published']?'Ciornă cu modificări':'Sincronizat cu magazinul' ?></span></header>
    <?php if (isset($_GET['saved'])) echo '<p role="status" class="notice notice-success">Modificările au fost salvate.</p>'; ?>
    <nav class="ts-tabs" aria-label="Secțiuni Brand Studio"><?php foreach ($groups as $id=>[$name]) : ?><a <?= $id===$tab?'aria-current="page"':'' ?> href="<?=esc_url(ts_admin_url($id))?>"><?=esc_html($name)?></a><?php endforeach; ?></nav>
    <form class="ts-card" method="post" action="<?=esc_url(admin_url('admin-post.php'))?>">
    <input type="hidden" name="action" value="tiron_studio_edit"><input type="hidden" name="version" value="<?=(int)$s['version']?>"><input type="hidden" name="tab" value="<?=esc_attr($tab)?>">
    <?php wp_nonce_field('ts_edit'); ?>
    <h2><?=esc_html($groups[$tab][0])?></h2>
    <p><?=esc_html(['brand'=>'Folosește un logo transparent, lizibil pe espresso, și o pictogramă pătrată. Câmpurile goale păstrează setările existente.',
    'hero'=>'Alege imaginile în ordinea celor trei cadre. Completează textul alternativ în Media.',
    'luna'=>'Selectează galeria în ordinea dorită. Prima imagine devine coperta. Folosește numai materiale comerciale publice.',
    'story'=>'Pentru comparație alege trei imagini cu același subiect și cadraj. Bifează doar secțiunile pe care vrei să le ascunzi.',
    'campaign'=>'Anunțul poate avea o perioadă opțională. Întrebările și răspunsurile completate apar pe homepage.',
    'footer'=>'Introdu doar datele publice aprobate și paginile juridice publicate.',
    'seo'=>'Câmpurile goale folosesc titlurile, descrierile și imaginile existente.'][$tab])?></p>
    <div class="ts-grid"><?php foreach ($groups[$tab][1] as $key=>$field) ts_field($key,$field,$s['draft'][$key]??''); ?></div>
    <button class="button button-primary" name="op" value="save">Salvează secțiunea în ciornă</button>
    </form><section class="ts-card"><h2>Previzualizare și publicare</h2><p>Verifică ultima ciornă salvată. Publicarea aplică toate secțiunile împreună.</p>
    <p><a class="button" href="<?=esc_url(add_query_arg($preview,home_url('/')))?>">Previzualizează Acasă</a>
    <a class="button" href="<?=esc_url(add_query_arg($preview,t_url('luna')))?>">Previzualizează Luna</a></p>
    <form method="post" action="<?=esc_url(admin_url('admin-post.php'))?>">
    <input type="hidden" name="action" value="tiron_studio_edit"><input type="hidden" name="tab" value="<?=esc_attr($tab)?>"><input type="hidden" name="version" value="<?=(int)$s['version']?>"><?php wp_nonce_field('ts_edit'); ?>
    <label><input type="checkbox" name="confirm" value="1"> Am verificat ciorna și aprob publicarea.</label>
    <button class="button button-primary" name="op" value="publish">Publică versiunea</button>
    <details><summary>Istoric și resetare</summary><p>Restaurarea și resetarea modifică numai ciorna.</p>
    <?php foreach ($s['history'] as $entry) : ?><p><?=esc_html(wp_date('d.m.Y H:i',$entry['time']).' · '.get_the_author_meta('display_name',$entry['author']))?> <button class="button" name="op" value="restore-<?=(int)$entry['version']?>">Restaurează v<?=(int)$entry['version']?></button></p><?php endforeach; ?>
    <button class="button" name="op" value="reset">Revino în ciornă la setările de bază</button></details></form></section></main>
    <?php
}
add_action('admin_post_tiron_studio_edit',static function () {
    if (!ts_can_edit() || ($_SERVER['REQUEST_METHOD']??'')!=='POST') wp_die('Acces restricționat.','',['response'=>403]);
    check_admin_referer('ts_edit');
    $s=ts_state(); $after=$s; $groups=ts_groups();
    $tab=sanitize_key($_POST['tab']??''); $op=sanitize_key($_POST['op']??'');
    if (!isset($groups[$tab]) || (string)$s['version']!==($_POST['version']??null)) wp_die('Ciorna s-a schimbat. Redeschide secțiunea.','',['response'=>409]);
    try {
        if ($op==='save') {
            $input=wp_unslash($_POST['content']??[]);
            foreach ($groups[$tab][1] as $key=>$field) {
                if (!is_array($input) || (!array_key_exists($key,$input) && $field[1]!=='check')) throw new DomainException('Formular incomplet.');
                $value=ts_clean($input[$key]??'',$field);
                if ($value==='') unset($after['draft'][$key]); else $after['draft'][$key]=$value;
            }
        } elseif ($op==='reset') $after['draft']=[];
        elseif ($op==='publish' && ($_POST['confirm']??'')==='1') {
            ts_validate_release($s['draft']);
            array_unshift($after['history'],['version'=>$s['version'],'time'=>time(),'author'=>get_current_user_id(),'values'=>$s['published']]);
            $after['history']=array_slice($after['history'],0,12);
            $after['published']=$s['draft'];
        } elseif (str_starts_with($op,'restore-')) {
            $entry=array_filter($s['history'],static fn($r)=>'restore-'.$r['version']===$op);
            if (!$entry) throw new DomainException('Revizie indisponibilă.');
            $after['draft']=reset($entry)['values'];
        } else throw new DomainException('Verifică operația și confirmarea publicării.');
    } catch (DomainException $e) { wp_die(esc_html($e->getMessage()),'',['response'=>400,'back_link'=>true]); }
    if (!ts_store($s,$after)) wp_die('Salvarea nu a fost aplicată. Reîncarcă pagina.','',['response'=>409]);
    if ($op==='publish') do_action('litespeed_purge_all');
    wp_safe_redirect(add_query_arg('saved','1',ts_admin_url($tab))); exit;
});
