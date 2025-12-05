<?php
/**
 * Plugin Name: AiMentor Elementor
 * Plugin URI: https://jagjourney.com/
 * Update URI: https://github.com/jagjourney/aimentor-elementor
 * Description: 🚀 FREE AI Page Builder - Generate full Elementor layouts with AiMentor. One prompt = complete pages!
 * Version: 1.9.0
 * Author: AiMentor
 * Author URI: https://jagjourney.com/
 * License: GPL v2 or later
 * Text Domain: aimentor
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Elementor tested up to: 3.27
 * Elementor Pro tested up to: 3.27
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! defined( 'AIMENTOR_PLUGIN_VERSION' ) ) {
        /**
         * Current AiMentor Elementor plugin version.
         *
         * Updated for each tagged release so dependent systems can detect
         * available updates and WordPress can surface the correct metadata.
         */
        define( 'AIMENTOR_PLUGIN_VERSION', '1.9.0' );
}

if ( ! defined( 'AIMENTOR_PLUGIN_FILE' ) ) {
        define( 'AIMENTOR_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'AIMENTOR_PLUGIN_BASENAME' ) ) {
        define( 'AIMENTOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'AIMENTOR_PLUGIN_DIR' ) ) {
        define( 'AIMENTOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'AIMENTOR_PLUGIN_URL' ) ) {
        define( 'AIMENTOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'AIMENTOR_PROVIDER_HEALTH_EVENT' ) ) {
        define( 'AIMENTOR_PROVIDER_HEALTH_EVENT', 'aimentor_daily_provider_health_check' );
}

if ( ! defined( 'AIMENTOR_AUTOMATION_EVENT' ) ) {
        define( 'AIMENTOR_AUTOMATION_EVENT', 'aimentor_hourly_automation_runner' );
}

if ( ! function_exists( 'aimentor_get_error_log_directory' ) ) {
        /**
         * Determine the directory where the AiMentor error log should be stored.
         *
         * @return string
         */
        function aimentor_get_error_log_directory() {
                $default_directory = trailingslashit( WP_CONTENT_DIR ) . 'uploads/aimentor';

                /**
                 * Allow third parties to override the AiMentor error log directory.
                 *
                 * @param string $default_directory Default log directory.
                 */
                $directory = apply_filters( 'aimentor_error_log_directory', $default_directory );

                if ( ! is_string( $directory ) || '' === trim( $directory ) ) {
                        $directory = $default_directory;
                }

                if ( ! file_exists( $directory ) ) {
                        if ( function_exists( 'wp_mkdir_p' ) ) {
                                wp_mkdir_p( $directory );
                        } else {
                                @mkdir( $directory, 0755, true );
                        }
                }

                if ( is_dir( $directory ) && is_writable( $directory ) ) {
                        return $directory;
                }

                $fallback_directory = trailingslashit( WP_CONTENT_DIR );

                if ( is_dir( $fallback_directory ) && is_writable( $fallback_directory ) ) {
                        return $fallback_directory;
                }

                return AIMENTOR_PLUGIN_DIR . 'includes';
        }
}

if ( ! function_exists( 'aimentor_get_error_log_path' ) ) {
        /**
         * Retrieve the fully-qualified path to the AiMentor error log file.
         *
         * @return string
         */
        function aimentor_get_error_log_path() {
                $directory = aimentor_get_error_log_directory();

                return trailingslashit( $directory ) . 'aimentor-errors.log';
        }
}

/**
 * Load plugin translations.
 */
function aimentor_load_textdomain() {
        load_plugin_textdomain( 'aimentor', false, dirname( AIMENTOR_PLUGIN_BASENAME ) . '/languages/' );
}
add_action( 'plugins_loaded', 'aimentor_load_textdomain' );

/**
 * Determine if an option value should be considered empty for migration.
 *
 * @param mixed $value Option value.
 * @return bool
 */
function aimentor_is_option_value_empty( $value ) {
        if ( false === $value || null === $value ) {
                return true;
        }

        if ( is_array( $value ) ) {
                return empty( $value );
        }

        return '' === $value;
}

/**
 * Copy legacy JagGrok options to their AiMentor equivalents and migrate the error log file.
 */
function aimentor_maybe_run_legacy_migration() {
        static $ran = false;

        if ( $ran ) {
                return;
        }

        $ran = true;

        $option_map = array(
                'jaggrok_provider'               => 'aimentor_provider',
                'jaggrok_xai_api_key'            => 'aimentor_xai_api_key',
                'jaggrok_openai_api_key'         => 'aimentor_openai_api_key',
                'jaggrok_auto_insert'            => 'aimentor_auto_insert',
                'jaggrok_theme_style'            => 'aimentor_theme_style',
                'jaggrok_max_tokens'             => 'aimentor_max_tokens',
                'jaggrok_provider_models'        => 'aimentor_provider_models',
                'jaggrok_model'                  => 'aimentor_model',
                'jaggrok_openai_model'           => 'aimentor_openai_model',
                'jaggrok_api_tested'             => 'aimentor_api_tested',
                'jaggrok_provider_test_statuses' => 'aimentor_provider_test_statuses',
                'jaggrok_onboarding_dismissed'   => 'aimentor_onboarding_dismissed',
        );

        foreach ( $option_map as $legacy_key => $modern_key ) {
                $legacy_value = get_option( $legacy_key, null );

                if ( null === $legacy_value || false === $legacy_value ) {
                        continue;
                }

                $modern_value = get_option( $modern_key, null );

                if ( aimentor_is_option_value_empty( $modern_value ) && ! aimentor_is_option_value_empty( $legacy_value ) ) {
                        update_option( $modern_key, $legacy_value );
                }
        }

        $modern_log = aimentor_get_error_log_path();
        $legacy_logs = array(
                AIMENTOR_PLUGIN_DIR . 'includes/jaggrok-errors.log',
                AIMENTOR_PLUGIN_DIR . 'includes/aimentor-errors.log',
        );

        foreach ( $legacy_logs as $legacy_log ) {
                if ( ! file_exists( $legacy_log ) ) {
                        continue;
                }

                if ( $legacy_log === $modern_log ) {
                        continue;
                }

                $log_directory = dirname( $modern_log );

                if ( ! file_exists( $log_directory ) ) {
                        if ( function_exists( 'wp_mkdir_p' ) ) {
                                wp_mkdir_p( $log_directory );
                        } else {
                                @mkdir( $log_directory, 0755, true );
                        }
                }

                if ( file_exists( $modern_log ) ) {
                        $modern_log_dir = dirname( $modern_log );
                        $can_write      = is_writable( $modern_log ) || ( is_dir( $modern_log_dir ) && is_writable( $modern_log_dir ) );

                        if ( $can_write ) {
                                $legacy_contents = file_get_contents( $legacy_log );

                                if ( false !== $legacy_contents ) {
                                        file_put_contents( $modern_log, $legacy_contents, FILE_APPEND | LOCK_EX );
                                        @unlink( $legacy_log );
                                }

                                continue;
                        }
                }

                if ( ! @rename( $legacy_log, $modern_log ) ) {
                        $legacy_contents = file_get_contents( $legacy_log );

                        if ( false !== $legacy_contents ) {
                                file_put_contents( $modern_log, $legacy_contents, FILE_APPEND | LOCK_EX );
                        }

                        @unlink( $legacy_log );
                }
        }
}
add_action( 'plugins_loaded', 'aimentor_maybe_run_legacy_migration', 1 );

/**
 * Register script handles for both AiMentor and legacy JagGrok identifiers.
 */
function aimentor_register_asset_handles() {
        static $registered = false;

        if ( $registered ) {
                return;
        }

        $registered = true;

        $version = AIMENTOR_PLUGIN_VERSION;
        $base    = AIMENTOR_PLUGIN_URL;

        wp_register_style( 'aimentor-admin-settings', $base . 'assets/css/admin-settings.css', array(), $version );
        wp_style_add_data( 'aimentor-admin-settings', 'rtl', 'replace' );

        wp_register_script( 'aimentor-admin-settings', $base . 'js/admin-settings.js', array( 'jquery', 'wp-util' ), $version, true );
        wp_register_script( 'aimentor-elementor-widget', $base . 'js/elementor-widget.js', array( 'jquery', 'elementor-frontend' ), $version, true );
}
add_action( 'init', 'aimentor_register_asset_handles' );

/**
 * Register the private post type used to archive generated layouts.
 */
function aimentor_register_ai_layout_post_type() {
        $archival_enabled = 'yes' === get_option( 'aimentor_archive_layouts', 'no' );
        $show_ui_option   = 'yes' === get_option( 'aimentor_archive_layouts_show_ui', 'no' );
        $show_ui          = $archival_enabled && $show_ui_option;

        $labels = array(
                'name'                  => __( 'AI Layouts', 'aimentor' ),
                'singular_name'         => __( 'AI Layout', 'aimentor' ),
                'menu_name'             => __( 'AI Layouts', 'aimentor' ),
                'name_admin_bar'        => __( 'AI Layout', 'aimentor' ),
                'add_new'               => __( 'Add New', 'aimentor' ),
                'add_new_item'          => __( 'Add New AI Layout', 'aimentor' ),
                'edit_item'             => __( 'Edit AI Layout', 'aimentor' ),
                'new_item'              => __( 'New AI Layout', 'aimentor' ),
                'view_item'             => __( 'View AI Layout', 'aimentor' ),
                'search_items'          => __( 'Search AI Layouts', 'aimentor' ),
                'not_found'             => __( 'No AI layouts found.', 'aimentor' ),
                'not_found_in_trash'    => __( 'No AI layouts found in Trash.', 'aimentor' ),
                'all_items'             => __( 'All AI Layouts', 'aimentor' ),
                'archives'              => __( 'AI Layout Archives', 'aimentor' ),
                'items_list'            => __( 'AI Layout list', 'aimentor' ),
                'filter_items_list'     => __( 'Filter AI layouts list', 'aimentor' ),
                'items_list_navigation' => __( 'AI layouts list navigation', 'aimentor' ),
        );

        register_post_type(
                'ai_layout',
                array(
                        'labels'              => $labels,
                        'public'              => false,
                        'hierarchical'        => false,
                        'show_ui'             => $show_ui,
                        'show_in_menu'        => false,
                        'show_in_admin_bar'   => $show_ui,
                        'show_in_nav_menus'   => false,
                        'show_in_rest'        => false,
                        'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
                        'menu_icon'           => 'dashicons-layout',
                        'capability_type'     => 'post',
                        'map_meta_cap'        => true,
                        'has_archive'         => false,
                        'rewrite'             => false,
                        'query_var'           => false,
                        'exclude_from_search' => true,
                        'can_export'          => false,
                )
        );

        register_post_meta(
                'ai_layout',
                '_aimentor_provider',
                array(
                        'type'              => 'string',
                        'single'            => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'show_in_rest'      => false,
                )
        );

        register_post_meta(
                'ai_layout',
                '_aimentor_prompt',
                array(
                        'type'              => 'string',
                        'single'            => true,
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'show_in_rest'      => false,
                )
        );

        register_post_meta(
                'ai_layout',
                '_aimentor_generation_type',
                array(
                        'type'              => 'string',
                        'single'            => true,
                        'sanitize_callback' => 'sanitize_key',
                        'show_in_rest'      => false,
                )
        );

        register_post_meta(
                'ai_layout',
                '_aimentor_model',
                array(
                        'type'              => 'string',
                        'single'            => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'show_in_rest'      => false,
                )
        );

        register_post_meta(
                'ai_layout',
                '_aimentor_tier',
                array(
                        'type'              => 'string',
                        'single'            => true,
                        'sanitize_callback' => 'sanitize_key',
                        'show_in_rest'      => false,
                )
        );

        register_post_meta(
                'ai_layout',
                '_aimentor_task',
                array(
                        'type'              => 'string',
                        'single'            => true,
                        'sanitize_callback' => 'sanitize_key',
                        'show_in_rest'      => false,
                )
        );

        register_post_meta(
                'ai_layout',
                '_aimentor_payload_format',
                array(
                        'type'              => 'string',
                        'single'            => true,
                        'sanitize_callback' => 'sanitize_key',
                        'show_in_rest'      => false,
                )
        );

        register_post_meta(
                'ai_layout',
                '_aimentor_frame_enabled',
                array(
                        'type'              => 'string',
                        'single'            => true,
                        'sanitize_callback' => 'aimentor_sanitize_frame_toggle_meta',
                        'show_in_rest'      => false,
                )
        );

        register_post_meta(
                'ai_layout',
                '_aimentor_frame_summary',
                array(
                        'type'              => 'string',
                        'single'            => true,
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'show_in_rest'      => false,
                )
        );

        register_post_meta(
                'ai_layout',
                '_aimentor_frame_sections',
                array(
                        'type'              => 'string',
                        'single'            => true,
                        'sanitize_callback' => 'aimentor_sanitize_frame_sections_meta',
                        'show_in_rest'      => false,
                )
        );

        register_post_meta(
                'ai_layout',
                '_aimentor_frame_preview_id',
                array(
                        'type'              => 'integer',
                        'single'            => true,
                        'sanitize_callback' => 'absint',
                        'show_in_rest'      => false,
                )
        );
}
add_action( 'init', 'aimentor_register_ai_layout_post_type', 5 );

if ( ! function_exists( 'aimentor_sanitize_frame_toggle_meta' ) ) {
        /**
         * Sanitize the frame toggle meta value.
         *
         * @param mixed $value Raw meta value.
         * @return string
         */
        function aimentor_sanitize_frame_toggle_meta( $value ) {
                return 'yes' === $value ? 'yes' : '';
        }
}

if ( ! function_exists( 'aimentor_normalize_frame_sections' ) ) {
        /**
         * Normalize a list of frame section labels into a clean array.
         *
         * @param mixed $value Raw input value.
         * @return array
         */
        function aimentor_normalize_frame_sections( $value ) {
                if ( is_string( $value ) ) {
                        $value = wp_unslash( $value );
                        $decoded = json_decode( $value, true );

                        if ( is_array( $decoded ) ) {
                                $value = $decoded;
                        }
                }

                if ( ! is_array( $value ) ) {
                        $parts = preg_split( '/[\r\n,]+/', (string) $value );
                        $value = is_array( $parts ) ? $parts : [];
                }

                $sections = [];

                foreach ( $value as $section ) {
                        if ( is_array( $section ) ) {
                                $section = isset( $section['label'] ) ? $section['label'] : implode( ' ', $section );
                        }

                        $section = sanitize_text_field( (string) $section );

                        if ( '' === $section ) {
                                continue;
                        }

                        $sections[] = $section;
                }

                if ( empty( $sections ) ) {
                        return [];
                }

                return array_values( array_unique( $sections ) );
        }
}

if ( ! function_exists( 'aimentor_sanitize_frame_sections_meta' ) ) {
        /**
         * Sanitize the stored frame section list meta value.
         *
         * @param mixed $value Raw meta value.
         * @return string
         */
        function aimentor_sanitize_frame_sections_meta( $value ) {
                $sections = aimentor_normalize_frame_sections( $value );

                if ( empty( $sections ) ) {
                        return '';
                }

                return wp_json_encode( $sections );
        }
}

/**
 * Output the Elementor dependency notice.
 */
function aimentor_render_missing_elementor_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
                return;
        }

        echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'AiMentor Elementor', 'aimentor' ) . '</strong> ' . esc_html__( 'requires Elementor to be installed and active.', 'aimentor' ) . '</p></div>';
}

/**
 * Ensure Elementor is active before proceeding.
 *
 * @param bool $show_notice Whether to schedule the admin notice when Elementor is missing.
 * @return bool
 */
function aimentor_check_dependencies( $show_notice = true ) {
        $transient_key = 'aimentor_missing_elementor_notice';

        $elementor_active = did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) || defined( 'ELEMENTOR_VERSION' );

        if ( ! $elementor_active ) {
                if ( ! function_exists( 'is_plugin_active' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
                        require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'elementor/elementor.php' ) ) {
                        $elementor_active = true;
                }
        }

        if ( $elementor_active ) {
                if ( $show_notice ) {
                        delete_transient( $transient_key );
                }

                return true;
        }

        if ( ! $show_notice ) {
                return false;
        }

        if ( false === get_transient( $transient_key ) ) {
                set_transient( $transient_key, 1 );
                add_action( 'admin_notices', 'aimentor_render_missing_elementor_notice' );
        }

        return false;
}
add_action( 'plugins_loaded', 'aimentor_check_dependencies', 20 );

/**
 * Determine if Elementor Pro is active.
 *
 * @return bool
 */
function aimentor_is_pro_active() {
        return class_exists( '\ElementorPro\Plugin' ) || defined( 'ELEMENTOR_PRO_VERSION' );
}

/**
 * Add a link to the AiMentor settings page from the plugins list.
 *
 * @param array  $actions     Existing action links.
 * @param string $plugin_file Plugin file basename.
 * @return array
 */
function aimentor_settings_link( $actions, $plugin_file ) {
        if ( $plugin_file === plugin_basename( AIMENTOR_PLUGIN_FILE ) ) {
                $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=aimentor-settings' ) ) . '">' . esc_html__( 'Settings', 'aimentor' ) . '</a>';
                array_unshift( $actions, $settings_link );
        }

        return $actions;
}
add_filter( 'plugin_action_links', 'aimentor_settings_link', 10, 2 );

/**
 * Localize shared AJAX payload.
 *
 * @return array
 */
/**
 * Prepare quick action metadata for client consumption.
 *
 * @param array $payload Quick action payload including registry and settings.
 *
 * @return array{
 *     actions: array<int, array<string, mixed>>,
 *     enabled: array<int, array<string, mixed>>,
 *     states: array<string, array<string, mixed>>,
 *     lookup: array<string, array<string, mixed>>,
 * }
 */
function aimentor_prepare_quick_actions_client_state( $payload ) {
        $registry = isset( $payload['registry'] ) && is_array( $payload['registry'] ) ? $payload['registry'] : array();
        $settings = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : array();

        $actions = array();
        $enabled = array();
        $states  = array();
        $lookup  = array();

        foreach ( $registry as $slug => $definition ) {
                $normalized_slug = sanitize_key(
                        is_array( $definition ) && ! empty( $definition['slug'] ) ? $definition['slug'] : $slug
                );

                if ( '' === $normalized_slug ) {
                        continue;
                }

                $labels   = isset( $definition['labels'] ) && is_array( $definition['labels'] ) ? $definition['labels'] : array();
                $defaults = isset( $definition['defaults'] ) && is_array( $definition['defaults'] ) ? $definition['defaults'] : array();
                $stored   = isset( $settings[ $normalized_slug ] ) && is_array( $settings[ $normalized_slug ] )
                        ? $settings[ $normalized_slug ]
                        : array();

                $name = isset( $labels['name'] ) ? (string) $labels['name'] : '';
                if ( '' === $name ) {
                        $name = $normalized_slug;
                }

                $menu_label = '';
                if ( isset( $labels['menu_label'] ) ) {
                        $menu_label = (string) $labels['menu_label'];
                } elseif ( isset( $labels['menuLabel'] ) ) {
                        $menu_label = (string) $labels['menuLabel'];
                }
                if ( '' === $menu_label ) {
                        $menu_label = $name;
                }

                $description = isset( $labels['description'] ) ? (string) $labels['description'] : '';

                $enabled_flag = array_key_exists( 'enabled', $stored )
                        ? (bool) $stored['enabled']
                        : (bool) ( $defaults['enabled'] ?? false );

                $prompt_value = isset( $stored['prompt'] ) && '' !== $stored['prompt']
                        ? (string) $stored['prompt']
                        : ( isset( $defaults['prompt'] ) ? (string) $defaults['prompt'] : '' );

                $system_value = isset( $stored['system'] ) && '' !== $stored['system']
                        ? (string) $stored['system']
                        : ( isset( $defaults['system'] ) ? (string) $defaults['system'] : '' );

                $entry = array(
                        'slug'        => $normalized_slug,
                        'name'        => $name,
                        'menuLabel'   => $menu_label,
                        'description' => $description,
                        'enabled'     => $enabled_flag,
                        'prompt'      => $prompt_value,
                        'system'      => $system_value,
                );

                $actions[]                   = $entry;
                $lookup[ $normalized_slug ]  = $entry;
                $states[ $normalized_slug ]  = array(
                        'enabled' => $enabled_flag,
                        'prompt'  => $prompt_value,
                        'system'  => $system_value,
                );

                if ( $enabled_flag ) {
                        $enabled[] = $entry;
                }
        }

        return array(
                'actions' => $actions,
                'enabled' => $enabled,
                'states'  => $states,
                'lookup'  => $lookup,
        );
}

function aimentor_get_ajax_payload() {
        $provider_meta_map     = aimentor_get_provider_meta_map();
        $provider_labels       = wp_list_pluck( $provider_meta_map, 'label' );
        $provider_summaries    = wp_list_pluck( $provider_meta_map, 'summary' );
        $defaults              = aimentor_get_default_options();
        $default_task_fallback = aimentor_sanitize_generation_type( $defaults['aimentor_default_generation_type'] ?? 'content' );
        $default_tier_fallback = aimentor_sanitize_performance_tier( $defaults['aimentor_default_performance'] ?? 'fast' );
        $default_task          = aimentor_sanitize_generation_type( get_option( 'aimentor_default_generation_type', $default_task_fallback ) );
        $default_tier          = aimentor_sanitize_performance_tier( get_option( 'aimentor_default_performance', $default_tier_fallback ) );
        $document_defaults     = aimentor_get_document_provider_defaults();
        $option_default_provider = get_option( 'aimentor_provider', $defaults['aimentor_provider'] );
        $context_default         = isset( $document_defaults['default']['provider'] ) ? $document_defaults['default']['provider'] : $option_default_provider;
        $default_provider        = aimentor_sanitize_provider( $context_default );
        $default_provider_model  = isset( $document_defaults['default']['model'] ) ? $document_defaults['default']['model'] : '';
        $saved_prompts         = function_exists( 'aimentor_get_saved_prompts_payload' ) ? aimentor_get_saved_prompts_payload() : [
                'global' => [],
                'user'   => [],
        ];
        $canvas_history        = function_exists( 'aimentor_get_canvas_history' ) ? aimentor_get_canvas_history() : [];
        $tone_presets          = function_exists( 'aimentor_get_tone_presets' ) ? aimentor_get_tone_presets() : [];
        $knowledge_packs       = array_map( 'aimentor_prepare_knowledge_pack_for_response', aimentor_get_knowledge_packs() );
        $quick_actions_payload = aimentor_get_quick_actions_payload();
        $quick_actions_state   = aimentor_prepare_quick_actions_client_state( $quick_actions_payload );
        $quick_actions_payload = array_merge(
                $quick_actions_payload,
                array(
                        'actions'         => $quick_actions_state['actions'],
                        'enabled_actions' => $quick_actions_state['enabled'],
                        'states'          => $quick_actions_state['states'],
                        'lookup'          => $quick_actions_state['lookup'],
                )
        );

        return array(
                'ajaxurl'           => admin_url( 'admin-ajax.php' ),
                'nonce'             => wp_create_nonce( 'aimentor_test' ),
                'rewriteNonce'      => wp_create_nonce( 'aimentor_rewrite' ),
                'savedPromptNonce'  => wp_create_nonce( 'aimentor_saved_prompts' ),
                'dismissNonce'      => wp_create_nonce( 'aimentor_onboarding' ),
                'usageNonce'        => wp_create_nonce( 'aimentor_usage_metrics' ),
                'logNonce'          => wp_create_nonce( 'aimentor_error_log' ),
                'tabNonce'          => aimentor_get_settings_tab_nonce(),
                'tabAction'         => 'aimentor_load_settings_tab',
                'restNonce'         => wp_create_nonce( 'wp_rest' ),
                'canvasHistoryNonce' => wp_create_nonce( 'aimentor_canvas_history' ),
                'historyEndpoint'   => esc_url_raw( rest_url( 'aimentor/v1/history' ) ),
                'promptsEndpoint'   => esc_url_raw( rest_url( 'aimentor/v1/prompts' ) ),
                'knowledgeEndpoint' => esc_url_raw( rest_url( 'aimentor/v1/knowledge-packs' ) ),
                'quickActionsEndpoint' => esc_url_raw( rest_url( 'aimentor/v1/quick-actions' ) ),
                'analyticsEndpoint' => esc_url_raw( rest_url( 'aimentor/v1/analytics/summary' ) ),
                'analyticsGuardrailsEndpoint' => esc_url_raw( rest_url( 'aimentor/v1/analytics/guardrails' ) ),
                'usageRefreshInterval' => apply_filters( 'aimentor_usage_refresh_interval', MINUTE_IN_SECONDS ),
                'strings'           => array(
                        'tabLoadError'      => __( 'Unable to load tab content. Please try again.', 'aimentor' ),
                        'tabLoading'        => __( 'Loading…', 'aimentor' ),
                        'testingBadge'       => __( 'Testing', 'aimentor' ),
                        'testingDescription' => __( 'Testing connection…', 'aimentor' ),
                        'missingKey'         => __( 'Enter an API key before testing.', 'aimentor' ),
                        'errorBadge'         => __( 'Error', 'aimentor' ),
                        'unknownError'       => __( 'Unknown error', 'aimentor' ),
                        'onboardingDismissError' => __( 'Unable to dismiss the onboarding card. Please try again.', 'aimentor' ),
                        'usageUpdated'        => __( 'Updated %s', 'aimentor' ),
                        'usageNoActivity'     => __( 'No activity yet', 'aimentor' ),
                        'usageNoContext'      => __( 'Most recent context unavailable.', 'aimentor' ),
                        'usageJustNow'        => __( 'Just now', 'aimentor' ),
                        'logFetchError'       => __( 'Unable to load error logs. Please try again.', 'aimentor' ),
                        'logDownloadError'    => __( 'Unable to download the error log. Please try again.', 'aimentor' ),
                        'logDownloadReady'    => __( 'Log download will begin shortly.', 'aimentor' ),
                        'logClearConfirm'     => __( 'Are you sure you want to clear the error log? This cannot be undone.', 'aimentor' ),
                        'logClearSuccess'     => __( 'Error log cleared.', 'aimentor' ),
                        'logClearError'       => __( 'Unable to clear the error log. Please try again.', 'aimentor' ),
                        /* translators: %s: Provider label. */
                        'generateWith'       => __( 'Generate with %s', 'aimentor' ),
                        /* translators: %s: Provider label. */
                        'generatingWith'     => __( 'Generating with %s…', 'aimentor' ),
                        /* translators: %s: Provider label. */
                        'contentGenerated'   => __( 'Content generated with %s.', 'aimentor' ),
                        /* translators: %s: Provider label. */
                        'writeWith'          => __( 'Write with %s', 'aimentor' ),
                        'chooseProvider'     => __( 'Choose provider', 'aimentor' ),
                        'promptRequired'     => __( 'Please enter a prompt!', 'aimentor' ),
                        'generateAgain'      => __( 'Generate Again', 'aimentor' ),
                        'closeModal'         => __( 'Close modal', 'aimentor' ),
                        'successPrefix'      => __( '✅', 'aimentor' ),
                        'errorPrefix'        => __( 'Error:', 'aimentor' ),
                        'askAiMentor'        => __( 'Ask AiMentor', 'aimentor' ),
                        /* translators: %s: Provider label. */
                        'askAiMentorWith'    => __( 'Ask AiMentor via %s', 'aimentor' ),
                        'generationType'     => __( 'Generation Type', 'aimentor' ),
                        'pageLayout'         => __( 'Page Layout', 'aimentor' ),
                        'pageCopy'           => __( 'Page Copy', 'aimentor' ),
                        'performanceLabel'   => __( 'Performance', 'aimentor' ),
                        'fastLabel'          => __( 'Fast', 'aimentor' ),
                        'qualityLabel'       => __( 'Quality', 'aimentor' ),
                        'layoutLabel'        => __( 'Layout', 'aimentor' ),
                        'copyLabel'          => __( 'Copy', 'aimentor' ),
                        /* translators: %s: Model label. */
                        'summaryPoweredBy'   => __( '– powered by %s', 'aimentor' ),
                        'summarySeparator'   => _x( ' • ', 'separator between task and tier', 'aimentor' ),
                        'promptLabel'        => __( 'Prompt', 'aimentor' ),
                        'promptPlaceholder'  => __( 'Describe your page (e.g., hero with CTA)', 'aimentor' ),
                        'tonePresetLabel'    => __( 'Tone', 'aimentor' ),
                        'tonePresetPlaceholder' => __( 'Select a tone preset…', 'aimentor' ),
                        'tonePresetCustomOption' => __( 'Custom tone…', 'aimentor' ),
                        'tonePresetCustomLabel' => __( 'Custom tone keywords', 'aimentor' ),
                        'tonePresetCustomPlaceholder' => __( 'e.g., bold, welcoming, energetic', 'aimentor' ),
                        'rewriteButtonLabel' => __( 'Rewrite with Tone', 'aimentor' ),
                        'rewriteWorking'     => __( 'Rewriting…', 'aimentor' ),
                        'rewriteSuccess'     => __( 'Copy rewritten to match the selected tone.', 'aimentor' ),
                        'rewriteError'       => __( 'Unable to rewrite the selection. Try again.', 'aimentor' ),
                        'rewriteMissingSource' => __( 'Highlight text in Elementor or enter a prompt to rewrite.', 'aimentor' ),
                        'rewriteAppliedToControl' => __( 'Updated the selected Elementor control with rewritten copy.', 'aimentor' ),
                        'rewriteAppliedToPrompt'  => __( 'Prompt updated with rewritten copy.', 'aimentor' ),
                        'missingConfig'      => __( 'AiMentor AJAX configuration is missing. Please ensure the plugin assets are enqueued properly.', 'aimentor' ),
                        'savedPromptLabel'        => __( 'Saved Prompts', 'aimentor' ),
                        'savedPromptPlaceholder'  => __( 'Select a saved prompt…', 'aimentor' ),
                        'savedPromptEmpty'        => __( 'No saved prompts found.', 'aimentor' ),
                        'savedPromptGroupUser'    => __( 'My Prompts', 'aimentor' ),
                        'savedPromptGroupGlobal'  => __( 'Shared Prompts', 'aimentor' ),
                        'savedPromptColumnLabel'  => __( 'Label', 'aimentor' ),
                        'savedPromptColumnPrompt' => __( 'Prompt', 'aimentor' ),
                        'savedPromptColumnActions' => __( 'Actions', 'aimentor' ),
                        'savedPromptDeleteLabel'  => __( 'Delete', 'aimentor' ),
                        'savedPromptListEmpty'    => __( 'No prompts saved yet.', 'aimentor' ),
                        'savedPromptCreateSuccess' => __( 'Prompt saved.', 'aimentor' ),
                        'savedPromptCreateError'   => __( 'Unable to save the prompt. Please try again.', 'aimentor' ),
                        /* translators: %s: Saved prompt label. */
                        'savedPromptDeleteConfirm' => __( 'Delete "%s"? This cannot be undone.', 'aimentor' ),
                        'savedPromptDeleteSuccess' => __( 'Prompt deleted.', 'aimentor' ),
                        'savedPromptDeleteError'   => __( 'Unable to delete the prompt. Please try again.', 'aimentor' ),
                        'savedPromptPermissionError' => __( 'You do not have permission to manage saved prompts.', 'aimentor' ),
                        'knowledgeSaveSuccess'       => __( 'Knowledge pack saved.', 'aimentor' ),
                        'knowledgeSaveError'         => __( 'Unable to save the knowledge pack. Please try again.', 'aimentor' ),
                        /* translators: %s: Knowledge pack title. */
                        'knowledgeDeleteConfirm'     => __( 'Delete "%s"? This cannot be undone.', 'aimentor' ),
                        'knowledgeDeleteSuccess'     => __( 'Knowledge pack deleted.', 'aimentor' ),
                        'knowledgeDeleteError'       => __( 'Unable to delete the knowledge pack. Please try again.', 'aimentor' ),
                        'knowledgeEmpty'             => __( 'No knowledge packs have been added yet.', 'aimentor' ),
                        'knowledgePackLabel'         => __( 'Knowledge Packs', 'aimentor' ),
                        'knowledgePackPlaceholder'   => __( 'Select knowledge packs…', 'aimentor' ),
                        'knowledgePackDescription'   => __( 'Optional: ground the response with brand or product knowledge.', 'aimentor' ),
                        'knowledgePackSelectionEmpty' => __( 'No knowledge packs available yet.', 'aimentor' ),
                        'knowledgePackSelectedCount' => __( '%d knowledge packs selected', 'aimentor' ),
                        'quickActionsHeading'        => __( 'Quick actions', 'aimentor' ),
                        'quickActionsToolbarLabel'   => __( 'Quick actions toolbar', 'aimentor' ),
                        'quickActionsSaveSuccess'    => __( 'Quick actions updated.', 'aimentor' ),
                        'quickActionsSaveError'      => __( 'Unable to save quick actions. Please try again.', 'aimentor' ),
                        'quickActionsEmpty'          => __( 'No quick actions are registered yet.', 'aimentor' ),
                        'quickActionsPromptLabel'    => __( 'Prompt instructions', 'aimentor' ),
                        'quickActionsSystemLabel'    => __( 'System instructions', 'aimentor' ),
                        'quickActionsToggleLabel'    => __( 'Enable quick action', 'aimentor' ),
                        'quickActionsSave'           => __( 'Save Quick Actions', 'aimentor' ),
                        'quickActionsSaving'         => __( 'Saving…', 'aimentor' ),
                        'quickActionsNoChanges'      => __( 'No changes to save.', 'aimentor' ),
                        /* translators: %s: Quick action label. */
                        'quickActionWorking'         => __( 'Running "%s"…', 'aimentor' ),
                        /* translators: %s: Quick action label. */
                        'quickActionError'           => __( 'Unable to run "%s". Please try again.', 'aimentor' ),
                        'quickActionPromptMissing'   => __( 'Select or enter content before running this quick action.', 'aimentor' ),
                        'automationJobRemoveConfirm' => __( 'Remove this automation job? This cannot be undone.', 'aimentor' ),
                        'automationJobDefaultTitle'  => __( 'New automation job', 'aimentor' ),
                        /* translators: %s: Human readable duration. */
                        'rateLimitCooldown'       => __( 'Please wait %s before trying again.', 'aimentor' ),
                        'rateLimitSecondsFallbackSingular' => __( '%d second', 'aimentor' ),
                        'rateLimitSecondsFallback' => __( '%d seconds', 'aimentor' ),
                        'analyticsLoading'         => __( 'Loading analytics…', 'aimentor' ),
                        'analyticsLoadError'       => __( 'Unable to load analytics right now. Please try again later.', 'aimentor' ),
                        'analyticsEmpty'           => __( 'No analytics data recorded yet.', 'aimentor' ),
                        'analyticsGuardrailsSaved' => __( 'Guardrail updates saved.', 'aimentor' ),
                        'analyticsGuardrailsError' => __( 'Unable to update guardrails. Please try again.', 'aimentor' ),
                        'analyticsSummarySuccess'  => __( 'Successful requests', 'aimentor' ),
                        'analyticsSummaryErrors'   => __( 'Errors', 'aimentor' ),
                        'analyticsSummaryTokens'   => __( 'Tokens used', 'aimentor' ),
                        'analyticsIntervalFallbackLabel' => __( 'Intervals tracked', 'aimentor' ),
                        'analyticsIntervalHourLabel' => __( 'Hours tracked', 'aimentor' ),
                        'analyticsIntervalDayLabel'  => __( 'Days tracked', 'aimentor' ),
                        'analyticsIntervalWeekLabel' => __( 'Weeks tracked', 'aimentor' ),
                        'analyticsTableProvider'    => __( 'Provider', 'aimentor' ),
                        'analyticsTableRequests'    => __( 'Requests', 'aimentor' ),
                        'analyticsChartBarLabel'    => __( '%1$s: %2$d total requests', 'aimentor' ),
                        'analyticsWarningTitle'    => __( 'Guardrail warnings', 'aimentor' ),
                        'recentLayoutsHeading'     => __( 'Recent layouts', 'aimentor' ),
                        'recentLayoutsBrowse'      => __( 'Browse recent layouts', 'aimentor' ),
                        'recentLayoutsPrev'        => __( 'Show previous layout', 'aimentor' ),
                        'recentLayoutsNext'        => __( 'Show next layout', 'aimentor' ),
                        'recentLayoutsEmpty'       => __( 'Generate a layout to see it here after your next run.', 'aimentor' ),
                        'recentLayoutsUse'         => __( 'Insert layout', 'aimentor' ),
                        'recentLayoutsPreviewMissing' => __( 'Preview unavailable for this layout.', 'aimentor' ),
                        'recentLayoutsTimestamp'   => __( 'Generated %s', 'aimentor' ),
                        'recentLayoutsMetaSeparator' => _x( ' • ', 'separator between layout details', 'aimentor' ),
                        'frameLibraryHeading'      => __( 'Frame Library', 'aimentor' ),
                        'frameLibraryDescription'  => __( 'Insert curated layouts or pull their prompts into the generator to start faster.', 'aimentor' ),
                        'frameLibraryEmpty'        => __( 'No frames have been curated yet. Promote archived layouts from the Frame Library tab in AiMentor settings.', 'aimentor' ),
                        'frameLibraryInsert'       => __( 'Insert frame', 'aimentor' ),
                        'frameLibrarySeed'         => __( 'Seed prompt', 'aimentor' ),
                        'frameLibrarySectionsLabel' => __( 'Suggested sections', 'aimentor' ),
                        'frameLibraryPromptLabel'  => __( 'Starter prompt', 'aimentor' ),
                        'frameLibraryProviderLabel' => __( 'Source', 'aimentor' ),
                        'frameLibraryLoading'      => __( 'Loading curated frames…', 'aimentor' ),
                        'frameLibraryError'        => __( 'Unable to load frames. Refresh the panel or try again later.', 'aimentor' ),
                        'frameLibraryUpdated'      => __( 'Updated %s', 'aimentor' ),
                        'frameLibrarySelectImage'  => __( 'Choose preview', 'aimentor' ),
                        'frameLibraryUseImage'     => __( 'Use preview', 'aimentor' ),
                        'frameLibraryPreviewPending' => __( 'Preview pending', 'aimentor' ),
                        'modalAsideLabel'          => __( 'Explore saved layouts and frames', 'aimentor' ),
                        'canvasVariationsHeading'    => __( 'Choose a layout style', 'aimentor' ),
                        'canvasVariationLabel'       => __( 'Variation %d', 'aimentor' ),
                        'canvasVariationsDescription' => __( 'AiMentor generated multiple layouts. Select your favorite to insert it into Elementor.', 'aimentor' ),
                        'canvasVariationInserted'    => __( 'Layout inserted!', 'aimentor' ),
                        'canvasVariationInsertedNamed' => __( '%s inserted into the canvas.', 'aimentor' ),
                        'canvasVariationPreviewPlaceholder' => __( 'Preview not available for this layout yet.', 'aimentor' ),
                        'canvasVariationMetaSectionsSingular' => __( '%d section', 'aimentor' ),
                        'canvasVariationMetaSections' => __( '%d sections', 'aimentor' ),
                        'canvasVariationMetaColumnsSingular' => __( '%d column', 'aimentor' ),
                        'canvasVariationMetaColumns' => __( '%d columns', 'aimentor' ),
                        'canvasVariationMetaWidgetsSingular' => __( '%d widget', 'aimentor' ),
                        'canvasVariationMetaWidgets' => __( '%d widgets', 'aimentor' ),
                        'canvasVariationMetaSeparator' => _x( ' • ', 'separator between canvas variation meta labels', 'aimentor' ),
                        'canvasVariationActionLabel' => __( 'Insert this layout', 'aimentor' ),
                        'canvasVariationsCount'      => __( '%d layout styles ready', 'aimentor' ),
                        'canvasVariationsEmpty'      => __( 'No layouts available yet. Try generating again.', 'aimentor' ),
                ),
                'providerLabels'    => $provider_labels,
                'providerSummaries' => $provider_summaries,
                'providersMeta'     => $provider_meta_map,
                'defaults'          => array(
                        'task' => $default_task,
                        'tier' => $default_tier,
                        'provider' => $default_provider,
                        'model' => $default_provider_model,
                        'contexts' => $document_defaults,
                ),
                'provider'          => $default_provider,
                'modelPresets'      => aimentor_get_model_presets(),
                'modelLabels'       => aimentor_get_model_labels(),
                'promptPresets'     => function_exists( 'aimentor_get_prompt_preset_catalog' ) ? aimentor_get_prompt_preset_catalog() : [],
                'framePresets'      => function_exists( 'aimentor_get_frame_prompt_presets' ) ? aimentor_get_frame_prompt_presets() : [],
                'isProActive'       => aimentor_is_pro_active(),
                'savedPrompts'      => $saved_prompts,
                'canvasHistory'     => $canvas_history,
                'tonePresets'       => $tone_presets,
                'knowledgePacks'    => $knowledge_packs,
                'quickActions'      => $quick_actions_payload,
                'canvasHistoryMax'  => function_exists( 'aimentor_get_canvas_history_max_items' ) ? aimentor_get_canvas_history_max_items() : 0,
                'frameLibraryEndpoint' => esc_url_raw( rest_url( 'aimentor/v1/frames' ) ),
                'frameLibrary'      => function_exists( 'aimentor_get_frame_library_items' ) ? aimentor_get_frame_library_items( [ 'posts_per_page' => 50 ] ) : [],
                'canvasVariationCount' => apply_filters( 'aimentor_canvas_variation_count', 3, $default_provider, 'canvas', $default_tier ),
        );
}

/**
 * Build the provider prompt used for rewrite operations.
 *
 * @param string $source         Original copy selected for rewriting.
 * @param string $tone_keywords  Tone keywords requested by the editor.
 * @param array  $args           Optional arguments.
 *
 * @return string
 */
function aimentor_build_rewrite_prompt( $source, $tone_keywords, $args = array() ) {
        $source        = trim( (string) $source );
        $tone_keywords = isset( $tone_keywords ) ? aimentor_sanitize_tone_keywords( $tone_keywords ) : '';

        $instructions = array(
                __( 'You are rewriting on-brand Elementor copy for a WordPress site.', 'aimentor' ),
                __( 'Preserve the original meaning, structure, and any shortcodes or dynamic placeholders.', 'aimentor' ),
        );

        if ( '' !== $tone_keywords ) {
                $instructions[] = sprintf(
                        /* translators: %s: Tone keywords list. */
                        __( 'Match this tone: %s.', 'aimentor' ),
                        $tone_keywords
                );
        }

        $instructions[] = __( 'Return only the rewritten copy without commentary, markdown fences, or surrounding narrative.', 'aimentor' );

        $prompt_sections = array(
                implode( ' ', $instructions ),
                __( 'Source copy:', 'aimentor' ),
                $source,
                __( 'Rewritten copy:', 'aimentor' ),
        );

        $prompt = implode( "\n\n", $prompt_sections );

        /**
         * Filter the rewrite prompt before sending it to the provider.
         *
         * @param string $prompt        Final prompt string.
         * @param string $source        Original source copy.
         * @param string $tone_keywords Sanitized tone keywords.
         * @param array  $args          Additional context arguments.
         */
        return apply_filters( 'aimentor_rewrite_prompt', $prompt, $source, $tone_keywords, $args );
}

/**
 * Enqueue admin assets for the settings screen.
 *
 * @param string $hook Current admin hook.
 */
function aimentor_enqueue_assets( $hook ) {
        aimentor_register_asset_handles();

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        $is_settings_screen  = ( 'settings_page_aimentor-settings' === $hook );
        $is_elementor_screen = ( $screen && 0 === strpos( $screen->id, 'elementor' ) );

        if ( ! $is_settings_screen && ! $is_elementor_screen ) {
                return;
        }

        if ( $is_elementor_screen && ! $is_settings_screen ) {
                // Elementor assets are handled via elementor/editor/after_enqueue_scripts.
                return;
        }

        if ( $is_settings_screen ) {
                wp_enqueue_style( 'aimentor-admin-settings' );
                if ( function_exists( 'wp_enqueue_media' ) ) {
                        wp_enqueue_media();
                }
        }

        wp_enqueue_script( 'aimentor-admin-settings' );
        $ajax_payload = aimentor_get_ajax_payload();
        wp_localize_script( 'aimentor-admin-settings', 'aimentorAjax', $ajax_payload );
        wp_add_inline_script(
                'aimentor-admin-settings',
                "window.aimentorAjax = window.aimentorAjax || {};\n"
                . "window.aimentorAjax.legacy = Object.assign({ prefixes: { id: 'jaggrok_', class: 'jaggrok-' } }, window.aimentorAjax.legacy || {});\n",
                'after'
        );
}
add_action( 'admin_enqueue_scripts', 'aimentor_enqueue_assets' );

/**
 * Enqueue assets for the Elementor editor.
 */
function aimentor_enqueue_elementor_assets() {
        aimentor_register_asset_handles();

        wp_enqueue_script( 'aimentor-elementor-widget' );
        $ajax_payload = aimentor_get_ajax_payload();
        wp_localize_script( 'aimentor-elementor-widget', 'aimentorAjax', $ajax_payload );
        wp_add_inline_script(
                'aimentor-elementor-widget',
                "window.aimentorAjax = window.aimentorAjax || {};\n",
                'after'
        );
}
add_action( 'elementor/editor/after_enqueue_scripts', 'aimentor_enqueue_elementor_assets' );

/**
 * Build the provider metadata map shared with the editor.
 *
 * @return array
 */
function aimentor_get_provider_meta_map() {
        $provider_labels = function_exists( 'aimentor_get_provider_labels' ) ? aimentor_get_provider_labels() : array(
                'grok'      => __( 'xAI Grok', 'aimentor' ),
                'anthropic' => __( 'Anthropic Claude', 'aimentor' ),
                'openai'    => __( 'OpenAI', 'aimentor' ),
        );

        $meta = array();

        foreach ( $provider_labels as $key => $label ) {
                switch ( $key ) {
                        case 'grok':
                                $badge_text  = __( 'xAI', 'aimentor' );
                                $badge_color = '#1E1E1E';
                                $icon        = '🚀';
                                break;
                        case 'anthropic':
                                $badge_text  = __( 'Claude', 'aimentor' );
                                $badge_color = '#FF5C35';
                                $icon        = '✨';
                                break;
                        case 'openai':
                                $badge_text  = __( 'OpenAI', 'aimentor' );
                                $badge_color = '#2B8CFF';
                                $icon        = '🔷';
                                break;
                        default:
                                $badge_text  = $label;
                                $badge_color = '#3B82F6';
                                $icon        = '🤖';
                                break;
                }

                $meta[ $key ] = array(
                        'label'      => $label,
                        'icon'       => $icon,
                        'summary'    => sprintf( __( 'Content generated with %s.', 'aimentor' ), $label ),
                        'badgeText'  => $badge_text,
                        'badgeColor' => $badge_color,
                );
        }

        $meta = apply_filters( 'aimentor_provider_meta_map', $meta );
        $meta = apply_filters( 'jaggrok_provider_meta_map', $meta );

        return $meta;
}

// Schema validation and repair (Phase 1 foundation).
require_once AIMENTOR_PLUGIN_DIR . 'includes/schema/class-elementor-schema-validator.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/schema/class-elementor-json-repair.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/schema/class-widget-definitions.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/schema/class-elementor-prompt-builder.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/schema/class-generation-pipeline.php';

// Providers.
require_once AIMENTOR_PLUGIN_DIR . 'includes/providers/class-aimentor-provider-interface.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/providers/trait-aimentor-provider-variations.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/providers/class-aimentor-grok-provider.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/providers/class-aimentor-anthropic-provider.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/providers/class-aimentor-openai-provider.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/knowledge-base.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/analytics.php';

if ( class_exists( 'AiMentor_Provider_Interface' ) && ! class_exists( 'JagGrok_Provider_Interface' ) ) {
        class_alias( 'AiMentor_Provider_Interface', 'JagGrok_Provider_Interface' );
}

if ( class_exists( 'AiMentor_Grok_Provider' ) && ! class_exists( 'JagGrok_Grok_Provider' ) ) {
        class_alias( 'AiMentor_Grok_Provider', 'JagGrok_Grok_Provider' );
}

if ( class_exists( 'AiMentor_Anthropic_Provider' ) && ! class_exists( 'JagGrok_Anthropic_Provider' ) ) {
        class_alias( 'AiMentor_Anthropic_Provider', 'JagGrok_Anthropic_Provider' );
}

if ( class_exists( 'AiMentor_OpenAI_Provider' ) && ! class_exists( 'JagGrok_OpenAI_Provider' ) ) {
        class_alias( 'AiMentor_OpenAI_Provider', 'JagGrok_OpenAI_Provider' );
}

// Settings & helpers.
require_once AIMENTOR_PLUGIN_DIR . 'includes/settings.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/automation.php';
require_once AIMENTOR_PLUGIN_DIR . 'includes/legacy-shims.php';

function aimentor_schedule_provider_health_check() {
        if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'aimentor_health_checks_enabled' ) ) {
                return;
        }

        if ( ! aimentor_health_checks_enabled() ) {
                aimentor_clear_provider_health_schedule();
                return;
        }

        if ( ! wp_next_scheduled( AIMENTOR_PROVIDER_HEALTH_EVENT ) ) {
                wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', AIMENTOR_PROVIDER_HEALTH_EVENT );
        }
}

function aimentor_clear_provider_health_schedule() {
        if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
                wp_clear_scheduled_hook( AIMENTOR_PROVIDER_HEALTH_EVENT );
        }
}

add_action( 'init', 'aimentor_schedule_provider_health_check' );
add_action( 'update_option_aimentor_enable_health_checks', 'aimentor_refresh_provider_health_schedule', 10, 2 );

if ( function_exists( 'aimentor_run_scheduled_provider_checks' ) ) {
        add_action( AIMENTOR_PROVIDER_HEALTH_EVENT, 'aimentor_run_scheduled_provider_checks' );
}

function aimentor_refresh_provider_health_schedule( $old_value, $value ) {
        if ( ! function_exists( 'aimentor_sanitize_toggle' ) ) {
                return;
        }

        $normalized = aimentor_sanitize_toggle( $value );

        if ( 'yes' === $normalized ) {
                aimentor_schedule_provider_health_check();
                return;
        }

        aimentor_clear_provider_health_schedule();
}

function aimentor_schedule_automation_runner() {
        if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) || ! function_exists( 'aimentor_automation_enabled' ) ) {
                return;
        }

        if ( ! aimentor_automation_enabled() ) {
                aimentor_clear_automation_schedule();
                return;
        }

        if ( ! wp_next_scheduled( AIMENTOR_AUTOMATION_EVENT ) ) {
                wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', AIMENTOR_AUTOMATION_EVENT );
        }
}

function aimentor_clear_automation_schedule() {
        if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
                wp_clear_scheduled_hook( AIMENTOR_AUTOMATION_EVENT );
        }
}

function aimentor_refresh_automation_schedule( $old_value, $value ) {
        if ( ! function_exists( 'aimentor_sanitize_toggle' ) ) {
                return;
        }

        $normalized = aimentor_sanitize_toggle( $value );

        if ( 'yes' === $normalized ) {
                aimentor_schedule_automation_runner();
                return;
        }

        aimentor_clear_automation_schedule();
}

add_action( 'init', 'aimentor_schedule_automation_runner' );
add_action( 'update_option_aimentor_enable_automation', 'aimentor_refresh_automation_schedule', 10, 2 );

if ( function_exists( 'aimentor_run_scheduled_automation_jobs' ) ) {
        add_action( AIMENTOR_AUTOMATION_EVENT, 'aimentor_run_scheduled_automation_jobs' );
}

register_activation_hook( AIMENTOR_PLUGIN_FILE, 'aimentor_activate_plugin' );
/**
 * Run setup tasks during plugin activation.
 */
function aimentor_activate_plugin() {
        if ( function_exists( 'aimentor_seed_default_options' ) ) {
                aimentor_seed_default_options();
        }

        aimentor_schedule_provider_health_check();
        aimentor_schedule_automation_runner();
}

register_deactivation_hook( AIMENTOR_PLUGIN_FILE, 'aimentor_deactivate_plugin' );

function aimentor_deactivate_plugin() {
        aimentor_clear_provider_health_schedule();
        aimentor_clear_automation_schedule();
}

if ( function_exists( 'aimentor_seed_default_options' ) ) {
        add_action( 'plugins_loaded', 'aimentor_seed_default_options', 5 );
}

// Elementor widget registration.
add_action( 'elementor/widgets/register', function( $widgets_manager ) {
        if ( ! aimentor_check_dependencies( false ) ) {
                return;
        }

        require_once AIMENTOR_PLUGIN_DIR . 'includes/elementor-widget.php';

        if ( class_exists( 'AiMentor_AI_Generator_Widget' ) && ! class_exists( 'JagGrok_AI_Generator_Widget' ) ) {
                class_alias( 'AiMentor_AI_Generator_Widget', 'JagGrok_AI_Generator_Widget' );
        }

        $widgets_manager->register( new AiMentor_AI_Generator_Widget() );
} );

// Updater.
require_once AIMENTOR_PLUGIN_DIR . 'includes/updater.php';

if ( class_exists( 'AiMentor_Updater' ) ) {
        global $aimentor_updater;

        if ( ! isset( $aimentor_updater ) || ! $aimentor_updater instanceof AiMentor_Updater ) {
		$aimentor_updater = new AiMentor_Updater( 'jagjourney/aimentor-elementor', 'aimentor-elementor' );
        }
}

/**
 * Control whether AiMentor Elementor should receive automatic updates.
 *
 * @param bool   $should_update Whether WordPress intends to auto update the plugin.
 * @param object $item          Plugin update data.
 * @return bool
 */
function aimentor_control_plugin_auto_updates( $should_update, $item ) {
        if ( empty( $item->plugin ) || AIMENTOR_PLUGIN_BASENAME !== $item->plugin ) {
                return $should_update;
        }

        if ( ! function_exists( 'aimentor_auto_updates_enabled' ) ) {
                return $should_update;
        }

        if ( ! aimentor_auto_updates_enabled() ) {
                return false;
        }

        if ( function_exists( 'aimentor_wordpress_allows_plugin_auto_updates' ) && ! aimentor_wordpress_allows_plugin_auto_updates() ) {
                return false;
        }

        return true;
}
add_filter( 'auto_update_plugin', 'aimentor_control_plugin_auto_updates', 10, 2 );

/**
 * Resolve the active provider based on configuration.
 *
 * @param string|null $provider_key Provider key.
 * @return AiMentor_Provider_Interface
 */
function jaggrok_get_active_provider( $provider_key = null ) {
        if ( null === $provider_key ) {
                $provider_key = get_option( 'aimentor_provider', 'grok' );
        }

        switch ( $provider_key ) {
                case 'openai':
                        return new JagGrok_OpenAI_Provider();
                case 'anthropic':
                        return new JagGrok_Anthropic_Provider();
                case 'grok':
                default:
                        return new JagGrok_Grok_Provider();
        }
}

if ( ! function_exists( 'aimentor_get_active_provider' ) ) {
        function aimentor_get_active_provider( $provider_key = null ) {
                return jaggrok_get_active_provider( $provider_key );
        }
}

/**
 * Get available fallback providers in priority order.
 *
 * @since 1.9.0
 *
 * @param string $primary_provider The primary provider that failed.
 * @return array Fallback provider keys that are configured.
 */
function aimentor_get_fallback_providers( $primary_provider ) {
        $all_providers = [ 'anthropic', 'openai', 'grok' ];
        $fallbacks     = [];

        foreach ( $all_providers as $provider ) {
                if ( $provider === $primary_provider ) {
                        continue;
                }

                if ( aimentor_is_provider_configured( $provider ) ) {
                        $fallbacks[] = $provider;
                }
        }

        return $fallbacks;
}

/**
 * Check if a provider is configured with API key.
 *
 * @since 1.9.0
 *
 * @param string $provider Provider key.
 * @return bool Whether the provider is configured.
 */
function aimentor_is_provider_configured( $provider ) {
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
 * Execute generation with automatic fallback on failure.
 *
 * @since 1.9.0
 *
 * @param string $prompt        The generation prompt.
 * @param array  $args          Generation arguments.
 * @param string $provider_key  Primary provider to use.
 * @return array|WP_Error Generation result or error.
 */
function aimentor_generate_with_fallback( $prompt, $args = [], $provider_key = null ) {
        if ( null === $provider_key ) {
                $provider_key = get_option( 'aimentor_provider', 'grok' );
        }

        // Try using the generation pipeline if available.
        if ( class_exists( 'AiMentor_Generation_Pipeline' ) ) {
                $pipeline = new AiMentor_Generation_Pipeline();
                return $pipeline->generate( $prompt, $args, $provider_key );
        }

        // Fallback to direct provider call.
        $provider = aimentor_get_active_provider( $provider_key );

        if ( null === $provider ) {
                return new WP_Error(
                        'aimentor_invalid_provider',
                        __( 'No valid provider configured.', 'aimentor' )
                );
        }

        $result = $provider->request( $prompt, $args );

        // If primary succeeds, return.
        if ( ! is_wp_error( $result ) ) {
                return $result;
        }

        // Don't retry on rate limits or auth errors.
        $error_code = $result->get_error_code();
        if ( in_array( $error_code, [ 'aimentor_rate_limited', 'aimentor_unauthorized', 'aimentor_missing_api_key' ], true ) ) {
                return $result;
        }

        // Try fallback providers.
        $fallbacks = aimentor_get_fallback_providers( $provider_key );

        foreach ( $fallbacks as $fallback_key ) {
                $fallback_provider = aimentor_get_active_provider( $fallback_key );

                if ( null === $fallback_provider ) {
                        continue;
                }

                $fallback_result = $fallback_provider->request( $prompt, $args );

                if ( ! is_wp_error( $fallback_result ) ) {
                        $fallback_result['fallback_used']     = $fallback_key;
                        $fallback_result['original_provider'] = $provider_key;
                        return $fallback_result;
                }
        }

        // All providers failed.
        return $result;
}

function jaggrok_normalize_generation_task( $task ) {
        $task = sanitize_key( $task );

        return in_array( $task, [ 'canvas', 'content' ], true ) ? $task : 'content';
}

function jaggrok_normalize_performance_tier( $tier ) {
        $tier = sanitize_key( $tier );

        return in_array( $tier, [ 'fast', 'quality' ], true ) ? $tier : 'fast';
}

function jaggrok_resolve_generation_preset( $provider_key, $requested_task, $requested_tier, $supports_canvas, $is_pro ) {
        $defaults     = aimentor_get_default_options();
        $model_presets  = aimentor_get_model_presets();
        $model_defaults = aimentor_get_provider_model_defaults();

        $default_task = jaggrok_normalize_generation_task( $defaults['aimentor_default_generation_type'] ?? 'content' );
        $default_tier = jaggrok_normalize_performance_tier( $defaults['aimentor_default_performance'] ?? 'fast' );

        $requested_task = $requested_task ? jaggrok_normalize_generation_task( $requested_task ) : '';
        $requested_tier = $requested_tier ? jaggrok_normalize_performance_tier( $requested_tier ) : '';

        $task = $requested_task ? $requested_task : $default_task;
        $tier = $requested_tier ? $requested_tier : $default_tier;

        if ( 'canvas' === $task && ( ! $supports_canvas || ! $is_pro ) ) {
                $task = 'content';
        }

        if ( ! isset( $model_presets[ $provider_key ] ) ) {
                $provider_key = array_key_exists( $provider_key, $model_defaults ) ? $provider_key : 'grok';
        }

        if ( ! isset( $model_presets[ $provider_key ][ $task ] ) ) {
                $task = 'content';
        }

        if ( ! isset( $model_presets[ $provider_key ][ $task ][ $tier ] ) || '' === $model_presets[ $provider_key ][ $task ][ $tier ] ) {
                $tier_candidates = array_keys( $model_presets[ $provider_key ][ $task ] );
                if ( ! empty( $tier_candidates ) ) {
                        $tier = in_array( $default_tier, $tier_candidates, true ) ? $default_tier : reset( $tier_candidates );
                }
        }

        $model = $model_presets[ $provider_key ][ $task ][ $tier ] ?? '';

        if ( '' === $model ) {
                $model = $model_defaults[ $provider_key ][ $task ][ $tier ] ?? '';
        }

        if ( '' === $model ) {
                $fallbacks = aimentor_map_presets_to_legacy_defaults( $model_defaults );
                $model     = $fallbacks[ $provider_key ] ?? '';
        }

        return [
                'task'  => $task,
                'tier'  => $tier,
                'model' => $model,
        ];
}

if ( ! function_exists( 'aimentor_resolve_generation_preset' ) ) {
        function aimentor_resolve_generation_preset( $provider_key, $requested_task, $requested_tier, $supports_canvas, $is_pro ) {
                return jaggrok_resolve_generation_preset( $provider_key, $requested_task, $requested_tier, $supports_canvas, $is_pro );
        }
}

add_action( 'wp_ajax_aimentor_rewrite_content', 'jaggrok_rewrite_content_ajax' );
add_action( 'wp_ajax_jaggrok_rewrite_content', 'jaggrok_rewrite_content_ajax' );
add_action( 'wp_ajax_aimentor_generate_page', 'jaggrok_generate_page_ajax' );
add_action( 'wp_ajax_jaggrok_generate_page', 'jaggrok_generate_page_ajax' );
/**
 * Handle AJAX rewrite requests for Elementor controls.
 */
function jaggrok_rewrite_content_ajax() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'aimentor_rewrite' ) && ! wp_verify_nonce( $nonce, 'aimentor_test' ) ) {
                wp_send_json_error(
                        array(
                                'message' => __( 'Security check failed.', 'aimentor' ),
                                'code'    => 'aimentor_invalid_nonce',
                        ),
                        403
                );
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
                wp_send_json_error(
                        array(
                                'message' => __( 'Insufficient permissions to rewrite content.', 'aimentor' ),
                                'code'    => 'aimentor_insufficient_permissions',
                        ),
                        403
                );
        }

        $raw_source = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $source     = is_string( $raw_source ) ? wp_kses_post( $raw_source ) : '';
        $source     = trim( $source );

        if ( '' === $source ) {
                wp_send_json_error(
                        array(
                                'message' => __( 'Provide content to rewrite.', 'aimentor' ),
                                'code'    => 'aimentor_missing_source',
                        ),
                        400
                );
        }

        $tone_keywords = isset( $_POST['tone_keywords'] ) ? wp_unslash( $_POST['tone_keywords'] ) : '';
        $tone_keywords = aimentor_sanitize_tone_keywords( $tone_keywords );

        $brand_preferences = aimentor_get_brand_preferences();
        $brand_tone        = isset( $brand_preferences['tone_keywords'] ) ? $brand_preferences['tone_keywords'] : '';

        if ( '' === $tone_keywords ) {
                $tone_keywords = $brand_tone;
        }

        $provider_labels = aimentor_get_provider_labels();
        $provider_key    = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : get_option( 'aimentor_provider', 'grok' );

        if ( ! array_key_exists( $provider_key, $provider_labels ) ) {
                $provider_key = get_option( 'aimentor_provider', 'grok' );
        }

        $provider = jaggrok_get_active_provider( $provider_key );

        if ( ! $provider instanceof AiMentor_Provider_Interface ) {
                aimentor_log_error(
                        'Invalid provider configuration for rewrite.',
                        array(
                                'provider' => $provider_key,
                                'user_id'  => get_current_user_id(),
                                'origin'   => 'rewrite',
                        )
                );

                wp_send_json_error( __( 'Provider configuration error.', 'aimentor' ) );
        }

        $requested_tier = isset( $_POST['tier'] ) ? sanitize_text_field( wp_unslash( $_POST['tier'] ) ) : 'quality';

        if ( function_exists( 'aimentor_sanitize_performance_tier' ) ) {
                $requested_tier = aimentor_sanitize_performance_tier( $requested_tier );
        } else {
                $requested_tier = jaggrok_normalize_performance_tier( $requested_tier );
        }

        $is_pro          = aimentor_is_pro_active();
        $supports_canvas = $provider->supports_canvas();
        $resolution      = jaggrok_resolve_generation_preset( $provider_key, 'content', $requested_tier, $supports_canvas, $is_pro );
        $tier            = $resolution['tier'];
        $model           = $resolution['model'];

        switch ( $provider_key ) {
                case 'openai':
                        $api_key = get_option( 'aimentor_openai_api_key' );
                        break;
                case 'anthropic':
                        $api_key = get_option( 'aimentor_anthropic_api_key' );
                        break;
                case 'grok':
                default:
                        $api_key = get_option( 'aimentor_xai_api_key' );
                        break;
        }

        if ( is_array( $brand_preferences ) ) {
                $brand_preferences['tone_keywords'] = $tone_keywords;
        } else {
                $brand_preferences = array(
                        'tone_keywords' => $tone_keywords,
                );
        }

        $prompt = aimentor_build_rewrite_prompt(
                $source,
                $tone_keywords,
                array(
                        'provider' => $provider_key,
                        'model'    => $model,
                        'tier'     => $tier,
                )
        );

        $request_context = array(
                'task'   => 'content',
                'tier'   => $tier,
                'brand'  => $brand_preferences,
                'intent' => 'rewrite',
        );

        if ( isset( $_POST['knowledge_ids'] ) && function_exists( 'aimentor_prepare_provider_knowledge_context' ) ) {
                $knowledge_context = aimentor_prepare_provider_knowledge_context(
                        wp_unslash( $_POST['knowledge_ids'] ),
                        $provider_key,
                        array(
                                'origin'  => 'rewrite',
                                'task'    => 'content',
                                'tier'    => $tier,
                                'user_id' => get_current_user_id(),
                        )
                );

                if ( ! empty( $knowledge_context ) ) {
                        $request_context['knowledge'] = $knowledge_context;
                }
        }

        $result = $provider->request(
                $prompt,
                array(
                        'api_key'    => $api_key,
                        'model'      => $model,
                        'max_tokens' => get_option( 'aimentor_max_tokens', 2000 ),
                        'context'    => $request_context,
                )
        );

        if ( is_wp_error( $result ) ) {
                $error_message = $result->get_error_message();

                if ( function_exists( 'aimentor_record_provider_usage' ) ) {
                        aimentor_record_provider_usage(
                                $provider_key,
                                'error',
                                array(
                                        'model'  => $model,
                                        'task'   => 'content',
                                        'tier'   => $tier,
                                        'origin' => 'rewrite',
                                )
                        );
                }

                aimentor_log_error(
                        $error_message . ' | Details: ' . wp_json_encode( $result->get_error_data() ),
                        array(
                                'provider' => $provider_key,
                                'model'    => $model,
                                'task'     => 'content',
                                'tier'     => $tier,
                                'origin'   => 'rewrite',
                                'user_id'  => get_current_user_id(),
                        )
                );

                $error_data = $result->get_error_data();

                if ( ! is_array( $error_data ) ) {
                        $error_data = array();
                }

                if ( function_exists( 'aimentor_record_usage_event' ) ) {
                        aimentor_record_usage_event(
                                array(
                                        'provider'   => $provider_key,
                                        'status'     => 'error',
                                        'task'       => $task,
                                        'tier'       => $tier,
                                        'model'      => $model,
                                        'origin'     => 'generation',
                                        'user_id'    => get_current_user_id(),
                                        'type'       => 'generation',
                                        'rate_limit' => isset( $error_data['rate_limit'] ) ? $error_data['rate_limit'] : array(),
                                )
                        );
                }

                if ( ! empty( $guardrail_warnings ) ) {
                        $error_data['warnings'] = array_values( array_map( 'sanitize_text_field', $guardrail_warnings ) );
                }

                if ( ! empty( $guardrail_state ) ) {
                        $error_data['guardrail_state'] = $guardrail_state;
                }

                $error_data['message'] = $error_message;

                wp_send_json_error( $error_data );
        }

        $rewritten = '';

        if ( isset( $result['content'] ) && is_string( $result['content'] ) ) {
                $rewritten = trim( $result['content'] );
        } elseif ( ! empty( $result['content_variations'][0]['html'] ) ) {
                $rewritten = trim( (string) $result['content_variations'][0]['html'] );
        }

        if ( '' === $rewritten ) {
                wp_send_json_error(
                        array(
                                'message' => __( 'The provider response did not include rewritten content.', 'aimentor' ),
                                'code'    => 'aimentor_empty_rewrite',
                        ),
                        500
                );
        }

        if ( function_exists( 'aimentor_record_provider_usage' ) ) {
                aimentor_record_provider_usage(
                        $provider_key,
                        'success',
                        array(
                                'model'  => $model,
                                'task'   => 'content',
                                'tier'   => $tier,
                                'origin' => 'rewrite',
                        )
                );
        }

        $response_payload = array(
                'provider'      => $provider_key,
                'model'         => $model,
                'tier'          => $tier,
                'rewritten'     => $rewritten,
                'tone_keywords' => $tone_keywords,
        );

        if ( ! empty( $result['rate_limit'] ) ) {
                $response_payload['rate_limit'] = $result['rate_limit'];
        }

        wp_send_json_success( $response_payload );
}

if ( ! function_exists( 'aimentor_rewrite_content_ajax' ) ) {
        function aimentor_rewrite_content_ajax() {
                jaggrok_rewrite_content_ajax();
        }
}

/**
 * Handle the AJAX request to generate Elementor content.
 */
function jaggrok_generate_page_ajax() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'aimentor_test' ) && ! wp_verify_nonce( $nonce, 'jaggrok_test' ) ) {
                wp_send_json_error(
                        array(
                                'message' => __( 'Security check failed.', 'aimentor' ),
                                'code'    => 'aimentor_invalid_nonce',
                        ),
                        403
                );
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
                wp_send_json_error(
                        array(
                                'message' => __( 'Insufficient permissions to generate content.', 'aimentor' ),
                                'code'    => 'aimentor_insufficient_permissions',
                        ),
                        403
                );
        }

        $quick_action_slug = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : '';

        if ( '' !== $quick_action_slug ) {
                if ( ! function_exists( 'aimentor_ajax_execute_quick_action' ) ) {
                        require_once AIMENTOR_PLUGIN_DIR . 'includes/settings.php';
                }

                aimentor_ajax_execute_quick_action();
                return;
        }

        $prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';
        $is_pro = aimentor_is_pro_active();

        $provider_labels = aimentor_get_provider_labels();
        $provider_key    = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : get_option( 'aimentor_provider', 'grok' );

        if ( ! array_key_exists( $provider_key, $provider_labels ) ) {
                $provider_key = get_option( 'aimentor_provider', 'grok' );
        }

        $provider = jaggrok_get_active_provider( $provider_key );

        if ( ! $provider instanceof AiMentor_Provider_Interface ) {
                aimentor_log_error(
                        'Invalid provider configuration.',
                        array(
                                'provider' => $provider_key,
                                'model'    => '',
                                'task'     => '',
                                'tier'     => '',
                                'user_id'  => get_current_user_id(),
                        )
                );

                wp_send_json_error( __( 'Provider configuration error.', 'aimentor' ) );
        }

        $requested_task_raw = '';
        foreach ( array( 'task', 'task_type' ) as $task_field ) {
                if ( ! isset( $_POST[ $task_field ] ) ) {
                        continue;
                }

                $candidate = sanitize_text_field( wp_unslash( $_POST[ $task_field ] ) );

                if ( '' === $candidate ) {
                        continue;
                }

                $requested_task_raw = $candidate;
                break;
        }

        $requested_tier_raw = '';
        foreach ( array( 'tier', 'performance_tier' ) as $tier_field ) {
                if ( ! isset( $_POST[ $tier_field ] ) ) {
                        continue;
                }

                $candidate = sanitize_text_field( wp_unslash( $_POST[ $tier_field ] ) );

                if ( '' === $candidate ) {
                        continue;
                }

                $requested_tier_raw = $candidate;
                break;
        }

        $requested_task = $requested_task_raw;
        $requested_tier = $requested_tier_raw;

        if ( function_exists( 'aimentor_sanitize_generation_type' ) ) {
                $requested_task = aimentor_sanitize_generation_type( $requested_task );
        } else {
                $requested_task = $requested_task ? jaggrok_normalize_generation_task( $requested_task ) : '';
        }

        if ( function_exists( 'aimentor_sanitize_performance_tier' ) ) {
                $requested_tier = aimentor_sanitize_performance_tier( $requested_tier );
        } else {
                $requested_tier = $requested_tier ? jaggrok_normalize_performance_tier( $requested_tier ) : '';
        }

        $supports_canvas = $provider->supports_canvas();
        $resolution = jaggrok_resolve_generation_preset( $provider_key, $requested_task, $requested_tier, $supports_canvas, $is_pro );
        $task       = $resolution['task'];
        $tier       = $resolution['tier'];
        $model      = $resolution['model'];

        switch ( $provider_key ) {
                case 'openai':
                        $api_key = get_option( 'aimentor_openai_api_key' );
                        break;
                case 'grok':
                default:
                        $api_key = get_option( 'aimentor_xai_api_key' );
                        break;
        }

        $variation_count = 1;

        if ( 'canvas' === $task ) {
                $requested_variations = 0;

                foreach ( array( 'variations', 'variation_count' ) as $variation_field ) {
                        if ( isset( $_POST[ $variation_field ] ) ) {
                                $requested_variations = absint( wp_unslash( $_POST[ $variation_field ] ) );

                                if ( $requested_variations > 0 ) {
                                        break;
                                }
                        }
                }

                if ( $requested_variations > 0 ) {
                        $variation_count = $requested_variations;
                } else {
                        /**
                         * Filter the number of canvas variations to request from the provider.
                         *
                         * @param int    $count        Default variation count.
                         * @param string $provider_key Active provider key.
                         * @param string $task         Normalized task.
                         * @param string $tier         Normalized tier.
                         */
                        $variation_count = apply_filters( 'aimentor_canvas_variation_count', 3, $provider_key, $task, $tier );
                }
        }

        if ( $variation_count < 1 ) {
                $variation_count = 1;
        }

        $request_context = array(
                'task' => $task,
                'tier' => $tier,
        );

        if ( isset( $_POST['knowledge_ids'] ) && function_exists( 'aimentor_prepare_provider_knowledge_context' ) ) {
                $knowledge_context = aimentor_prepare_provider_knowledge_context(
                        wp_unslash( $_POST['knowledge_ids'] ),
                        $provider_key,
                        array(
                                'origin'  => 'ajax',
                                'task'    => $task,
                                'tier'    => $tier,
                                'user_id' => get_current_user_id(),
                        )
                );

                if ( ! empty( $knowledge_context ) ) {
                        $request_context['knowledge'] = $knowledge_context;
                }
        }

        $guardrail_warnings = array();
        $guardrail_state    = array();

        if ( function_exists( 'aimentor_enforce_usage_guardrails' ) ) {
                $guardrail_check = aimentor_enforce_usage_guardrails(
                        array(
                                'provider' => $provider_key,
                                'task'     => $task,
                                'tier'     => $tier,
                                'origin'   => 'ajax',
                                'user_id'  => get_current_user_id(),
                                'type'     => 'generation',
                        )
                );

                if ( is_wp_error( $guardrail_check ) ) {
                        $error_data = $guardrail_check->get_error_data();

                        if ( ! is_array( $error_data ) ) {
                                $error_data = array();
                        }

                        $error_data['message'] = $guardrail_check->get_error_message();
                        $error_data['code']    = $guardrail_check->get_error_code();

                        wp_send_json_error( $error_data, 429 );
                }

                $guardrail_warnings = isset( $guardrail_check['warnings'] ) ? array_filter( (array) $guardrail_check['warnings'] ) : array();
                $guardrail_state    = isset( $guardrail_check['state'] ) && is_array( $guardrail_check['state'] ) ? $guardrail_check['state'] : array();
        }

        $result = $provider->request( $prompt, array(
                'api_key'    => $api_key,
                'model'      => $model,
                'max_tokens' => get_option( 'aimentor_max_tokens', 2000 ),
                'context'    => $request_context,
                'variations' => $variation_count,
        ) );

        if ( is_wp_error( $result ) ) {
                $error_message = $result->get_error_message();

                if ( function_exists( 'aimentor_record_provider_usage' ) ) {
                        aimentor_record_provider_usage(
                                $provider_key,
                                'error',
                                array(
                                        'model'  => $model,
                                        'task'   => $task,
                                        'tier'   => $tier,
                                        'origin' => 'generation',
                                )
                        );
                }

                aimentor_log_error(
                        $error_message . ' | Details: ' . wp_json_encode( $result->get_error_data() ),
                        array(
                                'provider' => $provider_key,
                                'model'    => $model,
                                'task'     => $task,
                                'tier'     => $tier,
                                'user_id'  => get_current_user_id(),
                        )
                );

                $error_data = $result->get_error_data();

                if ( ! is_array( $error_data ) ) {
                        $error_data = array();
                }

                $error_data['message'] = $error_message;

                wp_send_json_error( $error_data );
        }

        $response_payload = array(
                'provider'       => $provider_key,
                'provider_label' => $provider_labels[ $provider_key ] ?? ucfirst( $provider_key ),
                'model'          => $model,
                'task'           => $task,
                'tier'           => $tier,
        );

        if ( ! empty( $result['rate_limit'] ) ) {
                $response_payload['rate_limit'] = $result['rate_limit'];
        }

        if ( function_exists( 'aimentor_record_usage_event' ) ) {
                aimentor_record_usage_event(
                        array(
                                'provider'   => $provider_key,
                                'status'     => 'success',
                                'task'       => $task,
                                'tier'       => $tier,
                                'model'      => $model,
                                'origin'     => 'generation',
                                'user_id'    => get_current_user_id(),
                                'type'       => 'generation',
                                'rate_limit' => isset( $result['rate_limit'] ) ? $result['rate_limit'] : array(),
                        )
                );
        }

        $history_entry = null;

        if ( function_exists( 'aimentor_store_generation_history_entry' ) ) {
                $history_entry = aimentor_store_generation_history_entry(
                        $prompt,
                        $provider_key,
                        array(
                                'task'       => $task,
                                'tier'       => $tier,
                                'model'      => $model,
                                'origin'     => 'generation',
                                'user_id'    => get_current_user_id(),
                                'rate_limit' => isset( $result['rate_limit'] ) ? $result['rate_limit'] : array(),
                                'tokens'     => isset( $result['tokens'] ) ? absint( $result['tokens'] ) : 0,
                        )
                );

                if ( is_wp_error( $history_entry ) && function_exists( 'aimentor_log_error' ) ) {
                        aimentor_log_error(
                                $history_entry->get_error_message(),
                                array(
                                        'provider' => $provider_key,
                                        'model'    => $model,
                                        'task'     => $task,
                                        'tier'     => $tier,
                                        'user_id'  => get_current_user_id(),
                                )
                        );
                }
        }

        $response_payload['history_recorded'] = $history_entry && ! is_wp_error( $history_entry );

        if ( ! empty( $guardrail_warnings ) ) {
                $response_payload['warnings'] = array_values( array_map( 'sanitize_text_field', $guardrail_warnings ) );
        }

        if ( ! empty( $guardrail_state ) ) {
                $response_payload['guardrail_state'] = $guardrail_state;
        }

        if ( isset( $result['type'] ) && 'canvas' === $result['type'] ) {
                if ( function_exists( 'aimentor_record_provider_usage' ) ) {
                        aimentor_record_provider_usage(
                                $provider_key,
                                'success',
                                array(
                                        'model'  => $model,
                                        'task'   => $task,
                                        'tier'   => $tier,
                                        'origin' => 'generation',
                                )
                        );
                }

                if ( function_exists( 'aimentor_maybe_archive_generation_payload' ) ) {
                        aimentor_maybe_archive_generation_payload(
                                $result['content'],
                                array(
                                        'type'     => 'canvas',
                                        'prompt'   => $prompt,
                                        'provider' => $provider_key,
                                        'model'    => $model,
                                        'task'     => $task,
                                        'tier'     => $tier,
                                )
                        );
                }

                $response_payload['canvas_json'] = $result['content'];

                if ( ! empty( $result['canvas_variations'] ) && is_array( $result['canvas_variations'] ) ) {
                        $response_payload['canvas_variations'] = array_values( $result['canvas_variations'] );
                }

                if ( ! empty( $result['summary'] ) && is_string( $result['summary'] ) ) {
                        $response_payload['summary'] = $result['summary'];
                }

                wp_send_json_success( $response_payload );
        }

        if ( function_exists( 'aimentor_record_provider_usage' ) ) {
                aimentor_record_provider_usage(
                        $provider_key,
                        'success',
                        array(
                                'model'  => $model,
                                'task'   => $task,
                                'tier'   => $tier,
                                'origin' => 'generation',
                        )
                );
        }

        if ( function_exists( 'aimentor_maybe_archive_generation_payload' ) ) {
                aimentor_maybe_archive_generation_payload(
                        $result['content'],
                        array(
                                'type'     => 'content',
                                'prompt'   => $prompt,
                                'provider' => $provider_key,
                                'model'    => $model,
                                'task'     => $task,
                                'tier'     => $tier,
                        )
                );
        }

        $response_payload['html'] = $result['content'];

        if ( ! empty( $result['content_variations'] ) && is_array( $result['content_variations'] ) ) {
                $response_payload['content_variations'] = array_values( $result['content_variations'] );
        }

        if ( ! empty( $result['summary'] ) && is_string( $result['summary'] ) ) {
                $response_payload['summary'] = $result['summary'];
        }
        wp_send_json_success( $response_payload );
}

if ( ! function_exists( 'aimentor_generate_page_ajax' ) ) {
        function aimentor_generate_page_ajax() {
                jaggrok_generate_page_ajax();
        }
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
        require_once AIMENTOR_PLUGIN_DIR . 'includes/settings.php';
        require_once AIMENTOR_PLUGIN_DIR . 'includes/cli.php';

        if ( ! class_exists( 'AiMentor_CLI_Command' ) ) {
                /**
                 * Manage AiMentor generation workflows from WP-CLI.
                 */
                class AiMentor_CLI_Command extends WP_CLI_Command {
                        /**
                         * Generate Elementor content or canvas JSON via the configured provider.
                         *
                         * ## OPTIONS
                         *
                         * [--prompt=<prompt>]
                         * : Prompt text to send to the provider. Required.
                         *
                         * [--provider=<provider>]
                         * : Provider slug to use (grok or openai). Defaults to the saved provider option.
                         *
                         * [--task=<task>]
                         * : Optional generation task to request (content or canvas).
                         *
                         * [--tier=<tier>]
                         * : Optional performance tier to request (fast or quality).
                         *
                         * [--out=<file>]
                         * : Optional file path where the generated output should be written.
                         *
                         * [--max_tokens=<tokens>]
                         * : Override the stored max token limit for this run.
                         *
                         * ## EXAMPLES
                         *
                         *     wp aimentor generate --prompt="Homepage hero for a bakery" --provider=grok
                         *     wp aimentor generate --prompt="Canvas layout" --task=canvas --out=canvas.json
                         *
                         * @when after_wp_load
                         *
                         * @param array $args       Positional arguments (unused).
                         * @param array $assoc_args Associative arguments.
                         */
                        public function generate( $args, $assoc_args ) {
                                $prompt = \WP_CLI\Utils\get_flag_value( $assoc_args, 'prompt', '' );
                                $prompt = sanitize_textarea_field( wp_unslash( (string) $prompt ) );

                                if ( '' === $prompt ) {
                                        WP_CLI::error( __( 'Prompt is required.', 'aimentor' ) );
                                }

                                $stored_provider = get_option( 'aimentor_provider', 'grok' );
                                $provider_key    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'provider', $stored_provider );
                                $provider_key    = sanitize_key( $provider_key );

                                if ( function_exists( 'aimentor_sanitize_provider' ) ) {
                                        $provider_key = aimentor_sanitize_provider( $provider_key );
                                }

                                $requested_task = sanitize_key( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'task', '' ) );
                                $requested_tier = sanitize_key( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'tier', '' ) );

                                $provider = jaggrok_get_active_provider( $provider_key );

                                if ( ! $provider instanceof AiMentor_Provider_Interface ) {
                                        WP_CLI::error( __( 'Provider configuration error.', 'aimentor' ) );
                                }

                                $is_pro          = function_exists( 'aimentor_is_pro_active' ) ? aimentor_is_pro_active() : false;
                                $supports_canvas = $provider->supports_canvas();
                                $resolution      = jaggrok_resolve_generation_preset(
                                        $provider_key,
                                        $requested_task,
                                        $requested_tier,
                                        $supports_canvas,
                                        $is_pro
                                );

                                $task  = $resolution['task'];
                                $tier  = $resolution['tier'];
                                $model = $resolution['model'];

                                $max_tokens_flag = \WP_CLI\Utils\get_flag_value( $assoc_args, 'max_tokens', null );
                                $max_tokens      = null !== $max_tokens_flag ? absint( $max_tokens_flag ) : get_option( 'aimentor_max_tokens', 2000 );

                                if ( function_exists( 'aimentor_sanitize_max_tokens' ) ) {
                                        $max_tokens = aimentor_sanitize_max_tokens( $max_tokens );
                                }

                                switch ( $provider_key ) {
                                        case 'openai':
                                                $api_key = get_option( 'aimentor_openai_api_key' );
                                                break;
                                        case 'grok':
                                        default:
                                                $api_key = get_option( 'aimentor_xai_api_key' );
                                                break;
                                }

                                $context = array(
                                        'task'   => $task,
                                        'tier'   => $tier,
                                        'origin' => 'cli',
                                );

                                $result = $provider->request(
                                        $prompt,
                                        array(
                                                'api_key'    => $api_key,
                                                'model'      => $model,
                                                'max_tokens' => $max_tokens,
                                                'context'    => $context,
                                        )
                                );

                                if ( is_wp_error( $result ) ) {
                                        if ( function_exists( 'aimentor_record_provider_usage' ) ) {
                                                aimentor_record_provider_usage(
                                                        $provider_key,
                                                        'error',
                                                        array(
                                                                'model'  => $model,
                                                                'task'   => $task,
                                                                'tier'   => $tier,
                                                                'origin' => 'cli',
                                                        )
                                                );
                                        }

                                        if ( function_exists( 'aimentor_log_error' ) ) {
                                                aimentor_log_error(
                                                        $result->get_error_message(),
                                                        array(
                                                                'provider' => $provider_key,
                                                                'model'    => $model,
                                                                'task'     => $task,
                                                                'tier'     => $tier,
                                                                'origin'   => 'cli',
                                                        )
                                                );
                                        }

                                        $error_data = $result->get_error_data();

                                        if ( is_array( $error_data ) && isset( $error_data['rate_limit'] ) ) {
                                                WP_CLI::warning( sprintf( 'Rate limit details: %s', wp_json_encode( $error_data['rate_limit'] ) ) );
                                        }

                                        WP_CLI::error( $result->get_error_message() );
                                }

                                if ( function_exists( 'aimentor_record_provider_usage' ) ) {
                                        aimentor_record_provider_usage(
                                                $provider_key,
                                                'success',
                                                array(
                                                        'model'  => $model,
                                                        'task'   => $task,
                                                        'tier'   => $tier,
                                                        'origin' => 'cli',
                                                )
                                        );
                                }

                                if ( function_exists( 'aimentor_store_generation_history_entry' ) ) {
                                        $history_result = aimentor_store_generation_history_entry( $prompt, $provider_key );

                                        if ( is_wp_error( $history_result ) ) {
                                                WP_CLI::warning( $history_result->get_error_message() );
                                        }
                                }

                                if ( function_exists( 'aimentor_maybe_archive_generation_payload' ) ) {
                                        aimentor_maybe_archive_generation_payload(
                                                $result['content'],
                                                array(
                                                        'type'     => $result['type'],
                                                        'prompt'   => $prompt,
                                                        'provider' => $provider_key,
                                                        'model'    => $model,
                                                        'task'     => $task,
                                                        'tier'     => $tier,
                                                        'origin'   => 'cli',
                                                )
                                        );
                                }

                                $output = 'canvas' === ( $result['type'] ?? 'content' )
                                        ? wp_json_encode( $result['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
                                        : (string) $result['content'];

                                if ( false === $output || null === $output ) {
                                        $output = '';
                                }

                                $summary = sprintf(
                                        /* translators: 1: provider, 2: task, 3: tier */
                                        __( 'Generated output with %1$s (%2$s/%3$s).', 'aimentor' ),
                                        $provider_key,
                                        $task,
                                        $tier
                                );

                                WP_CLI::log( $summary );

                                if ( '' !== $output ) {
                                        WP_CLI::line( $output );
                                }

                                if ( ! empty( $result['rate_limit'] ) ) {
                                        WP_CLI::log( sprintf( 'Rate limit: %s', wp_json_encode( $result['rate_limit'] ) ) );
                                }

                                $out_path = \WP_CLI\Utils\get_flag_value( $assoc_args, 'out', '' );
                                $out_path = is_string( $out_path ) ? trim( $out_path ) : '';

                                if ( '' !== $out_path ) {
                                        $normalized_path = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $out_path ) : $out_path;
                                        $dir             = dirname( $normalized_path );

                                        if ( ! file_exists( $dir ) && function_exists( 'wp_mkdir_p' ) ) {
                                                wp_mkdir_p( $dir );
                                        }

                                        $bytes = file_put_contents( $normalized_path, $output );

                                        if ( false === $bytes ) {
                                                WP_CLI::warning( sprintf( __( 'Unable to write output to %s.', 'aimentor' ), $normalized_path ) );
                                        } else {
                                                WP_CLI::success( sprintf( __( 'Saved generation output to %s.', 'aimentor' ), $normalized_path ) );
                                                return;
                                        }
                                }

                                WP_CLI::success( __( 'Generation complete.', 'aimentor' ) );
                        }
                }
        }

        WP_CLI::add_command( 'aimentor', 'AiMentor_CLI_Command' );
}

register_uninstall_hook( AIMENTOR_PLUGIN_FILE, 'aimentor_uninstall' );
/**
 * Invoke the uninstall cleanup routine.
 */
function aimentor_uninstall() {
        $uninstall_file = AIMENTOR_PLUGIN_DIR . 'uninstall.php';

        if ( file_exists( $uninstall_file ) ) {
                include $uninstall_file;
        }
}

// Emergency widget registration fallbacks.
$emergency_register = function() {
        if ( ! did_action( 'elementor/loaded' ) || did_action( 'elementor/widgets/register' ) ) {
                return;
        }

        add_action( 'elementor/widgets/register', function( $widgets_manager ) {
                require_once AIMENTOR_PLUGIN_DIR . 'includes/elementor-widget.php';

                if ( class_exists( 'AiMentor_AI_Generator_Widget' ) && ! class_exists( 'JagGrok_AI_Generator_Widget' ) ) {
                        class_alias( 'AiMentor_AI_Generator_Widget', 'JagGrok_AI_Generator_Widget' );
                }

                $widgets_manager->register( new AiMentor_AI_Generator_Widget() );
        }, 999 );
};

add_action( 'init', $emergency_register );
add_action( 'plugins_loaded', $emergency_register );
add_action( 'wp_loaded', $emergency_register );
