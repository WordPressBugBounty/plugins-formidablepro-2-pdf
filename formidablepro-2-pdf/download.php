<?php

if (!defined('ABSPATH')) {
    exit;
}

if (isset($_POST['wpfx_submit_nonce']) && wp_verify_nonce(sanitize_key($_POST['wpfx_submit_nonce']), 'fpropdf_wpfx_submit')) {
    $wpfx_submit_nonce = sanitize_key($_POST['wpfx_submit_nonce']);
    $params = $_GET;
    $_POST['wpfx_submit'] = 1;
    $_POST['wpfx_dataset'] = -3;
    if ($params['dataset']) {
        $_POST['wpfx_dataset'] = $params['dataset'];
    }
    $_POST['wpfx_layout'] = $params['layout'];
    $_POST['wpfx_form'] = $params['form'];

    if (fpropdf_enable_security()) {
        if (!isset($_REQUEST['key']) || (isset($_REQUEST['key']) && $_REQUEST['key'] != fpropdf_dataset_key($params['dataset'], $params['form'], $params['layout'], isset($params['user']) ? $params['user'] : null, isset($params['role']) ? $params['role'] : null, isset($params['condition']) ? $params['condition'] : null))) {
            ob_get_clean();
            die('The secret key for this form is not valid.');
        }
    }

    if (!defined('FPROPDF_IS_GENERATING')) {
        define('FPROPDF_IS_GENERATING', true);
    }

    ob_start();
    wpfx_admin();
    $html = ob_get_clean();

    if (isset($params['form2']) && $params['form2']) {
        $_POST['wpfx_submit'] = 1;
        $_POST['wpfx_dataset'] = -3;
        if ($params['dataset']) {
            $_POST['wpfx_dataset'] = $params['dataset2'];
        }
        $_POST['wpfx_layout'] = $params['layout2'];
        $_POST['wpfx_form'] = $params['form2'];

        if (fpropdf_enable_security()) {
            if (!isset($_REQUEST['key2']) || (isset($_REQUEST['key2']) && $_REQUEST['key2'] != fpropdf_dataset_key($params['dataset2'], $params['form2'], $params['layout2'], $params['user'], $params['role'], $params['condition']))) {
                ob_get_clean();
                die('The secret key for this form is not valid.');
            }
        }

        ob_start();
        wpfx_admin();
        $html2 = ob_get_clean();
    }

    $_POST = array(
        'wpfx_submit_nonce' => $wpfx_submit_nonce,
    );

    if (preg_match('/<input type = "hidden" name = "desired" value = "([^"]+)" /', $html, $m)) {
        $_POST['desired'] = wp_specialchars_decode($m[1], ENT_QUOTES);
    }

    if (preg_match('/<input type = "hidden" name = "actual"  value = "([^"]+)" /', $html, $m)) {
        $_POST['actual'] = wp_specialchars_decode($m[1], ENT_QUOTES);
    }

    if (preg_match('/<input type = "hidden" name = "lock" value = "([^"]+)" /', $html, $m)) {
        $_POST['lock'] = $m[1];
    }

    if (preg_match('/<input type = "hidden" name = "passwd" value = "([^"]+)" /', $html, $m)) {
        $_POST['passwd'] = wp_specialchars_decode($m[1], ENT_QUOTES);
    }

    if (preg_match('/<input type = "hidden" name = "lang" value = "([^"]+)" /', $html, $m)) {
        $_POST['lang'] = $m[1];
    }

    if (preg_match('/<input type = "hidden" name = "filename" value = "([^"]+)" /', $html, $m)) {
        $_POST['filename'] = $m[1];
    }

    if (preg_match('/<input type = "hidden" name = "name_email" value = "([^"]+)" /', $html, $m)) {
        $_POST['name_email'] = $m[1];
    }

    if (preg_match('/<input type = "hidden" name = "restrict_user" value = "([^"]+)" /', $html, $m)) {
        $_POST['restrict_user'] = $m[1];
    }

    if (preg_match('/<input type = "hidden" name = "restrict_role" value = "([^"]+)" /', $html, $m)) {
        $_POST['restrict_role'] = $m[1];
    }

    if (preg_match('/<input type = "hidden" name = "default_format" value = "([^"]+)" /', $html, $m)) {
        $_REQUEST['default_format'] = $m[1];
    }

    if (!defined('FPROPDF_IS_PDF_GENERATING')) {

        $deny = false;

        $restrict_condition = isset($_REQUEST['condition']) ? sanitize_text_field(wp_unslash($_REQUEST['condition'])) : null;
        if (!$restrict_condition) {
            $restrict_condition = get_option('fpropdf_restrict_condition');
        }
        if (!$restrict_condition) {
            $restrict_condition = 'and';
        }

        $restrict_1 = true;
        $restrict_2 = true;

        if (isset($_REQUEST['role'])) {
            $request_role = sanitize_text_field(wp_unslash($_REQUEST['role']));
            if ($request_role) {
                $_POST['restrict_role'] = $request_role;
            }
        }
        if (isset($_REQUEST['user'])) {
            $request_user = sanitize_text_field(wp_unslash($_REQUEST['user']));
            if ($request_user) {
                $_POST['restrict_user'] = $request_user;
            }
        }

        if (isset($_POST['restrict_user'])) {
            $post_restrict_user = sanitize_text_field(wp_unslash($_POST['restrict_user']));
            if (!fpropdf_check_user_id($post_restrict_user)) {
                $restrict_1 = false;
            }
        }

        if (isset($_POST['restrict_role'])) {
            $post_restrict_role = sanitize_text_field(wp_unslash($_POST['restrict_role']));
            if (!fpropdf_check_user_role($post_restrict_role)) {
                $restrict_2 = false;
            }
        }

        if ($restrict_condition == 'or') {

            if (!isset($_POST['restrict_user']) || !isset($_POST['restrict_role'])) {
                $restrict_condition = 'and';
            }

            if (isset($_POST['restrict_user'])) {
                $post_restrict_user = sanitize_text_field(wp_unslash($_POST['restrict_user']));
                if (!$post_restrict_user) {
                    $restrict_condition = 'and';
                }
            }

            if (isset($_POST['restrict_role'])) {
                $post_restrict_role = sanitize_text_field(wp_unslash($_POST['restrict_role']));
                if (!$post_restrict_role) {
                    $restrict_condition = 'and';
                }
            }
        }

        if ($restrict_condition == 'and') {
            if (!$restrict_1 || !$restrict_2) {
                $deny = true;
            }
        }
        if ($restrict_condition == 'or') {
            if (!$restrict_1 && !$restrict_2) {
                $deny = true;
            }
        }

        if ($deny) {
            ob_get_clean();
            die("You don't have sufficient permissions to download this file.");
        }
    }

    if (isset($html2) && $html2) {
        if (preg_match('/<input type = "hidden" name = "desired" value = "([^"]+)" /', $html2, $m)) {
            $_POST['desired2'] = wp_specialchars_decode($m[1], ENT_QUOTES);
        }

        if (preg_match('/<input type = "hidden" name = "actual"  value = "([^"]+)" /', $html2, $m)) {
            $_POST['actual2'] = $m[1];
        }

        if (preg_match('/<input type = "hidden" name = "lang" value = "([^"]+)" /', $html2, $m)) {
            $_POST['lang'] = $m[1];
        }

        if (preg_match('/<input type = "hidden" name = "lock" value = "([^"]+)" /', $html2, $m)) {
            $_POST['lock2'] = $m[1];
        }
    }

    $_POST['download'] = 'Download';

    if (isset($params['password']) && $params['password']) {
        $_POST['passwd'] = stripslashes($params['password']);
    }

    require __DIR__ . '/generate-pdf.php';
}
