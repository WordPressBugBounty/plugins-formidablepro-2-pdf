<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('fpropdf_header')) {

    function fpropdf_transliterate_string($txt) {
        $transliterationTable = array(
            'á' => 'a', 'Á' => 'A', 'à' => 'a', 'À' => 'A', 'ă' => 'a', 'Ă' => 'A', 'â' => 'a', 'Â' => 'A', 'å' => 'a', 'Å' => 'A', 'ã' => 'a', 'Ã' => 'A', 'ą' => 'a', 'Ą' => 'A', 'ā' => 'a', 'Ā' => 'A', 'ä' => 'ae', 'Ä' => 'AE', 'æ' => 'ae', 'Æ' => 'AE', 'ḃ' => 'b', 'Ḃ' => 'B', 'ć' => 'c', 'Ć' => 'C', 'ĉ' => 'c', 'Ĉ' => 'C', 'č' => 'c', 'Č' => 'C', 'ċ' => 'c', 'Ċ' => 'C', 'ç' => 'c', 'Ç' => 'C', 'ď' => 'd', 'Ď' => 'D', 'ḋ' => 'd', 'Ḋ' => 'D', 'đ' => 'd', 'Đ' => 'D', 'ð' => 'dh', 'Ð' => 'Dh', 'é' => 'e', 'É' => 'E', 'è' => 'e', 'È' => 'E', 'ĕ' => 'e', 'Ĕ' => 'E', 'ê' => 'e', 'Ê' => 'E', 'ě' => 'e', 'Ě' => 'E', 'ë' => 'e', 'Ë' => 'E', 'ė' => 'e', 'Ė' => 'E', 'ę' => 'e', 'Ę' => 'E', 'ē' => 'e', 'Ē' => 'E', 'ḟ' => 'f', 'Ḟ' => 'F', 'ƒ' => 'f', 'Ƒ' => 'F', 'ğ' => 'g', 'Ğ' => 'G', 'ĝ' => 'g', 'Ĝ' => 'G', 'ġ' => 'g', 'Ġ' => 'G', 'ģ' => 'g', 'Ģ' => 'G', 'ĥ' => 'h', 'Ĥ' => 'H', 'ħ' => 'h', 'Ħ' => 'H', 'í' => 'i', 'Í' => 'I', 'ì' => 'i', 'Ì' => 'I', 'î' => 'i', 'Î' => 'I', 'ï' => 'i', 'Ï' => 'I', 'ĩ' => 'i', 'Ĩ' => 'I', 'į' => 'i', 'Į' => 'I', 'ī' => 'i', 'Ī' => 'I', 'ĵ' => 'j', 'Ĵ' => 'J', 'ķ' => 'k', 'Ķ' => 'K', 'ĺ' => 'l', 'Ĺ' => 'L', 'ľ' => 'l', 'Ľ' => 'L', 'ļ' => 'l', 'Ļ' => 'L', 'ł' => 'l', 'Ł' => 'L', 'ṁ' => 'm', 'Ṁ' => 'M', 'ń' => 'n', 'Ń' => 'N', 'ň' => 'n', 'Ň' => 'N', 'ñ' => 'n', 'Ñ' => 'N', 'ņ' => 'n', 'Ņ' => 'N', 'ó' => 'o', 'Ó' => 'O', 'ò' => 'o', 'Ò' => 'O', 'ô' => 'o', 'Ô' => 'O', 'ő' => 'o', 'Ő' => 'O', 'õ' => 'o', 'Õ' => 'O', 'ø' => 'oe', 'Ø' => 'OE', 'ō' => 'o', 'Ō' => 'O', 'ơ' => 'o', 'Ơ' => 'O', 'ö' => 'oe', 'Ö' => 'OE', 'ṗ' => 'p', 'Ṗ' => 'P', 'ŕ' => 'r', 'Ŕ' => 'R', 'ř' => 'r', 'Ř' => 'R', 'ŗ' => 'r', 'Ŗ' => 'R', 'ś' => 's', 'Ś' => 'S', 'ŝ' => 's', 'Ŝ' => 'S', 'š' => 's', 'Š' => 'S', 'ṡ' => 's', 'Ṡ' => 'S', 'ş' => 's', 'Ş' => 'S', 'ș' => 's', 'Ș' => 'S', 'ß' => 'SS', 'ť' => 't', 'Ť' => 'T', 'ṫ' => 't', 'Ṫ' => 'T', 'ţ' => 't', 'Ţ' => 'T', 'ț' => 't', 'Ț' => 'T', 'ŧ' => 't', 'Ŧ' => 'T', 'ú' => 'u', 'Ú' => 'U', 'ù' => 'u', 'Ù' => 'U', 'ŭ' => 'u', 'Ŭ' => 'U', 'û' => 'u', 'Û' => 'U', 'ů' => 'u', 'Ů' => 'U', 'ű' => 'u', 'Ű' => 'U', 'ũ' => 'u', 'Ũ' => 'U', 'ų' => 'u', 'Ų' => 'U', 'ū' => 'u', 'Ū' => 'U', 'ư' => 'u', 'Ư' => 'U', 'ü' => 'ue', 'Ü' => 'UE', 'ẃ' => 'w', 'Ẃ' => 'W', 'ẁ' => 'w', 'Ẁ' => 'W', 'ŵ' => 'w', 'Ŵ' => 'W', 'ẅ' => 'w', 'Ẅ' => 'W', 'ý' => 'y', 'Ý' => 'Y', 'ỳ' => 'y', 'Ỳ' => 'Y', 'ŷ' => 'y', 'Ŷ' => 'Y', 'ÿ' => 'y', 'Ÿ' => 'Y', 'ź' => 'z', 'Ź' => 'Z', 'ž' => 'z', 'Ž' => 'Z', 'ż' => 'z', 'Ż' => 'Z', 'þ' => 'th', 'Þ' => 'Th', 'µ' => 'u', 'а' => 'a', 'А' => 'a', 'б' => 'b', 'Б' => 'b', 'в' => 'v', 'В' => 'v', 'г' => 'g', 'Г' => 'g', 'д' => 'd', 'Д' => 'd', 'е' => 'e', 'Е' => 'E', 'ё' => 'e', 'Ё' => 'E', 'ж' => 'zh', 'Ж' => 'zh', 'з' => 'z', 'З' => 'z', 'и' => 'i', 'И' => 'i', 'й' => 'j', 'Й' => 'j', 'к' => 'k', 'К' => 'k', 'л' => 'l', 'Л' => 'l', 'м' => 'm', 'М' => 'm', 'н' => 'n', 'Н' => 'n', 'о' => 'o', 'О' => 'o', 'п' => 'p', 'П' => 'p', 'р' => 'r', 'Р' => 'r', 'с' => 's', 'С' => 's', 'т' => 't', 'Т' => 't', 'у' => 'u', 'У' => 'u', 'ф' => 'f', 'Ф' => 'f', 'х' => 'h', 'Х' => 'h', 'ц' => 'c', 'Ц' => 'c', 'ч' => 'ch', 'Ч' => 'ch', 'ш' => 'sh', 'Ш' => 'sh', 'щ' => 'sch', 'Щ' => 'sch', 'ъ' => '', 'Ъ' => '', 'ы' => 'y', 'Ы' => 'y', 'ь' => '', 'Ь' => '', 'э' => 'e', 'Э' => 'e', 'ю' => 'ju', 'Ю' => 'ju', 'я' => 'ja', 'Я' => 'ja',
        );
        return str_replace(array_keys($transliterationTable), array_values($transliterationTable), $txt);
    }

}

if (!function_exists('fpropdf_header')) {

    function fpropdf_header($h) {
        global $FPROPDF_NO_HEADERS;
        if (!$FPROPDF_NO_HEADERS) {
            @header($h);
        }
    }

}

global $fpropdfSignatures;
$err = 0;

if (isset($_POST['wpfx_submit_nonce']) && wp_verify_nonce(sanitize_key($_POST['wpfx_submit_nonce']), 'fpropdf_wpfx_submit') && isset($_POST['desired']) && isset($_POST['actual'])) {

    $post_desired = sanitize_text_field(wp_unslash($_POST['desired']));
    if (!file_exists($post_desired) || !is_file($post_desired)) {
        die("PDF source can't be loaded.");
    }

    $post_actual = sanitize_text_field(wp_unslash($_POST['actual']));
    if (!file_exists($post_actual) || !is_file($post_actual)) {
        die("Fields data can't be loaded.");
    }

    $desired = $post_desired;
    $actual = $post_actual;
    $actual2 = isset($_POST['actual2']) ? sanitize_text_field(wp_unslash($_POST['actual2'])) : null;
    $flatten = isset($_POST['lock']) && intval($_POST['lock']) ? 'flatten' : '';
    $real_flatten = (isset($_POST['lock']) && intval($_POST['lock']) == 2 );
    $lang = isset($_POST['lang']) && $_POST['lang'] ? intval($_POST['lang']) : false;

    $format = isset($_REQUEST['format']) ? sanitize_text_field(wp_unslash($_REQUEST['format'])) : null;
    if (!$format) {
        $format = isset($_REQUEST['default_format']) ? sanitize_text_field(wp_unslash($_REQUEST['default_format'])) : 'pdf';
    }

    if (function_exists('strtolower') && $format) {
        $format = strtolower($format);
    }

    if (!in_array($format, array('pdf', 'docx'), true)) {
        $format = 'pdf';
    }

    if (isset($_REQUEST['flattenOverride'])) {
        if ($_REQUEST['flattenOverride'] == 'yes') {
            $flatten = 'flatten';
        }
        if ($_REQUEST['flattenOverride'] == 'no') {
            $flatten = '';
        }
        if ($_REQUEST['flattenOverride'] == 'image') {
            $real_flatten = true;
        }
    }

    $encrypt = false;
    if (isset($_POST['passwd'])) {
        $post_passwd = sanitize_text_field(wp_unslash($_POST['passwd']));
        if ($post_passwd) {
            $pass = escapeshellarg($post_passwd);
            $encrypt = ' encrypt_40bit user_pw ' . $pass;
        }
    }

    if ($format == 'docx') {
        $encrypt = false;
        $flatten = '';
    }

    $generated_filename = isset($_POST['filename']) ? sanitize_text_field(wp_unslash($_POST['filename'])) : null;

    if (isset($_GET['filename'])) {
        $get_filename = sanitize_text_field(wp_unslash($_GET['filename']));
        $generated_filename = $get_filename;
    }

    if (defined('FPROPDF_IS_SENDING_EMAIL')) {
        if (isset($_POST['name_email'])) {
            $post_name_email = trim(sanitize_text_field(wp_unslash($_POST['name_email'])));
            if ($post_name_email) {
                $generated_filename = $post_name_email;
            }
        }
    }

    global $currentFieldsData;
    if (!$currentFieldsData) {
        $currentFieldsData = array();
    }

    if (!defined('FPROPDF_IS_PDF_GENERATING')) {
        preg_match_all('/\[(\d+)\]/', $generated_filename, $matches);
        if (isset($matches['1']) && !empty($matches['1'])) {
            $fields_to_replace = $matches[1];
            foreach ($fields_to_replace as $key => $value) {
                $field = FrmField::getOne($value);
                if ($field && array_key_exists($value, $currentFieldsData)) {
                    $generated_filename = str_replace('[' . $value . ']', $currentFieldsData[$value], $generated_filename);
                } elseif ($field && array_key_exists($field->field_key, $currentFieldsData)) {
                    $generated_filename = str_replace('[' . $value . ']', $currentFieldsData[$field->field_key], $generated_filename);
                } else {
                    $generated_filename = str_replace('[' . $value . ']', '', $generated_filename);
                }
            }
        }
    }

    foreach ($currentFieldsData as $field_id => $val) {
        if (is_array($val)) {
            $search_id = array(
                '[' . $field_id . ']',
            );
        } else {
            $search_id = '[' . $field_id . ']';
        }
        $generated_filename = str_replace($search_id, $val, $generated_filename);
    }

    $generated_filename = fpropdf_transliterate_string($generated_filename);
    $generated_filename = preg_replace('/[^a-zA-Z0-9\_\.\- ]+/', ' ', $generated_filename);
    $generated_filename = preg_replace('/ +/', ' ', $generated_filename);
    $generated_filename = trim($generated_filename);
    $generated_filename = trim($generated_filename, '_');
    if (!$generated_filename) {
        $generated_filename = 'Form';
    }

    if (!preg_match('/\.' . $format . '$/i', $generated_filename)) {
        $generated_filename .= '.' . $format;
    }

    $old_post_data = $_POST;
    unset($_POST);

    if ($format == 'pdf') {
        fpropdf_header('Content-type: application/pdf');
    } elseif ($format == 'docx') {
        fpropdf_header('Content-type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }
    fpropdf_header('Content-Disposition: attachment; filename="' . $generated_filename . '"');

    if (isset($_GET['inline'])) {
        fpropdf_header('Content-Disposition: inline; filename="' . $generated_filename . '"');
    }

    global $FPROPDF_FILENAME;
    $FPROPDF_FILENAME = $generated_filename;

    $tmp = false;
    $command = false;
    $tmpPdf = '-';

    ob_start();
    $data = ob_get_clean();

    if (is_callable('shell_exec') && is_callable('escapeshellarg') && shell_exec('which pdftk') && is_callable('passthru') && (defined('FPROPDF_IS_MASTER') || get_option('fpropdf_enable_local') || get_option('fpropdf_licence') == 'OFFLINE_SITE')) {
        if ($actual2 && $actual2 != "''") {
            $tmp = escapeshellarg(tempnam(PROPDF_TEMP_DIR, 'fpropdfTmpFile') . '.pdf');
            $desired = escapeshellarg($desired);
            $actual = escapeshellarg($actual);
            $actual2 = escapeshellarg($actual2);
            shell_exec("pdftk $desired fill_form $actual output $tmp 2>&1");
            $command = "pdftk $tmp fill_form $actual2 output $tmpPdf $flatten";
        } else {
            $desired = escapeshellarg($desired);
            $actual = escapeshellarg($actual);
            $command = "pdftk $desired fill_form $actual output $tmpPdf $flatten";
        }

        ob_start();
        passthru($command);
        $buffer = ob_get_clean();

        if ($real_flatten && shell_exec('which convert')) {
            $tmpPdf = tempnam(PROPDF_TEMP_DIR, 'fpropdfTmpFile') . '.pdf';
            $tmpDir = tempnam(PROPDF_TEMP_DIR, 'fpropdfTmpFile') . '-jpgs';
            @mkdir($tmpDir);
            file_put_contents($tmpPdf, $buffer);

            $filesTmp = array();
            shell_exec('convert -density 300 ' . escapeshellarg($tmpPdf) . ' ' . escapeshellarg($tmpDir . '/%04d.jpg') . ' 2>&1');
            $handle = opendir($tmpDir);
            $entries = array();
            while (false !== ($entry = readdir($handle))) { //phpcs:ignore
                if ($entry == '.') {
                    continue;
                }
                if ($entry == '..') {
                    continue;
                }
                $entries[] = $entry;
            }
            closedir($handle);
            sort($entries);

            foreach ($entries as $entry) {
                $fileTmp = $tmpDir . '/' . $entry;
                shell_exec('convert ' . escapeshellarg($fileTmp) . ' ' . escapeshellarg($fileTmp . '.pdf'));
                $filesTmp[] = escapeshellarg($fileTmp . '.pdf');
            }

            $buffer = shell_exec('pdftk ' . implode(' ', $filesTmp) . ' cat output - ');
            shell_exec('rm -fr ' . $tmpDir);
            if (file_exists($tmpPdf) && is_file($tmpPdf)) {
                @unlink($tmpPdf);
            }
        }

        if (file_exists($tmp) && is_file($tmp)) {
            @unlink($tmp);
        }
        $data = $buffer;

        if ($data && $encrypt) {
            $tmpPdf = tempnam(PROPDF_TEMP_DIR, 'fpropdfTmpFile') . '.pdf';
            file_put_contents($tmpPdf, $data);
            $data = shell_exec('pdftk ' . escapeshellarg($tmpPdf) . ' output - ' . $encrypt);
            if (file_exists($tmpPdf) && is_file($tmpPdf)) {
                @unlink($tmpPdf);
            }
        }
    }

    $upload_pdf = intval(get_option('fpropdf_faster_uploads'));
    $needs_upload = true;
    $docxError = false;

    $fetch_remote = !get_option('fpropdf_disable_local');
    if (get_option('fpropdf_enable_local')) {
        $fetch_remote = false;
    }

    if (get_option('fpropdf_licence') != 'OFFLINE_SITE') {

        if ((!defined('FPROPDF_IS_MASTER') && ((!$data && !get_option('fpropdf_restrict_remote_requests')) || $fetch_remote) && fpropdf_is_activated())) {

            ob_start();
            $data = ob_get_clean();
            $pdftk = '';

            $post_data = array_merge(
                    array(
                        'salt' => FPROPDF_SALT,
                        'form' => isset($_GET['form']) ? sanitize_title(wp_unslash($_GET['form'])) : '',
                        'passwd' => isset($_POST['passwd']) ? sanitize_text_field(wp_unslash($_POST['passwd'])) : null,
                        'lang' => isset($_POST['lang']) ? intval($_POST['lang']) : null,
                        'flatten' => isset($_POST['flatten']) ? intval($_POST['flatten']) : null,
                        'flattenOverride' => isset($_REQUEST['flattenOverride']) ? sanitize_text_field(wp_unslash($_REQUEST['flattenOverride'])) : null,
                        'format' => isset($_REQUEST['format']) ? sanitize_text_field(wp_unslash($_REQUEST['format'])) : null,
                        'default_format' => isset($_REQUEST['default_format']) ? sanitize_text_field(wp_unslash($_REQUEST['default_format'])) : 'pdf',
                        'site_url' => site_url('/'),
                        'site_title' => get_bloginfo('name'),
                        'site_ip' => isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : '',
                        'fpropdf_pdfaid_api_key' => trim(get_option('fpropdf_pdfaid_api_key')),
                        'filename' => $generated_filename,
                        'code' => get_option('fpropdf_licence'),
                        'fpropdfSignatures' => @serialize($fpropdfSignatures),
                    ), $old_post_data
            );

            if (isset($post_data['passwd']) && $currentFieldsData) {
                $pass = $post_data['passwd'];

                if (!defined('FPROPDF_IS_PDF_GENERATING')) {
                    preg_match_all('/\[(\d+)\]/', $pass, $matches);
                    if (isset($matches['1']) && !empty($matches['1'])) {
                        $fields_to_replace = $matches[1];
                        foreach ($fields_to_replace as $key => $value) {
                            $field = FrmField::getOne($value);
                            if ($field && array_key_exists($value, $currentFieldsData)) {
                                $pass = str_replace('[' . $value . ']', $currentFieldsData[$value], $pass);
                            } elseif ($field && array_key_exists($field->field_key, $currentFieldsData)) {
                                $pass = str_replace('[' . $value . ']', $currentFieldsData[$field->field_key], $pass);
                            } else {
                                $pass = str_replace('[' . $value . ']', '', $pass);
                            }
                        }
                    }
                }

                foreach ($currentFieldsData as $field_id => $val) {
                    if (is_array($val)) {
                        $search_id = array(
                            '[' . $field_id . ']',
                        );
                    } else {
                        $search_id = '[' . $field_id . ']';
                    }

                    $pass = str_replace($search_id, $val, $pass);
                }
                $post_data['passwd'] = $pass;
            }

            if ($upload_pdf) {
                $new_post_data = $post_data;
                unset($new_post_data['fpropdfSignatures']);
                $new_post_data['hash'] = md5_file($post_data['desired']);

                $request = wp_remote_post(
                        FPROPDF_SERVER . 'licence/pdf_uploaded.php',
                        array(
                            'method' => 'POST',
                            'timeout' => 600,
                            'body' => $new_post_data,
                        )
                );

                if (!is_wp_error($request)) {
                    $pdf_data = json_decode(wp_remote_retrieve_body($request));
                    if ($pdf_data) {
                        if ($pdf_data->uploaded) {
                            $needs_upload = false;
                            $upload_pdf = false;
                        }
                    }
                }
            }

            $keys = array(
                'actual', 'actual2', 'desired',
            );
            if (!$needs_upload) {
                $keys = array(
                    'actual', 'actual2',
                );
                $post_data['desired_key'] = $new_post_data['hash'];
            }
            foreach ($keys as $key) {
                if (isset($post_data[$key]) && $post_data[$key]) {
                    if (file_exists(realpath($post_data[$key]))) {
                        $post_data[$key . '_string'] = base64_encode(file_get_contents(realpath($post_data[$key])));
                    } else {
                        $post_data[$key . '_string'] = '';
                    }
                    $post_data[$key] = '@' . realpath($post_data[$key]);
                }
            }

            $post_data['upload_pdf'] = intval($upload_pdf);

            $request = wp_remote_post(
                    FPROPDF_SERVER . 'licence/pdftk.php?' . time(),
                    array(
                        'method' => 'POST',
                        'timeout' => 600,
                        'body' => $post_data,
                    )
            );

            if (is_wp_error($request)) {
                $err = "Your server wasn't able to upload PDF file.";
            } else {
                $data = wp_remote_retrieve_body($request);
                if (preg_match('/^\{.*\}$/', $data)) {
                    $tmp = json_decode($data);
                    $data = false;
                    $err = $tmp->error;
                } elseif ($data === false) {
                    $data = false;
                    $err = "Your server wasn't able to upload PDF file.";
                }
            }
        } else {

            if ($format == 'docx') {
                if ($data) {
                    if (!class_exists('SoapClient')) {
                        fpropdf_header('Content-Type: text/plain; charset=utf-8');
                        fpropdf_header('Content-Disposition: inline; filename="error.txt"');
                        echo 'PHP SOAP extension should be installed in order to generate DOCX files. It is required by PDFaid.com. Please contact your hosting provider or server administrator to install it.';
                        exit;
                    }

                    $key = isset($_REQUEST['fpropdf_pdfaid_api_key']) ? sanitize_text_field(wp_unslash($_REQUEST['fpropdf_pdfaid_api_key'])) : trim(get_option('fpropdf_pdfaid_api_key'));

                    require dirname(__FILE__) . '/PdfaidServices.php';

                    $tmpPdf = tempnam(PROPDF_TEMP_DIR, 'fpropdfTmpFile') . '.pdf';
                    file_put_contents($tmpPdf, $data);

                    $tmpDocx = tempnam(PROPDF_TEMP_DIR, 'fpropdfTmpFile') . '.docx';

                    $myPdf2Doc = new Pdf2Doc();
                    $myPdf2Doc->apiKey = $key;
                    $myPdf2Doc->inputPdfLocation = $tmpPdf;
                    $myPdf2Doc->outputDocLocation = $tmpDocx;
                    $result = $myPdf2Doc->Pdf2Doc();

                    if ($result != 'OK') {
                        $data = false;
                        $docxError = $result;
                    } else {
                        $data = file_get_contents($tmpDocx);
                        if (!$data) {
                            $docxError = 'PDFaid API returned an empty file. Probably API key is wrong, or your file cannot be processed.';
                        }
                    }

                    @unlink($tmpPdf);
                    @unlink($tmpDocx);
                }
            }
        }
    }

    if (!$data) {
        ob_start();
        fpropdf_header('Content-Type: text/html; charset=utf-8');
        fpropdf_header('Content-Disposition: inline; filename="error.txt"');
        $debug = 'The form could not be filled in.';
        if ($command) {
            $debug = shell_exec("$command 2>&1");
            if (preg_match('/java\.lang\.NullPointerException/', $debug)) {
                $debug = "The form could not be filled in.\n\n$debug";
            }
        }
        if ($err) {
            $debug = $err;
        }
        if (preg_match('/has not been activated/', $debug)) {
            $debug .= ' <a href="admin.php?page=fpdf&tab=forms" target="_blank">Click here</a> to manage your activated forms.';
        }
        echo '<pre>There was an error generating the PDF file.';
        if ($command) {
            echo "\nThe command was: $command";
        }
        echo "\n$debug\n";
        echo '</pre>';
        $data = ob_get_clean();
        if ($docxError) {
            $data = 'PDF file was successfully created. However, PDFaid API returned an error: ' . esc_html($docxError);
        }
        global $FPROPDF_GEN_ERROR;
        if (!$FPROPDF_GEN_ERROR) {
            $FPROPDF_GEN_ERROR = $data;
        }
    }

    if (function_exists('do_action') && ($format == 'pdf') && !defined('FPROPDF_IS_DATA_SUBMITTING')) {
        do_action('fpro2pdf_pdf_generated', $data, $_REQUEST);
    }

    fpropdf_header('Content-length: ' . strlen($data));
    global $FPROPDF_NO_HEADERS, $FPROPDF_CONTENT;
    if (!$FPROPDF_NO_HEADERS) {
        echo $data;
    } elseif (!$FPROPDF_CONTENT) {
        $FPROPDF_CONTENT = $data;
    }
} else {
    die('Wrong post params');
}
