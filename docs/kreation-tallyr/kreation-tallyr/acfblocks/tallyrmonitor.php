<?php
add_action('acf/init', 'acf_init_tallyrmonitor');
function acf_init_tallyrmonitor() {

    if( function_exists('acf_register_block_type') ) {

        acf_register_block_type(array(
            'name'              => 'tallyrmonitor',
            'title'             => __('Tallyr: Monitor'),
            'render_template'   => 'blocks/tallyrmonitor.php',
            'category'          => 'codesueyblocks',
            'icon'              => 'visibility',
            'keywords'          => array( 'Monitor', 'Uptime' ),
            'align' => 'full',
            'example'  => array(
	            'attributes' => array(
	                'mode' => 'preview',
	                'data' => array(
	                	'preview_image_help' => 'blockpreviews/tallyrmonitor.png',
	                )
	            )
	        )
        ));
    }
}

if( function_exists('acf_add_local_field_group') ):

    acf_add_local_field_group(array(
        'key' => 'group_monitor_block_1',
        'title' => 'Tallyr: Monitor',
        'fields' => array(

        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/tallyrmonitor',
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