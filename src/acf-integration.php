<?php
/**
 * Advanced Custom Fields (ACF) integration functions for handling user address and contact info fields.
 *
 * @package DmbcTools
 */

declare(strict_types=1);
namespace DmbcTools;

/**
 * A class for the Advanced Custom Fields (ACF) information we attach to the users.
 */
class AcfIntegration {

	/**
	 * Register the ACF address and Phone fields
	 *
	 * @return void
	 */
	public static function register_acf_fields(): void {
		add_action(
			'acf/include_fields',
			function () {
				if ( ! function_exists( 'acf_add_local_field_group' ) ) {
					return;
				}

				acf_add_local_field_group(
					array(
						'key'                   => 'group_69a4b16b17eb9',
						'title'                 => 'Address',
						'fields'                => array(
							array(
								'key'               => 'field_69a4b16aa4a13',
								'label'             => 'Mailing Street',
								'name'              => 'mailing_street',
								'aria-label'        => '',
								'type'              => 'text',
								'instructions'      => '',
								'required'          => 0,
								'conditional_logic' => 0,
								'wrapper'           => array(
									'width' => '',
									'class' => '',
									'id'    => '',
								),
								'default_value'     => '',
								'maxlength'         => '',
								'allow_in_bindings' => 0,
								'placeholder'       => '',
								'prepend'           => '',
								'append'            => '',
							),
							array(
								'key'               => 'field_69a4b1a6a4a14',
								'label'             => 'Mailing City',
								'name'              => 'mailing_city',
								'aria-label'        => '',
								'type'              => 'text',
								'instructions'      => '',
								'required'          => 0,
								'conditional_logic' => 0,
								'wrapper'           => array(
									'width' => '',
									'class' => '',
									'id'    => '',
								),
								'default_value'     => '',
								'maxlength'         => '',
								'allow_in_bindings' => 0,
								'placeholder'       => '',
								'prepend'           => '',
								'append'            => '',
							),
							array(
								'key'               => 'field_69a4b1bba4a15',
								'label'             => 'Mailing State/Prov',
								'name'              => 'mailing_stateprov',
								'aria-label'        => '',
								'type'              => 'text',
								'instructions'      => '',
								'required'          => 0,
								'conditional_logic' => 0,
								'wrapper'           => array(
									'width' => '',
									'class' => '',
									'id'    => '',
								),
								'default_value'     => '',
								'maxlength'         => '',
								'allow_in_bindings' => 0,
								'placeholder'       => '',
								'prepend'           => '',
								'append'            => '',
							),
							array(
								'key'               => 'field_69a4b1caa4a16',
								'label'             => 'Mailing Zip/Postal',
								'name'              => 'mailing_zippostal',
								'aria-label'        => '',
								'type'              => 'text',
								'instructions'      => '',
								'required'          => 0,
								'conditional_logic' => 0,
								'wrapper'           => array(
									'width' => '',
									'class' => '',
									'id'    => '',
								),
								'default_value'     => '',
								'maxlength'         => '',
								'allow_in_bindings' => 0,
								'placeholder'       => '',
								'prepend'           => '',
								'append'            => '',
							),
						),
						'location'              => array(
							array(
								array(
									'param'    => 'user_form',
									'operator' => '==',
									'value'    => 'all',
								),
							),
						),
						'menu_order'            => 0,
						'position'              => 'normal',
						'style'                 => 'default',
						'label_placement'       => 'top',
						'instruction_placement' => 'label',
						'hide_on_screen'        => '',
						'active'                => true,
						'description'           => '',
						'show_in_rest'          => 0,
						'display_title'         => '',
						'allow_ai_access'       => false,
						'ai_description'        => '',
					)
				);

				acf_add_local_field_group(
					array(
						'key'                   => 'group_6aa438ced538d',
						'title'                 => 'Contact Info',
						'fields'                => array(
							array(
								'key'               => 'field_6aa438ce4ed00',
								'label'             => 'Telephone',
								'name'              => 'telephone',
								'aria-label'        => '',
								'type'              => 'text',
								'instructions'      => '',
								'required'          => 0,
								'conditional_logic' => 0,
								'wrapper'           => array(
									'width' => '',
									'class' => '',
									'id'    => '',
								),
								'default_value'     => '',
								'maxlength'         => '',
								'allow_in_bindings' => 0,
								'placeholder'       => 'phone',
								'prepend'           => '',
								'append'            => '',
							),
						),
						'location'              => array(
							array(
								array(
									'param'    => 'user_form',
									'operator' => '==',
									'value'    => 'all',
								),
							),
						),
						'menu_order'            => 0,
						'position'              => 'normal',
						'style'                 => 'default',
						'label_placement'       => 'top',
						'instruction_placement' => 'label',
						'hide_on_screen'        => '',
						'active'                => true,
						'description'           => '',
						'show_in_rest'          => 0,
						'display_title'         => '',
						'allow_ai_access'       => false,
						'ai_description'        => '',
					)
				);
			}
		);
	}
}
