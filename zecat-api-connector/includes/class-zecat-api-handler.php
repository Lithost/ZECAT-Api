<?php
/**
 * Maneja las interacciones con la API de Zecat
 *
 * @package Zecat_API_Connector
 * @subpackage Zecat_API_Connector/includes
 */

class Zecat_API_Handler {

    private $api_url = 'https://api.zecat.cl/generic_product';

    /**
     * Obtiene los productos desde la API y los guarda en la base de datos
     *
     * @return array|WP_Error Resultado de la operación o error
     */
    public function fetch_and_store_products() {
        Zecat_API_Error_Logger::log('Iniciando fetch_and_store_products', 'INFO');

        $total_inserted = 0;
        $total_updated = 0;
        $page = 1;
        $total_pages = 1;

        do {
            $endpoint = $this->api_url . "?page={$page}&limit=50";
            Zecat_API_Error_Logger::log("Solicitando productos a la API - Página {$page}", 'INFO');
            $response = wp_remote_get($endpoint, array(
                'timeout' => 60
            ));

            if (is_wp_error($response)) {
                Zecat_API_Error_Logger::log('Error en la respuesta de la API: ' . $response->get_error_message(), 'ERROR');
                return $response;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!isset($data['generic_products']) || !is_array($data['generic_products'])) {
                Zecat_API_Error_Logger::log('Datos inválidos recibidos de la API: ' . print_r($data, true), 'ERROR');
                return new WP_Error('datos_invalidos', 'Los datos recibidos de la API no son válidos');
            }

            $total_pages = isset($data['total_pages']) ? intval($data['total_pages']) : 1;

            $result = $this->process_products($data['generic_products']);
            $total_inserted += $result['inserted'];
            $total_updated += $result['updated'];

            $page++;
        } while ($page <= $total_pages);

        Zecat_API_Error_Logger::log("Total de productos insertados: $total_inserted, actualizados: $total_updated", 'INFO');

        return array(
            'inserted' => $total_inserted,
            'updated' => $total_updated
        );
    }

    /**
     * Obtiene un producto específico de la API
     *
     * @param string $external_id ID externo del producto
     * @return array|WP_Error Datos del producto o error
     */
    public function get_product($external_id) {
        $endpoint = $this->api_url . '/' . $external_id;
        Zecat_API_Error_Logger::log('Solicitando producto específico a la API: ' . $external_id, 'INFO');
        
        $response = wp_remote_get($endpoint, array(
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            Zecat_API_Error_Logger::log('Error en la respuesta de la API: ' . $response->get_error_message(), 'ERROR');
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['generic_product'])) {
            Zecat_API_Error_Logger::log('Datos inválidos recibidos de la API para el producto ' . $external_id, 'ERROR');
            return new WP_Error('datos_invalidos', 'Los datos recibidos de la API no son válidos');
        }

        return $data['generic_product'];
    }

    private function process_products($products) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'zecat_productos_cache';

        $inserted = 0;
        $updated = 0;

        foreach ($products as $product) {
            Zecat_API_Error_Logger::log('Procesando producto: ' . $product['external_id'], 'DEBUG');

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE external_id = %s",
                $product['external_id']
            ));

            $product_data = array(
                'external_id' => $product['external_id'],
                'name' => $product['name'],
                'description' => $product['description'],
                'price' => $product['price'],
                'currency' => $product['currency'],
                'minimum_order_quantity' => $product['minimum_order_quantity'],
                'unit_weight' => $product['unit_weight'],
                'height' => $product['height'],
                'length' => $product['length'],
                'families' => json_encode($product['families']),
                'images' => json_encode($product['images']),
                'products' => json_encode($product['products']),
                'subattributes' => json_encode($product['subattributes'])
            );

            if ($exists) {
                $result = $wpdb->update($table_name, $product_data, array('external_id' => $product['external_id']));
                if ($result === false) {
                    Zecat_API_Error_Logger::log('Error actualizando producto: ' . $wpdb->last_error, 'ERROR');
                } else {
                    $updated++;
                }
            } else {
                $result = $wpdb->insert($table_name, $product_data);
                if ($result === false) {
                    Zecat_API_Error_Logger::log('Error insertando producto: ' . $wpdb->last_error, 'ERROR');
                } else {
                    $inserted++;
                }
            }
        }

        return array(
            'inserted' => $inserted,
            'updated' => $updated
        );
    }
}

