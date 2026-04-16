<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Writes a JSON snapshot of all active rules (with their active schemas) to
 * wp-content/uploads/scm-cache/scm-rules-cache.json whenever the data changes.
 *
 * The database remains the authoritative source of truth.  This file is a
 * read-only export meant for auditing, debugging, or external tooling.
 *
 * Hooks registered via register_hooks() fire on the custom actions that
 * SCM_Rules and SCM_Schemas dispatch after every successful write:
 *   scm_after_rule_create / scm_after_rule_update / scm_after_rule_delete
 *   scm_after_schema_create / scm_after_schema_update / scm_after_schema_delete
 */
class SCM_Cache {

    /** @var SCM_Rules */
    private $rules;

    /** @var SCM_Schemas */
    private $schemas;

    const CACHE_SUBDIR = 'scm-cache';
    const CACHE_FILE   = 'scm-rules-cache.json';

    public function __construct( SCM_Rules $rules, SCM_Schemas $schemas ) {
        $this->rules   = $rules;
        $this->schemas = $schemas;
    }

    // ── Path helpers ──────────────────────────────────────────────────────────

    /**
     * Absolute path to the cache directory (no trailing slash).
     * Resolves to wp-content/uploads/scm-cache/.
     *
     * @return string
     */
    public function get_cache_dir(): string {
        $uploads = wp_upload_dir( null, false );
        return untrailingslashit( $uploads['basedir'] ) . '/' . self::CACHE_SUBDIR;
    }

    /**
     * Absolute path to the cache JSON file.
     *
     * @return string
     */
    public function get_cache_path(): string {
        return $this->get_cache_dir() . '/' . self::CACHE_FILE;
    }

    /**
     * Create the cache directory when it does not yet exist.
     * Adds an .htaccess that denies direct HTTP access on Apache.
     *
     * @return bool  True when the directory is ready for writing.
     */
    public function ensure_dir(): bool {
        $dir = $this->get_cache_dir();

        if ( ! is_dir( $dir ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                return false;
            }
        }

        // Block direct browser access on Apache servers.
        $htaccess = $dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            // Suppress warning: non-fatal if the write fails (Nginx installs, etc.).
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            @file_put_contents( $htaccess, "Deny from all\n" );
        }

        // Nginx sites may honour an index.php that returns 403.
        $index = $dir . '/index.php';
        if ( ! file_exists( $index ) ) {
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            @file_put_contents( $index, "<?php // Silence is golden.\n" );
        }

        return true;
    }

    // ── Cache generation ──────────────────────────────────────────────────────

    /**
     * Read all active rules and their active schemas from the database and
     * write a normalised JSON snapshot to disk.
     *
     * Can be called directly or used as a WordPress action callback; the
     * optional $ignored parameter absorbs any action argument without effect.
     *
     * @param  mixed $ignored  Unused — accepts the rule/schema ID passed by action hooks.
     * @return bool            True on success, false on any failure.
     */
    public function regenerate_rules_cache( $ignored = null ): bool {
        if ( ! $this->ensure_dir() ) {
            return false;
        }

        $active_rules = $this->rules->get_all( array( 'is_active' => 1 ) );
        $rules_output = array();

        foreach ( $active_rules as $rule ) {
            $replaced_types = is_array( $rule['replaced_types'] )
                ? $rule['replaced_types']
                : ( json_decode( (string) ( $rule['replaced_types'] ?? '[]' ), true ) ?: array() );

            $schemas_raw    = $this->schemas->get_active_by_rule( (int) $rule['id'] );
            $schemas_output = array();

            foreach ( $schemas_raw as $schema ) {
                $schemas_output[] = array(
                    'id'            => (int) $schema['id'],
                    'label'         => (string) $schema['label'],
                    'schema_type'   => (string) $schema['schema_type'],
                    'schema_source' => (string) $schema['schema_source'],
                    'priority'      => (int) $schema['priority'],
                    'schema_json'   => (string) $schema['schema_json'],
                );
            }

            $rules_output[] = array(
                'id'             => (int) $rule['id'],
                'label'          => (string) $rule['label'],
                'target_type'    => (string) $rule['target_type'],
                'target_value'   => (string) $rule['target_value'],
                'mode'           => (string) $rule['mode'],
                'replaced_types' => $replaced_types,
                'priority'       => (int) $rule['priority'],
                'updated_at'     => (string) $rule['updated_at'],
                'schemas'        => $schemas_output,
            );
        }

        $payload = array(
            'generated_at'   => gmdate( DATE_W3C ),
            'plugin_version' => defined( 'SCM_VERSION' ) ? SCM_VERSION : '',
            'rule_count'     => count( $rules_output ),
            'rules'          => $rules_output,
        );

        $json = wp_json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ( false === $json ) {
            // wp_json_encode failed (non-UTF-8 data, resource types, etc.).
            return false;
        }

        // LOCK_EX prevents partial reads if two requests regenerate simultaneously.
        $bytes = file_put_contents( $this->get_cache_path(), $json, LOCK_EX );

        return false !== $bytes;
    }

    // ── Hook registration ─────────────────────────────────────────────────────

    /**
     * Attach regenerate_rules_cache() to the custom actions fired by SCM_Rules
     * and SCM_Schemas after every successful database write.
     */
    public function register_hooks(): void {
        $cb = array( $this, 'regenerate_rules_cache' );

        add_action( 'scm_after_rule_create',   $cb, 10, 1 );
        add_action( 'scm_after_rule_update',   $cb, 10, 1 );
        add_action( 'scm_after_rule_delete',   $cb, 10, 1 );
        add_action( 'scm_after_schema_create', $cb, 10, 1 );
        add_action( 'scm_after_schema_update', $cb, 10, 1 );
        add_action( 'scm_after_schema_delete', $cb, 10, 1 );
    }
}
