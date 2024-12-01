<?php
/**
 * Maneja la importación de productos a WooCommerce
 *
 * @package Zecat_API_Connector
 * @subpackage Zecat_API_Connector/includes
 */

class Zecat_Product_Importer {
    private $batch_size = 10;
    private $omit_fields = array();
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'zecat_productos_cache';
        
        $options = get_option('zecat_api_connector_options', array());
        $this->omit_fields = isset($options['zecat_api_omit_fields']) ? (array)$options['zecat_api_omit_fields'] : array();
        
        Zecat_API_Error_Logger::log("Zecat_Product_Importer inicializado", 'INFO');
    }

    public function import_products($offset = 0) {
        Zecat_API_Error_Logger::log("Iniciando importación de productos desde el offset: $offset", 'INFO');
        $products = $this->get_products_batch($offset);
        
        $results = array(
            'imported' => 0,
            'updated' => 0,
            'errors' => array()
        );

        foreach ($products as $product) {
            try {
                $action = $this->import_single_product($product);
                $results[$action]++;
            } catch (Exception $e) {
                $error_message = "Error al importar el producto {$product->external_id}: " . $e->getMessage();
                Zecat_API_Error_Logger::log($error_message, 'ERROR');
                $results['errors'][] = $error_message;
            }
        }

        $total_products = $this->get_total_products();
        $is_complete = ($offset + $this->batch_size) >= $total_products;

        Zecat_API_Error_Logger::log("Importación completada. Resultados: " . print_r($results, true), 'INFO');

        return array_merge($results, array(
            'is_complete' => $is_complete,
            'next_offset' => $offset + $this->batch_size,
            'total_products' => $total_products
        ));
    }

    private function get_products_batch($offset) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} LIMIT %d OFFSET %d",
            $this->batch_size,
            $offset
        );
        Zecat_API_Error_Logger::log("Obteniendo lote de productos. Query: $query", 'DEBUG');
        return $this->wpdb->get_results($query);
    }

    private function get_total_products() {
        return $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    private function import_single_product($product_data) {
        $this->log_product_data($product_data);
        Zecat_API_Error_Logger::log("Importando producto: {$product_data->external_id}", 'DEBUG');
        
        $existing_product_id = wc_get_product_id_by_sku($product_data->external_id);

        if ($existing_product_id) {
            $product = wc_get_product($existing_product_id);
            $action = 'updated';
            $this->omit_fields = array_merge($this->omit_fields, ['categories']);
            Zecat_API_Error_Logger::log("Actualizando producto existente: {$product_data->external_id}", 'DEBUG');
        } else {
            // Determinar si el producto es simple o variable
            $is_variable = $this->is_variable_product($product_data);
            
            if ($is_variable) {
                $product = new WC_Product_Variable();
            } else {
                $product = new WC_Product_Simple();
            }
            
            $action = 'imported';
            Zecat_API_Error_Logger::log("Creando nuevo producto " . ($is_variable ? "variable" : "simple") . ": {$product_data->external_id}", 'DEBUG');
        }

        $this->update_product_fields($product, $product_data);
        $product->save();

        if ($product->is_type('variable')) {
            $this->create_variations($product, $product_data);
        }

        Zecat_API_Error_Logger::log("Producto {$product_data->external_id} $action exitosamente", 'INFO');
        return $action;
    }

    private function update_product_fields($product, $product_data) {
        if (!in_array('title', $this->omit_fields)) {
            $product->set_name($product_data->name);
        }
        if (!in_array('description', $this->omit_fields)) {
            $product->set_description($product_data->description);
        }
        if (!in_array('categories', $this->omit_fields)) {
            $this->set_category_ids($product, $product_data);
        }
        if (!in_array('image', $this->omit_fields)) {
            $this->update_images($product, $product_data);
        }

        $product->set_sku($product_data->external_id);

        if (!in_array('price', $this->omit_fields) && isset($product_data->price)) {
            $price = floatval($product_data->price);
            $product->set_regular_price($price);
            $product->set_price($price);
            Zecat_API_Error_Logger::log("Precio establecido para producto: {$product_data->external_id}, Precio: $price", 'DEBUG');
        }

        if ($product->is_type('simple')) {
            $variants = json_decode($product_data->products, true);
            if (!empty($variants)) {
                $variant = reset($variants);
                if (!in_array('stock', $this->omit_fields)) {
                    $product->set_stock_quantity($variant['stock']);
                    $product->set_manage_stock(true);
                }
            }
        } else if ($product->is_type('variable')) {
            $variants = json_decode($product_data->products, true);
            if (!empty($variants)) {
                $attributes = $this->create_attributes($variants);
                $product->set_attributes($attributes);
                
                if (!in_array('price', $this->omit_fields) && isset($product_data->price)) {
                    $price = floatval($product_data->price);
                    $product->set_regular_price($price);
                    $product->set_price($price);
                    Zecat_API_Error_Logger::log("Precio base establecido para producto variable: {$product_data->external_id}, Precio: $price", 'DEBUG');
                }
            }
        }
    }

    private function create_variations($product, $product_data) {
        $price = floatval($product_data->price);
        $variations = json_decode($product_data->products, true);
        
        if (!empty($variations)) {
            foreach ($variations as $variation) {
                $variation_id = $this->create_or_update_variation($product, $variation, $price);
                if ($variation_id) {
                    $this->update_variation_fields($variation_id, $variation, $price);
                }
            }
            $product->save(); // Guardar el producto después de crear todas las variaciones
        }
    }

    private function create_attributes($variations) {
        $attributes = array();
        $attribute_names = array('pa_color', 'pa_color2');
        $attribute_labels = array('Color', 'Color 2');

        foreach ($attribute_names as $index => $attribute_name) {
            $attribute_id = wc_attribute_taxonomy_id_by_name(str_replace('pa_', '', $attribute_name));
            
            if (!$attribute_id) {
                $args = array(
                    'name' => $attribute_labels[$index],
                    'slug' => str_replace('pa_', '', $attribute_name),
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => false,
                );
                $attribute_id = wc_create_attribute($args);
                
                if (!is_wp_error($attribute_id)) {
                    $taxonomy_name = wc_attribute_taxonomy_name(str_replace('pa_', '', $attribute_name));
                    register_taxonomy(
                        $taxonomy_name,
                        apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, array('product')),
                        apply_filters('woocommerce_taxonomy_args_' . $taxonomy_name, array(
                            'labels' => array(
                                'name' => $attribute_labels[$index],
                            ),
                            'hierarchical' => false,
                            'show_ui' => true,
                            'query_var' => true,
                            'rewrite' => false,
                        ))
                    );
                }
                Zecat_API_Error_Logger::log("Atributo global '{$attribute_labels[$index]}' creado con ID: $attribute_id", 'INFO');
            }

            $values = array();
            foreach ($variations as $variation) {
                $element_description = $index === 0 ? 'element_description_1' : 'element_description_2';
                if (!empty($variation[$element_description])) {
                    $values[] = $variation[$element_description];
                }
            }
            $values = array_unique(array_filter($values));

            $term_slugs = array();
            foreach ($values as $term) {
                $term_info = term_exists($term, $attribute_name);
                if (!$term_info) {
                    $term_info = wp_insert_term($term, $attribute_name);
                    Zecat_API_Error_Logger::log("Término '$term' agregado al atributo {$attribute_labels[$index]}", 'DEBUG');
                }
                if (!is_wp_error($term_info)) {
                    $term_slugs[] = sanitize_title($term);
                }
            }

            $attribute = new WC_Product_Attribute();
            $attribute->set_id(wc_attribute_taxonomy_id_by_name(str_replace('pa_', '', $attribute_name)));
            $attribute->set_name($attribute_name);
            $attribute->set_options($term_slugs);
            $attribute->set_visible(true);
            $attribute->set_variation(true);

            $attributes[] = $attribute;
        }

        return $attributes;
    }


    private function create_or_update_variation($product, $variation_data, $price) {
        if (empty($variation_data['element_description_1']) && empty($variation_data['element_description_2'])) {
            Zecat_API_Error_Logger::log("Saltando variación sin colores: {$variation_data['id']}", 'DEBUG');
            return false;
        }
        $variation_id = wc_get_product_id_by_sku($variation_data['id']);
        if ($variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation && $variation->is_type('variation')) {
                Zecat_API_Error_Logger::log("Actualizando variación existente con SKU: {$variation_data['id']}", 'DEBUG');
            } else {
                $variation = new WC_Product_Variation();
            }
        } else {
            $variation = new WC_Product_Variation();
        }
    
        $variation->set_parent_id($product->get_id());
    
        $attributes = array();
        $color_attributes = array(
            'pa_color' => 'element_description_1',
            'pa_color2' => 'element_description_2'
        );

        foreach ($color_attributes as $attribute_name => $element_description) {
            if (!empty($variation_data[$element_description])) {
                $color_value = $variation_data[$element_description];
                $color_slug = sanitize_title($color_value);
                
                $term = get_term_by('slug', $color_slug, $attribute_name);
                if (!$term) {
                    $term_result = wp_insert_term($color_value, $attribute_name);
                    if (!is_wp_error($term_result)) {
                        $term = get_term($term_result['term_id'], $attribute_name);
                        Zecat_API_Error_Logger::log("Nuevo término de color creado para $attribute_name: $color_value", 'DEBUG');
                    }
                }
            
                if ($term && !is_wp_error($term)) {
                    $attributes[$attribute_name] = $term->slug;
                    Zecat_API_Error_Logger::log("$attribute_name asignado a variación {$variation_data['id']}: {$color_value}", 'DEBUG');
                }
            }
        }
    
        $variation->set_attributes($attributes);
        $variation->set_sku($variation_data['id']);
        $variation->set_description($variation_data['general_description']);

        if (!in_array('price', $this->omit_fields)) {
            $variation->set_regular_price($price);
            $variation->set_price($price);
            Zecat_API_Error_Logger::log("Precio establecido para variación: {$variation_data['id']}, Precio: $price", 'DEBUG');
        }

        if (!in_array('stock', $this->omit_fields)) {
            $stock = $variation_data['stock'];
            $variation->set_stock_quantity($stock);
            $variation->set_manage_stock(true);
            Zecat_API_Error_Logger::log("Stock establecido para variación: {$variation_data['id']}, Stock: $stock", 'DEBUG');
        }

        $variation->save();
        return $variation->get_id();
    }

    private function update_variation_fields($variation_id, $variation_data, $price) {
        $variation = wc_get_product($variation_id);

        $variation->set_description($variation_data['general_description']);

        if (!in_array('price', $this->omit_fields)) {
            $variation->set_regular_price($price);
            $variation->set_price($price);
            Zecat_API_Error_Logger::log("Precio actualizado para variación: {$variation_data['id']}, Precio: $price", 'DEBUG');
        }

        if (!in_array('stock', $this->omit_fields)) {
            $stock = $variation_data['stock'];
            $variation->set_stock_quantity($stock);
            $variation->set_manage_stock(true);
            Zecat_API_Error_Logger::log("Stock actualizado para variación: {$variation_data['id']}, Stock: $stock", 'DEBUG');
        }

        $attributes = array();
        $color_attributes = array(
            'pa_color' => 'element_description_1',
            'pa_color2' => 'element_description_2'
        );

        foreach ($color_attributes as $attribute_name => $element_description) {
            if (!empty($variation_data[$element_description])) {
                $color_value = $variation_data[$element_description];
                $color_slug = sanitize_title($color_value);
                
                $term = get_term_by('slug', $color_slug, $attribute_name);
                if (!$term) {
                    $term_result = wp_insert_term($color_value, $attribute_name);
                    if (!is_wp_error($term_result)) {
                        $term = get_term($term_result['term_id'], $attribute_name);
                        Zecat_API_Error_Logger::log("Nuevo término de color creado para $attribute_name: $color_value", 'DEBUG');
                    }
                }
            
                if ($term && !is_wp_error($term)) {
                    $attributes[$attribute_name] = $term->slug;
                    Zecat_API_Error_Logger::log("$attribute_name actualizado para variación {$variation_data['id']}: {$color_value}", 'DEBUG');
                }
            }
        }

        $variation->set_attributes($attributes);
        $variation->set_sku($variation_data['id']);

        $variation->save();
    }

    private function set_category_ids($product, $product_data) {
        $families = json_decode($product_data->families, true);
        $category_ids = array();

        foreach ($families as $family) {
            $category_name = trim($family['description']);
            $term = term_exists($category_name, 'product_cat');

            if (!$term) {
                $term = wp_insert_term($category_name, 'product_cat');
                if (is_wp_error($term)) {
                    Zecat_API_Error_Logger::log("Error al crear la categoría '$category_name': " . $term->get_error_message(), 'ERROR');
                } else {
                    Zecat_API_Error_Logger::log("Nueva categoría creada: $category_name (ID: {$term['term_id']})", 'INFO');
                }
            }

            if (!is_wp_error($term)) {
                $category_ids[] = $term['term_id'];
            }
        }

        $product->set_category_ids($category_ids);
        Zecat_API_Error_Logger::log("Categorías actualizadas para el producto: {$product_data->external_id}", 'DEBUG');
    }

    private function update_images($product, $product_data) {
        // Verificar si el producto ya tiene una imagen principal
        if ($product->get_image_id()) {
            Zecat_API_Error_Logger::log("El producto {$product_data->external_id} ya tiene una imagen principal. Omitiendo importación de imagen.", 'INFO');
            return;
        }

        $images = json_decode($product_data->images, true);
        if (!empty($images)) {
            $image = reset($images); // Obtener la primera imagen
            $image_id = $this->import_image($image['image_url'], $product_data->name);
            if ($image_id) {
                $product->set_image_id($image_id);
                Zecat_API_Error_Logger::log("Imagen principal actualizada para el producto: {$product_data->external_id}", 'DEBUG');
            } else {
                Zecat_API_Error_Logger::log("No se pudo importar la imagen para el producto: {$product_data->external_id}", 'WARNING');
            }
        }
    }

    private function import_image($image_url, $product_name) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        if (empty($image_url)) {
            Zecat_API_Error_Logger::log('URL de imagen vacía para el producto: ' . $product_name, 'ERROR');
            return false;
        }

        Zecat_API_Error_Logger::log('Intentando importar imagen desde URL: ' . $image_url, 'DEBUG');

        // Descargar la imagen
        $temp_file = download_url($image_url);

        if (is_wp_error($temp_file)) {
            Zecat_API_Error_Logger::log('Error al descargar la imagen: ' . $temp_file->get_error_message(), 'ERROR');
            return false;
        }

        // Preparar el archivo para la importación
        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $temp_file
        );

        // Comprobar el tipo de archivo
        $file_type = wp_check_filetype_and_ext($temp_file, $file_array['name']);
        if (!$file_type['type']) {
            unlink($temp_file);
            Zecat_API_Error_Logger::log('Tipo de archivo no válido para la imagen: ' . $image_url, 'ERROR');
            return false;
        }

        // Mover el archivo temporal a la carpeta de uploads
        $file = wp_handle_sideload($file_array, array('test_form' => false));

        if (isset($file['error'])) {
            Zecat_API_Error_Logger::log('Error al mover la imagen: ' . $file['error'], 'ERROR');
            return false;
        }

        // Crear el objeto de adjunto
        $attachment = array(
            'post_mime_type' => $file['type'],
            'post_title' => sanitize_file_name($file_array['name']),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Insertar el adjunto en la biblioteca de medios
        $attach_id = wp_insert_attachment($attachment, $file['file']);

        if (is_wp_error($attach_id)) {
            Zecat_API_Error_Logger::log('Error al insertar la imagen en la biblioteca de medios: ' . $attach_id->get_error_message(), 'ERROR');
            return false;
        }

        // Generar metadatos para el adjunto
        $attach_data = wp_generate_attachment_metadata($attach_id, $file['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        Zecat_API_Error_Logger::log('Imagen importada exitosamente. ID: ' . $attach_id, 'INFO');
        return $attach_id;
    }

    private function log_product_data($product_data) {
        $log_message = "Datos del producto:\n";
        $log_message .= "ID Externo: " . $product_data->external_id . "\n";
        $log_message .= "Nombre: " . $product_data->name . "\n";
        Zecat_API_Error_Logger::log($log_message, 'DEBUG');
    }

    private function is_variable_product($product_data) {
        $variants = json_decode($product_data->products, true);
        return !empty($variants) && count($variants) > 1;
    }
}

