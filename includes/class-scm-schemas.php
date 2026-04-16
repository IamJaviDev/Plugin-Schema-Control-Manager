<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCM_Schemas {
    private $db;
    private $validator;

    public function __construct( SCM_DB $db, SCM_Validator $validator ) {
        $this->db        = $db;
        $this->validator = $validator;
    }

    public function get_by_rule( $rule_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->db->schemas_table()} WHERE rule_id = %d ORDER BY priority ASC, id ASC", (int) $rule_id ), ARRAY_A );
    }

    public function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->db->schemas_table()} WHERE id = %d", (int) $id ), ARRAY_A );
    }

    public function create( $data ) {
        global $wpdb;
        $decoded = $this->validator->validate_json( $data['schema_json'] );
        if ( is_wp_error( $decoded ) ) {
            return $decoded;
        }

        $now = current_time( 'mysql' );
        $wpdb->insert(
            $this->db->schemas_table(),
            array(
                'rule_id'       => (int) $data['rule_id'],
                'label'         => sanitize_text_field( $data['label'] ),
                'schema_type'   => sanitize_text_field( $data['schema_type'] ?: $this->validator->detect_schema_type( $decoded ) ),
                'schema_source' => sanitize_text_field( $data['schema_source'] ?? 'manual_json' ),
                'schema_json'   => $data['schema_json'],
                'priority'      => isset( $data['priority'] ) ? (int) $data['priority'] : 10,
                'is_active'     => empty( $data['is_active'] ) ? 0 : 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        $id = (int) $wpdb->insert_id;
        do_action( 'scm_after_schema_create', $id, (int) $data['rule_id'] );
        return $id;
    }

    public function update( $id, $data ) {
        global $wpdb;
        $decoded = $this->validator->validate_json( $data['schema_json'] );
        if ( is_wp_error( $decoded ) ) {
            return $decoded;
        }

        $wpdb->update(
            $this->db->schemas_table(),
            array(
                'label'         => sanitize_text_field( $data['label'] ),
                'schema_type'   => sanitize_text_field( $data['schema_type'] ?: $this->validator->detect_schema_type( $decoded ) ),
                'schema_source' => sanitize_text_field( $data['schema_source'] ?? 'manual_json' ),
                'schema_json'   => $data['schema_json'],
                'priority'      => isset( $data['priority'] ) ? (int) $data['priority'] : 10,
                'is_active'     => empty( $data['is_active'] ) ? 0 : 1,
                'updated_at'    => current_time( 'mysql' ),
            ),
            array( 'id' => (int) $id ),
            array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
            array( '%d' )
        );

        do_action( 'scm_after_schema_update', (int) $id, (int) ( $data['rule_id'] ?? 0 ) );
        return true;
    }

    public function delete( $id ) {
        global $wpdb;
        $result = $wpdb->delete( $this->db->schemas_table(), array( 'id' => (int) $id ), array( '%d' ) );
        do_action( 'scm_after_schema_delete', (int) $id );
        return $result;
    }

    public function get_active_by_rule( $rule_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->db->schemas_table()} WHERE rule_id = %d AND is_active = 1 ORDER BY priority ASC, id ASC",
                (int) $rule_id
            ),
            ARRAY_A
        );
    }

    /**
     * Count schemas whose rule_id references a rule that no longer exists.
     * Used for the admin integrity notice on the rules list page.
     *
     * @return int
     */
    public function get_orphan_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(s.id)
             FROM {$this->db->schemas_table()} s
             LEFT JOIN {$this->db->rules_table()} r ON r.id = s.rule_id
             WHERE r.id IS NULL"
        );
    }
}
