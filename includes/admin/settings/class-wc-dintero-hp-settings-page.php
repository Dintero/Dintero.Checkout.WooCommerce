<?php
/**
 * WooCommerce Dintero HP Settings Page/Tab
 *
 * @package     WDHP/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Dintero_HP_Settings_Page', false ) ) :

	/**
	 * WC_Dintero_HP_Settings_Page.
	 */
	abstract class WC_Dintero_HP_Settings_Page {

		/**
		 * The plugin ID. Used for option names.
		 *
		 * @var string
		 */
		public $plugin_id = 'woocommerce_';

		/**
		 * Setting page id.
		 *
		 * @var string
		 */
		protected $id = '';

		/**
		 * Setting page label.
		 *
		 * @var string
		 */
		protected $label = '';

		/**
		 * Form option fields.
		 *
		 * @var array
		 */
		protected $form_fields = array();

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->add_scripts();
			//add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
			//add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
			add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
			add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
			add_action( 'woocommerce_update_options_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		private function add_scripts() {
			$suffix       = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_register_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION );
			wp_enqueue_style( 'woocommerce_admin_styles' );

			wp_register_style( 'jquery-tiptip-css', WCDHP()->plugin_url() . '/assets/css/tipTip.css', array(), WC_VERSION );
			wp_enqueue_style( 'jquery-tiptip-css' );

			wp_register_script( 'jquery-tiptip', WCDHP()->plugin_url() . '/assets/js/admin/jquery.tipTip' . $suffix . '.js', array(), WC_VERSION, true );

			wp_register_script( 'wcdhp-setting', WCDHP()->plugin_url() . '/assets/js/admin/wcdhp-setting.js', array(), WC_VERSION );

			wp_enqueue_script( 'jquery-tiptip' );
			wp_enqueue_script( 'wcdhp-setting' );
		}

		/**
		 * Get settings page ID.
		 *
		 * @since 3.0.0
		 * @return string
		 */
		public function get_id() {
			return $this->id;
		}

		/**
		 * Get settings page label.
		 *
		 * @since 3.0.0
		 * @return string
		 */
		public function get_label() {
			return $this->label;
		}

		/**
		 * Add this page to settings.
		 *
		 * @param array $pages
		 *
		 * @return mixed
		 */
		public function add_settings_page( $pages ) {
			$pages[ $this->id ] = $this->label;

			return $pages;
		}

		/**
		 * Get settings array.
		 *
		 * @return array
		 */
		public function get_settings() {
			return apply_filters( 'woocommerce_get_settings_' . $this->id, array() );
		}

		/**
		 * Get sections.
		 *
		 * @return array
		 */
		public function get_sections() {
			return apply_filters( 'woocommerce_get_sections_' . $this->id, array() );
		}

		/**
		 * Output sections.
		 */
		public function output_sections() {
			global $current_section;

			$sections = $this->get_sections();

			if ( empty( $sections ) || 1 === count( $sections ) ) {
				return;
			}

			echo '<ul class="subsubsub">';

			$array_keys = array_keys( $sections );

			foreach ( $sections as $id => $label ) {
				echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=wc-dintero-settings&tab=' . $this->id . '&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) ) . ' </li>';
			}

			echo '</ul><br class="clear" />';
		}

		/**
		 * Output the settings.
		 */
		public function output() {
			$settings = $this->get_settings();

			WC_Dintero_HP_Admin_Settings::output_fields( $settings );
		}

		/**
		 * Save settings.
		 */
		public function save() {
			global $current_section;

			$settings = $this->get_settings();
			WC_Dintero_HP_Admin_Settings::save_fields( $settings );

			if ( $current_section ) {
				do_action( 'woocommerce_update_options_' . $this->id . '_' . $current_section );
			}
		}


		/**
		 * Get the form fields after they are initialized.
		 *
		 * @return array of options
		 */
		public function get_form_fields() {
			return apply_filters( 'woocommerce_settings_api_form_fields_' . $this->id, array_map( array( $this, 'set_defaults' ), $this->form_fields ) );
		}

		/**
		 * Set default required properties for each field.
		 *
		 * @param array $field Setting field array.
		 * @return array
		 */
		protected function set_defaults( $field ) {
			if ( ! isset( $field['default'] ) ) {
				$field['default'] = '';
			}
			return $field;
		}

		/**
		 * Output the admin options table.
		 */
		public function admin_options() {
			echo( '<table class="form-table">' );
			$this->generate_settings_html( $this->get_form_fields() );
			echo( '</table>' );

			$nonce = wp_create_nonce( 'dhp-nonce' );
			echo( '<input type="hidden" id="_dhp_setting_nonce" name="_dhp_setting_nonce" value="' . esc_attr( $nonce ) . '" />' );
		}

		/**
		 * Initialise settings form fields.
		 *
		 * Add an array of fields to be displayed on the gateway's settings screen.
		 *
		 * @since  1.0.0
		 */
		public function init_form_fields() {}

		/**
		 * Return the name of the option in the WP DB.
		 *
		 * @since 2.6.0
		 * @return string
		 */
		public function get_option_key() {
			return $this->plugin_id . $this->id . '_settings';
		}

		/**
		 * Get a fields type. Defaults to "text" if not set.
		 *
		 * @param  array $field Field key.
		 * @return string
		 */
		public function get_field_type( $field ) {
			return empty( $field['type'] ) ? 'text' : $field['type'];
		}

		/**
		 * Get a fields default value. Defaults to "" if not set.
		 *
		 * @param  array $field Field key.
		 * @return string
		 */
		public function get_field_default( $field ) {
			return empty( $field['default'] ) ? '' : $field['default'];
		}

		/**
		 * Get a field's posted and validated value.
		 *
		 * @param string $key Field key.
		 * @param array  $field Field array.
		 * @param array  $post_data Posted data.
		 * @return string
		 */
		public function get_field_value( $key, $field, $post_data = array() ) {
			try {
				$type      = $this->get_field_type( $field );
				$field_key = $this->get_field_key( $key );

				if ( isset( $_REQUEST['_dhp_setting_nonce'] ) ) {
					$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_dhp_setting_nonce'] ) );
					if ( ! wp_verify_nonce( $nonce, 'dhp-nonce' ) ) {
						echo( 'We were unable to process your request' );
					} else {
						$post_data = empty( $post_data ) ? $_POST : $post_data; // WPCS: CSRF ok, input var ok.
						$value     = isset( $post_data[ $field_key ] ) ? $post_data[ $field_key ] : null;

						if ( isset( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
							return call_user_func( $field['sanitize_callback'], $value );
						}

						// Look for a validate_FIELDID_field method for special handling.
						if ( is_callable( array( $this, 'validate_' . $key . '_field' ) ) ) {
							return $this->{'validate_' . $key . '_field'}( $key, $value );
						}

						// Look for a validate_FIELDTYPE_field method.
						if ( is_callable( array( $this, 'validate_' . $type . '_field' ) ) ) {
							return $this->{'validate_' . $type . '_field'}( $key, $value );
						}

						// Fallback to text.
						return $this->validate_text_field( $key, $value );
					}
				}
			} catch ( Exception $e ) {
				wc_add_notice( $e->getMessage(), 'error' );
			}
		}

		/**
		 * Sets the POSTed data. This method can be used to set specific data, instead of taking it from the $_POST array.
		 *
		 * @param array $data Posted data.
		 */
		public function set_post_data( $data = array() ) {
			$this->data = $data;
		}

		/**
		 * Returns the POSTed data, to be used to save the settings.
		 *
		 * @return array
		 */
		public function get_post_data() {
			try {
				if ( isset( $_REQUEST['_dhp_setting_nonce'] ) ) {
					$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_dhp_setting_nonce'] ) );
					if ( ! wp_verify_nonce( $nonce, 'dhp-nonce' ) ) {
						echo( 'We were unable to process your request' );
					} else {
						if ( ! empty( $this->data ) && is_array( $this->data ) ) {
							return $this->data;
						}
						return $_POST; // WPCS: CSRF ok, input var ok.
					}
				}
			} catch ( Exception $e ) {
				wc_add_notice( $e->getMessage(), 'error' );
			}
		}

		/**
		 * Update a single option.
		 *
		 * @since 3.4.0
		 * @param string $key Option key.
		 * @param mixed  $value Value to set.
		 * @return bool was anything saved?
		 */
		public function update_option( $key, $value = '' ) {
			if ( empty( $this->settings ) ) {
				$this->init_settings();
			}

			$this->settings[ $key ] = $value;

			return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
		}

		/**
		 * Processes and saves options.
		 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
		 *
		 * @return bool was anything saved?
		 */
		public function process_admin_options() {
			$this->init_settings();

			$post_data = $this->get_post_data();

			foreach ( $this->get_form_fields() as $key => $field ) {
				if ( 'title' !== $this->get_field_type( $field ) ) {
					try {
						$this->settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
					} catch ( Exception $e ) {
						$this->add_error( $e->getMessage() );
					}
				}
			}

			return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
		}

		/**
		 * Add an error message for display in admin on save.
		 *
		 * @param string $error Error message.
		 */
		public function add_error( $error ) {
			$this->errors[] = $error;
		}

		/**
		 * Get admin error messages.
		 */
		public function get_errors() {
			return $this->errors;
		}

		/**
		 * Display admin error messages.
		 */
		public function display_errors() {
			if ( $this->get_errors() ) {
				echo '<div id="woocommerce_errors" class="error notice is-dismissible">';
				foreach ( $this->get_errors() as $error ) {
					echo '<p>' . wp_kses_post( $error ) . '</p>';
				}
				echo '</div>';
			}
		}

		/**
		 * Initialise Settings.
		 *
		 * Store all settings in a single database entry
		 * and make sure the $settings array is either the default
		 * or the settings stored in the database.
		 *
		 * @since 1.0.0
		 * @uses get_option(), add_option()
		 */
		public function init_settings() {
			$this->settings = get_option( $this->get_option_key(), null );

			// If there are no settings defined, use defaults.
			if ( ! is_array( $this->settings ) ) {
				$form_fields    = $this->get_form_fields();
				$this->settings = array_merge( array_fill_keys( array_keys( $form_fields ), '' ), wp_list_pluck( $form_fields, 'default' ) );
			}
		}

		/**
		 * Get option from DB.
		 *
		 * Gets an option from the settings API, using defaults if necessary to prevent undefined notices.
		 *
		 * @param  string $key Option key.
		 * @param  mixed  $empty_value Value when empty.
		 * @return string The value specified for the option or a default value for the option.
		 */
		public function get_option( $key, $empty_value = null ) {
			if ( empty( $this->settings ) ) {
				$this->init_settings();
			}

			// Get option default if unset.
			if ( ! isset( $this->settings[ $key ] ) ) {
				$form_fields            = $this->get_form_fields();
				$this->settings[ $key ] = isset( $form_fields[ $key ] ) ? $this->get_field_default( $form_fields[ $key ] ) : '';
			}

			if ( ! is_null( $empty_value ) && '' === $this->settings[ $key ] ) {
				$this->settings[ $key ] = $empty_value;
			}

			return $this->settings[ $key ];
		}

		/**
		 * Prefix key for settings.
		 *
		 * @param  string $key Field key.
		 * @return string
		 */
		public function get_field_key( $key ) {
			return $this->plugin_id . $this->id . '_' . $key;
		}

		/**
		 * Generate Settings HTML.
		 *
		 * Generate the HTML for the fields on the "settings" screen.
		 *
		 * @param array $form_fields (default: array()) Array of form fields.
		 * @param bool  $echo Echo or return.
		 * @return string the html for the settings
		 * @since  1.0.0
		 * @uses   method_exists()
		 */
		public function generate_settings_html( $form_fields = array() ) {
			if ( empty( $form_fields ) ) {
				$form_fields = $this->get_form_fields();
			}

			$html = '';
			foreach ( $form_fields as $k => $v ) {
				$type = $this->get_field_type( $v );

				if ( method_exists( $this, 'generate_' . $type . '_html' ) ) {
					$html .= $this->{'generate_' . $type . '_html'}( $k, $v );
				} else {
					$html .= $this->generate_text_html( $k, $v );
				}
			}

			return $html;
		}

		/**
		 * Get HTML for tooltips.
		 *
		 * @param  array $data Data for the tooltip.
		 * @return string
		 */
		public function get_tooltip_html( $data ) {
			if ( true === $data['desc_tip'] ) {
				$tip = $data['description'];
			} elseif ( ! empty( $data['desc_tip'] ) ) {
				$tip = $data['desc_tip'];
			} else {
				$tip = '';
			}

			return $tip ? $this->wc_help_tip( $tip, true ) : '';
		}

		/**
		 * Display a WooCommerce help tip.
		 *
		 * @since  2.5.0
		 *
		 * @param  string $tip        Help tip text.
		 * @param  bool   $allow_html Allow sanitized HTML if true or escape.
		 * @return string
		 */
		public function wc_help_tip( $tip, $allow_html = false ) {
			if ( $allow_html ) {
				$tip = $this->wc_sanitize_tooltip( $tip );
			} else {
				$tip = esc_attr( $tip );
			}

			return '<span class="woocommerce-help-tip" data-tip="' . $tip . '"></span>';
		}

		/**
		 * Sanitize a string destined to be a tooltip.
		 *
		 * @since  2.3.10 Tooltips are encoded with htmlspecialchars to prevent XSS. Should not be used in conjunction with esc_attr()
		 * @param  string $var Data to sanitize.
		 * @return string
		 */
		public function wc_sanitize_tooltip( $var ) {
			return htmlspecialchars(
				wp_kses(
					html_entity_decode( $var ),
					array(
						'br'     => array(),
						'em'     => array(),
						'strong' => array(),
						'small'  => array(),
						'span'   => array(),
						'ul'     => array(),
						'li'     => array(),
						'ol'     => array(),
						'p'      => array(),
					)
				)
			);
		}

		/**
		 * Get HTML for descriptions.
		 *
		 * @param  array $data Data for the description.
		 * @return string
		 */
		public function get_description_html( $data ) {
			if ( true === $data['desc_tip'] ) {
				$description = '';
			} elseif ( ! empty( $data['desc_tip'] ) ) {
				$description = $data['description'];
			} elseif ( ! empty( $data['description'] ) ) {
				$description = $data['description'];
			} else {
				$description = '';
			}

			return $description ? '<p class="description">' . wp_kses_post( $description ) . '</p>' . "\n" : '';
		}

		/**
		 * Get custom attributes.
		 *
		 * @param  array $data Field data.
		 * @return string
		 */
		public function get_custom_attribute_html( $data ) {
			$custom_attributes = array();

			if ( ! empty( $data['custom_attributes'] ) && is_array( $data['custom_attributes'] ) ) {
				foreach ( $data['custom_attributes'] as $attribute => $attribute_value ) {
					$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
				}
			}

			return implode( ' ', $custom_attributes );
		}

		/**
		 * Generate Text Input HTML.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_text_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
			);

			$data = wp_parse_args( $data, $defaults );

			echo( '
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field_key ); ?>">' . esc_html( $data['title'] ) . wp_kses_post( $this->get_tooltip_html( $data ) ) . '</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>' . wp_kses_post( $data['title'] ) . '</span></legend>
						<input class="input-text regular-input ' . esc_attr( $data['class'] ) . '" type="' . esc_attr( $data['type'] ) . '" name="' . esc_attr( $field_key ) . '" id="' . esc_attr( $field_key ) . '" style="' . esc_attr( $data['css'] ) . '" value="' . esc_attr( $this->get_option( $key ) ) . '" placeholder="' . esc_attr( $data['placeholder'] ) . '" ' . disabled( $data['disabled'], true ) . ' ' . wp_kses_post( $this->get_custom_attribute_html( $data ) ) . ' />' . wp_kses_post( $this->get_description_html( $data ) ) . '
					</fieldset>
				</td>
			</tr>' );
		}

		/**
		 * Generate Price Input HTML.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_price_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
			);

			$data = wp_parse_args( $data, $defaults );

			echo( '
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="' . esc_attr( $field_key ) . '">' . wp_kses_post( $data['title'] ) . wp_kses_post( $this->get_tooltip_html( $data ) ) . '</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>' . wp_kses_post( $data['title'] ) . '</span></legend>
						<input class="wc_input_price input-text regular-input ' . esc_attr( $data['class'] ) . '" type="text" name="' . esc_attr( $field_key ) . '" id="' . esc_attr( $field_key ) . '" style="' . esc_attr( $data['css'] ) . '" value="' . esc_attr( wc_format_localized_price( $this->get_option( $key ) ) ) . '" placeholder="' . esc_attr( $data['placeholder'] ) . '" ' . disabled( $data['disabled'], true ) . ' ' . wp_kses_post( $this->get_custom_attribute_html( $data ) ) . ' />' . wp_kses_post( $this->get_description_html( $data ) ) . '
					</fieldset>
				</td>
			</tr>' );
		}

		/**
		 * Generate Decimal Input HTML.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_decimal_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
			);

			$data = wp_parse_args( $data, $defaults );

			echo( '
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="' . esc_attr( $field_key ) . '">' . wp_kses_post( $data['title'] ) . wp_kses_post( $this->get_tooltip_html( $data ) ) . '</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>' . wp_kses_post( $data['title'] ) . '</span></legend>
						<input class="wc_input_decimal input-text regular-input ' . esc_attr( $data['class'] ) . '" type="text" name="' . esc_attr( $field_key ) . '" id="' . esc_attr( $field_key ) . '" style="' . esc_attr( $data['css'] ) . '" value="' . esc_attr( wc_format_localized_decimal( $this->get_option( $key ) ) ) . '" placeholder="' . esc_attr( $data['placeholder'] ) . '" ' . disabled( $data['disabled'], true ) . ' ' . wp_kses_post( $this->get_custom_attribute_html( $data ) ) . ' />' . wp_kses_post( $this->get_description_html( $data ) ) . '
					</fieldset>
				</td>
			</tr>' );			
		}

		/**
		 * Generate Password Input HTML.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_password_html( $key, $data ) {
			$data['type'] = 'password';
			$this->generate_text_html( $key, $data );
		}

		/**
		 * Generate Color Picker Input HTML.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_color_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
			);

			$data = wp_parse_args( $data, $defaults );

			echo( '
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="' . esc_attr( $field_key ) . '">' . wp_kses_post( $data['title'] ) . wp_kses_post( $this->get_tooltip_html( $data ) ) . '</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>' . wp_kses_post( $data['title'] ) . '</span></legend>
						<span class="colorpickpreview" style="background:' . esc_attr( $this->get_option( $key ) ) . ';">&nbsp;</span>
						<input class="colorpick ' . esc_attr( $data['class'] ) . '" type="text" name="' . esc_attr( $field_key ) . '" id="' . esc_attr( $field_key ) . '" style="' . esc_attr( $data['css'] ) . '" value="' . esc_attr( $this->get_option( $key ) ) . '" placeholder="' . esc_attr( $data['placeholder'] ) . '" ' . disabled( $data['disabled'], true ) . ' ' . wp_kses_post( $this->get_custom_attribute_html( $data ) ) . '/>
						<div id="colorPickerDiv_' . esc_attr( $field_key ) . '" class="colorpickdiv" style="z-index: 100; background: #eee; border: 1px solid #ccc; position: absolute; display: none;"></div>' . wp_kses_post( $this->get_description_html( $data ) ) . '
					</fieldset>
				</td>
			</tr>' );
		}

		/**
		 * Generate Textarea HTML.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_textarea_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
			);

			$data = wp_parse_args( $data, $defaults );

			echo( '
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="' . esc_attr( $field_key ) . '">' . wp_kses_post( $data['title'] ) . wp_kses_post( $this->get_tooltip_html( $data ) ) . '</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>' . wp_kses_post( $data['title'] ) . '</span></legend>
						<textarea rows="3" cols="20" class="input-text wide-input ' . esc_attr( $data['class'] ) . '" type="' . esc_attr( $data['type'] ) . '" name="' . esc_attr( $field_key ) . '" id="' . esc_attr( $field_key ) . '" style="' . esc_attr( $data['css'] ) . '" placeholder="' . esc_attr( $data['placeholder'] ) . '" ' . disabled( $data['disabled'], true ) . ' ' . wp_kses_post( $this->get_custom_attribute_html( $data ) ) . '>' . esc_textarea( $this->get_option( $key ) ) . '</textarea>' . wp_kses_post( $this->get_description_html( $data ) ) . '
					</fieldset>
				</td>
			</tr>' );
		}

		/**
		 * Generate Checkbox HTML.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_checkbox_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title'             => '',
				'label'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
			);

			$data = wp_parse_args( $data, $defaults );

			if ( ! $data['label'] ) {
				$data['label'] = $data['title'];
			}

			echo( '
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="' . esc_attr( $field_key ) . '">' . wp_kses_post( $data['title'] ) . wp_kses_post( $this->get_tooltip_html( $data ) ) . '</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>' . wp_kses_post( $data['title'] ) . '</span></legend>
						<label for="' . esc_attr( $field_key ) . '">
						<input ' . disabled( $data['disabled'], true ) . ' class="' . esc_attr( $data['class'] ) . '" type="checkbox" name="' . esc_attr( $field_key ) . '" id="' . esc_attr( $field_key ) . '" style="' . esc_attr( $data['css'] ) . '" value="1" ' );
			checked( $this->get_option( $key ), 'yes' );
			echo( ' ' . wp_kses_post( $this->get_custom_attribute_html( $data ) ) . ' /> ' . wp_kses_post( $data['label'] ) . '</label><br/>' . wp_kses_post( $this->get_description_html( $data ) ) . '
					</fieldset>
				</td>
			</tr>' );
		}

		/**
		 * Generate Select HTML.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_select_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
				'options'           => array(),
			);

			$data = wp_parse_args( $data, $defaults );

			echo( '
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="' . esc_attr( $field_key ) . '">' . wp_kses_post( $data['title'] ) . ' ' . wp_kses_post( $this->get_tooltip_html( $data ) ) . '</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>' . wp_kses_post( $data['title'] ) . '</span></legend>
						<select class="select ' . esc_attr( $data['class'] ) . '" name="' . esc_attr( $field_key ) . '" id="' . esc_attr( $field_key ) . '" style="' . esc_attr( $data['css'] ) . '" ' . disabled( $data['disabled'], true ) . ' ' . wp_kses_post( $this->get_custom_attribute_html( $data ) ) . '>' );

			foreach ( (array) $data['options'] as $option_key => $option_value ) {
				echo( '<option value="' . esc_attr( $option_key ) . '" ' . selected( (string) $option_key, esc_attr( $this->get_option( $key ) ) ) . '>' . esc_attr( $option_value ) . '</option>' );
			}
						
			echo( '</select>' . wp_kses_post( $this->get_description_html( $data ) ) . '
					</fieldset>
				</td>
			</tr>' );
		}

		/**
		 * Generate Multiselect HTML.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_multiselect_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
				'select_buttons'    => false,
				'options'           => array(),
			);

			$data  = wp_parse_args( $data, $defaults );
			$value = (array) $this->get_option( $key, array() );

			echo( '
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="' . esc_attr( $field_key ) . '">' . wp_kses_post( $data['title'] ) . ' ' . wp_kses_post( $this->get_tooltip_html( $data ) ) . '</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>' . wp_kses_post( $data['title'] ) . '</span></legend>
						<select multiple="multiple" class="multiselect ' . esc_attr( $data['class'] ) . '" name="' . esc_attr( $field_key ) . '[]" id="' . esc_attr( $field_key ) . '" style="' . esc_attr( $data['css'] ) . '" ' . disabled( $data['disabled'], true ) . ' ' . wp_kses_post( $this->get_custom_attribute_html( $data ) ) . '>' );

			foreach ( (array) $data['options'] as $option_key => $option_value ) {
				if ( is_array( $option_value ) ) {
					echo( '<optgroup label="' . esc_attr( $option_key ) . '">' );
					foreach ( $option_value as $option_key_inner => $option_value_inner ) {
						echo( '<option value="' . esc_attr( $option_key_inner ) . '" ' . selected( in_array( (string) $option_key_inner, $value, true ), true ) . '>' . esc_attr( $option_value_inner ) . '</option>' );
					}
					echo( '</optgroup>' );
				} else {
					echo( '<option value="' . esc_attr( $option_key ) . '" ' . selected( in_array( (string) $option_key, $value, true ), true ) . '>' . esc_attr( $option_value ) . '</option>' );
				}
			}

			echo( '</select>' . wp_kses_post( $this->get_description_html( $data ) ) );

			if ( $data['select_buttons'] ) {
				echo( '<br/><a class="select_all button" href="#">' );
				esc_html_e( 'Select all', 'woocommerce' );
				echo( '</a> <a class="select_none button" href="#">' );
				esc_html_e( 'Select none', 'woocommerce' );
				echo( '</a>' );
			}

			echo( '</fieldset>
				</td>
			</tr>' );
		}

		/**
		 * Generate Title HTML.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_title_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title' => '',
				'class' => '',
			);

			$data = wp_parse_args( $data, $defaults );

			echo( '
				</table>
				<h3 class="wc-settings-sub-title ' . esc_attr( $data['class'] ) . '" id="' . esc_attr( $field_key ) . '">' . wp_kses_post( $data['title'] ) . '</h3>' );

			if ( ! empty( $data['description'] ) ) {
				echo( '<p>' . wp_kses_post( $data['description'] ) . '</p>' );
			}

			echo( '<table class="form-table">' );
		}

		/**
		 * Generate Title HTML.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_subtitle_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title' => '',
				'class' => '',
			);

			$data = wp_parse_args( $data, $defaults );

			echo( '
				</table>
				<h4 class="wc-settings-sub-title ' . esc_attr( $data['class'] ) . '" id="' . esc_attr( $field_key ) . '">' . wp_kses_post( $data['title'] ) . '</h4>' );

			if ( ! empty( $data['description'] ) ) {
				echo( '<p>' . wp_kses_post( $data['description'] ) . '</p>' );
			}

			echo( '<table class="form-table">' );			
		}

		/**
		 * Validate Text Field.
		 *
		 * Make sure the data is escaped correctly, etc.
		 *
		 * @param  string $key Field key.
		 * @param  string $value Posted Value.
		 * @return string
		 */
		public function validate_text_field( $key, $value ) {
			$value = is_null( $value ) ? '' : $value;
			return wp_kses_post( trim( stripslashes( $value ) ) );
		}

		/**
		 * Validate Price Field.
		 *
		 * Make sure the data is escaped correctly, etc.
		 *
		 * @param  string $key Field key.
		 * @param  string $value Posted Value.
		 * @return string
		 */
		public function validate_price_field( $key, $value ) {
			$value = is_null( $value ) ? '' : $value;
			return ( '' === $value ) ? '' : wc_format_decimal( trim( stripslashes( $value ) ) );
		}

		/**
		 * Validate Decimal Field.
		 *
		 * Make sure the data is escaped correctly, etc.
		 *
		 * @param  string $key Field key.
		 * @param  string $value Posted Value.
		 * @return string
		 */
		public function validate_decimal_field( $key, $value ) {
			$value = is_null( $value ) ? '' : $value;
			return ( '' === $value ) ? '' : wc_format_decimal( trim( stripslashes( $value ) ) );
		}

		/**
		 * Validate Password Field. No input sanitization is used to avoid corrupting passwords.
		 *
		 * @param  string $key Field key.
		 * @param  string $value Posted Value.
		 * @return string
		 */
		public function validate_password_field( $key, $value ) {
			$value = is_null( $value ) ? '' : $value;
			return trim( stripslashes( $value ) );
		}

		/**
		 * Validate Textarea Field.
		 *
		 * @param  string $key Field key.
		 * @param  string $value Posted Value.
		 * @return string
		 */
		public function validate_textarea_field( $key, $value ) {
			$value = is_null( $value ) ? '' : $value;
			return wp_kses( trim( stripslashes( $value ) ),
				array_merge(
					array(
						'iframe' => array(
							'src'   => true,
							'style' => true,
							'id'    => true,
							'class' => true,
						),
					),
					wp_kses_allowed_html( 'post' )
				)
			);
		}

		/**
		 * Validate Checkbox Field.
		 *
		 * If not set, return "no", otherwise return "yes".
		 *
		 * @param  string $key Field key.
		 * @param  string $value Posted Value.
		 * @return string
		 */
		public function validate_checkbox_field( $key, $value ) {
			return ! is_null( $value ) ? 'yes' : 'no';
		}

		/**
		 * Validate Select Field.
		 *
		 * @param  string $key Field key.
		 * @param  string $value Posted Value.
		 * @return string
		 */
		public function validate_select_field( $key, $value ) {
			$value = is_null( $value ) ? '' : $value;
			return wc_clean( stripslashes( $value ) );
		}

		/**
		 * Validate Multiselect Field.
		 *
		 * @param  string $key Field key.
		 * @param  string $value Posted Value.
		 * @return string|array
		 */
		public function validate_multiselect_field( $key, $value ) {
			return is_array( $value ) ? array_map( 'wc_clean', array_map( 'stripslashes', $value ) ) : '';
		}

		/**
		 * Validate the data on the "Settings" form.
		 *
		 * @deprecated 2.6.0 No longer used.
		 * @param array $form_fields Array of fields.
		 */
		public function validate_settings_fields( $form_fields = array() ) {
			wc_deprecated_function( 'validate_settings_fields', '2.6' );
		}

		/**
		 * Format settings if needed.
		 *
		 * @deprecated 2.6.0 Unused.
		 * @param  array $value Value to format.
		 * @return array
		 */
		public function format_settings( $value ) {
			wc_deprecated_function( 'format_settings', '2.6' );
			return $value;
		}

	}

endif;
