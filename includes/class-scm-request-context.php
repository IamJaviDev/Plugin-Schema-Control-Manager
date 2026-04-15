<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Snapshot of all WordPress conditional data relevant to rule matching.
 *
 * Built once per request via from_wp(), or injected from a plain array
 * via from_array() for unit tests. All matching logic reads from this
 * object instead of calling WordPress functions directly.
 */
class SCM_Request_Context {

    // ── Boolean flags ─────────────────────────────────────────────────────────

    /** @var bool True when the static front page is displayed. */
    public $is_front_page = false;

    /** @var bool True when the posts/blog-index page is displayed. */
    public $is_home = false;

    /** @var bool True when a single post/page/CPT is displayed. */
    public $is_singular = false;

    /** @var bool True when a CPT archive is displayed. */
    public $is_post_type_archive = false;

    /** @var bool True when a category archive is displayed. */
    public $is_category = false;

    /** @var bool True when a tag archive is displayed. */
    public $is_tag = false;

    /** @var bool True when a custom taxonomy term archive is displayed. */
    public $is_tax = false;

    /** @var bool True when an author archive is displayed. */
    public $is_author = false;

    // ── Scalars ───────────────────────────────────────────────────────────────

    /** @var string Post type of the queried singular post. */
    public $post_type = '';

    /** @var int ID of the queried singular post. */
    public $post_id = 0;

    /** @var string Post type for the current CPT archive. */
    public $archive_post_type = '';

    /** @var string Singular label of the current CPT archive post type (e.g. "Movie"). */
    public $archive_post_type_label = '';

    /** @var string Slug of the current category term. */
    public $category_slug = '';

    /** @var string Slug of the current tag term. */
    public $tag_slug = '';

    /** @var string Taxonomy name for the current custom-taxonomy term page. */
    public $taxonomy = '';

    /** @var string Term slug for the current custom-taxonomy term page. */
    public $term_slug = '';

    /** @var string user_nicename for the current author archive. */
    public $author_nicename = '';

    /** @var string Normalised current URL (no trailing slash, no query string). */
    public $current_url = '';

    /** @var string post_name of the queried object (exact_slug primary check). */
    public $queried_slug = '';

    /** @var string Trimmed $wp->request path (exact_slug fallback). */
    public $request_path = '';

    // ── Template placeholder fields ───────────────────────────────────────────

    /** @var string Title of the queried singular post/page. */
    public $post_title = '';

    /** @var string Permalink of the queried singular post/page. */
    public $post_url = '';

    /** @var string Excerpt of the queried singular post/page (tags stripped). */
    public $post_excerpt = '';

    /** @var string Display name of the current term archive (category, tag, custom taxonomy). */
    public $queried_term_name = '';

    /** @var string Slug of the current term archive. */
    public $queried_term_slug = '';

    /** @var string Taxonomy name of the current term archive. */
    public $queried_taxonomy = '';

    /** @var string Display name of the author on an author archive page. */
    public $author_name = '';

    /** @var string Nicename (slug) of the author on an author archive page. */
    public $author_slug = '';

    /** @var string Full URL of the featured image for the current singular post. */
    public $featured_image_url = '';

    /** @var string Alt text of the featured image for the current singular post. */
    public $featured_image_alt = '';

    /** @var string Published date of the current singular post in W3C/ISO 8601 format. */
    public $post_date = '';

    /** @var string Last-modified date of the current singular post in W3C/ISO 8601 format. */
    public $post_modified_date = '';

    /** @var string Email address of the post author (singular) or queried author (author archive). */
    public $author_email = '';

    /** @var string Archive URL for the current CPT archive page. */
    public $archive_post_type_url = '';

    // ── Static context cache ──────────────────────────────────────────────────

    /**
     * Snapshot captured at 'wp' action time, before any plugin or AIOSEO
     * secondary queries can alter $wp_query. Null until prime() is called.
     *
     * @var self|null
     */
    private static $primed_context = null;

    /**
     * Capture the current WP request state and store it for later use.
     *
     * Must be hooked to the 'wp' action (priority 1) so it runs before
     * AIOSEO or any other plugin fires secondary WP_Query loops that would
     * make is_category() / get_queried_object() reflect the wrong object.
     * Subsequent calls are no-ops.
     */
    public static function prime(): void {
        if ( null === self::$primed_context ) {
            self::$primed_context = self::from_wp();
        }
    }

    /**
     * Return the primed context. Falls back to a fresh from_wp() call when
     * prime() was never called (e.g. CLI or WP-CLI contexts).
     *
     * @return self
     */
    public static function get_cached(): self {
        if ( null !== self::$primed_context ) {
            return self::$primed_context;
        }
        return self::from_wp();
    }

    /**
     * Clear the static cache. For use in unit tests only.
     */
    public static function reset_cache(): void {
        self::$primed_context = null;
    }

    // ── Constructors ──────────────────────────────────────────────────────────

    private function __construct() {}

    /**
     * Build a context from the live WordPress request.
     *
     * Must only be called after the main query has run (e.g. inside wp_head).
     *
     * @return self
     */
    public static function from_wp(): self {
        $ctx = new self();

        $ctx->is_front_page = (bool) is_front_page();
        $ctx->is_home       = (bool) is_home();
        $ctx->is_singular   = (bool) is_singular();

        $queried = get_queried_object();

        if ( $ctx->is_singular && is_object( $queried ) && isset( $queried->post_type ) ) {
            $ctx->post_type   = (string) $queried->post_type;
            $ctx->post_id     = (int) ( $queried->ID ?? 0 );
            $ctx->queried_slug = (string) ( $queried->post_name ?? '' );
        } elseif ( is_object( $queried ) && isset( $queried->post_name ) ) {
            $ctx->queried_slug = (string) $queried->post_name;
        }

        $ctx->request_path = trim( $GLOBALS['wp']->request ?? '', '/' );

        $raw               = home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) );
        $ctx->current_url  = untrailingslashit( strtok( (string) $raw, '?' ) );

        $ctx->is_post_type_archive = (bool) is_post_type_archive();
        if ( $ctx->is_post_type_archive ) {
            $ctx->archive_post_type = (string) get_query_var( 'post_type', '' );
            if ( '' !== $ctx->archive_post_type ) {
                $pto = get_post_type_object( $ctx->archive_post_type );
                $ctx->archive_post_type_label = is_object( $pto ) ? (string) ( $pto->labels->singular_name ?? $pto->label ?? '' ) : '';
                if ( function_exists( 'get_post_type_archive_link' ) ) {
                    $archive_link = get_post_type_archive_link( $ctx->archive_post_type );
                    $ctx->archive_post_type_url = $archive_link ? (string) $archive_link : '';
                }
            }
        }

        $ctx->is_category = (bool) is_category();
        if ( $ctx->is_category && is_object( $queried ) && isset( $queried->slug ) ) {
            $ctx->category_slug = (string) $queried->slug;
            // Populate taxonomy/term_slug so taxonomy_term rules can match category pages.
            $ctx->taxonomy  = 'category';
            $ctx->term_slug = (string) $queried->slug;
        }

        $ctx->is_tag = (bool) is_tag();
        if ( $ctx->is_tag && is_object( $queried ) && isset( $queried->slug ) ) {
            $ctx->tag_slug = (string) $queried->slug;
            // Populate taxonomy/term_slug so taxonomy_term rules can match tag pages.
            $ctx->taxonomy  = 'post_tag';
            $ctx->term_slug = (string) $queried->slug;
        }

        $ctx->is_tax = (bool) is_tax();
        if ( $ctx->is_tax && is_object( $queried ) && isset( $queried->taxonomy, $queried->slug ) ) {
            $ctx->taxonomy  = (string) $queried->taxonomy;
            $ctx->term_slug = (string) $queried->slug;
        }

        $ctx->is_author = (bool) is_author();
        if ( $ctx->is_author && is_object( $queried ) && isset( $queried->user_nicename ) ) {
            $ctx->author_nicename = (string) $queried->user_nicename;
        }

        // ── Template placeholder fields ───────────────────────────────────────

        if ( $ctx->post_id > 0 ) {
            // Title and excerpt — use explicit-ID WP functions so context is correct
            // at 'wp' action priority 1, before setup_postdata() or the global $post
            // are guaranteed to reflect the queried singular post.
            if ( $ctx->is_singular ) {
                $ctx->post_title = function_exists( 'get_the_title' )
                    ? (string) get_the_title( $ctx->post_id )
                    : (string) ( $queried->post_title ?? '' );
                $ctx->post_excerpt = function_exists( 'get_the_excerpt' )
                    ? wp_strip_all_tags( (string) get_the_excerpt( $ctx->post_id ) )
                    : wp_strip_all_tags( $queried->post_excerpt ?? '' );
            }

            $permalink     = get_permalink( $ctx->post_id );
            $ctx->post_url = $permalink ? (string) $permalink : '';

            // Featured image URL and alt text.
            if ( function_exists( 'get_post_thumbnail_id' ) && function_exists( 'wp_get_attachment_image_url' ) ) {
                $thumb_id = get_post_thumbnail_id( $ctx->post_id );
                if ( $thumb_id ) {
                    $img_url = wp_get_attachment_image_url( $thumb_id, 'full' );
                    $ctx->featured_image_url = $img_url ? (string) $img_url : '';
                    $alt = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
                    $ctx->featured_image_alt = is_string( $alt ) ? $alt : '';
                }
            }

            // Post author name and email (singular) — fetched via get_userdata() so
            // values come from the object cache, not global post state.
            if ( $ctx->is_singular && is_object( $queried ) && isset( $queried->post_author ) ) {
                $author_id = (int) $queried->post_author;
                if ( $author_id > 0 && function_exists( 'get_userdata' ) ) {
                    $user_data = get_userdata( $author_id );
                    if ( $user_data ) {
                        $ctx->author_name  = (string) ( $user_data->display_name ?? '' );
                        $ctx->author_email = isset( $user_data->user_email ) ? (string) $user_data->user_email : '';
                    }
                }
            }

            // Published and modified dates.
            if ( function_exists( 'get_post_time' ) ) {
                $date = get_post_time( DATE_W3C, true, $ctx->post_id );
                $ctx->post_date = $date ? (string) $date : '';
            }
            if ( function_exists( 'get_post_modified_time' ) ) {
                $modified = get_post_modified_time( DATE_W3C, true, $ctx->post_id );
                $ctx->post_modified_date = $modified ? (string) $modified : '';
            }
        }

        // Queried term placeholders (category / tag / custom taxonomy archives).
        // All three values are read directly from the queried object so there is
        // no dependency on the intermediate $ctx->term_slug / $ctx->taxonomy chain.
        if ( $ctx->is_category || $ctx->is_tag || $ctx->is_tax ) {
            $ctx->queried_term_name = isset( $queried->name )     ? (string) $queried->name     : '';
            $ctx->queried_term_slug = isset( $queried->slug )     ? (string) $queried->slug     : '';
            $ctx->queried_taxonomy  = isset( $queried->taxonomy ) ? (string) $queried->taxonomy : '';
        }

        // Author placeholders.
        if ( $ctx->is_author && is_object( $queried ) ) {
            $ctx->author_name  = (string) ( $queried->display_name ?? '' );
            $ctx->author_slug  = $ctx->author_nicename;
            $ctx->author_email = isset( $queried->user_email ) ? (string) $queried->user_email : '';
        }

        return $ctx;
    }

    /**
     * Build a context from a plain array — for unit tests only.
     *
     * Keys must match the public property names. Unknown keys are silently
     * ignored so tests only need to specify the fields they care about.
     *
     * @param array $data
     * @return self
     */
    public static function from_array( array $data ): self {
        $ctx = new self();
        foreach ( $data as $key => $value ) {
            if ( property_exists( $ctx, $key ) ) {
                $ctx->$key = $value;
            }
        }
        return $ctx;
    }
}
