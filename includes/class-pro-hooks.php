<?php
/**
 * Pro Extensibility Hooks
 *
 * Provides action and filter hooks for AiMentor Pro add-on.
 *
 * @package AiMentor
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Pro_Hooks {

	/**
	 * Instance.
	 *
	 * @var AiMentor_Pro_Hooks|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return AiMentor_Pro_Hooks
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Generation hooks
		add_action( 'aimentor_before_generation', [ $this, 'before_generation' ], 10, 2 );
		add_action( 'aimentor_after_generation', [ $this, 'after_generation' ], 10, 3 );
		add_filter( 'aimentor_generation_prompt', [ $this, 'filter_prompt' ], 10, 2 );
		add_filter( 'aimentor_generation_result', [ $this, 'filter_result' ], 10, 3 );

		// Provider hooks
		add_filter( 'aimentor_available_providers', [ $this, 'filter_providers' ], 10, 1 );
		add_filter( 'aimentor_provider_settings', [ $this, 'filter_provider_settings' ], 10, 2 );

		// UI/Branding hooks
		add_filter( 'aimentor_branding', [ $this, 'filter_branding' ], 10, 1 );
		add_filter( 'aimentor_admin_menu', [ $this, 'filter_admin_menu' ], 10, 1 );
		add_filter( 'aimentor_widget_settings', [ $this, 'filter_widget_settings' ], 10, 1 );

		// Pipeline hooks
		add_filter( 'aimentor_pipeline_triggers', [ $this, 'filter_pipeline_triggers' ], 10, 1 );
		add_filter( 'aimentor_pipeline_actions', [ $this, 'filter_pipeline_actions' ], 10, 1 );
		add_action( 'aimentor_pipeline_before_run', [ $this, 'pipeline_before_run' ], 10, 2 );
		add_action( 'aimentor_pipeline_after_run', [ $this, 'pipeline_after_run' ], 10, 3 );

		// Template hooks
		add_filter( 'aimentor_section_templates', [ $this, 'filter_section_templates' ], 10, 1 );
		add_filter( 'aimentor_page_types', [ $this, 'filter_page_types' ], 10, 1 );

		// Analytics hooks
		add_action( 'aimentor_track_usage', [ $this, 'track_usage' ], 10, 3 );
		add_action( 'aimentor_track_generation', [ $this, 'track_generation' ], 10, 4 );

		// License hooks
		add_filter( 'aimentor_is_pro_active', [ $this, 'is_pro_active' ], 10, 1 );
		add_filter( 'aimentor_pro_features', [ $this, 'get_pro_features' ], 10, 1 );

		// Export/Import hooks
		add_filter( 'aimentor_export_data', [ $this, 'filter_export_data' ], 10, 2 );
		add_filter( 'aimentor_import_data', [ $this, 'filter_import_data' ], 10, 2 );

		// Team/Agency hooks
		add_filter( 'aimentor_user_capabilities', [ $this, 'filter_user_capabilities' ], 10, 2 );
		add_filter( 'aimentor_team_limits', [ $this, 'filter_team_limits' ], 10, 1 );
	}

	/**
	 * Fire before generation hook.
	 *
	 * @param string $prompt  Generation prompt.
	 * @param array  $context Generation context.
	 */
	public function before_generation( $prompt, $context ) {
		do_action( 'aimentor_pro_before_generation', $prompt, $context );
	}

	/**
	 * Fire after generation hook.
	 *
	 * @param mixed  $result   Generation result.
	 * @param string $prompt   Original prompt.
	 * @param array  $context  Generation context.
	 */
	public function after_generation( $result, $prompt, $context ) {
		do_action( 'aimentor_pro_after_generation', $result, $prompt, $context );

		// Track for analytics
		do_action( 'aimentor_track_generation', $result, $prompt, $context, [
			'provider'   => $context['provider'] ?? '',
			'task'       => $context['task'] ?? 'copy',
			'success'    => ! is_wp_error( $result ),
			'timestamp'  => current_time( 'mysql' ),
		] );
	}

	/**
	 * Filter generation prompt.
	 *
	 * @param string $prompt  Original prompt.
	 * @param array  $context Generation context.
	 * @return string Modified prompt.
	 */
	public function filter_prompt( $prompt, $context ) {
		return apply_filters( 'aimentor_pro_generation_prompt', $prompt, $context );
	}

	/**
	 * Filter generation result.
	 *
	 * @param mixed  $result  Generation result.
	 * @param string $prompt  Original prompt.
	 * @param array  $context Generation context.
	 * @return mixed Modified result.
	 */
	public function filter_result( $result, $prompt, $context ) {
		return apply_filters( 'aimentor_pro_generation_result', $result, $prompt, $context );
	}

	/**
	 * Filter available providers.
	 *
	 * @param array $providers Available providers.
	 * @return array Modified providers.
	 */
	public function filter_providers( $providers ) {
		return apply_filters( 'aimentor_pro_providers', $providers );
	}

	/**
	 * Filter provider settings.
	 *
	 * @param array  $settings Provider settings.
	 * @param string $provider Provider key.
	 * @return array Modified settings.
	 */
	public function filter_provider_settings( $settings, $provider ) {
		return apply_filters( 'aimentor_pro_provider_settings', $settings, $provider );
	}

	/**
	 * Filter branding.
	 *
	 * @param array $branding Branding configuration.
	 * @return array Modified branding.
	 */
	public function filter_branding( $branding ) {
		$defaults = [
			'name'        => 'AiMentor',
			'logo_url'    => '',
			'icon_url'    => '',
			'color'       => '#3788d8',
			'powered_by'  => true,
			'support_url' => 'https://jagjourney.com/',
		];

		$branding = wp_parse_args( $branding, $defaults );

		return apply_filters( 'aimentor_pro_branding', $branding );
	}

	/**
	 * Filter admin menu.
	 *
	 * @param array $menu Menu items.
	 * @return array Modified menu.
	 */
	public function filter_admin_menu( $menu ) {
		return apply_filters( 'aimentor_pro_admin_menu', $menu );
	}

	/**
	 * Filter widget settings.
	 *
	 * @param array $settings Widget settings.
	 * @return array Modified settings.
	 */
	public function filter_widget_settings( $settings ) {
		return apply_filters( 'aimentor_pro_widget_settings', $settings );
	}

	/**
	 * Filter pipeline triggers.
	 *
	 * @param array $triggers Available triggers.
	 * @return array Modified triggers.
	 */
	public function filter_pipeline_triggers( $triggers ) {
		return apply_filters( 'aimentor_pro_pipeline_triggers', $triggers );
	}

	/**
	 * Filter pipeline actions.
	 *
	 * @param array $actions Available actions.
	 * @return array Modified actions.
	 */
	public function filter_pipeline_actions( $actions ) {
		return apply_filters( 'aimentor_pro_pipeline_actions', $actions );
	}

	/**
	 * Fire before pipeline run.
	 *
	 * @param int   $pipeline_id Pipeline ID.
	 * @param array $context     Run context.
	 */
	public function pipeline_before_run( $pipeline_id, $context ) {
		do_action( 'aimentor_pro_pipeline_before_run', $pipeline_id, $context );
	}

	/**
	 * Fire after pipeline run.
	 *
	 * @param int   $pipeline_id Pipeline ID.
	 * @param array $results     Run results.
	 * @param array $context     Run context.
	 */
	public function pipeline_after_run( $pipeline_id, $results, $context ) {
		do_action( 'aimentor_pro_pipeline_after_run', $pipeline_id, $results, $context );
	}

	/**
	 * Filter section templates.
	 *
	 * @param array $templates Available templates.
	 * @return array Modified templates.
	 */
	public function filter_section_templates( $templates ) {
		return apply_filters( 'aimentor_pro_section_templates', $templates );
	}

	/**
	 * Filter page types.
	 *
	 * @param array $types Available page types.
	 * @return array Modified types.
	 */
	public function filter_page_types( $types ) {
		return apply_filters( 'aimentor_pro_page_types', $types );
	}

	/**
	 * Track usage event.
	 *
	 * @param string $event   Event name.
	 * @param array  $data    Event data.
	 * @param int    $user_id User ID.
	 */
	public function track_usage( $event, $data, $user_id = 0 ) {
		do_action( 'aimentor_pro_track_usage', $event, $data, $user_id ?: get_current_user_id() );
	}

	/**
	 * Track generation event.
	 *
	 * @param mixed  $result  Generation result.
	 * @param string $prompt  Prompt used.
	 * @param array  $context Generation context.
	 * @param array  $meta    Additional metadata.
	 */
	public function track_generation( $result, $prompt, $context, $meta ) {
		do_action( 'aimentor_pro_track_generation', $result, $prompt, $context, $meta );
	}

	/**
	 * Check if pro is active.
	 *
	 * @param bool $is_active Current status.
	 * @return bool Pro active status.
	 */
	public function is_pro_active( $is_active ) {
		return apply_filters( 'aimentor_pro_is_active', $is_active );
	}

	/**
	 * Get pro features list.
	 *
	 * @param array $features Current features.
	 * @return array Pro features.
	 */
	public function get_pro_features( $features ) {
		return apply_filters( 'aimentor_pro_features_list', $features );
	}

	/**
	 * Filter export data.
	 *
	 * @param array  $data Export data.
	 * @param string $type Export type.
	 * @return array Modified data.
	 */
	public function filter_export_data( $data, $type ) {
		return apply_filters( 'aimentor_pro_export_data', $data, $type );
	}

	/**
	 * Filter import data.
	 *
	 * @param array  $data Import data.
	 * @param string $type Import type.
	 * @return array Modified data.
	 */
	public function filter_import_data( $data, $type ) {
		return apply_filters( 'aimentor_pro_import_data', $data, $type );
	}

	/**
	 * Filter user capabilities.
	 *
	 * @param array $caps    User capabilities.
	 * @param int   $user_id User ID.
	 * @return array Modified capabilities.
	 */
	public function filter_user_capabilities( $caps, $user_id ) {
		return apply_filters( 'aimentor_pro_user_capabilities', $caps, $user_id );
	}

	/**
	 * Filter team limits.
	 *
	 * @param array $limits Team limits.
	 * @return array Modified limits.
	 */
	public function filter_team_limits( $limits ) {
		$defaults = [
			'max_users'             => 1,
			'max_generations_day'   => 50,
			'max_generations_month' => 1000,
			'max_pipelines'         => 5,
			'max_scheduled'         => 20,
		];

		$limits = wp_parse_args( $limits, $defaults );

		return apply_filters( 'aimentor_pro_team_limits', $limits );
	}
}

// Helper functions

/**
 * Check if AiMentor Pro is active.
 *
 * @return bool
 */
function aimentor_is_pro_active() {
	return apply_filters( 'aimentor_is_pro_active', false );
}

/**
 * Get branding configuration.
 *
 * @return array
 */
function aimentor_get_branding() {
	return apply_filters( 'aimentor_branding', [] );
}

/**
 * Track a usage event.
 *
 * @param string $event Event name.
 * @param array  $data  Event data.
 */
function aimentor_track_event( $event, $data = [] ) {
	do_action( 'aimentor_track_usage', $event, $data, get_current_user_id() );
}

/**
 * Get team limits.
 *
 * @return array
 */
function aimentor_get_team_limits() {
	return apply_filters( 'aimentor_team_limits', [] );
}

/**
 * Check user capability for AiMentor feature.
 *
 * @param string $capability Capability to check.
 * @param int    $user_id    User ID (default current user).
 * @return bool
 */
function aimentor_user_can( $capability, $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	$caps    = apply_filters( 'aimentor_user_capabilities', [], $user_id );

	// Admins can do everything
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}

	return isset( $caps[ $capability ] ) && $caps[ $capability ];
}

// Initialize
AiMentor_Pro_Hooks::instance();
