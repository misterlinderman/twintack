<?php
/**
 * Custom Grip Inquiry page fields.
 *
 * Page settings for the simplified custom grip intake template.
 * Gravity Forms notifications send the request to sales.
 *
 * @package twintack2025
 */

/**
 * Registers the inquiry page field group.
 */
class TwinTack_Custom_Grip_Inquiry_Fields {
	/**
	 * Singleton instance.
	 *
	 * @var TwinTack_Custom_Grip_Inquiry_Fields|null
	 */
	private static $instance = null;

	/**
	 * Default introduction shown until the page field is saved.
	 *
	 * @return string
	 */
	public static function default_intro_message() {
		return '<p>Thank you for your custom grip request. A TwinTack team member will reach out within the next 24 hours to confirm your design.</p>'
			. '<p>To help us get started, please share your logo, design idea details, and desired quantities in the form below.</p>';
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return TwinTack_Custom_Grip_Inquiry_Fields
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook field registration.
	 */
	private function __construct() {
		add_action( 'acf/init', array( $this, 'register_fields' ) );
		add_filter( 'acf/load_field/name=inquiry_gravity_form', array( $this, 'populate_form_choices' ) );
	}

	/**
	 * Register the inquiry page settings.
	 *
	 * @return void
	 */
	public function register_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'                   => 'group_custom_grip_inquiry',
				'title'                 => 'Custom Grip Inquiry',
				'fields'                => array(
					array(
						'key'           => 'field_inquiry_intro_message',
						'label'         => 'Introduction Message',
						'name'          => 'inquiry_intro_message',
						'type'          => 'wysiwyg',
						'instructions'  => 'Shown above the form. Tell the customer that TwinTack will follow up, and what to include in the request.',
						'required'      => 0,
						'default_value' => self::default_intro_message(),
						'tabs'          => 'all',
						'toolbar'       => 'basic',
						'media_upload'  => 0,
						'delay'         => 0,
					),
					array(
						'key'           => 'field_inquiry_gravity_form',
						'label'         => 'Intake Form',
						'name'          => 'inquiry_gravity_form',
						'type'          => 'select',
						'instructions'  => 'Choose the simplified Gravity Form. Form notifications send the request to sales. This page does not add the request to checkout.',
						'required'      => 1,
						'choices'       => array(),
						'allow_null'    => 1,
						'ui'            => 1,
						'ajax'          => 0,
						'return_format' => 'value',
						'placeholder'   => 'Select a form',
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'page_template',
							'operator' => '==',
							'value'    => 'page-custom-grip-inquiry.php',
						),
					),
				),
				'menu_order'            => 0,
				'position'              => 'normal',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen'        => array( 'the_content' ),
				'active'                => true,
				'description'           => 'Introduction and form for the simplified custom grip inquiry.',
			)
		);
	}

	/**
	 * Fill the form dropdown from Gravity Forms.
	 *
	 * @param array $field ACF field settings.
	 * @return array
	 */
	public function populate_form_choices( $field ) {
		$field['choices'] = array();

		if ( ! class_exists( 'GFAPI' ) ) {
			$field['choices'][''] = 'Gravity Forms is not available';
			return $field;
		}

		$forms = GFAPI::get_forms();
		if ( empty( $forms ) || is_wp_error( $forms ) ) {
			return $field;
		}

		foreach ( $forms as $form ) {
			if ( empty( $form['id'] ) ) {
				continue;
			}
			$label = isset( $form['title'] ) ? $form['title'] : 'Untitled form';
			$field['choices'][ (string) $form['id'] ] = $label . ' (ID ' . $form['id'] . ')';
		}

		return $field;
	}

	/**
	 * Render the selected Gravity Form.
	 *
	 * Notifications and confirmation stay in the form settings.
	 *
	 * @param int $form_id Gravity Form ID.
	 * @return string
	 */
	public static function render_form( $form_id ) {
		$form_id = absint( $form_id );

		if ( ! $form_id || ! function_exists( 'gravity_form' ) ) {
			return self::missing_form_notice();
		}

		if ( class_exists( 'GFAPI' ) ) {
			$form = GFAPI::get_form( $form_id );
			if ( empty( $form ) || is_wp_error( $form ) ) {
				return self::missing_form_notice();
			}
		}

		return gravity_form( $form_id, false, false, false, null, true, 0, false );
	}

	/**
	 * Editor-only note when the intake form is not selected.
	 *
	 * @return string
	 */
	private static function missing_form_notice() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return '';
		}

		return '<p class="custom-grip-inquiry__notice">' . esc_html__( 'Select the intake form in the Custom Grip Inquiry settings on this page.', 'twintack2025' ) . '</p>';
	}
}

TwinTack_Custom_Grip_Inquiry_Fields::get_instance();
