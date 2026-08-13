<?php
add_action('acf/init', 'acf_init_tallyrrecurring');
function acf_init_tallyrrecurring() {

    if( function_exists('acf_register_block_type') ) {

        acf_register_block_type(array(
            'name'              => 'tallyrrecurring',
            'title'             => __('Tallyr: Recurring'),
            'render_template'   => 'blocks/tallyrrecurring.php',
            'category'          => 'codesueyblocks',
            'icon'              => 'update',
            'keywords'          => array( 'Recurring', 'Wiederkehrend', 'Rechnung' ),
            'align' => 'full',
            'example'  => array(
	            'attributes' => array(
	                'mode' => 'preview',
	                'data' => array(
	                	'preview_image_help' => 'blockpreviews/tallyrrecurring.png',
	                )
	            )
	        )
        ));
    }
}

if( function_exists('acf_add_local_field_group') ):

    acf_add_local_field_group(array(
        'key' => 'group_tallyrrecurring_001',
        'title' => 'Tallyr: Recurring',
        'fields' => array(

        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/tallyrrecurring',
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
