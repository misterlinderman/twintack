<?php

class Ced_Amazon_Html_Tags {

	public $extra_attributes_to_skip = array( 'woo_tax', 'amz_tax', 'MFN', 'AFN', 'both' );


	public function print_label( $title = '', $tooltip_desc = '', $display_tooltip = false ) {

		if ( !empty($title) ) { ?>
	
			<label for="woocommerce_currency">
				<?php
				echo esc_html__( $title, 'amazon-for-woocommerce' );
				if ( $display_tooltip ) {
					print_r( wc_help_tip( $tooltip_desc, 'amazon-for-woocommerce' ) );
				}
				?>
			</label>
			<?php
		}
	}
	
	
	public function print_table_label( $title = '', $tooltip_desc = '', $display_tooltip = false, $is_settings = false ) {

		$style = ''; 
		if ( $is_settings ) {
			$style ='padding-left:10px';
		}
		?>
		<th scope="row" class="titledesc" style="<?php echo esc_attr( $style ); ?>" >
			<?php $this->print_label( $title, $tooltip_desc, $display_tooltip ); ?>
		</th>
		<?php
	}


	public function content_tags( $tag, $is_italic, $content, $class = '' ) {

		if ( 'p' == $tag ) {
			?>
				<p 
				class="<?php echo esc_attr($class); ?>"
				>
					<?php if ( $is_italic ) { ?>
					  <i> 
						<?php
					} echo esc_attr($content);  if ( $is_italic ) {
						?>
						</i>
						<?php } ?> 
					</p>
			<?php
		}
	}

	public function input_tag( $type = 'text', $name = '', $class = '', $id = '', $value = '', $style = '', $placeholder = '', $extraAttributes = array(), $field_label_array = array(), $checked = '', $parent_tag_open_html = '', $parent_tag_closing_html = '' ) {

		echo wp_kses_post($parent_tag_open_html);
		?>
		<input 

			style="<?php echo esc_attr($style); ?>"
			type="<?php echo esc_attr($type); ?>"
			placeholder="<?php echo esc_attr($placeholder); ?>"
			id="<?php echo esc_attr($id); ?>"
			name="<?php echo esc_attr($name); ?>"
			class="<?php echo esc_attr($class); ?>"
			<?php 

			if ( ( 'radio' == $type  || 'checkbox' == $type ) && $checked ) {
				echo 'checked '; 
			} 

			if ( 'radio' !== $type && 'checkbox' !== $type ) {
				?>
				value="<?php echo esc_attr($value); ?>"
				<?php
			} elseif ( 'radio' == $type   ) {
				?>
				value="<?php echo esc_attr( array_keys($field_label_array)[0] ); ?>" 
				<?php
			}

			if (!empty($extraAttributes)) {
				foreach ($extraAttributes as $attribute => $value) {
					if ( in_array( $attribute, $this->extra_attributes_to_skip) ) {
						continue;
					}
					echo esc_attr($attribute) . '="' . esc_attr($value) . '" ';
				}
			}
			?>
		>
		   
		<?php
		if ( isset( $field_label_array ) && !empty( $field_label_array ) ) { 
			echo esc_attr( array_values($field_label_array)[0] );
			
			?>
			</input> 
			<?php
		}

		echo wp_kses_post($parent_tag_closing_html);

	}
	

	public function select_tag( $name = '', $class = '', $id = '', $saved_value = '', $style = '', $options = array(), $extraAttributes = array() ) {

		?>
		
		<select 
		style="<?php echo esc_attr($style); ?>"
		name="<?php echo esc_attr($name); ?>"
		class="<?php echo esc_attr($class); ?>"
		id="<?php echo esc_attr($id); ?>"
			<?php 
			if (!empty($extraAttributes)) {
				foreach ($extraAttributes as $attribute => $value ) {
					if ( in_array( $attribute, $this->extra_attributes_to_skip) ) {
						continue;
					}
					echo esc_attr($attribute) . '="' . esc_attr($value) . '" ';
				}
			}

			?>
						> 
			<?php

			if ( isset( $extraAttributes['default_option'] ) && $extraAttributes['default_option'] ) {
				?>
			   
			  <?php
			}
			  
			  
			if ( !empty( $options ) && is_array( $options ) ) {

				$is_multidimensional = count( $options ) !== count( $options, COUNT_RECURSIVE );
			
				if ( $is_multidimensional ) {
					// Multidimensional: render <optgroup>
					foreach ( $options as $group_label => $group_options ) {
						if ( is_array( $group_options ) ) {
							echo '<optgroup label="' . esc_attr( $group_label ) . '">';
							foreach ( $group_options as $option_value => $option_label ) {
								$selected = ( $option_value == $saved_value ) ? 'selected' : '';
								echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $option_value ) . '">' . esc_html( $option_label ) . '</option>';
							}
							echo '</optgroup>';
						}
					}
				} else {
					// Single-dimensional: render <option>
					foreach ( $options as $option_value => $option_label ) {
						$selected = ( $option_value == $saved_value ) ? 'selected' : '';
						echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $option_value ) . '">' . esc_html( $option_label ) . '</option>';
					}
				}
			}
			



			?>

			
		</select>

		<?php

	}


	public function ced_amz_product_global_and_custom_attributes( $ced_amazon_general_options = array(), $ced_amazon_global_settings = array(), $results = array(), $query = array(), $row = array(), $desc = '', $name = 'ced_amazon_general_options' ) {

		if ( 'ced_amazon_general_options' == $name ) {
			$modified_name =  $name . '[' . $row['attributes']['name'] . '][metakey]';
		} else {
			$modified_name =  $name . '[' . $row['attributes']['name'] . ']';
		}
		
		
		if ( 'ced_amazon_general_options' == $name ) {
			$selected_value2 = isset( $ced_amazon_general_options[ $row['attributes']['name'] ]['metakey'] ) ? $ced_amazon_general_options[ $row['attributes']['name'] ]['metakey'] : '';
		} else {
			$selected_value2 = isset( $ced_amazon_global_settings[ $row['attributes']['name'] ] ) ? $ced_amazon_global_settings[ $row['attributes']['name'] ] : '';
		}

		$selectDropdownHTML = '<select style="width: 100%;" class="ced_amazon_search_item_sepcifics_mapping select2" id="" name="' . $modified_name . '" >';
		foreach ( $results as $key2 => $meta_key ) {
			$post_meta_keys[] = $meta_key['meta_key'];
		}
		$custom_prd_attrb = array();
		$attrOptions      = array();

		if ( ! empty( $query ) ) {
			foreach ( $query as $key3 => $db_attribute_pair ) {

				foreach ( maybe_unserialize( $db_attribute_pair['meta_value'] ) as $key4 => $attribute_pair ) {

					if ( 1 != $attribute_pair['is_taxonomy'] ) {
						$custom_prd_attrb[] = $attribute_pair['name'];
					}
				}
			}
		}

		$attributes = wc_get_attribute_taxonomies();

		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $attributesObject ) {
				$attrOptions[ 'umb_pattr_' . $attributesObject->attribute_name ] = $attributesObject->attribute_label;
			}
		}

		ob_start();
		$fieldID             = '{{*fieldID}}';
		$selectId            = $fieldID . '_attibuteMeta';
		$selectDropdownHTML .= '<option value=""> -- select -- </option>';


		if ( is_array( $attrOptions ) && ! empty( $attrOptions ) ) {

			$selectDropdownHTML .= '<optgroup label="Global Attributes">';
			foreach ( $attrOptions as $attrKey => $attrName ) {
				$selected = '';
				if ( $selected_value2 == $attrKey ) {
					$selected = 'selected';
				}
				$selectDropdownHTML .= '<option ' . $selected . ' value="' . $attrKey . '">' . $attrName . '</option>';
			}
		}

		if ( ! empty( $custom_prd_attrb ) ) {
			$custom_prd_attrb    = array_unique( $custom_prd_attrb );
			$selectDropdownHTML .= '<optgroup label="Custom Attributes">';
			foreach ( $custom_prd_attrb as $key5 => $custom_attrb ) {
				$selected = '';
				if ( 'ced_cstm_attrb_' . esc_attr( $custom_attrb ) == $selected_value2 ) {
					$selected = 'selected';
				}
				$selectDropdownHTML .= '<option ' . $selected . ' value="ced_cstm_attrb_' . esc_attr( $custom_attrb ) . '">' . esc_html( $custom_attrb ) . '</option>';
			}
		}

		if ( ! empty( $post_meta_keys ) ) {
			$post_meta_keys      = array_unique( $post_meta_keys );
			$selectDropdownHTML .= '<optgroup label="Custom Fields">';
			foreach ( $post_meta_keys as $key7 => $p_meta_key ) {
				$selected = '';
				if ( $selected_value2 == $p_meta_key ) {
					$selected = 'selected';

				}
				$selectDropdownHTML .= '<option ' . $selected . ' value="' . $p_meta_key . '">' . $p_meta_key . '</option>';
			}
		}

		$selectDropdownHTML .= '</select>';

		print_r( $selectDropdownHTML );

		
		
										
	}









	

	
}
