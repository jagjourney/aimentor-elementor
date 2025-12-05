<?php
/**
 * Elementor JSON Repair System
 *
 * Attempts to fix common issues in AI-generated Elementor JSON.
 *
 * @package AiMentor
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Elementor_JSON_Repair {

	/**
	 * Repairs performed during the last repair operation.
	 *
	 * @var array
	 */
	protected $repairs = [];

	/**
	 * ID counter for generating unique IDs.
	 *
	 * @var int
	 */
	protected $id_counter = 0;

	/**
	 * Attempt to repair Elementor JSON.
	 *
	 * @param string|array $data The JSON string or array to repair.
	 * @return array Result with 'success', 'data', 'repairs', and 'raw_input' keys.
	 */
	public function repair( $data ) {
		$this->repairs    = [];
		$this->id_counter = 0;
		$raw_input        = $data;

		// Step 1: Handle string input and extract JSON
		if ( is_string( $data ) ) {
			$data = $this->extract_json_from_string( $data );

			if ( null === $data ) {
				return [
					'success'   => false,
					'data'      => null,
					'repairs'   => $this->repairs,
					'raw_input' => $raw_input,
					'error'     => 'Could not extract valid JSON from input.',
				];
			}
		}

		if ( ! is_array( $data ) ) {
			return [
				'success'   => false,
				'data'      => null,
				'repairs'   => $this->repairs,
				'raw_input' => $raw_input,
				'error'     => 'Input is not a valid array or object.',
			];
		}

		// Step 2: Normalize structure
		$data = $this->normalize_structure( $data );

		// Step 3: Fix element hierarchy
		$data = $this->fix_hierarchy( $data );

		// Step 4: Fix element IDs
		$data = $this->fix_element_ids( $data );

		// Step 5: Fix widget types
		$data = $this->fix_widget_types( $data );

		// Step 6: Fix settings
		$data = $this->fix_settings( $data );

		// Step 7: Clean up empty elements
		$data = $this->clean_empty_elements( $data );

		return [
			'success'   => true,
			'data'      => $data,
			'repairs'   => $this->repairs,
			'raw_input' => $raw_input,
		];
	}

	/**
	 * Extract JSON from a string that may contain markdown or other text.
	 *
	 * @param string $input The input string.
	 * @return array|null The extracted JSON array or null.
	 */
	protected function extract_json_from_string( $input ) {
		// Try direct JSON decode first
		$decoded = json_decode( $input, true );

		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			return $decoded;
		}

		// Try to extract JSON from markdown code blocks
		$patterns = [
			'/```json\s*\n?([\s\S]*?)\n?```/i',
			'/```\s*\n?([\s\S]*?)\n?```/i',
			'/\{[\s\S]*\}/m',
			'/\[[\s\S]*\]/m',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $input, $matches ) ) {
				$json_str = isset( $matches[1] ) ? $matches[1] : $matches[0];
				$decoded  = json_decode( trim( $json_str ), true );

				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
					$this->repairs[] = [
						'type'    => 'extracted_json',
						'message' => 'Extracted JSON from surrounding text/markdown.',
					];
					return $decoded;
				}
			}
		}

		// Try to fix common JSON errors
		$fixed = $this->fix_json_string( $input );

		if ( null !== $fixed ) {
			return $fixed;
		}

		return null;
	}

	/**
	 * Attempt to fix common JSON string errors.
	 *
	 * @param string $json The JSON string to fix.
	 * @return array|null The fixed JSON array or null.
	 */
	protected function fix_json_string( $json ) {
		// Remove BOM
		$json = preg_replace( '/^\xEF\xBB\xBF/', '', $json );

		// Remove trailing commas before ] or }
		$json = preg_replace( '/,\s*([}\]])/', '$1', $json );

		// Fix unquoted keys
		$json = preg_replace( '/([{,]\s*)([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '$1"$2":', $json );

		// Fix single quotes to double quotes (careful with content)
		$json = preg_replace( "/(?<![\\\\])'([^']*)'(?=\s*[:,}\]])/", '"$1"', $json );

		// Try decode again
		$decoded = json_decode( $json, true );

		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			$this->repairs[] = [
				'type'    => 'fixed_json_syntax',
				'message' => 'Fixed JSON syntax errors (trailing commas, quotes, etc.).',
			];
			return $decoded;
		}

		return null;
	}

	/**
	 * Normalize the overall structure.
	 *
	 * @param array $data The data to normalize.
	 * @return array Normalized data.
	 */
	protected function normalize_structure( $data ) {
		// If it's wrapped in an "elements" key, extract it
		if ( isset( $data['elements'] ) && is_array( $data['elements'] ) ) {
			return $data;
		}

		// If it's a direct array of elements
		if ( isset( $data[0] ) && is_array( $data[0] ) ) {
			// Check if first element looks like an Elementor element
			if ( isset( $data[0]['elType'] ) || isset( $data[0]['id'] ) ) {
				$this->repairs[] = [
					'type'    => 'wrapped_elements',
					'message' => 'Wrapped bare element array in standard structure.',
				];
				return [ 'elements' => $data ];
			}
		}

		// If it's a single element object
		if ( isset( $data['elType'] ) ) {
			$this->repairs[] = [
				'type'    => 'wrapped_single_element',
				'message' => 'Wrapped single element in elements array.',
			];
			return [ 'elements' => [ $data ] ];
		}

		// Try to find elements in nested structure
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) && ( isset( $value['elType'] ) || isset( $value[0]['elType'] ) ) ) {
				$this->repairs[] = [
					'type'    => 'found_nested_elements',
					'message' => sprintf( 'Found elements under "%s" key and normalized structure.', $key ),
				];

				if ( isset( $value['elType'] ) ) {
					return [ 'elements' => [ $value ] ];
				}

				return [ 'elements' => $value ];
			}
		}

		// Return as-is if we can't normalize
		return $data;
	}

	/**
	 * Fix element hierarchy issues.
	 *
	 * @param array $data The data to fix.
	 * @return array Fixed data.
	 */
	protected function fix_hierarchy( $data ) {
		if ( ! isset( $data['elements'] ) || ! is_array( $data['elements'] ) ) {
			return $data;
		}

		$data['elements'] = $this->fix_elements_hierarchy( $data['elements'], null );

		return $data;
	}

	/**
	 * Recursively fix element hierarchy.
	 *
	 * @param array       $elements    The elements to fix.
	 * @param string|null $parent_type The parent element type.
	 * @return array Fixed elements.
	 */
	protected function fix_elements_hierarchy( $elements, $parent_type ) {
		$fixed = [];

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$el_type = isset( $element['elType'] ) ? $element['elType'] : null;

			// Fix missing elType
			if ( null === $el_type ) {
				$el_type = $this->infer_element_type( $element, $parent_type );
				$element['elType'] = $el_type;

				$this->repairs[] = [
					'type'    => 'inferred_eltype',
					'message' => sprintf( 'Inferred element type as "%s".', $el_type ),
				];
			}

			// Wrap widgets in column if they're at section level
			if ( 'section' === $parent_type && 'widget' === $el_type ) {
				$this->repairs[] = [
					'type'    => 'wrapped_widget_in_column',
					'message' => 'Wrapped widget in a column (widgets cannot be direct children of sections).',
				];

				$column = [
					'id'       => $this->generate_id(),
					'elType'   => 'column',
					'settings' => [ '_column_size' => 100 ],
					'elements' => [ $element ],
				];

				$fixed[] = $column;
				continue;
			}

			// Wrap widgets in section > column if they're at root level
			if ( null === $parent_type && 'widget' === $el_type ) {
				$this->repairs[] = [
					'type'    => 'wrapped_widget_in_section',
					'message' => 'Wrapped widget in section > column structure (widgets cannot be at root level).',
				];

				$section = [
					'id'       => $this->generate_id(),
					'elType'   => 'section',
					'settings' => [],
					'elements' => [
						[
							'id'       => $this->generate_id(),
							'elType'   => 'column',
							'settings' => [ '_column_size' => 100 ],
							'elements' => [ $element ],
						],
					],
				];

				$fixed[] = $section;
				continue;
			}

			// Wrap column in section if it's at root level
			if ( null === $parent_type && 'column' === $el_type ) {
				$this->repairs[] = [
					'type'    => 'wrapped_column_in_section',
					'message' => 'Wrapped column in section (columns cannot be at root level).',
				];

				$section = [
					'id'       => $this->generate_id(),
					'elType'   => 'section',
					'settings' => [],
					'elements' => [ $element ],
				];

				$fixed[] = $section;
				continue;
			}

			// Recursively fix children
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = $this->fix_elements_hierarchy( $element['elements'], $el_type );
			}

			$fixed[] = $element;
		}

		return $fixed;
	}

	/**
	 * Infer element type from structure and context.
	 *
	 * @param array       $element     The element.
	 * @param string|null $parent_type The parent type.
	 * @return string The inferred element type.
	 */
	protected function infer_element_type( $element, $parent_type ) {
		// Has widgetType -> it's a widget
		if ( isset( $element['widgetType'] ) ) {
			return 'widget';
		}

		// Has _column_size -> it's a column
		if ( isset( $element['settings']['_column_size'] ) ) {
			return 'column';
		}

		// Has structure or layout settings -> likely section
		if ( isset( $element['settings']['structure'] ) || isset( $element['settings']['layout'] ) ) {
			return 'section';
		}

		// Based on parent
		if ( null === $parent_type ) {
			return 'section';
		}

		if ( 'section' === $parent_type ) {
			return 'column';
		}

		if ( 'column' === $parent_type || 'container' === $parent_type ) {
			return 'widget';
		}

		return 'section';
	}

	/**
	 * Fix element IDs.
	 *
	 * @param array $data The data to fix.
	 * @return array Fixed data.
	 */
	protected function fix_element_ids( $data ) {
		if ( ! isset( $data['elements'] ) ) {
			return $data;
		}

		$used_ids = [];
		$data['elements'] = $this->fix_ids_recursive( $data['elements'], $used_ids );

		return $data;
	}

	/**
	 * Recursively fix element IDs.
	 *
	 * @param array $elements The elements.
	 * @param array $used_ids Reference to used IDs array.
	 * @return array Fixed elements.
	 */
	protected function fix_ids_recursive( $elements, &$used_ids ) {
		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			// Fix missing or invalid ID
			if ( ! isset( $element['id'] ) || ! is_string( $element['id'] ) || '' === $element['id'] ) {
				$element['id'] = $this->generate_id();

				$this->repairs[] = [
					'type'    => 'generated_id',
					'message' => sprintf( 'Generated missing element ID: %s.', $element['id'] ),
				];
			}

			// Fix duplicate ID
			if ( in_array( $element['id'], $used_ids, true ) ) {
				$old_id = $element['id'];
				$element['id'] = $this->generate_id();

				$this->repairs[] = [
					'type'    => 'fixed_duplicate_id',
					'message' => sprintf( 'Fixed duplicate ID "%s" -> "%s".', $old_id, $element['id'] ),
				];
			}

			$used_ids[] = $element['id'];

			// Recurse into children
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = $this->fix_ids_recursive( $element['elements'], $used_ids );
			}
		}

		return $elements;
	}

	/**
	 * Fix widget types.
	 *
	 * @param array $data The data to fix.
	 * @return array Fixed data.
	 */
	protected function fix_widget_types( $data ) {
		if ( ! isset( $data['elements'] ) ) {
			return $data;
		}

		$data['elements'] = $this->fix_widgets_recursive( $data['elements'] );

		return $data;
	}

	/**
	 * Recursively fix widget types.
	 *
	 * @param array $elements The elements.
	 * @return array Fixed elements.
	 */
	protected function fix_widgets_recursive( $elements ) {
		// Common widget type mappings (AI sometimes uses wrong names)
		$widget_mappings = [
			'text'           => 'text-editor',
			'paragraph'      => 'text-editor',
			'content'        => 'text-editor',
			'title'          => 'heading',
			'h1'             => 'heading',
			'h2'             => 'heading',
			'h3'             => 'heading',
			'header'         => 'heading',
			'img'            => 'image',
			'picture'        => 'image',
			'photo'          => 'image',
			'btn'            => 'button',
			'cta'            => 'button',
			'link'           => 'button',
			'separator'      => 'divider',
			'hr'             => 'divider',
			'gap'            => 'spacer',
			'space'          => 'spacer',
			'map'            => 'google_maps',
			'maps'           => 'google_maps',
			'google-maps'    => 'google_maps',
			'googlemap'      => 'google_maps',
			'list'           => 'icon-list',
			'bullet-list'    => 'icon-list',
			'checklist'      => 'icon-list',
			'features'       => 'icon-box',
			'feature'        => 'icon-box',
			'service'        => 'icon-box',
			'card'           => 'image-box',
			'review'         => 'testimonial',
			'quote'          => 'testimonial',
			'faq'            => 'accordion',
			'questions'      => 'accordion',
			'collapsible'    => 'accordion',
			'tab'            => 'tabs',
			'tabbed'         => 'tabs',
			'social'         => 'social-icons',
			'social-links'   => 'social-icons',
			'follow'         => 'social-icons',
			'rating'         => 'star-rating',
			'stars'          => 'star-rating',
			'number'         => 'counter',
			'stat'           => 'counter',
			'statistic'      => 'counter',
			'progress-bar'   => 'progress',
			'percentage'     => 'progress',
			'warning'        => 'alert',
			'notice'         => 'alert',
			'info'           => 'alert',
			'youtube'        => 'video',
			'vimeo'          => 'video',
			'embed'          => 'video',
		];

		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( 'widget' === ( $element['elType'] ?? '' ) ) {
				$widget_type = $element['widgetType'] ?? '';

				// Fix missing widget type
				if ( '' === $widget_type ) {
					$widget_type = $this->infer_widget_type( $element );
					$element['widgetType'] = $widget_type;

					$this->repairs[] = [
						'type'    => 'inferred_widget_type',
						'message' => sprintf( 'Inferred widget type as "%s".', $widget_type ),
					];
				}

				// Map incorrect widget type names
				$lower_type = strtolower( $widget_type );

				if ( isset( $widget_mappings[ $lower_type ] ) ) {
					$old_type = $widget_type;
					$element['widgetType'] = $widget_mappings[ $lower_type ];

					$this->repairs[] = [
						'type'    => 'mapped_widget_type',
						'message' => sprintf( 'Mapped widget type "%s" -> "%s".', $old_type, $element['widgetType'] ),
					];
				}
			}

			// Recurse
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = $this->fix_widgets_recursive( $element['elements'] );
			}
		}

		return $elements;
	}

	/**
	 * Infer widget type from settings.
	 *
	 * @param array $element The element.
	 * @return string The inferred widget type.
	 */
	protected function infer_widget_type( $element ) {
		$settings = $element['settings'] ?? [];

		if ( isset( $settings['title'] ) && ! isset( $settings['editor'] ) ) {
			return 'heading';
		}

		if ( isset( $settings['editor'] ) ) {
			return 'text-editor';
		}

		if ( isset( $settings['image'] ) || isset( $settings['image_url'] ) ) {
			return 'image';
		}

		if ( isset( $settings['text'] ) && isset( $settings['link'] ) ) {
			return 'button';
		}

		if ( isset( $settings['selected_icon'] ) || isset( $settings['icon'] ) ) {
			return 'icon';
		}

		// Default to text-editor
		return 'text-editor';
	}

	/**
	 * Fix common settings issues.
	 *
	 * @param array $data The data to fix.
	 * @return array Fixed data.
	 */
	protected function fix_settings( $data ) {
		if ( ! isset( $data['elements'] ) ) {
			return $data;
		}

		$data['elements'] = $this->fix_settings_recursive( $data['elements'] );

		return $data;
	}

	/**
	 * Recursively fix settings.
	 *
	 * @param array $elements The elements.
	 * @return array Fixed elements.
	 */
	protected function fix_settings_recursive( $elements ) {
		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			// Ensure settings exists
			if ( ! isset( $element['settings'] ) ) {
				$element['settings'] = [];
			}

			// Ensure settings is array
			if ( ! is_array( $element['settings'] ) ) {
				$element['settings'] = [];

				$this->repairs[] = [
					'type'    => 'fixed_settings_type',
					'message' => 'Converted non-array settings to empty array.',
				];
			}

			$el_type     = $element['elType'] ?? '';
			$widget_type = $element['widgetType'] ?? '';

			// Fix column settings
			if ( 'column' === $el_type ) {
				if ( ! isset( $element['settings']['_column_size'] ) ) {
					$element['settings']['_column_size'] = 100;
				}
			}

			// Fix widget-specific settings
			if ( 'widget' === $el_type ) {
				$element = $this->fix_widget_settings( $element, $widget_type );
			}

			// Recurse
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = $this->fix_settings_recursive( $element['elements'] );
			}
		}

		return $elements;
	}

	/**
	 * Fix widget-specific settings.
	 *
	 * @param array  $element     The element.
	 * @param string $widget_type The widget type.
	 * @return array Fixed element.
	 */
	protected function fix_widget_settings( $element, $widget_type ) {
		$settings = &$element['settings'];

		switch ( $widget_type ) {
			case 'heading':
				// Move 'text' to 'title' if needed
				if ( ! isset( $settings['title'] ) && isset( $settings['text'] ) ) {
					$settings['title'] = $settings['text'];
					unset( $settings['text'] );

					$this->repairs[] = [
						'type'    => 'fixed_heading_title',
						'message' => 'Moved "text" to "title" for heading widget.',
					];
				}

				// Ensure header_size exists
				if ( ! isset( $settings['header_size'] ) ) {
					$settings['header_size'] = 'h2';
				}
				break;

			case 'text-editor':
				// Move 'text' or 'content' to 'editor'
				if ( ! isset( $settings['editor'] ) ) {
					if ( isset( $settings['text'] ) ) {
						$settings['editor'] = $settings['text'];
						unset( $settings['text'] );
					} elseif ( isset( $settings['content'] ) ) {
						$settings['editor'] = $settings['content'];
						unset( $settings['content'] );
					}

					if ( isset( $settings['editor'] ) ) {
						$this->repairs[] = [
							'type'    => 'fixed_text_editor',
							'message' => 'Moved content to "editor" field for text-editor widget.',
						];
					}
				}
				break;

			case 'image':
				// Fix image structure
				if ( ! isset( $settings['image'] ) || ! is_array( $settings['image'] ) ) {
					$url = '';

					if ( isset( $settings['image'] ) && is_string( $settings['image'] ) ) {
						$url = $settings['image'];
					} elseif ( isset( $settings['image_url'] ) ) {
						$url = $settings['image_url'];
					} elseif ( isset( $settings['url'] ) ) {
						$url = $settings['url'];
					} elseif ( isset( $settings['src'] ) ) {
						$url = $settings['src'];
					}

					if ( '' === $url ) {
						$url = 'https://via.placeholder.com/800x600';
					}

					$settings['image'] = [
						'url' => $url,
						'id'  => '',
					];

					$this->repairs[] = [
						'type'    => 'fixed_image_structure',
						'message' => 'Fixed image settings structure.',
					];
				}
				break;

			case 'button':
				// Ensure text exists
				if ( ! isset( $settings['text'] ) ) {
					if ( isset( $settings['label'] ) ) {
						$settings['text'] = $settings['label'];
						unset( $settings['label'] );
					} elseif ( isset( $settings['title'] ) ) {
						$settings['text'] = $settings['title'];
						unset( $settings['title'] );
					} else {
						$settings['text'] = 'Click Here';
					}

					$this->repairs[] = [
						'type'    => 'fixed_button_text',
						'message' => 'Fixed button text field.',
					];
				}

				// Fix link structure
				if ( isset( $settings['link'] ) && is_string( $settings['link'] ) ) {
					$settings['link'] = [
						'url'         => $settings['link'],
						'is_external' => false,
						'nofollow'    => false,
					];

					$this->repairs[] = [
						'type'    => 'fixed_button_link',
						'message' => 'Fixed button link structure.',
					];
				}
				break;

			case 'icon':
			case 'icon-box':
				// Fix icon structure
				if ( isset( $settings['icon'] ) && is_string( $settings['icon'] ) ) {
					$icon_value = $settings['icon'];
					$settings['selected_icon'] = [
						'value'   => $icon_value,
						'library' => strpos( $icon_value, 'fa-' ) !== false ? 'fa-solid' : 'fa-solid',
					];
					unset( $settings['icon'] );

					$this->repairs[] = [
						'type'    => 'fixed_icon_structure',
						'message' => 'Fixed icon settings structure.',
					];
				}
				break;

			case 'social-icons':
				// Fix social icons structure
				if ( isset( $settings['social_icon_list'] ) && is_array( $settings['social_icon_list'] ) ) {
					foreach ( $settings['social_icon_list'] as &$item ) {
						if ( isset( $item['social'] ) && is_string( $item['social'] ) ) {
							$social_value = $item['social'];
							$item['social_icon'] = [
								'value'   => 'fab fa-' . strtolower( $social_value ),
								'library' => 'fa-brands',
							];
							unset( $item['social'] );
						}
					}
				}
				break;
		}

		return $element;
	}

	/**
	 * Clean up empty or invalid elements.
	 *
	 * @param array $data The data to clean.
	 * @return array Cleaned data.
	 */
	protected function clean_empty_elements( $data ) {
		if ( ! isset( $data['elements'] ) ) {
			return $data;
		}

		$data['elements'] = $this->clean_elements_recursive( $data['elements'] );

		return $data;
	}

	/**
	 * Recursively clean empty elements.
	 *
	 * @param array $elements The elements.
	 * @return array Cleaned elements.
	 */
	protected function clean_elements_recursive( $elements ) {
		$cleaned = [];

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			// Skip elements without required properties
			if ( ! isset( $element['elType'] ) || ! isset( $element['id'] ) ) {
				continue;
			}

			// Recurse first
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = $this->clean_elements_recursive( $element['elements'] );
			}

			// Ensure elements array exists for non-widgets
			if ( 'widget' !== $element['elType'] && ! isset( $element['elements'] ) ) {
				$element['elements'] = [];
			}

			$cleaned[] = $element;
		}

		return $cleaned;
	}

	/**
	 * Generate a unique Elementor-style ID.
	 *
	 * @return string The generated ID.
	 */
	protected function generate_id() {
		$this->id_counter++;
		return substr( md5( uniqid( 'aimentor_' . $this->id_counter, true ) ), 0, 7 );
	}

	/**
	 * Get the repairs performed in the last operation.
	 *
	 * @return array The repairs.
	 */
	public function get_repairs() {
		return $this->repairs;
	}
}
