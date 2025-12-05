<?php
/**
 * Elementor JSON Schema Validator
 *
 * Validates AI-generated JSON against Elementor's expected structure.
 *
 * @package AiMentor
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Elementor_Schema_Validator {

	/**
	 * Valid Elementor element types.
	 *
	 * @var array
	 */
	const ELEMENT_TYPES = [ 'section', 'column', 'widget', 'container' ];

	/**
	 * Valid free Elementor widget types.
	 *
	 * @var array
	 */
	const FREE_WIDGET_TYPES = [
		'heading',
		'text-editor',
		'image',
		'video',
		'button',
		'divider',
		'spacer',
		'google_maps',
		'icon',
		'image-box',
		'icon-box',
		'star-rating',
		'icon-list',
		'counter',
		'progress',
		'testimonial',
		'tabs',
		'accordion',
		'toggle',
		'social-icons',
		'alert',
		'audio',
		'shortcode',
		'html',
		'menu-anchor',
		'sidebar',
		'read-more',
		'image-carousel',
		'basic-gallery',
	];

	/**
	 * Valid Elementor Pro widget types.
	 *
	 * @var array
	 */
	const PRO_WIDGET_TYPES = [
		'form',
		'posts',
		'portfolio',
		'slides',
		'nav-menu',
		'animated-headline',
		'price-list',
		'price-table',
		'flip-box',
		'call-to-action',
		'media-carousel',
		'testimonial-carousel',
		'reviews',
		'table-of-contents',
		'countdown',
		'share-buttons',
		'blockquote',
		'facebook-button',
		'facebook-comments',
		'facebook-embed',
		'facebook-page',
		'template',
		'global',
		'gallery',
		'lottie',
		'hotspot',
		'code-highlight',
		'video-playlist',
		'loop-grid',
		'loop-carousel',
	];

	/**
	 * Validation errors collected during validation.
	 *
	 * @var array
	 */
	protected $errors = [];

	/**
	 * Validation warnings (non-fatal issues).
	 *
	 * @var array
	 */
	protected $warnings = [];

	/**
	 * Track used element IDs to detect duplicates.
	 *
	 * @var array
	 */
	protected $used_ids = [];

	/**
	 * Whether Elementor Pro is active.
	 *
	 * @var bool
	 */
	protected $has_pro = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->has_pro = defined( 'ELEMENTOR_PRO_VERSION' );
	}

	/**
	 * Validate Elementor JSON structure.
	 *
	 * @param array|string $data The Elementor data to validate.
	 * @return array Validation result with 'valid', 'errors', 'warnings', and 'data' keys.
	 */
	public function validate( $data ) {
		$this->reset();

		// Handle string input
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$this->errors[] = [
					'code'    => 'invalid_json',
					'message' => 'Input is not valid JSON: ' . json_last_error_msg(),
					'path'    => 'root',
				];

				return $this->build_result( null );
			}
		}

		if ( ! is_array( $data ) ) {
			$this->errors[] = [
				'code'    => 'not_array',
				'message' => 'Elementor data must be an array or object.',
				'path'    => 'root',
			];

			return $this->build_result( null );
		}

		// Check for elements array (standard Elementor format)
		if ( isset( $data['elements'] ) && is_array( $data['elements'] ) ) {
			$this->validate_elements( $data['elements'], 'root' );
		} elseif ( isset( $data[0] ) && is_array( $data[0] ) ) {
			// Direct array of elements
			$this->validate_elements( $data, 'root' );
		} else {
			// Single element - check if it's a valid element
			if ( isset( $data['elType'] ) ) {
				$this->validate_element( $data, 'root', 0 );
			} else {
				$this->errors[] = [
					'code'    => 'missing_elements',
					'message' => 'No valid Elementor elements found. Expected "elements" array or array of element objects.',
					'path'    => 'root',
				];
			}
		}

		return $this->build_result( $data );
	}

	/**
	 * Validate an array of elements.
	 *
	 * @param array  $elements The elements to validate.
	 * @param string $path     The current path in the structure.
	 * @param string $parent_type The parent element type.
	 */
	protected function validate_elements( $elements, $path, $parent_type = null ) {
		if ( ! is_array( $elements ) ) {
			$this->errors[] = [
				'code'    => 'elements_not_array',
				'message' => 'Elements must be an array.',
				'path'    => $path,
			];
			return;
		}

		foreach ( $elements as $index => $element ) {
			$element_path = $path . '.elements[' . $index . ']';
			$this->validate_element( $element, $element_path, $index, $parent_type );
		}
	}

	/**
	 * Validate a single element.
	 *
	 * @param array  $element     The element to validate.
	 * @param string $path        The current path in the structure.
	 * @param int    $index       The element index.
	 * @param string $parent_type The parent element type.
	 */
	protected function validate_element( $element, $path, $index, $parent_type = null ) {
		if ( ! is_array( $element ) ) {
			$this->errors[] = [
				'code'    => 'element_not_array',
				'message' => 'Element must be an object.',
				'path'    => $path,
			];
			return;
		}

		// Validate ID
		$this->validate_element_id( $element, $path );

		// Validate elType
		if ( ! isset( $element['elType'] ) ) {
			$this->errors[] = [
				'code'    => 'missing_eltype',
				'message' => 'Element is missing required "elType" property.',
				'path'    => $path,
			];
			return;
		}

		$el_type = $element['elType'];

		if ( ! in_array( $el_type, self::ELEMENT_TYPES, true ) ) {
			$this->errors[] = [
				'code'    => 'invalid_eltype',
				'message' => sprintf( 'Invalid element type "%s". Must be one of: %s', $el_type, implode( ', ', self::ELEMENT_TYPES ) ),
				'path'    => $path,
			];
			return;
		}

		// Validate hierarchy
		$this->validate_hierarchy( $el_type, $parent_type, $path );

		// Validate widget-specific properties
		if ( 'widget' === $el_type ) {
			$this->validate_widget( $element, $path );
		}

		// Validate settings if present
		if ( isset( $element['settings'] ) && ! is_array( $element['settings'] ) ) {
			$this->errors[] = [
				'code'    => 'settings_not_array',
				'message' => 'Element settings must be an object.',
				'path'    => $path . '.settings',
			];
		}

		// Recursively validate child elements
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$this->validate_elements( $element['elements'], $path, $el_type );
		}
	}

	/**
	 * Validate element ID.
	 *
	 * @param array  $element The element to validate.
	 * @param string $path    The current path.
	 */
	protected function validate_element_id( $element, $path ) {
		if ( ! isset( $element['id'] ) ) {
			$this->errors[] = [
				'code'    => 'missing_id',
				'message' => 'Element is missing required "id" property.',
				'path'    => $path,
			];
			return;
		}

		$id = $element['id'];

		if ( ! is_string( $id ) || '' === $id ) {
			$this->errors[] = [
				'code'    => 'invalid_id_type',
				'message' => 'Element ID must be a non-empty string.',
				'path'    => $path,
			];
			return;
		}

		// Elementor IDs are typically 7-8 character alphanumeric
		if ( ! preg_match( '/^[a-z0-9]{1,10}$/i', $id ) ) {
			$this->warnings[] = [
				'code'    => 'id_format',
				'message' => sprintf( 'Element ID "%s" does not match typical Elementor format (alphanumeric, 7-8 chars).', $id ),
				'path'    => $path,
			];
		}

		// Check for duplicate IDs
		if ( in_array( $id, $this->used_ids, true ) ) {
			$this->errors[] = [
				'code'    => 'duplicate_id',
				'message' => sprintf( 'Duplicate element ID "%s" found.', $id ),
				'path'    => $path,
			];
		} else {
			$this->used_ids[] = $id;
		}
	}

	/**
	 * Validate element hierarchy.
	 *
	 * @param string $el_type     The element type.
	 * @param string $parent_type The parent element type.
	 * @param string $path        The current path.
	 */
	protected function validate_hierarchy( $el_type, $parent_type, $path ) {
		// Root level should only have sections or containers
		if ( null === $parent_type || 'root' === $parent_type ) {
			if ( ! in_array( $el_type, [ 'section', 'container' ], true ) ) {
				$this->warnings[] = [
					'code'    => 'hierarchy_root',
					'message' => sprintf( 'Root level elements should typically be sections or containers, found "%s".', $el_type ),
					'path'    => $path,
				];
			}
			return;
		}

		// Sections should contain columns
		if ( 'section' === $parent_type && 'column' !== $el_type ) {
			$this->warnings[] = [
				'code'    => 'hierarchy_section',
				'message' => sprintf( 'Sections typically contain columns, found "%s".', $el_type ),
				'path'    => $path,
			];
		}

		// Columns should contain widgets (or nested sections/containers for advanced layouts)
		if ( 'column' === $parent_type && ! in_array( $el_type, [ 'widget', 'section', 'container' ], true ) ) {
			$this->warnings[] = [
				'code'    => 'hierarchy_column',
				'message' => sprintf( 'Columns typically contain widgets, found "%s".', $el_type ),
				'path'    => $path,
			];
		}

		// Containers can contain widgets or nested containers
		if ( 'container' === $parent_type && ! in_array( $el_type, [ 'widget', 'container' ], true ) ) {
			$this->warnings[] = [
				'code'    => 'hierarchy_container',
				'message' => sprintf( 'Containers typically contain widgets or nested containers, found "%s".', $el_type ),
				'path'    => $path,
			];
		}
	}

	/**
	 * Validate widget properties.
	 *
	 * @param array  $element The widget element.
	 * @param string $path    The current path.
	 */
	protected function validate_widget( $element, $path ) {
		if ( ! isset( $element['widgetType'] ) ) {
			$this->errors[] = [
				'code'    => 'missing_widget_type',
				'message' => 'Widget element is missing required "widgetType" property.',
				'path'    => $path,
			];
			return;
		}

		$widget_type = $element['widgetType'];
		$all_widgets = array_merge( self::FREE_WIDGET_TYPES, self::PRO_WIDGET_TYPES );

		if ( ! in_array( $widget_type, $all_widgets, true ) ) {
			$this->warnings[] = [
				'code'    => 'unknown_widget_type',
				'message' => sprintf( 'Unknown widget type "%s". This may be a third-party widget or typo.', $widget_type ),
				'path'    => $path,
			];
			return;
		}

		// Check if Pro widget is used without Pro
		if ( in_array( $widget_type, self::PRO_WIDGET_TYPES, true ) && ! $this->has_pro ) {
			$this->warnings[] = [
				'code'    => 'pro_widget_no_pro',
				'message' => sprintf( 'Widget "%s" requires Elementor Pro which is not detected.', $widget_type ),
				'path'    => $path,
			];
		}

		// Validate widget-specific required settings
		$this->validate_widget_settings( $widget_type, $element, $path );
	}

	/**
	 * Validate widget-specific settings.
	 *
	 * @param string $widget_type The widget type.
	 * @param array  $element     The widget element.
	 * @param string $path        The current path.
	 */
	protected function validate_widget_settings( $widget_type, $element, $path ) {
		$settings = isset( $element['settings'] ) ? $element['settings'] : [];

		switch ( $widget_type ) {
			case 'heading':
				if ( ! isset( $settings['title'] ) || '' === trim( $settings['title'] ) ) {
					$this->warnings[] = [
						'code'    => 'heading_no_title',
						'message' => 'Heading widget has no title content.',
						'path'    => $path . '.settings',
					];
				}
				break;

			case 'text-editor':
				if ( ! isset( $settings['editor'] ) || '' === trim( $settings['editor'] ) ) {
					$this->warnings[] = [
						'code'    => 'text_editor_no_content',
						'message' => 'Text Editor widget has no content.',
						'path'    => $path . '.settings',
					];
				}
				break;

			case 'image':
				if ( ! isset( $settings['image'] ) || ! isset( $settings['image']['url'] ) ) {
					$this->warnings[] = [
						'code'    => 'image_no_url',
						'message' => 'Image widget has no image URL.',
						'path'    => $path . '.settings',
					];
				}
				break;

			case 'button':
				if ( ! isset( $settings['text'] ) || '' === trim( $settings['text'] ) ) {
					$this->warnings[] = [
						'code'    => 'button_no_text',
						'message' => 'Button widget has no text.',
						'path'    => $path . '.settings',
					];
				}
				break;

			case 'icon':
			case 'icon-box':
				if ( ! isset( $settings['selected_icon'] ) && ! isset( $settings['icon'] ) ) {
					$this->warnings[] = [
						'code'    => 'icon_not_set',
						'message' => 'Icon widget has no icon selected.',
						'path'    => $path . '.settings',
					];
				}
				break;
		}
	}

	/**
	 * Reset validator state.
	 */
	protected function reset() {
		$this->errors   = [];
		$this->warnings = [];
		$this->used_ids = [];
	}

	/**
	 * Build validation result.
	 *
	 * @param array|null $data The validated data.
	 * @return array Validation result.
	 */
	protected function build_result( $data ) {
		return [
			'valid'    => empty( $this->errors ),
			'errors'   => $this->errors,
			'warnings' => $this->warnings,
			'data'     => $data,
		];
	}

	/**
	 * Get all supported widget types.
	 *
	 * @param bool $include_pro Whether to include Pro widgets.
	 * @return array Widget types.
	 */
	public function get_supported_widgets( $include_pro = null ) {
		if ( null === $include_pro ) {
			$include_pro = $this->has_pro;
		}

		$widgets = self::FREE_WIDGET_TYPES;

		if ( $include_pro ) {
			$widgets = array_merge( $widgets, self::PRO_WIDGET_TYPES );
		}

		return $widgets;
	}

	/**
	 * Check if a widget type is valid.
	 *
	 * @param string $widget_type The widget type to check.
	 * @return bool Whether the widget type is valid.
	 */
	public function is_valid_widget( $widget_type ) {
		return in_array( $widget_type, $this->get_supported_widgets( true ), true );
	}

	/**
	 * Check if a widget requires Pro.
	 *
	 * @param string $widget_type The widget type to check.
	 * @return bool Whether the widget requires Pro.
	 */
	public function widget_requires_pro( $widget_type ) {
		return in_array( $widget_type, self::PRO_WIDGET_TYPES, true );
	}
}
