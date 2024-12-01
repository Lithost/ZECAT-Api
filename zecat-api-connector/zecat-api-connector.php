<?php
/**
 * Plugin Name: Zecat API Connector
 * Description: Conector de API Zecat para WooCommerce
 * Version: 3.7
 * Author: Nicolás Gatica
 * Author URI: https://lithost.cl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * @package Zecat_API_Connector
 */

// Si este archivo es llamado directamente, abortar.
if (!defined('WPINC')) {
    die;
}

// Definir constantes del plugin
define('ZECAT_API_CONNECTOR_VERSION', '3.7');
define('ZECAT_API_CONNECTOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZECAT_API_CONNECTOR_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Código que se ejecuta en la activación del plugin.
 */
function zecat_api_connector_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'zecat_productos_cache';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        external_id VARCHAR(100) NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10, 2) NOT NULL,
        currency VARCHAR(10) NOT NULL,
        minimum_order_quantity INT NOT NULL,
        unit_weight INT NOT NULL,
        height INT NOT NULL,
        length INT NOT NULL,
        families JSON,
        images JSON,
        products JSON,
        subattributes JSON,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY external_id (external_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (!empty($wpdb->last_error)) {
        Zecat_API_Error_Logger::log('Error al crear la tabla de productos cache: ' . $wpdb->last_error, 'ERROR');
    } else {
        Zecat_API_Error_Logger::log('Tabla de productos cache creada o actualizada exitosamente', 'INFO');
    }

    add_option('zecat_api_connector_version', ZECAT_API_CONNECTOR_VERSION);
    Zecat_API_Error_Logger::log('Plugin Zecat API Connector activado (versión ' . ZECAT_API_CONNECTOR_VERSION . ')', 'INFO');
}

/**
 * Código que se ejecuta cuando se desinstala el plugin.
 */
function zecat_api_connector_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'zecat_productos_cache';
    
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    Zecat_API_Error_Logger::log('Tabla de productos cache eliminada', 'INFO');
    
    delete_option('zecat_api_connector_version');
    delete_option('zecat_api_connector_settings');
    Zecat_API_Error_Logger::log('Plugin Zecat API Connector desinstalado', 'INFO');
}

register_activation_hook(__FILE__, 'zecat_api_connector_activate');
register_uninstall_hook(__FILE__, 'zecat_api_connector_uninstall');

/**
 * La función principal para iniciar el plugin.
 */
function run_zecat_api_connector() {
    require_once ZECAT_API_CONNECTOR_PLUGIN_DIR . 'includes/class-zecat-api-handler.php';
    require_once ZECAT_API_CONNECTOR_PLUGIN_DIR . 'includes/class-zecat-product-importer.php';
    require_once ZECAT_API_CONNECTOR_PLUGIN_DIR . 'includes/class-zecat-api-error-logger.php';
    require_once ZECAT_API_CONNECTOR_PLUGIN_DIR . 'admin/class-zecat-api-connector-admin.php';

    $plugin_admin = new Zecat_API_Connector_Admin('zecat-api-connector', ZECAT_API_CONNECTOR_VERSION);
    
    // Inicializar el manejador de API y el importador de productos
    $api_handler = new Zecat_API_Handler();
    $product_importer = new Zecat_Product_Importer();

    // Hooks para el área de administración
    add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_styles_and_scripts'));
    add_action('admin_menu', array($plugin_admin, 'add_plugin_admin_menu'));
    add_action('admin_init', array($plugin_admin, 'register_settings'));
    
    // Agregar hooks para las acciones AJAX
    add_action('wp_ajax_zecat_fetch_products', 'zecat_fetch_products_ajax');
    add_action('wp_ajax_zecat_import_products', 'zecat_import_products_ajax');
}

function zecat_fetch_products_ajax() {
    check_ajax_referer('zecat_api_connector_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        Zecat_API_Error_Logger::log('Intento de acceso no autorizado a zecat_fetch_products_ajax', 'WARNING');
        wp_send_json_error('Permisos insuficientes');
    }

    $api_handler = new Zecat_API_Handler();
    $result = $api_handler->fetch_and_store_products();

    if (is_wp_error($result)) {
        Zecat_API_Error_Logger::log('Error al obtener productos: ' . $result->get_error_message(), 'ERROR');
        wp_send_json_error($result->get_error_message());
    } else {
        Zecat_API_Error_Logger::log('Productos obtenidos exitosamente', 'INFO');
        wp_send_json_success($result);
    }
}

function zecat_import_products_ajax() {
    check_ajax_referer('zecat_api_connector_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        Zecat_API_Error_Logger::log('Intento de acceso no autorizado a zecat_import_products_ajax', 'WARNING');
        wp_send_json_error('Permisos insuficientes');
    }

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

    $product_importer = new Zecat_Product_Importer();
    $result = $product_importer->import_products($offset);

    Zecat_API_Error_Logger::log('Importación de productos completada. Offset: ' . $offset . ', Resultado: ' . print_r($result, true), 'INFO');
    wp_send_json_success($result);
}

function zecat_get_total_products() {
    check_ajax_referer('zecat_api_connector_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        Zecat_API_Error_Logger::log('Intento de acceso no autorizado a zecat_get_total_products', 'WARNING');
        wp_send_json_error('Permisos insuficientes');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'zecat_productos_cache';
    $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    Zecat_API_Error_Logger::log('Total de productos obtenido: ' . $total_products, 'INFO');
    wp_send_json_success(array('total_products' => $total_products));
}
add_action('wp_ajax_zecat_get_total_products', 'zecat_get_total_products');

function zecat_api_connector_register_settings() {
    register_setting(
        'zecat_api_connector_options_group',
        'zecat_api_connector_options',
        'zecat_api_connector_sanitize_options'
    );
}
add_action('admin_init', 'zecat_api_connector_register_settings');

function zecat_api_connector_sanitize_options($input) {
    $valid_fields = array('title', 'description', 'price', 'stock', 'categories', 'brand', 'image');
    
    if (isset($input['zecat_api_omit_fields']) && is_array($input['zecat_api_omit_fields'])) {
        $input['zecat_api_omit_fields'] = array_intersect($input['zecat_api_omit_fields'], $valid_fields);
    } else {
        $input['zecat_api_omit_fields'] = array();
    }
    
    Zecat_API_Error_Logger::log('Opciones del plugin actualizadas: ' . print_r($input, true), 'INFO');
    
    // Añadir mensaje de éxito
    add_settings_error(
        'zecat_api_connector_messages',
        'zecat_api_connector_message',
        __('Configuración guardada exitosamente.', 'zecat-api-connector'),
        'updated'
    );
    
    return $input;
}

// Añadir columna 'Zecat' a la lista de productos
function zecat_add_product_column($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'sku') {
            $new_columns['zecat'] = __('Zecat', 'zecat-api-connector');
        }
    }
    return $new_columns;
}
add_filter('manage_edit-product_columns', 'zecat_add_product_column');

// Rellenar la columna 'Zecat' con el indicador correspondiente
function zecat_product_column_content($column, $product_id) {
    if ($column == 'zecat') {
        $product = wc_get_product($product_id);
        $sku = $product->get_sku();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'zecat_productos_cache';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE external_id = %s",
            $sku
        ));
        
        if ($result > 0) {
            echo '<span class="dashicons dashicons-yes" style="color: green;" title="Producto de Zecat"></span>';
        } else {
            echo '<span class="dashicons dashicons-minus" style="color: gray;" title="No es producto de Zecat"></span>';
        }
    }
}
add_action('manage_product_posts_custom_column', 'zecat_product_column_content', 10, 2);

// Hacer la columna 'Zecat' ordenable
function zecat_product_column_sortable($columns) {
    $columns['zecat'] = 'zecat';
    return $columns;
}
add_filter('manage_edit-product_sortable_columns', 'zecat_product_column_sortable');

// Manejar la ordenación de la columna 'Zecat'
function zecat_product_column_orderby($query) {
    if (!is_admin()) {
        return;
    }

    $orderby = $query->get('orderby');
    if ('zecat' === $orderby) {
        $query->set('meta_key', '_zecat_product');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'zecat_product_column_orderby');

// Añadir un campo meta oculto para facilitar la ordenación
function zecat_add_product_meta($product_id) {
    $product = wc_get_product($product_id);
    $sku = $product->get_sku();
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'zecat_productos_cache';
    
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE external_id = %s",
        $sku
    ));
    
    update_post_meta($product_id, '_zecat_product', $result > 0 ? 'yes' : 'no');
}
add_action('save_post_product', 'zecat_add_product_meta');

/**
 * Iniciar el plugin
 */
run_zecat_api_connector();

