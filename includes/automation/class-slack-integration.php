<?php
/**
 * Slack Integration
 *
 * Send notifications and content to Slack channels.
 *
 * @package AiMentor
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Slack_Integration {

	/**
	 * Option key for Slack settings.
	 */
	const SETTINGS_KEY = 'aimentor_slack_settings';

	/**
	 * Notification types.
	 */
	const NOTIFICATION_TYPES = [
		'content_generated'    => [
			'label'       => 'Content Generated',
			'description' => 'When AI content is generated',
			'default'     => false,
		],
		'pipeline_completed'   => [
			'label'       => 'Pipeline Completed',
			'description' => 'When a pipeline run completes',
			'default'     => true,
		],
		'pipeline_failed'      => [
			'label'       => 'Pipeline Failed',
			'description' => 'When a pipeline run fails',
			'default'     => true,
		],
		'scheduled_completed'  => [
			'label'       => 'Scheduled Content Ready',
			'description' => 'When scheduled content is generated',
			'default'     => true,
		],
		'scheduled_failed'     => [
			'label'       => 'Scheduled Content Failed',
			'description' => 'When scheduled content generation fails',
			'default'     => true,
		],
		'api_error'            => [
			'label'       => 'API Errors',
			'description' => 'When AI provider API errors occur',
			'default'     => true,
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
		// Hook into AiMentor events
		add_action( 'aimentor_content_generated', [ $this, 'on_content_generated' ], 10, 2 );
		add_action( 'aimentor_pipeline_executed', [ $this, 'on_pipeline_executed' ], 10, 2 );
		add_action( 'aimentor_scheduled_content_completed', [ $this, 'on_scheduled_completed' ], 10, 2 );
		add_action( 'aimentor_scheduled_content_failed', [ $this, 'on_scheduled_failed' ], 10, 2 );
		add_action( 'aimentor_api_error', [ $this, 'on_api_error' ], 10, 2 );

		// AJAX handlers
		add_action( 'wp_ajax_aimentor_slack_test', [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_aimentor_slack_send', [ $this, 'ajax_send_message' ] );
	}

	/**
	 * Get Slack settings.
	 *
	 * @return array Settings.
	 */
	public function get_settings() {
		$defaults = [
			'webhook_url'        => '',
			'default_channel'    => '',
			'bot_name'           => 'AiMentor',
			'bot_icon'           => ':robot_face:',
			'notifications'      => [],
			'include_site_info'  => true,
		];

		$settings = get_option( self::SETTINGS_KEY, [] );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update Slack settings.
	 *
	 * @param array $settings New settings.
	 * @return bool Success.
	 */
	public function update_settings( $settings ) {
		$current = $this->get_settings();
		$updated = wp_parse_args( $settings, $current );

		// Sanitize
		$updated['webhook_url']     = esc_url_raw( $updated['webhook_url'] );
		$updated['default_channel'] = sanitize_text_field( $updated['default_channel'] );
		$updated['bot_name']        = sanitize_text_field( $updated['bot_name'] );
		$updated['bot_icon']        = sanitize_text_field( $updated['bot_icon'] );

		return update_option( self::SETTINGS_KEY, $updated );
	}

	/**
	 * Check if Slack is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		$settings = $this->get_settings();
		return ! empty( $settings['webhook_url'] );
	}

	/**
	 * Check if a notification type is enabled.
	 *
	 * @param string $type Notification type.
	 * @return bool
	 */
	public function is_notification_enabled( $type ) {
		$settings = $this->get_settings();

		if ( isset( $settings['notifications'][ $type ] ) ) {
			return (bool) $settings['notifications'][ $type ];
		}

		// Return default if not set
		return self::NOTIFICATION_TYPES[ $type ]['default'] ?? false;
	}

	/**
	 * Send a message to Slack.
	 *
	 * @param string $message Message text.
	 * @param string $channel Optional channel override.
	 * @param array  $options Additional options (attachments, blocks, etc.).
	 * @return bool|WP_Error Success or error.
	 */
	public function send_message( $message, $channel = '', $options = [] ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Slack is not configured.', 'aimentor' ) );
		}

		$settings = $this->get_settings();

		$payload = [
			'text'       => $message,
			'username'   => $settings['bot_name'],
			'icon_emoji' => $settings['bot_icon'],
		];

		// Add channel if specified
		if ( ! empty( $channel ) ) {
			$payload['channel'] = $channel;
		} elseif ( ! empty( $settings['default_channel'] ) ) {
			$payload['channel'] = $settings['default_channel'];
		}

		// Add attachments if provided
		if ( ! empty( $options['attachments'] ) ) {
			$payload['attachments'] = $options['attachments'];
		}

		// Add blocks if provided
		if ( ! empty( $options['blocks'] ) ) {
			$payload['blocks'] = $options['blocks'];
		}

		// Add site info if enabled
		if ( $settings['include_site_info'] && empty( $options['skip_site_info'] ) ) {
			$payload['attachments']   = $payload['attachments'] ?? [];
			$payload['attachments'][] = [
				'fallback' => get_bloginfo( 'name' ),
				'footer'   => get_bloginfo( 'name' ) . ' | ' . home_url(),
				'ts'       => time(),
			];
		}

		$response = wp_remote_post( $settings['webhook_url'], [
			'body'    => wp_json_encode( $payload ),
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code !== 200 ) {
			return new WP_Error(
				'slack_error',
				sprintf( __( 'Slack returned status %d', 'aimentor' ), $status_code )
			);
		}

		return true;
	}

	/**
	 * Send a rich notification with blocks.
	 *
	 * @param array $notification Notification data.
	 * @return bool|WP_Error Success or error.
	 */
	public function send_notification( $notification ) {
		$defaults = [
			'title'       => '',
			'message'     => '',
			'color'       => '#3788d8',
			'fields'      => [],
			'actions'     => [],
			'channel'     => '',
		];

		$notification = wp_parse_args( $notification, $defaults );

		$blocks = [];

		// Header
		if ( ! empty( $notification['title'] ) ) {
			$blocks[] = [
				'type' => 'header',
				'text' => [
					'type'  => 'plain_text',
					'text'  => $notification['title'],
					'emoji' => true,
				],
			];
		}

		// Main message
		if ( ! empty( $notification['message'] ) ) {
			$blocks[] = [
				'type' => 'section',
				'text' => [
					'type' => 'mrkdwn',
					'text' => $notification['message'],
				],
			];
		}

		// Fields
		if ( ! empty( $notification['fields'] ) ) {
			$field_elements = [];
			foreach ( $notification['fields'] as $field ) {
				$field_elements[] = [
					'type' => 'mrkdwn',
					'text' => "*{$field['title']}*\n{$field['value']}",
				];
			}

			$blocks[] = [
				'type'   => 'section',
				'fields' => $field_elements,
			];
		}

		// Divider
		if ( ! empty( $notification['fields'] ) && ! empty( $notification['actions'] ) ) {
			$blocks[] = [ 'type' => 'divider' ];
		}

		// Actions (buttons)
		if ( ! empty( $notification['actions'] ) ) {
			$action_elements = [];
			foreach ( $notification['actions'] as $action ) {
				$action_elements[] = [
					'type' => 'button',
					'text' => [
						'type'  => 'plain_text',
						'text'  => $action['text'],
						'emoji' => true,
					],
					'url'   => $action['url'],
					'style' => $action['style'] ?? 'primary',
				];
			}

			$blocks[] = [
				'type'     => 'actions',
				'elements' => $action_elements,
			];
		}

		// Context (timestamp)
		$blocks[] = [
			'type'     => 'context',
			'elements' => [
				[
					'type' => 'mrkdwn',
					'text' => '📅 ' . current_time( 'F j, Y g:i A' ),
				],
			],
		];

		// Build attachment for color
		$attachments = [
			[
				'color'  => $notification['color'],
				'blocks' => $blocks,
			],
		];

		return $this->send_message( '', $notification['channel'], [
			'attachments'    => $attachments,
			'skip_site_info' => true,
		] );
	}

	/**
	 * Test Slack connection.
	 *
	 * @return bool|WP_Error Success or error.
	 */
	public function test_connection() {
		return $this->send_message(
			'🔗 AiMentor Slack integration test successful!',
			'',
			[ 'skip_site_info' => false ]
		);
	}

	// Event handlers

	/**
	 * Handle content generated event.
	 */
	public function on_content_generated( $content, $context ) {
		if ( ! $this->is_notification_enabled( 'content_generated' ) ) {
			return;
		}

		$word_count = is_string( $content ) ? str_word_count( $content ) : 0;
		$preview    = is_string( $content ) ? wp_trim_words( $content, 30 ) : 'Complex content';

		$this->send_notification( [
			'title'   => '✍️ Content Generated',
			'message' => $preview,
			'color'   => '#27ae60',
			'fields'  => [
				[
					'title' => 'Word Count',
					'value' => number_format( $word_count ),
				],
				[
					'title' => 'Provider',
					'value' => ucfirst( $context['provider'] ?? 'Unknown' ),
				],
			],
		] );
	}

	/**
	 * Handle pipeline executed event.
	 */
	public function on_pipeline_executed( $pipeline_id, $results ) {
		$success = $results['success'] ?? false;
		$type    = $success ? 'pipeline_completed' : 'pipeline_failed';

		if ( ! $this->is_notification_enabled( $type ) ) {
			return;
		}

		$pipeline_name = 'Pipeline #' . $pipeline_id;
		if ( class_exists( 'AiMentor_Content_Pipeline' ) ) {
			$pipeline_manager = new AiMentor_Content_Pipeline();
			$pipeline = $pipeline_manager->get_pipeline( $pipeline_id );
			if ( $pipeline ) {
				$pipeline_name = $pipeline['name'];
			}
		}

		$actions_completed = count( array_filter( $results['actions'] ?? [], function( $a ) {
			return $a['success'] ?? false;
		} ) );
		$actions_total = count( $results['actions'] ?? [] );

		if ( $success ) {
			$this->send_notification( [
				'title'   => '✅ Pipeline Completed',
				'message' => "Pipeline *{$pipeline_name}* completed successfully.",
				'color'   => '#27ae60',
				'fields'  => [
					[
						'title' => 'Actions',
						'value' => "{$actions_completed}/{$actions_total} completed",
					],
					[
						'title' => 'Duration',
						'value' => $this->format_duration( $results['started_at'], $results['completed_at'] ),
					],
				],
				'actions' => [
					[
						'text' => 'View Pipeline',
						'url'  => admin_url( 'edit.php?post_type=aimentor_pipeline' ),
					],
				],
			] );
		} else {
			$failed_actions = array_filter( $results['actions'] ?? [], function( $a ) {
				return ! ( $a['success'] ?? true );
			} );
			$first_error = reset( $failed_actions );

			$this->send_notification( [
				'title'   => '❌ Pipeline Failed',
				'message' => "Pipeline *{$pipeline_name}* failed to complete.",
				'color'   => '#e74c3c',
				'fields'  => [
					[
						'title' => 'Actions',
						'value' => "{$actions_completed}/{$actions_total} completed",
					],
					[
						'title' => 'Error',
						'value' => $first_error['result'] ?? 'Unknown error',
					],
				],
				'actions' => [
					[
						'text'  => 'View Logs',
						'url'   => admin_url( 'edit.php?post_type=aimentor_pipeline' ),
						'style' => 'danger',
					],
				],
			] );
		}
	}

	/**
	 * Handle scheduled content completed event.
	 */
	public function on_scheduled_completed( $item_id, $post_id ) {
		if ( ! $this->is_notification_enabled( 'scheduled_completed' ) ) {
			return;
		}

		$post = get_post( $post_id );
		$title = $post ? $post->post_title : "Content #{$post_id}";

		$this->send_notification( [
			'title'   => '📅 Scheduled Content Ready',
			'message' => "Scheduled content *{$title}* has been generated and is ready for review.",
			'color'   => '#27ae60',
			'fields'  => [
				[
					'title' => 'Status',
					'value' => ucfirst( $post->post_status ?? 'draft' ),
				],
				[
					'title' => 'Type',
					'value' => ucfirst( $post->post_type ?? 'post' ),
				],
			],
			'actions' => [
				[
					'text' => 'Edit Content',
					'url'  => get_edit_post_link( $post_id, 'raw' ),
				],
				[
					'text'  => 'View Content',
					'url'   => get_permalink( $post_id ),
					'style' => 'primary',
				],
			],
		] );
	}

	/**
	 * Handle scheduled content failed event.
	 */
	public function on_scheduled_failed( $item_id, $error_message ) {
		if ( ! $this->is_notification_enabled( 'scheduled_failed' ) ) {
			return;
		}

		$title = "Scheduled Item #{$item_id}";
		if ( class_exists( 'AiMentor_Content_Calendar' ) ) {
			$calendar = new AiMentor_Content_Calendar();
			$item = $calendar->get_scheduled( $item_id );
			if ( $item ) {
				$title = $item['title'];
			}
		}

		$this->send_notification( [
			'title'   => '❌ Scheduled Content Failed',
			'message' => "Scheduled content *{$title}* failed to generate.",
			'color'   => '#e74c3c',
			'fields'  => [
				[
					'title' => 'Error',
					'value' => $error_message,
				],
			],
			'actions' => [
				[
					'text'  => 'View Calendar',
					'url'   => admin_url( 'admin.php?page=aimentor-calendar' ),
					'style' => 'danger',
				],
			],
		] );
	}

	/**
	 * Handle API error event.
	 */
	public function on_api_error( $error, $context ) {
		if ( ! $this->is_notification_enabled( 'api_error' ) ) {
			return;
		}

		$error_message = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;

		$this->send_notification( [
			'title'   => '⚠️ AI Provider Error',
			'message' => 'An error occurred while communicating with the AI provider.',
			'color'   => '#f39c12',
			'fields'  => [
				[
					'title' => 'Provider',
					'value' => ucfirst( $context['provider'] ?? 'Unknown' ),
				],
				[
					'title' => 'Error',
					'value' => wp_trim_words( $error_message, 30 ),
				],
			],
			'actions' => [
				[
					'text' => 'Check Settings',
					'url'  => admin_url( 'admin.php?page=aimentor-settings' ),
				],
			],
		] );
	}

	/**
	 * Format duration between two timestamps.
	 *
	 * @param string $start Start time.
	 * @param string $end   End time.
	 * @return string Formatted duration.
	 */
	protected function format_duration( $start, $end ) {
		$start_time = strtotime( $start );
		$end_time   = strtotime( $end );
		$diff       = $end_time - $start_time;

		if ( $diff < 60 ) {
			return $diff . ' seconds';
		} elseif ( $diff < 3600 ) {
			return round( $diff / 60 ) . ' minutes';
		} else {
			return round( $diff / 3600, 1 ) . ' hours';
		}
	}

	// AJAX handlers

	/**
	 * AJAX: Test Slack connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'aimentor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$result = $this->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		wp_send_json_success( [ 'message' => 'Connection successful!' ] );
	}

	/**
	 * AJAX: Send custom message.
	 */
	public function ajax_send_message() {
		check_ajax_referer( 'aimentor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';
		$channel = isset( $_POST['channel'] ) ? sanitize_text_field( $_POST['channel'] ) : '';

		if ( empty( $message ) ) {
			wp_send_json_error( [ 'message' => 'Message is required' ], 400 );
		}

		$result = $this->send_message( $message, $channel );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		wp_send_json_success( [ 'message' => 'Message sent!' ] );
	}
}

// Initialize
new AiMentor_Slack_Integration();
