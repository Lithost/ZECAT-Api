<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php
    // Show settings errors and success messages
    settings_errors('zecat_api_connector_messages');
    ?>

    <form method="post" action="options.php">
        <?php
        settings_fields('zecat_api_connector_options_group');
        do_settings_sections('zecat_api_connector_options_group');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Campos a omitir en la actualización/importación', 'zecat-api-connector'); ?></th>
                <td>
                    <?php
                    $options = get_option('zecat_api_connector_options', array());
                    $omit_fields = isset($options['zecat_api_omit_fields']) ? (array)$options['zecat_api_omit_fields'] : array();
                    $fields = array(
                        'title' => __('Nombre', 'zecat-api-connector'),
                        'description' => __('Descripción', 'zecat-api-connector'),
                        'price' => __('Precio', 'zecat-api-connector'),
                        'stock' => __('Stock', 'zecat-api-connector'),
                        'categories' => __('Categorías', 'zecat-api-connector'),
                        'packing' => __('Empaquetado', 'zecat-api-connector'),
                        'variants' => __('Variantes', 'zecat-api-connector'),
                        'image' => __('Imagen', 'zecat-api-connector')
                    );
                    foreach ($fields as $field_key => $field_label) {
                        $checked = in_array($field_key, $omit_fields) ? 'checked' : '';
                        echo '<label><input type="checkbox" name="zecat_api_connector_options[zecat_api_omit_fields][]" value="' . esc_attr($field_key) . '" ' . $checked . '> ' . esc_html($field_label) . '</label><br>';
                    }
                    ?>
                </td>
            </tr>
        </table>
        <?php 
        submit_button(__('Guardar cambios', 'zecat-api-connector'));
        ?>
    </form>
</div>

