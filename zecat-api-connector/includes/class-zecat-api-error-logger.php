<?php
/**
 * Clase para manejar el registro de errores del plugin Zecat API Connector.
 *
 * @package Zecat_API_Connector
 * @subpackage Zecat_API_Connector/includes
 */

class Zecat_API_Error_Logger {
    private static $log_file;

    /**
     * Inicializa el logger.
     */
    public static function init() {
        self::$log_file = WP_CONTENT_DIR . '/zecat-api-connector-error.log';
        if (!file_exists(dirname(self::$log_file))) {
            mkdir(dirname(self::$log_file), 0755, true);
        }
    }

    /**
     * Registra un mensaje en el archivo de registro.
     *
     * @param string $message El mensaje a registrar.
     * @param string $type El tipo de mensaje (ERROR, WARNING, INFO, DEBUG).
     */
    public static function log($message, $type = 'ERROR') {
        if (!self::$log_file) {
            self::init();
        }

        $timestamp = current_time('mysql');
        $log_entry = sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($type), $message);

        error_log($log_entry, 3, self::$log_file);
    }

    /**
     * Obtiene los últimos mensajes registrados.
     *
     * @param int $limit Número de mensajes a obtener.
     * @return array Array de mensajes.
     */
    public static function get_recent_errors($limit = 10) {
        if (!self::$log_file) {
            self::init();
        }

        if (!file_exists(self::$log_file)) {
            return array();
        }

        $lines = file(self::$log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse($lines);
        return array_slice($lines, 0, $limit);
    }
}

// Inicializar el logger
add_action('plugins_loaded', array('Zecat_API_Error_Logger', 'init'));