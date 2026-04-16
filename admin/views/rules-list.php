<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap scm-wrap">
    <h1 class="wp-heading-inline">Reglas de schema</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=scm_rule_edit' ) ); ?>" class="page-title-action">Añadir nueva</a>
    <hr class="wp-header-end">

    <?php if ( ! empty( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success"><p>Regla eliminada.</p></div>
    <?php endif; ?>
    <?php if ( ! empty( $_GET['duplicated'] ) ) : ?>
        <div class="notice notice-success"><p>Regla duplicada. La copia está inactiva — revísala y actívala cuando esté lista.</p></div>
    <?php endif; ?>

    <?php if ( ! empty( $orphan_count ) ) : ?>
        <div class="notice notice-warning">
            <p>
                <strong>Advertencia de integridad de datos</strong> &mdash;
                <?php printf(
                    esc_html( _n(
                        '%d schema referencia una regla que ya no existe.',
                        '%d schemas referencian reglas que ya no existen.',
                        $orphan_count,
                        'schema-control-manager'
                    ) ),
                    (int) $orphan_count
                ); ?>
                Estos schemas no pueden renderizarse y deben eliminarse o reasignarse.
            </p>
        </div>
    <?php endif; ?>

    <form method="get" class="scm-filters">
        <input type="hidden" name="page" value="scm_rules">
        <input type="search" name="search" placeholder="Buscar reglas..." value="<?php echo esc_attr( $_GET['search'] ?? '' ); ?>">
        <select name="target_type">
            <option value="">Todos los objetivos</option>
            <?php foreach ( array(
                'home'               => 'Inicio',
                'front_page'         => 'Portada',
                'exact_url'          => 'URL exacta',
                'exact_slug'         => 'Slug exacto',
                'post_type'          => 'Tipo de entrada',
                'post_type_archive'  => 'Archivo de tipo de entrada',
                'category'           => 'Archivo de categoría',
                'tag'                => 'Archivo de etiqueta',
                'taxonomy_term'      => 'Término de taxonomía',
                'author'             => 'Página de autor',
            ) as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $_GET['target_type'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="is_active">
            <option value="">Cualquier estado</option>
            <option value="1" <?php selected( $_GET['is_active'] ?? '', '1' ); ?>>Activo</option>
            <option value="0" <?php selected( $_GET['is_active'] ?? '', '0' ); ?>>Inactivo</option>
        </select>
        <button class="button">Filtrar</button>
    </form>

    <table class="widefat striped scm-table">
        <thead>
            <tr>
                <th>Etiqueta</th>
                <th>Objetivo</th>
                <th>Modo</th>
                <th>Tipos reemplazados</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Actualizado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $rules ) ) : ?>
                <tr><td colspan="8"><?php echo ! empty( $search ) ? 'No se encontraron reglas para tu búsqueda.' : 'No se encontraron reglas.'; ?></td></tr>
            <?php else : ?>
                <?php foreach ( $rules as $rule ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $rule['label'] ); ?></strong></td>
                        <td>
                            <div><?php echo esc_html( $rule['target_type'] ); ?></div>
                            <code><?php echo esc_html( $rule['target_value'] ); ?></code>
                        </td>
                        <td><?php echo esc_html( $rule['mode'] ); ?></td>
                        <td><?php echo esc_html( implode( ', ', json_decode( $rule['replaced_types'], true ) ?: array() ) ); ?></td>
                        <td><?php echo esc_html( $rule['priority'] ?? 100 ); ?></td>
                        <td><?php echo ! empty( $rule['is_active'] ) ? '<span class="scm-status active">Activo</span>' : '<span class="scm-status inactive">Inactivo</span>'; ?></td>
                        <td><?php echo esc_html( $rule['updated_at'] ); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=scm_rule_edit&rule_id=' . (int) $rule['id'] ) ); ?>">Editar</a>
                            <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=scm_rules&action=duplicate_rule&rule_id=' . (int) $rule['id'] ), 'scm_duplicate_rule_' . (int) $rule['id'] ) ); ?>">Duplicar</a>
                            <a class="button button-small button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=scm_rules&scm_delete_rule=' . (int) $rule['id'] ), 'scm_delete_rule_' . (int) $rule['id'] ) ); ?>">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
