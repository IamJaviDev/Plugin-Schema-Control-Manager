<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCM_Admin {
    protected $rules;
    protected $schemas;
    private $validator;
    private $import_export;
    private $graph_manager;
    private $normalizer;

    public function __construct( SCM_Rules $rules, SCM_Schemas $schemas, SCM_Validator $validator, SCM_Import_Export $import_export, SCM_Graph_Manager $graph_manager, SCM_Input_Normalizer $normalizer ) {
        $this->rules         = $rules;
        $this->schemas       = $schemas;
        $this->validator     = $validator;
        $this->import_export = $import_export;
        $this->graph_manager = $graph_manager;
        $this->normalizer    = $normalizer;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
        add_action( 'wp_ajax_scm_generate_preview', array( $this, 'handle_ajax_generate_preview' ) );
    }

    public function register_menu() {
        add_menu_page( __( 'Schema Manager', 'schema-control-manager' ), __( 'Schema Manager', 'schema-control-manager' ), 'manage_options', 'scm_rules', array( $this, 'render_rules_page' ), 'dashicons-media-code', 81 );
        add_submenu_page( 'scm_rules', __( 'Rules', 'schema-control-manager' ), __( 'Rules', 'schema-control-manager' ), 'manage_options', 'scm_rules', array( $this, 'render_rules_page' ) );
        add_submenu_page( 'scm_rules', __( 'Add Rule', 'schema-control-manager' ), __( 'Add Rule', 'schema-control-manager' ), 'manage_options', 'scm_rule_edit', array( $this, 'render_rule_edit_page' ) );
        add_submenu_page( 'scm_rules', __( 'Import / Export', 'schema-control-manager' ), __( 'Import / Export', 'schema-control-manager' ), 'manage_options', 'scm_import_export', array( $this, 'render_import_export_page' ) );
        add_submenu_page( 'scm_rules', __( 'Settings', 'schema-control-manager' ), __( 'Settings', 'schema-control-manager' ), 'manage_options', 'scm_settings', array( $this, 'render_settings_page' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'scm_' ) ) {
            return;
        }

        wp_enqueue_style( 'scm-admin', SCM_PLUGIN_URL . 'admin/assets/admin.css', array(), SCM_VERSION );
        wp_enqueue_script( 'scm-admin', SCM_PLUGIN_URL . 'admin/assets/admin.js', array(), SCM_VERSION, true );
        wp_localize_script( 'scm-admin', 'scmData', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'scm_preview_nonce' ),
        ) );
    }

    public function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['scm_save_rule'] ) ) {
            check_admin_referer( 'scm_save_rule' );
            $rule_data = array(
                'label'          => wp_unslash( $_POST['label'] ?? '' ),
                'target_type'    => wp_unslash( $_POST['target_type'] ?? 'exact_slug' ),
                'target_value'   => wp_unslash( $_POST['target_value'] ?? '' ),
                'mode'           => wp_unslash( $_POST['mode'] ?? 'aioseo_plus_custom' ),
                'replaced_types' => isset( $_POST['replaced_types'] ) ? (array) wp_unslash( $_POST['replaced_types'] ) : array(),
                'priority'       => isset( $_POST['priority'] ) ? (int) $_POST['priority'] : 100,
                'is_active'      => ! empty( $_POST['is_active'] ) ? 1 : 0,
            );

            $rule_id = isset( $_POST['rule_id'] ) ? (int) $_POST['rule_id'] : 0;

            // ── Rule-level validation (e.g. taxonomy_term format) ─────────────
            $rule_validation = $this->validator->validate_rule( $rule_data );
            if ( is_wp_error( $rule_validation ) ) {
                $back = admin_url( 'admin.php?page=scm_rule_edit' );
                if ( $rule_id ) {
                    $back = add_query_arg( 'rule_id', $rule_id, $back );
                }
                wp_safe_redirect( add_query_arg( 'rule_error', rawurlencode( $rule_validation->get_error_message() ), $back ) );
                exit;
            }

            if ( $rule_id ) {
                $this->rules->update( $rule_id, $rule_data );
            } else {
                $rule_id = $this->rules->create( $rule_data );
            }

            wp_safe_redirect( admin_url( 'admin.php?page=scm_rule_edit&rule_id=' . $rule_id . '&updated=1' ) );
            exit;
        }

        if ( isset( $_POST['scm_save_schema'] ) ) {
            check_admin_referer( 'scm_save_schema' );
            $schema_data = array(
                'rule_id'       => (int) ( $_POST['rule_id'] ?? 0 ),
                'label'         => wp_unslash( $_POST['schema_label'] ?? '' ),
                'schema_type'   => wp_unslash( $_POST['schema_type'] ?? '' ),
                'schema_source' => wp_unslash( $_POST['schema_source'] ?? 'manual_json' ),
                'schema_json'   => wp_unslash( $_POST['schema_json'] ?? '' ),
                'priority'      => (int) ( $_POST['priority'] ?? 10 ),
                'is_active'     => ! empty( $_POST['schema_is_active'] ) ? 1 : 0,
            );

            $schema_id       = isset( $_POST['schema_id'] ) ? (int) $_POST['schema_id'] : 0;
            $target          = admin_url( 'admin.php?page=scm_rule_edit&rule_id=' . (int) $schema_data['rule_id'] );
            $rule_raw        = $this->rules->get( (int) $schema_data['rule_id'] );
            $existing_schema = $schema_id > 0 ? $this->schemas->get( $schema_id ) : null;

            // ── Ownership and payload validation (must run before any write) ──
            $ownership_error = $this->validate_schema_ownership(
                (int) $schema_data['rule_id'],
                $schema_id,
                $rule_raw,
                $existing_schema,
                $schema_data['schema_json']
            );
            if ( null !== $ownership_error ) {
                wp_safe_redirect( add_query_arg( 'schema_error', rawurlencode( $ownership_error->get_error_message() ), $target ) );
                exit;
            }

            // $rule_raw is guaranteed non-null after ownership validation passes.
            $rule = $rule_raw;
            $rule['replaced_types'] = is_array( $rule['replaced_types'] ?? null ) ? $rule['replaced_types'] : ( json_decode( $rule['replaced_types'] ?? '[]', true ) ?: array() );

            $diagnostics   = $this->graph_manager->get_diagnostics_for_json( $schema_data['schema_json'], $rule );
            $blocked_types = $this->get_blocked_structural_types( $diagnostics['types'] );

            if ( 'aioseo_plus_custom' === $rule['mode'] && ! empty( $blocked_types ) ) {
                // Hard-block structural types in aioseo_plus_custom.
                $dangerous     = array_intersect( array( 'webpage', 'website', 'profilepage' ), $blocked_types );
                $danger_notice = ! empty( $dangerous )
                    ? sprintf(
                        ' ' . __( 'Types %s are especially dangerous as they overwrite AIOSEO\'s page structure.', 'schema-control-manager' ),
                        implode( ', ', array_map( 'ucfirst', $dangerous ) )
                    )
                    : '';
                $result = new WP_Error(
                    'unsafe_structural_addition',
                    sprintf(
                        /* translators: 1: list of blocked types, 2: optional danger notice */
                        __( 'AIOSEO + Custom is intended for additive types (FAQPage, HowTo, Service…). Structural types detected: %1$s.%2$s Use "Override selected types" mode instead.', 'schema-control-manager' ),
                        implode( ', ', $blocked_types ),
                        $danger_notice
                    )
                );
            } elseif ( in_array( $rule['mode'], array( 'custom_only', 'custom_override_selected' ), true ) && ! empty( $diagnostics['errors'] ) ) {
                $result = new WP_Error( 'graph_integrity', implode( ' | ', $diagnostics['errors'] ) );
            } else {
                $result = $schema_id ? $this->schemas->update( $schema_id, $schema_data ) : $this->schemas->create( $schema_data );
            }

            if ( is_wp_error( $result ) ) {
                $target = add_query_arg( 'schema_error', rawurlencode( $result->get_error_message() ), $target );
            } else {
                $target = add_query_arg( 'schema_updated', 1, $target );
            }

            wp_safe_redirect( $target );
            exit;
        }

        if ( isset( $_GET['action'], $_GET['rule_id'] ) && 'duplicate_rule' === $_GET['action'] ) {
            $rule_id = (int) $_GET['rule_id'];
            check_admin_referer( 'scm_duplicate_rule_' . $rule_id );

            $original = $this->rules->get( $rule_id );
            if ( $original ) {
                $original['replaced_types'] = is_array( $original['replaced_types'] )
                    ? $original['replaced_types']
                    : ( json_decode( $original['replaced_types'], true ) ?: array() );

                $base_label = preg_replace( '/ \(Copy\)$/', '', $original['label'] );

                $new_rule_id = $this->rules->create( array(
                    'label'          => $base_label . ' (Copy)',
                    'target_type'    => $original['target_type'],
                    'target_value'   => $original['target_value'],
                    'mode'           => $original['mode'],
                    'replaced_types' => $original['replaced_types'],
                    'priority'       => $original['priority'],
                    'is_active'      => 0,
                ) );

                foreach ( $this->schemas->get_by_rule( $rule_id ) as $schema ) {
                    $this->schemas->create( array(
                        'rule_id'       => $new_rule_id,
                        'label'         => $schema['label'],
                        'schema_type'   => $schema['schema_type'],
                        'schema_source' => $schema['schema_source'],
                        'schema_json'   => $schema['schema_json'],
                        'priority'      => $schema['priority'],
                        'is_active'     => $schema['is_active'],
                    ) );
                }

                wp_safe_redirect( admin_url( 'admin.php?page=scm_rule_edit&rule_id=' . $new_rule_id . '&duplicated=1' ) );
                exit;
            }

            wp_safe_redirect( admin_url( 'admin.php?page=scm_rules' ) );
            exit;
        }

        if ( isset( $_GET['scm_delete_rule'], $_GET['_wpnonce'] ) ) {
            $id = (int) $_GET['scm_delete_rule'];
            if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'scm_delete_rule_' . $id ) ) {
                $this->rules->delete( $id );
                wp_safe_redirect( admin_url( 'admin.php?page=scm_rules&deleted=1' ) );
                exit;
            }
        }

        if ( isset( $_GET['scm_delete_schema'], $_GET['_wpnonce'] ) ) {
            $id      = (int) $_GET['scm_delete_schema'];
            $rule_id = (int) ( $_GET['rule_id'] ?? 0 );
            if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'scm_delete_schema_' . $id ) ) {
                $this->schemas->delete( $id );
                wp_safe_redirect( admin_url( 'admin.php?page=scm_rule_edit&rule_id=' . $rule_id . '&schema_deleted=1' ) );
                exit;
            }
        }

        if ( isset( $_POST['scm_save_settings'] ) ) {
            check_admin_referer( 'scm_save_settings' );
            $settings = array(
                'aioseo_integration_enabled'    => ! empty( $_POST['aioseo_integration_enabled'] ) ? 1 : 0,
                'debug_mode'                    => ! empty( $_POST['debug_mode'] ) ? 1 : 0,
                'pretty_print_json'             => ! empty( $_POST['pretty_print_json'] ) ? 1 : 0,
                'strip_empty_values'            => ! empty( $_POST['strip_empty_values'] ) ? 1 : 0,
                'auto_add_context'              => ! empty( $_POST['auto_add_context'] ) ? 1 : 0,
                'auto_wrap_graph'               => ! empty( $_POST['auto_wrap_graph'] ) ? 1 : 0,
                'warn_on_structural_without_id' => ! empty( $_POST['warn_on_structural_without_id'] ) ? 1 : 0,
                'enable_graph_diagnostics'      => ! empty( $_POST['enable_graph_diagnostics'] ) ? 1 : 0,
                'conflict_types_default'        => array_values( array_filter( array_map( 'trim', explode( ',', wp_unslash( $_POST['conflict_types_default'] ?? '' ) ) ) ) ),
                'preview_language'              => in_array( wp_unslash( $_POST['preview_language'] ?? '' ), array( 'en', 'es' ), true ) ? wp_unslash( $_POST['preview_language'] ) : 'en',
                'delete_data_on_uninstall'      => ! empty( $_POST['delete_data_on_uninstall'] ) ? 1 : 0,
            );
            update_option( 'scm_settings', $settings );
            wp_safe_redirect( admin_url( 'admin.php?page=scm_settings&updated=1' ) );
            exit;
        }

        if ( isset( $_GET['scm_export'] ) ) {
            $scope = sanitize_text_field( wp_unslash( $_GET['scm_export'] ) );
            if ( 'all' === $scope ) {
                check_admin_referer( 'scm_export_all' );
                $this->import_export->download_export_all();
            } elseif ( 'rule' === $scope && ! empty( $_GET['rule_id'] ) ) {
                $rule_id = (int) $_GET['rule_id'];
                check_admin_referer( 'scm_export_rule_' . $rule_id );
                $this->import_export->download_export_rule( $rule_id );
            }
            exit;
        }

        if ( isset( $_POST['scm_import_payload'] ) ) {
            check_admin_referer( 'scm_import_payload' );
            if ( empty( $_FILES['scm_import_file']['tmp_name'] ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=scm_import_export&error=no_file' ) );
                exit;
            }
            $contents = file_get_contents( $_FILES['scm_import_file']['tmp_name'] );
            $payload  = json_decode( $contents, true );
            if ( JSON_ERROR_NONE !== json_last_error() ) {
                wp_safe_redirect( admin_url( 'admin.php?page=scm_import_export&error=invalid_json' ) );
                exit;
            }
            $result = $this->import_export->import_payload( $payload );
            if ( is_wp_error( $result ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=scm_import_export&error=' . rawurlencode( $result->get_error_message() ) ) );
                exit;
            }
            wp_safe_redirect( admin_url( 'admin.php?page=scm_import_export&imported=1' ) );
            exit;
        }
    }

    // ── AJAX: simulated preview ───────────────────────────────────────────────

    public function handle_ajax_generate_preview() {
        // Capture any stray output (debug notices, plugin hooks firing during
        // admin_init) so it cannot corrupt the JSON response body.
        ob_start();

        // 'nonce' is the field name sent by the JS FormData payload.
        check_ajax_referer( 'scm_preview_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'schema-control-manager' ) ), 403 );
        }

        $rule_id = isset( $_POST['rule_id'] ) ? (int) $_POST['rule_id'] : 0;
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

        if ( $rule_id < 1 ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Invalid rule ID.', 'schema-control-manager' ) ) );
        }

        if ( $post_id < 1 ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'schema-control-manager' ) ) );
        }

        if ( ! get_post( $post_id ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => sprintf(
                /* translators: %d: post ID */
                __( 'Post #%d was not found.', 'schema-control-manager' ),
                $post_id
            ) ) );
        }

        $rule = $this->rules->get( $rule_id );
        if ( ! $rule ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => sprintf(
                /* translators: %d: rule ID */
                __( 'Rule #%d was not found.', 'schema-control-manager' ),
                $rule_id
            ) ) );
        }

        $rule['replaced_types'] = is_array( $rule['replaced_types'] )
            ? $rule['replaced_types']
            : ( json_decode( $rule['replaced_types'] ?? '[]', true ) ?: array() );

        $context = SCM_Request_Context::from_post_id( $post_id );
        $nodes   = $this->graph_manager->get_custom_nodes_for_rule( $rule_id, $rule, $context );

        $json = wp_json_encode(
            array( '@context' => 'https://schema.org', '@graph' => $nodes ),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ( false === $json ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Failed to encode schema output as JSON.', 'schema-control-manager' ) ) );
        }

        ob_end_clean();
        wp_send_json_success( array(
            'post_id'  => $post_id,
            'rule_id'  => $rule_id,
            'json'     => $json,
            'is_empty' => empty( $nodes ),
        ) );
    }

    public function render_rules_page() {
        $search = sanitize_text_field( $_GET['search'] ?? '' );
        $rules  = $this->rules->get_all(
            array(
                'target_type' => sanitize_text_field( $_GET['target_type'] ?? '' ),
                'is_active'   => isset( $_GET['is_active'] ) ? sanitize_text_field( $_GET['is_active'] ) : '',
                'search'      => $search,
            )
        );
        $orphan_count = $this->schemas->get_orphan_count();
        include SCM_PLUGIN_DIR . 'admin/views/rules-list.php';
    }

    public function render_rule_edit_page() {
        $rule_id        = (int) ( $_GET['rule_id'] ?? 0 );
        $edit_schema_id = (int) ( $_GET['schema_id'] ?? 0 );
        $rule           = $rule_id ? $this->rules->get( $rule_id ) : null;
        $rule           = $rule ?: array(
            'id'             => 0,
            'label'          => '',
            'target_type'    => 'exact_slug',
            'target_value'   => '',
            'mode'           => 'aioseo_plus_custom',
            'replaced_types' => wp_json_encode( array() ),
            'is_active'      => 1,
        );
        $rule['replaced_types'] = is_array( $rule['replaced_types'] ) ? $rule['replaced_types'] : ( json_decode( $rule['replaced_types'], true ) ?: array() );
        $schemas                = $rule_id ? $this->schemas->get_by_rule( $rule_id ) : array();

        // ── Edit-screen ownership check ───────────────────────────────────────
        $edit_schema_mismatch = false;
        $edit_schema          = null;
        if ( $edit_schema_id > 0 ) {
            $edit_schema = $this->schemas->get( $edit_schema_id );
            if ( null === $edit_schema ) {
                $edit_schema_mismatch = true;
            } elseif ( $rule_id > 0 && (int) $edit_schema['rule_id'] !== $rule_id ) {
                $edit_schema_mismatch = true;
                $edit_schema          = null;
            }
        }
        if ( null === $edit_schema ) {
            $edit_schema = array(
                'id'            => 0,
                'label'         => '',
                'schema_type'   => 'Custom',
                'schema_source' => 'manual_json',
                'schema_json'   => "{\n  \"@type\": \"Thing\"\n}",
                'priority'      => 10,
                'is_active'     => 1,
            );
        }
        $settings = get_option( 'scm_settings', array() );

        $rule_summary = $this->build_rule_summary( $rule, $schemas );

        // ── Per-schema diagnostics (individual schema being edited) ────────
        $diagnostics = array(
            'errors'              => array(),
            'structural_warnings' => array(),
            'semantic_warnings'   => array(),
            'warnings'            => array(),
            'node_count'          => 0,
            'types'               => array(),
            'domains'             => array(),
            'normalized'          => null,
        );
        if ( ! empty( $edit_schema['schema_json'] ) ) {
            $diagnostics = $this->graph_manager->get_diagnostics_for_json( $edit_schema['schema_json'], $rule );
        }

        // ── Rule-level diagnostics (all active schemas combined) ───────────
        $rule_diagnostics         = null;
        $rule_diagnostics_notices = array( 'errors' => array(), 'warnings' => array() );
        if ( $rule_id && ! empty( $schemas ) ) {
            $rule_diagnostics         = $this->graph_manager->get_diagnostics_for_rule( $rule_id, $rule );
            $rule_diagnostics_notices = $this->graph_manager->get_last_merge_notices();
            // Merge normalization errors into rule_diagnostics.
            if ( ! empty( $rule_diagnostics_notices['errors'] ) ) {
                $rule_diagnostics['errors'] = array_values( array_unique(
                    array_merge( $rule_diagnostics['errors'], $rule_diagnostics_notices['errors'] )
                ) );
            }
        }

        // ── Runtime notices from last frontend render ──────────────────────
        $runtime_notices = null;
        if ( $rule_id ) {
            $stored = get_transient( 'scm_runtime_notices_rule_' . $rule_id );
            if ( $stored ) {
                $runtime_notices = $stored;
                delete_transient( 'scm_runtime_notices_rule_' . $rule_id );
            }
        }

        // ── Final Graph Preview payload ─────────────────────────────────────
        $preview_payload = null;
        if ( $rule_id ) {
            $preview_payload = $this->graph_manager->get_preview_payload_for_rule( $rule_id, $rule );
        }

        // ── Posts for simulated preview (context-aware filtering) ────────────
        $preview_posts           = array();
        $preview_preselect_id    = 0;
        $preview_context_notice  = '';
        $preview_posts_match_map = array(); // post_id => bool

        if ( $rule_id ) {
            $target_type  = $rule['target_type'];
            $target_value = (string) ( $rule['target_value'] ?? '' );

            $base_args = array(
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'no_found_rows'  => true,
            );

            switch ( $target_type ) {

                case 'post_type':
                    $pt            = sanitize_key( $target_value ) ?: 'post';
                    $preview_posts = get_posts( array_merge( $base_args, array( 'post_type' => $pt ) ) );
                    foreach ( $preview_posts as $p ) {
                        $preview_posts_match_map[ $p->ID ] = ( $p->post_type === $pt );
                    }
                    break;

                case 'post_type_archive':
                    $pt            = sanitize_key( $target_value ) ?: 'post';
                    $preview_posts = get_posts( array_merge( $base_args, array( 'post_type' => $pt ) ) );
                    $preview_context_notice = __( 'This rule targets a CPT archive page. The preview simulates a singular post context — useful for template resolution, but the rule fires on the archive page, not on individual posts.', 'schema-control-manager' );
                    foreach ( $preview_posts as $p ) {
                        $preview_posts_match_map[ $p->ID ] = false;
                    }
                    break;

                case 'category':
                    $slug          = sanitize_text_field( $target_value );
                    $preview_posts = get_posts( array_merge( $base_args, array(
                        'post_type' => 'any',
                        'tax_query' => array( array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => $slug ) ),
                    ) ) );
                    if ( empty( $preview_posts ) ) {
                        $preview_posts = get_posts( array_merge( $base_args, array( 'post_type' => 'any' ) ) );
                        $preview_context_notice = sprintf(
                            /* translators: %s: category slug */
                            __( 'No posts found in category "%s". Showing all posts.', 'schema-control-manager' ),
                            $slug
                        );
                        foreach ( $preview_posts as $p ) {
                            $preview_posts_match_map[ $p->ID ] = false;
                        }
                    } else {
                        $preview_context_notice = __( 'This rule targets a category archive. The preview simulates a singular post context.', 'schema-control-manager' );
                        foreach ( $preview_posts as $p ) {
                            $preview_posts_match_map[ $p->ID ] = true;
                        }
                    }
                    break;

                case 'tag':
                    $slug          = sanitize_text_field( $target_value );
                    $preview_posts = get_posts( array_merge( $base_args, array(
                        'post_type' => 'any',
                        'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $slug ) ),
                    ) ) );
                    if ( empty( $preview_posts ) ) {
                        $preview_posts = get_posts( array_merge( $base_args, array( 'post_type' => 'any' ) ) );
                        $preview_context_notice = sprintf(
                            /* translators: %s: tag slug */
                            __( 'No posts found with tag "%s". Showing all posts.', 'schema-control-manager' ),
                            $slug
                        );
                        foreach ( $preview_posts as $p ) {
                            $preview_posts_match_map[ $p->ID ] = false;
                        }
                    } else {
                        $preview_context_notice = __( 'This rule targets a tag archive. The preview simulates a singular post context.', 'schema-control-manager' );
                        foreach ( $preview_posts as $p ) {
                            $preview_posts_match_map[ $p->ID ] = true;
                        }
                    }
                    break;

                case 'taxonomy_term':
                    $parts     = explode( ':', $target_value, 2 );
                    $taxonomy  = isset( $parts[0] ) ? sanitize_key( trim( $parts[0] ) ) : '';
                    $term_slug = isset( $parts[1] ) ? sanitize_text_field( trim( $parts[1] ) ) : '';
                    if ( $taxonomy && $term_slug ) {
                        $preview_posts = get_posts( array_merge( $base_args, array(
                            'post_type' => 'any',
                            'tax_query' => array( array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $term_slug ) ),
                        ) ) );
                        $preview_context_notice = __( 'This rule targets a taxonomy term archive. The preview simulates a singular post context.', 'schema-control-manager' );
                    }
                    if ( empty( $preview_posts ) ) {
                        $preview_posts = get_posts( array_merge( $base_args, array( 'post_type' => 'any' ) ) );
                        if ( $taxonomy && $term_slug ) {
                            $preview_context_notice = sprintf(
                                /* translators: 1: taxonomy, 2: term slug */
                                __( 'No posts found for %1$s:%2$s. Showing all posts.', 'schema-control-manager' ),
                                $taxonomy,
                                $term_slug
                            );
                        }
                        foreach ( $preview_posts as $p ) {
                            $preview_posts_match_map[ $p->ID ] = false;
                        }
                    } else {
                        foreach ( $preview_posts as $p ) {
                            $preview_posts_match_map[ $p->ID ] = true;
                        }
                    }
                    break;

                case 'exact_slug':
                    $slug       = sanitize_text_field( $target_value );
                    $slug_posts = get_posts( array(
                        'name'           => $slug,
                        'post_type'      => 'any',
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        'no_found_rows'  => true,
                    ) );
                    if ( ! empty( $slug_posts ) ) {
                        $preview_preselect_id = (int) $slug_posts[0]->ID;
                        $preview_posts        = $slug_posts;
                        $preview_posts_match_map[ $slug_posts[0]->ID ] = true;
                    } else {
                        $preview_posts = get_posts( array_merge( $base_args, array( 'post_type' => 'any' ) ) );
                        $preview_context_notice = sprintf(
                            /* translators: %s: slug */
                            __( 'No published post found with slug "%s". Showing all posts.', 'schema-control-manager' ),
                            $slug
                        );
                        foreach ( $preview_posts as $p ) {
                            $preview_posts_match_map[ $p->ID ] = ( $p->post_name === $slug );
                        }
                    }
                    break;

                case 'exact_url':
                    $resolved_id = function_exists( 'url_to_postid' ) ? (int) url_to_postid( $target_value ) : 0;
                    // Fallback: treat target as a relative path slug.
                    if ( 0 === $resolved_id && '' !== $target_value ) {
                        $path_slug  = sanitize_text_field( ltrim( $target_value, '/' ) );
                        $slug_parts = explode( '/', $path_slug );
                        $leaf_slug  = end( $slug_parts );
                        if ( '' !== $leaf_slug ) {
                            $slug_posts = get_posts( array(
                                'name'           => $leaf_slug,
                                'post_type'      => 'any',
                                'post_status'    => 'publish',
                                'posts_per_page' => 1,
                                'no_found_rows'  => true,
                            ) );
                            if ( ! empty( $slug_posts ) ) {
                                $resolved_id = (int) $slug_posts[0]->ID;
                            }
                        }
                    }
                    if ( $resolved_id > 0 ) {
                        $resolved_post = get_post( $resolved_id );
                        if ( $resolved_post ) {
                            $preview_preselect_id = $resolved_id;
                            $preview_posts        = array( $resolved_post );
                            $preview_posts_match_map[ $resolved_id ] = true;
                            break;
                        }
                    }
                    $preview_posts = get_posts( array_merge( $base_args, array( 'post_type' => 'any' ) ) );
                    $preview_context_notice = __( 'Could not resolve the target URL to a published post. Showing all posts.', 'schema-control-manager' );
                    foreach ( $preview_posts as $p ) {
                        $preview_posts_match_map[ $p->ID ] = false;
                    }
                    break;

                case 'author':
                    $author = get_user_by( 'slug', sanitize_text_field( $target_value ) );
                    if ( $author ) {
                        $preview_posts = get_posts( array_merge( $base_args, array(
                            'post_type' => 'any',
                            'author'    => $author->ID,
                        ) ) );
                        $preview_context_notice = __( 'This rule targets an author archive. The preview simulates a singular post context.', 'schema-control-manager' );
                    }
                    if ( empty( $preview_posts ) ) {
                        $preview_posts = get_posts( array_merge( $base_args, array( 'post_type' => 'any' ) ) );
                        if ( $target_value ) {
                            $preview_context_notice = sprintf(
                                /* translators: %s: author slug */
                                __( 'No posts found for author "%s". Showing all posts.', 'schema-control-manager' ),
                                $target_value
                            );
                        }
                        foreach ( $preview_posts as $p ) {
                            $preview_posts_match_map[ $p->ID ] = false;
                        }
                    } else {
                        $author_id = isset( $author->ID ) ? (int) $author->ID : 0;
                        foreach ( $preview_posts as $p ) {
                            $preview_posts_match_map[ $p->ID ] = ( $author_id > 0 && (int) $p->post_author === $author_id );
                        }
                    }
                    break;

                case 'home':
                case 'front_page':
                    $preview_context_notice = __( 'This rule targets the home/front page. The preview uses a singular post context — results are an approximation only.', 'schema-control-manager' );
                    $preview_posts = get_posts( array_merge( $base_args, array( 'post_type' => 'any' ) ) );
                    foreach ( $preview_posts as $p ) {
                        $preview_posts_match_map[ $p->ID ] = false;
                    }
                    break;

                default:
                    $preview_posts = get_posts( array_merge( $base_args, array( 'post_type' => 'any' ) ) );
                    foreach ( $preview_posts as $p ) {
                        $preview_posts_match_map[ $p->ID ] = false;
                    }
                    break;
            }
        }

        include SCM_PLUGIN_DIR . 'admin/views/rule-edit.php';
    }

    public function render_import_export_page() {
        include SCM_PLUGIN_DIR . 'admin/views/import-export.php';
    }

    public function render_settings_page() {
        $settings = get_option( 'scm_settings', array() );
        include SCM_PLUGIN_DIR . 'admin/views/settings.php';
    }

    // ── Validation ──────────────────────────────────────────────────────────

    /**
     * Validate schema ownership before any save or update.
     *
     * Checks (in order):
     *  1. rule_id is present and > 0
     *  2. the rule actually exists
     *  3. when editing (schema_id > 0): the schema exists
     *  4. when editing: the schema belongs to this rule (not another)
     *  5. schema_json is not empty
     *
     * Public so that test subclasses can call it directly without instantiating
     * the full WordPress admin environment.
     *
     * @param int        $rule_id         Rule context from the form (POST rule_id).
     * @param int        $schema_id       Schema being edited; 0 for a new schema.
     * @param array|null $rule            Result of rules->get($rule_id); null if not found.
     * @param array|null $existing_schema Result of schemas->get($schema_id); null if not found.
     * @param string     $schema_json     Raw JSON payload from the form.
     * @return WP_Error|null              null on success, WP_Error on the first failure.
     */
    public function validate_schema_ownership(
        int    $rule_id,
        int    $schema_id,
        ?array $rule,
        ?array $existing_schema,
        string $schema_json
    ): ?WP_Error {
        if ( $rule_id <= 0 ) {
            return new WP_Error(
                'missing_rule_id',
                __( 'No rule context provided. A schema must be saved under a specific rule.', 'schema-control-manager' )
            );
        }

        if ( null === $rule ) {
            return new WP_Error(
                'rule_not_found',
                sprintf(
                    /* translators: %d: rule ID */
                    __( 'Rule #%d does not exist. The schema cannot be saved.', 'schema-control-manager' ),
                    $rule_id
                )
            );
        }

        if ( $schema_id > 0 ) {
            if ( null === $existing_schema ) {
                return new WP_Error(
                    'schema_not_found',
                    sprintf(
                        /* translators: %d: schema ID */
                        __( 'Schema #%d does not exist. It may have been deleted.', 'schema-control-manager' ),
                        $schema_id
                    )
                );
            }

            if ( (int) $existing_schema['rule_id'] !== $rule_id ) {
                return new WP_Error(
                    'schema_rule_mismatch',
                    sprintf(
                        /* translators: 1: schema ID, 2: actual rule ID, 3: expected rule ID */
                        __( 'Schema #%1$d belongs to Rule #%2$d, not Rule #%3$d. Saving is blocked to prevent data corruption.', 'schema-control-manager' ),
                        $schema_id,
                        (int) $existing_schema['rule_id'],
                        $rule_id
                    )
                );
            }
        }

        if ( '' === trim( $schema_json ) ) {
            return new WP_Error(
                'empty_schema_json',
                __( 'The schema JSON payload is empty. Nothing was saved.', 'schema-control-manager' )
            );
        }

        return null;
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    /**
     * Return blocked structural type names (lowercase) present in the detected types list.
     */
    private function get_blocked_structural_types( $detected_types ) {
        $structural = array( 'breadcrumblist', 'person', 'organization', 'webpage', 'website', 'profilepage', 'collectionpage' );
        return array_values( array_intersect( $structural, array_map( 'strtolower', (array) $detected_types ) ) );
    }

    private function build_rule_summary( $rule, $schemas ) {
        $mode          = $rule['mode'];
        $aioseo_status = 'aioseo_only' === $mode
            ? __( 'Active only', 'schema-control-manager' )
            : ( 'custom_only' === $mode ? __( 'Disabled', 'schema-control-manager' ) : __( 'Active', 'schema-control-manager' ) );
        $output        = 'aioseo_only' === $mode
            ? __( 'Only AIOSEO output', 'schema-control-manager' )
            : ( 'custom_only' === $mode
                ? __( 'Only custom output', 'schema-control-manager' )
                : ( 'custom_override_selected' === $mode
                    ? __( 'AIOSEO filtered + custom', 'schema-control-manager' )
                    : __( 'AIOSEO + custom merge', 'schema-control-manager' )
                )
            );

        return array(
            'aioseo_status' => $aioseo_status,
            'custom_count'  => count( $schemas ),
            'replacements'  => (array) $rule['replaced_types'],
            'output'        => $output,
        );
    }
}
