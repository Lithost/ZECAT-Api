<?php
/**
 * Proporciona una vista de área de administración para el plugin
 *
 * @package Zecat_API_Connector
 * @subpackage Zecat_API_Connector/admin/partials
 */
?>

<div class="wrap">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8"><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="bg-white overflow-hidden shadow rounded-lg divide-y divide-gray-200 mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recomendaciones Importantes</h2>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-600">
                    <li>No recargue la página durante la importación.</li>
                    <li>Mantenga esta página abierta hasta que el proceso se complete.</li>
                    <li>Evite realizar cambios en productos o categorías durante la importación.</li>
                    <li>Asegúrese de tener una conexión a internet estable.</li>
                </ul>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg divide-y divide-gray-200">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900 mb-4">Importación de Productos</h2>
                <p class="mb-4 text-sm text-gray-500">Utiliza los botones a continuación para gestionar la importación masiva de productos desde la API de Zecat a WooCommerce.</p>
                <div class="mt-5 flex flex-col sm:flex-row sm:space-x-4">
                    <button id="zecat-update-products" class="mb-3 sm:mb-0 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="mr-2 -ml-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Actualizar Productos
                    </button>
                    <button id="zecat-import-products" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <svg class="mr-2 -ml-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                        </svg>
                        Importar Productos
                    </button>
                </div>
            </div>
            <div class="px-4 py-4 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Estado de la Sincronización</h3>
                <div id="zecat-sync-status" class="bg-gray-50 p-4 rounded-md">
                    <p class="text-sm text-gray-500">No hay sincronización en progreso.</p>
                </div>
            </div>
        </div>
    </div>
</div>

