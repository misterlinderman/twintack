<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manage xml related functions to use in amazon.
 *
 * @class    Ced_Amzon_XML_Lib
 * @version  1.0.0
 * @package Class
 * @link  http://www.cedcommerce.com/
 */

class Ced_Amzon_XML_Lib {

	public $isProfileAssignedToProduct = false;
	public $template_details           = '';
	public $product_array              = array();
	public $final_product_details      = array();
	public $browse_node_ids            = '';
	public $profile_data               = ''; 
	public $mplocation                 = '';


	/**
	 * This function fetches meta value of a product in accordance with profile assigned and meta value available.
	 *
	 * @name fetchMetaValueOfProduct()
	 * @link  http://www.cedcommerce.com/
	 */
	public function fetchMetaValueOfProduct( $product_id, $metaKey, $getopt_data = '' ) {

		$final_value = '';

		if ( 'browse_node_ids' == $metaKey ) {
			if ( isset( $this->browse_node_ids ) && '' != $this->browse_node_ids ) {
				return $this->browse_node_ids;
			}
		}

		if ( $this->isProfileAssignedToProduct ) {

			$_product = wc_get_product( $product_id );
			if ( 'variation' == $_product->get_type() ) {
				$parentId        = $_product->get_parent_id();
				$_product_parent = wc_get_product( $parentId );
			} else {
				$parentId = '0';
			}

			if ( '' != $getopt_data && '' != $metaKey ) {
				$geo_value = get_post_meta( $product_id, $metaKey, true );

				if ( '' != $geo_value ) {
					return $geo_value;
				}
			}

			if (str_ends_with($metaKey, '.value')) {
				$index   = strpos($metaKey, '.value');
				$metaKey = substr($metaKey, 0, $index);
			}
			
			$metaKey = str_replace( '.', '_', $metaKey );

			// modify key ends
			if ( ! empty( $this->profile_data ) && isset( $this->profile_data[ $metaKey ] ) ) {

				$tempProfileData = $this->profile_data[ $metaKey ];

				// Fields mapping from general option data if profile level fields not mapped
				$seller_id                       = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
				$global_profile_mapping_data_arr = get_option( 'ced_amazon_general_options', array() );
				$global_profile_mapping_data     = $global_profile_mapping_data_arr[ 'general_options' ] ?? array();

				if ( isset( $global_profile_mapping_data[ $metaKey ] ) && ! empty( $global_profile_mapping_data[ $metaKey ] ) ) {

					if ( empty( $tempProfileData['default'] ) && 'null' == $tempProfileData['metakey'] ) {
						$globalTempData = $global_profile_mapping_data[ $metaKey ];
						if ( isset( $globalTempData['default'] ) && ! empty( $globalTempData['default'] ) ) {
							$tempProfileData['default'] = $globalTempData['default'];
						}
						if ( isset( $globalTempData['metakey'] ) && 'null' != $globalTempData['metakey'] ) {
							$tempProfileData['metakey'] = $globalTempData['metakey'];
						}
					}
				}

				if ( isset( $tempProfileData['default'] ) && '' != $tempProfileData['default'] && ! is_null( $tempProfileData['default'] ) ) {

					$value = $tempProfileData['default'];
					return $value;

				} elseif ( isset( $tempProfileData['metakey'] ) && ! empty( $tempProfileData['metakey'] ) && '' != $tempProfileData['metakey'] && ! is_null( $tempProfileData['metakey'] ) ) {

					$meta_code = $tempProfileData['metakey'];
					$value     = get_post_meta( $product_id, $meta_code, true );

					// If woo attribute is selected
					if ( false !== strpos( $tempProfileData['metakey'], 'umb_pattr_' ) ) {

						$wooAttribute = explode( 'umb_pattr_', $tempProfileData['metakey'] );
						$wooAttribute = end( $wooAttribute );

						if ( 'variation' == $_product->get_type() ) { 
							$attributes = $_product->get_variation_attributes();
							if ( isset( $attributes[ 'attribute_pa_' . $wooAttribute ] ) && ! empty( $attributes[ 'attribute_pa_' . $wooAttribute ] ) ) {
								$wooAttributeValue = $attributes[ 'attribute_pa_' . $wooAttribute ];
								if ( '0' != $parentId ) {
									$product_terms = get_the_terms( $parentId, 'pa_' . $wooAttribute );
								} else {
									$product_terms = get_the_terms( $product_id, 'pa_' . $wooAttribute );
								}
							} else {
								
								$wooAttributeValue = $_product_parent->get_attribute( 'pa_' . $wooAttribute );

								if ( '0' != $parentId ) {
									$product_terms = get_the_terms( $parentId, 'pa_' . $wooAttribute );
								} else {
									$product_terms = get_the_terms( $product_id, 'pa_' . $wooAttribute );
								}
							}

							if ( is_array( $product_terms ) && ! empty( $product_terms ) ) {
								foreach ( $product_terms as $tempkey => $tempvalue ) {
									if ( $tempvalue->slug == $wooAttributeValue ) {
										$wooAttributeValue = $tempvalue->name;
										break;
									}
								}
								if ( isset( $wooAttributeValue ) && ! empty( $wooAttributeValue ) ) {
									$value = $wooAttributeValue;
								} else {
									$value = get_post_meta( $product_id, $metaKey, true ); 
								}
							} else {
								$value = get_post_meta( $product_id, $metaKey, true );
							}
						} else {
							$wooAttributeValue = $_product->get_attribute( 'pa_' . $wooAttribute );
							$product_terms     = get_the_terms( $product_id, 'pa_' . $wooAttribute );
							if ( is_array( $product_terms ) && ! empty( $product_terms ) ) {
								foreach ( $product_terms as $tempkey => $tempvalue ) {
									if ( $tempvalue->slug == $wooAttributeValue ) {
										$wooAttributeValue = $tempvalue->name;
										break;
									}
								}
								if ( isset( $wooAttributeValue ) && ! empty( $wooAttributeValue ) ) {
									$value = $wooAttributeValue;
								} else {
									$value = get_post_meta( $product_id, $metaKey, true );
								}
							} else {
								$value = get_post_meta( $product_id, $metaKey, true );
							}
						}

					} elseif ( false !== strpos( $tempProfileData['metakey'], 'ced_cstm_attrb_' ) ) {
						$custom_prd_attrb = explode( 'ced_cstm_attrb_', $tempProfileData['metakey'] );
						$custom_prd_attrb = end( $custom_prd_attrb );
						$wooAttribute     = $custom_prd_attrb;
						if ( ! empty( $wooAttribute ) ) {
							if ( 'variation' == $_product->get_type() ) { 
								
								$attributes        = $_product->get_variation_attributes();
								$wooAttributeLower = strtolower( $wooAttribute );

								if ( isset( $attributes[ 'attribute_' . $wooAttributeLower ] ) && ! empty( $attributes[ 'attribute_' . $wooAttributeLower ] ) ) {
									$wooAttributeValue = $attributes[ 'attribute_' . $wooAttributeLower ];
								} else {
								   
									$wooAttributeValue = $_product_parent->get_attribute( $wooAttribute );
									
									if ( ! empty( $wooAttributeValue ) ) {
										$wooAttributeValue = str_replace( '|', ',', $wooAttributeValue );
									}
									
								}

								if ( isset( $wooAttributeValue ) && ! empty( $wooAttributeValue ) ) {
									$value = $wooAttributeValue;
								} else {
									$value = get_post_meta( $product_id, $metaKey, true );
								}
							} else {
								
								$wooAttributeValue = $_product->get_attribute( $wooAttribute );
								if ( ! empty( $wooAttributeValue ) ) {
									$wooAttributeValue = str_replace( '|', ',', $wooAttributeValue );
									$value             = $wooAttributeValue;
								}
							}
						}
					} elseif ( false !== strpos( $tempProfileData['metakey'], 'ced_product_tags' ) ) {
						$terms             = get_the_terms( $product_id, 'product_tag' );
						$product_tags_list = array();
						if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
							foreach ( $terms as $term ) {
								$product_tags_list[] = $term->name;
							}
						}
						if ( ! empty( $product_tags_list ) ) {
							$value = implode( ',', $product_tags_list );
						} else {
							$value = '';
						}
					} else {
						$value = get_post_meta( $product_id, $tempProfileData['metakey'], true );
						if ( '_thumbnail_id' == $tempProfileData['metakey'] ) {
							$value = wp_get_attachment_image_url( get_post_meta( $product_id, '_thumbnail_id', true ), 'full' ) ? wp_get_attachment_image_url( get_post_meta( $product_id, '_thumbnail_id', true ), 'full' ) : '';
						}
						if ( ! isset( $value ) || empty( $value ) || '' == $value || is_null( $value ) || '0' == $value || 'null' == $value ) {
							if ( '0' != $parentId ) {

								$value = get_post_meta( $parentId, $tempProfileData['metakey'], true );
								if ( '_thumbnail_id' == $tempProfileData['metakey'] ) {
									$value = wp_get_attachment_image_url( get_post_meta( $parentId, '_thumbnail_id', true ), 'full' ) ? wp_get_attachment_image_url( get_post_meta( $parentId, '_thumbnail_id', true ), 'full' ) : '';
								}

								if ( ! isset( $value ) || empty( $value ) || '' == $value || is_null( $value ) ) {
									$value = get_post_meta( $product_id, $metaKey, true );

								}
							} else {
								$value = get_post_meta( $product_id, $metaKey, true );
							}
						}
					}
					
				} else {
					$value = get_post_meta( $product_id, $metaKey, true );
				}
			} else {
				$value = get_post_meta( $product_id, $metaKey, true );
			}
		} else {
			$value = get_post_meta( $product_id, $metaKey, true );
		}

		if ( '' != $final_value && '' == $value ) {
			$value = $final_value;
		}

		return $value;
	}


	/**
	 * This function formats php array in SIMPLE_XML_ELEMENT object.
	 *
	 * @name array2XML()
	 * @link  http://www.cedcommerce.com/
	 */
	public function array2XML( $xml_obj, $array ) {
		foreach ( $array as $key => $value ) {
			if ( is_numeric( $key ) ) {
				$key = $key;
			}
			if ( is_array( $value ) ) {
				$node = $xml_obj->addChild( $key );
				$this->array2XML( $node, $value );
			} else {
				$xml_obj->addChild( $key, htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ) );
			}
		}
	}


	/**
	 * This function gets substring between to string chunks.
	 *
	 * @name get_string_between()
	 * @link  http://www.cedcommerce.com/
	 */
	public function get_string_between( $string, $start, $end ) {
		$string = ' ' . $string;
		$ini    = strpos( $string, $start );
		if ( 0 == $ini ) {
			return '';
		}
		$ini += strlen( $start );
		$len  = strpos( $string, $end, $ini ) - $ini;
		return substr( $string, $ini, $len );
	}



	/**
	 * This function fetches data in accordance with profile assigned to product.
	 *
	 * @name fetchAssignedProfileDataOfProduct()
	 * @link  http://www.cedcommerce.com/
	 */
	public function fetchAssignedProfileDataOfProduct( $product_id, $mplocation = '', $profileID = '' ) {

		global $wpdb;
		$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';

		$this->mplocation = $mplocation;

		if ( '' == $profileID ) {

			// updated code
			$terms      = get_the_terms( $product_id, 'product_cat' );
			$term_array = array();
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_array[] = $term->term_id;
				}
			}

			$profileID              = '';
			$ced_woo_amazon_mapping = get_option( 'ced_woo_amazon_mapping', array() );
			$ced_woo_amazon_mapping = isset( $ced_woo_amazon_mapping[ $seller_id ] ) ? $ced_woo_amazon_mapping[ $seller_id ] : array();

			if ( ! empty( $ced_woo_amazon_mapping ) ) {
				foreach ( $ced_woo_amazon_mapping as $key => $woo_cat_array ) {

					$match_woo_cat = array_intersect( $woo_cat_array, $term_array );
					if ( is_array( $match_woo_cat ) && ! empty( $match_woo_cat ) ) {

						$profileID = $key;
						break;
					}
				}
			}
			// updated code

		}

		$profile_data     = array();
		$template_details = array();

		if ( isset( $profileID ) && ! empty( $profileID ) && '' != $profileID ) {

			$this->isProfileAssignedToProduct = true;
			$profileid                        = (int) $profileID;

			$profile_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_profiles WHERE `id`= %d", $profileid ), 'ARRAY_A' );

			if ( is_array( $profile_data ) ) {
				if ( ! isset( $profile_data['0'] ) ) {
					return;
				}
				$temp_profile_data = $profile_data['0'];
				$profile_country   = $mplocation;
				
				$profile_category = $temp_profile_data['product_type'];
				$template_name    = $profile_country . '_' . $profile_category ;
			
				$final_template_details = array(
					'country'       => $profile_country,
					'category'      => $profile_category,
					'template_name' => $template_name,
				);

				$template_details = '';

				if ( empty( $template_details ) || '' == $template_details ) {

					$template_details       = array();
					$amazonProductsFilePath = 'templates/' . $profile_country . '/' . $profile_category . '/products_json_fields.json';
					$amazonProductsDetails  = '';
					$upload_dir             = wp_upload_dir();
					$amazonProductsDetails  = $upload_dir['basedir'] . '/ced-amazon/' . $amazonProductsFilePath;

					ob_start();
					readfile( $amazonProductsDetails );

					$json_data             = ob_get_clean();
					$products_json_fields  = json_decode( $json_data, true );
					$amazonProductsDetails = isset( $products_json_fields['properties'] ) ? $products_json_fields['properties'] : array();
					$mandatory_fields      = isset( $products_json_fields['required'] ) ? $products_json_fields['required'] : array(); 
					
					if ( is_array( $amazonProductsDetails ) ) {

						$possible_type = array(
							'offer'                 => 'Offer',
							'images'                => 'Images',
							'shipping'              => 'Shipping',
							'safety_and_compliance' => 'Safety & Compliance',
							'product_identity'      => 'Product Identity',
							'product_details'       => 'Product Details',
							
						);
						$required_field             = array();
						$categories_specific_fields = array();


						// ----------------------------------------- extract template fields ---------------------------------------------------
						$categories_specific_fields = $this->cedExtractFieldNames( $products_json_fields, '' );
						$categories_specific_fields =  array_combine( $categories_specific_fields, $categories_specific_fields );

						$required_fields                              = array_combine( $mandatory_fields, $mandatory_fields );
						$template_details['mandantory']               = wp_json_encode( $required_fields );
						$template_details['category_specific_fields'] = wp_json_encode( $categories_specific_fields );
						$template_details['possible_fields_types']    = wp_json_encode( $possible_type );
						$template_details['template_details_info']    = wp_json_encode( $final_template_details );
						
						$template_details['product_details'] = wp_json_encode( $amazonProductsDetails );

					}

					$json_data                 = $temp_profile_data['category_attributes_structure'];
					$amazonAllFieldsDetailsNew = array();
					$amazonAllFieldsDetails    = json_decode( $json_data, true );
					if ( is_array( $amazonAllFieldsDetails ) ) {

						foreach ( $amazonAllFieldsDetails as $allFieldsKey => $allFieldsValue ) {
							$amazonAllFieldsDetailsNew[ $allFieldsKey ] = $allFieldsKey;
						}

						$template_details['all_fields_details'] = wp_json_encode( $amazonAllFieldsDetailsNew );
					}

					// This is for flat file template feed structure
					$amazonTemplateFieldsFilePath = 'templates/' . $profile_country . '/' . $profile_category . '/products_json_fields.json';
					$amazonTemplateDetails        = '';
					
					$upload_dir            = wp_upload_dir();
					$amazonTemplateDetails = $upload_dir['basedir'] . '/ced-amazon/' . $amazonTemplateFieldsFilePath;

					ob_start();
					readfile( $amazonTemplateDetails );
					$json_data             = ob_get_clean();
					$amazonTemplateDetails = json_decode( $json_data, true );

					if ( is_array( $amazonTemplateDetails ) ) {
						$template_details['template_fields_details'] = wp_json_encode( $amazonTemplateDetails );
					}
				}

				$profile_data  = isset( $profile_data[0] ) ? $profile_data[0] : $profile_data;
				$browsenode_id = isset( $profile_data['browse_nodes'] ) ? $profile_data['browse_nodes'] : '';
				$profile_data  = isset( $profile_data['category_attributes_data'] ) ? json_decode( $profile_data['category_attributes_data'], true ) : array();

				$template_details = is_array( $template_details ) ? $template_details : array();

			}
		} else {
			$this->isProfileAssignedToProduct = false;
		}

		if ( isset( $browsenode_id ) && '' != $browsenode_id ) {
			$this->browse_node_ids = $browsenode_id;
		}

		$this->profile_data = $profile_data;

		if ( isset( $template_details ) ) {
			$this->template_details = $template_details;
		}
	}

	/**
	 * This function make array for simple, variations and variable product
	 *
	 * @name prepareAllProductTypeData()
	 * @link  http://www.cedcommerce.com/
	 */
	public function prepareAllProductTypeData( $product_id = '', $mplocation = '', $profileId = '' ) {

		$wooc_product = wc_get_product( $product_id );
		if ( ! is_object( $wooc_product ) ) {
			return;
		}
		$productType = $wooc_product->get_type();
		$this->getFormatedData( $product_id, $wooc_product, $productType, $mplocation );

		return;
	}



	/*
		function to get all product informations from woocommerce
	*/
	public function getFormatedData( $productId = '', $wooc_product = '', $productType = '', $getopt_data = '' ) {
		
		$this->product_array         = array();
		$this->final_product_details = array();

		// Get global settings data
		$seller_global_settings = array();
		$global_settings        = get_option( 'ced_amazon_global_settings' );
		$seller_location        = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
		if ( isset( $global_settings[ $seller_location ] ) && ! empty( $global_settings[ $seller_location ] ) ) {
			$seller_global_settings = $global_settings[ $seller_location ];
		}

		$productData = $wooc_product->get_data();
		
		if ( 0 != $productData['parent_id'] ) {
			$wooc_par_product      = wc_get_product( $productData['parent_id'] );
			$wooc_par_product_data = $wooc_par_product->get_data();
		} else {
			$wooc_par_product_data = $wooc_product->get_data();
			$wooc_par_product      = $wooc_product;
		}

		/***************************************** SET PRODUCT DESCRIPTION */
		$this->ced_amz_set_product_description( $productData, $wooc_par_product_data, $productType );


		$ship_dimension_unit = strtoupper( get_option( 'woocommerce_dimension_unit' ) );
		$ship_weight_unit    = strtoupper( get_option( 'woocommerce_weight_unit' ) );

		$product_keys = array( 'id', 'name', 'slug', 'status', 'description', 'short_description', 'sku', 'price', 'regular_price', 'sale_price', 'tax_status', 'manage_stock', 'stock_quantity', 'stock_status', 'low_stock_amount', 'backorders', 'weight', 'length', 'width', 'height', 'parent_id', 'tag_ids', 'category_ids', 'image_id', 'gallery_image_ids', 'date_on_sale_from', 'date_on_sale_to' );

		$from_woo_details = array(

			'item_sku.value'                         => 'sku',
			'item_name.value'                        => 'name',
			'part_number'                            => 'sku',
			
			'purchasable_offer.maximum_retail_price' => 'price',
			'purchasable_offer.our_price'            => 'regular_price',

			'fulfillment_availability.quantity'      => 'stock_quantity',
			'item_dimensions.width.value'            => 'width',
			'item_dimensions.height.value'           => 'height',
			'item_dimensions.length.value'           => 'length',
			'item_weight.value'                      => 'weight',
											
			'item_package_dimensions.width.value'    => 'width',
			'item_package_dimensions.height.value'   => 'height',
			'item_package_dimensions.length.value'   => 'length',
			'item_package_weight.value'              => 'weight',

		);

		$product_details = array();
		if ( is_object( $wooc_product ) ) {
			foreach ( $product_keys as $key => $value ) {
				if ( isset( $productData[ $value ] ) ) {

					if (  is_numeric( $productData[ $value ] ) ) {
						$product_details[ $value ] = floatval($productData[ $value ]);
					} else {
						$product_details[ $value ] = $productData[ $value ];
					}

					
					if ( empty( $productData[ $value ] ) && isset( $wooc_par_product_data[ $value ] ) ) {
						if ( ! empty( $wooc_par_product_data[ $value ] ) && ( '' != $wooc_par_product_data[ $value ] && 'sale_price' != $value ) ) {
							$product_details[ $value ] = $wooc_par_product_data[ $value ];
						}
					}
				}
			}
		}

		foreach ( $from_woo_details as $fieldKey => $fieldValue ) {

			if ( isset( $from_woo_details[ $fieldKey ] ) && isset( $product_details[ $fieldValue ] ) && ! empty( $product_details[ $fieldValue ] ) ) {
				$this->product_array[ $fieldKey ] = $product_details[ $fieldValue ];
			}
		}

		/********************************* Add necessary fields to product array */
		$keys_to_insert = array(
			'condition_type'      => 'New',
			'number_of_items'     => 1,
			// 'fulfillment_availability.lead_time_to_ship_max_days' => 2,
			'fulfillment_availability.fulfillment_channel_code' => 'DEFAULT'
		);

		$this->product_array = $this->ced_amz_add_necessary_fields( $this->product_array, $keys_to_insert );

		/********************************* SET PRODUCT PARENT SKU */
		if ( ! isset( $this->product_array['child_parent_sku_relationship.parent_sku'] ) && isset( $wooc_par_product_data['sku'] ) && '' != $wooc_par_product_data['sku'] ) {
			$this->product_array['child_parent_sku_relationship.parent_sku'] = $wooc_par_product_data['sku'];
		}

		/********************************* SET PRODUCT Images */
		// $this->ced_amz_set_product_images( $productId, $product_details, $wooc_par_product, $this->product_array );

		if ( 'variation' == $productType ) {

			$variation_attriburte_value = wc_get_formatted_variation( $wooc_product->get_variation_attributes(), true );

			if ( isset( $wooc_par_product_data['name'] ) && $wooc_par_product_data['name'] == $productData['name'] ) {
				$this->product_array['item_name'] = $productData['name'] . ' - ' . $variation_attriburte_value;
				$product_details['name']          = $this->product_array['item_name'];
			}

			$this->product_array['parentage_level.value']                                 = 'child';
			$this->product_array['child_parent_sku_relationship.child_relationship_type'] = 'variation';

			if ( false !== stripos( $variation_attriburte_value, 'Size' ) ) {
				$child_theme_1 = 'SizeName';
			}
			if ( false !== stripos( $variation_attriburte_value, 'Color' ) || false !== stripos( $variation_attriburte_value, 'Colour' ) ) {
				$child_theme_2 = 'ColorName';
			}

			if ( isset( $child_theme_1 ) && ! empty( $child_theme_1 ) ) {
				$this->product_array['variation_theme'] = $child_theme_1;
			}

			if ( isset( $child_theme_2 ) && ! empty( $child_theme_2 ) ) {
				$this->product_array['variation_theme'] = $child_theme_2;
			}

			if ( ( isset( $child_theme_1 ) && ! empty( $child_theme_1 ) ) && ( isset( $child_theme_2 ) && ! empty( $child_theme_2 ) ) ) {
				$this->product_array['variation_theme'] = 'SizeName-ColorName';
			}
		}

		if ( 'variable' == $productType ) {

			$this->product_array['parentage_level.value'] = 'parent';
			unset( $this->product_array['child_parent_sku_relationship.parent_sku'] );
			$parentProduct = wc_get_product( $productId );
			$variationData = $parentProduct->get_available_variations();

			if ( ! empty( $variationData[0]['variation_id'] ) ) {
				$variationId           = $variationData[0]['variation_id'];
				$variationProduct      = wc_get_product( $variationId );
				$variation_attr_value  = wc_get_formatted_variation( $variationProduct->get_variation_attributes(), true );
				$child_variation_theme = get_post_meta( $variationId, 'variation_theme', true );

				if ( false !== stripos( $variation_attr_value, 'Size' ) ) {
					$var_theme_1 = 'SizeName';
				}
				if ( false !== stripos( $variation_attr_value, 'Color' ) || false !== stripos( $variation_attr_value, 'Colour' ) ) {
					$var_theme_2 = 'ColorName';
				}

				if ( isset( $var_theme_1 ) && ! empty( $var_theme_1 ) ) {
					$this->product_array['variation_theme'] = $var_theme_1;
				}

				if ( isset( $var_theme_2 ) && ! empty( $var_theme_2 ) ) {
					$this->product_array['variation_theme'] = $var_theme_2;
				}

				if ( ( isset( $var_theme_1 ) && ! empty( $var_theme_1 ) ) && ( isset( $var_theme_2 ) && ! empty( $var_theme_2 ) ) ) {
					$this->product_array['variation_theme'] = 'SizeName-ColorName';
				}

				if ( ( ! isset( $this->product_array['variation_theme'] ) || empty( $this->product_array['variation_theme'] ) ) && ! empty( $child_variation_theme ) ) {
					$this->product_array['variation_theme'] = $child_variation_theme;
				}
			}
		}

		if ( isset( $this->product_array['external_product_id'] ) ) {
			$ext_prod_id_len = strlen( $this->product_array['external_product_id'] );
			if ( $ext_prod_id_len < 10 || $ext_prod_id_len > 16 ) {
				$this->product_array['external_product_id']      = '';
				$this->product_array['external_product_id_type'] = '';
			} elseif ( 10 == $ext_prod_id_len ) {
				$this->product_array['external_product_id_type'] = 'ASIN';
			} elseif ( 11 == $ext_prod_id_len || 12 == $ext_prod_id_len ) {
				$this->product_array['external_product_id_type'] = 'UPC';
			} elseif ( 13 == $ext_prod_id_len || 14 == $ext_prod_id_len ) {
				$this->product_array['external_product_id_type'] = 'EAN';
			} elseif ( 15 == $ext_prod_id_len ) {
				$this->product_array['external_product_id_type'] = 'GTIN';
			} elseif ( 16 == $ext_prod_id_len ) {
				$this->product_array['external_product_id_type'] = 'GCID';
			}
		}

		if ( isset( $product_details['category_ids']['0'] ) && ! empty( $product_details['category_ids'] ) ) {

			$product_category_names        = array();
			$product_category_names_string = '';
			foreach ( $product_details['category_ids'] as $key => $value ) {
				$term = get_term_by( 'id', $value, 'product_cat' );

				if ( '' != $term->name ) {
					$product_category_names_string        .= $term->name . ', ';
					$product_category_names[ $term->name ] = $term->name;
				}
			}
			$product_category_names_string             = rtrim( $product_category_names_string, ', ' );
			$product_details['category_ids']           = $product_category_names;
			$product_details['product_category_names'] = $product_category_names_string;

		}

		if ( isset( $product_details['tag_ids']['0'] ) && ! empty( $product_details['tag_ids'] ) ) {
			$product_tag_names_string = '';
			$product_tag_names        = array();
			foreach ( $product_details['tag_ids'] as $key => $value ) {
				$term = get_term_by( 'id', $value, 'product_tag' );

				if ( '' != $term->name ) {
					$product_tag_names_string        .= $term->name . ', ';
					$product_tag_names[ $term->name ] = $term->name;
				}
			}
			$product_tag_names_string             = rtrim( $product_tag_names_string, ', ' );
			$product_details['tag_ids']           = $product_tag_names;
			$product_details['product_tag_names'] = $product_tag_names_string;

		}

		/***************************************** PREPARE BULLET POINTS */
		if ( isset( $this->product_array['product_description.value'] ) && ! empty( $this->product_array['product_description.value'] ) ) {
			// echo 'yesw';
			$this->ced_amz_set_bullet_points( $product_details );
		}

		/********************************* Add necessary fields to product array */
		$keys_to_insert      = array(
			'item_package_quantity' => 1,
			'number_of_items'       => 1,
			'handling_time'         => 2,
		);
		$this->product_array = $this->ced_amz_add_necessary_fields( $this->product_array, $keys_to_insert );

		if ( ! isset( $this->product_array['currency'] ) || '' == $this->product_array['currency'] ) {
			$currency_code = get_option( 'woocommerce_currency' );
			if ( '' != $currency_code ) {
				$this->product_array['currency'] = $currency_code;
			}
		}

		/********************************* Handle product dimension and weight unit */
		$this->ced_amz_ship_dimension_and_weight_unit( $ship_dimension_unit, $ship_weight_unit );
		if ( isset( $this->product_array['sale_price'] ) && ! empty( $this->product_array['sale_price'] ) ) {
			$sale_price_val = (float) $this->product_array['sale_price'];
		} else {
			$sale_price_val = '';
		}

		/********************************* CED AMAZON SET SALE DATES */
		// $this->ced_amz_set_sale_date( $product_details, $sale_price_val );

		/********************************* Add necessary fields to product array */
		$keys_to_insert      = array(
			'product_tax_code'      => 'A_GEN_NOTAX',
			'update_delete'         => 'Update',
			'fulfillment_center_id' => 'DEFAULT',
			'condition_type'        => 'New',
		);
		$this->product_array = $this->ced_amz_add_necessary_fields( $this->product_array, $keys_to_insert );

		$standard_price_val = -1;
		if ( isset( $this->product_array['purchasable_offer.our_price'] ) ) {
			$standard_price_val = (int) $this->product_array['purchasable_offer.our_price'];
		}

		if ( $standard_price_val <= 0 ) {
			$this->product_array['purchasable_offer.our_price']            = '0.00';
			$this->product_array['purchasable_offer.maximum_retail_price'] = '0.00';
		}

		/* Browze node id managment for all marketplaces using profiel data*/
		if ( ! isset( $this->product_array['recommended_browse_nodes.value'] ) || '' == $this->product_array['recommended_browse_nodes.value'] ) {
			$recommended_browse_nodes = $this->fetchMetaValueOfProduct( $productId, 'recommended_browse_nodes', $getopt_data );
			if ( '' != $recommended_browse_nodes ) {
				$this->product_array['recommended_browse_nodes.value'] = $recommended_browse_nodes;
			}
		}

		if ( ! isset( $this->template_details ) ) {
			return;
		}

		$template_details       = $this->template_details;
		$final_validated_fields = array();

		if ( isset( $template_details['category_specific_fields'] ) && ! empty( $template_details['category_specific_fields'] ) ) {

			$validation_fields = false;
			if ( isset( $template_details['validation_field_details'] ) && ! empty( $template_details['validation_field_details'] ) ) {

				$validation_field_details = json_decode( $template_details['validation_field_details'], true );
				$validation_fields        = true;

			}

			if ( isset( $template_details['mandantory'] ) ) {
				
				$category_all_required_fields = json_decode( $template_details['mandantory'], true );
				$category_all_fields          = json_decode( $template_details['category_specific_fields'], true );
				$category_all_fields          = array_keys( $category_all_fields );

			} else {

				$category_all_required_fields = json_decode( $template_details['category_specific_fields'], true );
				$category_all_fields          = json_decode( $template_details['category_specific_fields'], true );
				$category_all_fields          = array_keys( $category_all_fields );
			}

			
			foreach ( $category_all_fields as $test_keys => $field_key ) {

				$variable_index_to_skip = array( 
					 
					'style.value', 'collar_style.value', 'color.value', 'fit_type.value', 'special_size_type.value', 'care_instructions.value',
					'shirt_size.size_system','shirt_size.size_class','shirt_size.size','shirt_size.body_type','shirt_form_type.value',
					'sleeve.cuff_style','sleeve.type','closure.type', 'purchasable_offer.our_price', 'product_tax_code', 'standard_price', 
					'list_price.value','list_price.currency', 'list_price.value_with_tax',

					'item_type','special_features.value',  'manufacturer.value', 'department.value', 'style_name', 'closure_type', 'lifestyle.value', 
					'material_type.value', 'pattern.value', 'model_year', 'shoe_dimension_unit_of_measure', 'binding','condition_type.value', 
					'publication_date', 'author', 'part_number.value', 'shapewear_size.size_system', 'shapewear_size.size_class', 'shapewear_size.size',
					'shapewear_size.body_type',
					
					'external_product_information.entity', 'external_product_information.key', 'external_product_information.value','external_product_id_type',
					'externally_assigned_product_identifier.type','externally_assigned_product_identifier.value','external_product_id',

					'item_display_dimensions.depth','item_display_dimensions.depth.value','item_display_dimensions.depth.unit','item_display_dimensions.diameter',
					'item_display_dimensions.diameter.value','item_display_dimensions.diameter.unit','item_display_dimensions.height','item_display_dimensions.height.value',
					'item_display_dimensions.height.unit','item_display_dimensions.length','item_display_dimensions.length.value','item_display_dimensions.length.unit',
					'item_display_dimensions.width','item_display_dimensions.width.value','item_display_dimensions.width.unit',

					'item_dimensions.depth', 'item_dimensions.depth.value', 'item_dimensions.depth.unit', 'item_dimensions.diameter', 'item_dimensions.diameter.value',
					'item_dimensions.diameter.unit', 'item_dimensions.height', 'item_dimensions.height.value', 'item_dimensions.height.unit','item_dimensions.length',
					'item_dimensions.length.value','item_dimensions.length.unit','item_dimensions.width','item_dimensions.width.value','item_dimensions.width.unit',

					'item_weight.unit','item_package_dimensions.length.unit','item_package_dimensions.width.unit','item_package_dimensions.height.unit',
					'item_package_weight.unit', 'item_weight.value','item_package_dimensions.length.value','item_package_dimensions.width.value','item_package_dimensions.height.value',
					'item_package_weight.value', 'footwear_size.size_system', 'footwear_size.age_group', 'footwear_size.gender', 'footwear_size.size_class',
					'footwear_size.width', 'footwear_size.size'

				);

				if ( 'variable' == $productType ) {
					if ( in_array( $field_key, $variable_index_to_skip ) ) {
						continue;
					}
				}

				$assigned_profile_field_data = $this->fetchMetaValueOfProduct( $productId, $field_key, $getopt_data );
				if ( isset( $this->product_array[ $field_key ] ) && '' != $this->product_array[ $field_key ] ) {

					if (  is_numeric( $this->product_array[ $field_key ] ) ) {
						$this->final_product_details[ $field_key ] = floatval( $this->product_array[ $field_key ] );
					} else {
						$this->final_product_details[ $field_key ] = $this->product_array[ $field_key ];
					}
					
				}

				if ( '' != $assigned_profile_field_data ) {
					$this->final_product_details[ $field_key ] = $assigned_profile_field_data;
				}

				if ( $validation_fields && isset( $validation_field_details[ $field_key ]['base'] ) && '' != $validation_field_details[ $field_key ]['base'] && isset( $this->final_product_details[ $field_key ] ) && '' != $this->final_product_details[ $field_key ] ) {
					$validated_value = '';

					if ( '' != $validated_value ) {
						$final_validated_fields[ $field_key ]      = array( $validated_value => $this->final_product_details[ $field_key ] );
						$this->final_product_details[ $field_key ] = $validated_value;
					}
				}

	
			}

		}


		if ( 'variable' == $productType ) {
			/********************************* Add necessary fields to product array */
			$keys_to_insert = array(
				'parentage_level.value'  => 'parent', 'child_parent_sku_relationship.child_relationship_type'  => '',
			
			);
			$this->final_product_details = $this->ced_amz_add_necessary_fields( $this->final_product_details, $keys_to_insert );

		}

		if ( ! isset( $this->final_product_details['sale_price'] ) || empty( $this->final_product_details['sale_price'] ) ) {
			/********************************* Remove unnecessary fields */
			$keys_to_remove              = array( 'sale_price', 'sale_end_date', 'sale_from_date' );
			$this->final_product_details = $this->ced_amz_remove_unnecessary_fields( $this->final_product_details, $keys_to_remove );

		}

		if ( 'simple' == $productType && isset( $this->final_product_details['variation_theme'] ) ) {
			unset( $this->final_product_details['variation_theme'] );
		}

		/* SET PRODUCT IMAGES */
		$this->ced_amz_set_product_images( $productData, $productId, $product_details, $wooc_par_product, $productType );

		/** Price makup start */
		$markup_type     = $seller_global_settings['ced_amazon_product_markup_type'] ?? '';
		$markup_val      = $seller_global_settings['ced_amazon_product_markup'] ?? '';
		$roundOff_option = $seller_global_settings['ced_amazon_product_rounding_off_type'] ?? '';   
		// if ( isset( $seller_global_settings['ced_amazon_product_markup_type'] ) && ! empty( $seller_global_settings['ced_amazon_product_markup_type'] ) && isset( $seller_global_settings['ced_amazon_product_markup'] ) && ! empty( $seller_global_settings['ced_amazon_product_markup'] ) ) {
			$ced_product_price = $this->ced_amz_modify_product_price( $markup_type, $markup_val, $roundOff_option );
		// }
		/** Price makup end */

		// Set product quantity if manage quantity not enable but product status in stock/out of stock
		$this->ced_amz_set_product_quantity( $productId );

		if ( 'variable' == $productType ) {
			/** Remove unnecessary fields */
			$keys_to_remove              = array( 'purchasable_offer.maximum_retail_price', 'purchasable_offer.standard_price', 'sale_price' );
			$this->final_product_details = $this->ced_amz_remove_unnecessary_fields( $this->final_product_details, $keys_to_remove );
		}

		if ( 'simple' == $productType ) {
			$keys_to_remove              = array( 'child_parent_sku_relationship.parent_sku', 'child_parent_sku_relationship.child_relationship_type', 'sale_price' );
			$this->final_product_details = $this->ced_amz_remove_unnecessary_fields( $this->final_product_details, $keys_to_remove );
		}


		/** Code to save product data in file starts */
		$upload_dir = wp_upload_dir();
		$folderPath = $upload_dir['basedir'] . '/ced-amazon/product_upload_schema/' . $this->mplocation ; 

		if ( !is_dir( $folderPath ) ) {
			mkdir( $folderPath, 0755, true );
		}

		$id          = $wooc_par_product_data['id'];
		$filePath    = $folderPath . '/' . $id . '.json' ;
		$jsonContent = '{}';
		if ( file_exists($filePath) ) {
			$jsonContent = file_get_contents($filePath);
		} else {
			// If not, create it with an empty JSON object
			file_put_contents($filePath, json_encode(new stdClass(), JSON_PRETTY_PRINT));
		}
		
		$decodedContent = array();
		if ( !empty( $jsonContent ) ) {
			$decodedContent = json_decode( $jsonContent, true );
		}

		$decodedContent['final_product_details'][$productId] = $this->final_product_details;
		$result = file_put_contents($filePath, json_encode( $decodedContent ) );
		/** Code to save product data in file ends */

		$this->final_product_details = apply_filters( 'ced_data_format_before_upload', $this->final_product_details, $productId, $wooc_product, $productType, $getopt_data );

		if ( is_array( $this->final_product_details ) && ! empty( $this->final_product_details ) ) {
			update_post_meta( $productId, 'ced_amazon_final_pro_det_' . $getopt_data, $this->final_product_details );
		} else {
			update_post_meta( $productId, 'ced_amazon_final_pro_det_' . $getopt_data, array() );
		}

	}


	/**
	 * Function to remove_empty_tags_recursive
	 */
	public function remove_empty_tags_recursive( $str, $repto = null ) {
		if ( ! function_exists( 'woocommerce_product_loop_start' ) ) {

			include_once dirname( WC_PLUGIN_FILE ) . '/includes/wc-template-functions.php';

			/**
			 * Function to get content
			 *
			 * @param 'function'
			 * @param  integer 'limit'
			 * @return 'count'
			 * @since 1.0.0
			 */
			$str = apply_filters( 'the_content', $str );
		}

		$doshortcode_str = do_shortcode( $str );
		if ( ! empty( $doshortcode_str ) ) {
			$str = $doshortcode_str;
		}

		if ( preg_match_all( '/\[([^\]]+)\]/', $str, $shortCodes ) ) {
			foreach ( $shortCodes[0] as $eachShortCode ) {
				$str = str_replace( $eachShortCode, '', $str );
			}
		}
		// convert UTF-8 characters AND REMOVE WHITE AND TAB SPACES AND ANY SHOP LINKS , SHORTCODE ETC.
		$str = htmlentities( $str, ENT_QUOTES, 'UTF-8', false );
		$str = htmlspecialchars_decode( $str, ENT_QUOTES );

		$str = str_replace( chr( 0xE2 ) . chr( 0x97 ) . chr( 0x8F ), '&bull;', $str );

		$str = str_replace( chr( 194 ) . chr( 160 ), ' ', $str );

		$str = preg_replace( '#<a.*?>(.*?)</a>#i', ' $1 ', $str );

		$str = nl2br( trim( strip_tags( $str, '<b><i>' ) ) );
		$str = str_replace( array( "\n", "\r" ), '', $str );
		$str = str_replace( array( "\t" ), ' ', $str );
		if ( strlen( $str ) > 2000 ) {
			$str = substr( $str, 0, 1900 );
		}

		return preg_replace( '/<([^<\/>]*)>([\s]*?|(?R))<\/\1>/imsU', ! is_string( $repto ) ? '' : $repto, $str );
	}



	/**
	 * Function to modify product price based on markup
	 */
	public function ced_amz_modify_product_price( $markup_type = '', $markup_value = 0, $roundOff_option = '' ) {	

		$purchasable_offer_our_price            = 0;
		$purchasable_offer_maximum_retail_price = 0;
		$sale_price                             = 0;
		$list_price_value                       = 0;
		$list_price_value_with_tax              = 0;

		if ( 'Fixed_Increased' == $markup_type ) { 

			if ( isset( $this->final_product_details['purchasable_offer.our_price'] ) && ! empty( $this->final_product_details['purchasable_offer.our_price'] ) ) {
				$markup_price                = (float) $this->final_product_details['purchasable_offer.our_price'] + (float) $markup_value;
				$purchasable_offer_our_price = 1;
			}

			if ( isset( $this->final_product_details['purchasable_offer.maximum_retail_price'] ) && ! empty( $this->final_product_details['purchasable_offer.maximum_retail_price'] ) ) {
				$markup_price                           = (float) $this->final_product_details['purchasable_offer.maximum_retail_price'] + (float) $markup_value;
				$purchasable_offer_maximum_retail_price = 1;
			}

			if ( isset( $this->final_product_details['sale_price'] ) && ! empty( $this->final_product_details['sale_price'] ) ) {
				$markup_price = (float) $this->final_product_details['sale_price'] + (float) $markup_value;
				$sale_price   = 1;
			}

			if ( isset( $this->final_product_details['list_price.value'] ) && ! empty( $this->final_product_details['list_price.value'] ) ) {
				$markup_price     = (float) $this->final_product_details['list_price.value'] + (float) $markup_value;
				$list_price_value = 1;
			}

			if ( isset( $this->final_product_details['list_price.value_with_tax'] ) && ! empty( $this->final_product_details['list_price.value_with_tax'] ) ) {
				$markup_price              = (float) $this->final_product_details['list_price.value_with_tax'] + (float) $markup_value;
				$list_price_value_with_tax = 1;
			}

		} elseif ( 'Fixed_Decreased' == $markup_type ) { 

			if ( isset( $this->final_product_details['purchasable_offer.our_price'] ) && ! empty( $this->final_product_details['purchasable_offer.our_price'] ) ) {
				$markup_price                = (float) $this->final_product_details['purchasable_offer.our_price'] - (float) $markup_value;
				$purchasable_offer_our_price = 1;
			}

			if ( isset( $this->final_product_details['purchasable_offer.maximum_retail_price'] ) && ! empty( $this->final_product_details['purchasable_offer.maximum_retail_price'] ) ) {
				$markup_price                           = (float) $this->final_product_details['purchasable_offer.maximum_retail_price'] - (float) $markup_value;
				$purchasable_offer_maximum_retail_price = 1;
			}

			if ( isset( $this->final_product_details['sale_price'] ) && ! empty( $this->final_product_details['sale_price'] ) ) {
				$markup_price = (float) $this->final_product_details['sale_price'] - (float) $markup_value;
				$sale_price   = 1;
			}

			if ( isset( $this->final_product_details['list_price.value'] ) && ! empty( $this->final_product_details['list_price.value'] ) ) {
				$markup_price     = (float) $this->final_product_details['list_price.value'] - (float) $markup_value;
				$list_price_value = 1;
			}

			if ( isset( $this->final_product_details['list_price.value_with_tax'] ) && ! empty( $this->final_product_details['list_price.value_with_tax'] ) ) {
				$markup_price              = (float) $this->final_product_details['list_price.value_with_tax'] - (float) $markup_value;
				$list_price_value_with_tax = 1;
			}

		} elseif ( 'Percentage_Increased' == $markup_type ) {  

			if ( isset( $this->final_product_details['purchasable_offer.our_price'] ) && ! empty( $this->final_product_details['purchasable_offer.our_price'] ) ) {
				$markup_price                = ( ( ( (float) $this->final_product_details['purchasable_offer.our_price'] * (float) $markup_value ) / 100 ) + $this->final_product_details['purchasable_offer.our_price'] );
				$purchasable_offer_our_price = 1;
			}

			if ( isset( $this->final_product_details['purchasable_offer.maximum_retail_price'] ) && ! empty( $this->final_product_details['purchasable_offer.maximum_retail_price'] ) ) {
				$markup_price                           = ( ( ( (float) $this->final_product_details['purchasable_offer.maximum_retail_price'] * (float) $markup_value ) / 100 ) + $this->final_product_details['purchasable_offer.maximum_retail_price'] );
				$purchasable_offer_maximum_retail_price = 1;
			}

			if ( isset( $this->final_product_details['sale_price'] ) && ! empty( $this->final_product_details['sale_price'] ) ) {
				$markup_price = ( ( ( (float) $this->final_product_details['sale_price'] * (float) $markup_value ) / 100 ) + $this->final_product_details['sale_price'] );
				$sale_price   = 1;
			}

			if ( isset( $this->final_product_details['list_price.value'] ) && ! empty( $this->final_product_details['list_price.value'] ) ) {
				$markup_price     = ( ( ( (float) $this->final_product_details['list_price.value'] * (float) $markup_value ) / 100 ) + $this->final_product_details['list_price.value'] );
				$list_price_value = 1;
			}

			if ( isset( $this->final_product_details['list_price.value_with_tax'] ) && ! empty( $this->final_product_details['list_price.value_with_tax'] ) ) {
				$markup_price              = ( ( ( (float) $this->final_product_details['list_price.value_with_tax'] * (float) $markup_value ) / 100 ) + $this->final_product_details['list_price.value_with_tax'] );
				$list_price_value_with_tax = 1;
			}

		} elseif ( 'Percentage_Decreased' == $markup_type ) { 

			if ( isset( $this->final_product_details['purchasable_offer.our_price'] ) && ! empty( $this->final_product_details['purchasable_offer.our_price'] ) ) {
				$markup_price                = ( (float) $this->final_product_details['purchasable_offer.our_price'] ) - ( ( (float) $this->final_product_details['purchasable_offer.our_price'] * (float) $markup_value ) / 100 );
				$purchasable_offer_our_price = 1;
			}

			if ( isset( $this->final_product_details['purchasable_offer.maximum_retail_price'] ) && ! empty( $this->final_product_details['purchasable_offer.maximum_retail_price'] ) ) {
				$markup_price                           = ( (float) $this->final_product_details['purchasable_offer.maximum_retail_price'] ) - ( ( (float) $this->final_product_details['purchasable_offer.maximum_retail_price'] * (float) $markup_value ) / 100 );
				$purchasable_offer_maximum_retail_price = 1;
			}

			if ( isset( $this->final_product_details['sale_price'] ) && ! empty( $this->final_product_details['sale_price'] ) ) {
				$markup_price = ( (float) $this->final_product_details['sale_price'] ) - ( ( (float) $this->final_product_details['sale_price'] * (float) $markup_value ) / 100 );
				$sale_price   = 1;
			}

			if ( isset( $this->final_product_details['list_price.value'] ) && ! empty( $this->final_product_details['list_price.value'] ) ) {
				$markup_price     = ( (float) $this->final_product_details['list_price.value'] ) - ( ( (float) $this->final_product_details['list_price.value'] * (float) $markup_value ) / 100 );
				$list_price_value = 1;
				
			}

			if ( isset( $this->final_product_details['list_price.value_with_tax'] ) && ! empty( $this->final_product_details['list_price.value_with_tax'] ) ) {
				$markup_price              = ( (float) $this->final_product_details['list_price.value_with_tax'] ) - ( ( (float) $this->final_product_details['list_price.value_with_tax'] * (float) $markup_value ) / 100 );
				$list_price_value_with_tax = 1;
			}

		} 

		if ( $purchasable_offer_our_price ) {
		   $this->final_product_details['purchasable_offer.our_price'] = ced_roundoff_numbers( $markup_price, $roundOff_option );
		}

		if ( $purchasable_offer_maximum_retail_price ) {
		   $this->final_product_details['purchasable_offer.maximum_retail_price'] = ced_roundoff_numbers( $markup_price, $roundOff_option );
		}
		if ( $sale_price ) {
			$this->final_product_details['sale_price'] = ced_roundoff_numbers( $markup_price, $roundOff_option );
		}
		if ( $list_price_value ) {
			$this->final_product_details['list_price.value'] = ced_roundoff_numbers( $markup_price, $roundOff_option );
		}
		if ( $list_price_value_with_tax ) {
			$this->final_product_details['list_price.value_with_tax'] = ced_roundoff_numbers( $markup_price, $roundOff_option );
		}
		

	}


	/**
	 * This function replace the https:// to http:// as per amazon standard.
	 *
	 * @name modifyImageUrl()
	 * @link  http://www.cedcommerce.com/
	 */
	public function modifyImageUrl( $image_url ) {

		if ( '/wp-content/' == substr( $image_url, 0, 12 ) ) {
			$image_url = str_replace( '/wp-content', content_url(), $image_url );
		}
		$image_url = str_replace( ':443', '', $image_url );
		$image_url = str_replace( 'https://', 'http://', $image_url );
		return $image_url;
	}


	public function ced_amazon_webp_to_jpeg_convertor( $image_path = '' ) {

		$image_name        = basename( $image_path );
		$upload_dir        = wp_upload_dir();
		$image_name        = exif_imagetype( $image_path ) == IMAGETYPE_WEBP ? str_replace( '.webp', '.jpeg', $image_name ) : $image_name;
		$image_custom_path = $upload_dir['baseurl'] . '/ced-amazon/ced_amazon_converted_image/' . $image_name;
		if ( ! is_dir( ( $upload_dir['basedir'] . '/ced-amazon/ced_amazon_converted_image/' ) ) ) {
			mkdir( ( $upload_dir['basedir'] . '/ced-amazon/ced_amazon_converted_image/' ), 0755 );
		}
		if ( exif_imagetype( $image_path ) == IMAGETYPE_WEBP && ! file_exists( $image_custom_path ) ) {
			$im = imagecreatefromwebp( $image_path );
			imagejpeg( $im, $upload_dir['basedir'] . '/ced-amazon/ced_amazon_converted_image/' . $image_name, 100 );
			imagedestroy( $im );
			$image_path = $image_custom_path;
		} elseif ( file_exists( $image_custom_path ) ) {
			$image_path = $image_custom_path;
		}
		return $image_path;
	}


	/**
	 * Function to Remove unnecessary fields
	 */
	public function ced_amz_remove_unnecessary_fields( $product_data, $fields_array = array() ) {

		if ( ! empty( $fields_array ) && is_array( $fields_array ) ) {
			foreach ( $fields_array as $key ) {
				if ( isset( $product_data[ $key ] ) ) {
					unset( $product_data[ $key ] );
				}
			}
		}

		return $product_data;
	}


	/**
	 * Function to Add necessary fields
	 */
	public function ced_amz_add_necessary_fields( $array_to_modify = array(), $fields_array = array() ) {

		if ( ! empty( $fields_array ) && is_array( $fields_array ) ) {
			foreach ( $fields_array as $key => $value ) {
				if ( ! isset( $array_to_modify[ $key ] ) || '' == $array_to_modify[ $key ] ) {
					$array_to_modify[ $key ] = $value;
				}
			}
		}

		return $array_to_modify;
	}


	/**
	 * Function to set product dimension and ship unit
	 */
	public function ced_amz_ship_dimension_and_weight_unit( $ship_dimension_unit = '', $ship_weight_unit = '' ) {

		if ( '' != $ship_weight_unit ) {
			if ( 'G' == $ship_weight_unit ) {
				$ship_weight_unit = 'GR';
			}
			if ( 'LBS' == $ship_weight_unit ) {
				$ship_weight_unit = 'LB';
			}

		} else {
			
			$this->product_array['item_dimensions.length.unit'] = 'GR';
			$this->product_array['item_dimensions.width.unit']  = 'GR';
			$this->product_array['item_dimensions.height.unit'] = 'GR';

			$this->product_array['item_package_dimensions.length.unit'] =  'GR';
			$this->product_array['item_package_dimensions.width.unit']  =  'GR';
			$this->product_array['item_package_dimensions.height.unit'] =  'GR';
		}

	}

	/**
	 * Function to set product quantity
	 */
	public function ced_amz_set_product_quantity( $productId = 0 ) {

		$product_data = wc_get_product( $productId );
		$qty_status   = $product_data->get_stock_status();
		if ( ( ! isset( $this->final_product_details['fulfillment_availability.quantity'] ) || '' == $this->final_product_details['fulfillment_availability.quantity'] ) && 'instock' == $qty_status ) {
			$this->final_product_details['fulfillment_availability.quantity'] = 1;
		} elseif ( ( ! isset( $this->final_product_details['fulfillment_availability.quantity'] ) || '' == $this->final_product_details['fulfillment_availability.quantity'] ) && 'outofstock' == $qty_status ) {
			$this->final_product_details['fulfillment_availability.quantity'] = 0;
		}

		/** Get global settings data */
		$seller_global_settings = array();
		$global_settings        = get_option( 'ced_amazon_global_settings' );
		$seller_location        = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
		
		if ( isset( $global_settings[ $seller_location ] ) && ! empty( $global_settings[ $seller_location ] ) ) {
			$seller_global_settings = isset( $global_settings[ $seller_location ] ) ? $global_settings[ $seller_location ] : array();
		}

		/** Stock quantity thershold */
		if ( isset( $seller_global_settings['ced_amazon_product_stock_type'] ) && ! empty( $seller_global_settings['ced_amazon_product_stock_type'] ) && isset( $seller_global_settings['ced_amazon_listing_stock'] ) && ! empty( $seller_global_settings['ced_amazon_listing_stock'] ) ) {

			$max_quantity_threshold = $seller_global_settings['ced_amazon_listing_stock'];
			if ( isset( $this->final_product_details['fulfillment_availability.quantity'] ) && $this->final_product_details['fulfillment_availability.quantity'] > $max_quantity_threshold ) {
				$this->final_product_details['fulfillment_availability.quantity'] = $max_quantity_threshold;
			}
		}

		$ced_amazon_rsrve_stck = isset( $seller_global_settings['ced_amazon_rsrve_stck'] ) ? $seller_global_settings['ced_amazon_rsrve_stck'] : '';
		if ( is_int( $ced_amazon_rsrve_stck ) && $this->final_product_details['fulfillment_availability.quantity'] >= $ced_amazon_rsrve_stck ) {
			$this->final_product_details['fulfillment_availability.quantity'] = $this->final_product_details['fulfillment_availability.quantity'] - $ced_amazon_rsrve_stck;
		}

	}

	/**
	 * Function to set product description
	 */
	public function ced_amz_set_product_description( $productData, $wooc_par_product_data, $productType ) {

		$description_amazon = '';
		$Description_string = '';

		if ( '' != $productData['description'] ) {
			$description_amazon = isset( $productData['description'] ) ? $productData['description'] : '';
		}

		if ( isset( $productData['short_description'] ) && ! empty( $productData['short_description'] ) ) {
			$description_amazon .= $productData['short_description'];

		}

		if ( '' == $description_amazon ) {
			$description_amazon = isset( $productData['short_description'] ) ? $productData['short_description'] : '';
		}
		if ( '' == $description_amazon && isset( $wooc_par_product_data['description'] ) ) {
			$description_amazon = isset( $wooc_par_product_data['description'] ) ? $wooc_par_product_data['description'] : '';
		}
		if ( '' == $description_amazon && isset( $wooc_par_product_data['short_description'] ) ) {
			$description_amazon = isset( $wooc_par_product_data['short_description'] ) ? $wooc_par_product_data['short_description'] : '';
		}

		if ( isset( $wooc_par_product_data['short_description'] ) && ! empty( $wooc_par_product_data['short_description'] ) ) {
			$description_amazon .= $wooc_par_product_data['short_description'];
		}

		if ( '' != $description_amazon ) {

			$Description_string = preg_replace( '/<([a-z][a-z0-9]*)[^>]*?(\/?)>/i', '<$1$2>', $description_amazon );
			$Description_string = htmlspecialchars_decode( $Description_string, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 );
			$Description_string = preg_replace( '/(<)([img])(\w+)([^>]*>)/', '', $Description_string );
			$Description_string = $this->remove_empty_tags_recursive( $Description_string );
			$Description_string = preg_replace( '/\s+/', ' ', $Description_string );

			// Remove all attributes from HTML tags, keeping only the tag names
			$Description_string = preg_replace('/<([a-z][a-z0-9]*)\s*[^>]*?(\/?)>/', '<$1$2>', $Description_string);
			// $Description_string = preg_replace('/<(\w+)[^>]*>/', '<$1>', $Description_string);

			if ( '' != $Description_string && ! isset( $this->product_array['product_description.value'] ) ) {
				$this->product_array['product_description.value'] = $Description_string;
			}
		}

		if ( 'simple' == $productType || 'variation' == $productType ) {
			$this->product_array['product_description.value'] = $Description_string;
		}



	}

	/**
	 * Function to set product main image and gallery images
	 */
	public function ced_amz_set_product_images( $productData, $productId, $product_details, $wooc_par_product, $productType ) {

		$image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $productId ), 'full' );
		if ( isset( $image_url[0] ) ) {
			$image_url = $image_url[0];

		} elseif ( 0 != $product_details['parent_id'] ) {
			$image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $product_details['parent_id'] ), 'full' );
			if ( isset( $image_url[0] ) ) {
				$image_url = $image_url[0];
			}
		}
		if ( ! empty( $image_url ) ) {
			$attachment_url_modified = $this->modifyImageUrl( $image_url );
			$image_url               = ! empty( $attachment_url_modified ) ? $attachment_url_modified : $image_url;
			$this->final_product_details['main_product_image_locator.media_location'] = $image_url;
		}

		$wooc_product   = wc_get_product( $productId );
		$attachment_ids = $wooc_product->get_gallery_image_ids();

		if ( 'variation' == $productType ) {
			/*********************** GET PARENT IMAGES IF PRODUCT IMAGES DOESN'T EXISTS */
			if ( ! isset( $attachment_ids['0'] ) && 0 != $productData['parent_id'] ) {
				$attachment_ids = $wooc_par_product->get_gallery_image_ids();
			}
		}

		if ( ! empty( $attachment_ids ) && is_array( $attachment_ids ) ) {
			foreach ( $attachment_ids  as $key => $attachment_id ) {
				if ( $key > 7 ) {
					continue;
				}
				$image_id_key            = $key + 1;
				$attachment_url          = wp_get_attachment_image_src( $attachment_id, 'full' );
				$attachment_url_modified = $this->modifyImageUrl( $attachment_url[0] );

				$attachment_url = ! empty( $attachment_url_modified ) ? $attachment_url_modified : $attachment_url[0];

				if ( ! empty( $attachment_url ) ) {
					$gallery_images   = $attachment_url;
					$converted_images = $this->ced_amazon_webp_to_jpeg_convertor( $gallery_images );
					$this->final_product_details[ 'other_product_image_locator_' . $image_id_key . '.media_location' ] = $converted_images;
				}
			}
		}

		$main_image_url = isset( $this->final_product_details['main_product_image_locator.media_location'] ) ? $this->final_product_details['main_product_image_locator.media_location'] : '';
		if ( !empty( $main_image_url ) ) {

			$main_converted_image = $this->ced_amazon_webp_to_jpeg_convertor( $main_image_url );
			$this->final_product_details['main_product_image_locator.media_location'] = $main_converted_image;

		}

	}

	/**
	 * Function to set bullet points
	 */
	public function ced_amz_set_bullet_points( $product_details ) {

		$product_description     = $this->product_array['product_description.value'];
		$product_description_str = strip_tags( $product_description );
		
		$bullet_points = array();

		$bullet_points_array = $this->ced_amz_make_valid_bullet_point( $product_description_str, $bullet_points );

		if ( isset( $product_details['product_category_names'] ) && ! empty( $product_details['product_category_names'] ) ) {
			$bullet_points_array[] = substr( $product_details['product_category_names'], 0, 140 );
		}

		if ( isset( $product_details['product_tag_names'] ) && ! empty( $product_details['product_tag_names'] ) ) {
			$bullet_points_array[] = substr( $product_details['product_tag_names'], 0, 140 );

			$this->product_array['generic_keywords'] = substr( $product_details['product_tag_names'], 0, 50 );
		}

		if ( isset( $bullet_points_array['1'] ) ) {
			foreach ( $bullet_points_array as $key => $value ) {
				$key_val                                       = $key + 1;
				$this->product_array[ 'bullet_point.value' ][] = $value;
			}
		}


		$count = count( $this->product_array[ 'bullet_point.value' ] );
		if ( 10 < $count ) {
			$this->product_array[ 'bullet_point.value' ] = array_slice( $this->product_array[ 'bullet_point.value' ], 0, 10 );
		}


	}
	

	public function ced_amz_make_valid_bullet_point($product_description_str, &$bullet_points = []) {
		
		// Split the description into sentences based on periods
		$product_description_array = explode('.', $product_description_str);
	
		$buffer = ''; // Temporary buffer to hold small segments
	
		foreach ($product_description_array as $point) {
			$point = trim($point); // Trim whitespace
	
			// Skip empty points
			if (empty($point)) {
				continue;
			}
	
			// Append the buffer if it's not empty
			if (!empty($buffer)) {
				$point  = $buffer . ' ' . $point;
				$buffer = ''; // Clear the buffer
			}
	
			// Check if the point exceeds 180 characters
			while ( 180 < strlen($point) ) {
				$truncated_point = substr($point, 0, 180);
	
				// Prioritize splitting at a comma
				$comma_position = strrpos($truncated_point, ',');
	
				if ( false !== $comma_position ) {
					$truncated_point = substr($point, 0, $comma_position + 1);
					$point           = substr($point, $comma_position + 1);
				} else {
					// Split at the nearest space if no comma
					$space_position = strrpos($truncated_point, ' ');
					if ( false !== $space_position ) {
						$truncated_point = substr($point, 0, $space_position);
						$point           = substr($point, $space_position + 1);
					} else {
						// If no space or comma, force cut
						$truncated_point = substr($point, 0, 180);
						$point           = substr($point, 180);
					}
				}
	
				$truncated_point = trim($truncated_point);
				if (!empty($truncated_point)) {
					$bullet_points[] = $truncated_point;
				}
			}
	
			// Handle the remaining part
			if ( 180 >= strlen($point) ) {
				if (strpos($point, ',') === false) {
					// No comma, save to buffer for potential merging
					$buffer = trim($point);
				} else {
					// Ends with a comma or is valid; add directly
					$bullet_points[] = trim($point);
				}
			}
		}
	
		// Add any remaining buffer if it's non-empty
		if (!empty($buffer)) {
			$bullet_points[] = $buffer;
		}
	
		return $bullet_points;
	}
	

	public function ced_amz_set_sale_date( $product_details, $sale_price_val ) {

		$sale_from_date = gmdate( 'Y-m-d' );
		if ( isset( $product_details['date_on_sale_to'] ) && ! empty( $product_details['date_on_sale_to'] ) ) {

			if ( isset( $product_details['date_on_sale_to']->date ) ) {
				$sale_to_date = $product_details['date_on_sale_to']->date; 
				if ( strtotime( $sale_to_date ) < strtotime( $sale_from_date ) ) {
					$sale_to_date = '';
				}
			} else {
				$sale_to_date = '';
			}
			if ( '' == $sale_to_date ) {
				$sale_to_date = gmdate( 'Y-m-d', strtotime( '+3 months' ) );
			} else {
				$sale_to_date = gmdate( 'Y-m-d', strtotime( $sale_to_date ) );
			}

			if ( isset( $sale_to_date ) && '' != $sale_price_val && 0 < $sale_price_val && '' != $sale_to_date ) {
				$this->product_array['sale_price']     = $sale_price_val;
				$this->product_array['sale_from_date'] = $sale_from_date;
				$this->product_array['sale_end_date']  = $sale_to_date;
			} elseif ( isset( $this->product_array['sale_price'] ) ) {
				/********************************* Remove unnecessary fields */
				$keys_to_remove      = array( 'sale_price', 'sale_end_date', 'sale_from_date' );
				$this->product_array = $this->ced_amz_remove_unnecessary_fields( $this->product_array, $keys_to_remove );
			}
		}

		if ( isset( $this->product_array['sale_price'] ) && empty( $this->product_array['sale_price'] ) ) {
			/********************************* Remove unnecessary fields */
			$keys_to_remove      = array( 'sale_price', 'sale_end_date', 'sale_from_date' );
			$this->product_array = $this->ced_amz_remove_unnecessary_fields( $this->product_array, $keys_to_remove );
		}
	}


	public function cedExtractFieldNames($jsonSchema, $prefix = '') {
		
		$fields = [];
		
		if (isset($jsonSchema['properties']) && is_array($jsonSchema['properties'])) {
			foreach ($jsonSchema['properties'] as $key => $property) {
				$fullKey = $prefix ? "$prefix.$key" : $key;
				// $fields[] = $fullKey;
				
				if (isset($property['items']['properties']) && is_array($property['items']['properties'])) {
					foreach ($property['items']['properties'] as $nestedKey => $nestedProperty) {
						// Exclude specific fields
						if (!in_array($nestedKey, [ 'marketplace_id', 'language_tag'])) {

							// if( 'value' == $nestedKey ){
							// 	$fields[] =  "$key.$nestedKey.value";
							// } else{
								$fields[] = "$key.$nestedKey";
							// }
						  
						} 

						// If nested properties contain 'value' and 'unit', add them
						if (isset($nestedProperty['properties']) && is_array($nestedProperty['properties'])) {
							foreach ($nestedProperty['properties'] as $deepKey => $deepProperty) {
								if (in_array($deepKey, ['value', 'unit'])) {
									$fields[] = "$key.$nestedKey.$deepKey";
								}
							}
						}


					}
				}
			}
		}

		return $fields;

	}


}
