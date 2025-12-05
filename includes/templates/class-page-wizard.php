<?php
/**
 * Page Wizard System
 *
 * Guided page generation with page type presets and section assembly.
 *
 * @package AiMentor
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Page_Wizard {

	/**
	 * Section templates instance.
	 *
	 * @var AiMentor_Section_Templates
	 */
	protected $templates;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->templates = new AiMentor_Section_Templates();
	}

	/**
	 * Get all available page types.
	 *
	 * @return array Page type definitions.
	 */
	public function get_page_types() {
		return [
			'landing' => [
				'name'        => __( 'Landing Page', 'aimentor' ),
				'description' => __( 'High-converting landing page with hero, features, testimonials, and CTA.', 'aimentor' ),
				'icon'        => 'fas fa-rocket',
				'sections'    => [ 'hero', 'features', 'testimonials', 'stats', 'cta' ],
				'questions'   => [
					'business_name' => [
						'label'       => __( 'Business/Product Name', 'aimentor' ),
						'placeholder' => __( 'e.g., Acme Software', 'aimentor' ),
						'required'    => true,
					],
					'headline' => [
						'label'       => __( 'Main Headline', 'aimentor' ),
						'placeholder' => __( 'e.g., Transform Your Business Today', 'aimentor' ),
						'required'    => true,
					],
					'target_audience' => [
						'label'       => __( 'Target Audience', 'aimentor' ),
						'placeholder' => __( 'e.g., Small business owners, marketers', 'aimentor' ),
						'required'    => false,
					],
					'key_benefits' => [
						'label'       => __( 'Key Benefits (comma-separated)', 'aimentor' ),
						'placeholder' => __( 'e.g., Save time, Increase revenue, Easy to use', 'aimentor' ),
						'required'    => false,
					],
					'cta_text' => [
						'label'       => __( 'Call-to-Action Text', 'aimentor' ),
						'placeholder' => __( 'e.g., Start Free Trial', 'aimentor' ),
						'required'    => false,
					],
				],
			],

			'about' => [
				'name'        => __( 'About Page', 'aimentor' ),
				'description' => __( 'Company or personal about page with story, team, and values.', 'aimentor' ),
				'icon'        => 'fas fa-info-circle',
				'sections'    => [ 'hero', 'about', 'team', 'stats', 'cta' ],
				'questions'   => [
					'company_name' => [
						'label'       => __( 'Company/Person Name', 'aimentor' ),
						'placeholder' => __( 'e.g., Acme Inc.', 'aimentor' ),
						'required'    => true,
					],
					'founding_story' => [
						'label'       => __( 'Brief Story/Background', 'aimentor' ),
						'placeholder' => __( 'e.g., Founded in 2020 to solve...', 'aimentor' ),
						'required'    => false,
					],
					'mission' => [
						'label'       => __( 'Mission Statement', 'aimentor' ),
						'placeholder' => __( 'e.g., Empowering businesses to...', 'aimentor' ),
						'required'    => false,
					],
					'team_size' => [
						'label'       => __( 'Team Members to Feature', 'aimentor' ),
						'placeholder' => __( 'e.g., 3', 'aimentor' ),
						'required'    => false,
					],
				],
			],

			'services' => [
				'name'        => __( 'Services Page', 'aimentor' ),
				'description' => __( 'Showcase your services with descriptions and pricing.', 'aimentor' ),
				'icon'        => 'fas fa-concierge-bell',
				'sections'    => [ 'hero', 'services', 'features', 'testimonials', 'cta' ],
				'questions'   => [
					'business_name' => [
						'label'       => __( 'Business Name', 'aimentor' ),
						'placeholder' => __( 'e.g., Pro Design Agency', 'aimentor' ),
						'required'    => true,
					],
					'services_list' => [
						'label'       => __( 'Services Offered (comma-separated)', 'aimentor' ),
						'placeholder' => __( 'e.g., Web Design, Branding, SEO', 'aimentor' ),
						'required'    => true,
					],
					'unique_value' => [
						'label'       => __( 'What Makes You Different', 'aimentor' ),
						'placeholder' => __( 'e.g., 10+ years experience, 500+ projects', 'aimentor' ),
						'required'    => false,
					],
				],
			],

			'pricing' => [
				'name'        => __( 'Pricing Page', 'aimentor' ),
				'description' => __( 'Pricing plans with feature comparisons and FAQ.', 'aimentor' ),
				'icon'        => 'fas fa-tags',
				'sections'    => [ 'hero', 'pricing', 'features', 'faq', 'cta' ],
				'questions'   => [
					'product_name' => [
						'label'       => __( 'Product/Service Name', 'aimentor' ),
						'placeholder' => __( 'e.g., Pro Plan', 'aimentor' ),
						'required'    => true,
					],
					'pricing_tiers' => [
						'label'       => __( 'Pricing Tiers (e.g., Basic $19, Pro $49)', 'aimentor' ),
						'placeholder' => __( 'e.g., Starter $19/mo, Pro $49/mo, Enterprise $99/mo', 'aimentor' ),
						'required'    => true,
					],
					'billing_period' => [
						'label'       => __( 'Billing Period', 'aimentor' ),
						'placeholder' => __( 'e.g., monthly, yearly', 'aimentor' ),
						'required'    => false,
					],
				],
			],

			'contact' => [
				'name'        => __( 'Contact Page', 'aimentor' ),
				'description' => __( 'Contact information, form, and location map.', 'aimentor' ),
				'icon'        => 'fas fa-envelope',
				'sections'    => [ 'hero', 'contact', 'faq' ],
				'questions'   => [
					'business_name' => [
						'label'       => __( 'Business Name', 'aimentor' ),
						'placeholder' => __( 'e.g., Acme Inc.', 'aimentor' ),
						'required'    => true,
					],
					'address' => [
						'label'       => __( 'Address', 'aimentor' ),
						'placeholder' => __( 'e.g., 123 Main St, New York, NY', 'aimentor' ),
						'required'    => false,
					],
					'phone' => [
						'label'       => __( 'Phone Number', 'aimentor' ),
						'placeholder' => __( 'e.g., (555) 123-4567', 'aimentor' ),
						'required'    => false,
					],
					'email' => [
						'label'       => __( 'Email Address', 'aimentor' ),
						'placeholder' => __( 'e.g., hello@example.com', 'aimentor' ),
						'required'    => false,
					],
				],
			],

			'portfolio' => [
				'name'        => __( 'Portfolio Page', 'aimentor' ),
				'description' => __( 'Showcase work samples and case studies.', 'aimentor' ),
				'icon'        => 'fas fa-briefcase',
				'sections'    => [ 'hero', 'features', 'testimonials', 'cta' ],
				'questions'   => [
					'name' => [
						'label'       => __( 'Your Name/Company', 'aimentor' ),
						'placeholder' => __( 'e.g., John Doe Design', 'aimentor' ),
						'required'    => true,
					],
					'specialty' => [
						'label'       => __( 'Specialty/Expertise', 'aimentor' ),
						'placeholder' => __( 'e.g., UI/UX Design, Photography', 'aimentor' ),
						'required'    => true,
					],
					'project_types' => [
						'label'       => __( 'Project Types (comma-separated)', 'aimentor' ),
						'placeholder' => __( 'e.g., Websites, Mobile Apps, Branding', 'aimentor' ),
						'required'    => false,
					],
				],
			],

			'blog' => [
				'name'        => __( 'Blog Homepage', 'aimentor' ),
				'description' => __( 'Blog landing with featured posts and categories.', 'aimentor' ),
				'icon'        => 'fas fa-blog',
				'sections'    => [ 'hero', 'features', 'cta' ],
				'questions'   => [
					'blog_name' => [
						'label'       => __( 'Blog Name', 'aimentor' ),
						'placeholder' => __( 'e.g., The Marketing Blog', 'aimentor' ),
						'required'    => true,
					],
					'topics' => [
						'label'       => __( 'Main Topics (comma-separated)', 'aimentor' ),
						'placeholder' => __( 'e.g., Marketing, SEO, Social Media', 'aimentor' ),
						'required'    => false,
					],
					'subscribe_cta' => [
						'label'       => __( 'Newsletter CTA', 'aimentor' ),
						'placeholder' => __( 'e.g., Subscribe for weekly tips', 'aimentor' ),
						'required'    => false,
					],
				],
			],

			'coming_soon' => [
				'name'        => __( 'Coming Soon', 'aimentor' ),
				'description' => __( 'Pre-launch page with countdown and email signup.', 'aimentor' ),
				'icon'        => 'fas fa-hourglass-half',
				'sections'    => [ 'hero', 'features', 'cta' ],
				'questions'   => [
					'product_name' => [
						'label'       => __( 'Product/Site Name', 'aimentor' ),
						'placeholder' => __( 'e.g., Amazing App', 'aimentor' ),
						'required'    => true,
					],
					'launch_date' => [
						'label'       => __( 'Launch Date', 'aimentor' ),
						'placeholder' => __( 'e.g., January 2025', 'aimentor' ),
						'required'    => false,
					],
					'teaser' => [
						'label'       => __( 'Teaser Description', 'aimentor' ),
						'placeholder' => __( 'e.g., Something big is coming...', 'aimentor' ),
						'required'    => false,
					],
				],
			],
		];
	}

	/**
	 * Get a specific page type definition.
	 *
	 * @param string $type Page type slug.
	 * @return array|null Page type definition or null.
	 */
	public function get_page_type( $type ) {
		$types = $this->get_page_types();
		return isset( $types[ $type ] ) ? $types[ $type ] : null;
	}

	/**
	 * Build a prompt for page generation.
	 *
	 * @param string $page_type Page type slug.
	 * @param array  $answers   User's answers to questions.
	 * @param array  $context   Additional context (brand, knowledge, etc.).
	 * @return string Generated prompt.
	 */
	public function build_page_prompt( $page_type, $answers = [], $context = [] ) {
		$type_def = $this->get_page_type( $page_type );

		if ( ! $type_def ) {
			return '';
		}

		$prompt = sprintf(
			"Generate a complete %s with the following sections: %s.\n\n",
			$type_def['name'],
			implode( ', ', $type_def['sections'] )
		);

		$prompt .= "## Page Details\n";

		foreach ( $type_def['questions'] as $key => $question ) {
			if ( isset( $answers[ $key ] ) && '' !== trim( $answers[ $key ] ) ) {
				$prompt .= sprintf( "- %s: %s\n", $question['label'], $answers[ $key ] );
			}
		}

		$prompt .= "\n## Section Requirements\n";

		foreach ( $type_def['sections'] as $section ) {
			$section_info = $this->get_section_guidance( $section );
			$prompt .= sprintf( "- **%s Section**: %s\n", ucfirst( $section ), $section_info );
		}

		$prompt .= "\n## Output Requirements\n";
		$prompt .= "- Create a complete, professional page layout\n";
		$prompt .= "- Use realistic content based on the provided details\n";
		$prompt .= "- Ensure visual hierarchy and consistent styling\n";
		$prompt .= "- Include appropriate calls-to-action\n";

		return $prompt;
	}

	/**
	 * Get guidance text for a section type.
	 *
	 * @param string $section Section type.
	 * @return string Guidance text.
	 */
	protected function get_section_guidance( $section ) {
		$guidance = [
			'hero'         => 'Eye-catching hero with headline, subheadline, and primary CTA button.',
			'features'     => 'Feature boxes highlighting key benefits with icons.',
			'testimonials' => 'Customer testimonials with quotes, names, and ratings.',
			'pricing'      => 'Pricing tiers with feature lists and signup buttons.',
			'cta'          => 'Strong call-to-action encouraging the next step.',
			'faq'          => 'Common questions with expandable answers.',
			'team'         => 'Team members with photos, names, and titles.',
			'contact'      => 'Contact information with address, phone, email.',
			'stats'        => 'Key metrics and achievements with animated counters.',
			'services'     => 'Service offerings with descriptions and icons.',
			'about'        => 'Company story, mission, and values.',
			'footer'       => 'Footer with links, social icons, and copyright.',
		];

		return isset( $guidance[ $section ] ) ? $guidance[ $section ] : 'Standard ' . $section . ' section.';
	}

	/**
	 * Build a section-specific prompt.
	 *
	 * @param string $section_type Section type (hero, features, etc.).
	 * @param string $user_context Additional user context/requirements.
	 * @param array  $context      Generation context.
	 * @return string Generated prompt.
	 */
	public function build_section_prompt( $section_type, $user_context = '', $context = [] ) {
		$categories = $this->templates->get_categories();

		if ( ! isset( $categories[ $section_type ] ) ) {
			return $user_context;
		}

		$category = $categories[ $section_type ];

		$prompt = sprintf(
			"Generate a %s section.\n\n",
			$category['label']
		);

		$prompt .= sprintf( "Section Purpose: %s\n\n", $category['description'] );

		if ( '' !== $user_context ) {
			$prompt .= "## Specific Requirements\n";
			$prompt .= $user_context . "\n\n";
		}

		// Add template reference if available
		$templates = $this->templates->get_templates_by_category( $section_type );

		if ( ! empty( $templates ) ) {
			$first_template = reset( $templates );
			$prompt .= "## Reference Structure\n";
			$prompt .= "Use a structure similar to this pattern:\n";
			$prompt .= "- " . ( $first_template['description'] ?? 'Standard layout' ) . "\n";

			if ( isset( $first_template['widgets_used'] ) ) {
				$prompt .= "- Suggested widgets: " . implode( ', ', $first_template['widgets_used'] ) . "\n";
			}
		}

		return $prompt;
	}

	/**
	 * Get page types formatted for UI.
	 *
	 * @return array Page types for display.
	 */
	public function get_page_types_for_ui() {
		$types = $this->get_page_types();
		$formatted = [];

		foreach ( $types as $slug => $type ) {
			$formatted[] = [
				'slug'        => $slug,
				'name'        => $type['name'],
				'description' => $type['description'],
				'icon'        => $type['icon'],
				'sections'    => $type['sections'],
				'questions'   => $type['questions'],
			];
		}

		return $formatted;
	}

	/**
	 * Get section types formatted for UI dropdown.
	 *
	 * @return array Section types for select.
	 */
	public function get_section_types_for_ui() {
		$categories = $this->templates->get_categories();
		$formatted = [];

		foreach ( $categories as $slug => $category ) {
			$formatted[] = [
				'value' => $slug,
				'label' => $category['label'],
				'icon'  => $category['icon'],
			];
		}

		return $formatted;
	}

	/**
	 * Validate wizard answers.
	 *
	 * @param string $page_type Page type.
	 * @param array  $answers   User answers.
	 * @return array Validation result with 'valid' and 'errors' keys.
	 */
	public function validate_answers( $page_type, $answers ) {
		$type_def = $this->get_page_type( $page_type );

		if ( ! $type_def ) {
			return [
				'valid'  => false,
				'errors' => [ 'Invalid page type.' ],
			];
		}

		$errors = [];

		foreach ( $type_def['questions'] as $key => $question ) {
			if ( ! empty( $question['required'] ) ) {
				if ( ! isset( $answers[ $key ] ) || '' === trim( $answers[ $key ] ) ) {
					$errors[] = sprintf(
						__( '%s is required.', 'aimentor' ),
						$question['label']
					);
				}
			}
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}
}
