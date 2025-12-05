<?php
/**
 * Widget Definitions Catalog
 *
 * Provides structured information about Elementor widgets for prompt building.
 *
 * @package AiMentor
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiMentor_Widget_Definitions {

	/**
	 * Get all widget definitions.
	 *
	 * @param bool $include_pro Whether to include Pro widgets.
	 * @return array Widget definitions.
	 */
	public static function get_all( $include_pro = null ) {
		if ( null === $include_pro ) {
			$include_pro = defined( 'ELEMENTOR_PRO_VERSION' );
		}

		$widgets = self::get_free_widgets();

		if ( $include_pro ) {
			$widgets = array_merge( $widgets, self::get_pro_widgets() );
		}

		return $widgets;
	}

	/**
	 * Get free Elementor widget definitions.
	 *
	 * @return array Widget definitions.
	 */
	public static function get_free_widgets() {
		return [
			'heading' => [
				'name'        => 'heading',
				'label'       => 'Heading',
				'description' => 'Display headings with customizable size, alignment, and typography.',
				'use_cases'   => [ 'page titles', 'section headings', 'subtitles' ],
				'settings'    => [
					'title'       => [
						'type'        => 'string',
						'required'    => true,
						'description' => 'The heading text content',
					],
					'header_size' => [
						'type'        => 'string',
						'default'     => 'h2',
						'options'     => [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
						'description' => 'HTML heading tag',
					],
					'align'       => [
						'type'    => 'string',
						'default' => '',
						'options' => [ 'left', 'center', 'right', 'justify' ],
					],
				],
				'example'     => [
					'id'         => 'abc1234',
					'elType'     => 'widget',
					'widgetType' => 'heading',
					'settings'   => [
						'title'       => 'Welcome to Our Website',
						'header_size' => 'h1',
						'align'       => 'center',
					],
				],
			],

			'text-editor' => [
				'name'        => 'text-editor',
				'label'       => 'Text Editor',
				'description' => 'Rich text content with HTML support for paragraphs and formatted text.',
				'use_cases'   => [ 'paragraphs', 'body content', 'descriptions', 'rich text blocks' ],
				'settings'    => [
					'editor' => [
						'type'        => 'string',
						'required'    => true,
						'description' => 'HTML content for the text block',
					],
					'align'  => [
						'type'    => 'string',
						'default' => '',
						'options' => [ 'left', 'center', 'right', 'justify' ],
					],
				],
				'example'     => [
					'id'         => 'def5678',
					'elType'     => 'widget',
					'widgetType' => 'text-editor',
					'settings'   => [
						'editor' => '<p>This is a paragraph of text that describes your product or service. You can include <strong>bold</strong> and <em>italic</em> formatting.</p>',
					],
				],
			],

			'image' => [
				'name'        => 'image',
				'label'       => 'Image',
				'description' => 'Display images with optional caption, link, and lightbox.',
				'use_cases'   => [ 'hero images', 'product photos', 'team photos', 'logos' ],
				'settings'    => [
					'image'       => [
						'type'        => 'object',
						'required'    => true,
						'description' => 'Image object with url and id',
						'properties'  => [
							'url' => [ 'type' => 'string', 'required' => true ],
							'id'  => [ 'type' => 'string', 'default' => '' ],
						],
					],
					'image_size'  => [
						'type'    => 'string',
						'default' => 'large',
						'options' => [ 'thumbnail', 'medium', 'large', 'full' ],
					],
					'align'       => [
						'type'    => 'string',
						'default' => '',
						'options' => [ 'left', 'center', 'right' ],
					],
					'caption'     => [
						'type'        => 'string',
						'default'     => '',
						'description' => 'Image caption text',
					],
				],
				'example'     => [
					'id'         => 'ghi9012',
					'elType'     => 'widget',
					'widgetType' => 'image',
					'settings'   => [
						'image' => [
							'url' => 'https://via.placeholder.com/800x600',
							'id'  => '',
						],
						'align' => 'center',
					],
				],
			],

			'button' => [
				'name'        => 'button',
				'label'       => 'Button',
				'description' => 'Call-to-action button with customizable style, size, and link.',
				'use_cases'   => [ 'CTAs', 'form submissions', 'navigation links', 'downloads' ],
				'settings'    => [
					'text'        => [
						'type'        => 'string',
						'required'    => true,
						'description' => 'Button label text',
					],
					'link'        => [
						'type'        => 'object',
						'description' => 'Button link configuration',
						'properties'  => [
							'url'         => [ 'type' => 'string', 'default' => '#' ],
							'is_external' => [ 'type' => 'boolean', 'default' => false ],
							'nofollow'    => [ 'type' => 'boolean', 'default' => false ],
						],
					],
					'align'       => [
						'type'    => 'string',
						'default' => '',
						'options' => [ 'left', 'center', 'right', 'justify' ],
					],
					'size'        => [
						'type'    => 'string',
						'default' => 'sm',
						'options' => [ 'xs', 'sm', 'md', 'lg', 'xl' ],
					],
					'button_type' => [
						'type'    => 'string',
						'default' => '',
						'options' => [ 'default', 'info', 'success', 'warning', 'danger' ],
					],
				],
				'example'     => [
					'id'         => 'jkl3456',
					'elType'     => 'widget',
					'widgetType' => 'button',
					'settings'   => [
						'text'  => 'Get Started',
						'link'  => [
							'url'         => '#contact',
							'is_external' => false,
							'nofollow'    => false,
						],
						'align' => 'center',
						'size'  => 'lg',
					],
				],
			],

			'icon' => [
				'name'        => 'icon',
				'label'       => 'Icon',
				'description' => 'Display Font Awesome or custom icons.',
				'use_cases'   => [ 'decorative elements', 'feature indicators', 'social links' ],
				'settings'    => [
					'selected_icon' => [
						'type'        => 'object',
						'required'    => true,
						'description' => 'Icon configuration',
						'properties'  => [
							'value'   => [ 'type' => 'string', 'description' => 'Icon class e.g. fas fa-star' ],
							'library' => [ 'type' => 'string', 'default' => 'fa-solid' ],
						],
					],
					'align'         => [
						'type'    => 'string',
						'default' => 'center',
						'options' => [ 'left', 'center', 'right' ],
					],
				],
				'example'     => [
					'id'         => 'mno7890',
					'elType'     => 'widget',
					'widgetType' => 'icon',
					'settings'   => [
						'selected_icon' => [
							'value'   => 'fas fa-check-circle',
							'library' => 'fa-solid',
						],
						'align'         => 'center',
					],
				],
			],

			'icon-box' => [
				'name'        => 'icon-box',
				'label'       => 'Icon Box',
				'description' => 'Feature box with icon, title, and description.',
				'use_cases'   => [ 'features list', 'services', 'benefits', 'process steps' ],
				'settings'    => [
					'selected_icon'    => [
						'type'       => 'object',
						'properties' => [
							'value'   => [ 'type' => 'string' ],
							'library' => [ 'type' => 'string', 'default' => 'fa-solid' ],
						],
					],
					'title_text'       => [
						'type'        => 'string',
						'required'    => true,
						'description' => 'Feature title',
					],
					'description_text' => [
						'type'        => 'string',
						'description' => 'Feature description',
					],
					'position'         => [
						'type'    => 'string',
						'default' => 'top',
						'options' => [ 'top', 'left', 'right' ],
					],
				],
				'example'     => [
					'id'         => 'pqr1234',
					'elType'     => 'widget',
					'widgetType' => 'icon-box',
					'settings'   => [
						'selected_icon'    => [
							'value'   => 'fas fa-rocket',
							'library' => 'fa-solid',
						],
						'title_text'       => 'Fast Performance',
						'description_text' => 'Our solution delivers lightning-fast results for your business.',
						'position'         => 'top',
					],
				],
			],

			'image-box' => [
				'name'        => 'image-box',
				'label'       => 'Image Box',
				'description' => 'Card with image, title, and description.',
				'use_cases'   => [ 'team members', 'product cards', 'blog previews', 'portfolio items' ],
				'settings'    => [
					'image'            => [
						'type'       => 'object',
						'properties' => [
							'url' => [ 'type' => 'string' ],
							'id'  => [ 'type' => 'string', 'default' => '' ],
						],
					],
					'title_text'       => [
						'type'     => 'string',
						'required' => true,
					],
					'description_text' => [
						'type' => 'string',
					],
					'position'         => [
						'type'    => 'string',
						'default' => 'top',
						'options' => [ 'top', 'left', 'right' ],
					],
				],
				'example'     => [
					'id'         => 'stu5678',
					'elType'     => 'widget',
					'widgetType' => 'image-box',
					'settings'   => [
						'image'            => [
							'url' => 'https://via.placeholder.com/400x300',
							'id'  => '',
						],
						'title_text'       => 'John Doe',
						'description_text' => 'CEO & Founder',
					],
				],
			],

			'counter' => [
				'name'        => 'counter',
				'label'       => 'Counter',
				'description' => 'Animated number counter for statistics.',
				'use_cases'   => [ 'statistics', 'achievements', 'metrics', 'milestones' ],
				'settings'    => [
					'starting_number' => [
						'type'    => 'number',
						'default' => 0,
					],
					'ending_number'   => [
						'type'     => 'number',
						'required' => true,
					],
					'prefix'          => [
						'type'        => 'string',
						'default'     => '',
						'description' => 'Text before number e.g. $',
					],
					'suffix'          => [
						'type'        => 'string',
						'default'     => '',
						'description' => 'Text after number e.g. +, %, K',
					],
					'title'           => [
						'type'        => 'string',
						'description' => 'Label below the counter',
					],
				],
				'example'     => [
					'id'         => 'vwx9012',
					'elType'     => 'widget',
					'widgetType' => 'counter',
					'settings'   => [
						'starting_number' => 0,
						'ending_number'   => 500,
						'suffix'          => '+',
						'title'           => 'Happy Clients',
					],
				],
			],

			'testimonial' => [
				'name'        => 'testimonial',
				'label'       => 'Testimonial',
				'description' => 'Customer testimonial with quote, name, and title.',
				'use_cases'   => [ 'social proof', 'reviews', 'client feedback', 'quotes' ],
				'settings'    => [
					'testimonial_content' => [
						'type'        => 'string',
						'required'    => true,
						'description' => 'The testimonial quote text',
					],
					'testimonial_name'    => [
						'type'        => 'string',
						'required'    => true,
						'description' => 'Person name',
					],
					'testimonial_job'     => [
						'type'        => 'string',
						'description' => 'Job title or company',
					],
					'testimonial_image'   => [
						'type'       => 'object',
						'properties' => [
							'url' => [ 'type' => 'string' ],
							'id'  => [ 'type' => 'string', 'default' => '' ],
						],
					],
				],
				'example'     => [
					'id'         => 'yza3456',
					'elType'     => 'widget',
					'widgetType' => 'testimonial',
					'settings'   => [
						'testimonial_content' => 'This product transformed our business. Highly recommended!',
						'testimonial_name'    => 'Jane Smith',
						'testimonial_job'     => 'Marketing Director, ABC Corp',
						'testimonial_image'   => [
							'url' => 'https://via.placeholder.com/150x150',
							'id'  => '',
						],
					],
				],
			],

			'accordion' => [
				'name'        => 'accordion',
				'label'       => 'Accordion',
				'description' => 'Collapsible content sections, perfect for FAQs.',
				'use_cases'   => [ 'FAQs', 'feature details', 'specifications', 'expandable content' ],
				'settings'    => [
					'tabs' => [
						'type'        => 'array',
						'required'    => true,
						'description' => 'Array of accordion items',
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'tab_title'   => [ 'type' => 'string', 'required' => true ],
								'tab_content' => [ 'type' => 'string', 'required' => true ],
							],
						],
					],
				],
				'example'     => [
					'id'         => 'bcd7890',
					'elType'     => 'widget',
					'widgetType' => 'accordion',
					'settings'   => [
						'tabs' => [
							[
								'tab_title'   => 'What is your return policy?',
								'tab_content' => 'We offer a 30-day money-back guarantee on all products.',
							],
							[
								'tab_title'   => 'How long does shipping take?',
								'tab_content' => 'Standard shipping takes 3-5 business days.',
							],
						],
					],
				],
			],

			'tabs' => [
				'name'        => 'tabs',
				'label'       => 'Tabs',
				'description' => 'Tabbed content sections.',
				'use_cases'   => [ 'product variants', 'content organization', 'feature categories' ],
				'settings'    => [
					'tabs' => [
						'type'        => 'array',
						'required'    => true,
						'description' => 'Array of tab items',
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'tab_title'   => [ 'type' => 'string', 'required' => true ],
								'tab_content' => [ 'type' => 'string', 'required' => true ],
							],
						],
					],
				],
				'example'     => [
					'id'         => 'efg1234',
					'elType'     => 'widget',
					'widgetType' => 'tabs',
					'settings'   => [
						'tabs' => [
							[
								'tab_title'   => 'Features',
								'tab_content' => 'List of amazing features...',
							],
							[
								'tab_title'   => 'Specifications',
								'tab_content' => 'Technical specifications...',
							],
						],
					],
				],
			],

			'star-rating' => [
				'name'        => 'star-rating',
				'label'       => 'Star Rating',
				'description' => 'Display star ratings for reviews.',
				'use_cases'   => [ 'product ratings', 'review scores', 'feedback display' ],
				'settings'    => [
					'rating_scale' => [
						'type'    => 'number',
						'default' => 5,
					],
					'rating'       => [
						'type'        => 'number',
						'required'    => true,
						'description' => 'Rating value (e.g., 4.5)',
					],
				],
				'example'     => [
					'id'         => 'hij5678',
					'elType'     => 'widget',
					'widgetType' => 'star-rating',
					'settings'   => [
						'rating_scale' => 5,
						'rating'       => 4.5,
					],
				],
			],

			'icon-list' => [
				'name'        => 'icon-list',
				'label'       => 'Icon List',
				'description' => 'List with custom icons for each item.',
				'use_cases'   => [ 'feature lists', 'checklists', 'benefits', 'process steps' ],
				'settings'    => [
					'icon_list' => [
						'type'        => 'array',
						'required'    => true,
						'description' => 'Array of list items',
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'text'          => [ 'type' => 'string', 'required' => true ],
								'selected_icon' => [
									'type'       => 'object',
									'properties' => [
										'value'   => [ 'type' => 'string' ],
										'library' => [ 'type' => 'string' ],
									],
								],
								'link'          => [
									'type'       => 'object',
									'properties' => [
										'url' => [ 'type' => 'string' ],
									],
								],
							],
						],
					],
				],
				'example'     => [
					'id'         => 'klm9012',
					'elType'     => 'widget',
					'widgetType' => 'icon-list',
					'settings'   => [
						'icon_list' => [
							[
								'text'          => 'Free Shipping',
								'selected_icon' => [
									'value'   => 'fas fa-check',
									'library' => 'fa-solid',
								],
							],
							[
								'text'          => '24/7 Support',
								'selected_icon' => [
									'value'   => 'fas fa-check',
									'library' => 'fa-solid',
								],
							],
						],
					],
				],
			],

			'progress' => [
				'name'        => 'progress',
				'label'       => 'Progress Bar',
				'description' => 'Animated progress bar for skills or completion.',
				'use_cases'   => [ 'skills', 'project progress', 'completion rates', 'statistics' ],
				'settings'    => [
					'title'   => [
						'type'        => 'string',
						'description' => 'Progress bar label',
					],
					'percent' => [
						'type'        => 'number',
						'required'    => true,
						'description' => 'Progress percentage (0-100)',
					],
				],
				'example'     => [
					'id'         => 'nop3456',
					'elType'     => 'widget',
					'widgetType' => 'progress',
					'settings'   => [
						'title'   => 'Project Completion',
						'percent' => 75,
					],
				],
			],

			'social-icons' => [
				'name'        => 'social-icons',
				'label'       => 'Social Icons',
				'description' => 'Social media icon links.',
				'use_cases'   => [ 'social links', 'follow buttons', 'sharing' ],
				'settings'    => [
					'social_icon_list' => [
						'type'        => 'array',
						'required'    => true,
						'description' => 'Array of social icons',
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'social_icon' => [
									'type'       => 'object',
									'properties' => [
										'value'   => [ 'type' => 'string' ],
										'library' => [ 'type' => 'string', 'default' => 'fa-brands' ],
									],
								],
								'link'        => [
									'type'       => 'object',
									'properties' => [
										'url'         => [ 'type' => 'string' ],
										'is_external' => [ 'type' => 'boolean', 'default' => true ],
									],
								],
							],
						],
					],
				],
				'example'     => [
					'id'         => 'qrs7890',
					'elType'     => 'widget',
					'widgetType' => 'social-icons',
					'settings'   => [
						'social_icon_list' => [
							[
								'social_icon' => [
									'value'   => 'fab fa-facebook',
									'library' => 'fa-brands',
								],
								'link'        => [
									'url'         => 'https://facebook.com',
									'is_external' => true,
								],
							],
							[
								'social_icon' => [
									'value'   => 'fab fa-twitter',
									'library' => 'fa-brands',
								],
								'link'        => [
									'url'         => 'https://twitter.com',
									'is_external' => true,
								],
							],
						],
					],
				],
			],

			'divider' => [
				'name'        => 'divider',
				'label'       => 'Divider',
				'description' => 'Horizontal line separator.',
				'use_cases'   => [ 'section separators', 'visual breaks' ],
				'settings'    => [
					'style' => [
						'type'    => 'string',
						'default' => 'solid',
						'options' => [ 'solid', 'double', 'dotted', 'dashed' ],
					],
					'align' => [
						'type'    => 'string',
						'default' => 'center',
						'options' => [ 'left', 'center', 'right' ],
					],
				],
				'example'     => [
					'id'         => 'tuv1234',
					'elType'     => 'widget',
					'widgetType' => 'divider',
					'settings'   => [
						'style' => 'solid',
					],
				],
			],

			'spacer' => [
				'name'        => 'spacer',
				'label'       => 'Spacer',
				'description' => 'Empty space for layout control.',
				'use_cases'   => [ 'vertical spacing', 'layout adjustment' ],
				'settings'    => [
					'space' => [
						'type'        => 'object',
						'description' => 'Space size',
						'properties'  => [
							'size' => [ 'type' => 'number', 'default' => 50 ],
							'unit' => [ 'type' => 'string', 'default' => 'px' ],
						],
					],
				],
				'example'     => [
					'id'         => 'wxy5678',
					'elType'     => 'widget',
					'widgetType' => 'spacer',
					'settings'   => [
						'space' => [
							'size' => 50,
							'unit' => 'px',
						],
					],
				],
			],

			'video' => [
				'name'        => 'video',
				'label'       => 'Video',
				'description' => 'Embed videos from YouTube, Vimeo, or self-hosted.',
				'use_cases'   => [ 'product demos', 'tutorials', 'testimonial videos', 'background videos' ],
				'settings'    => [
					'video_type' => [
						'type'    => 'string',
						'default' => 'youtube',
						'options' => [ 'youtube', 'vimeo', 'dailymotion', 'hosted' ],
					],
					'youtube_url' => [
						'type'        => 'string',
						'description' => 'YouTube video URL',
					],
					'vimeo_url'   => [
						'type'        => 'string',
						'description' => 'Vimeo video URL',
					],
				],
				'example'     => [
					'id'         => 'zab9012',
					'elType'     => 'widget',
					'widgetType' => 'video',
					'settings'   => [
						'video_type'  => 'youtube',
						'youtube_url' => 'https://www.youtube.com/watch?v=XHOmBV4js_E',
					],
				],
			],

			'alert' => [
				'name'        => 'alert',
				'label'       => 'Alert',
				'description' => 'Notification/alert message box.',
				'use_cases'   => [ 'notices', 'warnings', 'success messages', 'info boxes' ],
				'settings'    => [
					'alert_type'        => [
						'type'    => 'string',
						'default' => 'info',
						'options' => [ 'info', 'success', 'warning', 'danger' ],
					],
					'alert_title'       => [
						'type' => 'string',
					],
					'alert_description' => [
						'type'     => 'string',
						'required' => true,
					],
				],
				'example'     => [
					'id'         => 'cde3456',
					'elType'     => 'widget',
					'widgetType' => 'alert',
					'settings'   => [
						'alert_type'        => 'info',
						'alert_title'       => 'Important Notice',
						'alert_description' => 'Please read this important information.',
					],
				],
			],

			'google_maps' => [
				'name'        => 'google_maps',
				'label'       => 'Google Maps',
				'description' => 'Embed Google Maps location.',
				'use_cases'   => [ 'contact pages', 'store locator', 'directions' ],
				'settings'    => [
					'address' => [
						'type'        => 'string',
						'required'    => true,
						'description' => 'Location address',
					],
					'zoom'    => [
						'type'        => 'object',
						'description' => 'Map zoom level',
						'properties'  => [
							'size' => [ 'type' => 'number', 'default' => 14 ],
						],
					],
					'height'  => [
						'type'       => 'object',
						'properties' => [
							'size' => [ 'type' => 'number', 'default' => 300 ],
							'unit' => [ 'type' => 'string', 'default' => 'px' ],
						],
					],
				],
				'example'     => [
					'id'         => 'fgh7890',
					'elType'     => 'widget',
					'widgetType' => 'google_maps',
					'settings'   => [
						'address' => '1600 Amphitheatre Parkway, Mountain View, CA',
						'zoom'    => [ 'size' => 14 ],
						'height'  => [ 'size' => 300, 'unit' => 'px' ],
					],
				],
			],
		];
	}

	/**
	 * Get Elementor Pro widget definitions.
	 *
	 * @return array Widget definitions.
	 */
	public static function get_pro_widgets() {
		return [
			'form' => [
				'name'        => 'form',
				'label'       => 'Form',
				'description' => 'Contact form with customizable fields.',
				'use_cases'   => [ 'contact forms', 'lead generation', 'subscriptions' ],
				'pro'         => true,
				'settings'    => [
					'form_name'   => [
						'type' => 'string',
					],
					'form_fields' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'field_type'  => [ 'type' => 'string' ],
								'field_label' => [ 'type' => 'string' ],
								'required'    => [ 'type' => 'string' ],
							],
						],
					],
					'button_text' => [
						'type'    => 'string',
						'default' => 'Send',
					],
				],
				'example'     => [
					'id'         => 'pro1234',
					'elType'     => 'widget',
					'widgetType' => 'form',
					'settings'   => [
						'form_name'   => 'Contact Form',
						'form_fields' => [
							[
								'field_type'  => 'text',
								'field_label' => 'Name',
								'required'    => 'true',
							],
							[
								'field_type'  => 'email',
								'field_label' => 'Email',
								'required'    => 'true',
							],
							[
								'field_type'  => 'textarea',
								'field_label' => 'Message',
								'required'    => 'false',
							],
						],
						'button_text' => 'Send Message',
					],
				],
			],

			'price-table' => [
				'name'        => 'price-table',
				'label'       => 'Price Table',
				'description' => 'Pricing table for plans and packages.',
				'use_cases'   => [ 'pricing pages', 'plan comparison', 'subscriptions' ],
				'pro'         => true,
				'settings'    => [
					'heading'        => [
						'type' => 'string',
					],
					'sub_heading'    => [
						'type' => 'string',
					],
					'price'          => [
						'type' => 'string',
					],
					'currency_symbol'=> [
						'type'    => 'string',
						'default' => '$',
					],
					'period'         => [
						'type'    => 'string',
						'default' => '/month',
					],
					'features_list'  => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'item_text' => [ 'type' => 'string' ],
							],
						],
					],
					'button_text'    => [
						'type' => 'string',
					],
				],
				'example'     => [
					'id'         => 'pro5678',
					'elType'     => 'widget',
					'widgetType' => 'price-table',
					'settings'   => [
						'heading'         => 'Professional',
						'sub_heading'     => 'Most Popular',
						'price'           => '49',
						'currency_symbol' => '$',
						'period'          => '/month',
						'features_list'   => [
							[ 'item_text' => 'Unlimited Projects' ],
							[ 'item_text' => 'Premium Support' ],
							[ 'item_text' => 'Custom Domain' ],
						],
						'button_text'     => 'Get Started',
					],
				],
			],

			'call-to-action' => [
				'name'        => 'call-to-action',
				'label'       => 'Call to Action',
				'description' => 'Eye-catching CTA section with background.',
				'use_cases'   => [ 'promotional banners', 'conversion sections', 'special offers' ],
				'pro'         => true,
				'settings'    => [
					'title'                => [ 'type' => 'string' ],
					'description'          => [ 'type' => 'string' ],
					'button'               => [ 'type' => 'string' ],
					'link'                 => [
						'type'       => 'object',
						'properties' => [
							'url' => [ 'type' => 'string' ],
						],
					],
					'background_image'     => [
						'type'       => 'object',
						'properties' => [
							'url' => [ 'type' => 'string' ],
						],
					],
				],
				'example'     => [
					'id'         => 'pro9012',
					'elType'     => 'widget',
					'widgetType' => 'call-to-action',
					'settings'   => [
						'title'       => 'Ready to Get Started?',
						'description' => 'Join thousands of satisfied customers today.',
						'button'      => 'Sign Up Now',
						'link'        => [ 'url' => '#signup' ],
					],
				],
			],

			'posts' => [
				'name'        => 'posts',
				'label'       => 'Posts',
				'description' => 'Display blog posts in a grid or list.',
				'use_cases'   => [ 'blog listings', 'recent posts', 'category archives' ],
				'pro'         => true,
				'settings'    => [
					'posts_per_page' => [
						'type'    => 'number',
						'default' => 6,
					],
					'columns'        => [
						'type'    => 'number',
						'default' => 3,
					],
				],
				'example'     => [
					'id'         => 'pro3456',
					'elType'     => 'widget',
					'widgetType' => 'posts',
					'settings'   => [
						'posts_per_page' => 6,
						'columns'        => 3,
					],
				],
			],

			'countdown' => [
				'name'        => 'countdown',
				'label'       => 'Countdown',
				'description' => 'Countdown timer to a specific date.',
				'use_cases'   => [ 'product launches', 'sales', 'events', 'deadlines' ],
				'pro'         => true,
				'settings'    => [
					'due_date' => [
						'type'        => 'string',
						'required'    => true,
						'description' => 'Target date in Y-m-d H:i format',
					],
					'label_days'    => [ 'type' => 'string', 'default' => 'Days' ],
					'label_hours'   => [ 'type' => 'string', 'default' => 'Hours' ],
					'label_minutes' => [ 'type' => 'string', 'default' => 'Minutes' ],
					'label_seconds' => [ 'type' => 'string', 'default' => 'Seconds' ],
				],
				'example'     => [
					'id'         => 'pro7890',
					'elType'     => 'widget',
					'widgetType' => 'countdown',
					'settings'   => [
						'due_date' => '2025-12-31 23:59',
					],
				],
			],

			'flip-box' => [
				'name'        => 'flip-box',
				'label'       => 'Flip Box',
				'description' => 'Interactive card that flips on hover.',
				'use_cases'   => [ 'features', 'team members', 'services', 'interactive elements' ],
				'pro'         => true,
				'settings'    => [
					'title_text_a'       => [ 'type' => 'string' ],
					'description_text_a' => [ 'type' => 'string' ],
					'title_text_b'       => [ 'type' => 'string' ],
					'description_text_b' => [ 'type' => 'string' ],
				],
				'example'     => [
					'id'         => 'proflip',
					'elType'     => 'widget',
					'widgetType' => 'flip-box',
					'settings'   => [
						'title_text_a'       => 'Front Title',
						'description_text_a' => 'Front description text.',
						'title_text_b'       => 'Back Title',
						'description_text_b' => 'Back description with more details.',
					],
				],
			],
		];
	}

	/**
	 * Get widget definition by name.
	 *
	 * @param string $widget_name The widget name.
	 * @return array|null Widget definition or null.
	 */
	public static function get_widget( $widget_name ) {
		$all = self::get_all( true );
		return isset( $all[ $widget_name ] ) ? $all[ $widget_name ] : null;
	}

	/**
	 * Get widget examples for prompt building.
	 *
	 * @param array $widget_names Optional specific widgets to include.
	 * @return array Widget examples.
	 */
	public static function get_examples( $widget_names = [] ) {
		$all      = self::get_all();
		$examples = [];

		foreach ( $all as $name => $definition ) {
			if ( ! empty( $widget_names ) && ! in_array( $name, $widget_names, true ) ) {
				continue;
			}

			if ( isset( $definition['example'] ) ) {
				$examples[ $name ] = $definition['example'];
			}
		}

		return $examples;
	}

	/**
	 * Get a summary of widgets for prompts.
	 *
	 * @return string Widget summary text.
	 */
	public static function get_prompt_summary() {
		$widgets = self::get_all();
		$lines   = [];

		foreach ( $widgets as $name => $definition ) {
			$use_cases = isset( $definition['use_cases'] ) ? implode( ', ', $definition['use_cases'] ) : '';
			$pro_tag   = ! empty( $definition['pro'] ) ? ' [PRO]' : '';
			$lines[]   = sprintf( '- %s%s: %s. Use for: %s', $name, $pro_tag, $definition['description'], $use_cases );
		}

		return implode( "\n", $lines );
	}
}
