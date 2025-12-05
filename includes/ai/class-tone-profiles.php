<?php
/**
 * Voice/Tone Profiles
 *
 * Manages voice and tone profiles for consistent AI content generation.
 *
 * @package AiMentor
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Tone_Profiles {

	/**
	 * Option key for custom profiles.
	 */
	const OPTION_KEY = 'aimentor_tone_profiles';

	/**
	 * Predefined tone profiles.
	 */
	const PREDEFINED_PROFILES = [
		'professional' => [
			'name'        => 'Professional',
			'description' => 'Formal, authoritative, and business-appropriate.',
			'attributes'  => [
				'formality'   => 'formal',
				'emotion'     => 'neutral',
				'personality' => 'authoritative',
				'pace'        => 'measured',
			],
			'instructions' => 'Write in a professional, business-appropriate tone. Use formal language, avoid slang, and maintain an authoritative but approachable voice. Focus on clarity and credibility.',
			'examples'     => [
				'good' => 'Our comprehensive solutions deliver measurable results for enterprise clients.',
				'bad'  => 'We\'ve got awesome stuff that\'ll totally blow your mind!',
			],
		],
		'friendly' => [
			'name'        => 'Friendly & Approachable',
			'description' => 'Warm, conversational, and welcoming.',
			'attributes'  => [
				'formality'   => 'casual',
				'emotion'     => 'warm',
				'personality' => 'approachable',
				'pace'        => 'conversational',
			],
			'instructions' => 'Write in a warm, friendly tone as if talking to a good friend. Use conversational language, contractions, and create a welcoming atmosphere. Be helpful and encouraging.',
			'examples'     => [
				'good' => 'Hey there! We\'re so glad you stopped by. Let us help you find exactly what you need.',
				'bad'  => 'Welcome to our establishment. Please peruse our offerings at your convenience.',
			],
		],
		'casual' => [
			'name'        => 'Casual & Relaxed',
			'description' => 'Laid-back, informal, and easy-going.',
			'attributes'  => [
				'formality'   => 'informal',
				'emotion'     => 'relaxed',
				'personality' => 'easygoing',
				'pace'        => 'quick',
			],
			'instructions' => 'Write in a super casual, relaxed style. Use everyday language, short sentences, and feel free to be playful. Keep it simple and real.',
			'examples'     => [
				'good' => 'Cool stuff ahead. Grab what you need and let\'s get going!',
				'bad'  => 'Herein you shall discover an assortment of curated selections.',
			],
		],
		'formal' => [
			'name'        => 'Formal & Traditional',
			'description' => 'Highly formal, traditional, and sophisticated.',
			'attributes'  => [
				'formality'   => 'very_formal',
				'emotion'     => 'reserved',
				'personality' => 'dignified',
				'pace'        => 'deliberate',
			],
			'instructions' => 'Write in a highly formal, traditional manner. Use sophisticated vocabulary, complete sentences, and maintain a dignified tone. Avoid contractions and colloquialisms.',
			'examples'     => [
				'good' => 'We cordially invite you to explore our distinguished collection of services.',
				'bad'  => 'Check out our cool services, they\'re pretty great!',
			],
		],
		'playful' => [
			'name'        => 'Playful & Fun',
			'description' => 'Energetic, humorous, and entertaining.',
			'attributes'  => [
				'formality'   => 'informal',
				'emotion'     => 'excited',
				'personality' => 'witty',
				'pace'        => 'upbeat',
			],
			'instructions' => 'Write with energy and humor! Be playful, use puns when appropriate, and make the reader smile. Keep things light and entertaining while still being informative.',
			'examples'     => [
				'good' => 'Ready to level up? Buckle up buttercup, because things are about to get interesting! 🚀',
				'bad'  => 'Please review our available options and make your selection.',
			],
		],
		'inspirational' => [
			'name'        => 'Inspirational & Motivating',
			'description' => 'Uplifting, empowering, and encouraging.',
			'attributes'  => [
				'formality'   => 'semi-formal',
				'emotion'     => 'passionate',
				'personality' => 'empowering',
				'pace'        => 'dynamic',
			],
			'instructions' => 'Write to inspire and motivate. Use powerful, action-oriented language that empowers the reader. Paint a vision of success and possibility. Be encouraging and uplifting.',
			'examples'     => [
				'good' => 'Your journey to greatness starts here. Every step forward is a step toward your dreams.',
				'bad'  => 'You can use our product to maybe improve some things.',
			],
		],
		'technical' => [
			'name'        => 'Technical & Precise',
			'description' => 'Accurate, detailed, and data-driven.',
			'attributes'  => [
				'formality'   => 'formal',
				'emotion'     => 'neutral',
				'personality' => 'expert',
				'pace'        => 'methodical',
			],
			'instructions' => 'Write with technical precision. Use accurate terminology, provide specific details, and maintain a factual, data-driven approach. Be thorough but clear.',
			'examples'     => [
				'good' => 'The system processes 10,000 requests per second with 99.9% uptime and sub-100ms latency.',
				'bad'  => 'Our system is super fast and hardly ever goes down!',
			],
		],
		'luxury' => [
			'name'        => 'Luxury & Premium',
			'description' => 'Elegant, exclusive, and sophisticated.',
			'attributes'  => [
				'formality'   => 'formal',
				'emotion'     => 'refined',
				'personality' => 'exclusive',
				'pace'        => 'graceful',
			],
			'instructions' => 'Write with elegance and sophistication. Use refined language that conveys exclusivity and premium quality. Create an atmosphere of luxury and distinguish excellence.',
			'examples'     => [
				'good' => 'Experience unparalleled excellence with our bespoke collection, crafted for the discerning connoisseur.',
				'bad'  => 'Buy our expensive stuff, it\'s really good quality!',
			],
		],
		'bold' => [
			'name'        => 'Bold & Confident',
			'description' => 'Direct, assertive, and impactful.',
			'attributes'  => [
				'formality'   => 'semi-formal',
				'emotion'     => 'confident',
				'personality' => 'assertive',
				'pace'        => 'punchy',
			],
			'instructions' => 'Write with confidence and directness. Make bold statements, use strong verbs, and don\'t hedge. Be assertive and impactful. Short, punchy sentences work well.',
			'examples'     => [
				'good' => 'The best in the industry. Period. No compromises. No excuses.',
				'bad'  => 'We think we might possibly be one of the better options available.',
			],
		],
		'empathetic' => [
			'name'        => 'Empathetic & Caring',
			'description' => 'Understanding, supportive, and compassionate.',
			'attributes'  => [
				'formality'   => 'casual',
				'emotion'     => 'caring',
				'personality' => 'supportive',
				'pace'        => 'gentle',
			],
			'instructions' => 'Write with empathy and understanding. Acknowledge the reader\'s feelings and challenges. Use supportive, caring language that shows you genuinely want to help.',
			'examples'     => [
				'good' => 'We understand how overwhelming this can feel. You\'re not alone, and we\'re here to help every step of the way.',
				'bad'  => 'Just follow the instructions and you\'ll be fine.',
			],
		],
		'storytelling' => [
			'name'        => 'Storytelling & Narrative',
			'description' => 'Engaging, descriptive, and immersive.',
			'attributes'  => [
				'formality'   => 'casual',
				'emotion'     => 'engaging',
				'personality' => 'narrator',
				'pace'        => 'flowing',
			],
			'instructions' => 'Write using storytelling techniques. Create narratives, use vivid descriptions, and engage the reader emotionally. Paint pictures with words and take them on a journey.',
			'examples'     => [
				'good' => 'It all started with a simple question: What if there was a better way? That question led us on an incredible journey...',
				'bad'  => 'Our company was founded in 2010 and has grown steadily since then.',
			],
		],
		'minimalist' => [
			'name'        => 'Minimalist & Clean',
			'description' => 'Concise, clear, and clutter-free.',
			'attributes'  => [
				'formality'   => 'neutral',
				'emotion'     => 'calm',
				'personality' => 'focused',
				'pace'        => 'sparse',
			],
			'instructions' => 'Write with minimal words for maximum impact. Every word should earn its place. Remove fluff, be direct, and embrace white space in your communication.',
			'examples'     => [
				'good' => 'Simple. Powerful. Yours.',
				'bad'  => 'We are very excited to present to you our incredibly amazing and wonderful new product that we have been working on.',
			],
		],
	];

	/**
	 * Get all profiles (predefined + custom).
	 *
	 * @return array All tone profiles.
	 */
	public function get_all_profiles() {
		$custom_profiles = get_option( self::OPTION_KEY, [] );

		// Merge custom profiles with predefined, custom can override
		$profiles = self::PREDEFINED_PROFILES;

		foreach ( $custom_profiles as $key => $profile ) {
			$profiles[ $key ] = $profile;
		}

		return $profiles;
	}

	/**
	 * Get predefined profiles only.
	 *
	 * @return array Predefined profiles.
	 */
	public function get_predefined_profiles() {
		return self::PREDEFINED_PROFILES;
	}

	/**
	 * Get custom profiles only.
	 *
	 * @return array Custom profiles.
	 */
	public function get_custom_profiles() {
		return get_option( self::OPTION_KEY, [] );
	}

	/**
	 * Get a specific profile.
	 *
	 * @param string $key Profile key.
	 * @return array|null Profile data or null.
	 */
	public function get_profile( $key ) {
		$profiles = $this->get_all_profiles();
		return $profiles[ $key ] ?? null;
	}

	/**
	 * Save a custom profile.
	 *
	 * @param string $key     Profile key (slug).
	 * @param array  $profile Profile data.
	 * @return bool Success.
	 */
	public function save_profile( $key, $profile ) {
		$key = sanitize_key( $key );

		$defaults = [
			'name'         => '',
			'description'  => '',
			'attributes'   => [],
			'instructions' => '',
			'examples'     => [],
			'custom'       => true,
		];

		$profile = wp_parse_args( $profile, $defaults );

		// Sanitize
		$profile['name']         = sanitize_text_field( $profile['name'] );
		$profile['description']  = sanitize_textarea_field( $profile['description'] );
		$profile['instructions'] = sanitize_textarea_field( $profile['instructions'] );

		if ( ! empty( $profile['attributes'] ) ) {
			$profile['attributes'] = array_map( 'sanitize_text_field', $profile['attributes'] );
		}

		if ( ! empty( $profile['examples'] ) ) {
			$profile['examples'] = array_map( 'sanitize_textarea_field', $profile['examples'] );
		}

		$custom_profiles         = $this->get_custom_profiles();
		$custom_profiles[ $key ] = $profile;

		return update_option( self::OPTION_KEY, $custom_profiles );
	}

	/**
	 * Delete a custom profile.
	 *
	 * @param string $key Profile key.
	 * @return bool Success.
	 */
	public function delete_profile( $key ) {
		$custom_profiles = $this->get_custom_profiles();

		if ( ! isset( $custom_profiles[ $key ] ) ) {
			return false;
		}

		unset( $custom_profiles[ $key ] );

		return update_option( self::OPTION_KEY, $custom_profiles );
	}

	/**
	 * Get tone instruction for AI prompt.
	 *
	 * @param string $key     Profile key.
	 * @param array  $options Additional options.
	 * @return string Tone instruction.
	 */
	public function get_tone_instruction( $key, $options = [] ) {
		$profile = $this->get_profile( $key );

		if ( ! $profile ) {
			// Default fallback
			return '';
		}

		$instruction = "\n## VOICE & TONE\n";
		$instruction .= "Write using the \"{$profile['name']}\" tone:\n";
		$instruction .= $profile['instructions'] . "\n\n";

		// Add attributes context
		if ( ! empty( $profile['attributes'] ) ) {
			$instruction .= "Attributes:\n";
			foreach ( $profile['attributes'] as $attr => $value ) {
				$instruction .= "- " . ucfirst( str_replace( '_', ' ', $attr ) ) . ": {$value}\n";
			}
			$instruction .= "\n";
		}

		// Add example if available
		if ( ! empty( $profile['examples']['good'] ) ) {
			$instruction .= "Example of good copy in this tone:\n";
			$instruction .= "\"{$profile['examples']['good']}\"\n\n";
		}

		return $instruction;
	}

	/**
	 * Apply tone to content generation.
	 *
	 * @param string $prompt   Original prompt.
	 * @param string $tone_key Tone profile key.
	 * @param array  $options  Additional options.
	 * @return string Enhanced prompt with tone.
	 */
	public function apply_tone_to_prompt( $prompt, $tone_key, $options = [] ) {
		$tone_instruction = $this->get_tone_instruction( $tone_key, $options );

		if ( empty( $tone_instruction ) ) {
			return $prompt;
		}

		return $prompt . $tone_instruction;
	}

	/**
	 * Rewrite content in a specific tone.
	 *
	 * @param string $content  Original content.
	 * @param string $tone_key Target tone profile key.
	 * @param array  $options  Rewrite options.
	 * @return string|WP_Error Rewritten content or error.
	 */
	public function rewrite_in_tone( $content, $tone_key, $options = [] ) {
		$profile = $this->get_profile( $tone_key );

		if ( ! $profile ) {
			return new WP_Error( 'invalid_tone', __( 'Invalid tone profile.', 'aimentor' ) );
		}

		$defaults = [
			'preserve_meaning'  => true,
			'preserve_length'   => true,
			'preserve_keywords' => [],
		];

		$options = wp_parse_args( $options, $defaults );

		$prompt = "Rewrite the following content in the \"{$profile['name']}\" tone.\n\n";
		$prompt .= "## Tone Guidelines:\n{$profile['instructions']}\n\n";

		if ( $options['preserve_meaning'] ) {
			$prompt .= "- Preserve the original meaning and key information\n";
		}

		if ( $options['preserve_length'] ) {
			$prompt .= "- Keep approximately the same length\n";
		}

		if ( ! empty( $options['preserve_keywords'] ) ) {
			$prompt .= "- Keep these keywords: " . implode( ', ', $options['preserve_keywords'] ) . "\n";
		}

		$prompt .= "\n## Original Content:\n{$content}\n\n";
		$prompt .= "## Rewritten Content:\nProvide ONLY the rewritten text, nothing else.";

		// Use existing provider system
		if ( function_exists( 'aimentor_generate_with_fallback' ) ) {
			$result = aimentor_generate_with_fallback( $prompt, [ 'task' => 'copy' ] );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Clean up response
			$result = preg_replace( '/^(Rewritten Content|Output):\s*/i', '', trim( $result ) );

			return trim( $result );
		}

		return new WP_Error( 'no_provider', __( 'No AI provider configured.', 'aimentor' ) );
	}

	/**
	 * Analyze content tone.
	 *
	 * @param string $content Content to analyze.
	 * @return array|WP_Error Analysis results or error.
	 */
	public function analyze_tone( $content ) {
		$profile_names = [];
		foreach ( self::PREDEFINED_PROFILES as $key => $profile ) {
			$profile_names[] = $profile['name'];
		}

		$prompt = "Analyze the tone of the following content and match it to one of these tone profiles:\n";
		$prompt .= implode( ', ', $profile_names ) . "\n\n";
		$prompt .= "Content:\n{$content}\n\n";
		$prompt .= "Respond in JSON format:\n";
		$prompt .= "{\n";
		$prompt .= '  "detected_tone": "Profile Name",';
		$prompt .= "\n";
		$prompt .= '  "confidence": 0.85,';
		$prompt .= "\n";
		$prompt .= '  "attributes": {"formality": "...", "emotion": "..."},';
		$prompt .= "\n";
		$prompt .= '  "suggestions": ["suggestion 1", "suggestion 2"]';
		$prompt .= "\n}";

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
			return new WP_Error( 'parse_error', __( 'Could not parse tone analysis.', 'aimentor' ) );
		}

		$analysis = json_decode( $matches[0], true );

		if ( ! is_array( $analysis ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid analysis response.', 'aimentor' ) );
		}

		// Find matching profile key
		foreach ( self::PREDEFINED_PROFILES as $key => $profile ) {
			if ( strcasecmp( $profile['name'], $analysis['detected_tone'] ?? '' ) === 0 ) {
				$analysis['profile_key'] = $key;
				break;
			}
		}

		return $analysis;
	}

	/**
	 * Get profile options for select dropdowns.
	 *
	 * @param bool $include_custom Include custom profiles.
	 * @return array Options array.
	 */
	public function get_profile_options( $include_custom = true ) {
		$options = [];

		// Add predefined profiles
		foreach ( self::PREDEFINED_PROFILES as $key => $profile ) {
			$options[ $key ] = $profile['name'];
		}

		// Add custom profiles
		if ( $include_custom ) {
			$custom = $this->get_custom_profiles();
			foreach ( $custom as $key => $profile ) {
				$options[ $key ] = $profile['name'] . ' (Custom)';
			}
		}

		return $options;
	}

	/**
	 * Create a profile from content analysis.
	 *
	 * @param string $content Sample content to base profile on.
	 * @param string $name    Profile name.
	 * @return array|WP_Error Created profile or error.
	 */
	public function create_profile_from_content( $content, $name ) {
		$prompt = "Analyze this sample content and create a writing style profile:\n\n";
		$prompt .= "Content:\n{$content}\n\n";
		$prompt .= "Generate a JSON profile with:\n";
		$prompt .= "{\n";
		$prompt .= '  "description": "Brief description of this writing style",';
		$prompt .= "\n";
		$prompt .= '  "attributes": {"formality": "formal|semi-formal|casual|informal", "emotion": "...", "personality": "...", "pace": "..."},';
		$prompt .= "\n";
		$prompt .= '  "instructions": "Detailed instructions for writing in this style",';
		$prompt .= "\n";
		$prompt .= '  "examples": {"good": "Example text in this style", "bad": "Example of what NOT to write"}';
		$prompt .= "\n}";

		if ( ! function_exists( 'aimentor_generate_with_fallback' ) ) {
			return new WP_Error( 'no_provider', __( 'No AI provider configured.', 'aimentor' ) );
		}

		$result = aimentor_generate_with_fallback( $prompt, [ 'task' => 'copy' ] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$json_match = preg_match( '/\{[\s\S]*\}/', $result, $matches );

		if ( ! $json_match ) {
			return new WP_Error( 'parse_error', __( 'Could not parse profile generation response.', 'aimentor' ) );
		}

		$profile_data = json_decode( $matches[0], true );

		if ( ! is_array( $profile_data ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid profile response.', 'aimentor' ) );
		}

		$profile = [
			'name'         => sanitize_text_field( $name ),
			'description'  => $profile_data['description'] ?? '',
			'attributes'   => $profile_data['attributes'] ?? [],
			'instructions' => $profile_data['instructions'] ?? '',
			'examples'     => $profile_data['examples'] ?? [],
			'custom'       => true,
		];

		// Save the profile
		$key = sanitize_key( $name );
		$this->save_profile( $key, $profile );

		return $profile;
	}
}
