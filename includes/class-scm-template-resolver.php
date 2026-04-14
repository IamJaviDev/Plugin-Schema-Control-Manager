<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Resolves {{ placeholder }} tokens inside custom schema JSON strings.
 *
 * Placeholders are replaced with live values from the current request context.
 * Unresolvable placeholders become empty strings — no exceptions are thrown.
 * Schemas that contain no '{{' are returned unchanged with zero processing cost.
 *
 * Supported tokens:
 *   {{post_title}}          — singular post/page title
 *   {{post_url}}            — singular post/page permalink
 *   {{post_excerpt}}        — singular post/page excerpt (tags stripped)
 *   {{post_id}}             — singular post/page ID
 *   {{post_type}}           — post type of the singular object
 *   {{meta:FIELD_KEY}}      — get_post_meta() value for FIELD_KEY
 *   {{term:TAXONOMY}}       — first term name for the post in TAXONOMY
 *   {{queried_term_name}}   — display name of the current term archive
 *   {{queried_term_slug}}   — slug of the current term archive
 *   {{queried_taxonomy}}    — taxonomy of the current term archive
 *   {{author_name}}         — display name on an author archive
 *   {{author_slug}}         — nicename on an author archive
 *   {{site_name}}           — site name (get_bloginfo('name'))
 *   {{site_url}}            — site home URL (home_url('/'))
 *   {{archive_post_type}}   — post type slug on a CPT archive page
 *   {{archive_post_type_label}} — singular label of the CPT on an archive page
 *   {{featured_image_url}}  — full URL of the featured image for the current singular post
 *   {{post_date}}           — published date of the current singular post (W3C/ISO 8601)
 *   {{post_modified_date}}  — last-modified date of the current singular post (W3C/ISO 8601)
 */
class SCM_Template_Resolver {

    /**
     * Returns true when the JSON string contains at least one {{ placeholder.
     *
     * Use this as a fast pre-check to avoid the regex overhead on schemas
     * that never use template syntax.
     *
     * @param string $schema_json
     * @return bool
     */
    public function has_placeholders( string $schema_json ): bool {
        return false !== strpos( $schema_json, '{{' );
    }

    /**
     * Replace all {{ placeholder }} tokens in a schema JSON string.
     *
     * Safe to call on any JSON — if no placeholders are found the original
     * string is returned without modification.
     *
     * @param string              $schema_json Raw schema JSON string.
     * @param SCM_Request_Context $context     Current request context.
     * @return string JSON with all placeholders resolved.
     */
    public function resolve( string $schema_json, SCM_Request_Context $context ): string {
        if ( ! $this->has_placeholders( $schema_json ) ) {
            return $schema_json;
        }

        $resolver = $this;
        $result   = preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            static function ( array $matches ) use ( $resolver, $context ): string {
                return $resolver->resolve_placeholder( trim( $matches[1] ), $context );
            },
            $schema_json
        );

        // preg_replace_callback returns null only on a regex error — fall back.
        return null !== $result ? $result : $schema_json;
    }

    /**
     * Resolve a single placeholder token to its string value.
     *
     * Called once per placeholder by resolve(). Public so callers can unit-test
     * individual tokens without constructing a full JSON string.
     *
     * @param string              $token   Placeholder content, without {{ and }}.
     * @param SCM_Request_Context $context Current request context.
     * @return string Resolved value, or '' if the token is unrecognised or empty.
     */
    public function resolve_placeholder( string $token, SCM_Request_Context $context ): string {
        // ── meta:FIELD_KEY ────────────────────────────────────────────────────
        if ( 0 === strpos( $token, 'meta:' ) ) {
            $field_key = substr( $token, 5 );
            if ( '' !== $field_key && $context->post_id > 0 ) {
                $value = get_post_meta( $context->post_id, $field_key, true );
                return is_scalar( $value ) ? (string) $value : '';
            }
            return '';
        }

        // ── term:TAXONOMY ─────────────────────────────────────────────────────
        if ( 0 === strpos( $token, 'term:' ) ) {
            $taxonomy = substr( $token, 5 );
            if ( '' !== $taxonomy && $context->post_id > 0 ) {
                $terms = get_the_terms( $context->post_id, $taxonomy );
                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                    return (string) $terms[0]->name;
                }
            }
            return '';
        }

        // ── Named placeholders ────────────────────────────────────────────────
        switch ( $token ) {
            case 'post_title':
                return $context->post_title;
            case 'post_url':
                return $context->post_url;
            case 'post_excerpt':
                return $context->post_excerpt;
            case 'post_id':
                return $context->post_id > 0 ? (string) $context->post_id : '';
            case 'post_type':
                return $context->post_type;
            case 'queried_term_name':
                return $context->queried_term_name;
            case 'queried_term_slug':
                return $context->queried_term_slug;
            case 'queried_taxonomy':
                return $context->queried_taxonomy;
            case 'author_name':
                return $context->author_name;
            case 'author_slug':
                return $context->author_slug;
            case 'archive_post_type':
                return $context->archive_post_type;
            case 'archive_post_type_label':
                return $context->archive_post_type_label;
            case 'site_name':
                return function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
            case 'site_url':
                return function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
            case 'featured_image_url':
                return $context->featured_image_url;
            case 'post_date':
                return $context->post_date;
            case 'post_modified_date':
                return $context->post_modified_date;
        }

        return '';
    }
}
