<?php
if (!defined('ABSPATH')) {
    exit;
}

function fpropdf_debug_page() {

    function fpropdf_print($v) {
        if ($v === false) {
            $v = 'No';
        } elseif ($v === true) {
            $v = 'Yes';
        } elseif (is_array($v)) {
            if (count($v)) {
                $v = array_map('fpropdf_print', $v);
                $v = implode(', ', $v);
            } else {
                $v = 'Empty';
            }
        } elseif ($v && strlen($v)) {
            $v = str_replace("\n", ' ', $v);
        } else {
            $v = 'No';
        }
        return $v;
    }

    $debug = array();
    $debug[] = '=== Formidable PRO2PDF ===';
    $debug[] = 'Site URL: ' . site_url('/');
    $debug[] = 'Plugin folder: ' . basename(dirname(__FILE__));
    $debug[] = 'PHP version: ' . phpversion();
    $debug[] = 'WP version: ' . fpropdf_print(get_bloginfo('version'));
    $debug[] = 'FrmAppHelper: ' . fpropdf_print(class_exists('FrmAppHelper'));
    $debug[] = 'Trial: ' . fpropdf_print(fpropdf_is_trial());
    if (function_exists('curl_version')) {
        $version = curl_version();
    } else {
        $version = array('version' => false);
    }
    $debug[] = 'CURL: ' . fpropdf_print($version['version']);

    $curl = false;
    if (function_exists('curl_init')) {
        $request = wp_remote_get(FPROPDF_SERVER . 'update/info.php');
        if (is_wp_error($request)) {
            $debug[] = 'CURL error: ' . $request->get_error_message();
        } else {
            $curl = true;
        }
    }
    $debug[] = 'CURL Test: ' . fpropdf_print($curl);
    $debug[] = 'PHP Extensions: ' . fpropdf_print(get_loaded_extensions());
    $debug[] = 'Plugins: ' . fpropdf_print(get_option('active_plugins'));

    $debug[] = '';
    $folders = array(__DIR__ . '/fields/', sys_get_temp_dir(), FPROPDF_FORMS_DIR);
    foreach ($folders as $folder) {
        $folder = @realpath($folder);
        if ($folder) {
            $debug[] = $folder . ' is writable: ' . fpropdf_print(is_writable($folder));
        }
    }

    $debug[] = '';
    $debug[] = '=== Formidable PRO2PDF PDFTK ===';
    $debug[] = is_callable('shell_exec') ? str_replace(array("\n", "\r"), ' ', shell_exec('uname -a')) : '';
    $debug[] = 'Shell Exec: ' . fpropdf_print(is_callable('shell_exec'));
    $debug[] = 'PDFTK: ' . fpropdf_print(is_callable('shell_exec') && shell_exec('which pdftk'));
    $debug[] = 'ImageMagick: ' . fpropdf_print(is_callable('shell_exec') && shell_exec('which convert'));
    $debug[] = 'Passthru: ' . fpropdf_print(is_callable('passthru'));

    $debug = implode("\n", $debug);
    ?>

    <p>Please copy-paste the information below for your customer support requests:</p>
    <textarea style="display: block; width: 100%; height: 600px;"><?php echo esc_textarea($debug); ?></textarea>

    <?php
}
