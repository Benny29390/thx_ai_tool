<?php
function smv_formatTextArea($input) {
    $lines = explode("\n", $input);
    $output = '<div class="smvformat">';
    foreach ($lines as $line) {
        $words = explode(" ", $line);
        $lineOutput = '';
        foreach ($words as $word) {
            if ($word === '…') {
                $lineOutput .= $word . '';
            } else {
                $lineOutput .= '<b>' . mb_substr($word, 0, 1, 'UTF-8') . '</b>' . mb_substr($word, 1, null, 'UTF-8') . ' ';
            }
        }
        $output .= '<div>' . trim($lineOutput) . '</div>';
    }
    $output .= '</div>';
    return $output;
}

function child_kreation_replace_tags($string = '', $pid = 0) {
	
   /*  return $string; */

	$string = preg_replace("/<p>\s*{{(.*)}}\s*<\/p>/", "{{\$1}}", $string);
	return preg_replace_callback('/\\{\\{([^{}]+)\}\\}/',
		function($matches) use ($pid)
		{
			$meta_arr = explode('|', $matches[1]);

			
			if ($meta_arr[0] == 'smv_piktogramme') {
                $piktos = get_post_meta($pid, 'taxonomy_piktogram', true);
                if (!$piktos) {
                    return $meta;
                }
                $meta .= '<div class="smvicons">';
                foreach ($piktos as $pik) {
                    $pikto_icon = get_field('pikto_icon', 'piktogram_' . $pik);
                    $pikto_icon = wp_get_attachment_image_src($pikto_icon, 'full');
                    $term_name = get_term( $pik )->name;
                    $meta .= '<div class="smvicon">';
                    $meta .= '<img alt="'.$term_name.' Icon" src="'.$pikto_icon[0].'" width="'.$pikto_icon[1].'" height="'.$pikto_icon[2].'" loading="lazy">';
					if (function_exists('weglot_get_current_language')) {
						$currentlang = weglot_get_current_language();
						if ($currentlang == 'de') {
							$meta .= '<div>'.$term_name.'</div>';
						} else {
    						/* $url = "https://api.weglot.com/translate?api_key=wg_61c92e35b32a497ada16b9eefdb724225&text=" . urlencode($term_name) . "&source=de&target=" . $currentlang;
							$response = wp_remote_get($url);
							if (!is_wp_error($response)) {
								$body = wp_remote_retrieve_body($response);
								$data = json_decode($body, true);
								$meta .= '<div>'.$data['data']['translations'][0]['text'].'</div>';
							} */
						}
					}
                    $meta .= '</div>';
                };
                $meta .= '</div>';
                $meta_arr = null;
			} else if ($meta_arr[0] == 'smv_imageurl') {
                $image = wp_get_attachment_url(get_post_meta($pid, $meta_arr[1], true));
                if ($image) {
                    $meta = '<img style="width: 49%" src="'.$image.'" />';
                }
                $meta_arr = null;
                
            } else if ($meta_arr[0] == 'smv_mainimage') {
                $image = get_the_post_thumbnail_url($pid, 'full');
                if ($image) {
                    $meta = '<img style="width: 100%" src="'.$image.'" />';
                }
                $meta_arr = null;
                
            } else if ($meta_arr[0] == 'smv_web3d') {
				$metalink = $meta_arr[3];
				if (filter_var($metalink, FILTER_VALIDATE_URL) === FALSE) {
					$metalink = get_post_meta($pid, $metalink, true);
				}
                if ($metalink) {
					if (strpos($metalink, 'Kein Konfigurator') !== false) {
						$metalink = '';
					} else if (strpos($metalink, '@FOLDER') !== false) {
                        $metalink = 'parentnode='.$metalink;
                    } else {
                        $metalink = 'permanentid='.$metalink;
                    }

					$language = '';
					if (function_exists('weglot_get_current_language')) {
						$language = weglot_get_current_language();	
					}
					if ($language == 'en') {
						$language = '&lang=en';
					} else {
						$language = '&lang=de';
					}

                    if ($metalink) {
						$meta = '<a class="iframe btn '.$meta_arr[1].'" target="_blank" href="'.$meta_arr[2].$metalink.'&showprice=false'.$language.'">'.$meta_arr[4].'</a>';
					} else {
						$meta = '';
					}
                }
				$meta_arr = null;
			} else if ($meta_arr[0] == 'smv_backlink') {
				$ptitle = get_post_meta($pid, $meta_arr[1], true);

				if ($ptitle) {
					global $wpdb;
					$linkpost = (int)$wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title REGEXP '%s' AND post_type='page'", $ptitle));
					
					if ($linkpost) {
						$meta = '<a class="smvbacklink" title="'.$ptitle.'" href="'.get_the_permalink($linkpost).'">» '.$ptitle.'</a>';
					}
				}
                
				$meta_arr = null;
			} else if ($meta_arr[0] == 'smv_pdflink') {
				$ptitle = get_post_meta($pid, $meta_arr[1], true);

				if ($ptitle) {
					global $wpdb;
					$pdf = (int)$wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title='%s' AND post_type='pdfs' LIMIT 1", $ptitle));
					if ($pdf) {
						$preview = get_post_meta($pdf, 'pdf_preview', true);
						$url = get_post_meta($pdf, 'pdf_url', true);
    					$title = get_the_title($pdf);

						if ($preview && $url) {
							$meta = '<a class="smvpdflink" title="'.$title.'" href="'.$url.'" target="_blank"><img loading="lazy" src="'.$preview.'" /><span>'.$title.'</span></a>';
						}
					}
				}
                
				$meta_arr = null;
			}else if ($meta_arr[0] == 'smv_pdflink_xls') {

				$language = '';
				if (function_exists('weglot_get_current_language')) {
					$language = weglot_get_current_language();	
				}
				if ($language == 'en') {
					$p_imagepdf = 'p_imagepdf_en';
				} else {
					$p_imagepdf = 'p_imagepdf';
				}

				$p_imagepdf = get_post_meta($pid, $p_imagepdf, true);

				if ($p_imagepdf) {
					// Explide neu line
					$p_imagepdf = explode("\n", $p_imagepdf);
				}

				$meta = '<div class="smvpdflinkswrap">';
				foreach ($p_imagepdf as $p_imagepd) {
					if ($p_imagepd) {
						$siteurl = site_url('wp-content/files');
						$url = $siteurl.$p_imagepd;
						$preview = $siteurl.str_replace('.pdf', '.jpg', $p_imagepd);
						
						if ($preview && $url) {
							$meta .= '<a class="smvpdflink" title="'.$title.'" href="'.$url.'" target="_blank"><img loading="lazy" src="'.$preview.'" /><span>'.$title.'</span></a>';
						}
					}
				}
				$meta .= '</div>';
                
				$meta_arr = null;
			} else if ($meta_arr[0] == 'smv_videobutton') {
				$pvideo = get_post_meta($pid, $meta_arr[1], true);

				if ($pvideo) {
					$meta = '<a data-muted="1" class="smv_videobutton csl btn accent" href="'.site_url().'/wp-content/'.$pvideo.'"><i class="bx bxs-videos"></i><span>Video</span></a>';
				}
                
				$meta_arr = null;
			} else if ($meta_arr[0] == 'smv_product_auszeichnung') {
				$auszeichnungen = get_post_meta($pid, 'taxonomy_auszeichnung', true);

				if ($auszeichnungen) {
					$meta = '<div class="ausz">';
						foreach ($auszeichnungen as $aus) {
							$graphic = get_field('ausz_icon', 'auszeichnung_' . $aus);
							if (!$graphic) {
								continue;
							}
							$graphic = wp_get_attachment_image_src($graphic, 'full');
							$term_name = get_term( $aus )->name;
							$meta .= '<div><img alt="'.$term_name.'" src="'.$graphic[0].'" width="'.$graphic[1].'" height="'.$graphic[2].'" loading="lazy"><span style="opacity:0">'.$term_name.'</span></div>';

						}
					$meta .= '</div>';
				}
                
				$meta_arr = null;
			} else {
                return $matches[0];
            }
			

			return $meta;
		}
		, $string);
}

if (!function_exists('child_global_block_fields')) {
	function child_global_block_fields() {

		$selectdev = array();
		$kre_divd = get_option('options_kre_dividers');
		$selectdev[''] = '-';
		if (!$kre_divd) {
			$kre_divd = 0;	
		}
		$i = 0;
		while($i < $kre_divd) {
			$selectdev[get_option('options_kre_dividers_'.$i.'_did')] = get_option('options_kre_dividers_'.$i.'_dname');
			$i++;
		}


		$array = array(
			'key' => 'field_ac1233456bce2',
			'label' => 'Blockeinstellungen',
			'name' => 'kre_glooptions',
			'type' => 'group',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'layout' => 'block',
			'sub_fields' => array(
				array(
					'key' => 'field_1a21cd218dadd',
					'label' => 'In Toggle verstecken',
					'name' => 'v_toggler',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
					'ui' => 1,
					'ui_on_text' => '',
					'ui_off_text' => '',
				),
				array(
					'key' => 'field_a7aa21c2f0a12',
					'label' => 'Toggle Container',
					'name' => 'v_togglercont',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_1a21cd218dadd',
								'operator' => '==',
								'value' => 1,
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'container' => 'Normal',
						'container small' => 'Schmal',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => 'container',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_1aa21acddbfd7',
					'label' => 'Text für Toggle',
					'name' => 'v_togglertext',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_1a21cd218dadd',
								'operator' => '==',
								'value' => 1,
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
				),
				array(
					'key' => 'field_12ca112af9ac3',
					'label' => 'Toggle: Hintergrundfarbe Titel',
					'name' => 'v_toggler_color',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_1a21cd218dadd',
								'operator' => '==',
								'value' => 1,
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'bgcolor_white' => 'Weiß',
						'bgcolor_1' => 'Hintergrund 1',
						'bgcolor_2' => 'Hintergrund 2',
						'bgcolor_3' => 'Hintergrund 3',
						'bgcolor_4' => 'Hintergrund 4',
						'bgcolor_grey' => 'Helles Grau',
						'bgcolor_light' => 'Haupt-Farbe (hell)',
						'bgcolor_accent' => 'Haupt-Farbe',
						'bgcolor_dark' => 'Haupt-Farbe (dunkel)',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => 'bgcolor_accent',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_12c21c2c292c1',
					'label' => 'Toggle: Schriftfarbe Titel',
					'name' => 'v_toggler_typo',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_1a21cd218dadd',
								'operator' => '==',
								'value' => 1,
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'tcolor_normal' => 'Normal (Dunkel)',
						'tcolor_1' => 'Hintergrund 1: Text',
						'tcolor_2' => 'Hintergrund 2: Text',
						'tcolor_3' => 'Hintergrund 3: Text',
						'tcolor_4' => 'Hintergrund 4: Text',
						'tcolor_light' => 'Haupt-Farbe (hell)',
						'tcolor_accent' => 'Haupt-Farbe',
						'tcolor_dark' => 'Haupt-Farbe (dunkel)',
						'tcolor_wh' => 'Weiß',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => 'tcolor_wh',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_12cc21c1f92ca',
					'label' => 'Toggle: Zustand Aktiv',
					'name' => 'v_toggler_active',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_1a21cd218dadd',
								'operator' => '==',
								'value' => 1,
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'active_1' => 'Keine Änderung',
						'active_2' => 'Haupt-Farbe / Weiß',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => 'active_1',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_1c21ac8d8dadd',
					'label' => 'Deaktivieren',
					'name' => 'v_inactive',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
					'ui' => 1,
					'ui_on_text' => '',
					'ui_off_text' => '',
				),
				array(
					'key' => 'field_1c25acfddbfd7',
					'label' => 'Veröffentlichen am',
					'name' => 'v_date',
					'type' => 'date_picker',
					'instructions' => 'Ab diesem Datum wird das Element angezeigt',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'display_format' => 'd.m.Y',
					'return_format' => 'U',
					'first_day' => 1,
				),
				array(
					'key' => 'field_1c2aceaccbfda',
					'label' => 'Zeigen bis',
					'name' => 'v_date_til',
					'type' => 'date_picker',
					'instructions' => 'Ab diesem Datum wird das Element versteckt',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'display_format' => 'd.m.Y',
					'return_format' => 'U',
					'first_day' => 1,
				),
				array(
					'key' => 'field_212cdaa1f0803',
					'label' => 'Innerer Abstand',
					'name' => 'v_spacing',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'' => 'Keinen',
						'sp_u sp_b' => 'Oben & unten',
						'sp_u' => 'Nur oben',
						'sp_b' => 'Nur unten',
						'spp_u spp_b' => 'Oben & unten (klein)',
						'spp_u' => 'Nur oben (klein)',
						'spp_b' => 'Nur unten (klein)',
						'sppp_u sppp_b' => 'Oben & unten (sehr klein)',
						'sppp_u' => 'Nur oben (sehr klein)',
						'sppp_b' => 'Nur unten (sehr klein)',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => 'spp_u spp_b',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_acdadad1f0803',
					'label' => 'Hintergrundfarbe',
					'name' => 'v_backgroundcolor',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'' => 'Weiss',
						'bgcolor_1' => 'Hintergrund 1',
						'bgcolor_2' => 'Hintergrund 2',
						'bgcolor_3' => 'Hintergrund 3',
						'bgcolor_4' => 'Hintergrund 4',
						'bgcolor_light' => 'Haupt-Farbe (hell)',
						'bgcolor_grey' => 'Helles Grau',
						'bgcolor_accent' => 'Haupt-Farbe',
						'bgcolor_dark' => 'Haupt-Farbe (dunkel)',
						'bg_gradient_1' => 'Verlauf: Haupt-Farbe -> Haupt-Farbe (dunkel)',
						'bg_gradient_2' => 'Verlauf: Haupt-Farbe -> Haupt-Farbe (hell)',
						'bg_gradient_3' => 'Verlauf: Hintergrund 1 -> Hintergrund 2',
						'bgcolor_transparent' => 'Transparenz',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => '',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_d91deac2f0803',
					'label' => 'Schriftfarbe',
					'name' => 'v_typocolor',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'' => 'Normal (Dunkel)',
						'tcolor_1' => 'Hintergrund 1: Text',
						'tcolor_2' => 'Hintergrund 2: Text',
						'tcolor_3' => 'Hintergrund 3: Text',
						'tcolor_4' => 'Hintergrund 4: Text',
						'tcolor_light' => 'Haupt-Farbe (hell)',
						'tcolor_accent' => 'Haupt-Farbe',
						'tcolor_dark' => 'Haupt-Farbe (dunkel)',
						'tcolor_wh' => 'Weiß',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => '',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_d92c2122f0803',
					'label' => 'Linkfarbe',
					'name' => 'v_linkcolor',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'' => 'Normal',
						'lcolor_1' => 'Hintergrund 1: Text',
						'lcolor_2' => 'Hintergrund 2: Text',
						'lcolor_2' => 'Hintergrund 3: Text',
						'lcolor_2' => 'Hintergrund 4: Text',
						'lcolor_light' => 'Haupt-Farbe (hell)',
						'lcolor_accent' => 'Haupt-Farbe',
						'lcolor_dark' => 'Haupt-Farbe (dunkel)',
						'lcolor_wh' => 'Weiß',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => '',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_111221ca91cdd',
					'label' => 'Beitragsbild als Hintergrund',
					'name' => 'v_patternthumb',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
					'ui' => 1,
					'ui_on_text' => '',
					'ui_off_text' => '',
				),
				array(
					'key' => 'field_6acd9f6288a53',
					'label' => 'Hintergrundbild',
					'name' => 'v_pattern',
					'type' => 'image',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'return_format' => 'id',
					'preview_size' => 'medium',
					'library' => 'all',
					'min_width' => '',
					'min_height' => '',
					'min_size' => '',
					'max_width' => '',
					'max_height' => '',
					'max_size' => '',
					'mime_types' => '',
				),
				array(
					'key' => 'field_d999aac2f0803',
					'label' => 'Hintergrundbild Größe',
					'name' => 'v_pattern_size',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_6acd9f6288a53',
								'operator' => '!=empty',
							),
						),
						array(
							array(
								'field' => 'field_111221ca91cdd',
								'operator' => '!=',
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'cover' => 'Cover',
						'contain' => 'Repeat',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => '',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_aedccdaebacf9',
					'label' => 'Hintergrundbild Effekt',
					'name' => 'v_pattern_effect',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_6acd9f6288a53',
								'operator' => '!=empty',
							),
						),
						array(
							array(
								'field' => 'field_111221ca91cdd',
								'operator' => '!=',
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'inherit' => 'Keinen',
						'multiply' => 'Multiplizieren',
						'color' => 'Color',
						'color-burn' => 'Color Burn',
						'color-dodge' => 'Color Dodge',
						'darken' => 'Darken',
						'difference' => 'Difference',
						'exclusion' => 'Exclusion',
						'hard-light' => 'Hard Light',
						'lighten' => 'Lighten',
						'luminosity' => 'Luminosity',
						'overlay' => 'Overlay',
						'plus-lighter' => 'Plus Lighter',
						'saturation' => 'Saturation',
						'screen' => 'Screen',
						'soft-light' => 'Soft Light',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => 'inherit',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_121281818dadd',
					'label' => 'Parallax für Hintergrund',
					'name' => 'v_pattern_para',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_6acd9f6288a53',
								'operator' => '!=empty',
							),
						),
						array(
							array(
								'field' => 'field_111221ca91cdd',
								'operator' => '!=',
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
					'ui' => 1,
					'ui_on_text' => '',
					'ui_off_text' => '',
				),
				array (
					'key' => 'field_5ac992ac2cba1',
					'label' => __('Hintergrundbild Transparenz', 'affiliatetheme-backend'),
					'name' => 'v_pattern_opa',
					'type' => 'range',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_6acd9f6288a53',
								'operator' => '!=empty',
							),
						),
						array(
							array(
								'field' => 'field_111221ca91cdd',
								'operator' => '!=',
							),
						),
					),
					'wrapper' => array (
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => 80,
					'placeholder' => '',
					'prepend' => '',
					'min' => 0,
					'max' => 100,
					'step' => 1,
					'append' => '%',
					'maxlength' => '',
					'readonly' => 0,
					'disabled' => 0,
				),
				array(
					'key' => 'field_1ceacda1f0803',
					'label' => 'Animation',
					'name' => 'v_animation',
					'type' => 'select',
					'instructions' => 'Animation muss in den Theme Options aktiviert sein.',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'' => 'Keine',
						'lhl' => 'Von links',
						'lhr' => 'Von rechts',
						'lhb' => 'Von unten',
						'lht' => 'Von oben',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => '',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_2aceac22f0803',
					'label' => 'Design Trenner oben',
					'name' => 'v_divd_top',
					'type' => 'select',
					'instructions' => 'Kann unter den Themeoptionen festgelegt werden',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => $selectdev,
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => '',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_da32aa29f0803',
					'label' => 'Design Trenner oben: Position',
					'name' => 'v_divd_top_pos',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_2aceac22f0803',
								'operator' => '!=empty',
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'absolute' => 'Überlagernd',
						'relative' => 'Anfügen',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => 'absolute',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_223ac122f0803',
					'label' => 'Design Trenner unten',
					'name' => 'v_divd_bot',
					'type' => 'select',
					'instructions' => 'Kann unter den Themeoptionen festgelegt werden',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => $selectdev,
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => '',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_fa2acc29f0803',
					'label' => 'Design Trenner unten: Position',
					'name' => 'v_divd_bot_pos',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_223ac122f0803',
								'operator' => '!=empty',
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'absolute' => 'Überlagernd',
						'relative' => 'Anfügen',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => 'absolute',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_1acaf941f0803',
					'label' => 'Sichtbarkeit',
					'name' => 'v_showon',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						's_both' => 'Desktop / Mobil',
						's_mobile' => 'Mobil',
						's_desktop' => 'Desktop',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => 's_both',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_1eeeacfddbfd7',
					'label' => 'Navigationstext (optional)',
					'name' => 'v_navi',
					'type' => 'text',
					'instructions' => 'Für Inhaltsverzeichnis (falls vorhanden)',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
				),
				array(
					'key' => 'field_1ac2f8addbfa2',
					'label' => 'Sticky Scroll Text',
					'name' => 'v_stickys',
					'type' => 'text',
					'instructions' => 'Für Sticky Scroll Link (falls vorhanden)',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
				),
				array(
					'key' => 'field_121a21218dadd',
					'label' => 'Sticky',
					'name' => 'v_sticky',
					'type' => 'true_false',
					'instructions' => 'Nur wenn in Spalten',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
					'ui' => 1,
					'ui_on_text' => '',
					'ui_off_text' => '',
				),
				array(
					'key' => 'field_2a21c2acf0a1a',
					'label' => 'Nutzer Sichtbarkeit',
					'name' => 'v_rights',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array(
						'all' => 'Sichtbar für jeden',
						'onlylog' => 'Nur eingeloggte Nutzer',
						'notlog' => 'Nur ausgeloggte Nutzer',
					),
					'allow_null' => 0,
					'other_choice' => 0,
					'default_value' => 'all',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_1efaeacddbfd7',
					'label' => 'ID',
					'name' => 'v_id',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
				),
			),
		);

		return $array;
	}
}

if (!function_exists('dynamique_block_fields')) {
	function dynamique_block_fields() {

		/* $selectdev = array();
		$kre_divd = get_option('options_kre_dividers');
		$selectdev[''] = '-';
		if (!$kre_divd) {
			$kre_divd = 0;	
		}
		$i = 0;
		while($i < $kre_divd) {
			$selectdev[get_option('options_kre_dividers_'.$i.'_did')] = get_option('options_kre_dividers_'.$i.'_dname');
			$i++;
		} */

		$selectableoptions = array(
			'' => '',
			'audience' => 'Zielgruppe',
			'getparameter' => 'GET Parameter',
		);

		$langsop = array('' => '');
		if (function_exists('weglot_get_destination_languages')) {
			$langs = weglot_get_destination_languages();
			$langsop[] = array('de' => 'de');
			foreach ($langs as $la) {
				$langsop[] = array($la['language_to'] => $la['language_to']);
			}
			$selectableoptions['lang'] = 'Sprache';
		} else if (function_exists('pll_the_languages')) {
			$langs = pll_languages_list(array('fields' => array()));
			foreach ($langs as $la) {
				if ($la->slug) {
					$langsop[$la->slug] = $la->slug;
				}
			}
			$selectableoptions['lang'] = 'Sprache';
		}  else if (function_exists('linguiseGetOptions')) { 
			$langs = linguiseGetOptions()['enabled_languages'];
			$langsop['de'] = 'de';
			foreach ($langs as $la) {
				$langsop[$la] = $la;
			}
			$selectableoptions['lang'] = 'Sprache';
		}


		$array = array(
			'key' => 'field_ac1ac1a26bce2',
			'label' => 'Dynamisierung',
			'name' => 'kre_dynamique',
			'type' => 'group',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'layout' => 'block',
			'sub_fields' => array(
				array(
					'key' => 'field_2a21c2afa0a1a',
					'label' => 'Zeige diesen Block nur an, wenn:',
					'name' => 'd_if',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => $selectableoptions,
					'allow_null' => 1,
					'other_choice' => 0,
					'default_value' => '',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_2ffaacfdf2aa7',
					'label' => 'GET Parameter ... vorhanden:',
					'name' => 'd_getparameter',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_2a21c2afa0a1a',
								'operator' => '==',
								'value' => 'getparameter',
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
				),
				array(
					'key' => 'field_2ffaacfddbfa7',
					'label' => 'Zielgruppe ist:',
					'name' => 'd_audience',
					'type' => 'text',
					'instructions' => 'Für mehrere Zielgruppen, mit "-" trennen. Zum Bsp.: 1-3-2',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_2a21c2afa0a1a',
								'operator' => '==',
								'value' => 'audience',
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
				),
				array(
					'key' => 'field_2a22a1cfa0a1a',
					'label' => 'Sprache ist:',
					'name' => 'd_langs',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_2a21c2afa0a1a',
								'operator' => '==',
								'value' => 'lang',
							),
						),
					),
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => $langsop,
					'allow_null' => 1,
					'other_choice' => 0,
					'default_value' => '',
					'layout' => 'vertical',
					'return_format' => 'value',
					'save_other_choice' => 0,
				),
				array(
					'key' => 'field_1221acf18dadd',
					'label' => 'Standard Content',
					'name' => 'd_standard',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
					'ui' => 1,
					'ui_on_text' => '',
					'ui_off_text' => '',
				),
				array(
					'key' => 'field_2ff21ac2f5a21',
					'label' => 'Nicht zeigen, wenn weniger als:',
					'name' => 'd_minshow',
					'type' => 'number',
					'instructions' => '',
					'append' => 'Zeichen',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
				),
			),
		);

		return $array;
	}
}