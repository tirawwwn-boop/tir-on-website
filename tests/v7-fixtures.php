<?php
// Use only inside the disposable WordPress fixture.
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || !str_contains(ABSPATH, 'tiron-atelier-qa-')) {
    throw new RuntimeException('Fixture locală necesară.');
}
$id=t_luna_id();
switch ($args[0]) {
    case 'seed':
        set_theme_mod('tiron_hero_title','Copertă V5 sintetică');
        update_option('tiron_profit_v1',['fixture'=>'profit-v5']);
        update_post_meta($id,'_tiron_packaging','Ambalaj V5 sintetic');
        $order=wc_create_order();
        $order->add_product(wc_get_product($id),2);
        $line=array_values($order->get_items())[0];
        $line->update_meta_data('_tiron_notes','Observație sintetică');
        $line->update_meta_data('_tiron_photo_id','private-fixture-id');
        $line->save();
        $order->update_meta_data('_tiron_atelier_workflow',['stage'=>'quality_control']);
        $order->calculate_totals();
        $order->save();
        update_option('v7_qa_order',$order->get_id());
        break;
    case 'snapshot':
        $order=wc_get_order(get_option('v7_qa_order'));
        $line=array_values($order->get_items())[0];
        echo wp_json_encode([get_theme_mods()['tiron_hero_title'],get_option('tiron_profit_v1'),get_post_meta($id,'_tiron_packaging',true),
            $order->get_id(),$order->get_status(),$order->get_total(),$order->get_meta('_tiron_atelier_workflow'),
            $line->get_meta('_tiron_notes'),$line->get_meta('_tiron_photo_id'),wc_get_product($id)->get_price(),
            get_option('woocommerce_stripe_settings'),get_option('woocommerce_enable_guest_checkout'),get_option('tiron_luna_product_id')]);
        break;
    case 'login':
        $result=[];
        foreach (['administrator','subscriber'] as $role) {
            $user=get_users(['role'=>$role,'number'=>1])[0]->ID ?? wp_insert_user(['user_login'=>'qa-'.$role,'user_pass'=>wp_generate_password(48),'role'=>$role]);
            $expires=time()+1800;
            $token=WP_Session_Tokens::get_instance($user)->create($expires);
            $result[$role]=[AUTH_COOKIE=>wp_generate_auth_cookie($user,$expires,'auth',$token), LOGGED_IN_COOKIE=>wp_generate_auth_cookie($user,$expires,'logged_in',$token)];
        }
        echo wp_json_encode($result);
        break;
    case 'media':
        $asset=wp_upload_bits('material-public-qa.png',null,file_get_contents(get_theme_file_path('assets/brand/favicon-32.png')));
        if ($asset['error']) throw new RuntimeException('Media fixture');
        $post=['post_title'=>'Material public QA','post_mime_type'=>'image/png','post_status'=>'inherit'];
        $media=wp_insert_attachment($post,$asset['file']);
        $private=wp_insert_attachment($post,TIRON_PRIVATE_PHOTO_DIR.'/qa-private.png');
        $page=wp_insert_post(['post_status'=>'publish','post_type'=>'page','post_title'=>'Informații QA']);
        echo wp_json_encode([$media,$private,$page]);
        break;
}
