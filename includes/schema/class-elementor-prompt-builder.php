<?php
/**
 * Enhanced Elementor Prompt Builder
 *
 * Builds structured, schema-aware prompts for AI canvas generation.
 *
 * @package AiMentor
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Elementor_Prompt_Builder {

	/**
	 * Section templates loader instance.
	 *
	 * @var AiMentor_Section_Templates|null
	 */
	protected $templates_loader = null;

	/**
	 * Get the section templates loader.
	 *
	 * @return AiMentor_Section_Templates|null
	 */
	protected function get_templates_loader() {
		if ( null === $this->templates_loader && class_exists( 'AiMentor_Section_Templates' ) ) {
			$this->templates_loader = new AiMentor_Section_Templates();
		}
		return $this->templates_loader;
	}

	/**
	 * Build a complete canvas generation prompt.
	 *
	 * @param string $user_prompt The user's original prompt.
	 * @param array  $context     Generation context (brand, knowledge, etc.).
	 * @return array Prompt components for the AI request.
	 */
	public function build_canvas_prompt( $user_prompt, $context = [] ) {
		$system_instruction = $this->get_system_instruction( $context );
		$enhanced_prompt    = $this->enhance_user_prompt( $user_prompt, $context );

		return [
			'system' => $system_instruction,
			'prompt' => $enhanced_prompt,
		];
	}

	/**
	 * Get the system instruction for canvas generation.
	 *
	 * @param array $context Generation context.
	 * @return string System instruction.
	 */
	public function get_system_instruction( $context = [] ) {
		$has_pro = defined( 'ELEMENTOR_PRO_VERSION' );

		$instruction = "You are an expert Elementor page builder assistant. Your task is to generate valid Elementor JSON that can be directly imported into the Elementor editor.\n\n";

		$instruction .= "## CRITICAL REQUIREMENTS\n\n";
		$instruction .= "1. Output ONLY valid JSON - no markdown, no code fences, no explanations\n";
		$instruction .= "2. Every element MUST have a unique 'id' (7 alphanumeric characters, e.g., 'abc1234')\n";
		$instruction .= "3. Follow the exact hierarchy: section > column > widget (or container > widget)\n";
		$instruction .= "4. Use only valid widgetType values from the supported list\n";
		$instruction .= "5. Include all required settings for each widget type\n\n";

		$instruction .= $this->get_structure_reference();
		$instruction .= $this->get_widget_reference( $has_pro );

		// Add brand context if available
		if ( ! empty( $context['brand'] ) ) {
			$instruction .= $this->get_brand_instruction( $context['brand'] );
		}

		return $instruction;
	}

	/**
	 * Get the Elementor JSON structure reference.
	 *
	 * @return string Structure documentation.
	 */
	protected function get_structure_reference() {
		$structure = "## ELEMENTOR JSON STRUCTURE\n\n";

		$structure .= "The output must be a JSON object with an 'elements' array containing sections:\n\n";

		$structure .= "```\n";
		$structure .= "{\n";
		$structure .= "  \"elements\": [\n";
		$structure .= "    {\n";
		$structure .= "      \"id\": \"abc1234\",\n";
		$structure .= "      \"elType\": \"section\",\n";
		$structure .= "      \"settings\": {\n";
		$structure .= "        \"structure\": \"20\"\n";
		$structure .= "      },\n";
		$structure .= "      \"elements\": [\n";
		$structure .= "        {\n";
		$structure .= "          \"id\": \"def5678\",\n";
		$structure .= "          \"elType\": \"column\",\n";
		$structure .= "          \"settings\": {\n";
		$structure .= "            \"_column_size\": 50\n";
		$structure .= "          },\n";
		$structure .= "          \"elements\": [\n";
		$structure .= "            {\n";
		$structure .= "              \"id\": \"ghi9012\",\n";
		$structure .= "              \"elType\": \"widget\",\n";
		$structure .= "              \"widgetType\": \"heading\",\n";
		$structure .= "              \"settings\": {\n";
		$structure .= "                \"title\": \"Your Heading Here\",\n";
		$structure .= "                \"header_size\": \"h2\"\n";
		$structure .= "              }\n";
		$structure .= "            }\n";
		$structure .= "          ]\n";
		$structure .= "        }\n";
		$structure .= "      ]\n";
		$structure .= "    }\n";
		$structure .= "  ]\n";
		$structure .= "}\n";
		$structure .= "```\n\n";

		$structure .= "### Element Types:\n";
		$structure .= "- **section**: Top-level container, contains columns. Use 'structure' setting for column layout.\n";
		$structure .= "- **column**: Inside sections, contains widgets. Use '_column_size' (percentage) for width.\n";
		$structure .= "- **widget**: Inside columns, renders content. Must have 'widgetType' property.\n";
		$structure .= "- **container**: (Alternative to section) Flexbox container, can contain widgets directly.\n\n";

		$structure .= "### Column Structures:\n";
		$structure .= "- Single column: structure: \"10\" (100%)\n";
		$structure .= "- Two equal: structure: \"20\" (50/50)\n";
		$structure .= "- Three equal: structure: \"30\" (33/33/33)\n";
		$structure .= "- Four equal: structure: \"40\" (25/25/25/25)\n";
		$structure .= "- 2/3 + 1/3: structure: \"21\" (66/33)\n";
		$structure .= "- 1/3 + 2/3: structure: \"22\" (33/66)\n";
		$structure .= "- 1/4 + 3/4: structure: \"23\" (25/75)\n\n";

		return $structure;
	}

	/**
	 * Get widget reference documentation.
	 *
	 * @param bool $include_pro Whether to include Pro widgets.
	 * @return string Widget documentation.
	 */
	protected function get_widget_reference( $include_pro = false ) {
		$reference = "## AVAILABLE WIDGETS\n\n";

		// Core widgets with settings
		$reference .= "### Text & Headings\n";
		$reference .= "**heading**: Title text with size control\n";
		$reference .= "  - settings: { title: \"Text\", header_size: \"h1|h2|h3|h4|h5|h6\", align: \"left|center|right\" }\n\n";

		$reference .= "**text-editor**: Rich text/paragraphs\n";
		$reference .= "  - settings: { editor: \"<p>HTML content</p>\" }\n\n";

		$reference .= "### Images & Media\n";
		$reference .= "**image**: Display images\n";
		$reference .= "  - settings: { image: { url: \"https://...\", id: \"\" }, align: \"center\" }\n\n";

		$reference .= "**video**: Embed videos\n";
		$reference .= "  - settings: { video_type: \"youtube\", youtube_url: \"https://youtube.com/watch?v=...\" }\n\n";

		$reference .= "### Buttons & Links\n";
		$reference .= "**button**: CTA buttons\n";
		$reference .= "  - settings: { text: \"Click Here\", link: { url: \"#\", is_external: false }, size: \"sm|md|lg\", align: \"center\" }\n\n";

		$reference .= "### Icons & Features\n";
		$reference .= "**icon**: Standalone icon\n";
		$reference .= "  - settings: { selected_icon: { value: \"fas fa-star\", library: \"fa-solid\" } }\n\n";

		$reference .= "**icon-box**: Icon with title and description (features)\n";
		$reference .= "  - settings: { selected_icon: { value: \"fas fa-rocket\", library: \"fa-solid\" }, title_text: \"Title\", description_text: \"Description\" }\n\n";

		$reference .= "**icon-list**: List with icons (checklists, features)\n";
		$reference .= "  - settings: { icon_list: [ { text: \"Item\", selected_icon: { value: \"fas fa-check\", library: \"fa-solid\" } } ] }\n\n";

		$reference .= "**image-box**: Image with title and description (cards)\n";
		$reference .= "  - settings: { image: { url: \"...\" }, title_text: \"Title\", description_text: \"Description\" }\n\n";

		$reference .= "### Social Proof\n";
		$reference .= "**testimonial**: Customer quotes\n";
		$reference .= "  - settings: { testimonial_content: \"Quote\", testimonial_name: \"Name\", testimonial_job: \"Title\" }\n\n";

		$reference .= "**star-rating**: Star ratings\n";
		$reference .= "  - settings: { rating: 4.5, rating_scale: 5 }\n\n";

		$reference .= "**counter**: Animated statistics\n";
		$reference .= "  - settings: { ending_number: 500, suffix: \"+\", title: \"Happy Clients\" }\n\n";

		$reference .= "### Interactive\n";
		$reference .= "**accordion**: Collapsible FAQ sections\n";
		$reference .= "  - settings: { tabs: [ { tab_title: \"Question?\", tab_content: \"Answer.\" } ] }\n\n";

		$reference .= "**tabs**: Tabbed content\n";
		$reference .= "  - settings: { tabs: [ { tab_title: \"Tab 1\", tab_content: \"Content\" } ] }\n\n";

		$reference .= "### Layout\n";
		$reference .= "**divider**: Horizontal separator line\n";
		$reference .= "  - settings: { style: \"solid\" }\n\n";

		$reference .= "**spacer**: Vertical spacing\n";
		$reference .= "  - settings: { space: { size: 50, unit: \"px\" } }\n\n";

		$reference .= "### Other\n";
		$reference .= "**social-icons**: Social media links\n";
		$reference .= "  - settings: { social_icon_list: [ { social_icon: { value: \"fab fa-facebook\", library: \"fa-brands\" }, link: { url: \"...\" } } ] }\n\n";

		$reference .= "**google_maps**: Embed maps\n";
		$reference .= "  - settings: { address: \"123 Main St, City\" }\n\n";

		$reference .= "**alert**: Notice/warning boxes\n";
		$reference .= "  - settings: { alert_type: \"info|success|warning|danger\", alert_title: \"Title\", alert_description: \"Message\" }\n\n";

		$reference .= "**progress**: Progress bars\n";
		$reference .= "  - settings: { title: \"Skill\", percent: 75 }\n\n";

		if ( $include_pro ) {
			$reference .= "### PRO WIDGETS (Elementor Pro detected)\n";
			$reference .= "**form**: Contact forms\n";
			$reference .= "  - settings: { form_fields: [ { field_type: \"text|email|textarea\", field_label: \"Name\", required: \"true\" } ], button_text: \"Send\" }\n\n";

			$reference .= "**price-table**: Pricing tables\n";
			$reference .= "  - settings: { heading: \"Plan Name\", price: \"99\", currency_symbol: \"$\", period: \"/month\", features_list: [ { item_text: \"Feature\" } ], button_text: \"Buy\" }\n\n";

			$reference .= "**call-to-action**: CTA banners\n";
			$reference .= "  - settings: { title: \"CTA Title\", description: \"Description\", button: \"Button Text\" }\n\n";

			$reference .= "**countdown**: Event timers\n";
			$reference .= "  - settings: { due_date: \"2025-12-31 23:59\" }\n\n";

			$reference .= "**posts**: Blog post grids\n";
			$reference .= "  - settings: { posts_per_page: 6, columns: 3 }\n\n";
		}

		$reference .= "### Common Font Awesome Icons:\n";
		$reference .= "- fas fa-check, fas fa-star, fas fa-heart, fas fa-arrow-right\n";
		$reference .= "- fas fa-rocket, fas fa-lightbulb, fas fa-shield-alt, fas fa-cog\n";
		$reference .= "- fas fa-users, fas fa-envelope, fas fa-phone, fas fa-map-marker-alt\n";
		$reference .= "- fab fa-facebook, fab fa-twitter, fab fa-instagram, fab fa-linkedin\n\n";

		return $reference;
	}

	/**
	 * Get brand-specific instructions.
	 *
	 * @param array $brand Brand context.
	 * @return string Brand instructions.
	 */
	protected function get_brand_instruction( $brand ) {
		$instruction = "## BRAND GUIDELINES\n\n";

		if ( ! empty( $brand['primary_color'] ) ) {
			$instruction .= sprintf( "- Primary brand color: %s (use for buttons, accents, highlights)\n", $brand['primary_color'] );
		}

		if ( ! empty( $brand['secondary_color'] ) ) {
			$instruction .= sprintf( "- Secondary color: %s\n", $brand['secondary_color'] );
		}

		if ( ! empty( $brand['tone_keywords'] ) ) {
			$instruction .= sprintf( "- Brand voice/tone: %s\n", $brand['tone_keywords'] );
			$instruction .= "- Match this tone in all headings, descriptions, and button text\n";
		}

		$instruction .= "\n";

		return $instruction;
	}

	/**
	 * Enhance the user prompt with additional context.
	 *
	 * @param string $user_prompt Original prompt.
	 * @param array  $context     Generation context.
	 * @return string Enhanced prompt.
	 */
	public function enhance_user_prompt( $user_prompt, $context = [] ) {
		$enhanced = $user_prompt;

		// Add knowledge context
		if ( ! empty( $context['knowledge'] ) && ! empty( $context['knowledge']['summary'] ) ) {
			$enhanced .= "\n\n## CONTEXT INFORMATION\n";
			$enhanced .= $context['knowledge']['summary'];

			if ( ! empty( $context['knowledge']['guidance'] ) ) {
				$enhanced .= "\n\n" . $context['knowledge']['guidance'];
			}
		}

		// Add output reminder
		$enhanced .= "\n\n## OUTPUT INSTRUCTIONS\n";
		$enhanced .= "Generate the Elementor JSON now. Remember:\n";
		$enhanced .= "- Output ONLY the JSON object, starting with { and ending with }\n";
		$enhanced .= "- Every element needs a unique 7-character alphanumeric 'id'\n";
		$enhanced .= "- Use realistic, contextual content - not placeholder text like 'Lorem ipsum'\n";
		$enhanced .= "- Create a complete, professional layout appropriate for the request\n";

		return $enhanced;
	}

	/**
	 * Build a retry prompt after a failed generation.
	 *
	 * @param string $original_prompt The original prompt.
	 * @param array  $errors          Validation errors from the failed attempt.
	 * @param string $raw_response    The raw response that failed.
	 * @return array Retry prompt components.
	 */
	public function build_retry_prompt( $original_prompt, $errors, $raw_response = '' ) {
		$system = "You are fixing a failed Elementor JSON generation. The previous attempt had errors.\n\n";

		$system .= "## ERRORS TO FIX\n";
		foreach ( $errors as $error ) {
			$system .= sprintf( "- %s (at %s)\n", $error['message'], $error['path'] ?? 'unknown' );
		}
		$system .= "\n";

		$system .= $this->get_structure_reference();

		$prompt = "Please regenerate valid Elementor JSON for this request:\n\n";
		$prompt .= $original_prompt;
		$prompt .= "\n\n## CRITICAL\n";
		$prompt .= "- Fix all the errors listed above\n";
		$prompt .= "- Output ONLY valid JSON - no explanations, no markdown\n";
		$prompt .= "- Ensure every element has a unique 'id' property\n";
		$prompt .= "- Use the correct hierarchy: section > column > widget\n";

		return [
			'system' => $system,
			'prompt' => $prompt,
		];
	}

	/**
	 * Build a section-specific prompt.
	 *
	 * @param string $section_type Type of section (hero, features, testimonials, etc.).
	 * @param string $user_prompt  Additional user requirements.
	 * @param array  $context      Generation context.
	 * @return array Prompt components.
	 */
	public function build_section_prompt( $section_type, $user_prompt = '', $context = [] ) {
		$section_templates = $this->get_section_templates();

		if ( ! isset( $section_templates[ $section_type ] ) ) {
			return $this->build_canvas_prompt( $user_prompt, $context );
		}

		$template = $section_templates[ $section_type ];
		$system   = $this->get_system_instruction( $context );

		// Add section-specific template example if available
		$template_example = $this->get_template_example_for_section( $section_type );
		if ( ! empty( $template_example ) ) {
			$system .= $template_example;
		}

		$prompt = sprintf( "Generate a %s section with the following characteristics:\n\n", $template['label'] );
		$prompt .= $template['description'] . "\n\n";

		if ( ! empty( $template['suggested_widgets'] ) ) {
			$prompt .= "Suggested widgets to use: " . implode( ', ', $template['suggested_widgets'] ) . "\n\n";
		}

		if ( ! empty( $user_prompt ) ) {
			$prompt .= "Additional requirements: " . $user_prompt . "\n\n";
		}

		$prompt .= "Generate the Elementor JSON for this single section.";

		return [
			'system' => $system,
			'prompt' => $prompt,
		];
	}

	/**
	 * Get a template example for a specific section type.
	 *
	 * @param string $section_type The section type.
	 * @return string Template example documentation or empty string.
	 */
	protected function get_template_example_for_section( $section_type ) {
		$loader = $this->get_templates_loader();
		if ( ! $loader ) {
			return '';
		}

		// Map section types to template categories
		$category_map = [
			'hero'         => 'hero',
			'features'     => 'features',
			'testimonials' => 'testimonials',
			'pricing'      => 'pricing',
			'cta'          => 'cta',
			'faq'          => 'faq',
			'team'         => 'team',
			'stats'        => 'stats',
			'contact'      => 'contact',
			'gallery'      => 'gallery',
			'services'     => 'services',
			'about'        => 'about',
		];

		if ( ! isset( $category_map[ $section_type ] ) ) {
			return '';
		}

		$category  = $category_map[ $section_type ];
		$templates = $loader->get_templates_by_category( $category );

		if ( empty( $templates ) ) {
			return '';
		}

		// Get the first template as an example
		$first_template = reset( $templates );
		$template_data  = $loader->load_template( $first_template['file'] );

		if ( ! $template_data || empty( $template_data['elements'] ) ) {
			return '';
		}

		$example = "\n## REFERENCE EXAMPLE: " . strtoupper( $section_type ) . " SECTION\n\n";
		$example .= "Here is a well-structured example of a {$section_type} section. Use this as a reference for structure and formatting:\n\n";
		$example .= "```json\n";
		$example .= wp_json_encode( [ 'elements' => $template_data['elements'] ], JSON_PRETTY_PRINT );
		$example .= "\n```\n\n";
		$example .= "Note: Generate your own unique content and IDs, but follow this structural pattern.\n\n";

		return $example;
	}

	/**
	 * Get section type templates.
	 *
	 * @return array Section templates.
	 */
	protected function get_section_templates() {
		return [
			'hero' => [
				'label'             => 'Hero Section',
				'description'       => 'A prominent hero section with a large heading, supporting text, and call-to-action button. Can include a background image or video.',
				'suggested_widgets' => [ 'heading', 'text-editor', 'button', 'image' ],
			],
			'features' => [
				'label'             => 'Features Section',
				'description'       => 'Showcase 3-4 key features or benefits using icon boxes in a multi-column layout.',
				'suggested_widgets' => [ 'heading', 'icon-box' ],
			],
			'testimonials' => [
				'label'             => 'Testimonials Section',
				'description'       => 'Display customer testimonials with quotes, names, titles, and optional photos.',
				'suggested_widgets' => [ 'heading', 'testimonial', 'star-rating' ],
			],
			'pricing' => [
				'label'             => 'Pricing Section',
				'description'       => 'Pricing tables comparing 2-3 plans with features list and CTA buttons.',
				'suggested_widgets' => [ 'heading', 'text-editor', 'icon-list', 'button' ],
			],
			'cta' => [
				'label'             => 'Call-to-Action Section',
				'description'       => 'A compelling CTA section with headline, brief description, and prominent button.',
				'suggested_widgets' => [ 'heading', 'text-editor', 'button' ],
			],
			'faq' => [
				'label'             => 'FAQ Section',
				'description'       => 'Frequently asked questions in an accordion format for easy navigation.',
				'suggested_widgets' => [ 'heading', 'accordion' ],
			],
			'team' => [
				'label'             => 'Team Section',
				'description'       => 'Team member showcase with photos, names, titles, and optional social links.',
				'suggested_widgets' => [ 'heading', 'image-box', 'social-icons' ],
			],
			'stats' => [
				'label'             => 'Statistics Section',
				'description'       => 'Key metrics and achievements displayed with animated counters.',
				'suggested_widgets' => [ 'heading', 'counter' ],
			],
			'contact' => [
				'label'             => 'Contact Section',
				'description'       => 'Contact information with address, phone, email, and optional map.',
				'suggested_widgets' => [ 'heading', 'icon-list', 'google_maps', 'button' ],
			],
			'gallery' => [
				'label'             => 'Gallery Section',
				'description'       => 'Image gallery showcasing products, projects, or portfolio items.',
				'suggested_widgets' => [ 'heading', 'image', 'image-box' ],
			],
			'services' => [
				'label'             => 'Services Section',
				'description'       => 'Service offerings with descriptions, icons, and optional pricing.',
				'suggested_widgets' => [ 'heading', 'icon-box', 'button' ],
			],
			'about' => [
				'label'             => 'About Section',
				'description'       => 'Company or personal about section with image, description, and key points.',
				'suggested_widgets' => [ 'heading', 'text-editor', 'image', 'icon-list' ],
			],
		];
	}

	/**
	 * Get available section types.
	 *
	 * @return array Section type options.
	 */
	public function get_section_types() {
		$templates = $this->get_section_templates();
		$types     = [];

		foreach ( $templates as $key => $template ) {
			$types[ $key ] = $template['label'];
		}

		return $types;
	}

	/**
	 * Build a page wizard prompt for complete page generation.
	 *
	 * @param string $page_type The type of page to generate.
	 * @param array  $answers   User answers to wizard questions.
	 * @param array  $context   Additional context (brand, knowledge).
	 * @return array Prompt components.
	 */
	public function build_page_wizard_prompt( $page_type, $answers = [], $context = [] ) {
		if ( ! class_exists( 'AiMentor_Page_Wizard' ) ) {
			// Fallback to basic prompt
			return $this->build_canvas_prompt(
				"Generate a complete {$page_type} page layout",
				$context
			);
		}

		$wizard = new AiMentor_Page_Wizard();
		$system = $this->get_system_instruction( $context );

		// Add page-specific guidance
		$system .= "\n## PAGE GENERATION MODE\n\n";
		$system .= "You are generating a complete, multi-section page. Create a cohesive design with:\n";
		$system .= "- Consistent styling across all sections\n";
		$system .= "- Logical content flow from top to bottom\n";
		$system .= "- Appropriate spacing and visual hierarchy\n";
		$system .= "- Professional, realistic content (not Lorem ipsum)\n\n";

		// Add template examples for relevant sections
		$page_types = $wizard->get_page_types();
		if ( isset( $page_types[ $page_type ]['sections'] ) ) {
			$sections = $page_types[ $page_type ]['sections'];
			$examples_added = 0;

			foreach ( $sections as $section_type ) {
				if ( $examples_added >= 2 ) {
					// Limit examples to avoid prompt bloat
					break;
				}
				$example = $this->get_template_example_for_section( $section_type );
				if ( ! empty( $example ) ) {
					$system .= $example;
					$examples_added++;
				}
			}
		}

		// Build the user prompt using the wizard
		$prompt = $wizard->build_page_prompt( $page_type, $answers );

		return [
			'system' => $system,
			'prompt' => $prompt,
		];
	}

	/**
	 * Get available template categories with templates.
	 *
	 * @return array Categories with their templates.
	 */
	public function get_available_templates() {
		$loader = $this->get_templates_loader();
		if ( ! $loader ) {
			return [];
		}

		$categories = $loader->get_categories();
		$result     = [];

		foreach ( $categories as $slug => $label ) {
			$templates = $loader->get_templates_by_category( $slug );
			if ( ! empty( $templates ) ) {
				$result[ $slug ] = [
					'label'     => $label,
					'templates' => $templates,
				];
			}
		}

		return $result;
	}

	/**
	 * Get available page types from the wizard.
	 *
	 * @return array Page types with their configurations.
	 */
	public function get_available_page_types() {
		if ( ! class_exists( 'AiMentor_Page_Wizard' ) ) {
			return [];
		}

		$wizard = new AiMentor_Page_Wizard();
		return $wizard->get_page_types();
	}
}
