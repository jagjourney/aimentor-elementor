<?php
/**
 * AiMentor Generation Pipeline
 *
 * Handles canvas generation with validation, repair, retry, and fallback logic.
 *
 * @package AiMentor
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Generation_Pipeline {

	/**
	 * Maximum retry attempts.
	 *
	 * @var int
	 */
	const MAX_RETRIES = 2;

	/**
	 * Schema validator instance.
	 *
	 * @var AiMentor_Elementor_Schema_Validator
	 */
	protected $validator;

	/**
	 * JSON repair instance.
	 *
	 * @var AiMentor_Elementor_JSON_Repair
	 */
	protected $repair;

	/**
	 * Prompt builder instance.
	 *
	 * @var AiMentor_Elementor_Prompt_Builder
	 */
	protected $prompt_builder;

	/**
	 * Pipeline execution log.
	 *
	 * @var array
	 */
	protected $log = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->validator      = new AiMentor_Elementor_Schema_Validator();
		$this->repair         = new AiMentor_Elementor_JSON_Repair();
		$this->prompt_builder = new AiMentor_Elementor_Prompt_Builder();
	}

	/**
	 * Execute a canvas generation with full pipeline.
	 *
	 * @param string $prompt   The user prompt.
	 * @param array  $args     Generation arguments.
	 * @param string $provider Primary provider to use.
	 * @return array|WP_Error Generation result or error.
	 */
	public function generate( $prompt, $args = [], $provider = null ) {
		$this->log = [];

		// Determine provider
		if ( null === $provider ) {
			$provider = $this->get_active_provider();
		}

		// Build enhanced prompt
		$context        = isset( $args['context'] ) ? $args['context'] : [];
		$prompt_parts   = $this->prompt_builder->build_canvas_prompt( $prompt, $context );
		$enhanced_args  = $args;

		// Inject system instruction if not already set
		if ( empty( $enhanced_args['context']['system'] ) ) {
			$enhanced_args['context']['system'] = $prompt_parts['system'];
		}

		$this->log[] = [
			'step'     => 'prompt_enhanced',
			'provider' => $provider,
			'time'     => current_time( 'mysql' ),
		];

		// Attempt generation with primary provider
		$result = $this->attempt_generation( $provider, $prompt_parts['prompt'], $enhanced_args );

		// If successful, return
		if ( ! is_wp_error( $result ) && ! empty( $result['valid'] ) ) {
			$result['pipeline_log'] = $this->log;
			return $result;
		}

		// If failed, try fallback providers
		$fallback_providers = $this->get_fallback_providers( $provider );

		foreach ( $fallback_providers as $fallback ) {
			$this->log[] = [
				'step'     => 'fallback_attempt',
				'provider' => $fallback,
				'reason'   => is_wp_error( $result ) ? $result->get_error_message() : 'validation_failed',
				'time'     => current_time( 'mysql' ),
			];

			$result = $this->attempt_generation( $fallback, $prompt_parts['prompt'], $enhanced_args );

			if ( ! is_wp_error( $result ) && ! empty( $result['valid'] ) ) {
				$result['pipeline_log']     = $this->log;
				$result['fallback_used']    = $fallback;
				$result['original_provider'] = $provider;
				return $result;
			}
		}

		// All providers failed
		$this->log[] = [
			'step'  => 'all_failed',
			'time'  => current_time( 'mysql' ),
		];

		if ( is_wp_error( $result ) ) {
			$result->add_data( [ 'pipeline_log' => $this->log ] );
			return $result;
		}

		return new WP_Error(
			'aimentor_generation_failed',
			__( 'Failed to generate valid Elementor JSON after all attempts.', 'aimentor' ),
			[
				'pipeline_log' => $this->log,
				'last_result'  => $result,
			]
		);
	}

	/**
	 * Attempt generation with a specific provider.
	 *
	 * @param string $provider Provider key.
	 * @param string $prompt   The prompt.
	 * @param array  $args     Generation arguments.
	 * @return array|WP_Error Result or error.
	 */
	protected function attempt_generation( $provider, $prompt, $args ) {
		$provider_instance = $this->get_provider_instance( $provider );

		if ( is_wp_error( $provider_instance ) ) {
			return $provider_instance;
		}

		$attempt = 0;
		$last_errors = [];

		while ( $attempt <= self::MAX_RETRIES ) {
			$attempt++;

			$this->log[] = [
				'step'     => 'generation_attempt',
				'attempt'  => $attempt,
				'provider' => $provider,
				'time'     => current_time( 'mysql' ),
			];

			// Adjust prompt for retries
			$current_prompt = $prompt;
			if ( $attempt > 1 && ! empty( $last_errors ) ) {
				$retry_parts    = $this->prompt_builder->build_retry_prompt( $prompt, $last_errors );
				$current_prompt = $retry_parts['prompt'];

				if ( ! empty( $retry_parts['system'] ) ) {
					$args['context']['system'] = $retry_parts['system'];
				}
			}

			// Make the API request
			$response = $provider_instance->request( $current_prompt, $args );

			if ( is_wp_error( $response ) ) {
				$this->log[] = [
					'step'    => 'api_error',
					'attempt' => $attempt,
					'error'   => $response->get_error_message(),
					'time'    => current_time( 'mysql' ),
				];

				// Don't retry on rate limits or auth errors
				$error_code = $response->get_error_code();
				if ( in_array( $error_code, [ 'aimentor_rate_limited', 'aimentor_unauthorized', 'aimentor_missing_api_key' ], true ) ) {
					return $response;
				}

				$last_errors = [
					[
						'code'    => $error_code,
						'message' => $response->get_error_message(),
						'path'    => 'api',
					],
				];
				continue;
			}

			// Process the response
			$processed = $this->process_response( $response );

			if ( $processed['valid'] ) {
				$this->log[] = [
					'step'    => 'success',
					'attempt' => $attempt,
					'repairs' => $processed['repairs'],
					'time'    => current_time( 'mysql' ),
				];

				return array_merge( $response, [
					'content'        => $processed['data'],
					'valid'          => true,
					'repairs'        => $processed['repairs'],
					'attempt_count'  => $attempt,
				] );
			}

			// Validation failed
			$this->log[] = [
				'step'    => 'validation_failed',
				'attempt' => $attempt,
				'errors'  => $processed['errors'],
				'time'    => current_time( 'mysql' ),
			];

			$last_errors = $processed['errors'];
		}

		// All retries exhausted
		return [
			'valid'   => false,
			'errors'  => $last_errors,
			'attempt' => $attempt,
		];
	}

	/**
	 * Process a provider response through validation and repair.
	 *
	 * @param array $response The provider response.
	 * @return array Processed result.
	 */
	protected function process_response( $response ) {
		$content = null;
		$repairs = [];

		// Extract the canvas content
		if ( isset( $response['type'] ) && 'canvas' === $response['type'] ) {
			$content = isset( $response['content'] ) ? $response['content'] : null;
		}

		if ( null === $content ) {
			// Try to get from variations
			if ( ! empty( $response['canvas_variations'] ) ) {
				$variation = $response['canvas_variations'][0];
				$content   = isset( $variation['layout'] ) ? $variation['layout'] : null;
			}
		}

		if ( null === $content ) {
			return [
				'valid'  => false,
				'errors' => [
					[
						'code'    => 'no_content',
						'message' => 'No canvas content found in response.',
						'path'    => 'root',
					],
				],
				'data'    => null,
				'repairs' => [],
			];
		}

		// Step 1: Validate the raw response
		$validation = $this->validator->validate( $content );

		if ( $validation['valid'] ) {
			return [
				'valid'    => true,
				'errors'   => [],
				'warnings' => $validation['warnings'],
				'data'     => $this->normalize_output( $validation['data'] ),
				'repairs'  => [],
			];
		}

		// Step 2: Attempt repair
		$repair_result = $this->repair->repair( $content );

		if ( ! $repair_result['success'] ) {
			return [
				'valid'   => false,
				'errors'  => $validation['errors'],
				'data'    => null,
				'repairs' => $repair_result['repairs'],
			];
		}

		$repairs = $repair_result['repairs'];

		// Step 3: Validate repaired content
		$post_repair_validation = $this->validator->validate( $repair_result['data'] );

		if ( $post_repair_validation['valid'] ) {
			return [
				'valid'    => true,
				'errors'   => [],
				'warnings' => $post_repair_validation['warnings'],
				'data'     => $this->normalize_output( $post_repair_validation['data'] ),
				'repairs'  => $repairs,
			];
		}

		// Repair wasn't sufficient
		return [
			'valid'   => false,
			'errors'  => $post_repair_validation['errors'],
			'data'    => $repair_result['data'],
			'repairs' => $repairs,
		];
	}

	/**
	 * Normalize the output structure.
	 *
	 * @param array $data The validated data.
	 * @return array Normalized data.
	 */
	protected function normalize_output( $data ) {
		// Ensure we have the elements array format
		if ( isset( $data['elements'] ) ) {
			return $data['elements'];
		}

		if ( isset( $data[0] ) ) {
			return $data;
		}

		return [ $data ];
	}

	/**
	 * Get provider instance.
	 *
	 * @param string $provider Provider key.
	 * @return object|WP_Error Provider instance or error.
	 */
	protected function get_provider_instance( $provider ) {
		if ( ! function_exists( 'jaggrok_get_active_provider' ) ) {
			return new WP_Error( 'aimentor_function_missing', 'Provider function not available.' );
		}

		$instance = jaggrok_get_active_provider( $provider );

		if ( null === $instance ) {
			return new WP_Error(
				'aimentor_invalid_provider',
				sprintf( __( 'Provider "%s" not found or not configured.', 'aimentor' ), $provider )
			);
		}

		return $instance;
	}

	/**
	 * Get the active provider key.
	 *
	 * @return string Provider key.
	 */
	protected function get_active_provider() {
		return get_option( 'aimentor_provider', 'grok' );
	}

	/**
	 * Get fallback providers in priority order.
	 *
	 * @param string $primary The primary provider that failed.
	 * @return array Fallback provider keys.
	 */
	protected function get_fallback_providers( $primary ) {
		$all_providers = [ 'anthropic', 'openai', 'grok' ];
		$fallbacks     = [];

		foreach ( $all_providers as $provider ) {
			if ( $provider === $primary ) {
				continue;
			}

			// Check if provider is configured
			if ( $this->is_provider_configured( $provider ) ) {
				$fallbacks[] = $provider;
			}
		}

		return $fallbacks;
	}

	/**
	 * Check if a provider is configured with API key.
	 *
	 * @param string $provider Provider key.
	 * @return bool Whether configured.
	 */
	protected function is_provider_configured( $provider ) {
		$key_option = '';

		switch ( $provider ) {
			case 'grok':
				$key_option = 'aimentor_xai_api_key';
				break;
			case 'openai':
				$key_option = 'aimentor_openai_api_key';
				break;
			case 'anthropic':
				$key_option = 'aimentor_anthropic_api_key';
				break;
		}

		if ( '' === $key_option ) {
			return false;
		}

		$key = get_option( $key_option, '' );
		return ! empty( $key );
	}

	/**
	 * Get the pipeline log.
	 *
	 * @return array Pipeline log.
	 */
	public function get_log() {
		return $this->log;
	}

	/**
	 * Get the validator instance.
	 *
	 * @return AiMentor_Elementor_Schema_Validator Validator.
	 */
	public function get_validator() {
		return $this->validator;
	}

	/**
	 * Get the repair instance.
	 *
	 * @return AiMentor_Elementor_JSON_Repair Repair.
	 */
	public function get_repair() {
		return $this->repair;
	}

	/**
	 * Get the prompt builder instance.
	 *
	 * @return AiMentor_Elementor_Prompt_Builder Prompt builder.
	 */
	public function get_prompt_builder() {
		return $this->prompt_builder;
	}
}
