<?php
// Asumimos que esta función está en tu archivo de clase principal o en functions.php
function get_zecat_error_logs($limit = 100) {
    $log_file = WP_CONTENT_DIR . '/zecat-api-connector-error.log';
    if (!file_exists($log_file)) {
        return array();
    }
    $logs = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logs = array_reverse($logs); // Más recientes primero
    return array_slice($logs, 0, $limit);
}

// En tu página de administración
$limit = isset($_GET['log_limit']) ? intval($_GET['log_limit']) : 100;
$recent_errors = get_zecat_error_logs($limit);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="get" action="">
        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
        <label for="log_limit">Número de registros a mostrar:</label>
        <select name="log_limit" id="log_limit" onchange="this.form.submit()">
            <option value="50" <?php selected($limit, 50); ?>>50</option>
            <option value="100" <?php selected($limit, 100); ?>>100</option>
            <option value="200" <?php selected($limit, 200); ?>>200</option>
            <option value="500" <?php selected($limit, 500); ?>>500</option>
            <option value="1000" <?php selected($limit, 1000); ?>>1000</option>
        </select>
    </form>

    <div class="console-container" style="background-color: #0c0c0c; color: #cccccc; font-family: 'Courier New', monospace; padding: 20px; border-radius: 5px; height: 600px; overflow-y: scroll; margin-top: 20px;">
        <?php foreach ($recent_errors as $error): ?>
            <?php
            $parts = explode('] [', $error);
            $timestamp = isset($parts[0]) ? trim($parts[0], '[]') : '';
            $severity = isset($parts[1]) ? trim($parts[1], '[]') : '';
            $message = isset($parts[2]) ? trim($parts[2], '[]') : '';
            
            if (empty($severity) && empty($message)) {
                $message = $error;
                $severity = 'UNKNOWN';
            }
            
            $severity_color = '#cccccc';
            switch (strtolower($severity)) {
                case 'error':
                    $severity_color = '#ff6b6b';
                    break;
                case 'warning':
                    $severity_color = '#feca57';
                    break;
                case 'info':
                    $severity_color = '#54a0ff';
                    break;
                case 'debug':
                    $severity_color = '#5f27cd';
                    break;
            }
            ?>
            <div class="console-line" style="margin-bottom: 5px;">
                <span style="color: #4ecdc4;">[<?php echo esc_html($timestamp); ?>]</span>
                <span style="color: <?php echo $severity_color; ?>;">[<?php echo esc_html($severity); ?>]</span>
                <span><?php echo esc_html($message); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <p style="margin-top: 10px; font-style: italic;">Nota: Se muestran los últimos <?php echo count($recent_errors); ?> registros de un total de <?php echo $limit; ?> solicitados. Los registros más antiguos se eliminan automáticamente después de 30 días.</p>
</div>

<style>
    .console-container {
        border: 1px solid #30363d;
    }
    .console-container::-webkit-scrollbar {
        width: 12px;
    }
    .console-container::-webkit-scrollbar-track {
        background: #1e1e1e;
    }
    .console-container::-webkit-scrollbar-thumb {
        background-color: #888;
        border-radius: 6px;
        border: 3px solid #1e1e1e;
    }
</style>

