<?php

class AiMentor_Anthropic_Provider implements AiMentor_Provider_Interface {
    use AiMentor_Provider_Variations_Trait;
    const PROVIDER_KEY = 'anthropic';
    const API_URL = 'https://api.anthropic.com/v1/messages';
    const API_VERSION = '2023-06-01';

    public function supports_canvas() {
        return true;
    }

    public function build_request( $prompt, $args = [] ) {
        $api_key = isset( $args['api_key'] ) ? trim( $args['api_key'] ) : '';

        if ( empty( $api_key ) ) {
            return new WP_Error( 'aimentor_missing_api_key', __( 'Anthropic API key not configured.', 'aimentor' ) );
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

        $body = [
            'model'       => $model,
            'max_tokens'  => $max_tokens,
            'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
            'temperature' => $this->get_temperature( $context ),
        ];

        $system_instruction = '';

        if ( ! empty( $context['system'] ) ) {
            $system_instruction = $context['system'];
        } elseif ( 'canvas' === $context['task'] ) {
            $system_instruction = __( 'Return only valid Elementor JSON for the requested layout. Avoid commentary.', 'aimentor' );
        }

        if ( '' !== $system_instruction ) {
            $body['system'] = $system_instruction;
        }

        return [
            'url'      => self::API_URL,
            'args'     => [
                'headers' => [
                    'x-api-key'          => $api_key,
                    'anthropic-version'  => self::API_VERSION,
                    'content-type'       => 'application/json',
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

        $variation_count = isset( $request['context']['variations'] ) ? absint( $request['context']['variations'] ) : 1;

        if ( $variation_count < 1 ) {
            $variation_count = 1;
        }

        $responses = [];

        for ( $i = 0; $i < $variation_count; $i++ ) {
            $response = wp_remote_post( $request['url'], $request['args'] );

            if ( is_wp_error( $response ) ) {
                return new WP_Error(
                    'aimentor_http_request_failed',
                    sprintf( __( 'API request failed: %s', 'aimentor' ), $response->get_error_message() ),
                    [ 'original' => $response ]
                );
            }

            $responses[] = $response;
        }

        return $this->parse_response(
            $responses,
            [
                'context' => $request['context'],
            ]
        );
    }

    public function parse_response( $response, $args = [] ) {
        $context = $this->normalize_context( $args['context'] ?? [] );

        $responses = is_array( $response ) ? $response : array( $response );
        $rate_limit = array();
        $decoded_bodies = array();
        $messages = array();

        foreach ( $responses as $single_response ) {
            $status_code = wp_remote_retrieve_response_code( $single_response );
            $raw_body    = wp_remote_retrieve_body( $single_response );
            $body        = json_decode( $raw_body, true );
            $json_error  = json_last_error();
            $rate_limit  = $this->extract_rate_limit_headers( $single_response );

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

            $decoded_bodies[] = $body;

            $content = '';

            if ( isset( $body['content'] ) && is_array( $body['content'] ) ) {
                $first_block = $body['content'][0] ?? [];
                if ( is_array( $first_block ) ) {
                    $content = isset( $first_block['text'] ) ? trim( (string) $first_block['text'] ) : '';
                }
            }

            if ( '' === $content && isset( $body['output_text'] ) ) {
                $content = trim( (string) $body['output_text'] );
            }

            if ( '' !== $content ) {
                $messages[] = $content;
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
                'raw'              => count( $decoded_bodies ) === 1 ? $decoded_bodies[0] : $decoded_bodies,
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
            'raw'                => count( $decoded_bodies ) === 1 ? $decoded_bodies[0] : $decoded_bodies,
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
        $numeric_candidates = [
            'limit_requests' => [ 'anthropic-ratelimit-requests-limit', 'anthropic-ratelimit-requests' ],
            'remaining_requests' => [ 'anthropic-ratelimit-requests-remaining' ],
            'limit_tokens' => [ 'anthropic-ratelimit-input-tokens-limit', 'anthropic-ratelimit-input-tokens' ],
            'remaining_tokens' => [ 'anthropic-ratelimit-input-tokens-remaining' ],
        ];

        foreach ( $numeric_candidates as $key => $header_names ) {
            foreach ( $header_names as $header_name ) {
                if ( isset( $headers[ $header_name ] ) ) {
                    $value = $this->sanitize_rate_limit_number( $headers[ $header_name ] );

                    if ( null !== $value ) {
                        $rate_limit[ $key ] = $value;
                        break;
                    }
                }
            }
        }

        $retry_after_seconds  = $this->parse_rate_limit_seconds( $headers['retry-after'] ?? null );
        $reset_requests       = $this->parse_rate_limit_seconds( $headers['anthropic-ratelimit-requests-reset'] ?? null );
        $reset_tokens         = $this->parse_rate_limit_seconds( $headers['anthropic-ratelimit-tokens-reset'] ?? $headers['anthropic-ratelimit-input-tokens-reset'] ?? null );

        $cooldown_candidates = array_filter(
            [ $retry_after_seconds, $reset_requests, $reset_tokens ],
            function ( $value ) {
                return is_numeric( $value ) && $value > 0;
            }
        );

        if ( null !== $retry_after_seconds ) {
            $rate_limit['retry_after_seconds'] = $retry_after_seconds;
        }

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
        if ( ! function_exists( 'aimentor_get_model_presets' ) ) {
            return 'claude-3-5-haiku';
        }

        $presets = aimentor_get_model_presets();

        if ( isset( $presets[ self::PROVIDER_KEY ][ $context['task'] ][ $context['tier'] ] ) ) {
            return $presets[ self::PROVIDER_KEY ][ $context['task'] ][ $context['tier'] ];
        }

        return 'claude-3-5-haiku';
    }

    protected function get_default_timeout( $context ) {
        $override = $this->get_task_override( $context, 'timeout' );

        if ( null !== $override ) {
            return $override;
        }

        return 'canvas' === $context['task'] ? 60 : 45;
    }

    protected function get_temperature( $context ) {
        $override = $this->get_task_override( $context, 'temperature' );

        if ( null !== $override ) {
            return $override;
        }

        return 'canvas' === $context['task'] ? 0.3 : 0.7;
    }

    protected function get_prompt_suffix( $context ) {
        $brand  = $context['brand'];
        $intent = isset( $context['intent'] ) ? $context['intent'] : 'generate';

        $suffix_parts = [];

        if ( 'rewrite' === $intent ) {
            $suffix_parts[] = __( 'Return only the rewritten copy ready to paste into Elementor while preserving any existing HTML tags, shortcodes, or dynamic placeholders.', 'aimentor' );
        } elseif ( 'canvas' === $context['task'] ) {
            $suffix_parts[] = __( 'Respond using structured Elementor JSON suitable for direct canvas insertion.', 'aimentor' );
        } else {
            $suffix_parts[] = __( 'Respond with concise Elementor-ready HTML blocks.', 'aimentor' );
        }

        if ( '' !== $brand['primary_color'] ) {
            $suffix_parts[] = sprintf(
                /* translators: %s: Hex color. */
                __( 'Primary brand color: %s.', 'aimentor' ),
                $brand['primary_color']
            );
        }

        if ( '' !== $brand['tone_keywords'] ) {
            $suffix_parts[] = sprintf(
                /* translators: %s: Tone keywords. */
                __( 'Preferred voice: %s.', 'aimentor' ),
                $brand['tone_keywords']
            );
        }

        $knowledge_guidance = $this->get_knowledge_prompt_guidance( $context['knowledge'] ?? [] );

        if ( '' !== $knowledge_guidance ) {
            $suffix_parts[] = $knowledge_guidance;
        }

        if ( empty( $suffix_parts ) ) {
            return '';
        }

        return '\n\n' . implode( ' ', $suffix_parts );
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
            $parts[] = sprintf( __( 'Knowledge summaries: %s.', 'aimentor' ), $normalized['summary'] );
        }

        if ( ! empty( $normalized['guidance'] ) ) {
            $parts[] = sprintf( __( 'Apply these directives:%s%s', 'aimentor' ), PHP_EOL, $normalized['guidance'] );
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
                $parts[] = sprintf( __( 'Incorporate insights from: %s.', 'aimentor' ), implode( ', ', $labels ) );
            }
        }

        return implode( ' ', $parts );
    }
}
