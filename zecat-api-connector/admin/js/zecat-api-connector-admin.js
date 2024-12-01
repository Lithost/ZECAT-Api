(function($) {
    'use strict';

    $(function() {
        function updateSyncStatus(message, isError = false) {
            const statusElement = $('#zecat-sync-status');
            statusElement.html(`<p class="text-sm ${isError ? 'text-red-500' : 'text-gray-500'}">${message}</p>`);
        }

        function handleAjaxError(jqXHR, textStatus, errorThrown, errorMessage) {
            NProgress.done();
            console.error('AJAX error:', textStatus, errorThrown);
            updateSyncStatus(errorMessage + errorThrown, true);
        }

        $('#zecat-update-products').on('click', function() {
            NProgress.start();
            updateSyncStatus('Actualizando productos de la base de datos...');

            $.ajax({
                url: zecatApiConnector.ajaxurl,
                type: 'POST',
                data: {
                    action: 'zecat_fetch_products',
                    nonce: zecatApiConnector.nonce
                },
                success: function(response) {
                    NProgress.done();
                    if (response.success) {
                        updateSyncStatus(`Productos obtenidos correctamente. Insertados: ${response.data.inserted}, Actualizados: ${response.data.updated}`);
                    } else {
                        updateSyncStatus('Error al obtener productos: ' + response.data, true);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    handleAjaxError(jqXHR, textStatus, errorThrown, 'Error de conexión al obtener productos: ');
                }
            });
        });
        
        function importProductsBatch(offset = 0, totalImported = 0, totalUpdated = 0) {
            $.ajax({
                url: zecatApiConnector.ajaxurl,
                type: 'POST',
                data: {
                    action: 'zecat_import_products',
                    nonce: zecatApiConnector.nonce,
                    offset: offset
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        totalImported += data.imported;
                        totalUpdated += data.updated;
                        const progress = ((offset + data.imported + data.updated) / data.total_products) * 100;
        
                        updateSyncStatus(`Progreso: ${progress.toFixed(2)}% - Importados: ${totalImported}, Actualizados: ${totalUpdated}`);
                        NProgress.set(progress / 100);
        
                        if (!data.is_complete) {
                            setTimeout(() => importProductsBatch(data.next_offset, totalImported, totalUpdated), 1000);
                        } else {
                            NProgress.done();
                            updateSyncStatus(`Importación completa. Importados: ${totalImported}, Actualizados: ${totalUpdated}`);
                        }
                    } else {
                        NProgress.done();
                        updateSyncStatus('Error al importar productos: ' + response.data, true);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    handleAjaxError(jqXHR, textStatus, errorThrown, 'Error de conexión al importar productos: ');
                }
            });
        }

        $('#zecat-import-products').on('click', function() {
            NProgress.start();
            updateSyncStatus('Iniciando importación de productos...');
            importProductsBatch();
        });

        $('#zecat-api-settings').on('submit', function(e) {
            e.preventDefault();
            var omitFields = $('input[name="zecat_api_connector_options[zecat_api_omit_fields][]"]:checked').map(function() {
                return this.value;
            }).get();

            $.ajax({
                url: zecatApiConnector.ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_zecat_api_settings',
                    nonce: zecatApiConnector.nonce,
                    omit_fields: omitFields
                },
                success: function(response) {
                    if (response.success) {
                        alert('Configuración guardada correctamente');
                    } else {
                        alert('Error al guardar la configuración');
                    }
                },
                error: function() {
                    alert('Error de conexión');
                }
            });
        });
    });
})(jQuery);