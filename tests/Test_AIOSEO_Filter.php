<?php
/**
 * Tests: SCM_AIOSEO::filter_schema_output() for custom_only mode.
 *
 * Verifies the fix for the double-render bug: filter_schema_output() must
 * return an empty array for custom_only and must not call
 * get_custom_nodes_for_rule() — output for that mode belongs exclusively
 * to SCM_Injector via wp_head.
 */

use PHPUnit\Framework\TestCase;

// ── Minimal SCM_Graph_Manager stub ───────────────────────────────────────────
// The real class is not loaded in bootstrap. The stub satisfies the type hint
// in SCM_AIOSEO::__construct() and lets the spy subclass track calls.

if ( ! class_exists( 'SCM_Graph_Manager' ) ) {
    class SCM_Graph_Manager {
        public function get_custom_nodes_for_rule( $rule_id, $rule = null ): array { return array(); }
        public function merge_graphs( $graphs, $custom_nodes, $rule ): array { return array(); }
        public function get_last_merge_notices(): array { return array( 'errors' => array(), 'warnings' => array() ); }
    }
}

require_once SCM_PLUGIN_DIR . 'includes/class-scm-aioseo.php';

// ── Spy: records whether get_custom_nodes_for_rule() was invoked ─────────────

class Spy_SCM_Graph_Manager extends SCM_Graph_Manager {
    public bool $nodes_called = false;

    public function get_custom_nodes_for_rule( $rule_id, $rule = null ): array {
        $this->nodes_called = true;
        return array( array( '@type' => 'FAQPage' ) ); // non-empty so a bug would be detectable
    }
}

// ── Stub: returns a preset rule from get_matching_rule_for_request() ─────────

class Stub_SCM_Rules_AIOSEO_Filter extends SCM_Rules {
    private array $rule;

    public function __construct( array $rule ) {
        $this->rule = $rule; // skip parent — $db not needed
    }

    public function get_matching_rule_for_request(): ?array {
        return $this->rule;
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

class Test_AIOSEO_Filter extends TestCase {

    private function make_rule( string $mode ): array {
        return array(
            'id'             => 1,
            'label'          => 'Test rule',
            'mode'           => $mode,
            'target_type'    => 'exact_slug',
            'target_value'   => 'some-slug',
            'replaced_types' => '[]',
            'priority'       => 100,
            'is_active'      => 1,
            'updated_at'     => '2024-01-01 00:00:00',
        );
    }

    /**
     * Core regression test for the double-render bug.
     *
     * When the matching rule is custom_only, filter_schema_output() must:
     *  1. return [] — no schemas are injected via the AIOSEO filter path
     *  2. never call get_custom_nodes_for_rule() — DB is not hit for this mode
     *
     * SCM_Injector::output_schemas() is the sole output owner for custom_only.
     */
    public function test_filter_returns_empty_and_skips_nodes_for_custom_only(): void {
        $spy   = new Spy_SCM_Graph_Manager();
        $rules = new Stub_SCM_Rules_AIOSEO_Filter( $this->make_rule( 'custom_only' ) );

        $aioseo = new SCM_AIOSEO( $rules, $spy );
        $result = $aioseo->filter_schema_output( array( array( '@type' => 'WebPage' ) ) );

        $this->assertSame( array(), $result,
            'filter_schema_output() must return [] for custom_only — SCM_Injector owns this output.' );

        $this->assertFalse( $spy->nodes_called,
            'get_custom_nodes_for_rule() must not be called for custom_only.' );
    }

    /**
     * Sanity check: aioseo_plus_custom still calls get_custom_nodes_for_rule().
     * Ensures the early return for custom_only does not bleed into other modes.
     */
    public function test_filter_calls_nodes_for_aioseo_plus_custom(): void {
        $spy   = new Spy_SCM_Graph_Manager();
        $rules = new Stub_SCM_Rules_AIOSEO_Filter( $this->make_rule( 'aioseo_plus_custom' ) );

        $aioseo = new SCM_AIOSEO( $rules, $spy );
        $aioseo->filter_schema_output( array() );

        $this->assertTrue( $spy->nodes_called,
            'get_custom_nodes_for_rule() must still be called for aioseo_plus_custom.' );
    }
}
