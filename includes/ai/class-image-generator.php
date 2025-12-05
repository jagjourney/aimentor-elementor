<?php
/**
 * AI Image Generator
 *
 * Handles image generation via DALL-E 3 and Stability AI.
 *
 * @package AiMentor
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Image_Generator {

	/**
	 * Supported image providers.
	 */
	const PROVIDER_DALLE = 'dalle';
	const PROVIDER_STABILITY = 'stability';

	/**
	 * Default image sizes.
	 */
	const SIZES = [
		'square'    => [ 'width' => 1024, 'height' => 1024, 'label' => 'Square (1024x1024)' ],
		'landscape' => [ 'width' => 1792, 'height' => 1024, 'label' => 'Landscape (1792x1024)' ],
		'portrait'  => [ 'width' => 1024, 'height' => 1792, 'label' => 'Portrait (1024x1792)' ],
	];

	/**
	 * Image styles for Stability AI.
	 */
	const STYLES = [
		'photographic'    => 'Photographic',
		'digital-art'     => 'Digital Art',
		'anime'           => 'Anime',
		'cinematic'       => 'Cinematic',
		'comic-book'      => 'Comic Book',
		'fantasy-art'     => 'Fantasy Art',
		'line-art'        => 'Line Art',
		'analog-film'     => 'Analog Film',
		'neon-punk'       => 'Neon Punk',
		'isometric'       => 'Isometric',
		'low-poly'        => 'Low Poly',
		'origami'         => 'Origami',
		'modeling-compound' => 'Modeling Compound',
		'3d-model'        => '3D Model',
		'pixel-art'       => 'Pixel Art',
	];

	/**
	 * Generate an image using the specified provider.
	 *
	 * @param string $prompt   The image description prompt.
	 * @param array  $options  Generation options.
	 * @return array|WP_Error Result with image URL or error.
	 */
	public function generate( $prompt, $options = [] ) {
		$defaults = [
			'provider' => $this->get_default_provider(),
			'size'     => 'square',
			'style'    => 'photographic',
			'quality'  => 'standard',
			'n'        => 1,
		];

		$options = wp_parse_args( $options, $defaults );

		// Enhance prompt for better results
		$enhanced_prompt = $this->enhance_prompt( $prompt, $options );

		switch ( $options['provider'] ) {
			case self::PROVIDER_DALLE:
				return $this->generate_with_dalle( $enhanced_prompt, $options );

			case self::PROVIDER_STABILITY:
				return $this->generate_with_stability( $enhanced_prompt, $options );

			default:
				return new WP_Error( 'invalid_provider', __( 'Invalid image generation provider.', 'aimentor' ) );
		}
	}

	/**
	 * Generate image using DALL-E 3.
	 *
	 * @param string $prompt  The image prompt.
	 * @param array  $options Generation options.
	 * @return array|WP_Error
	 */
	protected function generate_with_dalle( $prompt, $options ) {
		$api_key = get_option( 'aimentor_openai_api_key', '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'OpenAI API key not configured for DALL-E.', 'aimentor' ) );
		}

		$size_config = self::SIZES[ $options['size'] ] ?? self::SIZES['square'];
		$size_string = $size_config['width'] . 'x' . $size_config['height'];

		// DALL-E 3 size mapping
		$dalle_sizes = [
			'1024x1024' => '1024x1024',
			'1792x1024' => '1792x1024',
			'1024x1792' => '1024x1792',
		];

		$dalle_size = $dalle_sizes[ $size_string ] ?? '1024x1024';

		$body = [
			'model'   => 'dall-e-3',
			'prompt'  => $prompt,
			'size'    => $dalle_size,
			'quality' => $options['quality'] === 'hd' ? 'hd' : 'standard',
			'n'       => 1, // DALL-E 3 only supports n=1
		];

		$response = wp_remote_post(
			'https://api.openai.com/v1/images/generations',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 ) {
			$error_message = $body['error']['message'] ?? __( 'DALL-E API error', 'aimentor' );
			return new WP_Error( 'dalle_error', $error_message );
		}

		if ( empty( $body['data'][0]['url'] ) ) {
			return new WP_Error( 'no_image', __( 'No image was generated.', 'aimentor' ) );
		}

		return [
			'success'  => true,
			'provider' => 'dalle',
			'images'   => array_map( function( $item ) {
				return [
					'url'            => $item['url'],
					'revised_prompt' => $item['revised_prompt'] ?? '',
				];
			}, $body['data'] ),
		];
	}

	/**
	 * Generate image using Stability AI.
	 *
	 * @param string $prompt  The image prompt.
	 * @param array  $options Generation options.
	 * @return array|WP_Error
	 */
	protected function generate_with_stability( $prompt, $options ) {
		$api_key = get_option( 'aimentor_stability_api_key', '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'Stability AI API key not configured.', 'aimentor' ) );
		}

		$size_config = self::SIZES[ $options['size'] ] ?? self::SIZES['square'];

		$body = [
			'text_prompts' => [
				[
					'text'   => $prompt,
					'weight' => 1,
				],
			],
			'cfg_scale'         => 7,
			'height'            => $size_config['height'],
			'width'             => $size_config['width'],
			'samples'           => min( $options['n'], 4 ),
			'steps'             => $options['quality'] === 'hd' ? 50 : 30,
		];

		// Add style preset if not default
		if ( ! empty( $options['style'] ) && $options['style'] !== 'none' ) {
			$body['style_preset'] = $options['style'];
		}

		$response = wp_remote_post(
			'https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 ) {
			$error_message = $body['message'] ?? __( 'Stability AI API error', 'aimentor' );
			return new WP_Error( 'stability_error', $error_message );
		}

		if ( empty( $body['artifacts'] ) ) {
			return new WP_Error( 'no_image', __( 'No image was generated.', 'aimentor' ) );
		}

		// Upload base64 images to media library
		$images = [];
		foreach ( $body['artifacts'] as $index => $artifact ) {
			if ( $artifact['finishReason'] === 'SUCCESS' ) {
				$upload_result = $this->upload_base64_image(
					$artifact['base64'],
					'aimentor-generated-' . time() . '-' . $index . '.png'
				);

				if ( ! is_wp_error( $upload_result ) ) {
					$images[] = [
						'url'           => $upload_result['url'],
						'attachment_id' => $upload_result['attachment_id'],
					];
				}
			}
		}

		if ( empty( $images ) ) {
			return new WP_Error( 'upload_failed', __( 'Failed to save generated images.', 'aimentor' ) );
		}

		return [
			'success'  => true,
			'provider' => 'stability',
			'images'   => $images,
		];
	}

	/**
	 * Upload a base64 encoded image to the media library.
	 *
	 * @param string $base64_data The base64 encoded image data.
	 * @param string $filename    The filename to use.
	 * @return array|WP_Error Upload result or error.
	 */
	protected function upload_base64_image( $base64_data, $filename ) {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'upload_dir_error', $upload_dir['error'] );
		}

		$image_data = base64_decode( $base64_data );

		if ( false === $image_data ) {
			return new WP_Error( 'decode_error', __( 'Failed to decode image data.', 'aimentor' ) );
		}

		$file_path = trailingslashit( $upload_dir['path'] ) . $filename;

		// Save file
		$saved = file_put_contents( $file_path, $image_data );

		if ( false === $saved ) {
			return new WP_Error( 'save_error', __( 'Failed to save image file.', 'aimentor' ) );
		}

		// Check file type
		$file_type = wp_check_filetype( $filename, null );

		// Prepare attachment data
		$attachment = [
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		// Insert attachment
		$attachment_id = wp_insert_attachment( $attachment, $file_path );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		return [
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
			'path'          => $file_path,
		];
	}

	/**
	 * Enhance prompt for better image generation results.
	 *
	 * @param string $prompt  Original prompt.
	 * @param array  $options Generation options.
	 * @return string Enhanced prompt.
	 */
	protected function enhance_prompt( $prompt, $options ) {
		$enhancements = [];

		// Add quality descriptors
		if ( $options['quality'] === 'hd' ) {
			$enhancements[] = 'highly detailed';
			$enhancements[] = '8K resolution';
		}

		// Add style context for DALL-E (Stability uses style_preset)
		if ( $options['provider'] === self::PROVIDER_DALLE && ! empty( $options['style'] ) ) {
			$style_descriptions = [
				'photographic'    => 'photorealistic photograph',
				'digital-art'     => 'digital art illustration',
				'anime'           => 'anime style artwork',
				'cinematic'       => 'cinematic movie still',
				'fantasy-art'     => 'fantasy art illustration',
				'3d-model'        => '3D rendered image',
			];

			if ( isset( $style_descriptions[ $options['style'] ] ) ) {
				$enhancements[] = $style_descriptions[ $options['style'] ];
			}
		}

		// Prepend enhancements if any
		if ( ! empty( $enhancements ) ) {
			return implode( ', ', $enhancements ) . '. ' . $prompt;
		}

		return $prompt;
	}

	/**
	 * Get the default image provider.
	 *
	 * @return string Provider key.
	 */
	public function get_default_provider() {
		$default = get_option( 'aimentor_image_provider', '' );

		if ( empty( $default ) ) {
			// Auto-detect based on configured API keys
			if ( ! empty( get_option( 'aimentor_openai_api_key', '' ) ) ) {
				return self::PROVIDER_DALLE;
			}
			if ( ! empty( get_option( 'aimentor_stability_api_key', '' ) ) ) {
				return self::PROVIDER_STABILITY;
			}
		}

		return $default ?: self::PROVIDER_DALLE;
	}

	/**
	 * Get available providers.
	 *
	 * @return array Provider options.
	 */
	public function get_available_providers() {
		return [
			self::PROVIDER_DALLE => [
				'label'       => 'DALL-E 3 (OpenAI)',
				'description' => __( 'High-quality AI image generation with prompt enhancement.', 'aimentor' ),
				'configured'  => ! empty( get_option( 'aimentor_openai_api_key', '' ) ),
			],
			self::PROVIDER_STABILITY => [
				'label'       => 'Stability AI (SDXL)',
				'description' => __( 'Stable Diffusion XL with style presets and multiple outputs.', 'aimentor' ),
				'configured'  => ! empty( get_option( 'aimentor_stability_api_key', '' ) ),
			],
		];
	}

	/**
	 * Get available sizes.
	 *
	 * @return array Size options.
	 */
	public function get_available_sizes() {
		return self::SIZES;
	}

	/**
	 * Get available styles.
	 *
	 * @return array Style options.
	 */
	public function get_available_styles() {
		return self::STYLES;
	}

	/**
	 * Download a remote image to the media library.
	 *
	 * @param string $url      Remote image URL.
	 * @param string $filename Optional filename.
	 * @return array|WP_Error Upload result or error.
	 */
	public function download_to_media_library( $url, $filename = '' ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download file to temp location
		$temp_file = download_url( $url );

		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		// Prepare file array
		if ( empty( $filename ) ) {
			$filename = 'aimentor-generated-' . time() . '.png';
		}

		$file_array = [
			'name'     => $filename,
			'tmp_name' => $temp_file,
		];

		// Handle sideload
		$attachment_id = media_handle_sideload( $file_array, 0 );

		// Clean up temp file
		if ( file_exists( $temp_file ) ) {
			@unlink( $temp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return [
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
		];
	}
}
