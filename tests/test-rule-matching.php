<?php
/**
 * Tests: get_matching_rule_for_request() respects is_active filter.
 *
 * Uses a stub subclass of SCM_Rules that overrides get_all() and
 * matches_current_request() so no database or WordPress query functions
 * are required.
 */

use PHPUnit\Framework\TestCase;

// ── Stub ──────────────────────────────────────────────────────────────────────

/**
 * Subclass of SCM_Rules with injectable rows and a predictable matcher.
 * get_all() honours the is_active filter exactly as the production query does.
 * matches_current_request() always returns true so every active rule matches.
 */
class Stub_SCM_Rules_Matching extends SCM_Rules {
    /** @var array[] */
    private $rows = array();

    public function set_rows( array $rows ): void {
        $this->rows = $rows;
    }

    public function get_all( $args = array() ): array {
        $rows = $this->rows;

        if ( isset( $args['is_active'] ) && '' !== $args['is_active'] ) {
            $active = (int) $args['is_active'];
            $rows   = array_values(
                array_filter( $rows, static function ( $r ) use ( $active ) {
                    return (int) $r['is_active'] === $active;
                } )
            );
        }

        return $rows;
    }

    public function matches_current_request( $rule ): bool {
        return true; // every rule matches in isolation tests
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

class Test_Rule_Matching extends TestCase {

    private function make_stub(): Stub_SCM_Rules_Matching {
        return new Stub_SCM_Rules_Matching( new SCM_DB() );
    }

    private function make_row( int $id, int $priority, int $is_active ): array {
        return array(
            'id'             => $id,
            'label'          => 'Rule ' . $id,
            'target_type'    => 'exact_slug',
            'target_value'   => 'test',
            'mode'           => 'aioseo_plus_custom',
            'replaced_types' => '[]',
            'priority'       => $priority,
            'is_active'      => $is_active,
            'updated_at'     => '2024-01-01 00:00:00',
        );
    }

    private function make_custom_only_row( int $id, int $priority ): array {
        return array(
            'id'             => $id,
            'label'          => 'Rule ' . $id,
            'target_type'    => 'exact_slug',
            'target_value'   => 'same-slug',
            'mode'           => 'custom_only',
            'replaced_types' => '[]',
            'priority'       => $priority,
            'is_active'      => 1,
            'updated_at'     => '2024-01-01 00:00:00',
        );
    }

    /**
     * An inactive rule with higher priority must NOT win over a lower-priority
     * active rule. get_matching_rule_for_request() filters is_active=1 first,
     * then sorts; the inactive rule is never considered.
     */
    public function test_inactive_rule_ignored(): void {
        $stub = $this->make_stub();
        $stub->set_rows( array(
            $this->make_row( 1, 200, 0 ), // high priority but inactive
            $this->make_row( 2, 50,  1 ), // lower priority but active
        ) );

        $matched = $stub->get_matching_rule_for_request();

        $this->assertNotNull( $matched, 'Expected an active rule to be matched.' );
        $this->assertSame( 2, (int) $matched['id'], 'Inactive high-priority rule must not win.' );
    }

    /**
     * When all rules are inactive, no match is returned.
     */
    public function test_all_inactive_returns_null(): void {
        $stub = $this->make_stub();
        $stub->set_rows( array(
            $this->make_row( 1, 100, 0 ),
            $this->make_row( 2, 200, 0 ),
        ) );

        $this->assertNull( $stub->get_matching_rule_for_request() );
    }

    /**
     * Among multiple active rules the highest-priority one wins.
     */
    public function test_highest_priority_active_rule_wins(): void {
        $stub = $this->make_stub();
        $stub->set_rows( array(
            $this->make_row( 1, 50,  1 ),
            $this->make_row( 2, 200, 1 ),
            $this->make_row( 3, 100, 1 ),
        ) );

        $matched = $stub->get_matching_rule_for_request();

        $this->assertSame( 2, (int) $matched['id'] );
    }

    // ── Bug regression: double-render of custom_only schemas ─────────────────

    /**
     * Two active custom_only rules matching the same request: the higher-priority
     * rule's id is returned. Both the injector and the AIOSEO filter receive this
     * id and call get_custom_nodes_for_rule(winner_id), so rule B's schemas are
     * never fetched or rendered.
     */
    public function test_two_custom_only_rules_winner_is_highest_priority(): void {
        $stub = $this->make_stub();
        $stub->set_rows( array(
            $this->make_custom_only_row( 1, 200 ), // winner
            $this->make_custom_only_row( 2, 100 ), // loser
        ) );

        $matched = $stub->get_matching_rule_for_request();

        $this->assertNotNull( $matched );
        $this->assertSame( 1,   (int) $matched['id'],       'Winner must be the higher-priority rule.' );
        $this->assertSame( 200, (int) $matched['priority'], 'Winner priority must be 200.' );
        $this->assertSame( 'custom_only', $matched['mode'] );
    }

    /**
     * The losing rule must never be the matched result.
     * Confirms that get_custom_nodes_for_rule would never be called with rule B's id.
     */
    public function test_lower_priority_custom_only_rule_never_matched(): void {
        $stub = $this->make_stub();
        $stub->set_rows( array(
            $this->make_custom_only_row( 10, 200 ), // winner
            $this->make_custom_only_row( 20, 100 ), // loser
        ) );

        $matched = $stub->get_matching_rule_for_request();

        $this->assertNotNull( $matched );
        $this->assertNotSame( 20, (int) $matched['id'], 'Lower-priority rule id must not be returned.' );
    }
}
