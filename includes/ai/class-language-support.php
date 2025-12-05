<?php
/**
 * Multi-Language Support
 *
 * Handles multi-language content generation and translation.
 *
 * @package AiMentor
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Language_Support {

	/**
	 * Supported languages with their details.
	 */
	const LANGUAGES = [
		'en_US' => [
			'name'      => 'English (US)',
			'native'    => 'English',
			'code'      => 'en',
			'rtl'       => false,
		],
		'en_GB' => [
			'name'      => 'English (UK)',
			'native'    => 'English',
			'code'      => 'en-GB',
			'rtl'       => false,
		],
		'es_ES' => [
			'name'      => 'Spanish',
			'native'    => 'Español',
			'code'      => 'es',
			'rtl'       => false,
		],
		'fr_FR' => [
			'name'      => 'French',
			'native'    => 'Français',
			'code'      => 'fr',
			'rtl'       => false,
		],
		'de_DE' => [
			'name'      => 'German',
			'native'    => 'Deutsch',
			'code'      => 'de',
			'rtl'       => false,
		],
		'it_IT' => [
			'name'      => 'Italian',
			'native'    => 'Italiano',
			'code'      => 'it',
			'rtl'       => false,
		],
		'pt_BR' => [
			'name'      => 'Portuguese (Brazil)',
			'native'    => 'Português',
			'code'      => 'pt-BR',
			'rtl'       => false,
		],
		'pt_PT' => [
			'name'      => 'Portuguese (Portugal)',
			'native'    => 'Português',
			'code'      => 'pt',
			'rtl'       => false,
		],
		'nl_NL' => [
			'name'      => 'Dutch',
			'native'    => 'Nederlands',
			'code'      => 'nl',
			'rtl'       => false,
		],
		'pl_PL' => [
			'name'      => 'Polish',
			'native'    => 'Polski',
			'code'      => 'pl',
			'rtl'       => false,
		],
		'ru_RU' => [
			'name'      => 'Russian',
			'native'    => 'Русский',
			'code'      => 'ru',
			'rtl'       => false,
		],
		'ja' => [
			'name'      => 'Japanese',
			'native'    => '日本語',
			'code'      => 'ja',
			'rtl'       => false,
		],
		'zh_CN' => [
			'name'      => 'Chinese (Simplified)',
			'native'    => '简体中文',
			'code'      => 'zh-CN',
			'rtl'       => false,
		],
		'zh_TW' => [
			'name'      => 'Chinese (Traditional)',
			'native'    => '繁體中文',
			'code'      => 'zh-TW',
			'rtl'       => false,
		],
		'ko_KR' => [
			'name'      => 'Korean',
			'native'    => '한국어',
			'code'      => 'ko',
			'rtl'       => false,
		],
		'ar' => [
			'name'      => 'Arabic',
			'native'    => 'العربية',
			'code'      => 'ar',
			'rtl'       => true,
		],
		'he_IL' => [
			'name'      => 'Hebrew',
			'native'    => 'עברית',
			'code'      => 'he',
			'rtl'       => true,
		],
		'fa_IR' => [
			'name'      => 'Persian',
			'native'    => 'فارسی',
			'code'      => 'fa',
			'rtl'       => true,
		],
		'hi_IN' => [
			'name'      => 'Hindi',
			'native'    => 'हिन्दी',
			'code'      => 'hi',
			'rtl'       => false,
		],
		'th' => [
			'name'      => 'Thai',
			'native'    => 'ไทย',
			'code'      => 'th',
			'rtl'       => false,
		],
		'vi' => [
			'name'      => 'Vietnamese',
			'native'    => 'Tiếng Việt',
			'code'      => 'vi',
			'rtl'       => false,
		],
		'tr_TR' => [
			'name'      => 'Turkish',
			'native'    => 'Türkçe',
			'code'      => 'tr',
			'rtl'       => false,
		],
		'sv_SE' => [
			'name'      => 'Swedish',
			'native'    => 'Svenska',
			'code'      => 'sv',
			'rtl'       => false,
		],
		'da_DK' => [
			'name'      => 'Danish',
			'native'    => 'Dansk',
			'code'      => 'da',
			'rtl'       => false,
		],
		'fi' => [
			'name'      => 'Finnish',
			'native'    => 'Suomi',
			'code'      => 'fi',
			'rtl'       => false,
		],
		'nb_NO' => [
			'name'      => 'Norwegian',
			'native'    => 'Norsk',
			'code'      => 'nb',
			'rtl'       => false,
		],
		'uk' => [
			'name'      => 'Ukrainian',
			'native'    => 'Українська',
			'code'      => 'uk',
			'rtl'       => false,
		],
		'cs_CZ' => [
			'name'      => 'Czech',
			'native'    => 'Čeština',
			'code'      => 'cs',
			'rtl'       => false,
		],
		'el' => [
			'name'      => 'Greek',
			'native'    => 'Ελληνικά',
			'code'      => 'el',
			'rtl'       => false,
		],
		'ro_RO' => [
			'name'      => 'Romanian',
			'native'    => 'Română',
			'code'      => 'ro',
			'rtl'       => false,
		],
		'hu_HU' => [
			'name'      => 'Hungarian',
			'native'    => 'Magyar',
			'code'      => 'hu',
			'rtl'       => false,
		],
		'id_ID' => [
			'name'      => 'Indonesian',
			'native'    => 'Bahasa Indonesia',
			'code'      => 'id',
			'rtl'       => false,
		],
		'ms_MY' => [
			'name'      => 'Malay',
			'native'    => 'Bahasa Melayu',
			'code'      => 'ms',
			'rtl'       => false,
		],
	];

	/**
	 * Get the current site language.
	 *
	 * @return string Language code.
	 */
	public function get_site_language() {
		$locale = get_locale();

		// Check if it's a supported language
		if ( isset( self::LANGUAGES[ $locale ] ) ) {
			return $locale;
		}

		// Try base language code
		$base_locale = substr( $locale, 0, 2 );
		foreach ( self::LANGUAGES as $code => $details ) {
			if ( substr( $code, 0, 2 ) === $base_locale ) {
				return $code;
			}
		}

		return 'en_US';
	}

	/**
	 * Get all supported languages.
	 *
	 * @return array Language list.
	 */
	public function get_supported_languages() {
		return self::LANGUAGES;
	}

	/**
	 * Get language details.
	 *
	 * @param string $locale Language locale.
	 * @return array|null Language details or null.
	 */
	public function get_language( $locale ) {
		return self::LANGUAGES[ $locale ] ?? null;
	}

	/**
	 * Check if a language is RTL.
	 *
	 * @param string $locale Language locale.
	 * @return bool
	 */
	public function is_rtl( $locale ) {
		$language = $this->get_language( $locale );
		return $language ? $language['rtl'] : false;
	}

	/**
	 * Generate content in a specific language.
	 *
	 * @param string $prompt  The generation prompt.
	 * @param string $locale  Target language locale.
	 * @param array  $options Additional options.
	 * @return string|WP_Error Generated content or error.
	 */
	public function generate_in_language( $prompt, $locale, $options = [] ) {
		$language = $this->get_language( $locale );

		if ( ! $language ) {
			$language = self::LANGUAGES['en_US'];
		}

		$enhanced_prompt = $this->add_language_instruction( $prompt, $language, $options );

		// Use existing provider system
		if ( function_exists( 'aimentor_generate_with_fallback' ) ) {
			return aimentor_generate_with_fallback( $enhanced_prompt, $options );
		}

		return new WP_Error( 'no_provider', __( 'No AI provider configured.', 'aimentor' ) );
	}

	/**
	 * Translate content to a target language.
	 *
	 * @param string $content Source content.
	 * @param string $target  Target language locale.
	 * @param string $source  Source language locale (auto-detect if empty).
	 * @param array  $options Translation options.
	 * @return string|WP_Error Translated content or error.
	 */
	public function translate( $content, $target, $source = '', $options = [] ) {
		$target_language = $this->get_language( $target );

		if ( ! $target_language ) {
			return new WP_Error( 'invalid_language', __( 'Invalid target language.', 'aimentor' ) );
		}

		$defaults = [
			'preserve_formatting' => true,
			'preserve_html'       => true,
			'tone'                => 'neutral',
			'context'             => '',
		];

		$options = wp_parse_args( $options, $defaults );

		$prompt = $this->build_translation_prompt( $content, $target_language, $source, $options );

		// Use existing provider system
		if ( function_exists( 'aimentor_generate_with_fallback' ) ) {
			$result = aimentor_generate_with_fallback( $prompt, [ 'task' => 'copy' ] );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Clean up any extra formatting from AI
			return $this->clean_translation_output( $result, $options );
		}

		return new WP_Error( 'no_provider', __( 'No AI provider configured.', 'aimentor' ) );
	}

	/**
	 * Translate Elementor JSON content.
	 *
	 * @param array  $elements Elementor elements array.
	 * @param string $target   Target language locale.
	 * @param array  $options  Translation options.
	 * @return array|WP_Error Translated elements or error.
	 */
	public function translate_elementor_content( $elements, $target, $options = [] ) {
		$target_language = $this->get_language( $target );

		if ( ! $target_language ) {
			return new WP_Error( 'invalid_language', __( 'Invalid target language.', 'aimentor' ) );
		}

		// Extract translatable strings
		$strings = $this->extract_translatable_strings( $elements );

		if ( empty( $strings ) ) {
			return $elements; // Nothing to translate
		}

		// Batch translate strings
		$translations = $this->batch_translate( $strings, $target_language, $options );

		if ( is_wp_error( $translations ) ) {
			return $translations;
		}

		// Apply translations back to elements
		return $this->apply_translations( $elements, $translations );
	}

	/**
	 * Add language instruction to prompt.
	 *
	 * @param string $prompt   Original prompt.
	 * @param array  $language Language details.
	 * @param array  $options  Additional options.
	 * @return string Enhanced prompt.
	 */
	protected function add_language_instruction( $prompt, $language, $options = [] ) {
		$instruction = sprintf(
			"\n\n## LANGUAGE REQUIREMENT\nGenerate all content in %s (%s).\n",
			$language['name'],
			$language['native']
		);

		if ( $language['rtl'] ) {
			$instruction .= "Note: This is a right-to-left (RTL) language.\n";
		}

		if ( ! empty( $options['tone'] ) ) {
			$instruction .= sprintf( "Tone: %s\n", $options['tone'] );
		}

		if ( ! empty( $options['formality'] ) ) {
			$instruction .= sprintf( "Formality: %s\n", $options['formality'] );
		}

		return $prompt . $instruction;
	}

	/**
	 * Build translation prompt.
	 *
	 * @param string $content         Content to translate.
	 * @param array  $target_language Target language details.
	 * @param string $source_locale   Source language locale.
	 * @param array  $options         Translation options.
	 * @return string Translation prompt.
	 */
	protected function build_translation_prompt( $content, $target_language, $source_locale, $options ) {
		$prompt = "You are a professional translator. Translate the following content ";

		if ( ! empty( $source_locale ) ) {
			$source_language = $this->get_language( $source_locale );
			if ( $source_language ) {
				$prompt .= "from {$source_language['name']} ";
			}
		}

		$prompt .= "to {$target_language['name']} ({$target_language['native']}).\n\n";

		$prompt .= "## Translation Guidelines:\n";
		$prompt .= "- Maintain the original meaning and intent\n";
		$prompt .= "- Use natural, fluent {$target_language['name']}\n";
		$prompt .= "- Preserve any technical terms appropriately\n";

		if ( $options['preserve_html'] ) {
			$prompt .= "- Preserve all HTML tags exactly as they appear\n";
		}

		if ( $options['preserve_formatting'] ) {
			$prompt .= "- Preserve formatting, line breaks, and structure\n";
		}

		if ( ! empty( $options['tone'] ) ) {
			$prompt .= "- Tone: {$options['tone']}\n";
		}

		if ( ! empty( $options['context'] ) ) {
			$prompt .= "\n## Context:\n{$options['context']}\n";
		}

		$prompt .= "\n## Content to Translate:\n";
		$prompt .= $content;
		$prompt .= "\n\n## Output:\nProvide ONLY the translated text, nothing else.";

		return $prompt;
	}

	/**
	 * Extract translatable strings from Elementor elements.
	 *
	 * @param array  $elements Elementor elements.
	 * @param string $path     Current path for reference.
	 * @return array Translatable strings with paths.
	 */
	protected function extract_translatable_strings( $elements, $path = '' ) {
		$strings = [];

		foreach ( $elements as $index => $element ) {
			$current_path = $path ? "{$path}.{$index}" : (string) $index;

			// Check settings for translatable content
			if ( ! empty( $element['settings'] ) ) {
				$translatable_keys = [
					'title', 'title_text', 'description_text', 'editor',
					'testimonial_content', 'testimonial_name', 'testimonial_job',
					'text', 'alert_title', 'alert_description', 'tab_title',
					'tab_content', 'heading', 'sub_heading', 'price',
					'period', 'footer_additional_info', 'button_text',
					'ribbon_title', 'field_label', 'placeholder',
				];

				foreach ( $translatable_keys as $key ) {
					if ( ! empty( $element['settings'][ $key ] ) && is_string( $element['settings'][ $key ] ) ) {
						$strings[] = [
							'path'  => "{$current_path}.settings.{$key}",
							'value' => $element['settings'][ $key ],
						];
					}
				}

				// Handle arrays (like accordion tabs, icon lists, etc.)
				$array_keys = [ 'tabs', 'icon_list', 'social_icon_list', 'features_list', 'form_fields' ];
				foreach ( $array_keys as $array_key ) {
					if ( ! empty( $element['settings'][ $array_key ] ) && is_array( $element['settings'][ $array_key ] ) ) {
						foreach ( $element['settings'][ $array_key ] as $item_index => $item ) {
							foreach ( $translatable_keys as $key ) {
								if ( ! empty( $item[ $key ] ) && is_string( $item[ $key ] ) ) {
									$strings[] = [
										'path'  => "{$current_path}.settings.{$array_key}.{$item_index}.{$key}",
										'value' => $item[ $key ],
									];
								}
							}
						}
					}
				}
			}

			// Recurse into nested elements
			if ( ! empty( $element['elements'] ) ) {
				$nested_strings = $this->extract_translatable_strings( $element['elements'], "{$current_path}.elements" );
				$strings        = array_merge( $strings, $nested_strings );
			}
		}

		return $strings;
	}

	/**
	 * Batch translate strings.
	 *
	 * @param array $strings         Strings to translate.
	 * @param array $target_language Target language details.
	 * @param array $options         Translation options.
	 * @return array|WP_Error Translations or error.
	 */
	protected function batch_translate( $strings, $target_language, $options ) {
		// Build batch translation prompt
		$prompt = "Translate the following numbered strings to {$target_language['name']} ({$target_language['native']}).\n";
		$prompt .= "Return a JSON object mapping the original string numbers to their translations.\n\n";

		foreach ( $strings as $index => $string_data ) {
			$prompt .= sprintf( "%d. %s\n", $index + 1, $string_data['value'] );
		}

		$prompt .= "\n## Output Format (JSON only):\n";
		$prompt .= '{"1": "translation 1", "2": "translation 2", ...}';

		// Generate translations
		if ( ! function_exists( 'aimentor_generate_with_fallback' ) ) {
			return new WP_Error( 'no_provider', __( 'No AI provider configured.', 'aimentor' ) );
		}

		$result = aimentor_generate_with_fallback( $prompt, [ 'task' => 'copy' ] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Parse JSON response
		$json_match = preg_match( '/\{[\s\S]*\}/', $result, $matches );

		if ( ! $json_match ) {
			return new WP_Error( 'parse_error', __( 'Could not parse translation response.', 'aimentor' ) );
		}

		$translations_map = json_decode( $matches[0], true );

		if ( ! is_array( $translations_map ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid translation response.', 'aimentor' ) );
		}

		// Map translations back to paths
		$translations = [];
		foreach ( $strings as $index => $string_data ) {
			$key = (string) ( $index + 1 );
			if ( isset( $translations_map[ $key ] ) ) {
				$translations[ $string_data['path'] ] = $translations_map[ $key ];
			}
		}

		return $translations;
	}

	/**
	 * Apply translations to Elementor elements.
	 *
	 * @param array $elements     Original elements.
	 * @param array $translations Path to translation mapping.
	 * @return array Translated elements.
	 */
	protected function apply_translations( $elements, $translations ) {
		$elements_json  = wp_json_encode( $elements );
		$elements_array = json_decode( $elements_json, true );

		foreach ( $translations as $path => $translation ) {
			$this->set_nested_value( $elements_array, $path, $translation );
		}

		return $elements_array;
	}

	/**
	 * Set a nested value using dot notation path.
	 *
	 * @param array  $array Reference to array.
	 * @param string $path  Dot notation path.
	 * @param mixed  $value Value to set.
	 */
	protected function set_nested_value( &$array, $path, $value ) {
		$keys    = explode( '.', $path );
		$current = &$array;

		foreach ( $keys as $key ) {
			if ( ! isset( $current[ $key ] ) ) {
				return; // Path doesn't exist
			}
			$current = &$current[ $key ];
		}

		$current = $value;
	}

	/**
	 * Clean translation output.
	 *
	 * @param string $output  Raw AI output.
	 * @param array  $options Translation options.
	 * @return string Cleaned output.
	 */
	protected function clean_translation_output( $output, $options ) {
		// Remove any "Translation:" prefixes
		$output = preg_replace( '/^(Translation|Translated text|Output):\s*/i', '', trim( $output ) );

		// Remove markdown quotes if present
		$output = preg_replace( '/^["\']|["\']$/m', '', $output );

		return trim( $output );
	}

	/**
	 * Detect language of content.
	 *
	 * @param string $content Content to analyze.
	 * @return string|WP_Error Detected language locale or error.
	 */
	public function detect_language( $content ) {
		$prompt = "Detect the language of the following text. Return ONLY the language code (e.g., 'en', 'es', 'fr', 'de', 'ja', etc.):\n\n";
		$prompt .= substr( $content, 0, 500 );

		if ( ! function_exists( 'aimentor_generate_with_fallback' ) ) {
			return new WP_Error( 'no_provider', __( 'No AI provider configured.', 'aimentor' ) );
		}

		$result = aimentor_generate_with_fallback( $prompt, [ 'task' => 'copy' ] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$code = strtolower( trim( $result ) );

		// Map short code to full locale
		foreach ( self::LANGUAGES as $locale => $details ) {
			if ( $details['code'] === $code || substr( $locale, 0, 2 ) === $code ) {
				return $locale;
			}
		}

		return 'en_US'; // Default fallback
	}
}
