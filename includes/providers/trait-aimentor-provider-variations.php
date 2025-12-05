<?php

trait AiMentor_Provider_Variations_Trait {
/**
 * Sanitize the requested variation count and clamp it to a sensible range.
 *
 * @param int $value Requested variation count.
 * @param int $max   Maximum variations supported.
 *
 * @return int
 */
protected function sanitize_variation_count( $value, $max = 4 ) {
$value = absint( $value );

if ( $value < 1 ) {
$value = 1;
}

if ( $max > 0 && $value > $max ) {
$value = $max;
}

return $value;
}

/**
 * Build a localized label for a variation index.
 *
 * @param int $index Zero-based index.
 *
 * @return string
 */
protected function get_variation_label( $index ) {
$number = absint( $index ) + 1;

/* translators: %d: Variation number. */
return sprintf( __( 'Variation %d', 'aimentor' ), $number );
}

/**
 * Summarize Elementor canvas layout structure for quick descriptions.
 *
 * @param array $layout Canvas layout array.
 *
 * @return string
 */
protected function describe_canvas_layout( $layout ) {
$meta = $this->analyze_canvas_layout_counts( $layout );

$parts = array();

if ( $meta['sections'] > 0 ) {
$parts[] = sprintf(
_n( '%d section', '%d sections', $meta['sections'], 'aimentor' ),
number_format_i18n( $meta['sections'] )
);
}

if ( $meta['columns'] > 0 ) {
$parts[] = sprintf(
_n( '%d column', '%d columns', $meta['columns'], 'aimentor' ),
number_format_i18n( $meta['columns'] )
);
}

if ( $meta['widgets'] > 0 ) {
$parts[] = sprintf(
_n( '%d widget', '%d widgets', $meta['widgets'], 'aimentor' ),
number_format_i18n( $meta['widgets'] )
);
}

if ( empty( $parts ) ) {
return __( 'Layout variation', 'aimentor' );
}

return implode( _x( ' • ', 'separator between variation meta details', 'aimentor' ), $parts );
}

/**
 * Analyse the Elementor layout array for section/column/widget counts.
 *
 * @param mixed $layout Layout data.
 *
 * @return array
 */
protected function analyze_canvas_layout_counts( $layout ) {
$counts = array(
'sections' => 0,
'columns'  => 0,
'widgets'  => 0,
);

$walker = function( $elements ) use ( &$walker, &$counts ) {
if ( ! is_array( $elements ) ) {
return;
}

foreach ( $elements as $element ) {
if ( ! is_array( $element ) ) {
continue;
}

$type = isset( $element['elType'] ) ? $element['elType'] : '';

switch ( $type ) {
case 'section':
$counts['sections']++;
break;
case 'column':
$counts['columns']++;
break;
case 'widget':
$counts['widgets']++;
break;
}

if ( isset( $element['elements'] ) ) {
$walker( $element['elements'] );
}
}
};

if ( isset( $layout['elements'] ) && is_array( $layout['elements'] ) ) {
$walker( $layout['elements'] );
} elseif ( is_array( $layout ) ) {
$walker( $layout );
}

return $counts;
}

/**
 * Normalize raw canvas JSON strings into structured variation payloads.
 *
 * Uses the schema validator and repair system to ensure valid Elementor JSON.
 *
 * @param array $raw_messages Raw message strings from the provider.
 * @param array $rate_limit   Rate limit payload for error context.
 *
 * @return array|WP_Error
 */
protected function build_canvas_variations( array $raw_messages, $rate_limit = array() ) {
$variations = array();
$validator  = null;
$repair     = null;

// Initialize validator and repair if available.
if ( class_exists( 'AiMentor_Elementor_Schema_Validator' ) ) {
$validator = new AiMentor_Elementor_Schema_Validator();
}

if ( class_exists( 'AiMentor_Elementor_JSON_Repair' ) ) {
$repair = new AiMentor_Elementor_JSON_Repair();
}

foreach ( $raw_messages as $index => $raw_message ) {
$raw = trim( (string) $raw_message );

if ( '' === $raw ) {
continue;
}

$decoded      = null;
$repairs_made = array();

// Step 1: Try direct JSON decode.
$decoded = json_decode( $raw, true );

if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
// Step 2: Try repair if available.
if ( null !== $repair ) {
$repair_result = $repair->repair( $raw );

if ( $repair_result['success'] && is_array( $repair_result['data'] ) ) {
$decoded      = $repair_result['data'];
$repairs_made = $repair_result['repairs'];
}
}

// Still invalid after repair attempt.
if ( null === $decoded || ! is_array( $decoded ) ) {
return new WP_Error(
'aimentor_invalid_canvas',
__( 'The response was not valid Elementor JSON and could not be repaired.', 'aimentor' ),
array(
'content'    => $raw,
'rate_limit' => $rate_limit,
)
);
}
}

// Step 3: Validate structure if validator available.
if ( null !== $validator ) {
$validation = $validator->validate( $decoded );

if ( ! $validation['valid'] && null !== $repair ) {
// Try to repair validation errors.
$repair_result = $repair->repair( $decoded );

if ( $repair_result['success'] ) {
$decoded = $repair_result['data'];
$repairs_made = array_merge( $repairs_made, $repair_result['repairs'] );

// Re-validate after repair.
$validation = $validator->validate( $decoded );
}
}

// Log warnings but don't fail on them.
if ( ! empty( $validation['warnings'] ) ) {
$this->log_canvas_warnings( $validation['warnings'], $index );
}
}

// Normalize the output structure.
$layout = $decoded;
if ( isset( $decoded['elements'] ) ) {
$layout = $decoded['elements'];
}

$meta    = $this->analyze_canvas_layout_counts( $decoded );
$label   = $this->get_variation_label( $index );
$summary = $this->describe_canvas_layout( $decoded );

$variations[] = array(
'id'      => 'canvas-' . ( $index + 1 ),
'label'   => $label,
'summary' => $summary,
'layout'  => $layout,
'raw'     => $raw,
'meta'    => $meta,
'repairs' => $repairs_made,
);
}

if ( empty( $variations ) ) {
return new WP_Error(
'aimentor_empty_response',
__( 'The API response did not include generated content.', 'aimentor' ),
array(
'rate_limit' => $rate_limit,
)
);
}

return $variations;
}

/**
 * Log canvas validation warnings.
 *
 * @param array $warnings Validation warnings.
 * @param int   $index    Variation index.
 */
protected function log_canvas_warnings( $warnings, $index ) {
if ( empty( $warnings ) || ! function_exists( 'aimentor_log_error' ) ) {
return;
}

foreach ( $warnings as $warning ) {
aimentor_log_error( sprintf(
'Canvas variation %d warning: %s (at %s)',
$index + 1,
$warning['message'],
$warning['path'] ?? 'unknown'
) );
}
}

/**
 * Normalize raw HTML/text variations.
 *
 * @param array $raw_messages Raw message strings from the provider.
 *
 * @return array|WP_Error
 */
protected function build_content_variations( array $raw_messages ) {
$variations = array();

foreach ( $raw_messages as $index => $raw_message ) {
$content = trim( (string) $raw_message );

if ( '' === $content ) {
continue;
}

$label   = $this->get_variation_label( $index );
$summary = $this->summarize_html_snippet( $content );

$variations[] = array(
'id'      => 'content-' . ( $index + 1 ),
'label'   => $label,
'summary' => $summary,
'html'    => $content,
);
}

if ( empty( $variations ) ) {
return new WP_Error(
'aimentor_empty_response',
__( 'The API response did not include generated content.', 'aimentor' )
);
}

return $variations;
}

/**
 * Generate a concise summary snippet for textual content.
 *
 * @param string $html Raw HTML/text returned by the provider.
 *
 * @return string
 */
protected function summarize_html_snippet( $html ) {
$text = wp_strip_all_tags( (string) $html );
$text = trim( preg_replace( '/\s+/', ' ', $text ) );

if ( '' === $text ) {
return $this->get_variation_label( 0 );
}

if ( mb_strlen( $text ) > 140 ) {
$text = mb_substr( $text, 0, 137 ) . '…';
}

return $text;
}
}

