<?php
add_action('acf/init', 'acf_init_tallyrprojektplanner');
function acf_init_tallyrprojektplanner() {

    if( function_exists('acf_register_block_type') ) {

        acf_register_block_type(array(
            'name'              => 'tallyrprojektplanner',
            'title'             => __('Tallyr: Projektplanner'),
            'render_template'   => 'blocks/tallyrprojektplanner.php',
            'category'          => 'codesueyblocks',
            'icon'              => 'media-spreadsheet',
            'keywords'          => array( 'Projektplanner', 'Quartalsplanner', 'Plan' ),
            'align' => 'full',
            'example'  => array(
	            'attributes' => array(
	                'mode' => 'preview',
	                'data' => array(
	                	'preview_image_help' => 'blockpreviews/tallyrprojektplanner.png',
	                )
	            )
	        )
        ));
    }
}

if( function_exists('acf_add_local_field_group') ):

    acf_add_local_field_group(array(
        'key' => 'group_projektplanner_block_1',
        'title' => 'Tallyr: Projektplanner',
        'fields' => array(

        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/tallyrprojektplanner',
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