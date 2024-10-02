<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

function fpropdf_delete_data($blog_id = 0) {

    global $wpdb;

    $prefix = $wpdb->prefix;
    if ($blog_id) {
        $prefix = $wpdb->get_blog_prefix($blog_id);
    }

    $wpdb->query('DROP TABLE IF EXISTS ' . $prefix . 'fpropdf_layouts');
    $wpdb->query('DROP TABLE IF EXISTS ' . $prefix . 'fpropdf_fields');

    $wpdb->query('DELETE FROM ' . $prefix . 'options WHERE option_name LIKE "fpropdf%"');
    $wpdb->query('DELETE FROM ' . $prefix . 'options WHERE option_name LIKE "formidablepro2pdf%"');

    $upload_dir = wp_upload_dir();
    $dirs = array();
    $dirs[] = $upload_dir['basedir'] . '/fpropdf-forms/';
    $dirs[] = $upload_dir['basedir'] . '/fpropdf-backups/';
    foreach ($dirs as $dir) {
        $dh = opendir($dir);
        if ($dh) {
            while (false !== ($filename = readdir($dh))) {
                if (!preg_match('/^\./', $filename)) {
                    if (file_exists($dir . '/' . $filename)) {
                        @unlink($dir . '/' . $filename);
                    }
                }
            }
            closedir($dh);
        }
        if (file_exists($dir) && is_dir($dir)) {
            rmdir($dir);
        }
    }
}

if (!is_multisite()) {
    fpropdf_delete_data();
} else {
    global $wpdb;
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
    $original_blog_id = get_current_blog_id();

    foreach ($blog_ids as $current_blog_id) {
        switch_to_blog($current_blog_id);
        fpropdf_delete_data($current_blog_id);
    }

    switch_to_blog($original_blog_id);
}
