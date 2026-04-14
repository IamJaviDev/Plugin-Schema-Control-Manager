<?php
/**
 * Tests: SCM_Request_Context::from_wp() — queried term placeholder fields
 *
 * Verifies that queried_term_name, queried_term_slug, and queried_taxonomy are
 * populated correctly for category, tag, and custom taxonomy archive pages, and
 * that they remain empty on non-archive pages.
 *
 * WordPress conditional functions are stubbed via $GLOBALS['scm_test_wp_query']
 * (registered in bootstrap.php).
 */

use PHPUnit\Framework\TestCase;

class Test_Request_Context extends TestCase {

    protected function setUp(): void {
        // Reset all WP query state stubs between tests.
        $GLOBALS['scm_test_wp_query'] = array();
        $GLOBALS['wp']                = (object) array( 'request' => '' );
        // Clear the static primed context so each test gets a fresh from_wp() call.
        SCM_Request_Context::reset_cache();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Build a minimal WP_Term-like stdClass for a taxonomy archive.
     */
    private function make_term( string $name, string $slug, string $taxonomy ): stdClass {
        $term           = new stdClass();
        $term->name     = $name;
        $term->slug     = $slug;
        $term->taxonomy = $taxonomy;
        return $term;
    }

    // ── 1. Category archive — queried_term_name ───────────────────────────────

    public function test_category_archive_sets_queried_term_name(): void {
        $GLOBALS['scm_test_wp_query'] = array(
            'is_category'    => true,
            'queried_object' => $this->make_term( 'Noticias', 'noticias', 'category' ),
        );

        $ctx = SCM_Request_Context::from_wp();

        $this->assertSame( 'Noticias', $ctx->queried_term_name );
    }

    // ── 2. Category archive — queried_term_slug ───────────────────────────────

    public function test_category_archive_sets_queried_term_slug(): void {
        $GLOBALS['scm_test_wp_query'] = array(
            'is_category'    => true,
            'queried_object' => $this->make_term( 'Noticias', 'noticias', 'category' ),
        );

        $ctx = SCM_Request_Context::from_wp();

        $this->assertSame( 'noticias', $ctx->queried_term_slug );
        $this->assertSame( 'category', $ctx->queried_taxonomy );
    }

    // ── 3. Tag archive — all three queried term fields ────────────────────────

    public function test_tag_archive_sets_all_queried_term_fields(): void {
        $GLOBALS['scm_test_wp_query'] = array(
            'is_tag'         => true,
            'queried_object' => $this->make_term( 'SEO', 'seo', 'post_tag' ),
        );

        $ctx = SCM_Request_Context::from_wp();

        $this->assertSame( 'SEO',      $ctx->queried_term_name );
        $this->assertSame( 'seo',      $ctx->queried_term_slug );
        $this->assertSame( 'post_tag', $ctx->queried_taxonomy );
    }

    // ── 4. Custom taxonomy archive — all three queried term fields ────────────

    public function test_custom_taxonomy_archive_sets_queried_term_fields(): void {
        $GLOBALS['scm_test_wp_query'] = array(
            'is_tax'         => true,
            'queried_object' => $this->make_term( 'Acción', 'accion', 'genero' ),
        );

        $ctx = SCM_Request_Context::from_wp();

        $this->assertSame( 'Acción', $ctx->queried_term_name );
        $this->assertSame( 'accion', $ctx->queried_term_slug );
        $this->assertSame( 'genero', $ctx->queried_taxonomy );
    }

    // ── 5. Non-archive singular page — queried term fields stay empty ─────────

    public function test_singular_page_keeps_queried_term_fields_empty(): void {
        $post              = new stdClass();
        $post->post_type   = 'post';
        $post->ID          = 42;
        $post->post_name   = 'hello-world';
        $post->post_title  = 'Hello World';
        $post->post_excerpt = '';

        $GLOBALS['scm_test_wp_query'] = array(
            'is_singular'    => true,
            'queried_object' => $post,
        );

        $ctx = SCM_Request_Context::from_wp();

        $this->assertSame( '', $ctx->queried_term_name );
        $this->assertSame( '', $ctx->queried_term_slug );
        $this->assertSame( '', $ctx->queried_taxonomy );
    }

    // ── 6. Front page — queried term fields stay empty ────────────────────────

    public function test_front_page_keeps_queried_term_fields_empty(): void {
        $GLOBALS['scm_test_wp_query'] = array(
            'is_front_page' => true,
        );

        $ctx = SCM_Request_Context::from_wp();

        $this->assertSame( '', $ctx->queried_term_name );
        $this->assertSame( '', $ctx->queried_term_slug );
        $this->assertSame( '', $ctx->queried_taxonomy );
    }

    // ── 7. prime() captures the context at call time ──────────────────────────
    //
    // Simulates the runtime scenario: prime() is called early (at 'wp' action)
    // when is_category() is true. Later, a secondary query changes the stubs so
    // is_category() would return false. get_cached() must still return the
    // originally-primed values, not the corrupted later state.

    public function test_get_cached_returns_primed_context_not_later_state(): void {
        // State at 'wp' action time — category archive.
        $GLOBALS['scm_test_wp_query'] = array(
            'is_category'    => true,
            'queried_object' => $this->make_term( 'Noticias', 'noticias', 'category' ),
        );

        SCM_Request_Context::prime();

        // Simulate a secondary WP_Query loop corrupting global state.
        $GLOBALS['scm_test_wp_query'] = array(
            'is_category' => false, // is_category() now returns false
        );

        $ctx = SCM_Request_Context::get_cached();

        // Must still reflect the originally-primed archive context.
        $this->assertSame( 'Noticias',  $ctx->queried_term_name );
        $this->assertSame( 'noticias',  $ctx->queried_term_slug );
        $this->assertSame( 'category',  $ctx->queried_taxonomy );
    }

    // ── 8. get_cached() falls back to from_wp() when prime() was never called ─

    public function test_get_cached_falls_back_to_from_wp_when_not_primed(): void {
        $GLOBALS['scm_test_wp_query'] = array(
            'is_tag'         => true,
            'queried_object' => $this->make_term( 'SEO', 'seo', 'post_tag' ),
        );

        // prime() is NOT called — get_cached() must build fresh.
        $ctx = SCM_Request_Context::get_cached();

        $this->assertSame( 'SEO',      $ctx->queried_term_name );
        $this->assertSame( 'seo',      $ctx->queried_term_slug );
        $this->assertSame( 'post_tag', $ctx->queried_taxonomy );
    }
}
