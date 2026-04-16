<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap scm-wrap">
    <h1>Configuración</h1>
    <?php if ( ! empty( $_GET['updated'] ) ) : ?><div class="notice notice-success"><p>Configuración guardada.</p></div><?php endif; ?>
    <div class="scm-card scm-card-full">
        <form method="post">
            <?php wp_nonce_field( 'scm_save_settings' ); ?>
            <table class="form-table">
                <tr><th>Integración con AIOSEO</th><td><label><input type="checkbox" name="aioseo_integration_enabled" value="1" <?php checked( ! empty( $settings['aioseo_integration_enabled'] ) ); ?>> Activado</label></td></tr>
                <tr><th>JSON con formato legible</th><td><label><input type="checkbox" name="pretty_print_json" value="1" <?php checked( ! empty( $settings['pretty_print_json'] ) ); ?>> Activado</label></td></tr>
                <tr><th>Eliminar valores vacíos</th><td><label><input type="checkbox" name="strip_empty_values" value="1" <?php checked( ! empty( $settings['strip_empty_values'] ) ); ?>> Activado</label></td></tr>
                <tr><th>Añadir @context automáticamente</th><td><label><input type="checkbox" name="auto_add_context" value="1" <?php checked( ! empty( $settings['auto_add_context'] ) ); ?>> Activado</label><p class="description">Permite introducir nodos parciales y normaliza siempre la salida a JSON-LD.</p></td></tr>
                <tr><th>Envolver con @graph automáticamente</th><td><label><input type="checkbox" name="auto_wrap_graph" value="1" <?php checked( ! empty( $settings['auto_wrap_graph'] ) ); ?>> Activado</label></td></tr>
                <tr><th>Advertir si un nodo estructural no tiene @id</th><td><label><input type="checkbox" name="warn_on_structural_without_id" value="1" <?php checked( ! empty( $settings['warn_on_structural_without_id'] ) ); ?>> Activado</label></td></tr>
                <tr><th>Activar diagnósticos del grafo</th><td><label><input type="checkbox" name="enable_graph_diagnostics" value="1" <?php checked( ! empty( $settings['enable_graph_diagnostics'] ) ); ?>> Activado</label></td></tr>
                <tr><th>Modo debug</th><td><label><input type="checkbox" name="debug_mode" value="1" <?php checked( ! empty( $settings['debug_mode'] ) ); ?>> Activado</label></td></tr>
                <tr>
                    <th>Eliminar datos al desinstalar</th>
                    <td>
                        <label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>> Activado</label>
                        <p class="description">Si está marcado, todas las reglas, schemas y configuraciones se eliminan permanentemente al desinstalar el plugin. Desactivado por defecto para conservar los datos.</p>
                    </td>
                </tr>
                <tr><th><label for="conflict_types_default">Tipos en conflicto</label></th><td><input class="large-text" type="text" id="conflict_types_default" name="conflict_types_default" value="<?php echo esc_attr( implode( ', ', (array) ( $settings['conflict_types_default'] ?? array() ) ) ); ?>"><p class="description">Lista separada por comas usada en el editor de reglas.</p></td></tr>
                <tr>
                    <th><label for="preview_language">Idioma de la vista previa</label></th>
                    <td>
                        <select name="preview_language" id="preview_language">
                            <option value="en" <?php selected( ( $settings['preview_language'] ?? 'es' ), 'en' ); ?>>English</option>
                            <option value="es" <?php selected( ( $settings['preview_language'] ?? 'es' ), 'es' ); ?>>Español</option>
                        </select>
                        <p class="description">Idioma de las etiquetas del panel de vista previa del grafo final en el editor de reglas.</p>
                    </td>
                </tr>
            </table>
            <p><button class="button button-primary" name="scm_save_settings" value="1">Guardar configuración</button></p>
        </form>
    </div>
</div>
