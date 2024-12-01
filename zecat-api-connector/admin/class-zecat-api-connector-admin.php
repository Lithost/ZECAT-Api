<?php
/**
 * La clase de administración del plugin.
 *
 * @package Zecat_API_Connector
 * @subpackage Zecat_API_Connector/admin
 */

class Zecat_API_Connector_Admin {

    /**
     * El ID de este plugin.
     *
     * @var string $plugin_name
     */
    private $plugin_name;

    /**
     * La versión del plugin.
     *
     * @var string $version
     */
    private $version;

    /**
     * Inicializa la clase y establece sus propiedades.
     *
     * @param string $plugin_name El nombre del plugin.
     * @param string $version La versión del plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles_and_scripts'));
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
    }

    /**
     * Registra los estilos y scripts del área de administración.
     *
     * @param string $hook_suffix El sufijo del hook de la página actual.
     */
    public function enqueue_styles_and_scripts($hook_suffix) {
        // Comprueba si estamos en la página de nuestro plugin
        if (strpos($hook_suffix, $this->plugin_name) === false) {
            return;
        }
    
        // Tailwind CSS (desde CDN para este ejemplo, considera compilar para producción)
        wp_enqueue_style('tailwindcss', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', array(), '2.2.19', 'all');
    
        // SweetAlert2
        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.0', true);
    
        // NProgress
        wp_enqueue_style('nprogress', 'https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.css', array(), '0.2.0', 'all');
        wp_enqueue_script('nprogress', 'https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.js', array(), '0.2.0', true);
    
        // Estilos y scripts personalizados del plugin
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/zecat-api-connector-admin.css', array(), $this->version, 'all');
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/zecat-api-connector-admin.js', array('jquery', 'sweetalert2', 'nprogress'), $this->version, true);
    
        // Localizar el script
        wp_localize_script($this->plugin_name, 'zecatApiConnector', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zecat_api_connector_nonce'),
            'connectionError' => __('Error de conexión', 'zecat-api-connector')
        ));
    }

    /**
     * Agrega las páginas de menú del plugin al área de administración.
     */
    public function add_plugin_admin_menu() {
        // Página principal del plugin
        add_menu_page(
            'Zecat API Connector',
            'Zecat API Connector',
            'manage_options',
            'zecat-api-connector',
            array($this, 'display_plugin_admin_page'),
            'dashicons-update',
            6
        );
    
        // Submenú para la página principal (para mantener consistencia)
        add_submenu_page(
            'zecat-api-connector',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'zecat-api-connector',
            array($this, 'display_plugin_admin_page')
        );
    
        // Submenú para la página de configuración
        add_submenu_page(
            'zecat-api-connector',
            'Configuración',
            'Configuración',
            'manage_options',
            'zecat-api-connector-settings',
            array($this, 'display_plugin_settings_page')
        );
    
        // Submenú para el registro de errores
        add_submenu_page(
            'zecat-api-connector',
            'Registro de Errores',
            'Registro de Errores',
            'manage_options',
            'zecat-api-connector-errors',
            array($this, 'display_error_log_page')
        );
    }

    /**
     * Renderiza la página principal de configuración del plugin.
     */
    public function display_plugin_settings_page() {
        // Asegúrate de que solo los usuarios autorizados accedan a esta página
        if (!current_user_can('manage_options')) {
            return;
        }
    
        // Guarda los cambios en las opciones cuando se envía el formulario
        if (isset($_POST['zecat_api_connector_options'])) {
            check_admin_referer('zecat_api_connector_options');
            $options = array(
                'zecat_api_omit_fields' => isset($_POST['zecat_api_connector_options']['zecat_api_omit_fields']) ? array_map('sanitize_text_field', $_POST['zecat_api_connector_options']['zecat_api_omit_fields']) : array(),
            );
            update_option('zecat_api_connector_options', $options);
            add_settings_error('zecat_api_connector_messages', 'zecat_api_connector_message', __('Configuración guardada exitosamente.', 'zecat-api-connector'), 'updated');
        }
    
        // Obtén las opciones actuales
        $options = get_option('zecat_api_connector_options', array(
            'zecat_api_omit_fields' => array(),
        ));
    
        // Muestra el formulario de configuración
        include(plugin_dir_path(__FILE__) . 'partials/zecat-api-connector-admin-settings-display.php');
    }

    public function display_error_log_page() {
        // Asegúrate de que solo los usuarios autorizados accedan a esta página
        if (!current_user_can('manage_options')) {
            Zecat_API_Error_Logger::log("Unauthorized access attempt to error log page", 'WARNING');
            return;
        }
    
        $recent_errors = Zecat_API_Error_Logger::get_recent_errors(20);
        Zecat_API_Error_Logger::log("Displaying error log page", 'DEBUG');
        include(plugin_dir_path(__FILE__) . 'partials/zecat-api-connector-admin-error-log-display.php');
    }
    
    public function register_settings() {
        register_setting('zecat_api_connector_options', 'zecat_api_connector_options');
    }
    
    public function display_plugin_admin_page() {
        // Asegúrate de que solo los usuarios autorizados accedan a esta página
        if (!current_user_can('manage_options')) {
            return;
        }
    
        // Incluye el archivo de la vista principal del plugin
        include(plugin_dir_path(__FILE__) . 'partials/zecat-api-connector-admin-display.php');
    }
}