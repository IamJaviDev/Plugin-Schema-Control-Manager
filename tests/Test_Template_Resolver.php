<?php
/**
 * Tests: SCM_Template_Resolver
 *
 * Verifies that placeholders are resolved correctly for all supported token
 * types and that edge cases (missing values, no placeholders) are handled
 * without errors.
 */

use PHPUnit\Framework\TestCase;

class Test_Template_Resolver extends TestCase {

    private SCM_Template_Resolver $resolver;

    protected function setUp(): void {
        $this->resolver = new SCM_Template_Resolver();

        // Reset test stubs between tests.
        $GLOBALS['scm_test_post_meta'] = array();
        $GLOBALS['scm_test_terms']     = array();
    }

    // ── 1. Basic placeholder replacement ──────────────────────────────────────

    public function test_basic_placeholder_replacement(): void {
        $context = SCM_Request_Context::from_array( array(
            'post_id'    => 7,
            'post_title' => 'Desguace en Madrid',
            'post_url'   => 'https://example.com/desguace-madrid',
            'post_type'  => 'auto',
        ) );

        $json = '{"name":"{{post_title}}","url":"{{post_url}}","@type":"{{post_type}}","id":{{post_id}}}';

        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame(
            '{"name":"Desguace en Madrid","url":"https://example.com/desguace-madrid","@type":"auto","id":7}',
            $result
        );
    }

    // ── 2. Meta placeholder replacement ──────────────────────────────────────

    public function test_meta_placeholder_replacement(): void {
        $GLOBALS['scm_test_post_meta'][42]['telefono']  = '555-1234';
        $GLOBALS['scm_test_post_meta'][42]['direccion'] = 'Calle Mayor 1';

        $context = SCM_Request_Context::from_array( array( 'post_id' => 42 ) );
        $json    = '{"telephone":"{{meta:telefono}}","address":"{{meta:direccion}}"}';

        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame(
            '{"telephone":"555-1234","address":"Calle Mayor 1"}',
            $result
        );
    }

    // ── 3. Taxonomy term placeholder replacement ──────────────────────────────

    public function test_term_placeholder_replacement(): void {
        $term              = new stdClass();
        $term->name        = 'Sedanes';
        $term->slug        = 'sedanes';
        $GLOBALS['scm_test_terms'][10]['tipo_vehiculo'] = array( $term );

        $context = SCM_Request_Context::from_array( array( 'post_id' => 10 ) );
        $json    = '{"category":"{{term:tipo_vehiculo}}"}';

        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame( '{"category":"Sedanes"}', $result );
    }

    // ── 4. Unresolved placeholder becomes empty string ────────────────────────

    public function test_unresolved_placeholder_becomes_empty_string(): void {
        $context = SCM_Request_Context::from_array( array() ); // nothing set

        $json   = '{"name":"{{post_title}}","unknown":"{{totally_unknown_token}}"}';
        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame( '{"name":"","unknown":""}', $result );
    }

    // ── 5. Schema without placeholders is returned unchanged ─────────────────

    public function test_schema_without_placeholders_unchanged(): void {
        $context = SCM_Request_Context::from_array( array( 'post_title' => 'Ignored' ) );
        $json    = '{"@type":"LocalBusiness","name":"Static Name"}';

        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame( $json, $result );
    }

    // ── 6. Queried term placeholder replacement ───────────────────────────────

    public function test_queried_term_placeholder_replacement(): void {
        $context = SCM_Request_Context::from_array( array(
            'queried_term_name' => 'Berlinas',
            'queried_term_slug' => 'berlinas',
            'queried_taxonomy'  => 'tipo_vehiculo',
        ) );

        $json = '{"name":"{{queried_term_name}}","slug":"{{queried_term_slug}}","taxonomy":"{{queried_taxonomy}}"}';

        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame(
            '{"name":"Berlinas","slug":"berlinas","taxonomy":"tipo_vehiculo"}',
            $result
        );
    }

    // ── 7. Author placeholder replacement ─────────────────────────────────────

    public function test_author_placeholder_replacement(): void {
        $context = SCM_Request_Context::from_array( array(
            'author_name' => 'Don Javier',
            'author_slug' => 'don-javier',
        ) );

        $json = '{"author":"{{author_name}}","authorUrl":"/author/{{author_slug}}"}';

        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame(
            '{"author":"Don Javier","authorUrl":"/author/don-javier"}',
            $result
        );
    }

    // ── Extra: has_placeholders() fast check ─────────────────────────────────

    public function test_has_placeholders_returns_false_for_plain_json(): void {
        $this->assertFalse( $this->resolver->has_placeholders( '{"@type":"Thing"}' ) );
    }

    public function test_has_placeholders_returns_true_when_present(): void {
        $this->assertTrue( $this->resolver->has_placeholders( '{"name":"{{post_title}}"}' ) );
    }

    // ── Extra: meta placeholder with no post_id returns empty string ──────────

    public function test_meta_placeholder_without_post_id_returns_empty(): void {
        $context = SCM_Request_Context::from_array( array() ); // post_id = 0
        $result  = $this->resolver->resolve_placeholder( 'meta:telefono', $context );

        $this->assertSame( '', $result );
    }

    // ── Extra: term placeholder with no matching terms returns empty string ───

    public function test_term_placeholder_with_no_terms_returns_empty(): void {
        // No terms registered in the stub.
        $context = SCM_Request_Context::from_array( array( 'post_id' => 99 ) );
        $result  = $this->resolver->resolve_placeholder( 'term:category', $context );

        $this->assertSame( '', $result );
    }

    // ── New: site_name resolves via get_bloginfo() ────────────────────────────

    public function test_site_name_resolves_correctly(): void {
        $GLOBALS['scm_test_wp_query']['bloginfo']['name'] = 'Mi Sitio Web';

        $context = SCM_Request_Context::from_array( array() );
        $json    = '{"name":"{{site_name}}"}';
        $result  = $this->resolver->resolve( $json, $context );

        $this->assertSame( '{"name":"Mi Sitio Web"}', $result );
    }

    // ── New: site_url resolves via home_url('/') ──────────────────────────────

    public function test_site_url_resolves_correctly(): void {
        // home_url() stub always returns 'https://example.com' + $path.
        $context = SCM_Request_Context::from_array( array() );
        $result  = $this->resolver->resolve_placeholder( 'site_url', $context );

        $this->assertSame( 'https://example.com/', $result );
    }

    // ── New: archive_post_type resolves on CPT archive context ───────────────

    public function test_archive_post_type_resolves_on_archive_context(): void {
        $context = SCM_Request_Context::from_array( array( 'archive_post_type' => 'movie' ) );
        $json    = '{"type":"{{archive_post_type}}"}';
        $result  = $this->resolver->resolve( $json, $context );

        $this->assertSame( '{"type":"movie"}', $result );
    }

    // ── New: archive_post_type_label resolves on CPT archive context ──────────

    public function test_archive_post_type_label_resolves_on_archive_context(): void {
        $context = SCM_Request_Context::from_array( array( 'archive_post_type_label' => 'Movie' ) );
        $json    = '{"label":"{{archive_post_type_label}}"}';
        $result  = $this->resolver->resolve( $json, $context );

        $this->assertSame( '{"label":"Movie"}', $result );
    }

    // ── New: archive placeholders return '' outside archive context ───────────

    public function test_archive_placeholders_return_empty_outside_archive(): void {
        // No archive_post_type or archive_post_type_label set.
        $context = SCM_Request_Context::from_array( array( 'is_singular' => true, 'post_id' => 5 ) );

        $this->assertSame( '', $this->resolver->resolve_placeholder( 'archive_post_type', $context ) );
        $this->assertSame( '', $this->resolver->resolve_placeholder( 'archive_post_type_label', $context ) );
    }

    // ── New: featured_image_url resolves when image exists ───────────────────

    public function test_featured_image_url_resolves_when_image_exists(): void {
        $context = SCM_Request_Context::from_array( array(
            'featured_image_url' => 'https://example.com/wp-content/uploads/photo.jpg',
        ) );

        $json   = '{"image":"{{featured_image_url}}"}';
        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame( '{"image":"https://example.com/wp-content/uploads/photo.jpg"}', $result );
    }

    // ── New: featured_image_url returns '' when no image ─────────────────────

    public function test_featured_image_url_returns_empty_when_no_image(): void {
        $context = SCM_Request_Context::from_array( array( 'post_id' => 5 ) ); // featured_image_url not set

        $result = $this->resolver->resolve_placeholder( 'featured_image_url', $context );

        $this->assertSame( '', $result );
    }

    // ── New: post_date resolves correctly ─────────────────────────────────────

    public function test_post_date_resolves_correctly(): void {
        $context = SCM_Request_Context::from_array( array(
            'post_date' => '2024-06-15T10:30:00+00:00',
        ) );

        $json   = '{"datePublished":"{{post_date}}"}';
        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame( '{"datePublished":"2024-06-15T10:30:00+00:00"}', $result );
    }

    // ── New: post_modified_date resolves correctly ────────────────────────────

    public function test_post_modified_date_resolves_correctly(): void {
        $context = SCM_Request_Context::from_array( array(
            'post_modified_date' => '2025-01-20T08:00:00+00:00',
        ) );

        $json   = '{"dateModified":"{{post_modified_date}}"}';
        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame( '{"dateModified":"2025-01-20T08:00:00+00:00"}', $result );
    }

    // ── New: all three return '' outside singular context ─────────────────────

    public function test_date_and_image_placeholders_return_empty_outside_singular(): void {
        // Simulates a taxonomy archive: no post_id, no singular data.
        $context = SCM_Request_Context::from_array( array(
            'queried_term_name' => 'Sedanes',
            'queried_term_slug' => 'sedanes',
        ) );

        $this->assertSame( '', $this->resolver->resolve_placeholder( 'featured_image_url', $context ) );
        $this->assertSame( '', $this->resolver->resolve_placeholder( 'post_date', $context ) );
        $this->assertSame( '', $this->resolver->resolve_placeholder( 'post_modified_date', $context ) );
    }

    // ── New: author_email resolves on singular context ────────────────────────

    public function test_author_email_resolves_on_singular_context(): void {
        $context = SCM_Request_Context::from_array( array(
            'author_email' => 'autor@example.com',
        ) );

        $json   = '{"email":"{{author_email}}"}';
        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame( '{"email":"autor@example.com"}', $result );
    }

    // ── New: author_email resolves on author archive context ──────────────────

    public function test_author_email_resolves_on_author_archive_context(): void {
        $context = SCM_Request_Context::from_array( array(
            'is_author'    => true,
            'author_name'  => 'Don Javier',
            'author_slug'  => 'don-javier',
            'author_email' => 'javier@example.com',
        ) );

        $result = $this->resolver->resolve_placeholder( 'author_email', $context );

        $this->assertSame( 'javier@example.com', $result );
    }

    // ── New: archive_post_type_url resolves on archive context ────────────────

    public function test_archive_post_type_url_resolves_on_archive_context(): void {
        $context = SCM_Request_Context::from_array( array(
            'archive_post_type_url' => 'https://example.com/movies/',
        ) );

        $json   = '{"url":"{{archive_post_type_url}}"}';
        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame( '{"url":"https://example.com/movies/"}', $result );
    }

    // ── New: featured_image_alt resolves when alt text exists ─────────────────

    public function test_featured_image_alt_resolves_when_alt_exists(): void {
        $context = SCM_Request_Context::from_array( array(
            'featured_image_alt' => 'Un coche rojo en Madrid',
        ) );

        $json   = '{"description":"{{featured_image_alt}}"}';
        $result = $this->resolver->resolve( $json, $context );

        $this->assertSame( '{"description":"Un coche rojo en Madrid"}', $result );
    }

    // ── New: all three new placeholders return '' when unavailable ────────────

    public function test_new_placeholders_return_empty_when_unavailable(): void {
        $context = SCM_Request_Context::from_array( array() ); // nothing set

        $this->assertSame( '', $this->resolver->resolve_placeholder( 'author_email', $context ) );
        $this->assertSame( '', $this->resolver->resolve_placeholder( 'archive_post_type_url', $context ) );
        $this->assertSame( '', $this->resolver->resolve_placeholder( 'featured_image_alt', $context ) );
    }
}
