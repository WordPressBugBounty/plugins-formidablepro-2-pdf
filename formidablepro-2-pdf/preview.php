<?php

if (!defined('ABSPATH')) {
    exit;
}

function fpropdf_readfile($file) {
    global $_currentFile, $currentFile;
    if ($_currentFile && file_exists($_currentFile)) {
        @unlink($_currentFile);
    }
    if ($currentFile && file_exists($currentFile)) {
        @unlink($currentFile);
    }
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'fpropdf_ajax_preview_nonce')) {

    $file = isset($_GET['file']) ? sanitize_text_field(wp_unslash($_GET['file'])) : '';
    $file = base64_decode($file);
    if (preg_match('/[\/\\\\]/', $file)) {
        die('File cannot contain slashes');
    }
    $file = FPROPDF_FORMS_DIR . $file;
    if (!file_exists($file)) {
        die("File doesn't exist");
    }

    global $currentFile;
    if ($currentFile) {
        $file = $currentFile;
    }

    $fieldId = isset($_REQUEST['field']) ? sanitize_text_field(wp_unslash($_REQUEST['field'])) : '';
    header('Content-Type: image/png');
    header('Pragma: public');
    header('Cache-Control: max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));

    $file_key = md5(file_get_contents($file));

    $folder = '/fields/' . $file_key . '/';

    if (!file_exists(__DIR__ . $folder)) {
        mkdir(__DIR__ . $folder);
    }

    try {

        $testFile = __DIR__ . $folder . md5($fieldId) . '.png';
        $testFileOrig = $testFile;
        if (file_exists($testFile)) {
            fpropdf_readfile($testFile);
        }
        $testFile .= '.done';
        if (file_exists($testFile)) {
            throw new Exception('Already processed, but no data.');
        }

        if (!file_exists($file)) {
            throw new Exception('PDF file not found');
        }

        $the_file = $file;
        $the_folder = $folder;
        $file = $the_file;
        $folder = $the_folder;

        if (!defined('FPROPDF_IS_MASTER') && fpropdf_is_activated()) {

            $request = wp_remote_post(
                    FPROPDF_SERVER . 'licence/preview.php',
                    array(
                        'method' => 'POST',
                        'timeout' => 600,
                        'body' => array(
                            'salt' => FPROPDF_SALT,
                            'code' => get_option('fpropdf_licence'),
                            'field' => $fieldId,
                            'form' => isset($_GET['form']) ? sanitize_title(wp_unslash($_GET['form'])) : '',
                            'pdf_file' => '@' . realpath($file),
                            'pdf_file_string' => $file && file_exists(realpath($file)) ? base64_encode(file_get_contents(realpath($file))) : '',
                        ),
                    )
            );

            if (!is_wp_error($request)) {
                touch($testFile);
                file_put_contents($testFileOrig, wp_remote_retrieve_body($request));
                fpropdf_readfile($testFileOrig);
            }

            throw new Exception('Image could not be get from the server');
        }

        if (!defined('FPROPDF_IS_MASTER')) {
            throw new Exception('Previews cannot be generated on this server.');
        }
    } catch (Exception $e) {
        fpropdf_readfile(__DIR__ . '/res/blank.png');
    }
}
