<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap scm-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Schema Rules', 'schema-control-manager' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=scm_rule_edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'schema-control-manager' ); ?></a>
    <hr class="wp-header-end">

    <?php if ( ! empty( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success"><p><?php esc_html_e( 'Rule deleted.', 'schema-control-manager' ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $orphan_count ) ) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'Data integrity warning', 'schema-control-manager' ); ?></strong> &mdash;
                <?php printf(
                    /* translators: %d: number of orphan schemas */
                    esc_html( _n(
                        '%d schema is referencing a rule that no longer exists.',
                        '%d schemas are referencing rules that no longer exist.',
                        $orphan_count,
                        'schema-control-manager'
                    ) ),
                    (int) $orphan_count
                ); ?>
                <?php esc_html_e( 'These schemas cannot be rendered and should be deleted or reassigned.', 'schema-control-manager' ); ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="get" class="scm-filters">
        <input type="hidden" name="page" value="scm_rules">
        <input type="search" name="search" placeholder="<?php esc_attr_e( 'Search rules...', 'schema-control-manager' ); ?>" value="<?php echo esc_attr( $_GET['search'] ?? '' ); ?>">
        <select name="target_type">
            <option value=""><?php esc_html_e( 'All targets', 'schema-control-manager' ); ?></option>
            <?php foreach ( array( 'home' => 'Home', 'exact_url' => 'Exact URL', 'exact_slug' => 'Exact slug', 'author' => 'Author page' ) as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $_GET['target_type'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="is_active">
            <option value=""><?php esc_html_e( 'Any status', 'schema-control-manager' ); ?></option>
            <option value="1" <?php selected( $_GET['is_active'] ?? '', '1' ); ?>><?php esc_html_e( 'Active', 'schema-control-manager' ); ?></option>
            <option value="0" <?php selected( $_GET['is_active'] ?? '', '0' ); ?>><?php esc_html_e( 'Inactive', 'schema-control-manager' ); ?></option>
        </select>
        <button class="button"><?php esc_html_e( 'Filter', 'schema-control-manager' ); ?></button>
    </form>

    <table class="widefat striped scm-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Label', 'schema-control-manager' ); ?></th>
                <th><?php esc_html_e( 'Target', 'schema-control-manager' ); ?></th>
                <th><?php esc_html_e( 'Mode', 'schema-control-manager' ); ?></th>
                <th><?php esc_html_e( 'Replaced types', 'schema-control-manager' ); ?></th>
                <th><?php esc_html_e( 'Priority', 'schema-control-manager' ); ?></th>
                <th><?php esc_html_e( 'Status', 'schema-control-manager' ); ?></th>
                <th><?php esc_html_e( 'Updated', 'schema-control-manager' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'schema-control-manager' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $rules ) ) : ?>
                <tr><td colspan="8"><?php echo ! empty( $search ) ? esc_html__( 'No rules found for your search.', 'schema-control-manager' ) : esc_html__( 'No rules found.', 'schema-control-manager' ); ?></td></tr>
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
                        <td><?php echo ! empty( $rule['is_active'] ) ? '<span class="scm-status active">Active</span>' : '<span class="scm-status inactive">Inactive</span>'; ?></td>
                        <td><?php echo esc_html( $rule['updated_at'] ); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=scm_rule_edit&rule_id=' . (int) $rule['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'schema-control-manager' ); ?></a>
                            <a class="button button-small button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=scm_rules&scm_delete_rule=' . (int) $rule['id'] ), 'scm_delete_rule_' . (int) $rule['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'schema-control-manager' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
