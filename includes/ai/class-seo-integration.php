<?php
/**
 * SEO Integration
 *
 * Integrates with Yoast SEO and Rank Math for AI-powered SEO optimization.
 *
 * @package AiMentor
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_SEO_Integration {

	/**
	 * Detected SEO plugin.
	 *
	 * @var string|null
	 */
	protected $active_plugin = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->detect_seo_plugin();
	}

	/**
	 * Detect which SEO plugin is active.
	 */
	protected function detect_seo_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			$this->active_plugin = 'yoast';
		} elseif ( class_exists( 'RankMath' ) ) {
			$this->active_plugin = 'rankmath';
		}
	}

	/**
	 * Check if an SEO plugin is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return ! empty( $this->active_plugin );
	}

	/**
	 * Get the active SEO plugin name.
	 *
	 * @return string|null
	 */
	public function get_active_plugin() {
		return $this->active_plugin;
	}

	/**
	 * Generate SEO meta data using AI.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $content The page content.
	 * @param array  $options Generation options.
	 * @return array|WP_Error Generated SEO data or error.
	 */
	public function generate_seo_meta( $post_id, $content = '', $options = [] ) {
		$defaults = [
			'focus_keyword' => '',
			'generate'      => [ 'title', 'description', 'keywords' ],
			'tone'          => 'professional',
			'language'      => get_locale(),
		];

		$options = wp_parse_args( $options, $defaults );

		// Get post content if not provided
		if ( empty( $content ) ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$content = $post->post_content;
				if ( empty( $options['focus_keyword'] ) ) {
					$options['focus_keyword'] = $post->post_title;
				}
			}
		}

		// Strip Elementor data and shortcodes
		$clean_content = $this->clean_content( $content );

		// Build AI prompt
		$prompt = $this->build_seo_prompt( $clean_content, $options );

		// Generate using configured provider
		$result = $this->generate_with_ai( $prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Parse AI response
		$seo_data = $this->parse_seo_response( $result );

		if ( is_wp_error( $seo_data ) ) {
			return $seo_data;
		}

		return $seo_data;
	}

	/**
	 * Apply generated SEO data to a post.
	 *
	 * @param int   $post_id  The post ID.
	 * @param array $seo_data The SEO data to apply.
	 * @return bool|WP_Error Success or error.
	 */
	public function apply_seo_meta( $post_id, $seo_data ) {
		if ( ! $this->is_available() ) {
			return new WP_Error( 'no_seo_plugin', __( 'No SEO plugin detected.', 'aimentor' ) );
		}

		switch ( $this->active_plugin ) {
			case 'yoast':
				return $this->apply_yoast_meta( $post_id, $seo_data );

			case 'rankmath':
				return $this->apply_rankmath_meta( $post_id, $seo_data );

			default:
				return new WP_Error( 'unsupported_plugin', __( 'SEO plugin not supported.', 'aimentor' ) );
		}
	}

	/**
	 * Apply SEO meta for Yoast SEO.
	 *
	 * @param int   $post_id  The post ID.
	 * @param array $seo_data The SEO data.
	 * @return bool
	 */
	protected function apply_yoast_meta( $post_id, $seo_data ) {
		if ( ! empty( $seo_data['title'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_title', $seo_data['title'] );
		}

		if ( ! empty( $seo_data['description'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $seo_data['description'] );
		}

		if ( ! empty( $seo_data['focus_keyword'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $seo_data['focus_keyword'] );
		}

		// Open Graph
		if ( ! empty( $seo_data['og_title'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', $seo_data['og_title'] );
		}

		if ( ! empty( $seo_data['og_description'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_opengraph-description', $seo_data['og_description'] );
		}

		return true;
	}

	/**
	 * Apply SEO meta for Rank Math.
	 *
	 * @param int   $post_id  The post ID.
	 * @param array $seo_data The SEO data.
	 * @return bool
	 */
	protected function apply_rankmath_meta( $post_id, $seo_data ) {
		if ( ! empty( $seo_data['title'] ) ) {
			update_post_meta( $post_id, 'rank_math_title', $seo_data['title'] );
		}

		if ( ! empty( $seo_data['description'] ) ) {
			update_post_meta( $post_id, 'rank_math_description', $seo_data['description'] );
		}

		if ( ! empty( $seo_data['focus_keyword'] ) ) {
			update_post_meta( $post_id, 'rank_math_focus_keyword', $seo_data['focus_keyword'] );
		}

		// Open Graph
		if ( ! empty( $seo_data['og_title'] ) ) {
			update_post_meta( $post_id, 'rank_math_facebook_title', $seo_data['og_title'] );
		}

		if ( ! empty( $seo_data['og_description'] ) ) {
			update_post_meta( $post_id, 'rank_math_facebook_description', $seo_data['og_description'] );
		}

		// Twitter
		if ( ! empty( $seo_data['twitter_title'] ) ) {
			update_post_meta( $post_id, 'rank_math_twitter_title', $seo_data['twitter_title'] );
		}

		if ( ! empty( $seo_data['twitter_description'] ) ) {
			update_post_meta( $post_id, 'rank_math_twitter_description', $seo_data['twitter_description'] );
		}

		return true;
	}

	/**
	 * Get current SEO meta for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return array Current SEO meta.
	 */
	public function get_current_meta( $post_id ) {
		$meta = [
			'title'           => '',
			'description'     => '',
			'focus_keyword'   => '',
			'og_title'        => '',
			'og_description'  => '',
		];

		if ( ! $this->is_available() ) {
			return $meta;
		}

		switch ( $this->active_plugin ) {
			case 'yoast':
				$meta['title']          = get_post_meta( $post_id, '_yoast_wpseo_title', true );
				$meta['description']    = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
				$meta['focus_keyword']  = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
				$meta['og_title']       = get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true );
				$meta['og_description'] = get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true );
				break;

			case 'rankmath':
				$meta['title']          = get_post_meta( $post_id, 'rank_math_title', true );
				$meta['description']    = get_post_meta( $post_id, 'rank_math_description', true );
				$meta['focus_keyword']  = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
				$meta['og_title']       = get_post_meta( $post_id, 'rank_math_facebook_title', true );
				$meta['og_description'] = get_post_meta( $post_id, 'rank_math_facebook_description', true );
				break;
		}

		return $meta;
	}

	/**
	 * Clean content for analysis.
	 *
	 * @param string $content Raw content.
	 * @return string Cleaned content.
	 */
	protected function clean_content( $content ) {
		// Remove Elementor JSON data
		$content = preg_replace( '/\[elementor-template[^\]]*\]/', '', $content );

		// Strip shortcodes
		$content = strip_shortcodes( $content );

		// Strip HTML tags
		$content = wp_strip_all_tags( $content );

		// Normalize whitespace
		$content = preg_replace( '/\s+/', ' ', $content );

		return trim( $content );
	}

	/**
	 * Build SEO generation prompt.
	 *
	 * @param string $content Content to analyze.
	 * @param array  $options Generation options.
	 * @return string The prompt.
	 */
	protected function build_seo_prompt( $content, $options ) {
		$prompt = "You are an SEO expert. Analyze the following content and generate optimized SEO metadata.\n\n";

		$prompt .= "## Content to Analyze:\n";
		$prompt .= substr( $content, 0, 3000 ) . "\n\n";

		if ( ! empty( $options['focus_keyword'] ) ) {
			$prompt .= "## Target Focus Keyword:\n";
			$prompt .= $options['focus_keyword'] . "\n\n";
		}

		$prompt .= "## Requirements:\n";
		$prompt .= "- Tone: {$options['tone']}\n";
		$prompt .= "- Language: {$options['language']}\n\n";

		$prompt .= "## Generate the following (respond in JSON format only):\n";
		$prompt .= "{\n";
		$prompt .= '  "title": "SEO title (50-60 characters, include focus keyword)",';
		$prompt .= "\n";
		$prompt .= '  "description": "Meta description (150-160 characters, compelling, include focus keyword)",';
		$prompt .= "\n";
		$prompt .= '  "focus_keyword": "Primary keyword or phrase to target",';
		$prompt .= "\n";
		$prompt .= '  "secondary_keywords": ["keyword1", "keyword2", "keyword3"],';
		$prompt .= "\n";
		$prompt .= '  "og_title": "Open Graph title for social sharing (slightly different from SEO title)",';
		$prompt .= "\n";
		$prompt .= '  "og_description": "Open Graph description for social sharing",';
		$prompt .= "\n";
		$prompt .= '  "twitter_title": "Twitter card title",';
		$prompt .= "\n";
		$prompt .= '  "twitter_description": "Twitter card description"';
		$prompt .= "\n}\n\n";

		$prompt .= "Output ONLY valid JSON, no explanations or markdown.";

		return $prompt;
	}

	/**
	 * Generate SEO content using AI provider.
	 *
	 * @param string $prompt The prompt.
	 * @return string|WP_Error Generated content or error.
	 */
	protected function generate_with_ai( $prompt ) {
		// Use existing AiMentor provider system
		if ( function_exists( 'aimentor_generate_with_fallback' ) ) {
			return aimentor_generate_with_fallback( $prompt, [ 'task' => 'copy' ] );
		}

		// Fallback to direct provider call
		$provider_key = get_option( 'aimentor_provider', 'grok' );

		$provider_map = [
			'grok'      => 'AiMentor_Provider_Grok',
			'openai'    => 'AiMentor_Provider_OpenAI',
			'anthropic' => 'AiMentor_Provider_Anthropic',
		];

		if ( ! isset( $provider_map[ $provider_key ] ) || ! class_exists( $provider_map[ $provider_key ] ) ) {
			return new WP_Error( 'no_provider', __( 'No AI provider configured.', 'aimentor' ) );
		}

		$provider = new $provider_map[ $provider_key ]();
		$result   = $provider->generate( $prompt, [ 'task' => 'copy' ] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['content'] ?? '';
	}

	/**
	 * Parse SEO response from AI.
	 *
	 * @param string $response AI response.
	 * @return array|WP_Error Parsed SEO data or error.
	 */
	protected function parse_seo_response( $response ) {
		// Extract JSON from response
		$json_match = preg_match( '/\{[\s\S]*\}/', $response, $matches );

		if ( ! $json_match ) {
			return new WP_Error( 'parse_error', __( 'Could not parse SEO response.', 'aimentor' ) );
		}

		$data = json_decode( $matches[0], true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_error', __( 'Invalid JSON in SEO response.', 'aimentor' ) );
		}

		// Validate and sanitize
		$seo_data = [
			'title'              => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'description'        => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'focus_keyword'      => isset( $data['focus_keyword'] ) ? sanitize_text_field( $data['focus_keyword'] ) : '',
			'secondary_keywords' => isset( $data['secondary_keywords'] ) ? array_map( 'sanitize_text_field', (array) $data['secondary_keywords'] ) : [],
			'og_title'           => isset( $data['og_title'] ) ? sanitize_text_field( $data['og_title'] ) : '',
			'og_description'     => isset( $data['og_description'] ) ? sanitize_textarea_field( $data['og_description'] ) : '',
			'twitter_title'      => isset( $data['twitter_title'] ) ? sanitize_text_field( $data['twitter_title'] ) : '',
			'twitter_description' => isset( $data['twitter_description'] ) ? sanitize_textarea_field( $data['twitter_description'] ) : '',
		];

		return $seo_data;
	}

	/**
	 * Suggest focus keywords for content.
	 *
	 * @param string $content The content to analyze.
	 * @param int    $count   Number of keywords to suggest.
	 * @return array|WP_Error Suggested keywords or error.
	 */
	public function suggest_keywords( $content, $count = 5 ) {
		$clean_content = $this->clean_content( $content );

		$prompt = "Analyze this content and suggest {$count} focus keyword phrases for SEO.\n\n";
		$prompt .= "Content:\n" . substr( $clean_content, 0, 2000 ) . "\n\n";
		$prompt .= "Return ONLY a JSON array of keyword phrases, e.g.:\n";
		$prompt .= '["keyword phrase 1", "keyword phrase 2", "keyword phrase 3"]';

		$result = $this->generate_with_ai( $prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Parse JSON array
		$json_match = preg_match( '/\[[\s\S]*\]/', $result, $matches );

		if ( ! $json_match ) {
			return new WP_Error( 'parse_error', __( 'Could not parse keywords response.', 'aimentor' ) );
		}

		$keywords = json_decode( $matches[0], true );

		if ( ! is_array( $keywords ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid keywords response.', 'aimentor' ) );
		}

		return array_map( 'sanitize_text_field', $keywords );
	}

	/**
	 * Analyze content for SEO score.
	 *
	 * @param string $content       The content to analyze.
	 * @param string $focus_keyword The focus keyword.
	 * @return array Analysis results.
	 */
	public function analyze_content( $content, $focus_keyword = '' ) {
		$clean_content = $this->clean_content( $content );
		$word_count    = str_word_count( $clean_content );

		$analysis = [
			'word_count'            => $word_count,
			'keyword_density'       => 0,
			'keyword_in_first_100'  => false,
			'readability'           => 'unknown',
			'suggestions'           => [],
		];

		if ( ! empty( $focus_keyword ) ) {
			$keyword_count = substr_count( strtolower( $clean_content ), strtolower( $focus_keyword ) );
			$analysis['keyword_density'] = $word_count > 0 ? round( ( $keyword_count / $word_count ) * 100, 2 ) : 0;

			$first_100_words = implode( ' ', array_slice( explode( ' ', $clean_content ), 0, 100 ) );
			$analysis['keyword_in_first_100'] = stripos( $first_100_words, $focus_keyword ) !== false;
		}

		// Generate suggestions
		if ( $word_count < 300 ) {
			$analysis['suggestions'][] = __( 'Content is too short. Aim for at least 300 words.', 'aimentor' );
		}

		if ( ! empty( $focus_keyword ) ) {
			if ( $analysis['keyword_density'] < 0.5 ) {
				$analysis['suggestions'][] = __( 'Keyword density is too low. Use the focus keyword more often.', 'aimentor' );
			} elseif ( $analysis['keyword_density'] > 3 ) {
				$analysis['suggestions'][] = __( 'Keyword density is too high. Reduce keyword usage to avoid over-optimization.', 'aimentor' );
			}

			if ( ! $analysis['keyword_in_first_100'] ) {
				$analysis['suggestions'][] = __( 'Include the focus keyword in the first 100 words.', 'aimentor' );
			}
		}

		return $analysis;
	}
}
