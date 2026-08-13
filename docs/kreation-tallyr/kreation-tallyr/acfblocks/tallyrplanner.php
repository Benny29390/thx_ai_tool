<?php
add_action('acf/init', 'acf_init_tallyrplanner');
function acf_init_tallyrplanner() {

    if( function_exists('acf_register_block_type') ) {

        acf_register_block_type(array(
            'name'              => 'tallyrplanner',
            'title'             => __('Tallyr: Planner'),
            'render_template'   => 'blocks/tallyrplanner.php',
            'category'          => 'codesueyblocks',
            'icon'              => 'calendar-alt',
            'keywords'          => array( 'Planner', 'Wochenplaner', 'Tasks', 'Aufgaben', 'Todoist' ),
            'align' => 'full',
            'example'  => array(
	            'attributes' => array(
	                'mode' => 'preview',
	                'data' => array(
	                	'preview_image_help' => 'blockpreviews/tallyrplanner.png',
	                )
	            )
	        )
        ));
    }
}

if( function_exists('acf_add_local_field_group') ):

    acf_add_local_field_group(array(
        'key' => 'group_tallyrplanner_001',
        'title' => 'Tallyr: Planner',
        'fields' => array(

        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/tallyrplanner',
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
