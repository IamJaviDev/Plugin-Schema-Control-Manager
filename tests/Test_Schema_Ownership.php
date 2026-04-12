<?php
/**
 * Tests: SCM_Admin::validate_schema_ownership()
 *
 * Covers the Sprint-2 safety rules:
 *  1. Saving a schema with a nonexistent rule_id fails.
 *  2. Saving a schema with a missing (zero) rule_id fails.
 *  3. Editing a schema that does not exist fails.
 *  4. Editing a schema under the wrong rule_id fails.
 *  5. A correctly-owned schema save succeeds (returns null).
 *  6. An empty schema_json payload fails regardless of valid ownership.
 *
 * Uses a minimal subclass that bypasses WordPress hook registration and only
 * sets the two properties the method under test depends on.
 */

use PHPUnit\Framework\TestCase;

// ── Stubs ─────────────────────────────────────────────────────────────────────

/**
 * Overrides SCM_Rules::get() with an in-memory map.
 * All other methods are inherited from the real class (unused in these tests).
 */
class Stub_SCM_Rules_Ownership extends SCM_Rules {
    /** @var array<int, array> */
    private array $map = array();

    public function set_rules( array $map ): void {
        $this->map = $map;
    }

    public function get( $id ): ?array {
        return $this->map[ (int) $id ] ?? null;
    }
}

/**
 * Overrides SCM_Schemas::get() with an in-memory map.
 */
class Stub_SCM_Schemas_Ownership extends SCM_Schemas {
    /** @var array<int, array> */
    private array $map = array();

    public function set_schemas( array $map ): void {
        $this->map = $map;
    }

    public function get( $id ): ?array {
        return $this->map[ (int) $id ] ?? null;
    }
}

// ── Stubs for SCM_Admin dependencies ─────────────────────────────────────────
// These are only needed so PHP can parse class-scm-admin.php cleanly.
// SCM_Admin_OwnershipHarness bypasses the parent constructor, so these
// classes are never actually instantiated in these tests.

if ( ! class_exists( 'SCM_Graph_Manager' ) ) {
    class SCM_Graph_Manager {
        public function get_custom_nodes_for_rule( $rule_id, $rule = null ): array { return array(); }
        public function merge_graphs( $aioseo, $custom, $rule ): array { return array(); }
        public function get_last_merge_notices(): array { return array( 'errors' => array(), 'warnings' => array() ); }
        public function get_diagnostics_for_json( $json, $rule = null ): array {
            return array( 'errors' => array(), 'structural_warnings' => array(), 'semantic_warnings' => array(), 'warnings' => array(), 'node_count' => 0, 'types' => array(), 'domains' => array(), 'normalized' => null );
        }
        public function get_diagnostics_for_rule( $id, $rule = null ): array { return $this->get_diagnostics_for_json( '', null ); }
        public function get_preview_payload_for_rule( $id, $rule = null ): array {
            return array( 'status' => 'valid', 'counts' => array(), 'errors' => array(), 'structural_warnings' => array(), 'semantic_warnings' => array(), 'changes' => array(), 'final_graph' => array() );
        }
    }
}

if ( ! class_exists( 'SCM_Input_Normalizer' ) ) {
    class SCM_Input_Normalizer {}
}

require_once SCM_PLUGIN_DIR . 'includes/class-scm-admin.php';

// ── Test harness ──────────────────────────────────────────────────────────────

/**
 * Minimal SCM_Admin subclass that sets only the two properties used by
 * validate_schema_ownership() and skips the full WordPress-hooked constructor.
 */
class SCM_Admin_OwnershipHarness extends SCM_Admin {
    public function __construct( SCM_Rules $rules, SCM_Schemas $schemas ) {
        // Intentionally do NOT call parent::__construct() — this avoids
        // registering WordPress hooks (add_action, add_menu_page, etc.)
        // which are not available or relevant in the unit-test context.
        $this->rules   = $rules;
        $this->schemas = $schemas;
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

class Test_Schema_Ownership extends TestCase {

    // ── Fixture helpers ───────────────────────────────────────────────────────

    private function make_rules_stub( array $map = array() ): Stub_SCM_Rules_Ownership {
        $stub = new Stub_SCM_Rules_Ownership( new SCM_DB() );
        $stub->set_rules( $map );
        return $stub;
    }

    private function make_schemas_stub( array $map = array() ): Stub_SCM_Schemas_Ownership {
        $stub = new Stub_SCM_Schemas_Ownership( new SCM_DB(), new SCM_Validator() );
        $stub->set_schemas( $map );
        return $stub;
    }

    private function make_admin(
        Stub_SCM_Rules_Ownership   $rules,
        Stub_SCM_Schemas_Ownership $schemas
    ): SCM_Admin_OwnershipHarness {
        return new SCM_Admin_OwnershipHarness( $rules, $schemas );
    }

    private function make_rule( int $id ): array {
        return array(
            'id'             => $id,
            'label'          => 'Rule ' . $id,
            'target_type'    => 'exact_slug',
            'target_value'   => 'test',
            'mode'           => 'custom_only',
            'replaced_types' => '[]',
            'priority'       => 100,
            'is_active'      => 1,
        );
    }

    private function make_schema( int $id, int $rule_id ): array {
        return array(
            'id'          => $id,
            'rule_id'     => $rule_id,
            'label'       => 'Schema ' . $id,
            'schema_type' => 'Thing',
            'schema_json' => '{"@type":"Thing"}',
            'priority'    => 10,
            'is_active'   => 1,
        );
    }

    // ── Test 1: missing rule_id (zero) is rejected ────────────────────────────

    public function test_missing_rule_id_returns_error(): void {
        $admin = $this->make_admin(
            $this->make_rules_stub(),
            $this->make_schemas_stub()
        );

        $result = $admin->validate_schema_ownership( 0, 0, null, null, '{"@type":"Thing"}' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'missing_rule_id', $result->get_error_code() );
    }

    // ── Test 2: nonexistent rule_id is rejected ───────────────────────────────

    public function test_nonexistent_rule_id_returns_error(): void {
        $admin = $this->make_admin(
            $this->make_rules_stub( array() ), // no rules in DB
            $this->make_schemas_stub()
        );

        $result = $admin->validate_schema_ownership( 999, 0, null, null, '{"@type":"Thing"}' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'rule_not_found', $result->get_error_code() );
        $this->assertStringContainsString( '999', $result->get_error_message() );
    }

    // ── Test 3: editing a nonexistent schema_id is rejected ───────────────────

    public function test_nonexistent_schema_id_returns_error(): void {
        $rule  = $this->make_rule( 5 );
        $admin = $this->make_admin(
            $this->make_rules_stub( array( 5 => $rule ) ),
            $this->make_schemas_stub( array() ) // schema 42 does not exist
        );

        $result = $admin->validate_schema_ownership( 5, 42, $rule, null, '{"@type":"Thing"}' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'schema_not_found', $result->get_error_code() );
        $this->assertStringContainsString( '42', $result->get_error_message() );
    }

    // ── Test 4: schema belonging to a different rule is rejected ─────────────

    public function test_schema_rule_mismatch_returns_error(): void {
        $rule_a  = $this->make_rule( 7 );
        $schema  = $this->make_schema( 10, 3 ); // belongs to rule 3, NOT rule 7
        $admin   = $this->make_admin(
            $this->make_rules_stub( array( 7 => $rule_a ) ),
            $this->make_schemas_stub( array( 10 => $schema ) )
        );

        $result = $admin->validate_schema_ownership( 7, 10, $rule_a, $schema, '{"@type":"Thing"}' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'schema_rule_mismatch', $result->get_error_code() );
        // Error message must name both rule IDs so the admin can diagnose the situation.
        $this->assertStringContainsString( '10', $result->get_error_message() ); // schema id
        $this->assertStringContainsString( '3',  $result->get_error_message() ); // actual owner
        $this->assertStringContainsString( '7',  $result->get_error_message() ); // requested rule
    }

    // ── Test 5: correct ownership passes (returns null) ───────────────────────

    public function test_correct_ownership_returns_null(): void {
        $rule   = $this->make_rule( 7 );
        $schema = $this->make_schema( 10, 7 ); // correctly belongs to rule 7
        $admin  = $this->make_admin(
            $this->make_rules_stub( array( 7 => $rule ) ),
            $this->make_schemas_stub( array( 10 => $schema ) )
        );

        $result = $admin->validate_schema_ownership( 7, 10, $rule, $schema, '{"@type":"Thing"}' );

        $this->assertNull( $result, 'Valid ownership must return null (no error).' );
    }

    // ── Test 6: new schema (schema_id = 0) under valid rule passes ────────────

    public function test_new_schema_under_valid_rule_returns_null(): void {
        $rule  = $this->make_rule( 2 );
        $admin = $this->make_admin(
            $this->make_rules_stub( array( 2 => $rule ) ),
            $this->make_schemas_stub()
        );

        $result = $admin->validate_schema_ownership( 2, 0, $rule, null, '{"@type":"FAQPage"}' );

        $this->assertNull( $result, 'New schema under valid rule must pass validation.' );
    }

    // ── Test 7: empty schema_json is rejected ─────────────────────────────────

    public function test_empty_schema_json_returns_error(): void {
        $rule  = $this->make_rule( 1 );
        $admin = $this->make_admin(
            $this->make_rules_stub( array( 1 => $rule ) ),
            $this->make_schemas_stub()
        );

        $result = $admin->validate_schema_ownership( 1, 0, $rule, null, '   ' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'empty_schema_json', $result->get_error_code() );
    }
}
