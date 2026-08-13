<?php
add_action('acf/init', 'acf_init_tallyreinstellungen');
function acf_init_tallyreinstellungen() {

    if( function_exists('acf_register_block_type') ) {

        acf_register_block_type(array(
            'name'              => 'tallyreinstellungen',
            'title'             => __('Tallyr: Einstellungen'),
            'render_template'   => 'blocks/tallyreinstellungen.php',
            'category'          => 'codesueyblocks',
            'icon'              => 'admin-generic',
            'keywords'          => array( 'Einstellungen', 'Settings', 'Asana' ),
            'align' => 'full',
            'example'  => array(
	            'attributes' => array(
	                'mode' => 'preview',
	                'data' => array(
	                	'preview_image_help' => 'blockpreviews/tallyreinstellungen.png',
	                )
	            )
	        )
        ));
    }
}

if( function_exists('acf_add_local_field_group') ):

    acf_add_local_field_group(array(
        'key' => 'group_einstellungen_block_1',
        'title' => 'Tallyr: Einstellungen',
        'fields' => array(),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/tallyreinstellungen',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
        'show_in_rest' => 0,
    ));

    endif;