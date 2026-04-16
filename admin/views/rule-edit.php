<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap scm-wrap">
    <h1><?php echo $rule['id'] ? 'Editar regla' : 'Añadir regla'; ?></h1>

    <?php if ( ! empty( $_GET['updated'] ) ) : ?><div class="notice notice-success"><p>Regla guardada.</p></div><?php endif; ?>
    <?php if ( ! empty( $_GET['duplicated'] ) ) : ?><div class="notice notice-success"><p>Regla duplicada correctamente. Esta copia está inactiva — actívala cuando esté lista.</p></div><?php endif; ?>
    <?php if ( ! empty( $_GET['schema_updated'] ) ) : ?>
    <div class="notice notice-success"><p>
        <?php
        $rule_label_for_notice = ! empty( $rule['label'] ) ? $rule['label'] : ( $rule['id'] ? '#' . (int) $rule['id'] : '' );
        if ( $rule_label_for_notice ) {
            printf( 'Schema guardado para la regla: <strong>%s</strong>', esc_html( $rule_label_for_notice ) );
        } else {
            echo 'Schema guardado.';
        }
        ?>
    </p></div>
    <?php endif; ?>
    <?php if ( ! empty( $_GET['schema_deleted'] ) ) : ?><div class="notice notice-success"><p>Schema eliminado.</p></div><?php endif; ?>
    <?php if ( ! empty( $_GET['rule_error'] ) ) : ?><div class="notice notice-error"><p><strong>No se pudo guardar la regla:</strong> <?php echo esc_html( wp_unslash( $_GET['rule_error'] ) ); ?></p></div><?php endif; ?>
    <?php if ( ! empty( $_GET['schema_error'] ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( wp_unslash( $_GET['schema_error'] ) ); ?></p></div><?php endif; ?>

    <?php if ( ! empty( $edit_schema_mismatch ) ) : ?>
    <div class="notice notice-warning">
        <p><strong>Acceso al schema bloqueado</strong></p>
        <p><?php
        if ( ! empty( $_GET['schema_id'] ) ) {
            printf(
                'El schema #%d no puede editarse aquí — no pertenece a esta regla. Se ha cargado un formulario en blanco.',
                (int) $_GET['schema_id']
            );
        }
        ?></p>
    </div>
    <?php endif; ?>

    <?php // ── Runtime notices from last frontend page load ───────────────── ?>
    <?php if ( ! empty( $runtime_notices ) ) : ?>
        <div class="notice scm-notice-runtime">
            <p><strong>Problema detectado en tiempo de ejecución</strong>
            <?php if ( ! empty( $runtime_notices['rule_label'] ) ) : ?>
                (<?php echo esc_html( $runtime_notices['rule_label'] ); ?>)
            <?php endif; ?>
            <?php if ( ! empty( $runtime_notices['time'] ) ) : ?>
                &mdash; <?php echo esc_html( $runtime_notices['time'] ); ?>
            <?php endif; ?>
            </p>
            <?php if ( ! empty( $runtime_notices['errors'] ) ) : ?>
                <ul class="scm-warning-list scm-error-list">
                    <?php foreach ( $runtime_notices['errors'] as $err ) : ?>
                        <li><?php echo esc_html( $err ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ( ! empty( $runtime_notices['warnings'] ) ) : ?>
                <ul class="scm-warning-list scm-structural-warning-list">
                    <?php foreach ( $runtime_notices['warnings'] as $w ) : ?>
                        <li><?php echo esc_html( $w ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php // ── Rule-level diagnostics (all schemas combined) ─────────────── ?>
    <?php if ( ! empty( $rule_diagnostics ) ) : ?>
        <?php $has_rule_issues = ! empty( $rule_diagnostics['errors'] ) || ! empty( $rule_diagnostics['warnings'] ); ?>
        <?php if ( $has_rule_issues ) : ?>
            <div class="notice <?php echo ! empty( $rule_diagnostics['errors'] ) ? 'notice-error' : 'notice-warning'; ?> scm-notice-rule-diag">
                <p><strong>Diagnósticos de la regla (todos los schemas combinados)</strong></p>
                <?php if ( ! empty( $rule_diagnostics['errors'] ) ) : ?>
                    <ul class="scm-warning-list scm-error-list">
                        <?php foreach ( $rule_diagnostics['errors'] as $err ) : ?>
                            <li><?php echo esc_html( $err ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ( ! empty( $rule_diagnostics['structural_warnings'] ) ) : ?>
                    <ul class="scm-warning-list scm-structural-warning-list">
                        <?php foreach ( $rule_diagnostics['structural_warnings'] as $w ) : ?>
                            <li><?php echo esc_html( $w ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ( ! empty( $rule_diagnostics['semantic_warnings'] ) ) : ?>
                    <ul class="scm-warning-list scm-semantic-warning-list">
                        <?php foreach ( $rule_diagnostics['semantic_warnings'] as $w ) : ?>
                            <li><?php echo esc_html( $w ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="scm-grid scm-grid-top">
        <div class="scm-card">
            <h2>Regla</h2>
            <form method="post">
                <?php wp_nonce_field( 'scm_save_rule' ); ?>
                <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['id'] ); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="label">Etiqueta</label></th>
                        <td><input class="regular-text" type="text" name="label" id="label" value="<?php echo esc_attr( $rule['label'] ); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="target_type">Tipo de objetivo</label></th>
                        <td>
                            <select name="target_type" id="target_type">
                                <option value="home" <?php selected( $rule['target_type'], 'home' ); ?>>Inicio (portada o blog)</option>
                                <option value="front_page" <?php selected( $rule['target_type'], 'front_page' ); ?>>Portada (solo estática)</option>
                                <option value="exact_url" <?php selected( $rule['target_type'], 'exact_url' ); ?>>URL exacta</option>
                                <option value="exact_slug" <?php selected( $rule['target_type'], 'exact_slug' ); ?>>Slug exacto</option>
                                <option value="post_type" <?php selected( $rule['target_type'], 'post_type' ); ?>>Tipo de entrada (todos los singulares)</option>
                                <option value="post_type_archive" <?php selected( $rule['target_type'], 'post_type_archive' ); ?>>Archivo de tipo de entrada</option>
                                <option value="category" <?php selected( $rule['target_type'], 'category' ); ?>>Archivo de categoría</option>
                                <option value="tag" <?php selected( $rule['target_type'], 'tag' ); ?>>Archivo de etiqueta</option>
                                <option value="taxonomy_term" <?php selected( $rule['target_type'], 'taxonomy_term' ); ?>>Término de taxonomía</option>
                                <option value="author" <?php selected( $rule['target_type'], 'author' ); ?>>Página de autor</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="scm-target-value-row">
                        <th><label for="target_value">Valor del objetivo</label></th>
                        <td>
                            <input class="regular-text" type="text" name="target_value" id="target_value" value="<?php echo esc_attr( $rule['target_value'] ); ?>">
                            <p class="description" id="scm-target-help">
                                Inicio y Portada no necesitan un valor.
                                Para post_type / post_type_archive / category / tag: introduce el slug (ej. post, talleres, noticias).
                                Para taxonomy_term: usa el formato taxonomía:slug-del-término (ej. genero:ficcion).
                                Para páginas de autor: usa el user_nicename.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="mode">Modo</label></th>
                        <td>
                            <select name="mode" id="mode">
                                <option value="aioseo_only" <?php selected( $rule['mode'], 'aioseo_only' ); ?>>Solo AIOSEO</option>
                                <option value="aioseo_plus_custom" <?php selected( $rule['mode'], 'aioseo_plus_custom' ); ?>>AIOSEO + personalizado</option>
                                <option value="custom_override_selected" <?php selected( $rule['mode'], 'custom_override_selected' ); ?>>Reemplazar tipos seleccionados</option>
                                <option value="custom_only" <?php selected( $rule['mode'], 'custom_only' ); ?>>Solo personalizado</option>
                            </select>
                            <p class="description" id="scm-mode-help"></p>
                        </td>
                    </tr>
                    <tr class="scm-replaced-types-row">
                        <th>Tipos reemplazados</th>
                        <td>
                            <p class="description">Usa esto solo para reemplazos estructurales o anulaciones específicas. Los tipos estructurales como BreadcrumbList, Person y Organization pueden requerir reconexión de @id.</p>
                            <?php $types = $settings['conflict_types_default'] ?? array(); ?>
                            <?php foreach ( $types as $type ) : ?>
                                <label class="scm-inline"><input type="checkbox" name="replaced_types[]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, (array) $rule['replaced_types'], true ) ); ?>> <?php echo esc_html( $type ); ?></label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rule_priority">Prioridad</label></th>
                        <td>
                            <input class="small-text" type="number" name="priority" id="rule_priority" value="<?php echo esc_attr( $rule['priority'] ?? 100 ); ?>">
                            <p class="description">Número mayor = evaluado primero. Por defecto 100.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Estado</th>
                        <td><label><input type="checkbox" name="is_active" value="1" <?php checked( ! empty( $rule['is_active'] ) ); ?>> Activo</label></td>
                    </tr>
                </table>
                <p><button class="button button-primary" name="scm_save_rule" value="1">Guardar regla</button></p>
            </form>
        </div>

        <div class="scm-card">
            <h2>Resumen de la regla</h2>
            <ul class="scm-summary-list">
                <li><strong>AIOSEO:</strong> <span id="scm-summary-aioseo"><?php echo esc_html( $rule_summary['aioseo_status'] ); ?></span></li>
                <li><strong>Schemas personalizados:</strong> <span id="scm-summary-custom-count"><?php echo esc_html( $rule_summary['custom_count'] ); ?></span></li>
                <li><strong>Reemplazos:</strong> <span id="scm-summary-replacements"><?php echo esc_html( empty( $rule_summary['replacements'] ) ? '—' : implode( ', ', $rule_summary['replacements'] ) ); ?></span></li>
                <li><strong>Salida esperada:</strong> <span id="scm-summary-output"><?php echo esc_html( $rule_summary['output'] ); ?></span></li>
            </ul>
            <div class="scm-help-box">
                <p><strong>Consejos de uso</strong></p>
                <ul>
                    <li>Usa AIOSEO + personalizado para schemas aditivos como FAQPage, HowTo o Service.</li>
                    <li>Usa Reemplazar cuando sustituyas nodos estructurales como BreadcrumbList, Person u Organization.</li>
                    <li>Usa Solo personalizado cuando quieras control total sobre el grafo de la página.</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="scm-card">
        <h2>
            Schemas asociados a esta regla
            <?php if ( ! empty( $rule['label'] ) ) : ?>
                <span class="scm-rule-owner-badge"><?php echo esc_html( $rule['label'] ); ?></span>
            <?php endif; ?>
        </h2>
        <?php if ( ! $rule['id'] ) : ?>
            <p>Guarda la regla primero para adjuntar schemas.</p>
        <?php elseif ( empty( $schemas ) ) : ?>
            <p>Todavía no hay schemas.</p>
        <?php else : ?>
            <table class="widefat striped">
                <thead><tr><th>Etiqueta</th><th>Tipo</th><th>Prioridad</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ( $schemas as $schema ) : ?>
                    <tr>
                        <td><?php echo esc_html( $schema['label'] ); ?></td>
                        <td><?php echo esc_html( $schema['schema_type'] ); ?></td>
                        <td><?php echo esc_html( $schema['priority'] ); ?></td>
                        <td><?php echo ! empty( $schema['is_active'] ) ? 'Activo' : 'Inactivo'; ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=scm_rule_edit&rule_id=' . (int) $rule['id'] . '&schema_id=' . (int) $schema['id'] ) ); ?>">Editar</a>
                            <a class="button button-small button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=scm_rule_edit&rule_id=' . (int) $rule['id'] . '&scm_delete_schema=' . (int) $schema['id'] ), 'scm_delete_schema_' . (int) $schema['id'] ) ); ?>">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php // ── Final Graph Preview panel ──────────────────────────────────── ?>
    <?php if ( $rule['id'] && ! empty( $preview_payload ) ) : ?>
    <?php
        $p_status     = $preview_payload['status'] ?? 'valid';
        $p_counts     = $preview_payload['counts'] ?? array();
        $p_errors     = $preview_payload['errors'] ?? array();
        $p_structural = $preview_payload['structural_warnings'] ?? array();
        $p_semantic   = $preview_payload['semantic_warnings'] ?? array();
        $p_changes    = $preview_payload['changes'] ?? array();
        $p_graph      = $preview_payload['final_graph'] ?? array();
        $p_json       = wp_json_encode(
            array( '@context' => 'https://schema.org', '@graph' => $p_graph ),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $p_warning_count = count( $p_structural ) + count( $p_semantic );
        $preview_lang    = $settings['preview_language'] ?? 'es';
        $pt = array(
            'en' => array(
                'panel_title'  => 'Final Graph Preview',
                'panel_sub'    => 'Effective schema before frontend render',
                'status_valid' => 'Valid',
                'status_warn'  => 'Warnings',
                'status_err'   => 'Errors',
                'custom_nodes' => 'Custom nodes',
                'warnings'     => 'Warnings',
                'errors_label' => 'Errors',
                'crit_errors'  => 'Critical Errors',
                'str_warn'     => 'Structural Warnings',
                'sem_warn'     => 'Semantic Warnings',
                'changes'      => 'Changes applied',
                'no_changes'   => 'No graph changes detected',
                'final_graph'  => 'Final Graph (custom nodes)',
                'copy_json'    => 'Copy JSON',
                'copied'       => 'Copied!',
                'expand'       => 'Expand',
                'collapse'     => 'Collapse',
            ),
            'es' => array(
                'panel_title'  => 'Vista previa del grafo final',
                'panel_sub'    => 'Grafo efectivo antes del render en frontend',
                'status_valid' => 'Válido',
                'status_warn'  => 'Advertencias',
                'status_err'   => 'Errores',
                'custom_nodes' => 'Nodos personalizados',
                'warnings'     => 'Advertencias',
                'errors_label' => 'Errores',
                'crit_errors'  => 'Errores críticos',
                'str_warn'     => 'Advertencias estructurales',
                'sem_warn'     => 'Advertencias semánticas',
                'changes'      => 'Cambios aplicados',
                'no_changes'   => 'Sin cambios detectados',
                'final_graph'  => 'Grafo final (nodos personalizados)',
                'copy_json'    => 'Copiar JSON',
                'copied'       => '¡Copiado!',
                'expand'       => 'Expandir',
                'collapse'     => 'Contraer',
            ),
        );
        $t = $pt[ isset( $pt[ $preview_lang ] ) ? $preview_lang : 'es' ];
    ?>
    <div class="scm-card scm-card-full scm-preview-panel" id="scm-final-preview">

        <div class="scm-preview-header">
            <h2><?php echo esc_html( $t['panel_title'] ); ?></h2>
            <p class="scm-preview-subtitle"><?php echo esc_html( $t['panel_sub'] ); ?></p>
        </div>

        <div class="scm-preview-meta">
            <span class="scm-status-badge scm-status-<?php echo esc_attr( $p_status ); ?>">
                <?php
                if ( 'errors' === $p_status ) {
                    echo esc_html( $t['status_err'] );
                } elseif ( 'warnings' === $p_status ) {
                    echo esc_html( $t['status_warn'] );
                } else {
                    echo esc_html( $t['status_valid'] );
                }
                ?>
            </span>
            <span class="scm-preview-stats">
                <?php echo esc_html( $t['custom_nodes'] ); ?>: <strong><?php echo esc_html( $p_counts['added_nodes'] ?? 0 ); ?></strong>
                <?php if ( ( $p_counts['errors'] ?? 0 ) > 0 ) : ?>
                    &nbsp;&middot;&nbsp; <?php echo esc_html( $t['errors_label'] ); ?>: <strong class="scm-stat-error"><?php echo esc_html( $p_counts['errors'] ?? 0 ); ?></strong>
                <?php endif; ?>
                <?php if ( $p_warning_count > 0 ) : ?>
                    &nbsp;&middot;&nbsp; <?php echo esc_html( $t['warnings'] ); ?>: <strong class="scm-stat-warn"><?php echo esc_html( $p_warning_count ); ?></strong>
                <?php endif; ?>
            </span>
        </div>

        <?php if ( ! empty( $p_errors ) ) : ?>
        <div class="scm-collapsible">
            <button type="button" class="scm-collapsible-trigger" aria-expanded="true">
                <span class="scm-severity-critical"><?php echo esc_html( $t['crit_errors'] ); ?></span>
                <span class="scm-caret" aria-hidden="true">&#9658;</span>
            </button>
            <div class="scm-collapsible-body open">
                <ul class="scm-error-list">
                    <?php foreach ( $p_errors as $err ) : ?>
                        <li><?php echo esc_html( $err ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $p_structural ) ) : ?>
        <div class="scm-collapsible">
            <button type="button" class="scm-collapsible-trigger" aria-expanded="false">
                <span class="scm-severity-structural"><?php echo esc_html( $t['str_warn'] ); ?></span>
                <span class="scm-caret" aria-hidden="true">&#9658;</span>
            </button>
            <div class="scm-collapsible-body">
                <ul class="scm-structural-warning-list">
                    <?php foreach ( $p_structural as $w ) : ?>
                        <li><?php echo esc_html( $w ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $p_semantic ) ) : ?>
        <div class="scm-collapsible">
            <button type="button" class="scm-collapsible-trigger" aria-expanded="false">
                <span class="scm-severity-semantic"><?php echo esc_html( $t['sem_warn'] ); ?></span>
                <span class="scm-caret" aria-hidden="true">&#9658;</span>
            </button>
            <div class="scm-collapsible-body">
                <ul class="scm-semantic-warning-list">
                    <?php foreach ( $p_semantic as $w ) : ?>
                        <li><?php echo esc_html( $w ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <div class="scm-preview-changes">
            <p class="scm-preview-changes-title"><?php echo esc_html( $t['changes'] ); ?></p>
            <?php if ( ! empty( $p_changes ) ) : ?>
                <ul>
                    <?php foreach ( $p_changes as $change ) : ?>
                        <li><?php echo esc_html( $change ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="scm-no-changes"><?php echo esc_html( $t['no_changes'] ); ?></p>
            <?php endif; ?>
        </div>

        <div class="scm-preview-json">
            <div class="scm-preview-json-toolbar">
                <span class="scm-preview-json-title"><?php echo esc_html( $t['final_graph'] ); ?></span>
                <button type="button" class="button button-small" id="scm-copy-json" data-scm-copied="<?php echo esc_attr( $t['copied'] ); ?>"><?php echo esc_html( $t['copy_json'] ); ?></button>
                <button type="button" class="button button-small" id="scm-expand-json" data-scm-collapse="<?php echo esc_attr( $t['collapse'] ); ?>"><?php echo esc_html( $t['expand'] ); ?></button>
            </div>
            <pre class="scm-json-viewer" id="scm-json-viewer"><?php echo esc_html( false !== $p_json ? $p_json : '{}' ); ?></pre>
        </div>

    </div>
    <?php endif; ?>

    <?php if ( $rule['id'] ) : ?>
    <div class="scm-card scm-card-full scm-sim-preview-card">
        <h2>Vista previa (simulada)</h2>
        <p class="description">Selecciona una entrada publicada para simular el contexto de la solicitud y previsualizar el schema resuelto para esa entrada.</p>

        <?php if ( ! empty( $preview_context_notice ) ) : ?>
        <p class="scm-sim-context-notice">
            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
            <?php echo esc_html( $preview_context_notice ); ?>
        </p>
        <?php endif; ?>

        <?php if ( empty( $preview_posts ) ) : ?>
            <p>No se encontraron entradas publicadas.</p>
        <?php else : ?>
        <div class="scm-sim-preview-controls">
            <input
                type="text"
                id="scm-sim-post-search"
                class="regular-text scm-sim-post-search"
                placeholder="Filtrar por título, ID o slug&hellip;"
                autocomplete="off"
            >
            <select
                id="scm-sim-post-select"
                data-rule-target-type="<?php echo esc_attr( $rule['target_type'] ); ?>"
                data-preselect="<?php echo esc_attr( $preview_preselect_id ); ?>">
                <option value="">&mdash; Selecciona una entrada &mdash;</option>
                <?php foreach ( $preview_posts as $p ) :
                    $p_matches = ! empty( $preview_posts_match_map[ $p->ID ] );
                ?>
                    <option
                        value="<?php echo esc_attr( $p->ID ); ?>"
                        data-title="<?php echo esc_attr( $p->post_title ); ?>"
                        data-slug="<?php echo esc_attr( $p->post_name ); ?>"
                        data-post-type="<?php echo esc_attr( $p->post_type ); ?>"
                        data-matches="<?php echo $p_matches ? '1' : '0'; ?>"
                        <?php selected( (int) $p->ID, $preview_preselect_id ); ?>>
                        [<?php echo esc_html( $p->post_type ); ?>] <?php echo esc_html( $p->post_title ); ?> &middot; <?php echo esc_html( $p->post_name ); ?> &middot; ID:<?php echo esc_html( $p->ID ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div id="scm-sim-match-status" class="scm-sim-match-status" aria-live="polite"></div>
            <div class="scm-sim-btn-row">
                <button type="button" class="button" id="scm-sim-preview-btn"
                    data-rule-id="<?php echo esc_attr( $rule['id'] ); ?>">
                    Generar vista previa
                </button>
                <span class="scm-sim-preview-status" id="scm-sim-preview-status"></span>
            </div>
        </div>
        <pre class="scm-preview scm-sim-preview-output" id="scm-sim-preview-output" hidden></pre>
        <?php endif; ?>
    </div>

    <div class="scm-card scm-card-full">
        <h2>
            <?php echo $edit_schema['id'] ? 'Editar schema' : 'Añadir schema'; ?>
            <?php if ( ! empty( $rule['label'] ) ) : ?>
                <span class="scm-rule-owner-badge">Regla: <?php echo esc_html( $rule['label'] ); ?></span>
            <?php endif; ?>
        </h2>
        <form method="post">
            <?php wp_nonce_field( 'scm_save_schema' ); ?>
            <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['id'] ); ?>">
            <input type="hidden" name="schema_id" value="<?php echo esc_attr( $edit_schema['id'] ); ?>">
            <table class="form-table">
                <tr>
                    <th><label for="schema_label">Etiqueta</label></th>
                    <td><input class="regular-text" type="text" name="schema_label" id="schema_label" value="<?php echo esc_attr( $edit_schema['label'] ); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="schema_type">Tipo de schema</label></th>
                    <td>
                        <input class="regular-text" type="text" name="schema_type" id="schema_type" value="<?php echo esc_attr( $edit_schema['schema_type'] ); ?>">
                        <?php if ( 'author' === $rule['target_type'] ) : ?>
                            <p class="description">Para páginas de autor, Person se comporta habitualmente como nodo estructural y debería mantener un @id estable si es posible.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="priority">Prioridad</label></th>
                    <td><input class="small-text" type="number" name="priority" id="priority" value="<?php echo esc_attr( $edit_schema['priority'] ); ?>"></td>
                </tr>
                <tr>
                    <th>Estado</th>
                    <td><label><input type="checkbox" name="schema_is_active" value="1" <?php checked( ! empty( $edit_schema['is_active'] ) ); ?>> Activo</label></td>
                </tr>
                <tr>
                    <th><label for="schema_json">JSON-LD del schema</label></th>
                    <td>
                        <textarea class="large-text code scm-json-editor" rows="18" name="schema_json" id="schema_json" required><?php echo esc_textarea( $edit_schema['schema_json'] ); ?></textarea>
                        <p class="description">Puedes pegar un objeto JSON-LD completo, una lista de nodos o un objeto con @graph. El plugin normaliza a la estructura final de @context + @graph.</p>
                        <style>
                        .scm-var-inserter{position:relative;display:inline-block;margin:6px 0 4px}
                        .scm-var-panel{position:absolute;top:calc(100% + 4px);left:0;z-index:9999;background:#fff;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 4px 14px rgba(0,0,0,.13);width:300px;max-height:400px;display:flex;flex-direction:column;overflow:hidden}
                        .scm-var-panel[hidden]{display:none}
                        .scm-var-search{width:100%;box-sizing:border-box;padding:8px 10px;border:0;border-bottom:1px solid #e1e1e1;font-size:13px;outline:none;background:#f6f7f7}
                        .scm-var-groups{overflow-y:auto;flex:1;padding:4px 0}
                        .scm-var-group{padding:0}
                        .scm-var-group[hidden]{display:none}
                        .scm-var-group-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#999;padding:8px 12px 2px}
                        .scm-var-item{display:block;width:100%;text-align:left;background:none;border:none;padding:5px 12px;font-size:12px;font-family:monospace;color:#1d2327;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.5}
                        .scm-var-item:hover,.scm-var-item:focus{background:#f0f6fc;color:#0073aa;outline:none}
                        .scm-var-item[hidden]{display:none}
                        .scm-var-no-results{padding:10px 12px;font-size:12px;color:#888;font-style:italic}
                        </style>

                        <div class="scm-var-inserter">
                            <button type="button" class="button" id="scm-insert-var-btn">Insertar variable</button>
                            <div class="scm-var-panel" id="scm-var-panel" hidden>
                                <input type="search" class="scm-var-search" id="scm-var-search" placeholder="Buscar variable..." autocomplete="off">
                                <div class="scm-var-groups" id="scm-var-groups">

                                    <div class="scm-var-group">
                                        <div class="scm-var-group-title">Entrada / Página</div>
                                        <button type="button" class="scm-var-item" data-token="{{post_title}}">{{post_title}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{post_url}}">{{post_url}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{post_excerpt}}">{{post_excerpt}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{post_id}}">{{post_id}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{post_type}}">{{post_type}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{featured_image_url}}">{{featured_image_url}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{featured_image_alt}}">{{featured_image_alt}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{post_date}}">{{post_date}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{post_modified_date}}">{{post_modified_date}}</button>
                                    </div>

                                    <div class="scm-var-group">
                                        <div class="scm-var-group-title">Autor</div>
                                        <button type="button" class="scm-var-item" data-token="{{author_name}}">{{author_name}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{author_slug}}">{{author_slug}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{author_email}}">{{author_email}}</button>
                                    </div>

                                    <div class="scm-var-group">
                                        <div class="scm-var-group-title">Taxonomía</div>
                                        <button type="button" class="scm-var-item" data-token="{{queried_term_name}}">{{queried_term_name}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{queried_term_slug}}">{{queried_term_slug}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{queried_taxonomy}}">{{queried_taxonomy}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{term:TAXONOMY}}" data-prompt="term">{{term:TAXONOMY}}</button>
                                    </div>

                                    <div class="scm-var-group">
                                        <div class="scm-var-group-title">Sitio</div>
                                        <button type="button" class="scm-var-item" data-token="{{site_name}}">{{site_name}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{site_url}}">{{site_url}}</button>
                                    </div>

                                    <div class="scm-var-group">
                                        <div class="scm-var-group-title">Archivos / CPT</div>
                                        <button type="button" class="scm-var-item" data-token="{{archive_post_type}}">{{archive_post_type}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{archive_post_type_label}}">{{archive_post_type_label}}</button>
                                        <button type="button" class="scm-var-item" data-token="{{archive_post_type_url}}">{{archive_post_type_url}}</button>
                                    </div>

                                    <div class="scm-var-group">
                                        <div class="scm-var-group-title">Campos personalizados</div>
                                        <button type="button" class="scm-var-item" data-token="{{meta:FIELD_KEY}}" data-prompt="meta">{{meta:FIELD_KEY}}</button>
                                    </div>

                                    <p class="scm-var-no-results" id="scm-var-no-results" hidden>Ninguna variable coincide.</p>
                                </div>
                            </div>
                        </div>
                        <p class="description">
                            Los placeholders sin resolver se reemplazan por una cadena vacía.
                            Los placeholders solo se resuelven en tiempo de ejecución — la vista previa muestra los tokens sin procesar.
                        </p>

                        <script>
                        (function () {
                            var btn    = document.getElementById('scm-insert-var-btn');
                            var panel  = document.getElementById('scm-var-panel');
                            var search = document.getElementById('scm-var-search');
                            var noRes  = document.getElementById('scm-var-no-results');
                            var ta     = document.getElementById('schema_json');
                            if (!btn || !panel || !ta) return;

                            btn.addEventListener('click', function (e) {
                                e.stopPropagation();
                                var opening = panel.hidden;
                                panel.hidden = !opening;
                                if (opening) {
                                    search.value = '';
                                    filterVars('');
                                    search.focus();
                                }
                            });

                            document.addEventListener('click', function (e) {
                                if (!panel.hidden && !panel.contains(e.target) && e.target !== btn) {
                                    panel.hidden = true;
                                }
                            });

                            document.addEventListener('keydown', function (e) {
                                if (e.key === 'Escape' && !panel.hidden) {
                                    panel.hidden = true;
                                    btn.focus();
                                }
                            });

                            search.addEventListener('input', function () {
                                filterVars(this.value.toLowerCase().trim());
                            });

                            function filterVars(q) {
                                var groups  = panel.querySelectorAll('.scm-var-group');
                                var anyVis  = false;
                                groups.forEach(function (group) {
                                    var items    = group.querySelectorAll('.scm-var-item');
                                    var groupVis = false;
                                    items.forEach(function (item) {
                                        var match = !q || item.getAttribute('data-token').toLowerCase().indexOf(q) !== -1;
                                        item.hidden = !match;
                                        if (match) { groupVis = true; anyVis = true; }
                                    });
                                    group.hidden = !groupVis;
                                });
                                if (noRes) noRes.hidden = anyVis || !q;
                            }

                            panel.addEventListener('click', function (e) {
                                var item = e.target.closest('.scm-var-item');
                                if (!item) return;
                                var token  = item.getAttribute('data-token');
                                var prompt = item.getAttribute('data-prompt');
                                if (prompt === 'meta') {
                                    var key = window.prompt('<?php echo esc_js( 'Introduce el nombre del campo personalizado (ej. telefono):' ); ?>', '');
                                    if (key === null || key.trim() === '') return;
                                    token = '{{meta:' + key.trim() + '}}';
                                } else if (prompt === 'term') {
                                    var tax = window.prompt('<?php echo esc_js( 'Introduce el nombre de la taxonomía (ej. category):' ); ?>', '');
                                    if (tax === null || tax.trim() === '') return;
                                    token = '{{term:' + tax.trim() + '}}';
                                }
                                insertAtCursor(ta, token);
                                panel.hidden = true;
                                ta.focus();
                            });

                            function insertAtCursor(el, text) {
                                var start = el.selectionStart;
                                var end   = el.selectionEnd;
                                el.value  = el.value.slice(0, start) + text + el.value.slice(end);
                                var pos   = start + text.length;
                                el.setSelectionRange(pos, pos);
                            }
                        })();
                        </script>
                        <p><button type="button" class="button" id="scm-validate-json">Validar JSON</button> <span id="scm-json-status"></span></p>
                    </td>
                </tr>
                <tr>
                    <th>Vista previa normalizada</th>
                    <td><pre class="scm-preview" id="scm-json-preview"><?php echo esc_html( ! empty( $diagnostics['normalized'] ) ? wp_json_encode( $diagnostics['normalized'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '' ); ?></pre></td>
                </tr>
                <tr>
                    <th>Diagnósticos</th>
                    <td>
                        <div class="scm-diagnostics">
                            <p><strong>Nodos encontrados:</strong> <?php echo esc_html( $diagnostics['node_count'] ); ?></p>
                            <p><strong>Tipos detectados:</strong> <?php echo esc_html( empty( $diagnostics['types'] ) ? '—' : implode( ', ', $diagnostics['types'] ) ); ?></p>
                            <p><strong>Dominios en @id:</strong> <?php echo esc_html( empty( $diagnostics['domains'] ) ? '—' : implode( ', ', $diagnostics['domains'] ) ); ?></p>

                            <?php if ( ! empty( $diagnostics['errors'] ) ) : ?>
                                <p class="scm-severity-label scm-severity-critical">Errores críticos</p>
                                <ul class="scm-warning-list scm-error-list">
                                    <?php foreach ( $diagnostics['errors'] as $error ) : ?>
                                        <li><?php echo esc_html( $error ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if ( ! empty( $diagnostics['structural_warnings'] ) ) : ?>
                                <p class="scm-severity-label scm-severity-structural">Advertencias estructurales</p>
                                <ul class="scm-warning-list scm-structural-warning-list">
                                    <?php foreach ( $diagnostics['structural_warnings'] as $warning ) : ?>
                                        <li><?php echo esc_html( $warning ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if ( ! empty( $diagnostics['semantic_warnings'] ) ) : ?>
                                <p class="scm-severity-label scm-severity-semantic">Advertencias semánticas</p>
                                <ul class="scm-warning-list scm-semantic-warning-list">
                                    <?php foreach ( $diagnostics['semantic_warnings'] as $warning ) : ?>
                                        <li><?php echo esc_html( $warning ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if ( empty( $diagnostics['errors'] ) && empty( $diagnostics['warnings'] ) ) : ?>
                                <p class="scm-ok">No se detectaron problemas en el grafo de este schema.</p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
            <p><button class="button button-primary" name="scm_save_schema" value="1">Guardar schema</button></p>
        </form>
    </div>
    <?php endif; ?>
</div>
