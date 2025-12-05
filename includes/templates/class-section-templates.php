<?php
/**
 * Section Templates Library
 *
 * Manages pre-built Elementor section templates for AI generation.
 *
 * @package AiMentor
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Section_Templates {

	/**
	 * Templates directory path.
	 *
	 * @var string
	 */
	protected $templates_dir;

	/**
	 * Cached templates.
	 *
	 * @var array
	 */
	protected $cache = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->templates_dir = AIMENTOR_PLUGIN_DIR . 'includes/templates/';
	}

	/**
	 * Get all available section categories.
	 *
	 * @return array Section categories with metadata.
	 */
	public function get_categories() {
		return [
			'hero' => [
				'label'       => __( 'Hero Sections', 'aimentor' ),
				'description' => __( 'Eye-catching hero sections with headlines, CTAs, and imagery.', 'aimentor' ),
				'icon'        => 'fas fa-flag',
			],
			'features' => [
				'label'       => __( 'Features', 'aimentor' ),
				'description' => __( 'Showcase features, benefits, or services in grid layouts.', 'aimentor' ),
				'icon'        => 'fas fa-th-large',
			],
			'testimonials' => [
				'label'       => __( 'Testimonials', 'aimentor' ),
				'description' => __( 'Customer reviews and social proof sections.', 'aimentor' ),
				'icon'        => 'fas fa-quote-left',
			],
			'pricing' => [
				'label'       => __( 'Pricing', 'aimentor' ),
				'description' => __( 'Pricing tables and plan comparisons.', 'aimentor' ),
				'icon'        => 'fas fa-tags',
			],
			'cta' => [
				'label'       => __( 'Call to Action', 'aimentor' ),
				'description' => __( 'Conversion-focused CTA sections.', 'aimentor' ),
				'icon'        => 'fas fa-bullhorn',
			],
			'faq' => [
				'label'       => __( 'FAQ', 'aimentor' ),
				'description' => __( 'Frequently asked questions with accordions.', 'aimentor' ),
				'icon'        => 'fas fa-question-circle',
			],
			'team' => [
				'label'       => __( 'Team', 'aimentor' ),
				'description' => __( 'Team member showcases and about sections.', 'aimentor' ),
				'icon'        => 'fas fa-users',
			],
			'contact' => [
				'label'       => __( 'Contact', 'aimentor' ),
				'description' => __( 'Contact information and form sections.', 'aimentor' ),
				'icon'        => 'fas fa-envelope',
			],
			'stats' => [
				'label'       => __( 'Statistics', 'aimentor' ),
				'description' => __( 'Number counters and achievement displays.', 'aimentor' ),
				'icon'        => 'fas fa-chart-bar',
			],
			'services' => [
				'label'       => __( 'Services', 'aimentor' ),
				'description' => __( 'Service offerings and capabilities.', 'aimentor' ),
				'icon'        => 'fas fa-concierge-bell',
			],
			'about' => [
				'label'       => __( 'About', 'aimentor' ),
				'description' => __( 'Company or personal about sections.', 'aimentor' ),
				'icon'        => 'fas fa-info-circle',
			],
			'footer' => [
				'label'       => __( 'Footer', 'aimentor' ),
				'description' => __( 'Footer layouts with links and info.', 'aimentor' ),
				'icon'        => 'fas fa-shoe-prints',
			],
		];
	}

	/**
	 * Get templates for a specific category.
	 *
	 * @param string $category Category slug.
	 * @return array Templates in the category.
	 */
	public function get_templates_by_category( $category ) {
		$category = sanitize_key( $category );
		$category_dir = $this->templates_dir . $category . '/';

		if ( ! is_dir( $category_dir ) ) {
			return [];
		}

		if ( isset( $this->cache[ $category ] ) ) {
			return $this->cache[ $category ];
		}

		$templates = [];
		$files = glob( $category_dir . '*.json' );

		if ( ! $files ) {
			return [];
		}

		foreach ( $files as $file ) {
			$slug = basename( $file, '.json' );
			$template = $this->load_template( $category, $slug );

			if ( $template ) {
				$templates[ $slug ] = $template;
			}
		}

		$this->cache[ $category ] = $templates;

		return $templates;
	}

	/**
	 * Load a specific template.
	 *
	 * @param string $category Category slug.
	 * @param string $slug     Template slug.
	 * @return array|null Template data or null.
	 */
	public function load_template( $category, $slug ) {
		$category = sanitize_key( $category );
		$slug = sanitize_key( $slug );
		$file = $this->templates_dir . $category . '/' . $slug . '.json';

		if ( ! file_exists( $file ) ) {
			return null;
		}

		$content = file_get_contents( $file );
		$data = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $data;
	}

	/**
	 * Get all templates across all categories.
	 *
	 * @return array All templates organized by category.
	 */
	public function get_all_templates() {
		$all = [];
		$categories = $this->get_categories();

		foreach ( array_keys( $categories ) as $category ) {
			$templates = $this->get_templates_by_category( $category );
			if ( ! empty( $templates ) ) {
				$all[ $category ] = $templates;
			}
		}

		return $all;
	}

	/**
	 * Get template for prompt injection.
	 *
	 * @param string $category Category slug.
	 * @param string $slug     Template slug.
	 * @return string Template JSON for prompt or empty string.
	 */
	public function get_template_for_prompt( $category, $slug ) {
		$template = $this->load_template( $category, $slug );

		if ( ! $template || ! isset( $template['elements'] ) ) {
			return '';
		}

		return wp_json_encode( $template['elements'], JSON_PRETTY_PRINT );
	}

	/**
	 * Get template metadata without full elements.
	 *
	 * @param string $category Category slug.
	 * @param string $slug     Template slug.
	 * @return array|null Metadata or null.
	 */
	public function get_template_meta( $category, $slug ) {
		$template = $this->load_template( $category, $slug );

		if ( ! $template ) {
			return null;
		}

		return [
			'name'        => $template['name'] ?? $slug,
			'description' => $template['description'] ?? '',
			'category'    => $category,
			'slug'        => $slug,
			'widgets'     => $template['widgets_used'] ?? [],
			'preview'     => $template['preview_url'] ?? '',
		];
	}

	/**
	 * Get templates formatted for UI dropdown.
	 *
	 * @return array Formatted for select options.
	 */
	public function get_templates_for_select() {
		$options = [];
		$categories = $this->get_categories();

		foreach ( $categories as $cat_slug => $cat_meta ) {
			$templates = $this->get_templates_by_category( $cat_slug );

			if ( empty( $templates ) ) {
				continue;
			}

			$group = [
				'label'   => $cat_meta['label'],
				'options' => [],
			];

			foreach ( $templates as $slug => $template ) {
				$group['options'][] = [
					'value' => $cat_slug . '::' . $slug,
					'label' => $template['name'] ?? $slug,
				];
			}

			$options[] = $group;
		}

		return $options;
	}

	/**
	 * Parse a template selector value.
	 *
	 * @param string $value Value like "hero::centered".
	 * @return array With 'category' and 'slug' keys.
	 */
	public function parse_selector_value( $value ) {
		$parts = explode( '::', $value, 2 );

		return [
			'category' => isset( $parts[0] ) ? sanitize_key( $parts[0] ) : '',
			'slug'     => isset( $parts[1] ) ? sanitize_key( $parts[1] ) : '',
		];
	}
}
