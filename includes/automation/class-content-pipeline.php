<?php
/**
 * Content Pipeline
 *
 * Automated content generation pipeline with triggers and workflows.
 *
 * @package AiMentor
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Content_Pipeline {

	/**
	 * Pipeline post type.
	 */
	const POST_TYPE = 'aimentor_pipeline';

	/**
	 * Pipeline status taxonomy.
	 */
	const STATUS_TAXONOMY = 'pipeline_status';

	/**
	 * Cron hook for scheduled pipelines.
	 */
	const CRON_HOOK = 'aimentor_run_scheduled_pipelines';

	/**
	 * Pipeline statuses.
	 */
	const STATUS_DRAFT = 'draft';
	const STATUS_ACTIVE = 'active';
	const STATUS_PAUSED = 'paused';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED = 'failed';

	/**
	 * Trigger types.
	 */
	const TRIGGERS = [
		'schedule'    => [
			'label'       => 'Scheduled',
			'description' => 'Run on a recurring schedule',
			'icon'        => 'clock',
		],
		'manual'      => [
			'label'       => 'Manual',
			'description' => 'Run manually on demand',
			'icon'        => 'play',
		],
		'webhook'     => [
			'label'       => 'Webhook',
			'description' => 'Triggered by external webhook',
			'icon'        => 'webhook',
		],
		'post_publish' => [
			'label'       => 'Post Published',
			'description' => 'Run when a post is published',
			'icon'        => 'document',
		],
		'form_submit' => [
			'label'       => 'Form Submission',
			'description' => 'Run when a form is submitted',
			'icon'        => 'form',
		],
		'rss_new'     => [
			'label'       => 'New RSS Item',
			'description' => 'Run when new RSS feed item detected',
			'icon'        => 'rss',
		],
	];

	/**
	 * Action types.
	 */
	const ACTIONS = [
		'generate_content' => [
			'label'       => 'Generate Content',
			'description' => 'Generate AI content based on prompt',
		],
		'generate_page'    => [
			'label'       => 'Generate Page',
			'description' => 'Create a full Elementor page',
		],
		'generate_image'   => [
			'label'       => 'Generate Image',
			'description' => 'Generate AI images',
		],
		'translate'        => [
			'label'       => 'Translate Content',
			'description' => 'Translate content to target language',
		],
		'rewrite_tone'     => [
			'label'       => 'Rewrite in Tone',
			'description' => 'Rewrite content in specified tone',
		],
		'generate_seo'     => [
			'label'       => 'Generate SEO Meta',
			'description' => 'Generate SEO metadata for post',
		],
		'create_post'      => [
			'label'       => 'Create Post',
			'description' => 'Create a new WordPress post',
		],
		'update_post'      => [
			'label'       => 'Update Post',
			'description' => 'Update an existing post',
		],
		'send_webhook'     => [
			'label'       => 'Send Webhook',
			'description' => 'Send data to external webhook',
		],
		'send_slack'       => [
			'label'       => 'Send to Slack',
			'description' => 'Send notification to Slack',
		],
		'send_email'       => [
			'label'       => 'Send Email',
			'description' => 'Send email notification',
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
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( self::CRON_HOOK, [ $this, 'run_scheduled_pipelines' ] );
		add_action( 'transition_post_status', [ $this, 'handle_post_publish' ], 10, 3 );
		add_action( 'wp_ajax_aimentor_run_pipeline', [ $this, 'ajax_run_pipeline' ] );
		add_action( 'wp_ajax_aimentor_pipeline_webhook', [ $this, 'handle_webhook_trigger' ] );
		add_action( 'wp_ajax_nopriv_aimentor_pipeline_webhook', [ $this, 'handle_webhook_trigger' ] );

		// Schedule cron if not already scheduled
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Register pipeline post type.
	 */
	public function register_post_type() {
		register_post_type( self::POST_TYPE, [
			'labels'              => [
				'name'          => __( 'Content Pipelines', 'aimentor' ),
				'singular_name' => __( 'Content Pipeline', 'aimentor' ),
				'add_new'       => __( 'Add Pipeline', 'aimentor' ),
				'add_new_item'  => __( 'Add New Pipeline', 'aimentor' ),
				'edit_item'     => __( 'Edit Pipeline', 'aimentor' ),
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'aimentor-settings',
			'supports'            => [ 'title' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		] );
	}

	/**
	 * Create a new pipeline.
	 *
	 * @param array $data Pipeline configuration.
	 * @return int|WP_Error Pipeline ID or error.
	 */
	public function create_pipeline( $data ) {
		$defaults = [
			'name'        => '',
			'description' => '',
			'trigger'     => [],
			'actions'     => [],
			'conditions'  => [],
			'status'      => self::STATUS_DRAFT,
		];

		$data = wp_parse_args( $data, $defaults );

		// Validate trigger
		if ( empty( $data['trigger']['type'] ) || ! isset( self::TRIGGERS[ $data['trigger']['type'] ] ) ) {
			return new WP_Error( 'invalid_trigger', __( 'Invalid trigger type.', 'aimentor' ) );
		}

		// Validate actions
		if ( empty( $data['actions'] ) ) {
			return new WP_Error( 'no_actions', __( 'Pipeline must have at least one action.', 'aimentor' ) );
		}

		$post_id = wp_insert_post( [
			'post_type'   => self::POST_TYPE,
			'post_title'  => sanitize_text_field( $data['name'] ),
			'post_status' => 'publish',
			'meta_input'  => [
				'_pipeline_description' => sanitize_textarea_field( $data['description'] ),
				'_pipeline_trigger'     => $data['trigger'],
				'_pipeline_actions'     => $data['actions'],
				'_pipeline_conditions'  => $data['conditions'],
				'_pipeline_status'      => $data['status'],
				'_pipeline_run_count'   => 0,
				'_pipeline_last_run'    => null,
				'_pipeline_webhook_key' => wp_generate_uuid4(),
			],
		] );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set up schedule if needed
		if ( $data['trigger']['type'] === 'schedule' && $data['status'] === self::STATUS_ACTIVE ) {
			$this->schedule_pipeline( $post_id, $data['trigger'] );
		}

		return $post_id;
	}

	/**
	 * Update a pipeline.
	 *
	 * @param int   $pipeline_id Pipeline ID.
	 * @param array $data        Updated data.
	 * @return bool|WP_Error Success or error.
	 */
	public function update_pipeline( $pipeline_id, $data ) {
		$post = get_post( $pipeline_id );

		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return new WP_Error( 'invalid_pipeline', __( 'Pipeline not found.', 'aimentor' ) );
		}

		if ( isset( $data['name'] ) ) {
			wp_update_post( [
				'ID'         => $pipeline_id,
				'post_title' => sanitize_text_field( $data['name'] ),
			] );
		}

		$meta_fields = [
			'description' => '_pipeline_description',
			'trigger'     => '_pipeline_trigger',
			'actions'     => '_pipeline_actions',
			'conditions'  => '_pipeline_conditions',
			'status'      => '_pipeline_status',
		];

		foreach ( $meta_fields as $key => $meta_key ) {
			if ( isset( $data[ $key ] ) ) {
				update_post_meta( $pipeline_id, $meta_key, $data[ $key ] );
			}
		}

		// Update schedule if trigger changed
		if ( isset( $data['trigger'] ) ) {
			$this->clear_pipeline_schedule( $pipeline_id );
			if ( $data['trigger']['type'] === 'schedule' ) {
				$status = $data['status'] ?? get_post_meta( $pipeline_id, '_pipeline_status', true );
				if ( $status === self::STATUS_ACTIVE ) {
					$this->schedule_pipeline( $pipeline_id, $data['trigger'] );
				}
			}
		}

		return true;
	}

	/**
	 * Get pipeline by ID.
	 *
	 * @param int $pipeline_id Pipeline ID.
	 * @return array|null Pipeline data or null.
	 */
	public function get_pipeline( $pipeline_id ) {
		$post = get_post( $pipeline_id );

		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return null;
		}

		return [
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'description' => get_post_meta( $pipeline_id, '_pipeline_description', true ),
			'trigger'     => get_post_meta( $pipeline_id, '_pipeline_trigger', true ),
			'actions'     => get_post_meta( $pipeline_id, '_pipeline_actions', true ),
			'conditions'  => get_post_meta( $pipeline_id, '_pipeline_conditions', true ),
			'status'      => get_post_meta( $pipeline_id, '_pipeline_status', true ),
			'run_count'   => (int) get_post_meta( $pipeline_id, '_pipeline_run_count', true ),
			'last_run'    => get_post_meta( $pipeline_id, '_pipeline_last_run', true ),
			'webhook_key' => get_post_meta( $pipeline_id, '_pipeline_webhook_key', true ),
			'created'     => $post->post_date,
			'modified'    => $post->post_modified,
		];
	}

	/**
	 * Get all pipelines.
	 *
	 * @param array $args Query arguments.
	 * @return array Pipelines.
	 */
	public function get_pipelines( $args = [] ) {
		$defaults = [
			'status'   => '',
			'per_page' => 20,
			'page'     => 1,
		];

		$args = wp_parse_args( $args, $defaults );

		$query_args = [
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => $args['per_page'],
			'paged'          => $args['page'],
			'orderby'        => 'modified',
			'order'          => 'DESC',
		];

		if ( ! empty( $args['status'] ) ) {
			$query_args['meta_query'] = [
				[
					'key'   => '_pipeline_status',
					'value' => $args['status'],
				],
			];
		}

		$query     = new WP_Query( $query_args );
		$pipelines = [];

		foreach ( $query->posts as $post ) {
			$pipelines[] = $this->get_pipeline( $post->ID );
		}

		return [
			'pipelines' => $pipelines,
			'total'     => $query->found_posts,
			'pages'     => $query->max_num_pages,
		];
	}

	/**
	 * Run a pipeline.
	 *
	 * @param int   $pipeline_id Pipeline ID.
	 * @param array $context     Execution context (trigger data, etc.).
	 * @return array|WP_Error Execution results or error.
	 */
	public function run_pipeline( $pipeline_id, $context = [] ) {
		$pipeline = $this->get_pipeline( $pipeline_id );

		if ( ! $pipeline ) {
			return new WP_Error( 'invalid_pipeline', __( 'Pipeline not found.', 'aimentor' ) );
		}

		if ( $pipeline['status'] !== self::STATUS_ACTIVE && empty( $context['force'] ) ) {
			return new WP_Error( 'pipeline_inactive', __( 'Pipeline is not active.', 'aimentor' ) );
		}

		// Check conditions
		if ( ! $this->check_conditions( $pipeline['conditions'], $context ) ) {
			return new WP_Error( 'conditions_not_met', __( 'Pipeline conditions not met.', 'aimentor' ) );
		}

		$results = [
			'pipeline_id' => $pipeline_id,
			'started_at'  => current_time( 'mysql' ),
			'actions'     => [],
			'success'     => true,
		];

		$action_context = array_merge( $context, [
			'pipeline_id' => $pipeline_id,
			'results'     => [],
		] );

		// Execute actions in sequence
		foreach ( $pipeline['actions'] as $index => $action ) {
			$action_result = $this->execute_action( $action, $action_context );

			$results['actions'][] = [
				'index'   => $index,
				'type'    => $action['type'],
				'success' => ! is_wp_error( $action_result ),
				'result'  => is_wp_error( $action_result ) ? $action_result->get_error_message() : $action_result,
			];

			if ( is_wp_error( $action_result ) ) {
				$results['success'] = false;

				// Check if we should stop on error
				if ( ! empty( $action['stop_on_error'] ) ) {
					break;
				}
			}

			// Pass result to next action
			$action_context['results'][ $index ] = $action_result;
			$action_context['last_result'] = $action_result;
		}

		$results['completed_at'] = current_time( 'mysql' );

		// Update pipeline stats
		$run_count = (int) get_post_meta( $pipeline_id, '_pipeline_run_count', true );
		update_post_meta( $pipeline_id, '_pipeline_run_count', $run_count + 1 );
		update_post_meta( $pipeline_id, '_pipeline_last_run', $results['completed_at'] );

		// Log execution
		$this->log_execution( $pipeline_id, $results );

		// Fire action hook
		do_action( 'aimentor_pipeline_executed', $pipeline_id, $results );

		return $results;
	}

	/**
	 * Execute a single action.
	 *
	 * @param array $action  Action configuration.
	 * @param array $context Execution context.
	 * @return mixed|WP_Error Action result or error.
	 */
	protected function execute_action( $action, $context ) {
		if ( empty( $action['type'] ) || ! isset( self::ACTIONS[ $action['type'] ] ) ) {
			return new WP_Error( 'invalid_action', __( 'Invalid action type.', 'aimentor' ) );
		}

		// Process template variables in action config
		$action = $this->process_template_variables( $action, $context );

		switch ( $action['type'] ) {
			case 'generate_content':
				return $this->action_generate_content( $action, $context );

			case 'generate_page':
				return $this->action_generate_page( $action, $context );

			case 'generate_image':
				return $this->action_generate_image( $action, $context );

			case 'translate':
				return $this->action_translate( $action, $context );

			case 'rewrite_tone':
				return $this->action_rewrite_tone( $action, $context );

			case 'generate_seo':
				return $this->action_generate_seo( $action, $context );

			case 'create_post':
				return $this->action_create_post( $action, $context );

			case 'update_post':
				return $this->action_update_post( $action, $context );

			case 'send_webhook':
				return $this->action_send_webhook( $action, $context );

			case 'send_slack':
				return $this->action_send_slack( $action, $context );

			case 'send_email':
				return $this->action_send_email( $action, $context );

			default:
				return apply_filters( "aimentor_pipeline_action_{$action['type']}", null, $action, $context );
		}
	}

	/**
	 * Generate content action.
	 */
	protected function action_generate_content( $action, $context ) {
		$prompt = $action['prompt'] ?? '';

		if ( empty( $prompt ) ) {
			return new WP_Error( 'no_prompt', __( 'No prompt provided.', 'aimentor' ) );
		}

		$options = [
			'task' => 'copy',
		];

		if ( ! empty( $action['tone'] ) && class_exists( 'AiMentor_Tone_Profiles' ) ) {
			$tone_profiles = new AiMentor_Tone_Profiles();
			$prompt = $tone_profiles->apply_tone_to_prompt( $prompt, $action['tone'] );
		}

		if ( ! empty( $action['language'] ) && class_exists( 'AiMentor_Language_Support' ) ) {
			$language_support = new AiMentor_Language_Support();
			return $language_support->generate_in_language( $prompt, $action['language'], $options );
		}

		if ( function_exists( 'aimentor_generate_with_fallback' ) ) {
			return aimentor_generate_with_fallback( $prompt, $options );
		}

		return new WP_Error( 'no_provider', __( 'No AI provider configured.', 'aimentor' ) );
	}

	/**
	 * Generate page action.
	 */
	protected function action_generate_page( $action, $context ) {
		if ( ! class_exists( 'AiMentor_Elementor_Prompt_Builder' ) ) {
			return new WP_Error( 'missing_class', __( 'Prompt builder not available.', 'aimentor' ) );
		}

		$prompt_builder = new AiMentor_Elementor_Prompt_Builder();
		$page_type      = $action['page_type'] ?? 'landing';
		$answers        = $action['answers'] ?? [];

		$prompt_data = $prompt_builder->build_page_wizard_prompt( $page_type, $answers );

		if ( function_exists( 'aimentor_generate_with_fallback' ) ) {
			$result = aimentor_generate_with_fallback( $prompt_data['prompt'], [
				'task'   => 'canvas',
				'system' => $prompt_data['system'],
			] );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return [
				'elementor_data' => $result,
				'page_type'      => $page_type,
			];
		}

		return new WP_Error( 'no_provider', __( 'No AI provider configured.', 'aimentor' ) );
	}

	/**
	 * Generate image action.
	 */
	protected function action_generate_image( $action, $context ) {
		if ( ! class_exists( 'AiMentor_Image_Generator' ) ) {
			return new WP_Error( 'missing_class', __( 'Image generator not available.', 'aimentor' ) );
		}

		$generator = new AiMentor_Image_Generator();
		$prompt    = $action['prompt'] ?? '';

		if ( empty( $prompt ) ) {
			return new WP_Error( 'no_prompt', __( 'No image prompt provided.', 'aimentor' ) );
		}

		return $generator->generate( $prompt, [
			'provider' => $action['provider'] ?? null,
			'size'     => $action['size'] ?? 'square',
			'style'    => $action['style'] ?? 'photographic',
			'quality'  => $action['quality'] ?? 'standard',
		] );
	}

	/**
	 * Translate action.
	 */
	protected function action_translate( $action, $context ) {
		if ( ! class_exists( 'AiMentor_Language_Support' ) ) {
			return new WP_Error( 'missing_class', __( 'Language support not available.', 'aimentor' ) );
		}

		$language = new AiMentor_Language_Support();
		$content  = $action['content'] ?? $context['last_result'] ?? '';
		$target   = $action['target_language'] ?? 'es_ES';

		if ( empty( $content ) ) {
			return new WP_Error( 'no_content', __( 'No content to translate.', 'aimentor' ) );
		}

		return $language->translate( $content, $target );
	}

	/**
	 * Rewrite in tone action.
	 */
	protected function action_rewrite_tone( $action, $context ) {
		if ( ! class_exists( 'AiMentor_Tone_Profiles' ) ) {
			return new WP_Error( 'missing_class', __( 'Tone profiles not available.', 'aimentor' ) );
		}

		$tone_profiles = new AiMentor_Tone_Profiles();
		$content       = $action['content'] ?? $context['last_result'] ?? '';
		$tone          = $action['tone'] ?? 'professional';

		if ( empty( $content ) ) {
			return new WP_Error( 'no_content', __( 'No content to rewrite.', 'aimentor' ) );
		}

		return $tone_profiles->rewrite_in_tone( $content, $tone );
	}

	/**
	 * Generate SEO action.
	 */
	protected function action_generate_seo( $action, $context ) {
		if ( ! class_exists( 'AiMentor_SEO_Integration' ) ) {
			return new WP_Error( 'missing_class', __( 'SEO integration not available.', 'aimentor' ) );
		}

		$seo     = new AiMentor_SEO_Integration();
		$post_id = $action['post_id'] ?? $context['post_id'] ?? 0;

		if ( ! $post_id ) {
			return new WP_Error( 'no_post', __( 'No post ID provided.', 'aimentor' ) );
		}

		$seo_data = $seo->generate_seo_meta( $post_id, '', [
			'focus_keyword' => $action['focus_keyword'] ?? '',
		] );

		if ( is_wp_error( $seo_data ) ) {
			return $seo_data;
		}

		// Apply if requested
		if ( ! empty( $action['apply'] ) ) {
			$seo->apply_seo_meta( $post_id, $seo_data );
		}

		return $seo_data;
	}

	/**
	 * Create post action.
	 */
	protected function action_create_post( $action, $context ) {
		$post_data = [
			'post_title'   => $action['title'] ?? $context['last_result']['title'] ?? 'Untitled',
			'post_content' => $action['content'] ?? $context['last_result'] ?? '',
			'post_status'  => $action['status'] ?? 'draft',
			'post_type'    => $action['post_type'] ?? 'post',
			'post_author'  => $action['author'] ?? get_current_user_id(),
		];

		if ( ! empty( $action['categories'] ) ) {
			$post_data['post_category'] = $action['categories'];
		}

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Handle featured image
		if ( ! empty( $context['last_result']['images'][0]['attachment_id'] ) ) {
			set_post_thumbnail( $post_id, $context['last_result']['images'][0]['attachment_id'] );
		}

		return [
			'post_id' => $post_id,
			'url'     => get_permalink( $post_id ),
		];
	}

	/**
	 * Update post action.
	 */
	protected function action_update_post( $action, $context ) {
		$post_id = $action['post_id'] ?? $context['post_id'] ?? 0;

		if ( ! $post_id ) {
			return new WP_Error( 'no_post', __( 'No post ID provided.', 'aimentor' ) );
		}

		$post_data = [ 'ID' => $post_id ];

		if ( isset( $action['title'] ) ) {
			$post_data['post_title'] = $action['title'];
		}

		if ( isset( $action['content'] ) ) {
			$post_data['post_content'] = $action['content'];
		} elseif ( ! empty( $context['last_result'] ) && is_string( $context['last_result'] ) ) {
			$post_data['post_content'] = $context['last_result'];
		}

		if ( isset( $action['status'] ) ) {
			$post_data['post_status'] = $action['status'];
		}

		$result = wp_update_post( $post_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'post_id' => $post_id,
			'updated' => true,
		];
	}

	/**
	 * Send webhook action.
	 */
	protected function action_send_webhook( $action, $context ) {
		$url = $action['url'] ?? '';

		if ( empty( $url ) ) {
			return new WP_Error( 'no_url', __( 'No webhook URL provided.', 'aimentor' ) );
		}

		$payload = $action['payload'] ?? [
			'pipeline_id' => $context['pipeline_id'] ?? null,
			'data'        => $context['last_result'] ?? null,
			'timestamp'   => current_time( 'c' ),
		];

		$response = wp_remote_post( $url, [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return [
			'status_code' => wp_remote_retrieve_response_code( $response ),
			'body'        => wp_remote_retrieve_body( $response ),
		];
	}

	/**
	 * Send Slack action.
	 */
	protected function action_send_slack( $action, $context ) {
		if ( ! class_exists( 'AiMentor_Slack_Integration' ) ) {
			return new WP_Error( 'missing_class', __( 'Slack integration not available.', 'aimentor' ) );
		}

		$slack   = new AiMentor_Slack_Integration();
		$message = $action['message'] ?? '';
		$channel = $action['channel'] ?? '';

		if ( empty( $message ) ) {
			$message = is_string( $context['last_result'] ) ? $context['last_result'] : wp_json_encode( $context['last_result'] );
		}

		return $slack->send_message( $message, $channel );
	}

	/**
	 * Send email action.
	 */
	protected function action_send_email( $action, $context ) {
		$to      = $action['to'] ?? get_option( 'admin_email' );
		$subject = $action['subject'] ?? __( 'AiMentor Pipeline Notification', 'aimentor' );
		$message = $action['message'] ?? '';

		if ( empty( $message ) ) {
			$message = is_string( $context['last_result'] ) ? $context['last_result'] : wp_json_encode( $context['last_result'], JSON_PRETTY_PRINT );
		}

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$sent = wp_mail( $to, $subject, $message, $headers );

		return [
			'sent' => $sent,
			'to'   => $to,
		];
	}

	/**
	 * Process template variables in action config.
	 *
	 * @param array $action  Action configuration.
	 * @param array $context Execution context.
	 * @return array Processed action.
	 */
	protected function process_template_variables( $action, $context ) {
		$replacements = [
			'{{site_name}}'    => get_bloginfo( 'name' ),
			'{{site_url}}'     => home_url(),
			'{{date}}'         => current_time( 'Y-m-d' ),
			'{{datetime}}'     => current_time( 'Y-m-d H:i:s' ),
			'{{user_name}}'    => wp_get_current_user()->display_name ?? 'System',
			'{{last_result}}'  => is_string( $context['last_result'] ?? '' ) ? $context['last_result'] : '',
		];

		// Add context variables
		if ( ! empty( $context['trigger_data'] ) ) {
			foreach ( $context['trigger_data'] as $key => $value ) {
				if ( is_string( $value ) ) {
					$replacements[ "{{trigger.{$key}}}" ] = $value;
				}
			}
		}

		// Add post variables if available
		if ( ! empty( $context['post_id'] ) ) {
			$post = get_post( $context['post_id'] );
			if ( $post ) {
				$replacements['{{post.title}}']   = $post->post_title;
				$replacements['{{post.content}}'] = $post->post_content;
				$replacements['{{post.excerpt}}'] = $post->post_excerpt;
				$replacements['{{post.url}}']     = get_permalink( $post );
			}
		}

		return $this->replace_variables_recursive( $action, $replacements );
	}

	/**
	 * Recursively replace variables in array.
	 */
	protected function replace_variables_recursive( $data, $replacements ) {
		if ( is_string( $data ) ) {
			return str_replace( array_keys( $replacements ), array_values( $replacements ), $data );
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->replace_variables_recursive( $value, $replacements );
			}
		}

		return $data;
	}

	/**
	 * Check pipeline conditions.
	 */
	protected function check_conditions( $conditions, $context ) {
		if ( empty( $conditions ) ) {
			return true;
		}

		foreach ( $conditions as $condition ) {
			if ( ! $this->evaluate_condition( $condition, $context ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Evaluate a single condition.
	 */
	protected function evaluate_condition( $condition, $context ) {
		$field    = $condition['field'] ?? '';
		$operator = $condition['operator'] ?? 'equals';
		$value    = $condition['value'] ?? '';

		// Get actual value from context
		$actual = $this->get_context_value( $field, $context );

		switch ( $operator ) {
			case 'equals':
				return $actual === $value;
			case 'not_equals':
				return $actual !== $value;
			case 'contains':
				return strpos( $actual, $value ) !== false;
			case 'not_contains':
				return strpos( $actual, $value ) === false;
			case 'greater_than':
				return (float) $actual > (float) $value;
			case 'less_than':
				return (float) $actual < (float) $value;
			case 'is_empty':
				return empty( $actual );
			case 'not_empty':
				return ! empty( $actual );
			default:
				return true;
		}
	}

	/**
	 * Get value from context using dot notation.
	 */
	protected function get_context_value( $field, $context ) {
		$parts   = explode( '.', $field );
		$current = $context;

		foreach ( $parts as $part ) {
			if ( is_array( $current ) && isset( $current[ $part ] ) ) {
				$current = $current[ $part ];
			} else {
				return null;
			}
		}

		return $current;
	}

	/**
	 * Schedule a pipeline.
	 */
	protected function schedule_pipeline( $pipeline_id, $trigger ) {
		$schedule = $trigger['schedule'] ?? 'daily';

		wp_schedule_event(
			time(),
			$schedule,
			'aimentor_run_pipeline_' . $pipeline_id
		);

		add_action( 'aimentor_run_pipeline_' . $pipeline_id, function() use ( $pipeline_id ) {
			$this->run_pipeline( $pipeline_id );
		} );
	}

	/**
	 * Clear pipeline schedule.
	 */
	protected function clear_pipeline_schedule( $pipeline_id ) {
		wp_clear_scheduled_hook( 'aimentor_run_pipeline_' . $pipeline_id );
	}

	/**
	 * Run scheduled pipelines.
	 */
	public function run_scheduled_pipelines() {
		$pipelines = $this->get_pipelines( [
			'status'   => self::STATUS_ACTIVE,
			'per_page' => -1,
		] );

		foreach ( $pipelines['pipelines'] as $pipeline ) {
			if ( $pipeline['trigger']['type'] === 'schedule' ) {
				$this->check_and_run_scheduled( $pipeline );
			}
		}
	}

	/**
	 * Check and run a scheduled pipeline.
	 */
	protected function check_and_run_scheduled( $pipeline ) {
		$trigger   = $pipeline['trigger'];
		$last_run  = $pipeline['last_run'] ? strtotime( $pipeline['last_run'] ) : 0;
		$interval  = $this->get_schedule_interval( $trigger['schedule'] ?? 'daily' );

		if ( time() - $last_run >= $interval ) {
			$this->run_pipeline( $pipeline['id'] );
		}
	}

	/**
	 * Get schedule interval in seconds.
	 */
	protected function get_schedule_interval( $schedule ) {
		$intervals = [
			'hourly'     => HOUR_IN_SECONDS,
			'twicedaily' => 12 * HOUR_IN_SECONDS,
			'daily'      => DAY_IN_SECONDS,
			'weekly'     => WEEK_IN_SECONDS,
		];

		return $intervals[ $schedule ] ?? DAY_IN_SECONDS;
	}

	/**
	 * Handle post publish trigger.
	 */
	public function handle_post_publish( $new_status, $old_status, $post ) {
		if ( $new_status !== 'publish' || $old_status === 'publish' ) {
			return;
		}

		$pipelines = $this->get_pipelines( [
			'status'   => self::STATUS_ACTIVE,
			'per_page' => -1,
		] );

		foreach ( $pipelines['pipelines'] as $pipeline ) {
			if ( $pipeline['trigger']['type'] === 'post_publish' ) {
				// Check post type filter
				$post_types = $pipeline['trigger']['post_types'] ?? [ 'post' ];
				if ( in_array( $post->post_type, $post_types, true ) ) {
					$this->run_pipeline( $pipeline['id'], [
						'post_id'      => $post->ID,
						'trigger_data' => [
							'post_title' => $post->post_title,
							'post_type'  => $post->post_type,
						],
					] );
				}
			}
		}
	}

	/**
	 * Handle webhook trigger.
	 */
	public function handle_webhook_trigger() {
		$webhook_key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';

		if ( empty( $webhook_key ) ) {
			wp_send_json_error( [ 'message' => 'Missing webhook key' ], 400 );
		}

		// Find pipeline with this webhook key
		$query = new WP_Query( [
			'post_type'  => self::POST_TYPE,
			'meta_query' => [
				[
					'key'   => '_pipeline_webhook_key',
					'value' => $webhook_key,
				],
			],
			'posts_per_page' => 1,
		] );

		if ( ! $query->have_posts() ) {
			wp_send_json_error( [ 'message' => 'Invalid webhook key' ], 404 );
		}

		$pipeline_id = $query->posts[0]->ID;

		// Get request body
		$body = file_get_contents( 'php://input' );
		$data = json_decode( $body, true ) ?: [];

		$result = $this->run_pipeline( $pipeline_id, [
			'trigger_data' => $data,
		] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for running pipeline.
	 */
	public function ajax_run_pipeline() {
		check_ajax_referer( 'aimentor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$pipeline_id = isset( $_POST['pipeline_id'] ) ? (int) $_POST['pipeline_id'] : 0;

		if ( ! $pipeline_id ) {
			wp_send_json_error( [ 'message' => 'Invalid pipeline ID' ], 400 );
		}

		$result = $this->run_pipeline( $pipeline_id, [ 'force' => true ] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Log pipeline execution.
	 */
	protected function log_execution( $pipeline_id, $results ) {
		$logs = get_post_meta( $pipeline_id, '_pipeline_logs', true ) ?: [];

		// Keep last 50 logs
		$logs = array_slice( $logs, -49 );
		$logs[] = $results;

		update_post_meta( $pipeline_id, '_pipeline_logs', $logs );
	}

	/**
	 * Get pipeline execution logs.
	 *
	 * @param int $pipeline_id Pipeline ID.
	 * @param int $limit       Number of logs to return.
	 * @return array Execution logs.
	 */
	public function get_logs( $pipeline_id, $limit = 10 ) {
		$logs = get_post_meta( $pipeline_id, '_pipeline_logs', true ) ?: [];
		return array_slice( array_reverse( $logs ), 0, $limit );
	}

	/**
	 * Get webhook URL for a pipeline.
	 *
	 * @param int $pipeline_id Pipeline ID.
	 * @return string Webhook URL.
	 */
	public function get_webhook_url( $pipeline_id ) {
		$webhook_key = get_post_meta( $pipeline_id, '_pipeline_webhook_key', true );

		return add_query_arg( [
			'action' => 'aimentor_pipeline_webhook',
			'key'    => $webhook_key,
		], admin_url( 'admin-ajax.php' ) );
	}
}

// Initialize
new AiMentor_Content_Pipeline();
