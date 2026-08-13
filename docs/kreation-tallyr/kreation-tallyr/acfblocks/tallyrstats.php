<?php
add_action('acf/init', 'acf_init_tallyrstats');
function acf_init_tallyrstats() {

    if( function_exists('acf_register_block_type') ) {

        acf_register_block_type(array(
            'name'              => 'tallyrstats',
            'title'             => __('Tallyr: Stats'),
            'render_template'   => 'blocks/tallyrstats.php',
            'category'          => 'codesueyblocks',
            'icon'              => 'cover-image',
            'keywords'          => array( 'Hero', 'Header' ),
            'align' => 'full',
            'example'  => array(
	            'attributes' => array(
	                'mode' => 'preview',
	                'data' => array(
	                	'preview_image_help' => 'blockpreviews/tallyrstats.png',
	                )
	            )
	        )
        ));
    }
}

if( function_exists('acf_add_local_field_group') ):

    acf_add_local_field_group(array(
        'key' => 'group_s2aaf21ca21ce',
        'title' => 'Tallyr: Stats',
        'fields' => array(
            /* child_global_block_fields(), */
            
        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/tallyrstats',
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