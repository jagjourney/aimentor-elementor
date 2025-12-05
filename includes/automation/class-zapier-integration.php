<?php
/**
 * Zapier Integration
 *
 * Webhooks for Zapier integration to trigger and receive AiMentor events.
 *
 * @package AiMentor
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Zapier_Integration {

	/**
	 * Option key for webhook subscriptions.
	 */
	const SUBSCRIPTIONS_KEY = 'aimentor_zapier_subscriptions';

	/**
	 * REST API namespace.
	 */
	const REST_NAMESPACE = 'aimentor/v1';

	/**
	 * Supported trigger events.
	 */
	const TRIGGER_EVENTS = [
		'content_generated' => [
			'label'       => 'Content Generated',
			'description' => 'Triggers when AI content is generated',
		],
		'page_generated' => [
			'label'       => 'Page Generated',
			'description' => 'Triggers when an Elementor page is generated',
		],
		'image_generated' => [
			'label'       => 'Image Generated',
			'description' => 'Triggers when an AI image is generated',
		],
		'post_created' => [
			'label'       => 'Post Created',
			'description' => 'Triggers when a post is created via pipeline',
		],
		'pipeline_completed' => [
			'label'       => 'Pipeline Completed',
			'description' => 'Triggers when a pipeline run completes',
		],
		'scheduled_content_completed' => [
			'label'       => 'Scheduled Content Ready',
			'description' => 'Triggers when scheduled content is generated',
		],
	];

	/**
	 * Supported action endpoints.
	 */
	const ACTION_ENDPOINTS = [
		'generate_content' => [
			'label'       => 'Generate Content',
			'description' => 'Generate AI content from prompt',
		],
		'generate_image' => [
			'label'       => 'Generate Image',
			'description' => 'Generate AI image from prompt',
		],
		'translate' => [
			'label'       => 'Translate Content',
			'description' => 'Translate content to another language',
		],
		'create_post' => [
			'label'       => 'Create Post',
			'description' => 'Create a WordPress post with AI content',
		],
		'run_pipeline' => [
			'label'       => 'Run Pipeline',
			'description' => 'Trigger a content pipeline',
		],
		'schedule_content' => [
			'label'       => 'Schedule Content',
			'description' => 'Add content to the calendar',
		],
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	protected function init_hooks() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Hook into AiMentor events to send to Zapier
		add_action( 'aimentor_content_generated', [ $this, 'on_content_generated' ], 10, 2 );
		add_action( 'aimentor_page_generated', [ $this, 'on_page_generated' ], 10, 2 );
		add_action( 'aimentor_image_generated', [ $this, 'on_image_generated' ], 10, 2 );
		add_action( 'aimentor_pipeline_executed', [ $this, 'on_pipeline_completed' ], 10, 2 );
		add_action( 'aimentor_scheduled_content_completed', [ $this, 'on_scheduled_completed' ], 10, 2 );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		// Subscribe to triggers (for Zapier to call)
		register_rest_route( self::REST_NAMESPACE, '/zapier/subscribe', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_subscribe' ],
			'permission_callback' => [ $this, 'check_api_key' ],
		] );

		// Unsubscribe from triggers
		register_rest_route( self::REST_NAMESPACE, '/zapier/unsubscribe', [
			'methods'             => 'DELETE',
			'callback'            => [ $this, 'handle_unsubscribe' ],
			'permission_callback' => [ $this, 'check_api_key' ],
		] );

		// Perform sample for trigger (Zapier polls this)
		register_rest_route( self::REST_NAMESPACE, '/zapier/sample/(?P<event>[a-z_]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_sample' ],
			'permission_callback' => [ $this, 'check_api_key' ],
		] );

		// Action endpoints
		register_rest_route( self::REST_NAMESPACE, '/zapier/action/generate-content', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'action_generate_content' ],
			'permission_callback' => [ $this, 'check_api_key' ],
		] );

		register_rest_route( self::REST_NAMESPACE, '/zapier/action/generate-image', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'action_generate_image' ],
			'permission_callback' => [ $this, 'check_api_key' ],
		] );

		register_rest_route( self::REST_NAMESPACE, '/zapier/action/translate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'action_translate' ],
			'permission_callback' => [ $this, 'check_api_key' ],
		] );

		register_rest_route( self::REST_NAMESPACE, '/zapier/action/create-post', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'action_create_post' ],
			'permission_callback' => [ $this, 'check_api_key' ],
		] );

		register_rest_route( self::REST_NAMESPACE, '/zapier/action/run-pipeline', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'action_run_pipeline' ],
			'permission_callback' => [ $this, 'check_api_key' ],
		] );

		register_rest_route( self::REST_NAMESPACE, '/zapier/action/schedule-content', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'action_schedule_content' ],
			'permission_callback' => [ $this, 'check_api_key' ],
		] );

		// Authentication test
		register_rest_route( self::REST_NAMESPACE, '/zapier/auth-test', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_auth_test' ],
			'permission_callback' => [ $this, 'check_api_key' ],
		] );
	}

	/**
	 * Check API key authentication.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_api_key( $request ) {
		$api_key = $request->get_header( 'X-AiMentor-API-Key' );

		if ( empty( $api_key ) ) {
			$api_key = $request->get_param( 'api_key' );
		}

		$stored_key = get_option( 'aimentor_zapier_api_key', '' );

		if ( empty( $stored_key ) ) {
			// Generate key if not exists
			$stored_key = wp_generate_password( 32, false );
			update_option( 'aimentor_zapier_api_key', $stored_key );
		}

		if ( ! hash_equals( $stored_key, $api_key ) ) {
			return new WP_Error(
				'unauthorized',
				__( 'Invalid API key', 'aimentor' ),
				[ 'status' => 401 ]
			);
		}

		return true;
	}

	/**
	 * Handle auth test endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_auth_test( $request ) {
		return new WP_REST_Response( [
			'success'   => true,
			'site_name' => get_bloginfo( 'name' ),
			'site_url'  => home_url(),
		], 200 );
	}

	/**
	 * Handle subscription request from Zapier.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_subscribe( $request ) {
		$event       = $request->get_param( 'event' );
		$webhook_url = $request->get_param( 'hookUrl' );

		if ( ! isset( self::TRIGGER_EVENTS[ $event ] ) ) {
			return new WP_REST_Response( [
				'error' => 'Invalid event type',
			], 400 );
		}

		if ( empty( $webhook_url ) || ! filter_var( $webhook_url, FILTER_VALIDATE_URL ) ) {
			return new WP_REST_Response( [
				'error' => 'Invalid webhook URL',
			], 400 );
		}

		$subscription_id = $this->add_subscription( $event, $webhook_url );

		return new WP_REST_Response( [
			'id'    => $subscription_id,
			'event' => $event,
		], 201 );
	}

	/**
	 * Handle unsubscription request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_unsubscribe( $request ) {
		$subscription_id = $request->get_param( 'id' );

		if ( empty( $subscription_id ) ) {
			return new WP_REST_Response( [
				'error' => 'Subscription ID required',
			], 400 );
		}

		$this->remove_subscription( $subscription_id );

		return new WP_REST_Response( [
			'success' => true,
		], 200 );
	}

	/**
	 * Handle sample data request for trigger.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_sample( $request ) {
		$event = $request->get_param( 'event' );

		if ( ! isset( self::TRIGGER_EVENTS[ $event ] ) ) {
			return new WP_REST_Response( [
				'error' => 'Invalid event type',
			], 400 );
		}

		$sample = $this->get_sample_data( $event );

		return new WP_REST_Response( [ $sample ], 200 );
	}

	/**
	 * Add webhook subscription.
	 *
	 * @param string $event       Event name.
	 * @param string $webhook_url Webhook URL.
	 * @return string Subscription ID.
	 */
	protected function add_subscription( $event, $webhook_url ) {
		$subscriptions = get_option( self::SUBSCRIPTIONS_KEY, [] );

		$subscription_id = wp_generate_uuid4();

		$subscriptions[ $subscription_id ] = [
			'event'      => $event,
			'url'        => $webhook_url,
			'created_at' => current_time( 'mysql' ),
		];

		update_option( self::SUBSCRIPTIONS_KEY, $subscriptions );

		return $subscription_id;
	}

	/**
	 * Remove webhook subscription.
	 *
	 * @param string $subscription_id Subscription ID.
	 */
	protected function remove_subscription( $subscription_id ) {
		$subscriptions = get_option( self::SUBSCRIPTIONS_KEY, [] );

		unset( $subscriptions[ $subscription_id ] );

		update_option( self::SUBSCRIPTIONS_KEY, $subscriptions );
	}

	/**
	 * Get subscriptions for an event.
	 *
	 * @param string $event Event name.
	 * @return array Subscriptions.
	 */
	protected function get_subscriptions( $event ) {
		$subscriptions = get_option( self::SUBSCRIPTIONS_KEY, [] );

		return array_filter( $subscriptions, function( $sub ) use ( $event ) {
			return $sub['event'] === $event;
		} );
	}

	/**
	 * Send data to webhook subscribers.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Event data.
	 */
	protected function send_to_subscribers( $event, $data ) {
		$subscriptions = $this->get_subscriptions( $event );

		foreach ( $subscriptions as $subscription ) {
			wp_remote_post( $subscription['url'], [
				'body'    => wp_json_encode( $data ),
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'timeout' => 15,
			] );
		}
	}

	/**
	 * Get sample data for an event.
	 *
	 * @param string $event Event name.
	 * @return array Sample data.
	 */
	protected function get_sample_data( $event ) {
		$base = [
			'id'         => 'sample_' . wp_generate_uuid4(),
			'event'      => $event,
			'timestamp'  => current_time( 'c' ),
			'site_url'   => home_url(),
			'site_name'  => get_bloginfo( 'name' ),
		];

		switch ( $event ) {
			case 'content_generated':
				return array_merge( $base, [
					'content'    => 'This is sample generated content that would appear here.',
					'prompt'     => 'Write a blog post about AI technology',
					'word_count' => 150,
					'provider'   => 'grok',
				] );

			case 'page_generated':
				return array_merge( $base, [
					'page_type'      => 'landing',
					'sections_count' => 5,
					'post_id'        => 123,
					'edit_url'       => admin_url( 'post.php?post=123&action=elementor' ),
				] );

			case 'image_generated':
				return array_merge( $base, [
					'prompt'        => 'A beautiful sunset over mountains',
					'provider'      => 'dalle',
					'size'          => '1024x1024',
					'image_url'     => 'https://example.com/sample-image.png',
					'attachment_id' => 456,
				] );

			case 'post_created':
				return array_merge( $base, [
					'post_id'    => 789,
					'post_title' => 'Sample Blog Post Title',
					'post_url'   => home_url( '/sample-post/' ),
					'post_type'  => 'post',
					'status'     => 'draft',
				] );

			case 'pipeline_completed':
				return array_merge( $base, [
					'pipeline_id'   => 101,
					'pipeline_name' => 'Weekly Blog Generator',
					'success'       => true,
					'actions_count' => 3,
					'duration_ms'   => 5420,
				] );

			case 'scheduled_content_completed':
				return array_merge( $base, [
					'scheduled_id'  => 202,
					'content_type'  => 'blog_post',
					'result_post'   => 789,
					'scheduled_for' => current_time( 'c' ),
				] );

			default:
				return $base;
		}
	}

	// Event handlers

	/**
	 * Handle content generated event.
	 */
	public function on_content_generated( $content, $context ) {
		$this->send_to_subscribers( 'content_generated', [
			'id'         => wp_generate_uuid4(),
			'event'      => 'content_generated',
			'timestamp'  => current_time( 'c' ),
			'site_url'   => home_url(),
			'content'    => is_string( $content ) ? $content : wp_json_encode( $content ),
			'prompt'     => $context['prompt'] ?? '',
			'word_count' => is_string( $content ) ? str_word_count( $content ) : 0,
			'provider'   => $context['provider'] ?? '',
		] );
	}

	/**
	 * Handle page generated event.
	 */
	public function on_page_generated( $elementor_data, $context ) {
		$this->send_to_subscribers( 'page_generated', [
			'id'             => wp_generate_uuid4(),
			'event'          => 'page_generated',
			'timestamp'      => current_time( 'c' ),
			'site_url'       => home_url(),
			'page_type'      => $context['page_type'] ?? 'custom',
			'sections_count' => is_array( $elementor_data ) ? count( $elementor_data ) : 0,
			'post_id'        => $context['post_id'] ?? null,
		] );
	}

	/**
	 * Handle image generated event.
	 */
	public function on_image_generated( $result, $context ) {
		if ( is_wp_error( $result ) ) {
			return;
		}

		$this->send_to_subscribers( 'image_generated', [
			'id'            => wp_generate_uuid4(),
			'event'         => 'image_generated',
			'timestamp'     => current_time( 'c' ),
			'site_url'      => home_url(),
			'prompt'        => $context['prompt'] ?? '',
			'provider'      => $result['provider'] ?? '',
			'image_url'     => $result['images'][0]['url'] ?? '',
			'attachment_id' => $result['images'][0]['attachment_id'] ?? null,
		] );
	}

	/**
	 * Handle pipeline completed event.
	 */
	public function on_pipeline_completed( $pipeline_id, $results ) {
		$pipeline = null;
		if ( class_exists( 'AiMentor_Content_Pipeline' ) ) {
			$pipeline_manager = new AiMentor_Content_Pipeline();
			$pipeline = $pipeline_manager->get_pipeline( $pipeline_id );
		}

		$this->send_to_subscribers( 'pipeline_completed', [
			'id'            => wp_generate_uuid4(),
			'event'         => 'pipeline_completed',
			'timestamp'     => current_time( 'c' ),
			'site_url'      => home_url(),
			'pipeline_id'   => $pipeline_id,
			'pipeline_name' => $pipeline ? $pipeline['name'] : '',
			'success'       => $results['success'] ?? false,
			'actions_count' => count( $results['actions'] ?? [] ),
		] );
	}

	/**
	 * Handle scheduled content completed event.
	 */
	public function on_scheduled_completed( $item_id, $post_id ) {
		$item = null;
		if ( class_exists( 'AiMentor_Content_Calendar' ) ) {
			$calendar = new AiMentor_Content_Calendar();
			$item = $calendar->get_scheduled( $item_id );
		}

		$this->send_to_subscribers( 'scheduled_content_completed', [
			'id'           => wp_generate_uuid4(),
			'event'        => 'scheduled_content_completed',
			'timestamp'    => current_time( 'c' ),
			'site_url'     => home_url(),
			'scheduled_id' => $item_id,
			'content_type' => $item ? $item['content_type'] : '',
			'result_post'  => $post_id,
		] );
	}

	// Action endpoints

	/**
	 * Generate content action.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function action_generate_content( $request ) {
		$prompt = $request->get_param( 'prompt' );

		if ( empty( $prompt ) ) {
			return new WP_REST_Response( [
				'error' => 'Prompt is required',
			], 400 );
		}

		$options = [
			'task' => 'copy',
		];

		// Apply tone if specified
		$tone = $request->get_param( 'tone' );
		if ( $tone && class_exists( 'AiMentor_Tone_Profiles' ) ) {
			$tone_profiles = new AiMentor_Tone_Profiles();
			$prompt = $tone_profiles->apply_tone_to_prompt( $prompt, $tone );
		}

		// Apply language if specified
		$language = $request->get_param( 'language' );
		if ( $language && class_exists( 'AiMentor_Language_Support' ) ) {
			$language_support = new AiMentor_Language_Support();
			$result = $language_support->generate_in_language( $prompt, $language, $options );
		} elseif ( function_exists( 'aimentor_generate_with_fallback' ) ) {
			$result = aimentor_generate_with_fallback( $prompt, $options );
		} else {
			return new WP_REST_Response( [
				'error' => 'No AI provider configured',
			], 500 );
		}

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( [
				'error' => $result->get_error_message(),
			], 500 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'content' => $result,
		], 200 );
	}

	/**
	 * Generate image action.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function action_generate_image( $request ) {
		if ( ! class_exists( 'AiMentor_Image_Generator' ) ) {
			return new WP_REST_Response( [
				'error' => 'Image generator not available',
			], 500 );
		}

		$prompt = $request->get_param( 'prompt' );

		if ( empty( $prompt ) ) {
			return new WP_REST_Response( [
				'error' => 'Prompt is required',
			], 400 );
		}

		$generator = new AiMentor_Image_Generator();
		$result    = $generator->generate( $prompt, [
			'provider' => $request->get_param( 'provider' ),
			'size'     => $request->get_param( 'size' ) ?: 'square',
			'style'    => $request->get_param( 'style' ) ?: 'photographic',
		] );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( [
				'error' => $result->get_error_message(),
			], 500 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'images'  => $result['images'],
		], 200 );
	}

	/**
	 * Translate action.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function action_translate( $request ) {
		if ( ! class_exists( 'AiMentor_Language_Support' ) ) {
			return new WP_REST_Response( [
				'error' => 'Language support not available',
			], 500 );
		}

		$content = $request->get_param( 'content' );
		$target  = $request->get_param( 'target_language' );

		if ( empty( $content ) || empty( $target ) ) {
			return new WP_REST_Response( [
				'error' => 'Content and target_language are required',
			], 400 );
		}

		$language = new AiMentor_Language_Support();
		$result   = $language->translate( $content, $target );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( [
				'error' => $result->get_error_message(),
			], 500 );
		}

		return new WP_REST_Response( [
			'success'     => true,
			'translation' => $result,
		], 200 );
	}

	/**
	 * Create post action.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function action_create_post( $request ) {
		$title   = $request->get_param( 'title' );
		$content = $request->get_param( 'content' );
		$prompt  = $request->get_param( 'prompt' );

		if ( empty( $title ) ) {
			return new WP_REST_Response( [
				'error' => 'Title is required',
			], 400 );
		}

		// Generate content if prompt provided
		if ( empty( $content ) && ! empty( $prompt ) ) {
			if ( function_exists( 'aimentor_generate_with_fallback' ) ) {
				$content = aimentor_generate_with_fallback( $prompt, [ 'task' => 'copy' ] );
				if ( is_wp_error( $content ) ) {
					return new WP_REST_Response( [
						'error' => $content->get_error_message(),
					], 500 );
				}
			}
		}

		$post_id = wp_insert_post( [
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => $content ?: '',
			'post_status'  => $request->get_param( 'status' ) ?: 'draft',
			'post_type'    => $request->get_param( 'post_type' ) ?: 'post',
		] );

		if ( is_wp_error( $post_id ) ) {
			return new WP_REST_Response( [
				'error' => $post_id->get_error_message(),
			], 500 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'post_id' => $post_id,
			'url'     => get_permalink( $post_id ),
		], 201 );
	}

	/**
	 * Run pipeline action.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function action_run_pipeline( $request ) {
		if ( ! class_exists( 'AiMentor_Content_Pipeline' ) ) {
			return new WP_REST_Response( [
				'error' => 'Pipeline system not available',
			], 500 );
		}

		$pipeline_id = $request->get_param( 'pipeline_id' );

		if ( empty( $pipeline_id ) ) {
			return new WP_REST_Response( [
				'error' => 'Pipeline ID is required',
			], 400 );
		}

		$pipeline = new AiMentor_Content_Pipeline();
		$result   = $pipeline->run_pipeline( (int) $pipeline_id, [
			'force'        => true,
			'trigger_data' => $request->get_params(),
		] );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( [
				'error' => $result->get_error_message(),
			], 500 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'result'  => $result,
		], 200 );
	}

	/**
	 * Schedule content action.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function action_schedule_content( $request ) {
		if ( ! class_exists( 'AiMentor_Content_Calendar' ) ) {
			return new WP_REST_Response( [
				'error' => 'Calendar system not available',
			], 500 );
		}

		$calendar = new AiMentor_Content_Calendar();
		$result   = $calendar->schedule_content( [
			'title'          => $request->get_param( 'title' ),
			'content_type'   => $request->get_param( 'content_type' ) ?: 'blog_post',
			'scheduled_date' => $request->get_param( 'scheduled_date' ),
			'prompt'         => $request->get_param( 'prompt' ),
			'publish_status' => $request->get_param( 'publish_status' ) ?: 'draft',
		] );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( [
				'error' => $result->get_error_message(),
			], 400 );
		}

		return new WP_REST_Response( [
			'success'      => true,
			'scheduled_id' => $result,
		], 201 );
	}

	/**
	 * Get the API key for display.
	 *
	 * @return string API key.
	 */
	public function get_api_key() {
		$key = get_option( 'aimentor_zapier_api_key', '' );

		if ( empty( $key ) ) {
			$key = wp_generate_password( 32, false );
			update_option( 'aimentor_zapier_api_key', $key );
		}

		return $key;
	}

	/**
	 * Regenerate API key.
	 *
	 * @return string New API key.
	 */
	public function regenerate_api_key() {
		$key = wp_generate_password( 32, false );
		update_option( 'aimentor_zapier_api_key', $key );

		// Clear all subscriptions when key changes
		delete_option( self::SUBSCRIPTIONS_KEY );

		return $key;
	}

	/**
	 * Get all active subscriptions.
	 *
	 * @return array Subscriptions.
	 */
	public function get_all_subscriptions() {
		return get_option( self::SUBSCRIPTIONS_KEY, [] );
	}
}

// Initialize
new AiMentor_Zapier_Integration();
