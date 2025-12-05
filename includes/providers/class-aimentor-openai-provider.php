<?php

class AiMentor_OpenAI_Provider implements AiMentor_Provider_Interface {
    use AiMentor_Provider_Variations_Trait;
    const PROVIDER_KEY = 'openai';
    const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function supports_canvas() {
        return true;
    }

    public function build_request( $prompt, $args = [] ) {
        $api_key = isset( $args['api_key'] ) ? trim( $args['api_key'] ) : '';

        if ( empty( $api_key ) ) {
            return new WP_Error( 'aimentor_missing_api_key', __( 'OpenAI API key not configured.', 'aimentor' ) );
        }

        $context = isset( $args['context'] ) && is_array( $args['context'] )
            ? $args['context']
            : [];

        $context          = $this->normalize_context( $context );
        $variation_count  = isset( $args['variations'] ) ? $this->sanitize_variation_count( $args['variations'] ) : 1;
        $context['variations'] = $variation_count;

        $model      = ! empty( $args['model'] ) ? sanitize_text_field( $args['model'] ) : $this->get_default_model( $context );
        $max_tokens = isset( $args['max_tokens'] ) ? absint( $args['max_tokens'] ) : 2000;
        $timeout    = isset( $args['timeout'] ) ? absint( $args['timeout'] ) : $this->get_default_timeout( $context );
        $prompt     = trim( (string) $prompt ) . $this->get_prompt_suffix( $context );

        $messages = [];

        if ( ! empty( $context['system'] ) ) {
            $messages[] = [
                'role'    => 'system',
                'content' => $context['system'],
            ];
        }

        $messages[] = [
            'role'    => 'user',
            'content' => $prompt,
        ];

        $body = [
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => $max_tokens,
            'temperature' => $this->get_temperature( $context ),
        ];

        if ( 'canvas' === $context['task'] ) {
            $body['response_format'] = [ 'type' => 'json_object' ];
        }

        if ( $context['variations'] > 1 ) {
            $body['n'] = $context['variations'];
        }

        return [
            'url'      => self::API_URL,
            'args'     => [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $body ),
                'timeout' => $timeout,
            ],
            'context' => $context,
        ];
    }

    public function request( $prompt, $args = [] ) {
        $request = $this->build_request( $prompt, $args );

        if ( is_wp_error( $request ) ) {
            return $request;
        }

        $response = wp_remote_post( $request['url'], $request['args'] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'aimentor_http_request_failed',
                sprintf( __( 'API request failed: %s', 'aimentor' ), $response->get_error_message() ),
                [ 'original' => $response ]
            );
        }

        return $this->parse_response(
            $response,
            [
                'context' => $request['context'],
            ]
        );
    }

    public function parse_response( $response, $args = [] ) {
        $context = $this->normalize_context( $args['context'] ?? [] );

        $status_code = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $body        = json_decode( $raw_body, true );
        $json_error  = json_last_error();
        $rate_limit  = $this->extract_rate_limit_headers( $response );

        if ( 200 !== $status_code ) {
            $error_detail = '';

            if ( is_array( $body ) && isset( $body['error']['message'] ) ) {
                $error_detail = $body['error']['message'];
            } elseif ( ! empty( $raw_body ) ) {
                $error_detail = $raw_body;
            }

            $message = sprintf( 'API request failed with HTTP %d', $status_code );

            if ( ! empty( $error_detail ) ) {
                $message .= ': ' . $error_detail;
            }

            return new WP_Error(
                'aimentor_http_error',
                $message,
                [
                    'status_code' => $status_code,
                    'raw_body'    => $raw_body,
                    'rate_limit'  => $rate_limit,
                ]
            );
        }

        if ( JSON_ERROR_NONE !== $json_error || ! is_array( $body ) ) {
            return new WP_Error(
                'aimentor_invalid_response',
                __( 'Unexpected response from the API.', 'aimentor' ),
                [
                    'raw_body'   => $raw_body,
                    'rate_limit' => $rate_limit,
                ]
            );
        }

        $choices  = isset( $body['choices'] ) && is_array( $body['choices'] ) ? $body['choices'] : [];
        $messages = [];

        foreach ( $choices as $choice ) {
            $message_content = '';

            if ( isset( $choice['message'] ) && is_array( $choice['message'] ) && isset( $choice['message']['content'] ) ) {
                $message_content = trim( (string) $choice['message']['content'] );
            }

            if ( '' !== $message_content ) {
                $messages[] = $message_content;
            }
        }

        $type = 'canvas' === $context['task'] ? 'canvas' : 'content';

        if ( 'canvas' === $type ) {
            $variations = $this->build_canvas_variations( $messages, $rate_limit );

            if ( is_wp_error( $variations ) ) {
                return $variations;
            }

            $response_payload = [
                'type'             => 'canvas',
                'content'          => $variations[0]['layout'],
                'raw'              => $body,
                'rate_limit'       => $rate_limit,
                'canvas_variations'=> $variations,
            ];

            if ( ! empty( $variations[0]['summary'] ) ) {
                $response_payload['summary'] = $variations[0]['summary'];
            }

            return $response_payload;
        }

        $variations = $this->build_content_variations( $messages );

        if ( is_wp_error( $variations ) ) {
            return $variations;
        }

        $response_payload = [
            'type'               => 'content',
            'content'            => $variations[0]['html'],
            'raw'                => $body,
            'rate_limit'         => $rate_limit,
            'content_variations' => $variations,
        ];

        if ( ! empty( $variations[0]['summary'] ) ) {
            $response_payload['summary'] = $variations[0]['summary'];
        }

        return $response_payload;
    }

    protected function extract_rate_limit_headers( $response ) {
        $headers = wp_remote_retrieve_headers( $response );

        if ( empty( $headers ) ) {
            return [];
        }

        if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
            $headers = $headers->getAll();
        }

        if ( ! is_array( $headers ) ) {
            return [];
        }

        $headers = array_change_key_case( $headers, CASE_LOWER );

        $rate_limit = [];
        $numeric_map = [
            'x-ratelimit-limit-requests'     => 'limit_requests',
            'x-ratelimit-limit-tokens'       => 'limit_tokens',
            'x-ratelimit-remaining-requests' => 'remaining_requests',
            'x-ratelimit-remaining-tokens'   => 'remaining_tokens',
        ];

        foreach ( $numeric_map as $header => $key ) {
            if ( isset( $headers[ $header ] ) ) {
                $value = $this->sanitize_rate_limit_number( $headers[ $header ] );

                if ( null !== $value ) {
                    $rate_limit[ $key ] = $value;
                }
            }
        }

        $retry_after_seconds      = $this->parse_rate_limit_seconds( $headers['retry-after'] ?? null );
        $reset_requests_seconds   = $this->parse_rate_limit_seconds( $headers['x-ratelimit-reset-requests'] ?? null );
        $reset_tokens_seconds     = $this->parse_rate_limit_seconds( $headers['x-ratelimit-reset-tokens'] ?? null );

        if ( null !== $retry_after_seconds ) {
            $rate_limit['retry_after_seconds'] = $retry_after_seconds;
        }

        if ( null !== $reset_requests_seconds ) {
            $rate_limit['reset_requests_seconds'] = $reset_requests_seconds;
        }

        if ( null !== $reset_tokens_seconds ) {
            $rate_limit['reset_tokens_seconds'] = $reset_tokens_seconds;
        }

        $cooldown_candidates = array_filter(
            [ $retry_after_seconds, $reset_requests_seconds, $reset_tokens_seconds ],
            function ( $value ) {
                return is_numeric( $value ) && $value > 0;
            }
        );

        if ( $cooldown_candidates ) {
            $cooldown_seconds = (int) ceil( max( $cooldown_candidates ) );
            $rate_limit['cooldown_seconds'] = $cooldown_seconds;
            $rate_limit['cooldown_human']   = $this->format_cooldown_human( $cooldown_seconds );
        }

        return array_filter(
            $rate_limit,
            function ( $value ) {
                return null !== $value && '' !== $value;
            }
        );
    }

    protected function sanitize_rate_limit_number( $value ) {
        if ( is_array( $value ) ) {
            $value = reset( $value );
        }

        if ( is_numeric( $value ) ) {
            return 0 + $value;
        }

        return null;
    }

    protected function parse_rate_limit_seconds( $value ) {
        if ( is_array( $value ) ) {
            $value = reset( $value );
        }

        if ( null === $value || '' === $value ) {
            return null;
        }

        if ( is_numeric( $value ) ) {
            $seconds = (float) $value;

            return $seconds >= 0 ? $seconds : null;
        }

        $timestamp = strtotime( (string) $value );

        if ( false === $timestamp ) {
            return null;
        }

        $diff = $timestamp - time();

        return $diff > 0 ? $diff : null;
    }

    protected function format_cooldown_human( $seconds ) {
        $seconds = absint( $seconds );

        if ( $seconds <= 0 ) {
            return '';
        }

        if ( $seconds < MINUTE_IN_SECONDS ) {
            return sprintf(
                _n( '%s second', '%s seconds', $seconds, 'aimentor' ),
                number_format_i18n( $seconds )
            );
        }

        return human_time_diff( time(), time() + $seconds );
    }

    protected function normalize_context( $context ) {
        if ( ! is_array( $context ) ) {
            $context = [];
        }

        $task   = isset( $context['task'] ) ? sanitize_key( $context['task'] ) : 'content';
        $tier   = isset( $context['tier'] ) ? sanitize_key( $context['tier'] ) : 'fast';
        $intent = isset( $context['intent'] ) ? sanitize_key( $context['intent'] ) : 'generate';

        if ( ! in_array( $task, [ 'canvas', 'content' ], true ) ) {
            $task = 'content';
        }

        if ( ! in_array( $tier, [ 'fast', 'quality' ], true ) ) {
            $tier = 'fast';
        }

        if ( ! in_array( $intent, [ 'generate', 'rewrite' ], true ) ) {
            $intent = 'generate';
        }

        $brand      = $this->build_brand_context( $context );
        $knowledge  = $this->normalize_knowledge_context( $context['knowledge'] ?? [] );
        $variations = isset( $context['variations'] ) ? $this->sanitize_variation_count( $context['variations'] ) : 1;

        $system_instruction = '';

        if ( isset( $context['system'] ) && '' !== trim( $context['system'] ) ) {
            $system_instruction = sanitize_textarea_field( (string) $context['system'] );
        } elseif ( 'canvas' === $task && class_exists( 'AiMentor_Elementor_Prompt_Builder' ) ) {
            // Use enhanced prompt builder for canvas tasks.
            $prompt_builder = new AiMentor_Elementor_Prompt_Builder();
            $system_instruction = $prompt_builder->get_system_instruction( [
                'brand'     => $brand,
                'knowledge' => $knowledge,
            ] );
        }

        return [
            'task'       => $task,
            'tier'       => $tier,
            'brand'      => $brand,
            'knowledge'  => $knowledge,
            'variations' => $variations,
            'intent'     => $intent,
            'system'     => $system_instruction,
        ];
    }

    protected function build_brand_context( $context ) {
        $brand = [
            'primary_color' => '',
            'tone_keywords' => '',
        ];

        if ( function_exists( 'aimentor_get_brand_preferences' ) ) {
            $defaults = aimentor_get_brand_preferences();

            if ( is_array( $defaults ) ) {
                $brand = array_merge( $brand, array_intersect_key( $defaults, $brand ) );
            }
        }

        if ( isset( $context['brand'] ) && is_array( $context['brand'] ) ) {
            if ( array_key_exists( 'primary_color', $context['brand'] ) ) {
                $value = $context['brand']['primary_color'];

                if ( function_exists( 'aimentor_sanitize_primary_color' ) ) {
                    $brand['primary_color'] = aimentor_sanitize_primary_color( $value );
                } else {
                    $sanitized = sanitize_hex_color( $value );
                    $brand['primary_color'] = $sanitized ? strtoupper( $sanitized ) : $brand['primary_color'];
                }
            }

            if ( array_key_exists( 'tone_keywords', $context['brand'] ) ) {
                $value = $context['brand']['tone_keywords'];

                if ( function_exists( 'aimentor_sanitize_tone_keywords' ) ) {
                    $brand['tone_keywords'] = aimentor_sanitize_tone_keywords( $value );
                } else {
                    $sanitized = sanitize_textarea_field( $value );
                    $brand['tone_keywords'] = trim( preg_replace( '/\s+/', ' ', $sanitized ) );
                }
            }
        }

        return $brand;
    }

    protected function get_request_overrides() {
        if ( ! function_exists( 'aimentor_get_request_overrides' ) ) {
            return [];
        }

        $overrides = aimentor_get_request_overrides();

        if ( isset( $overrides[ self::PROVIDER_KEY ] ) && is_array( $overrides[ self::PROVIDER_KEY ] ) ) {
            return $overrides[ self::PROVIDER_KEY ];
        }

        return [];
    }

    protected function get_task_override( $context, $field ) {
        $overrides = $this->get_request_overrides();
        $task      = isset( $context['task'] ) ? $context['task'] : 'content';

        if ( ! isset( $overrides[ $task ] ) || ! is_array( $overrides[ $task ] ) ) {
            return null;
        }

        if ( ! array_key_exists( $field, $overrides[ $task ] ) ) {
            return null;
        }

        $value = $overrides[ $task ][ $field ];

        if ( '' === $value ) {
            return null;
        }

        if ( 'temperature' === $field ) {
            return (float) $value;
        }

        return (int) $value;
    }

    protected function get_default_model( $context ) {
        $defaults = [
            'canvas'  => [
                'fast'    => 'gpt-4.1-nano',
                'quality' => 'o4-mini',
            ],
            'content' => [
                'fast'    => 'gpt-4o-mini',
                'quality' => 'gpt-4o',
            ],
        ];

        $task_defaults = $defaults[ $context['task'] ] ?? $defaults['content'];

        return $task_defaults[ $context['tier'] ] ?? $defaults['content']['fast'];
    }

    protected function get_prompt_suffix( $context ) {
        $intent = isset( $context['intent'] ) ? $context['intent'] : 'generate';

        if ( 'rewrite' === $intent ) {
            $suffix = ' Return only the rewritten copy ready to paste into Elementor. Maintain any HTML tags, shortcodes, or dynamic placeholders present in the source.';
        } elseif ( 'canvas' === $context['task'] ) {
            $suffix = ' Respond using Elementor JSON schema with widgets, containers, and layout metadata.';
        } else {
            $suffix = ' Respond with concise Elementor HTML blocks optimized for fast editing.';
        }

        $brand = isset( $context['brand'] ) ? $context['brand'] : [];

        $brand_guidance     = $this->get_brand_prompt_guidance( $brand );
        $knowledge_guidance = $this->get_knowledge_prompt_guidance( $context['knowledge'] ?? [] );

        return $suffix . $brand_guidance . $knowledge_guidance;
    }

    protected function get_temperature( $context ) {
        $override = $this->get_task_override( $context, 'temperature' );

        if ( null !== $override ) {
            return $override;
        }

        if ( 'canvas' === $context['task'] ) {
            return 'quality' === $context['tier'] ? 0.25 : 0.2;
        }

        return 'quality' === $context['tier'] ? 0.65 : 0.55;
    }

    protected function get_default_timeout( $context ) {
        $override = $this->get_task_override( $context, 'timeout' );

        if ( null !== $override ) {
            return $override;
        }

        if ( 'canvas' === $context['task'] ) {
            return 'quality' === $context['tier'] ? 90 : 60;
        }

        return 'quality' === $context['tier'] ? 50 : 35;
    }

    protected function get_brand_prompt_guidance( $brand ) {
        if ( ! is_array( $brand ) ) {
            $brand = [];
        }

        $primary_color = isset( $brand['primary_color'] ) ? $brand['primary_color'] : '';
        $tone_keywords = isset( $brand['tone_keywords'] ) ? $brand['tone_keywords'] : '';

        $parts = [];

        if ( '' !== $primary_color ) {
            $parts[] = sprintf( 'Anchor styling around the brand primary color %s.', strtoupper( $primary_color ) );
        }

        if ( '' !== $tone_keywords ) {
            $parts[] = sprintf( 'Keep the writing voice aligned with these tone keywords: %s.', $tone_keywords );
        }

        if ( empty( $parts ) ) {
            return '';
        }

        return ' ' . implode( ' ', $parts );
    }

    protected function normalize_knowledge_context( $knowledge ) {
        if ( ! is_array( $knowledge ) ) {
            return [];
        }

        $ids = isset( $knowledge['ids'] ) && is_array( $knowledge['ids'] ) ? array_map( 'sanitize_text_field', $knowledge['ids'] ) : [];
        $ids = array_values( array_unique( array_filter( $ids ) ) );

        $packs = [];

        if ( isset( $knowledge['packs'] ) && is_array( $knowledge['packs'] ) ) {
            foreach ( $knowledge['packs'] as $pack ) {
                if ( ! is_array( $pack ) ) {
                    continue;
                }

                $id       = isset( $pack['id'] ) ? sanitize_text_field( $pack['id'] ) : '';
                $title    = isset( $pack['title'] ) ? sanitize_text_field( $pack['title'] ) : '';
                $summary  = isset( $pack['summary'] ) ? sanitize_textarea_field( $pack['summary'] ) : '';
                $guidance = isset( $pack['guidance'] ) ? sanitize_textarea_field( $pack['guidance'] ) : '';

                if ( '' === $id && '' === $title ) {
                    continue;
                }

                $packs[] = [
                    'id'       => $id,
                    'title'    => $title,
                    'summary'  => $summary,
                    'guidance' => $guidance,
                ];
            }
        }

        $summary  = isset( $knowledge['summary'] ) ? sanitize_textarea_field( $knowledge['summary'] ) : '';
        $guidance = isset( $knowledge['guidance'] ) ? sanitize_textarea_field( $knowledge['guidance'] ) : '';

        if ( empty( $packs ) && '' === $summary && '' === $guidance ) {
            return [];
        }

        return [
            'packs'    => $packs,
            'ids'      => $ids,
            'summary'  => $summary,
            'guidance' => $guidance,
        ];
    }

    protected function get_knowledge_prompt_guidance( $knowledge ) {
        $normalized = $this->normalize_knowledge_context( $knowledge );

        if ( empty( $normalized ) ) {
            return '';
        }

        $parts = [];

        if ( ! empty( $normalized['summary'] ) ) {
            $parts[] = sprintf( 'Reference these knowledge summaries: %s.', $normalized['summary'] );
        }

        if ( ! empty( $normalized['guidance'] ) ) {
            $parts[] = sprintf( "Follow these directives:\n%s", $normalized['guidance'] );
        }

        if ( empty( $parts ) && ! empty( $normalized['packs'] ) ) {
            $labels = [];

            foreach ( $normalized['packs'] as $pack ) {
                $label = $pack['title'] ? $pack['title'] : $pack['id'];

                if ( '' !== $label ) {
                    $labels[] = $label;
                }
            }

            if ( ! empty( $labels ) ) {
                $parts[] = sprintf( 'Incorporate insights from these knowledge packs: %s.', implode( ', ', $labels ) );
            }
        }

        if ( empty( $parts ) ) {
            return '';
        }

        return ' ' . implode( ' ', $parts );
    }
}
