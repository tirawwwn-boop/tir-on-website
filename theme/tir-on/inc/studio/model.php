<?php
defined('ABSPATH') || exit;

/** Overrides are optional. Empty values inherit the existing store at render time. */
function ts_groups(): array {
    $groups = [
        'brand' => ['Identitate', [
            'logo'=>['Logo pentru fundal espresso','image'], 'icon'=>['Pictogramă de site','image'],
            'palette'=>['Paletă','select',['' => 'Espresso și champagne', 'bronze'=>'Espresso și bronz', 'ivory'=>'Ivory și miere']],
            'type'=>['Tipografie','select',[''=>'Cormorant Garamond + DM Sans','sans'=>'DM Sans integral','classic'=>'Georgia + DM Sans']],
        ]],
        'hero' => ['Copertă', []], 'luna' => ['Produsul Luna', []], 'story' => ['Poveste și secțiuni', []],
        'campaign'=>['Campanii și FAQ', []], 'footer'=>['Contact și subsol', []], 'seo'=>['Căutare și distribuire', []],
    ];
    foreach (t_home_copy_fields() as $key => [$label,$default]) {
        $groups[str_starts_with($key,'hero') ? 'hero':'luna'][1]['tiron_'.$key]=[$label,'text'];
    }
    foreach (['hero'=>'Prima imagine','hero-alt'=>'În ambient','detail'=>'Detaliu'] as $key=>$label) {
        $groups['hero'][1]['tiron_image_'.$key]=[$label.' · desktop','image'];
        $groups['hero'][1]['tiron_image_'.$key.'-mobile']=[$label.' · mobil','image'];
        $groups['hero'][1]['caption_'.$key]=[$label.' · legendă','text'];
    }
    $groups['hero'][1]['slides']=['Cadre afișate','select',[''=>'Toate trei','main'=>'Doar prima imagine','ambient'=>'Prima și ambient','detail'=>'Prima și detaliul','swap'=>'Prima, detaliul, ambientul']];
    foreach (['primary'=>'principal','secondary'=>'secundar'] as $key=>$label) {
        $groups['hero'][1][$key.'_label']=['Text buton '.$label,'text'];
        $groups['hero'][1][$key.'_target']=['Destinație buton '.$label,'select',[
            ''=>'Destinația temei','product'=>'Pagina Luna','form'=>'Personalizare','light'=>'Efectul luminii']];
    }
    $groups['luna'][1] += [
        'gallery'=>['Galerie în ordinea afișării','gallery'], 'video'=>['Video de prezentare','video'], 'video_text'=>['Descriere accesibilă a materialului video','area'],
        'package_image'=>['Imaginea ambalajului','image'],
        'tiron_image_feature'=>['Fotografie în prezentarea de pe homepage','image'],
    ];
    foreach (['diameter'=>'Diametru','finish'=>'Finisaj','light'=>'Lumină','power'=>'Alimentare','packaging'=>'Ambalare','production'=>'Producție','delivery'=>'Livrare'] as $key=>$label)
        $groups['luna'][1]['spec_'.$key]=[$label,'text'];
    foreach (['original'=>'Fotografia originală','relief'=>'Litofan iluminat','relief-unlit'=>'Litofan stins'] as $key=>$label)
        $groups['story'][1]['tiron_image_'.$key]=[$label,'image'];
    foreach (tiron_story_fields() as $key=>[$label]) $groups['story'][1]['tiron_'.$key]=[$label,'text'];
    for ($i=1;$i<=3;$i++) {
        $groups['story'][1]['step_title_'.$i]=['Pasul '.$i.' · titlu','text'];
        $groups['story'][1]['step_copy_'.$i]=['Pasul '.$i.' · explicație','area'];
    }
    for ($i=1;$i<=5;$i++) {
        $groups['story'][1]['gift_title_'.$i]=['Cadou '.$i.' · ocazie','text'];
        $groups['story'][1]['gift_copy_'.$i]=['Cadou '.$i.' · sugestie','area'];
    }
    foreach (['hero'=>'Coperta','qualities'=>'Beneficiile','luna'=>'Prezentarea Luna','descopera-lumina'=>'Comparația','cum-functioneaza'=>'Pașii','cadouri'=>'Cadourile','final'=>'Invitația finală'] as $key=>$label)
        $groups['story'][1]['hide_'.$key]=['Ascunde: '.$label,'check'];
    $groups['campaign'][1]=['announcement'=>['Anunț','text'],'start'=>['Prima zi','date'],'end'=>['Ultima zi','date']];
    for ($i=1;$i<=6;$i++) {
        $groups['campaign'][1]['q'.$i]=['Întrebarea '.$i,'text'];
        $groups['campaign'][1]['a'.$i]=['Răspunsul '.$i,'area'];
    }
    $groups['footer'][1]=['footnote'=>['Text în subsol','text'],'email'=>['E-mail public','email'],'phone'=>['Telefon public','tel'],
        'instagram'=>['Instagram','url'],'tiktok'=>['TikTok','url'],'facebook'=>['Facebook','url'],
        'privacy'=>['Pagina de confidențialitate','page'],'terms'=>['Termeni și condiții','page']];
    foreach (['home'=>'Acasă','product'=>'Luna'] as $key=>$label) {
        $groups['seo'][1][$key.'_title_seo']=[$label.' · titlu','text'];
        $groups['seo'][1][$key.'_desc_seo']=[$label.' · descriere','area'];
        $groups['seo'][1][$key.'_social']=[$label.' · imagine socială','image'];
    }
    return $groups;
}

function ts_state(): array {
    return $GLOBALS['tiron_studio_state'] ??= get_option('tiron_brand_studio_v1', []) + ['version'=>0,'published'=>[],'draft'=>[],'history'=>[]];
}

function ts_can_edit(): bool {
    return current_user_can('manage_options') && current_user_can('manage_woocommerce');
}

function ts_value($key,$fallback='') {
    $state=ts_state();
    $source=!empty($GLOBALS['tiron_studio_preview']) ? 'draft' : 'published';
    return $state[$source][$key] ?? $fallback;
}

function ts_public_page($id): bool {
    $post=get_post($id);
    return $post && $post->post_type==='page' && $post->post_status==='publish' && $post->post_password==='';
}

function ts_public_media($id,$type): bool {
    $post=get_post($id);
    if (!$post || $post->post_type!=='attachment' || $post->post_status!=='inherit' || get_post_status($post)!=='publish' || $post->post_password) return false;
    $parent=$post->post_parent ? get_post($post->post_parent) : null;
    if ($parent && ($parent->post_password || !is_post_publicly_viewable($parent))) return false;
    $types=$type==='video'?['video/mp4','video/webm']:['image/jpeg','image/png','image/webp','image/avif','image/gif'];
    if (!in_array($post->post_mime_type,$types,true)) return false;
    $root=realpath(wp_get_upload_dir()['basedir']);
    $file=get_attached_file($id,true);
    $real=$file ? realpath($file) : false;
    $private=defined('TIRON_PRIVATE_PHOTO_DIR') ? realpath(TIRON_PRIVATE_PHOTO_DIR) : false;
    if ($private && $real && str_starts_with($real,$private.DIRECTORY_SEPARATOR)) return false;
    return $root && $real && is_file($real) && str_starts_with($real,$root.DIRECTORY_SEPARATOR);
}

function ts_clean($raw,$field) {
    if (!is_string($raw)) throw new DomainException('Valoare neașteptată.');
    [$label,$type]=$field;
    $raw=trim($raw);
    if ($raw==='') return '';
    $reject=static function () use ($label) { throw new DomainException('Verifică câmpul „'.$label.'”.'); };
    if (in_array($type,['image','video','gallery','page'],true)) {
        $values=$type==='gallery'?explode(',',$raw):[$raw];
        if (count($values)>24) $reject();
        $ids=[];
        foreach ($values as $id) {
            if (!ctype_digit($id) || !(int)$id || !($type==='page'?ts_public_page((int)$id):ts_public_media((int)$id,$type))) $reject();
            $ids[]=(int)$id;
        }
        return $type==='gallery'?implode(',',array_unique($ids)):$ids[0];
    }
    if ($type==='select' && !array_key_exists($raw,$field[2])) $reject();
    if ($type==='date') {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$raw);
        if (!$d || $d->format('Y-m-d')!==$raw) $reject();
    }
    if ($type==='email' && !is_email($raw)) $reject();
    if ($type==='tel' && !preg_match('/^[+0-9 ()-]{3,30}$/D',$raw)) $reject();
    if ($type==='url' && (!filter_var($raw,FILTER_VALIDATE_URL) || wp_parse_url($raw,PHP_URL_USER)!==null || wp_parse_url($raw,PHP_URL_PASS)!==null || wp_parse_url($raw,PHP_URL_SCHEME)!=='https')) $reject();
    if ($type==='check' && $raw!=='1') $reject();
    if (strlen($raw)>($type==='area'?3000:600)) $reject();
    return $type==='area'?sanitize_textarea_field($raw):sanitize_text_field($raw);
}

// A single compare-and-swap protects drafts and publication from stale tabs and simultaneous saves.
function ts_store(array $before,array $after): bool {
    global $wpdb;
    unset($GLOBALS['tiron_studio_state']);
    $after['version']=$before['version']+1;
    $name='tiron_brand_studio_v1';
    if (!$before['version']) {
        // A unique option name also serializes simultaneous first saves.
        $ok=$wpdb->insert($wpdb->options,['option_name'=>$name,'option_value'=>maybe_serialize($after),'autoload'=>'no']);
        wp_cache_delete('notoptions','options');
        return $ok===1;
    }
    $changed=$wpdb->update($wpdb->options,['option_value'=>maybe_serialize($after)],['option_name'=>$name,'option_value'=>maybe_serialize($before)]);
    wp_cache_delete($name,'options');
    return $changed===1;
}

function ts_validate_release(array $draft): void {
    foreach (ts_groups() as [,$fields]) foreach ($fields as $key=>$field) {
        if (isset($draft[$key])) ts_clean((string)$draft[$key],$field);
    }
    $set=array_intersect_key($draft,array_flip(['tiron_image_original','tiron_image_relief','tiron_image_relief-unlit']));
    if ($set && count($set)!==3) throw new DomainException('Alege toate cele trei imagini ale comparației, cu același subiect și cadraj.');
    if (isset($draft['start'],$draft['end']) && $draft['start']>$draft['end']) throw new DomainException('Verifică perioada anunțului.');
    for ($i=1;$i<=6;$i++) if (isset($draft['q'.$i])!==isset($draft['a'.$i])) throw new DomainException('Completează întrebarea și răspunsul FAQ '.$i.'.');
}
