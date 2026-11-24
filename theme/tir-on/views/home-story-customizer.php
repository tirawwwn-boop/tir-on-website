<?php
/**
 * Editable homepage copy shared by the WordPress Customizer and the templates.
 */
function tiron_story_fields() {
    return [
        'transformation_title' => [ 'Titlul secțiunii Transformarea', 'Din fotografie în lumină.' ],
        'transformation_text'  => [ 'Descrierea transformării', 'Imprimăm fotografia în relief. Zonele mai subțiri lasă să treacă mai multă lumină, iar cele mai groase păstrează umbrele. Așa reapare imaginea ta.' ],
        'steps_title'          => [ 'Titlul secțiunii Cum funcționează', 'În trei pași, acasă la tine.' ],
        'gift_title'           => [ 'Titlul secțiunii Cadouri', 'Pentru cine aprinzi Luna?' ],
        'final_title'          => [ 'Titlul invitației finale', 'Dă lumină unei amintiri.' ],
    ];
}

function tiron_story_text( $key ) {
    $fields = tiron_story_fields();
    $default = $fields[ $key ][1];
    return function_exists( 'get_theme_mod' ) ? get_theme_mod( 'tiron_' . $key, $default ) : $default;
}

if ( function_exists( 'add_action' ) ) {
    add_action( 'customize_register', function ( $customizer ) {
        $customizer->add_section( 'tiron_story_copy', [
            'title'    => 'TIR-ON · Povestea de pe pagina principală',
            'priority' => 150,
        ] );
        $customizer->add_setting('tiron_image_relief-unlit', ['default' => 0, 'sanitize_callback' => 'absint']);
        $customizer->add_control(new WP_Customize_Media_Control($customizer, 'tiron_image_relief-unlit', [
            'label' => 'Imagine pentru detaliul de relief',
            'section' => 'tiron_story_copy',
            'mime_type' => 'image',
        ]));
        foreach ( tiron_story_fields() as $key => $field ) {
            $setting = 'tiron_' . $key;
            $customizer->add_setting( $setting, [
                'default'           => $field[1],
                'sanitize_callback' => 'sanitize_text_field',
            ] );
            $customizer->add_control( $setting, [
                'label'   => $field[0],
                'section' => 'tiron_story_copy',
                'type'    => 'transformation_text' === $key ? 'textarea' : 'text',
            ] );
        }
    } );
}
