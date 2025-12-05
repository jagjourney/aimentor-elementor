<?php
/**
 * Content Calendar
 *
 * Schedule and manage AI content generation with a visual calendar.
 *
 * @package AiMentor
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Content_Calendar {

	/**
	 * Scheduled content post type.
	 */
	const POST_TYPE = 'aimentor_scheduled';

	/**
	 * Cron hook for processing scheduled content.
	 */
	const CRON_HOOK = 'aimentor_process_scheduled_content';

	/**
	 * Content statuses.
	 */
	const STATUS_SCHEDULED = 'scheduled';
	const STATUS_PROCESSING = 'processing';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED = 'failed';
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Content types.
	 */
	const CONTENT_TYPES = [
		'blog_post'   => [
			'label'       => 'Blog Post',
			'icon'        => 'admin-post',
			'post_type'   => 'post',
		],
		'page'        => [
			'label'       => 'Page',
			'icon'        => 'admin-page',
			'post_type'   => 'page',
		],
		'social'      => [
			'label'       => 'Social Media',
			'icon'        => 'share',
			'post_type'   => null,
		],
		'newsletter'  => [
			'label'       => 'Newsletter',
			'icon'        => 'email',
			'post_type'   => null,
		],
		'product'     => [
			'label'       => 'Product Description',
			'icon'        => 'cart',
			'post_type'   => 'product',
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
		add_action( self::CRON_HOOK, [ $this, 'process_scheduled_content' ] );

		// AJAX handlers
		add_action( 'wp_ajax_aimentor_calendar_get_events', [ $this, 'ajax_get_events' ] );
		add_action( 'wp_ajax_aimentor_calendar_create_event', [ $this, 'ajax_create_event' ] );
		add_action( 'wp_ajax_aimentor_calendar_update_event', [ $this, 'ajax_update_event' ] );
		add_action( 'wp_ajax_aimentor_calendar_delete_event', [ $this, 'ajax_delete_event' ] );
		add_action( 'wp_ajax_aimentor_calendar_reschedule', [ $this, 'ajax_reschedule' ] );

		// Schedule cron if not already scheduled
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'five_minutes', self::CRON_HOOK );
		}

		// Register custom cron interval
		add_filter( 'cron_schedules', [ $this, 'add_cron_interval' ] );
	}

	/**
	 * Add custom cron interval.
	 */
	public function add_cron_interval( $schedules ) {
		$schedules['five_minutes'] = [
			'interval' => 300,
			'display'  => __( 'Every Five Minutes', 'aimentor' ),
		];
		return $schedules;
	}

	/**
	 * Register scheduled content post type.
	 */
	public function register_post_type() {
		register_post_type( self::POST_TYPE, [
			'labels'              => [
				'name'          => __( 'Scheduled Content', 'aimentor' ),
				'singular_name' => __( 'Scheduled Content', 'aimentor' ),
			],
			'public'              => false,
			'show_ui'             => false,
			'supports'            => [ 'title' ],
			'capability_type'     => 'post',
		] );
	}

	/**
	 * Schedule content generation.
	 *
	 * @param array $data Content configuration.
	 * @return int|WP_Error Scheduled content ID or error.
	 */
	public function schedule_content( $data ) {
		$defaults = [
			'title'          => '',
			'content_type'   => 'blog_post',
			'scheduled_date' => '',
			'prompt'         => '',
			'options'        => [],
			'publish_status' => 'draft',
			'categories'     => [],
			'tags'           => [],
			'author'         => get_current_user_id(),
		];

		$data = wp_parse_args( $data, $defaults );

		// Validate
		if ( empty( $data['prompt'] ) ) {
			return new WP_Error( 'no_prompt', __( 'Content prompt is required.', 'aimentor' ) );
		}

		if ( empty( $data['scheduled_date'] ) ) {
			return new WP_Error( 'no_date', __( 'Scheduled date is required.', 'aimentor' ) );
		}

		$scheduled_time = strtotime( $data['scheduled_date'] );

		if ( ! $scheduled_time || $scheduled_time < time() - 60 ) {
			return new WP_Error( 'invalid_date', __( 'Scheduled date must be in the future.', 'aimentor' ) );
		}

		// Create scheduled content entry
		$post_id = wp_insert_post( [
			'post_type'   => self::POST_TYPE,
			'post_title'  => sanitize_text_field( $data['title'] ) ?: __( 'Scheduled Content', 'aimentor' ),
			'post_status' => 'publish',
			'meta_input'  => [
				'_scheduled_date'    => $data['scheduled_date'],
				'_content_type'      => $data['content_type'],
				'_generation_prompt' => $data['prompt'],
				'_generation_options' => $data['options'],
				'_publish_status'    => $data['publish_status'],
				'_categories'        => $data['categories'],
				'_tags'              => $data['tags'],
				'_author'            => $data['author'],
				'_calendar_status'   => self::STATUS_SCHEDULED,
				'_created_by'        => get_current_user_id(),
			],
		] );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		do_action( 'aimentor_content_scheduled', $post_id, $data );

		return $post_id;
	}

	/**
	 * Update scheduled content.
	 *
	 * @param int   $item_id Scheduled content ID.
	 * @param array $data    Updated data.
	 * @return bool|WP_Error Success or error.
	 */
	public function update_scheduled( $item_id, $data ) {
		$post = get_post( $item_id );

		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return new WP_Error( 'not_found', __( 'Scheduled content not found.', 'aimentor' ) );
		}

		$status = get_post_meta( $item_id, '_calendar_status', true );

		if ( $status !== self::STATUS_SCHEDULED ) {
			return new WP_Error( 'cannot_update', __( 'Cannot update content that is already processing or completed.', 'aimentor' ) );
		}

		// Update title
		if ( isset( $data['title'] ) ) {
			wp_update_post( [
				'ID'         => $item_id,
				'post_title' => sanitize_text_field( $data['title'] ),
			] );
		}

		// Update meta fields
		$meta_fields = [
			'scheduled_date'    => '_scheduled_date',
			'content_type'      => '_content_type',
			'prompt'            => '_generation_prompt',
			'options'           => '_generation_options',
			'publish_status'    => '_publish_status',
			'categories'        => '_categories',
			'tags'              => '_tags',
		];

		foreach ( $meta_fields as $key => $meta_key ) {
			if ( isset( $data[ $key ] ) ) {
				update_post_meta( $item_id, $meta_key, $data[ $key ] );
			}
		}

		do_action( 'aimentor_scheduled_content_updated', $item_id, $data );

		return true;
	}

	/**
	 * Cancel scheduled content.
	 *
	 * @param int $item_id Scheduled content ID.
	 * @return bool|WP_Error Success or error.
	 */
	public function cancel_scheduled( $item_id ) {
		$status = get_post_meta( $item_id, '_calendar_status', true );

		if ( $status === self::STATUS_PROCESSING ) {
			return new WP_Error( 'processing', __( 'Cannot cancel content that is currently processing.', 'aimentor' ) );
		}

		if ( $status === self::STATUS_COMPLETED ) {
			return new WP_Error( 'completed', __( 'Content has already been generated.', 'aimentor' ) );
		}

		update_post_meta( $item_id, '_calendar_status', self::STATUS_CANCELLED );

		do_action( 'aimentor_scheduled_content_cancelled', $item_id );

		return true;
	}

	/**
	 * Get scheduled content by ID.
	 *
	 * @param int $item_id Scheduled content ID.
	 * @return array|null Content data or null.
	 */
	public function get_scheduled( $item_id ) {
		$post = get_post( $item_id );

		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return null;
		}

		return $this->format_scheduled_item( $post );
	}

	/**
	 * Get scheduled content for date range.
	 *
	 * @param string $start Start date (Y-m-d).
	 * @param string $end   End date (Y-m-d).
	 * @param array  $args  Additional query args.
	 * @return array Scheduled content items.
	 */
	public function get_scheduled_range( $start, $end, $args = [] ) {
		$query_args = [
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => '_scheduled_date',
					'value'   => [ $start . ' 00:00:00', $end . ' 23:59:59' ],
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				],
			],
			'orderby'        => 'meta_value',
			'meta_key'       => '_scheduled_date',
			'order'          => 'ASC',
		];

		// Filter by status
		if ( ! empty( $args['status'] ) ) {
			$query_args['meta_query'][] = [
				'key'   => '_calendar_status',
				'value' => $args['status'],
			];
		}

		// Filter by content type
		if ( ! empty( $args['content_type'] ) ) {
			$query_args['meta_query'][] = [
				'key'   => '_content_type',
				'value' => $args['content_type'],
			];
		}

		$query = new WP_Query( $query_args );
		$items = [];

		foreach ( $query->posts as $post ) {
			$items[] = $this->format_scheduled_item( $post );
		}

		return $items;
	}

	/**
	 * Get calendar events for display.
	 *
	 * @param string $start Start date.
	 * @param string $end   End date.
	 * @return array Calendar events.
	 */
	public function get_calendar_events( $start, $end ) {
		$items  = $this->get_scheduled_range( $start, $end );
		$events = [];

		foreach ( $items as $item ) {
			$type_info = self::CONTENT_TYPES[ $item['content_type'] ] ?? [];

			$events[] = [
				'id'              => $item['id'],
				'title'           => $item['title'],
				'start'           => $item['scheduled_date'],
				'allDay'          => false,
				'extendedProps'   => [
					'content_type'   => $item['content_type'],
					'status'         => $item['status'],
					'prompt'         => $item['prompt'],
					'result_post_id' => $item['result_post_id'],
				],
				'backgroundColor' => $this->get_status_color( $item['status'] ),
				'borderColor'     => $this->get_status_color( $item['status'] ),
			];
		}

		return $events;
	}

	/**
	 * Format scheduled item for output.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Formatted item.
	 */
	protected function format_scheduled_item( $post ) {
		return [
			'id'              => $post->ID,
			'title'           => $post->post_title,
			'scheduled_date'  => get_post_meta( $post->ID, '_scheduled_date', true ),
			'content_type'    => get_post_meta( $post->ID, '_content_type', true ),
			'prompt'          => get_post_meta( $post->ID, '_generation_prompt', true ),
			'options'         => get_post_meta( $post->ID, '_generation_options', true ),
			'publish_status'  => get_post_meta( $post->ID, '_publish_status', true ),
			'categories'      => get_post_meta( $post->ID, '_categories', true ),
			'tags'            => get_post_meta( $post->ID, '_tags', true ),
			'author'          => get_post_meta( $post->ID, '_author', true ),
			'status'          => get_post_meta( $post->ID, '_calendar_status', true ),
			'result_post_id'  => get_post_meta( $post->ID, '_result_post_id', true ),
			'error_message'   => get_post_meta( $post->ID, '_error_message', true ),
			'created'         => $post->post_date,
			'created_by'      => get_post_meta( $post->ID, '_created_by', true ),
		];
	}

	/**
	 * Get status color for calendar display.
	 *
	 * @param string $status Content status.
	 * @return string Hex color.
	 */
	protected function get_status_color( $status ) {
		$colors = [
			self::STATUS_SCHEDULED  => '#3788d8',
			self::STATUS_PROCESSING => '#f39c12',
			self::STATUS_COMPLETED  => '#27ae60',
			self::STATUS_FAILED     => '#e74c3c',
			self::STATUS_CANCELLED  => '#95a5a6',
		];

		return $colors[ $status ] ?? '#3788d8';
	}

	/**
	 * Process scheduled content (cron handler).
	 */
	public function process_scheduled_content() {
		$now = current_time( 'mysql' );

		$query = new WP_Query( [
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => 10,
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'     => '_scheduled_date',
					'value'   => $now,
					'compare' => '<=',
					'type'    => 'DATETIME',
				],
				[
					'key'   => '_calendar_status',
					'value' => self::STATUS_SCHEDULED,
				],
			],
		] );

		foreach ( $query->posts as $post ) {
			$this->generate_scheduled_content( $post->ID );
		}
	}

	/**
	 * Generate content for a scheduled item.
	 *
	 * @param int $item_id Scheduled content ID.
	 * @return array|WP_Error Generation result or error.
	 */
	public function generate_scheduled_content( $item_id ) {
		$item = $this->get_scheduled( $item_id );

		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Scheduled content not found.', 'aimentor' ) );
		}

		// Mark as processing
		update_post_meta( $item_id, '_calendar_status', self::STATUS_PROCESSING );
		update_post_meta( $item_id, '_processing_started', current_time( 'mysql' ) );

		try {
			// Generate content based on type
			$result = $this->execute_generation( $item );

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Create the actual post/content
			$post_id = $this->create_result_post( $item, $result );

			if ( is_wp_error( $post_id ) ) {
				throw new Exception( $post_id->get_error_message() );
			}

			// Mark as completed
			update_post_meta( $item_id, '_calendar_status', self::STATUS_COMPLETED );
			update_post_meta( $item_id, '_result_post_id', $post_id );
			update_post_meta( $item_id, '_completed_at', current_time( 'mysql' ) );

			do_action( 'aimentor_scheduled_content_completed', $item_id, $post_id );

			return [
				'success' => true,
				'post_id' => $post_id,
			];

		} catch ( Exception $e ) {
			update_post_meta( $item_id, '_calendar_status', self::STATUS_FAILED );
			update_post_meta( $item_id, '_error_message', $e->getMessage() );
			update_post_meta( $item_id, '_failed_at', current_time( 'mysql' ) );

			do_action( 'aimentor_scheduled_content_failed', $item_id, $e->getMessage() );

			return new WP_Error( 'generation_failed', $e->getMessage() );
		}
	}

	/**
	 * Execute content generation.
	 *
	 * @param array $item Scheduled item data.
	 * @return string|array|WP_Error Generated content or error.
	 */
	protected function execute_generation( $item ) {
		$prompt  = $item['prompt'];
		$options = $item['options'] ?: [];

		// Apply tone if specified
		if ( ! empty( $options['tone'] ) && class_exists( 'AiMentor_Tone_Profiles' ) ) {
			$tone_profiles = new AiMentor_Tone_Profiles();
			$prompt = $tone_profiles->apply_tone_to_prompt( $prompt, $options['tone'] );
		}

		// Apply language if specified
		if ( ! empty( $options['language'] ) && class_exists( 'AiMentor_Language_Support' ) ) {
			$language_support = new AiMentor_Language_Support();
			return $language_support->generate_in_language( $prompt, $options['language'] );
		}

		// Generate content
		if ( function_exists( 'aimentor_generate_with_fallback' ) ) {
			$task = $item['content_type'] === 'page' ? 'canvas' : 'copy';
			return aimentor_generate_with_fallback( $prompt, [ 'task' => $task ] );
		}

		return new WP_Error( 'no_provider', __( 'No AI provider configured.', 'aimentor' ) );
	}

	/**
	 * Create result post from generated content.
	 *
	 * @param array        $item    Scheduled item data.
	 * @param string|array $content Generated content.
	 * @return int|WP_Error Post ID or error.
	 */
	protected function create_result_post( $item, $content ) {
		$type_info = self::CONTENT_TYPES[ $item['content_type'] ] ?? [];
		$post_type = $type_info['post_type'] ?? 'post';

		// For non-post content types, just store the result
		if ( ! $post_type ) {
			return $this->store_non_post_result( $item, $content );
		}

		$post_data = [
			'post_title'   => $item['title'],
			'post_content' => is_string( $content ) ? $content : '',
			'post_status'  => $item['publish_status'] ?: 'draft',
			'post_type'    => $post_type,
			'post_author'  => $item['author'] ?: get_current_user_id(),
		];

		// For Elementor pages
		if ( $item['content_type'] === 'page' && is_array( $content ) ) {
			$post_data['post_content'] = '';
			$post_data['meta_input']   = [
				'_elementor_data'      => wp_json_encode( $content ),
				'_elementor_edit_mode' => 'builder',
			];
		}

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Add categories
		if ( ! empty( $item['categories'] ) && $post_type === 'post' ) {
			wp_set_post_categories( $post_id, $item['categories'] );
		}

		// Add tags
		if ( ! empty( $item['tags'] ) && $post_type === 'post' ) {
			wp_set_post_tags( $post_id, $item['tags'] );
		}

		// Generate SEO if option is set
		if ( ! empty( $item['options']['generate_seo'] ) && class_exists( 'AiMentor_SEO_Integration' ) ) {
			$seo = new AiMentor_SEO_Integration();
			$seo_data = $seo->generate_seo_meta( $post_id );
			if ( ! is_wp_error( $seo_data ) ) {
				$seo->apply_seo_meta( $post_id, $seo_data );
			}
		}

		// Generate featured image if option is set
		if ( ! empty( $item['options']['generate_image'] ) && class_exists( 'AiMentor_Image_Generator' ) ) {
			$image_generator = new AiMentor_Image_Generator();
			$image_prompt    = $item['options']['image_prompt'] ?? "Featured image for: {$item['title']}";
			$image_result    = $image_generator->generate( $image_prompt );

			if ( ! is_wp_error( $image_result ) && ! empty( $image_result['images'][0] ) ) {
				$attachment_id = $image_result['images'][0]['attachment_id'] ?? null;

				if ( ! $attachment_id && ! empty( $image_result['images'][0]['url'] ) ) {
					$download = $image_generator->download_to_media_library( $image_result['images'][0]['url'] );
					if ( ! is_wp_error( $download ) ) {
						$attachment_id = $download['attachment_id'];
					}
				}

				if ( $attachment_id ) {
					set_post_thumbnail( $post_id, $attachment_id );
				}
			}
		}

		return $post_id;
	}

	/**
	 * Store non-post result (social, newsletter, etc.).
	 *
	 * @param array        $item    Scheduled item data.
	 * @param string|array $content Generated content.
	 * @return int Scheduled item ID (as reference).
	 */
	protected function store_non_post_result( $item, $content ) {
		update_post_meta( $item['id'], '_generated_content', $content );
		return $item['id'];
	}

	/**
	 * Reschedule content.
	 *
	 * @param int    $item_id  Scheduled content ID.
	 * @param string $new_date New scheduled date.
	 * @return bool|WP_Error Success or error.
	 */
	public function reschedule( $item_id, $new_date ) {
		$status = get_post_meta( $item_id, '_calendar_status', true );

		if ( ! in_array( $status, [ self::STATUS_SCHEDULED, self::STATUS_FAILED ], true ) ) {
			return new WP_Error( 'cannot_reschedule', __( 'Cannot reschedule this content.', 'aimentor' ) );
		}

		$new_time = strtotime( $new_date );

		if ( ! $new_time || $new_time < time() - 60 ) {
			return new WP_Error( 'invalid_date', __( 'New date must be in the future.', 'aimentor' ) );
		}

		update_post_meta( $item_id, '_scheduled_date', $new_date );

		// Reset to scheduled status if it was failed
		if ( $status === self::STATUS_FAILED ) {
			update_post_meta( $item_id, '_calendar_status', self::STATUS_SCHEDULED );
			delete_post_meta( $item_id, '_error_message' );
		}

		do_action( 'aimentor_content_rescheduled', $item_id, $new_date );

		return true;
	}

	/**
	 * Get calendar statistics.
	 *
	 * @param string $period Period (week, month, year).
	 * @return array Statistics.
	 */
	public function get_statistics( $period = 'month' ) {
		$now = current_time( 'timestamp' );

		switch ( $period ) {
			case 'week':
				$start = date( 'Y-m-d', strtotime( 'monday this week', $now ) );
				$end   = date( 'Y-m-d', strtotime( 'sunday this week', $now ) );
				break;
			case 'year':
				$start = date( 'Y-01-01', $now );
				$end   = date( 'Y-12-31', $now );
				break;
			case 'month':
			default:
				$start = date( 'Y-m-01', $now );
				$end   = date( 'Y-m-t', $now );
				break;
		}

		$items = $this->get_scheduled_range( $start, $end );

		$stats = [
			'total'      => count( $items ),
			'scheduled'  => 0,
			'processing' => 0,
			'completed'  => 0,
			'failed'     => 0,
			'cancelled'  => 0,
			'by_type'    => [],
		];

		foreach ( $items as $item ) {
			$status = $item['status'];
			if ( isset( $stats[ $status ] ) ) {
				$stats[ $status ]++;
			}

			$type = $item['content_type'];
			if ( ! isset( $stats['by_type'][ $type ] ) ) {
				$stats['by_type'][ $type ] = 0;
			}
			$stats['by_type'][ $type ]++;
		}

		return $stats;
	}

	/**
	 * Duplicate scheduled content.
	 *
	 * @param int    $item_id  Original item ID.
	 * @param string $new_date New scheduled date.
	 * @return int|WP_Error New item ID or error.
	 */
	public function duplicate( $item_id, $new_date ) {
		$item = $this->get_scheduled( $item_id );

		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Scheduled content not found.', 'aimentor' ) );
		}

		return $this->schedule_content( [
			'title'          => $item['title'] . ' (Copy)',
			'content_type'   => $item['content_type'],
			'scheduled_date' => $new_date,
			'prompt'         => $item['prompt'],
			'options'        => $item['options'],
			'publish_status' => $item['publish_status'],
			'categories'     => $item['categories'],
			'tags'           => $item['tags'],
		] );
	}

	/**
	 * Bulk schedule content from template.
	 *
	 * @param array $template Base template configuration.
	 * @param array $dates    Array of dates to schedule.
	 * @return array Created item IDs.
	 */
	public function bulk_schedule( $template, $dates ) {
		$created = [];

		foreach ( $dates as $index => $date ) {
			$data = array_merge( $template, [
				'scheduled_date' => $date,
				'title'          => sprintf( '%s #%d', $template['title'] ?? 'Content', $index + 1 ),
			] );

			$result = $this->schedule_content( $data );

			if ( ! is_wp_error( $result ) ) {
				$created[] = $result;
			}
		}

		return $created;
	}

	// AJAX Handlers

	/**
	 * AJAX: Get calendar events.
	 */
	public function ajax_get_events() {
		check_ajax_referer( 'aimentor_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$start = isset( $_GET['start'] ) ? sanitize_text_field( $_GET['start'] ) : date( 'Y-m-01' );
		$end   = isset( $_GET['end'] ) ? sanitize_text_field( $_GET['end'] ) : date( 'Y-m-t' );

		$events = $this->get_calendar_events( $start, $end );

		wp_send_json_success( $events );
	}

	/**
	 * AJAX: Create calendar event.
	 */
	public function ajax_create_event() {
		check_ajax_referer( 'aimentor_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$data = [
			'title'          => isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '',
			'content_type'   => isset( $_POST['content_type'] ) ? sanitize_key( $_POST['content_type'] ) : 'blog_post',
			'scheduled_date' => isset( $_POST['scheduled_date'] ) ? sanitize_text_field( $_POST['scheduled_date'] ) : '',
			'prompt'         => isset( $_POST['prompt'] ) ? sanitize_textarea_field( $_POST['prompt'] ) : '',
			'options'        => isset( $_POST['options'] ) ? (array) $_POST['options'] : [],
			'publish_status' => isset( $_POST['publish_status'] ) ? sanitize_key( $_POST['publish_status'] ) : 'draft',
		];

		$result = $this->schedule_content( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}

		wp_send_json_success( [
			'id'   => $result,
			'item' => $this->get_scheduled( $result ),
		] );
	}

	/**
	 * AJAX: Update calendar event.
	 */
	public function ajax_update_event() {
		check_ajax_referer( 'aimentor_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$item_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( ! $item_id ) {
			wp_send_json_error( [ 'message' => 'Invalid ID' ], 400 );
		}

		$data = [];

		if ( isset( $_POST['title'] ) ) {
			$data['title'] = sanitize_text_field( $_POST['title'] );
		}
		if ( isset( $_POST['scheduled_date'] ) ) {
			$data['scheduled_date'] = sanitize_text_field( $_POST['scheduled_date'] );
		}
		if ( isset( $_POST['prompt'] ) ) {
			$data['prompt'] = sanitize_textarea_field( $_POST['prompt'] );
		}
		if ( isset( $_POST['options'] ) ) {
			$data['options'] = (array) $_POST['options'];
		}

		$result = $this->update_scheduled( $item_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}

		wp_send_json_success( [
			'item' => $this->get_scheduled( $item_id ),
		] );
	}

	/**
	 * AJAX: Delete calendar event.
	 */
	public function ajax_delete_event() {
		check_ajax_referer( 'aimentor_nonce', 'nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$item_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( ! $item_id ) {
			wp_send_json_error( [ 'message' => 'Invalid ID' ], 400 );
		}

		$result = $this->cancel_scheduled( $item_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}

		wp_send_json_success( [ 'deleted' => true ] );
	}

	/**
	 * AJAX: Reschedule event.
	 */
	public function ajax_reschedule() {
		check_ajax_referer( 'aimentor_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$item_id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$new_date = isset( $_POST['new_date'] ) ? sanitize_text_field( $_POST['new_date'] ) : '';

		if ( ! $item_id || ! $new_date ) {
			wp_send_json_error( [ 'message' => 'Invalid parameters' ], 400 );
		}

		$result = $this->reschedule( $item_id, $new_date );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}

		wp_send_json_success( [
			'item' => $this->get_scheduled( $item_id ),
		] );
	}
}

// Initialize
new AiMentor_Content_Calendar();
