<?php

/**
 * Plugin Name: Formidable PRO2PDF
 * Version: 3.18
 * Description: This plugin allows to export data from Formidable Pro forms to PDF
 * Author: formidablepro2pdf.com
 * Plugin URI: http://www.formidablepro2pdf.com/
 * Author URI: http://www.formidablepro2pdf.com/
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/classes/class-fpropdf-global.php';
global $fpropdf_global;
$fpropdf_global = new Fpropdf_Global();
$fpropdf_version = get_file_data(__FILE__, array('Version' => 'Version'), false);

define('FPROPDF_VERSION', !empty($fpropdf_version['Version']) ? $fpropdf_version['Version'] : '3.09');

function fpropdf_enable_security() {
    return (get_option('fpropdf_enable_security') && !defined('FPROPDF_IS_SENDING_EMAIL') && !defined('FPROPDF_IS_DATA_SUBMITTING'));
}

function fpropdf_check_user_role($roles) {
    if (!$roles) {
        return true;
    }
    $result = true;

    $hierarchy = array(
        'contributor' => array('subscriber'),
        'author' => array('subscriber', 'contributor'),
        'editor' => array('subscriber', 'contributor', 'author'),
        'administrator' => array('subscriber', 'contributor', 'author', 'editor'),
        'super_admin' => array('subscriber', 'contributor', 'author', 'editor'),
        'superadmin' => array('subscriber', 'contributor', 'author', 'editor'),
        'super_admininstrator' => array('subscriber', 'contributor', 'author', 'editor'),
    );

    foreach (explode(',', $roles) as $v) {
        $v = trim($v);
        if (!$v) {
            continue;
        }
        if ($v == 'all') {
            return true;
        }
        if (!is_user_logged_in()) {
            return false;
        }
        $result = false;
        $current_user = wp_get_current_user();
        $current_user_roles = $current_user->roles;
        foreach ($current_user_roles as $role) {
            if ($v == 'any') {
                return true;
            }
            if (strtolower($role) == strtolower($v)) {
                return true;
            }
            if (isset($hierarchy[$role])) {
                foreach ($hierarchy[$role] as $hierarchy_role) {
                    if (strtolower($hierarchy_role) == strtolower($v)) {
                        return true;
                    }
                }
            }
        }
    }
    return $result;
}

function fpropdf_check_user_id($ids) {
    if (!$ids) {
        return true;
    }
    $result = true;
    foreach (explode(',', $ids) as $v) {
        $v = trim($v);
        if (!$v) {
            continue;
        }
        if ($v == 'all') {
            return true;
        }
        if (!is_user_logged_in()) {
            return false;
        }
        if ($v == 'any') {
            return true;
        }
        $result = false;
        $current_user = wp_get_current_user();
        if (intval($current_user->ID) == intval($v)) {
            return true;
        }
    }
    return $result;
}

function fpropdf_field_id_to_key($id) {
    if (preg_match('/^FPROPDF_/', $id)) {
        return $id;
    }
    if (!is_numeric($id)) {
        return $id;
    }
    global $wpdb;
    $got_id = $wpdb->get_var(
            $wpdb->prepare(
                    'SELECT field_key FROM ' . $wpdb->prefix . 'frm_fields WHERE id = %d', intval($id)
            )
    );
    if ($got_id) {
        return $got_id;
    }
    return $id;
}

function fpropdf_field_key_to_id($id) {
    if (is_numeric($id)) {
        return $id;
    }
    global $wpdb;
    $got_id = $wpdb->get_var(
            $wpdb->prepare(
                    'SELECT id FROM ' . $wpdb->prefix . 'frm_fields WHERE field_key = %s', $id
            )
    );
    if ($got_id) {
        return $got_id;
    }
    return $id;
}

function fpropdf_dataset_key($dataset, $form, $layout, $user = '', $role = '', $condition = '') {
    global $wpdb;
    if ($user || $role || $condition) {
        $layout .= $user . ',' . $role . ',' . $condition;
    }
    return md5(implode(',', array(NONCE_SALT, $wpdb->prefix, $dataset, $form, $layout)));
}

function fpropdf_admin_head() {
    $additional = apply_filters('fpropdf_additional_formatting', array());
    if (!count($additional)) {
        return;
    }
    echo '<script type="text/javascript">';
    echo 'window.fpropdfAdditionalFormatting = ' . json_encode(array_keys($additional)) . ";\n";
    echo '</script>';
}

add_action('admin_head', 'fpropdf_admin_head');

if (!defined('PROPDF_TEMP_DIR')) {
    $temp_dir = sys_get_temp_dir();
    if (is_dir($temp_dir) && is_writable($temp_dir) && is_readable($temp_dir)) {
        define('PROPDF_TEMP_DIR', $temp_dir);
    }
    if (!defined('PROPDF_TEMP_DIR')) {
        if (is_callable('ini_get')) {
            $temp_dir = ini_get('upload_tmp_dir');
            if (is_dir($temp_dir) && is_writable($temp_dir) && is_readable($temp_dir)) {
                define('PROPDF_TEMP_DIR', $temp_dir);
            }
        }
    }
    if (!defined('PROPDF_TEMP_DIR')) {
        define('PROPDF_TEMP_DIR', ABSPATH . 'tmp/');
    }
}

// fpropdfTmpFile
$dir = PROPDF_TEMP_DIR;
if (file_exists($dir) && is_dir($dir)) {
    if (substr($dir, strlen($dir) - 1, 1) != '/') {
        $dir .= '/';
    }

    $tmp_files = glob($dir . '*fpropdfTmpFile*', GLOB_MARK);
    foreach ($tmp_files as $file) {
        if (is_file($file) && time() - filemtime($file) >= 60 * 60) {
            @unlink($file);
        }
    }
}

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/backups.php';
require_once __DIR__ . '/debug.php';
require_once __DIR__ . '/templates.php';

function fpropdf_set_charset() {

    global $wpdb;
    $exists = $wpdb->get_var(
                    $wpdb->prepare(
                            'SELECT COUNT(*) FROM information_schema.TABLES WHERE (TABLE_SCHEMA = %s) AND (TABLE_NAME = %s)', DB_NAME, $wpdb->prefix . 'fpropdf_layouts'
                    )
            ) > 0;
    if ($exists === false) {
        $wpdb->query('RENAME TABLE wp_fxlayouts TO ' . $wpdb->prefix . 'fpropdf_layouts');
    }

    if (!file_exists(FPROPDF_BACKUPS_DIR)) {
        // Create forms folder in wp-content/uploads
        @mkdir(FPROPDF_BACKUPS_DIR, 0755);

        $rows = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'fpropdf_layouts', ARRAY_A);
        $num = count($rows);
        $ids = array();
        for ($i = 0; $i < $num; $i++) {
            $row = $rows[$i];
            $ids[] = $row['ID'];
        }

        foreach ($ids as $id) {
            wpfx_backup_layout($id);
        }
    }

    if (!file_exists(FPROPDF_BACKUPS_DIR . 'index.php')) {
        @file_put_contents(FPROPDF_BACKUPS_DIR . 'index.php', "<?php\n// Silence is golden.\n?>");
        if (file_exists(FPROPDF_BACKUPS_DIR . 'index.php')) {
            @chmod(FPROPDF_BACKUPS_DIR . 'index.php', 0644);
        }
    }
}

add_action('init', 'fpropdf_set_charset');

$upload_dir = wp_upload_dir();
define('FPROPDF_FORMS_DIR', $upload_dir['basedir'] . '/fpropdf-forms/');
define('FPROPDF_BACKUPS_DIR', $upload_dir['basedir'] . '/fpropdf-backups/');

global $wpdb;
define('FPROPDF_WPFXLAYOUTS', $wpdb->prefix . 'fpropdf_layouts');
define('FPROPDF_WPFXFIELDS', $wpdb->prefix . 'fpropdf_fields');
define('FPROPDF_WPFXTMP', $wpdb->prefix . 'fpropdf_tmp');

if (!file_exists(FPROPDF_FORMS_DIR)) {

    @mkdir(FPROPDF_FORMS_DIR, 0755);
    $old_forms = __DIR__ . '/forms/';

    if (file_exists($old_forms)) {
        $handle = opendir($old_forms);
        if ($handle) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry == '.') {
                    continue;
                }
                if ($entry == '..') {
                    continue;
                }
                @rename($old_forms . $entry, FPROPDF_FORMS_DIR . $entry);
            }
        }
    }
}

if (!file_exists(FPROPDF_FORMS_DIR . 'index.php')) {
    @file_put_contents(FPROPDF_FORMS_DIR . 'index.php', "<?php\n// Silence is golden.\n?>");
    if (file_exists(FPROPDF_FORMS_DIR . 'index.php')) {
        @chmod(FPROPDF_FORMS_DIR . 'index.php', 0644);
    }
}

add_filter('pre_set_site_transient_update_plugins', 'fpropdf_pre_update', 10, 1);

function fpropdf_pre_update($transient) {
    if (!is_object($transient)) {
        return $transient;
    }
    if (!defined('FPROPDF_PATH')) {
        define('FPROPDF_PATH', plugin_basename(__FILE__));
    }
    if (!isset($transient->response[FPROPDF_PATH]) && !isset($transient->no_update[FPROPDF_PATH])) {
        $request = wp_remote_get(FPROPDF_SERVER . 'update/info.php');
        $result = array();
        if (!is_wp_error($request)) {
            $result = json_decode(wp_remote_retrieve_body($request), true);
            if (isset($result['version'])) {
                $update_info = array(
                    'url' => $result['url'],
                    'slug' => dirname(plugin_basename(__FILE__)),
                    'plugin' => FPROPDF_PATH,
                    'package' => $result['package'],
                    'new_version' => $result['version'],
                    'tested' => $result['tested'],
                    'icons' => $result['icons'],
                    'id' => FPROPDF_PATH,
                );
                if (version_compare(FPROPDF_VERSION, $update_info['new_version'], '<')) {
                    $transient->response[FPROPDF_PATH] = (object) $update_info;
                }
            }
        }
    }
    return $transient;
}

add_action('install_plugins_pre_plugin-information', 'fpropdf_changelog', 9);

function fpropdf_changelog() {
    if (1 == 1 || $_REQUEST['plugin'] != 'formidablepro-2-pdf') {
        return;
    }
    $request = wp_remote_get(FPROPDF_SERVER . 'update/info.php');
    $result = array();
    if (!is_wp_error($request)) {
        $result = json_decode(wp_remote_retrieve_body($request), true);
    }
    if (isset($result['sections']['changelog'])) {
        echo '<div style="margin:10px; padding: 20px; border:1px solid #ccc;">';
        echo '<h2>' . $result['name'] . ' v' . $result['version'] . ' Changelog</h2>';
        echo $result['sections']['changelog'];
        echo '</div>';
    }
    exit;
}

// Plugin settings link in Plugins list
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'fpropdf_add_action_links');

function fpropdf_add_action_links($links) {
    $mylinks = array(
        '<a href="' . admin_url('admin.php?page=fpdf') . '">Settings</a>',
    );
    return array_merge($links, $mylinks);
}

function fpropdf_use_field_keys() {
    if (defined('FPROPDF_USE_KEYS')) {
        return FPROPDF_USE_KEYS;
    }
    return get_option('fpropdf_use_field_keys');
}

function fpropdf_myplugin_activate() {
    global $wpdb;

    if (!get_option('fpropdf_licence')) {
        update_option('fpropdf_use_field_keys', '1');
        update_option('fpropdf_limit_dropdowns', '1');

        if (!get_option('fpropdf_installed_version')) {
            update_option('fpropdf_installed_version', '20000');
        }
    }

    update_option('fpropdf_enable_security', '1');

    $exists = $wpdb->get_var(
                    $wpdb->prepare(
                            'SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`TABLES` WHERE `TABLE_SCHEMA` = %s AND `TABLE_NAME` = %s', DB_NAME, 'wp_fxlayouts'
                    )
            ) > 0;
    if ($exists === true) {
        $wpdb->query('RENAME TABLE wp_fxlayouts TO ' . $wpdb->prefix . 'fpropdf_layouts');
    }

    $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . $wpdb->prefix . 'fpropdf_layouts` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) CHARACTER SET utf8 NOT NULL,
    `file` varchar(255) CHARACTER SET utf8 NOT NULL,
    `data` LONGTEXT CHARACTER SET utf8 NOT NULL,
    `visible` tinyint(1) NOT NULL,
    `form` int(11) NOT NULL,
    `dname` int(11) NOT NULL,
    `created_at` datetime NOT NULL,
    `formats` LONGTEXT CHARACTER SET utf8,
    PRIMARY KEY (`ID`)
    ) CHARACTER SET utf8'
    );

    $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . $wpdb->prefix . 'fpropdf_fields` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `field_key` varchar(255) CHARACTER SET utf8 NOT NULL,
    `field_id` int(11) NOT NULL,
    `form_id`  int(11) NOT NULL,
    PRIMARY KEY (`ID`)
    ) CHARACTER SET utf8'
    );

    $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . $wpdb->prefix . 'fpropdf_tmp` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `form_id` int(11) NOT NULL,
    `layout_id` int(11) NOT NULL,
    `entry_id` int(11) NOT NULL,
    `path` varchar(255) NOT NULL,
    `signatures` LONGTEXT CHARACTER SET utf8 NOT NULL,
    PRIMARY KEY (`ID`)
    ) CHARACTER SET utf8'
    );

    $columns = $wpdb->get_col(
            $wpdb->prepare(
                    'SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = %s AND `TABLE_NAME` = %s', DB_NAME, $wpdb->prefix . 'fpropdf_tmp'
            )
    );

    if (!in_array('signatures', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_tmp` ADD COLUMN signatures LONGTEXT CHARACTER SET utf8 NOT NULL');
    }

    $columns = $wpdb->get_col(
            $wpdb->prepare(
                    'SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = %s AND `TABLE_NAME` = %s', DB_NAME, $wpdb->prefix . 'fpropdf_layouts'
            )
    );

    if (!in_array('formats', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN formats LONGTEXT CHARACTER SET utf8');
    }
    if (!in_array('passwd', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN passwd VARCHAR(255)');
    }
    if (!in_array('lang', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN lang INT(3) UNSIGNED NOT NULL DEFAULT "0"');
    }
    if (!in_array('name_email', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN name_email VARCHAR(255)');
    }
    if (!in_array('restrict_user', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN restrict_user TEXT CHARACTER SET utf8');
    }
    if (!in_array('restrict_role', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN restrict_role TEXT CHARACTER SET utf8');
    }
    if (!in_array('default_format', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN default_format VARCHAR(255) NOT NULL DEFAULT "pdf"');
    }
    if (!in_array('add_att', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN add_att INT(3) UNSIGNED NOT NULL DEFAULT "0"');
    }
    if (!in_array('add_att_ids', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN add_att_ids VARCHAR(255) NOT NULL DEFAULT "all"');
    }

    $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` MODIFY COLUMN `formats` LONGTEXT');
    $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` MODIFY COLUMN `data` LONGTEXT');

    $wpdb->query("UPDATE $wpdb->options SET `autoload`='no' WHERE `option_name` LIKE 'fpropdf_layout_%'");

    update_option('fpropdf_version', FPROPDF_VERSION);

    if (!get_option('fpropdf_licence')) {
        update_option('fpropdf_licence', 'TRIAL' . strtoupper(FPROPDF_SALT));
    }

    if (get_option('fpropdf_restrict_remote_requests')) {
        update_option('fpropdf_enable_local', '1');
    }
}

register_activation_hook(__FILE__, 'fpropdf_myplugin_activate');

if (!get_option('fpropdf_version')) {
    $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . $wpdb->prefix . 'fpropdf_fields` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `field_key` varchar(255) CHARACTER SET utf8 NOT NULL,
    `field_id` int(11) NOT NULL,
    `form_id`  int(11) NOT NULL,
    PRIMARY KEY (`ID`)
    ) CHARACTER SET utf8'
    );

    $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . $wpdb->prefix . 'fpropdf_tmp` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `form_id` int(11) NOT NULL,
    `layout_id` int(11) NOT NULL,
    `entry_id` int(11) NOT NULL,
    `path`varchar(255) NOT NULL,
    `signatures` LONGTEXT CHARACTER SET utf8 NOT NULL,
    PRIMARY KEY (`ID`)
    ) CHARACTER SET utf8'
    );

    update_option('fpropdf_version', FPROPDF_VERSION);
}

require_once __DIR__ . '/class.php';

// Plugin base url
$wpfx_url = trailingslashit(WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)));

// Generate file
function wpfx_output($form, $content) {
    $form = FPROPDF_FORMS_DIR . $form;
    $temp = tempnam(PROPDF_TEMP_DIR, 'fpropdfTmpFile');
    $file = fopen($temp, 'w');

    if ($file) {
        fwrite($file, $content);
        fclose($file);
        return $temp;
    } else {
        die('Can not open a temporary file for writing, verify the permissions.');
    }
}

function wpfx_download($content) {
    $temp = tempnam(PROPDF_TEMP_DIR, 'fpropdfTmpFile');
    $file = fopen($temp, 'w');

    if ($file) {
        fwrite($file, $content);
        fclose($file);

        return $temp;
    } else {
        die('Can not open a temporary file for writing, verify the permissions.');
    }
}

// Field mapping is performed here
function wpfx_extract($layout, $id, $custom = false) {
    global $wpdb;

    $layoutId = $layout;
    $id = intval($id); // Filter IDs

    $data = array();
    $array = array();

    // handle rental quotes form which is preceding inflatable
    if ($layout == 1) {
        $rows = $wpdb->get_results(
                $wpdb->prepare(
                        'SELECT `field_id` as id, `meta_value` as value FROM `' . $wpdb->prefix . 'frm_item_metas` WHERE `item_id` = %d OR `item_id` = %d', $id, $id - 1
                ), ARRAY_A
        );
    } else {
        $rows = $wpdb->get_results(
                $wpdb->prepare(
                        'SELECT `field_id` as id, `meta_value` as value FROM `' . $wpdb->prefix . 'frm_item_metas` WHERE `item_id` = %d', $id
                ), ARRAY_A
        );
    }

    $entry = FrmEntry::getOne($id, true);
    $entryId = $id;
    $formId = $entry->form_id;
    $fields = FrmField::get_all_for_form($entry->form_id, '', 'include');

    if (isset($entry->post_id) && $entry->post_id && class_exists('FrmProEntryMetaHelper')) {
        foreach ($fields as $field) {
            if (isset($field->field_options['post_field']) && $field->field_options['post_field']) {
                $rows[] = array(
                    'id' => $field->id,
                    'value' => FrmProEntryMetaHelper::get_post_or_meta_value(
                            $entry, $field, array()
                    ),
                );
            }
        }
    }

    foreach ($rows as $index => $row) {

        $data = $wpdb->get_row(
                $wpdb->prepare(
                        'SELECT * FROM `' . $wpdb->prefix . 'frm_fields` WHERE `id` = %d', intval($row['id'])
                ), ARRAY_A
        );

        if (!$data) {
            continue;
        }

        $field_options = array();
        if (isset($data['field_options'])) {
            $field_options = @unserialize($data['field_options']);
        }

        if ($data['type'] == 'image' || ($data['type'] == 'url' && isset($field_options['show_image']) && $field_options['show_image'] == '1' && preg_match('/(\.(?i)(jpg|jpeg|png|gif))$/', $row['value']))) {
            $url = $row['value'];
            $request = wp_remote_get($url);
            if (!is_wp_error($request)) {
                $rows[$index]['value'] = implode(
                        ':',
                        array(
                            'FPROPDF_IMAGE_FIELD',
                            basename($url),
                            base64_encode(wp_remote_retrieve_body($request)),
                        )
                );
            }
        }
        if ($data['type'] == 'file') {
            $files = @unserialize($row['value']);
            if (!$files || ($files && !is_array($files))) {
                $files = array($row['value']);
            }
            if ($files && is_array($files) && count($files)) {
                $image = false;
                foreach ($files as $filesIndex => $file) {
                    $path = get_attached_file($file);
                    if (preg_match('/\.(jpe?g|png|gif)$/i', $path)) {
                        $image = $path;
                        $rows[$index]['value'] = 'FPROPDF_IMAGE:' . str_replace(ABSPATH, '/', $path);
                    }
                }
                if (!$image) {
                    $urls = array();
                    foreach ($files as $file) {
                        $urls[] = wp_get_attachment_url($file);
                    }
                    $rows[$index]['value'] = implode(' ', $urls);
                }
            }
        }
        if (( $data['type'] == 'data' ) || ( $data['type'] == 'checkbox' )) {
            foreach ($fields as $field) {
                if ($field->id != $row['id']) {
                    continue;
                }
                $embedded_field_id = ( $entry->form_id != $field->form_id ) ? 'form' . $field->form_id : 0;
                $atts = array(
                    'type' => $field->type, 'post_id' => $entry->post_id,
                    'show_filename' => true, 'show_icon' => true, 'entry_id' => $entry->id,
                    'embedded_field_id' => $embedded_field_id,
                );

                if ($data['type'] == 'data') {
                    $rows[$index]['value'] = FrmEntriesHelper::prepare_display_value($entry, $field, $atts);
                } else {
                    $rows[$index]['value'] = $entry->metas[$field->id];
                }
            }
        }
    }

    $results = $wpdb->get_results(
            $wpdb->prepare(
                    'SELECT `id`, `description` AS `value` FROM `' . $wpdb->prefix . 'frm_fields` WHERE `type` = "html" AND `form_id` = %d', intval($entry->form_id)
            ), ARRAY_A
    );
    foreach ($results as $buf) {
        $s = $buf['value'];
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_COMPAT | ENT_HTML401, 'UTF-8');
        $buf['value'] = $s;
        $rows[] = $buf;
    }

    $row = $wpdb->get_row(
            $wpdb->prepare(
                    'SELECT * FROM `' . $wpdb->prefix . 'frm_items` WHERE id = %d', $id
            ), ARRAY_A
    );
    if (!$row) {
        $row = array();
    }

    $description = array();
    $description_data = isset($row['description']) && $row['description'] ? @unserialize($row['description']) : '';
    if (is_array($description_data)) {
        $description = $description_data;
    }

    $referrer = '';
    if (isset($description['referrer']) && @preg_match('/Referer +\d+\:[ \t]+([^\n\t]+)/', $description['referrer'], $m)) {
        $referrer = $m[1];
    } else {
        $referrer = isset($description['referrer']) ? $description['referrer'] : '';
    }

    $rows[] = array(
        'id' => 'FPROPDF_ITEM_KEY',
        'value' => isset($row['item_key']) ? $row['item_key'] : '',
    );
    $rows[] = array(
        'id' => 'FPROPDF_BROWSER',
        'value' => isset($description['browser']) ? $description['browser'] : '',
    );
    $rows[] = array(
        'id' => 'FPROPDF_IP',
        'value' => isset($row['ip']) ? $row['ip'] : '',
    );
    $rows[] = array(
        'id' => 'FPROPDF_CREATED_AT',
        'value' => isset($row['created_at']) ? get_date_from_gmt($row['created_at'], 'Y-m-d H:i:s') : '',
    );
    $rows[] = array(
        'id' => 'FPROPDF_UPDATED_AT',
        'value' => isset($row['updated_at']) ? get_date_from_gmt($row['updated_at'], 'Y-m-d H:i:s') : '',
    );
    $rows[] = array(
        'id' => 'FPROPDF_REFERRER',
        'value' => $referrer,
    );
    $rows[] = array(
        'id' => 'FPROPDF_USER_ID',
        'value' => isset($row['user_id']) ? $row['user_id'] : '',
    );
    $rows[] = array(
        'id' => 'FPROPDF_DATASET_ID',
        'value' => $entryId,
    );

    global $wpdb;
    $counter1 = $wpdb->get_var(
            $wpdb->prepare(
                    'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'frm_items WHERE form_id = %d AND id <= %d ORDER BY id ASC', intval($formId), intval($entryId)
            )
    );

    $counter2Key = 'fpropdf_layout_' . $layoutId . '_counter2_for_form_' . $formId;
    $counter2 = get_option($counter2Key);
    if (!$counter2) {
        $counter2 = 1;
    }
    $counter2KeyItem = 'fpropdf_layout_' . $layoutId . '_counter2_for_form_' . $formId . '_entry_' . $entryId;
    if (!get_option($counter2KeyItem)) {
        if (add_option($counter2Key, $counter2 + 1, '', 'no') === false) {
            update_option($counter2Key, $counter2 + 1);
        }
        if (add_option($counter2KeyItem, $counter2, '', 'no') === false) {
            update_option($counter2KeyItem, $counter2);
        }
    } else {
        $counter2 = get_option($counter2KeyItem);
    }

    $rows[] = array(
        'id' => 'FPROPDF_COUNTER1',
        'value' => $counter1,
    );
    $rows[] = array(
        'id' => 'FPROPDF_COUNTER2',
        'value' => $counter2,
    );

    $data = array();

    // get data
    foreach ($rows as $row) {
        $key = $row['id'];

        if (is_array($row['value'])) {
            $new_array = array();
            foreach ($row['value'] as $kk => $arr_val) {
                $new_array[$kk] = stripslashes($arr_val);
            }
            $val = $new_array;
        } else {

            $sig_data = $wpdb->get_row(
                    $wpdb->prepare(
                            'SELECT `type` FROM `' . $wpdb->prefix . 'frm_fields` WHERE `id` = %d', intval($row['id'])
                    ), ARRAY_A
            );

            if (isset($sig_data['type']) && $sig_data['type'] == 'signature') {
                $val = $row['value'];
            } else {
                $val = stripslashes($row['value']);
            }
        }

        $found = false;
        foreach ($data as $dataKey => $values) {
            if ($values[0] == $key) {
                $found = true;
                $data[$dataKey][1] = $val;
            }
        }
        if (!$found) {
            $data[] = array($key, $val);
        }
    }

    switch ($layout) {
        case 1: // inflatable app
            $array = array(
                1135 => 50, 1139 => 73, 1131 => 60, 1140 => 72, 1163 => 74, 1150 => 53, 1125 => 78, 1125 => 79,
                1124 => 82, 1130 => 56, 1127 => 57, 1128 => 59, 1363 => 'List', 1168 => 393, 1147 => 125,
                1148 => 216, 1462 => 151, 1462 => 31,
            ); // last one is date filling
            break;

        case 2: // business quote
            $array = array(
                845 => 71, 848 => 349, 826 => 378, 923 => 389, 876 => 491, 828 => 492, 847 => 489, 830 => 50,
                837 => 102, 928 => 60, 1052 => 346, 844 => 73, 927 => 53, 925 => 74, 932 => 72, 853 => 75,
                854 => 78, 840 => 79, 856 => 80, 855 => 82, 881 => 56, 882 => 57, 883 => 58, 884 => 59,
                857 => 91, 859 => 92, 858 => 93, 860 => 95,
            );
            break;

        case 3: // use custom layout
            $array = $custom;
            break;
    }

    // Prepare list for fdf forming in case of missing fields
    $awesome = array();
    if (is_array($array)) {
        foreach ($array as $datakey => $fdfKey) {
            if (isset($fdfKey[0]) && $fdfKey[0] == 'FPROPDF_DYNAMIC' && isset($fdfKey[2]) && $fdfKey[2] != '') {

                $fdfKey[2] = str_replace('[id]', $entryId, $fdfKey[2]);

                $value = do_shortcode($fdfKey[2]);

                if (class_exists('FrmProContent') && class_exists('FrmProDisplaysHelper')) {
                    $entry = FrmEntry::getOne($entryId);
                    $shortcodes = FrmProDisplaysHelper::get_shortcodes($value, $formId);
                    $value = FrmProContent::replace_shortcodes($value, $entry, $shortcodes, true);
                }

                $awesome[] = array($fdfKey[1], $value);
            } else {
                $found = false;
                foreach ($data as $values) {
                    if ((fpropdf_field_id_to_key($values[0]) == fpropdf_field_id_to_key($fdfKey[0])) || (($values[0]) == ($fdfKey[0]))) {
                        $awesome[] = array($fdfKey[1], $values[1]);
                        $found = true;
                    }
                }
                if (!$found) {
                    $awesome[] = array($fdfKey[1], '');
                }
            }
        }
    }

    add_filter('fpropdf_wpfx_extract_fields', 'fpropdf_wpfx_extract_fields');
    $awesome = apply_filters('fpropdf_wpfx_extract_fields', $awesome, $entry);

    return $awesome;
}

function fpropdf_wpfx_extract_fields($data) {
    return $data;
}

function fpropdf_is_activated() {
    if (defined('FPROPDF_IS_MASTER')) {
        return true;
    }
    $code = get_option('fpropdf_licence');
    return $code;
}

function fpropdf_is_trial() {
    $code = get_option('fpropdf_licence');
    return ($code && preg_match('/^TRIAL/', $code));
}

function fpropdf_check_code($code, $update = 0) {
    $request = wp_remote_post(
            FPROPDF_SERVER . 'licence/check.php',
            array(
                'method' => 'POST',
                'body' => array(
                    'salt' => FPROPDF_SALT,
                    'code' => $code,
                    'update' => $update,
                    'site_url' => site_url('/'),
                ),
            )
    );

    if (is_wp_error($request)) {
        throw new Exception('Server did not return any results. Please try again later.');
    } else {
        $result = json_decode(wp_remote_retrieve_body($request));
        if ($result->activated) {
            if ($update) {
                update_option('fpropdf_licence', $code);
            }
            return true;
        }
    }
    throw new Exception('This licence code is not valid.');
}

function wpfx_stripslashes_array($array) {
    if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
        return is_array($array) ? array_map('wpfx_stripslashes_array', $array) : stripslashes($array);
    } else {
        return $array;
    }
}

function wpfx_addslashes_array($array) {
    if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
        return $array;
    } else {
        return is_array($array) ? array_map('wpfx_addslashes_array', $array) : addslashes($array);
    }
}

define('FPROPDF_SERVER', 'http://www.idealchoiceinsurance.com/wp-content/plugins/fpropdf/');
global $wpdb;
define('FPROPDF_SALT', md5(NONCE_SALT . $wpdb->prefix));

// Admin Options page
function wpfx_admin() {
    global $wpfx_url;

    if (class_exists('FrmXMLHelper')) {
        if (get_option('fpropdf_installed_version') >= 20000) {
            if (!get_option('fpropdf_demo_imported')) {
                fpropdf_restore_backup(dirname(__FILE__) . '/demo.json', 990);
                update_option('fpropdf_demo_imported', 1);
            }
        }
    }

    if (isset($_FILES['postdata'])) {
        $_POST['wpfx_savecl'] = 1;
        $file = isset($_FILES['postdata']['tmp_name']) ? $_FILES['postdata']['tmp_name'] : ''; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Reason: https://github.com/WordPress/WordPress-Coding-Standards/issues/1720
        if ($file && file_exists($file)) {
            $data = file_get_contents($file);
            @unlink($file);
            $output = json_decode($data, true);
            if ($output) {
                foreach ($output as $k => $v) {
                    $_POST[$k] = $v;
                    $_REQUEST[$k] = $v;
                }
            }
        }
    }

    $wpfx_fdf = new FDFMaker();
    $get_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';

    echo '<div class = "parent formidable-pro-fpdf formidable-pro-fpdf-tab-' . esc_attr($get_tab) . '">';
    echo '<div class = "_first _left">';
    echo '<h1>Formidable PRO2PDF</h1>';

    if (version_compare(PHP_VERSION, '5.3.0', '<')) {
        echo '<div class="error"><p>This plugin requires PHP version 5.3 or higher. Your version is ' . PHP_VERSION . '. Please upgrade your PHP installation.</p></div>';
        exit;
    }

    if (isset($_GET['action']) && ( $_GET['action'] == 'deactivatekey' )) {
        update_option('fpropdf_licence', 'TRIAL' . strtoupper(FPROPDF_SALT));
        echo '<div class="updated"><p>The licence key has been deactivated.</p></div>';
    }

    // Start activating
    if (isset($_POST['action']) && $_POST['action'] == 'activate-fpropdf') {
        try {
            $code = isset($_POST['activation-code']) ? trim(sanitize_text_field(wp_unslash($_POST['activation-code']))) : '';
            if (!$code) {
                throw new Exception('Please paste the activation code into the text field.');
            }
            fpropdf_check_code($code, 2);
            echo '<div class="updated" style="margin-left: 0;"><p>Thanks for activating Formidable PRO2PDF! You are now using the full version of the plugin.</p></div>';
        } catch (Exception $e) {
            echo '<div class="error" style="margin-left: 0;"><p>' . esc_html($e->getMessage()) . ' <a href="#" class="fpropdf-activate">Click here</a> to retry.</p></div>';
        }
    }

    // start checking for errors
    $errors = array();

    try {
        $tmp = FPROPDF_FORMS_DIR;
        if (!file_exists($tmp)) {
            throw new Exception('Folder ' . $tmp . ' could not be created. Please create it using FTP, and set its permissions to 777.');
        }
        $tmp = FPROPDF_FORMS_DIR;
        if (!is_writable($tmp)) {
            throw new Exception('Folder ' . $tmp . ' should be writable. Please change its permissions to 777.');
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }

    try {
        $tmp = FPROPDF_BACKUPS_DIR;
        if (!file_exists($tmp)) {
            throw new Exception('Folder ' . $tmp . ' could not be created. Please create it using FTP, and set its permissions to 777. <br /> It is required to have automatic backups of your field maps.');
        }
        $tmp = FPROPDF_BACKUPS_DIR;
        if (!is_writable($tmp)) {
            throw new Exception('Folder ' . $tmp . ' should be writable. Please change its permissions to 777. <br /> It is required to have automatic backups of your field maps.');
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }

    try {
        $tmp = __DIR__ . '/fields';
        if (!is_writable($tmp)) {
            throw new Exception('Folder ' . $tmp . ' should be writable. Please change its permissions to 777.');
        }
        $tmp = PROPDF_TEMP_DIR;
        if (!is_writable($tmp)) {
            throw new Exception('Folder ' . $tmp . ' should be writable. Please change its permissions to 777.');
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }

    try {
        if (!class_exists('FrmAppHelper')) { // Check if Formidable class exists
            throw new Exception('Formidable PRO2PDF requires Formidable Forms plugin installed and activated. Please <a href="plugins.php">activate it</a>.');
        }
        $tmp = $version = FrmAppHelper::$plug_version;
        $version = explode('.', $version);
        if (intval($version[0]) < 2) {
            throw new Exception('Formidable PRO2PDF requires the latest version of Formidable Forms plugin (or at least 2.0.9). Your version is ' . $tmp . '. Please <a href="update-core.php">update it</a>.');
        } elseif (intval($version[0]) == 2) {
            if (intval($version[1]) == 0 && intval($version[2]) < 9) {
                throw new Exception('Formidable PRO2PDF requires the latest version of Formidable Forms plugin (or at least 2.0.9). Your version is ' . $tmp . '. Please <a href="update-core.php">update it</a>.');
            }
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }

    try {
        if (!fpropdf_is_activated() || fpropdf_is_trial()) {
            $msg = 'You can generate only 1 PDF form. You can <a href="#" class="fpropdf-activate">activate Formidable PRO2PDF</a> to use more forms.';
            throw new Exception($msg);
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }

    try {
        if (get_option('fpropdf_enable_local')) {
            $msg = "Local PDFTK can't be activated, due:";
            $msg .= '<ul>';
            if (ini_get('safe_mode')) {
                $msg .= '<li><code>safe_mode</code> must be turned off</li>';
            }

            $msg .= '</ul>';
            if (ini_get('safe_mode')) {
                throw new Exception($msg);
            }
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }

    try {
        if (!function_exists('mb_convert_encoding') && !function_exists('iconv')) {
            throw new Exception('Your server has to have PHP <code>MB</code> or <code>iconv</code> extension installed.');
        }
        if (!function_exists('curl_init')) {
            throw new Exception('Your server has to have <code>Curl</code> extension installed.');
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }

    try {
        if (!fpropdf_is_activated()) {
            throw new Exception("You are using a free version of the plugin. To unlock additional functions (no need of installing pdftk, pretty field selection and many others), please <a href='#' class='fpropdf-activate'>activate Formidable PRO2PDF</a>.");
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }

    foreach ($errors as $error) {
        echo '<div class="error" style="margin-left: 0;"><p>' . wp_kses_post($error) . '</p></div>';
    }

    if (get_transient('fpropdf_notification_new_layout')) {
        echo '<div class="updated" style="margin-left: 0;"><p>Layout has been added. You can now use it.</p></div>';
        delete_transient('fpropdf_notification_new_layout');
    }

    if (isset($_POST['action']) && $_POST['action'] == 'upload-pdf-file') {
        if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'fpropdf_upload_pdf_nonce')) {
            try {
                if (isset($_FILES['upload-pdf']['name']) && isset($_FILES['upload-pdf']['tmp_name'])) {
                    $file_name = sanitize_text_field(wp_unslash($_FILES['upload-pdf']['name']));
                    $tmp_name = $_FILES['upload-pdf']['tmp_name']; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Reason: https://github.com/WordPress/WordPress-Coding-Standards/issues/1720

                    $filetype = wp_check_filetype($file_name, array('pdf' => 'application/pdf'));
                    if ($filetype ['type'] != 'application/pdf') {
                        throw new Exception('The file should be a PDF file and have .pdf file extension. Please <a href="#" class="upl-new-pdf">upload another file</a>.');
                    }
                    @move_uploaded_file($tmp_name, FPROPDF_FORMS_DIR . $file_name);
                    echo '<div class="updated" style="margin-left: 0;"><p><b>' . esc_html($file_name) . '</b> has been uploaded. You can now use it in your layouts.</p></div>';
                } else {
                    throw new Exception('Please select a PDF file');
                }
            } catch (Exception $e) {
                echo '<div class="error" style="margin-left: 0;"><p>' . wp_kses_post($e->getMessage()) . '</p></div>';
            }
        }
    }

    // Handle user input
    if (isset($_POST['wpfx_submit']) && intval($_POST['wpfx_submit']) == 1) {
        echo "<div align = 'center'>";
        echo "<form method = 'POST' action = '" . esc_attr($wpfx_url) . "generate.php' target='_blank' id = 'dform' >";

        $filename = '';
        $filledfm = '';
        $wpfx_dataset = isset($_POST['wpfx_dataset']) ? intval($_POST['wpfx_dataset']) : 0;
        $wpfx_layout = isset($_POST['wpfx_layout']) ? intval($_POST['wpfx_layout']) : 0;

        $layout = wpfx_readlayout($wpfx_layout - 9);
        global $currentLayout;
        $currentLayout = $layout;

        // Generate pdf
        switch ($wpfx_layout) {
            case 1:
                $filename = wpfx_download($wpfx_fdf->makeInflatablesApp(wpfx_extract(1, $wpfx_dataset), FPROPDF_FORMS_DIR . 'InflatableApp.pdf'));
                $filledfm = 'InflatableApp.pdf';
                break;

            case 2:
                $filename = wpfx_download($wpfx_fdf->makeBusinessQuote(wpfx_extract(2, $wpfx_dataset), FPROPDF_FORMS_DIR . 'BusinessQuote.pdf'));
                $filledfm = 'BusinessQuote.pdf';
                break;

            default:
                $unicode = isset($layout['lang']) && $layout['lang'] == '1' ? true : false;
                $pdf = FPROPDF_FORMS_DIR . $layout['file'];
                $layout_id = $wpfx_layout - 9;
                $entry_id = $wpfx_dataset;
                global $wpdb;
                $tmpFDF = $wpdb->get_row(
                        $wpdb->prepare(
                                'SELECT * FROM `' . $wpdb->prefix . 'fpropdf_tmp` WHERE `layout_id` = %d AND `entry_id` = %d', $layout_id, $entry_id
                        ), ARRAY_A
                );

                if ($tmpFDF && file_exists($tmpFDF['path'])) {
                    $filename = $tmpFDF['path'];
                    if (isset($tmpFDF['signatures']) && $tmpFDF['signatures']) {
                        global $fpropdfSignatures;
                        $fpropdfSignatures = unserialize($tmpFDF['signatures']);
                    }
                } else {
                    $filename = wpfx_download($wpfx_fdf->makeFDF(wpfx_extract(3, $wpfx_dataset, $layout['data']), $pdf, $unicode));
                }
                $filledfm = $layout['file'];
                break;
        }

        $filledfm = FPROPDF_FORMS_DIR . $filledfm;

        echo '<input type = "hidden" name = "desired" value = "' . esc_attr($filledfm) . '" />';
        echo '<input type = "hidden" name = "actual"  value = "' . esc_attr($filename) . '" />';
        echo '<input type = "hidden" name = "lock" value = "' . esc_attr($layout['visible']) . '" />';
        echo '<input type = "hidden" name = "passwd" value = "' . esc_attr($layout['passwd']) . '" />';
        echo '<input type = "hidden" name = "lang" value = "' . esc_attr($layout['lang']) . '" />';
        echo '<input type = "hidden" name = "filename" value = "' . esc_attr($layout['name']) . '" />';
        echo '<input type = "hidden" name = "default_format" value = "' . esc_attr($layout['default_format']) . '" />';
        echo '<input type = "hidden" name = "name_email" value = "' . esc_attr($layout['name_email']) . '" />';
        echo '<input type = "hidden" name = "restrict_user" value = "' . esc_attr($layout['restrict_user'] ? $layout['restrict_user'] : get_option('fpropdf_restrict_user')) . '" />';
        echo '<input type = "hidden" name = "restrict_role" value = "' . esc_attr($layout['restrict_role'] ? $layout['restrict_role'] : get_option('fpropdf_restrict_role')) . '" />';
        echo '<input type = "submit" value = "Download" name = "download" id = "hideme" />';
        echo '</form>';
        echo '</div>';

        unset($_POST);

        if (defined('FPROPDF_IS_GENERATING')) {
            return;
        }
    } elseif (isset($_POST['wpfx_savecl']) && sanitize_text_field(wp_unslash($_POST['wpfx_savecl']))) { // Save a custom layout here
        if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'fpropdf_wpfx_savecl')) {

            $layout = array();
            $formats = array();

            $clform = isset($_POST['clfrom']) && is_array($_POST['clfrom']) ? $_POST['clfrom'] : array(); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Reason: sanitized later
            foreach ($clform as $index => $value) {
                $to = isset($_POST['clto'][$index]) ? sanitize_text_field(wp_unslash($_POST['clto'][$index])) : '';

                $_f = isset($_POST['format'][$index]) ? sanitize_text_field(wp_unslash($_POST['format'][$index])) : '';
                if (in_array($_f, array('curDate', 'date', 'number_f'), true)) {
                    $_f = isset($_POST['select_for_' . $_f][$index]) ? sanitize_text_field(wp_unslash($_POST['select_for_' . $_f][$index])) : '';
                }

                $formats[] = array(
                    $to,
                    $_f,
                    isset($_POST['repeatable_field'][$index]) ? sanitize_textarea_field(wp_unslash($_POST['repeatable_field'][$index])) : '',
                    isset($_POST['checkbox_field'][$index]) ? sanitize_text_field(wp_unslash($_POST['checkbox_field'][$index])) : '',
                    isset($_POST['image_field'][$index]) ? sanitize_text_field(wp_unslash($_POST['image_field'][$index])) : '',
                    isset($_POST['address_field'][$index]) ? sanitize_textarea_field(wp_unslash($_POST['address_field'][$index])) : '',
                    isset($_POST['credit_card_field'][$index]) ? sanitize_textarea_field(wp_unslash($_POST['credit_card_field'][$index])) : '',
                    isset($_POST['image_rotation'][$index]) ? sanitize_text_field(wp_unslash($_POST['image_rotation'][$index])) : '',
                );

                if (strlen(trim($value)) && strlen(trim($to))) {
                    $layout[] = array($value, $to, isset($_POST['dynamic_field'][$index]) ? sanitize_text_field(wp_unslash($_POST['dynamic_field'][$index])) : '');
                }
            }

            // Get desired dataset name
            // "clname" can be anything and does not need to be filtered
            $clname = isset($_POST['clname']) ? explode('_', sanitize_text_field(wp_unslash($_POST['clname']))) : array();

            $index = 0;
            if (isset($clname[1])) {
                $index = intval($clname[1]);
            }

            $wpfx_layout = isset($_POST['wpfx_layout']) ? intval($_POST['wpfx_layout']) : 0;
            $add_att = isset($_POST['wpfx_add_att']) ? sanitize_text_field(wp_unslash($_POST['wpfx_add_att'])) : '';
            $default_format = isset($_POST['wpfx_default_format']) ? sanitize_text_field(wp_unslash($_POST['wpfx_default_format'])) : '';
            $name_email = isset($_POST['wpfx_name_email']) ? sanitize_text_field(wp_unslash(($_POST['wpfx_name_email']))) : '';
            $restrict_user = isset($_POST['wpfx_restrict_user']) ? sanitize_text_field(wp_unslash($_POST['wpfx_restrict_user'])) : '';
            $restrict_role = isset($_POST['wpfx_restrict_role']) ? sanitize_text_field(wp_unslash($_POST['wpfx_restrict_role'])) : '';
            $add_att_ids = isset($_POST['wpfx_add_att_ids']) && is_array($_POST['wpfx_add_att_ids']) ? implode(',', array_map('sanitize_text_field', wp_unslash($_POST['wpfx_add_att_ids']))) : '';
            $wpfx_clname = isset($_POST['wpfx_clname']) ? sanitize_text_field(wp_unslash($_POST['wpfx_clname'])) : '';
            $wpfx_clfile = isset($_POST['wpfx_clfile']) ? sanitize_text_field(wp_unslash($_POST['wpfx_clfile'])) : '';
            $wpfx_clform = isset($_POST['wpfx_clform']) ? sanitize_text_field(wp_unslash($_POST['wpfx_clform'])) : '';
            $passwd = isset($_POST['wpfx_password']) ? sanitize_text_field(wp_unslash($_POST['wpfx_password'])) : '';
            $lang = isset($_POST['wpfx_lang']) ? sanitize_text_field(wp_unslash($_POST['wpfx_lang'])) : '';
            $wpfx_layout_visibility = isset($_POST['wpfx_layout_visibility']) ? intval($_POST['wpfx_layout_visibility']) : 0;

            if (isset($_POST['update']) && ($_POST['update'] == 'update')) {
                $r = wpfx_updatelayout($wpfx_layout - 9, $wpfx_clname, base64_decode(urldecode($wpfx_clfile)), $wpfx_layout_visibility, $wpfx_clform, $index, $layout, $formats, $add_att, $passwd, $lang, $add_att_ids, $default_format, $name_email, $restrict_role, $restrict_user);
            } else {
                $r = wpfx_writelayout($wpfx_clname, base64_decode(urldecode($wpfx_clfile)), $wpfx_layout_visibility, $wpfx_clform, $index, $layout, $formats, $add_att, $passwd, $lang, $add_att_ids, $default_format, $name_email, $restrict_role, $restrict_user);
            }

            global $wpdb;

            if ($r) {
                echo '<div class="updated" style="margin-left: 0;"><p>Layout has been saved!</p></div>';
            } else {
                echo '<div class="error" style="margin-left: 0;"><p>Failed to save this custom layout :( <br />' . esc_html($wpdb->last_error) . '</p></div>';
            }
        }

        echo '<script>window.location.href="' . esc_url_raw('?page=fpdf&wpfx_form=' . $wpfx_clform . '&wpfx_layout=' . ($r + 9) . '') . '";</script>';
        exit;
    }

    if (!is_plugin_active('formidable/formidable.php') && !is_plugin_active('formidable-2/formidable.php')) {
        echo '<div class="error" style="margin-left: 0;"><p>Formidable plugin not found</p></div>';
        echo '</div></div>';
        return;
    }

    $forms = wpfx_getforms();

    $has_forms = false;
    foreach ($forms as $key => $data) {
        if (($key == '9wfy4z') || ($key == '218da3') || (strtotime($data[1]) > strtotime('01 March 2013'))) {
            $has_forms = true;
        }
    }
    if (!$has_forms) {
        echo '<div class="error" style="margin-left: 0;"><p>You have no Formidable Forms. Please <a href="admin.php?page=formidable&frm_action=new" class="button-primary">Add a Formidable Form</a></p></div>';
        echo '</div></div>';
        return;
    }

    $currentTab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
    if (!in_array($currentTab, array('templates', 'debug', 'general', 'forms', 'settings', 'backups'), true)) {
        $currentTab = 'general';
    }

    if ($currentTab == 'general') {
        echo '<script>window.fpropdf_ajax_preview_nonce = "' . wp_create_nonce('fpropdf_ajax_preview_nonce') . '";</script>';
        echo '<script>window.fpropdf_upload_pdf_nonce = "' . wp_create_nonce('fpropdf_upload_pdf_nonce') . '";</script>';
        echo '<script>window.fpropdf_wpfx_peeklayout_nonce = "' . wp_create_nonce('fpropdf_wpfx_peeklayout_nonce') . '";</script>';
        echo '<script>window.fpropdf_wpfx_killlayout_nonce = "' . wp_create_nonce('fpropdf_wpfx_killlayout_nonce') . '";</script>';
        echo '<script>window.fpropdf_wpfx_getdataset_nonce = "' . wp_create_nonce('fpropdf_wpfx_getdataset_nonce') . '";</script>';
        echo '<script>window.fpropdf_wpfx_duplayout_nonce = "' . wp_create_nonce('fpropdf_wpfx_duplayout_nonce') . '";</script>';
        echo '<script>window.fpropdf_wpfx_remove_pdf_nonce = "' . wp_create_nonce('fpropdf_wpfx_remove_pdf_nonce') . '";</script>';
    }

    $tabs = array(
        'general' => 'Export',
        'forms' => 'Activated Forms',
        'settings' => 'Settings',
        'templates' => 'Templates',
        'backups' => 'Backups',
        'debug' => 'Under the Hood',
    );
    if (!fpropdf_check_user_role('administrator')) {
        unset($tabs['settings']);
    }
    echo '<div id="icon-themes" class="icon32"><br></div>';
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($tabs as $tab => $name) {
        if (!( fpropdf_is_activated() && !defined('FPROPDF_IS_MASTER') ) && ( $tab == 'forms' )) {
            continue;
        }
        if ($tab == 'forms') {
            if (get_option('fpropdf_licence') == 'OFFLINE_SITE') {
                continue;
            }
        }

        $class = ( $tab == $currentTab ) ? ' nav-tab-active' : '';
        echo '<a class="nav-tab' . $class . '" href="' . esc_url('?page=fpdf&tab=' . $tab . '') . '">' . esc_html($name) . '</a>';
    }
    echo '</h2>';

    if ($currentTab == 'debug') {
        fpropdf_debug_page();
        return;
    }

    if ($currentTab == 'settings') {
        fpropdf_settings_page();
        return;
    }

    if ($currentTab == 'backups') {
        fpropdf_backups_page();
        return;
    }

    if ($currentTab == 'templates') {
        fpropdf_templates_page();
        return;
    }

    if ($currentTab == 'forms') {

        $code = get_option('fpropdf_licence');
        if ($code && !fpropdf_is_trial()) {
            try {
                fpropdf_check_code($code, 1);
            } catch (Exception $e) {
                $e->getMessage();
            }
        }

        $this_site = new stdClass();
        $this_site->url = site_url('/');
        $this_site->site_salt = FPROPDF_SALT;
        $this_site->title = get_bloginfo('name');
        $this_site->not_active = true;
        $this_site->ip = isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : '';

        if (isset($_GET['action']) && $_GET['action'] == 'site_activate') {
            $request = wp_remote_post(
                    FPROPDF_SERVER . 'licence/licence-change.php',
                    array(
                        'method' => 'POST',
                        'body' => array(
                            'salt' => FPROPDF_SALT,
                            'code' => $code,
                            'action' => 'activate_site',
                            'title' => $this_site->title,
                            'url' => $this_site->url,
                            'site_url' => site_url('/'),
                        ),
                    )
            );

            if (is_wp_error($request)) {
                echo '<div class="error"><p>Unknown error. Please try again later.</p></div>';
            } else {
                $result = json_decode(wp_remote_retrieve_body($request));
                if (isset($result->success) && $result->success) {
                    echo '<div class="updated"><p>The site has been activated.</p></div>';
                } elseif (isset($result->error) && $result->error) {
                    echo '<div class="error"><p>' . esc_html($result->error) . '</p></div>';
                }
            }
        }

        if (isset($_GET['action']) && $_GET['action'] == 'form_activate') {
            $request = wp_remote_post(
                    FPROPDF_SERVER . 'licence/licence-change.php',
                    array(
                        'method' => 'POST',
                        'body' => array(
                            'salt' => FPROPDF_SALT,
                            'code' => $code,
                            'action' => 'activate_form',
                            'site_id' => isset($_GET['site']) ? intval($_GET['site']) : 0,
                            'form_id' => isset($_GET['form']) ? sanitize_text_field(wp_unslash($_GET['form'])) : '',
                            'title' => isset($_GET['title']) ? sanitize_text_field(wp_unslash($_GET['title'])) : '',
                            'site_url' => site_url('/'),
                        ),
                    )
            );

            if (is_wp_error($request)) {
                echo '<div class="error"><p>Unknown error. Please try again later.</p></div>';
            } else {
                $result = json_decode(wp_remote_retrieve_body($request));
                if ($result->success) {
                    echo '<div class="updated"><p>The form has been activated.</p></div>';
                } elseif ($result->error) {
                    echo '<div class="error"><p>' . esc_html($result->error) . '</p></div>';
                }
            }
        }

        if (isset($_GET['action']) && $_GET['action'] == 'form_deactivate') {
            $request = wp_remote_post(
                    FPROPDF_SERVER . 'licence/licence-change.php',
                    array(
                        'method' => 'POST',
                        'body' => array(
                            'salt' => FPROPDF_SALT,
                            'code' => $code,
                            'action' => 'deactivate_form',
                            'site_id' => isset($_GET['site']) ? intval($_GET['site']) : 0,
                            'form_id' => isset($_GET['form']) ? sanitize_text_field(wp_unslash($_GET['form'])) : '',
                            'site_url' => site_url('/'),
                        ),
                    )
            );

            if (is_wp_error($request)) {
                echo '<div class="error"><p>Unknown error. Please try again later.</p></div>';
            } else {
                $result = json_decode(wp_remote_retrieve_body($request));
                if ($result->success) {
                    echo '<div class="updated"><p>The form has been deactivated.</p></div>';
                } elseif ($result->error) {
                    echo '<div class="error"><p>' . esc_html($result->error) . '</p></div>';
                }
            }
        }

        if (isset($_GET['action']) && $_GET['action'] == 'site_deactivate') {
            $request = wp_remote_post(
                    FPROPDF_SERVER . 'licence/licence-change.php',
                    array(
                        'method' => 'POST',
                        'body' => array(
                            'salt' => FPROPDF_SALT,
                            'code' => $code,
                            'action' => 'deactivate_site',
                            'site_id' => intval($_GET['site']),
                            'site_url' => site_url('/'),
                        ),
                    )
            );

            if (is_wp_error($request)) {
                echo '<div class="error"><p>Unknown error. Please try again later.</p></div>';
            } else {
                $result = json_decode(wp_remote_retrieve_body($request));
                if ($result->success) {
                    echo '<div class="updated"><p>The site has been deactivated.</p></div>';
                } elseif ($result->error) {
                    echo '<div class="error"><p>' . esc_html($result->error) . '</p></div>';
                }
            }
        }

        $request = wp_remote_post(
                FPROPDF_SERVER . 'licence/info.php',
                array(
                    'method' => 'POST',
                    'body' => array(
                        'salt' => FPROPDF_SALT,
                        'code' => $code,
                    ),
                )
        );

        if (is_wp_error($request)) {
            echo "<div class='error'><p>Unknown error. Please try again later.</p></div>";
        } else {
            $result = json_decode(wp_remote_retrieve_body($request));
            $found = false;
            foreach ($result->sites as $site) {
                if ($site->site_salt == $this_site->site_salt && $this_site->url == $site->url) {
                    $found = true;
                }
            }
            if (!$found) {
                array_unshift($result->sites, $this_site);
            }

            $this_forms = array();
            foreach ($forms as $key => $form) {
                if (($key == '9wfy4z') || ($key == '218da3') || (strtotime($form[1]) > strtotime('01 March 2013'))) {
                    $this_form = new stdClass();
                    $this_form->form_id = $key;
                    $this_form->not_active = 1;
                    $this_form->title = $form[0];
                    $this_forms[] = $this_form;
                }
            }

            foreach ($result->sites as $site) {
                if ($site->site_salt == $this_site->site_salt && $this_site->url == $site->url) {
                    foreach ($this_forms as $form) {
                        $found = false;
                        if (property_exists($site, 'forms') && is_array($site->forms)) {
                            foreach ($site->forms as $site_form) {
                                if ($site_form->form_id == $form->form_id) {
                                    $found = true;
                                    $site_form->title = $form->title;
                                }
                            }
                        }
                        if (!$found) {
                            $site->forms[] = $form;
                        }
                    }
                }
            }

            $number_of_sites = count($result->sites);
            echo '<div class="updated"><p>';
            if (fpropdf_is_trial()) {
                echo 'You can activate only 1 form on this website. Please <a href="#" class="button-primary fpropdf-activate">upgrade</a> if you want to use more forms.';
            } else {
                echo 'Your licence key is <strong>' . esc_html($code) . '</strong>. <br /> It is valid until ' . date('m/d/Y', strtotime($result->licence->expires_on)) . ' <br />With this activation code, you can register up to <strong>' . esc_html($result->licence->sites) . '</strong> site' . ($result->licence->sites == 1 ? '' : 's') . ' and up to <strong>' . esc_html($result->licence->forms) . '</strong> form' . ($result->licence->forms == 1 ? '' : 's') . '. <a href="?page=fpdf&action=deactivatekey">Click here to deactivate this key.</a> </p><p>You have <strong>' . esc_html($result->sites_left) . '</strong> site' . (property_exists($result->licence, 'sites_left') && $result->licence->sites_left == 1 ? '' : 's') . ' and <strong>' . esc_html($result->forms_left) . '</strong> form' . (property_exists($result->licence, 'forms_left') && $result->licence->forms_left == 1 ? '' : 's') . ' left.';
                if ($number_of_sites > 1) {
                    echo '<a href="http://www.formidablepro2pdf.com/my-account/" target="_blank">Click to manage your activation key</a>';
                }
            }
            echo '</p></div>';

            echo '<ol class="fpropdf-sites">';
            if (!count($result->sites)) {
                echo '<li><i>you do not have any active sites</i></li>';
            } else {
                foreach ($result->sites as $site) {
                    if ($this_site->url == $site->url) {
                        echo '<li class="opt-' . (property_exists($site, 'not_active') && $site->not_active ? 'inactive' : 'active') . '">';
                        echo $site->url . ' (' . $site->title . ')';

                        if (property_exists($site, 'not_active') && $site->not_active) {

                            echo ' - not active. <a class="" href="?page=fpdf&tab=forms&action=site_activate" style="opacity: 1;">Activate this website</a>';
                            echo '</li>';
                            continue;
                        }

                        echo ' - active. <a class="" href="' . esc_url('?page=fpdf&action=site_deactivate&tab=forms&site=' . intval($site->site_id) . '') . '">Deactivate this website</a>';
                        if (!count($site->forms)) {
                            echo '<ul><li><i>no activated forms</i></li></ul>';
                        } else {
                            echo '<ul>';
                            foreach ($site->forms as $form) {
                                echo '<li class="opt-' . (property_exists($form, 'not_active') && $form->not_active ? 'inactive' : 'active') . '">';
                                echo $form->title;
                                if (property_exists($form, 'not_active') && $form->not_active) {
                                    echo ' - not active. <a class="" href="' . esc_url('?page=fpdf&action=form_activate&tab=forms&site=' . intval($site->site_id) . '&form=' . urlencode($form->form_id) . '&title=' . urlencode($form->title) . '') . '">Activate</a>';
                                } else {
                                    echo ' - active. <a class="" href="' . esc_url('?page=fpdf&action=form_deactivate&tab=forms&site=' . intval($site->site_id) . '&form=' . urlencode($form->form_id) . '') . '">Deactivate</a>';
                                }
                                echo '</li>';
                            }
                            echo '</ul>';
                        }
                        echo '</li>';
                    }
                }
            }

            echo '</ol>';
            echo '</div>';
        }
        return;
    }

    if (function_exists('add_thickbox')) {
        add_thickbox();
    }

    echo '<form method = "POST" id="frm-bg" data-limitdropdowns="' . intval(get_option('fpropdf_limit_dropdowns')) . '" data-automap="' . intval(get_option('fpropdf_automap')) . '" data-security="' . intval(fpropdf_enable_security()) . '" data-activated="' . intval(!fpropdf_is_trial()) . '" data-pdfaid="' . ( get_option('fpropdf_pdfaid_api_key') ? '1' : '0' ) . '">';
    echo '<table>';
    echo '<tr>';
    echo '<td width="300">Select the form to export data from:</td>';
    echo '<td colspan = "2"><select id = "wpfx_form" name = "wpfx_form">';

    $actual = array();

    // hardcode inflatable apps, business quote and new forms
    foreach ($forms as $key => $data) {
        if (($key == '9wfy4z') || ($key == '218da3') || (strtotime($data[1]) > strtotime('01 March 2013'))) {
            $allowed = array();
            global $wpdb;
            $rows = $wpdb->get_results(
                    $wpdb->prepare(
                            'SELECT * FROM ' . $wpdb->prefix . 'fpropdf_layouts WHERE `form` = %d', intval($data[2])
                    )
            );
            foreach ($rows as $row) {
                $allowed[] = $row->ID;
            }
            echo '<option value = "' . esc_attr($key) . '" ' . (isset($_GET['wpfx_form']) && $_GET['wpfx_form'] == $key ? ' selected="selected"' : '') . ' data-allowedlayouts="' . esc_attr(json_encode($allowed)) . '">' . esc_html($data[0]) . '</option>';
            $actual[$key] = $data;
        }
    }

    echo '</select> &nbsp; ';
    echo '<a class="button" target = "blank" href = "admin-ajax.php?action=frm_forms_preview" id = "wpfx_preview">Preview</a></td></tr>';
    echo '<tr><td>Select the dataset to export:</td>';
    echo '<td><select id = "wpfx_dataset" name = "wpfx_dataset">';
    echo '</select></td>';
    echo '<td></td></tr>';
    echo '<tr><td>Field Map to use:</td>';
    echo '<td colspan = "2"><select id = "wpfx_layout" name = "wpfx_layout">';
    echo '<option value = "3">New Field Map</option>';

    // Populate with custom saved layouts
    foreach (wpfx_getlayouts() as $key => $name) {
        echo '<option value = "' . esc_attr($key) . '">' . esc_html($name) . '</option>';
    }

    echo '</select></td></tr>';

    if (isset($_GET['wpfx_layout'])) {
        $get_wpfx_layout = sanitize_text_field(wp_unslash($_GET['wpfx_layout']));
        if ($get_wpfx_layout) {
            echo '<script>window.currentSelectedLayout = "' . intval($get_wpfx_layout) . '";</script>';
        }
    }

    if (fpropdf_is_activated() && !fpropdf_is_trial()) {
        echo '<tr><td></td><td> <label> <input type="checkbox" id="use-second-layout" /> Add a second dataset</label> </td></tr>';
        echo '<tr class="hidden-use-second">';
        echo '<td>Select the second form to export data from:</td>';
        echo '<td colspan = "2"><select id = "wpfx_form2" name = "wpfx_form2">';

        $actual = array();

        foreach ($forms as $key => $data) {
            if (($key == '9wfy4z') || ($key == '218da3') || (strtotime($data[1]) > strtotime('01 March 2013'))) {
                $allowed = array();
                global $wpdb;
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                'SELECT * FROM ' . $wpdb->prefix . 'fpropdf_layouts WHERE `form` = %d', intval($data[2])
                        )
                );
                foreach ($rows as $row) {
                    $allowed[] = $row->ID;
                }
                echo '<option value = "' . esc_attr($key) . '" data-allowedlayouts="' . esc_attr(json_encode($allowed)) . '">' . esc_html($data[0]) . '</option>';
                $actual[$key] = $data;
            }
        }

        echo '</select> &nbsp; ';
        echo '<a class="button" target = "blank" href = "admin-ajax.php?action=frm_forms_preview" id = "wpfx_preview2">Preview</a></td></tr>';
        echo '<tr class="hidden-use-second"><td>Select the second dataset to export:</td>';
        echo '<td><select id = "wpfx_dataset2" name = "wpfx_dataset2">';
        echo '</select></td>';
        echo '<td></td></tr>';
        echo '<tr class="hidden-use-second"><td>Second Field Map to use:</td>';
        echo '<td colspan = "2"><select id = "wpfx_layout2" name = "wpfx_layout2">';
        echo '<option value = "3">No Field Map</option>';
        foreach (wpfx_getlayouts() as $key => $name) {
            echo '<option value = "' . esc_attr($key) . '">' . esc_html($name) . '</option>';
        }

        echo '</select></td></tr>';
    }

    echo '<tr id="tr-export" style="display: none;"><td colspan = "3" align = "center"><hr /><a href="#" target="_blank" id="main-export-btn" class="button-primary">Export to PDF</a>';
    if (get_option('fpropdf_pdfaid_api_key')) {
        echo '&nbsp; <a href="#" target="_blank" id="main-export-btn-docx" class="button-primary">Export to DOCX</a>';
    }
    echo '</td></tr>';
    echo '</table>';
    echo '</form>';
    echo '</div>';
    echo '<div class = "_second _left"><div id = "loader"><img src = "' . esc_url($wpfx_url) . 'res/loader.gif" /> Loading layout... Please wait...</div><div class = "layout_builder" style="width: auto;"><h2>Field Map Designer</h2>';
    echo '<form method = "POST" id = "wpfx_layout_form"  enctype="multipart/form-data">';
    echo '<table>';
    echo '<tr><td>Name of Field Map (will be used as default filename):</td><td><input required="required" name = "wpfx_clname" id = "wpfx_clname" /></td></tr>';
    echo '<tr><td>Select PDF file to work with:</td><td><select name = "wpfx_clfile" id = "wpfx_clfile">';

    // Print existing PDF files
    $handle = opendir(FPROPDF_FORMS_DIR);
    if ($handle) {
        $files = array();
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..' && strtolower(substr($file, strrpos($file, '.') + 1)) == 'pdf') {
                $files[] = $file;
            }
        }
        natcasesort($files);
        foreach ($files as $file) {
            echo '<option value = "' . esc_attr(base64_encode($file)) . '">' . esc_html($file) . '</option>';
        }
        closedir($handle);
    } else {
        echo '<option>Error: can not list directory</option>';
    }
    echo '</select></td></tr>
    <tr><td></td><td>
    <a href="#" class="upl-new-pdf button-primary" style="margin: 1px;">Upload a PDF file</a>
    <input type="button" class="remove-pdf button" style="margin: 1px;" value="Remove this PDF file" />
    </td></tr>';
    echo '<tr><td>Select Form to work with:</td><td><select name = "wpfx_clform" id = "wpfx_clform">';

    $forms = wpfx_getforms();
    $actual = array();

    foreach ($forms as $key => $data) {
        if (($key == '9wfy4z') || ($key == '218da3') || (strtotime($data[1]) > strtotime('01 March 2013'))) {
            echo '<option value = "' . esc_attr($key) . '">' . esc_html($data[0]) . '</option>';
            $actual[$key] = $data;
        }
    }

    echo '<tr><td>Flatten PDF form</td>';
    if (fpropdf_is_activated() && !fpropdf_is_trial()) {
        echo '<td><select id = "wpfx_layoutvis"><option value = "1">Yes</option><option value="2">Yes, and transform text into images</option><option value = "0">No</option></select><div id="wpfx_layoutvis_options" style="display: none;">&nbsp;<b>Warning:</b> PDF file size will be about 1 MB per page.</div></td></tr>';
    } else {
        echo '<td><select id = "wpfx_layoutvis" disabled="disabled"><option value = "0">No</option></select></td></tr>';
    }

    echo '<tr><td valign="top" style="padding-top: 6px;">Attach file to Email notifications</td>';
    if (fpropdf_is_activated() && !fpropdf_is_trial()) {
        echo '<td>
      <select id = "wpfx_add_att" name="wpfx_add_att"><option value = "1">Yes</option><option value = "0">No</option></select>
<div id="wpfx_frm_actions"><input type="hidden" name="wpfx_add_att_ids[]" value="all" /></div>
<div id="wpfx_frm_actions2" style="display: none;">
<br/>PDF file name in e-mails:<br />
<input name="wpfx_name_email" id="wpfx_name_email" />
</div>
';
        echo '</td></tr>';
        echo '<tr><td valign="top" style="padding-top: 6px;">Language support:</td>';
        echo '<td><select id = "wpfx_lang" name="wpfx_lang"><option value = "0">Default</option><option value="1">Unicode</option></select><div id="wpfx_lang" style="display: none;">&nbsp;<b>Warning:</b> PDF font in forms will be replaced.</div>';
        echo '</td></tr>';
    } else {
        echo '<td><select id = "wpfx_add_att" name="wpfx_add_att" disabled="disabled"><option value = "0">No</option></select></td></tr>';
        echo '<tr><td valign="top" style="padding-top: 6px;">Language support:</td>';
        echo '<td><select id = "wpfx_lang" name="wpfx_lang" disabled="disabled"><option value = "0">Default</option></select><div id="wpfx_lang" style="display: none;">&nbsp;<b>Warning:</b> PDF font in forms will be replaced.</div>';
        echo '</td></tr>';
    }

    echo '<tr><td>PDF password <i>(leave empty if password shouldn\'t be set)</i>:</td><td><input name = "wpfx_password" id = "wpfx_password" /></td></tr>';
    echo '<tr><td>Allow downloads only for roles:</td><td><input name = "wpfx_restrict_role" id = "wpfx_restrict_role" placeholder="all" /></td></tr>';
    echo '<tr><td>Allow downloads only for user IDs:</td><td><input name = "wpfx_restrict_user" id = "wpfx_restrict_user" placeholder="all" /></td></tr>';
    if (get_option('fpropdf_pdfaid_api_key')) {
        echo '<tr><td>Default file format</td><td><select id = "wpfx_default_format" name="wpfx_default_format"><option value = "pdf">PDF</option><option value = "docx">DOCX</option></select></td></tr>';
    } else {
        echo '<input type="hidden" name="wpfx_default_format" id="wpfx_default_format" value="" />';
    }
    echo '<tr><td colspan = "2"><table class = "cltable">';
    echo '<thead><tr>';
    echo '<th>Use as <br />Dataset<br />Name?</th><th>Webform Data Field ID</th><th>Maps<br />to...</th><th>PDF Form Field Name</th>';
    if (fpropdf_is_activated() && !fpropdf_is_trial()) {
        echo '<th>Format</th>';
    }
    echo '<th>&nbsp;</th>';
    echo '</thead></tr><tbody id="clbody" data-activated="' . intval(!fpropdf_is_trial()) . '">';
    echo '</tbody></table>';
    echo '<br />';
    echo '</td></tr><tr><td colspan = "2"><table  width = "100%"><tr>';
    echo '<td align = "left"><input type = "button" id = "clnewmap" value = "Map Another Field" class="button" />';
    echo '<input type = "reset" value = "Reset" class="button" /></td>';
    echo '<input type = "hidden" value = "' . wp_create_nonce('fpropdf_wpfx_savecl') . '" name = "_wpnonce">';
    echo '<td align = "center"><input type = "submit" value = "Save Field Map" class="button-primary" name = "wpfx_savecl" id = "savecl"/></td>';
    echo '<td align = "right">
    <input type = "button" value = "Duplicate this Field Map" class="button" id = "dupcl"/>
    <input type = "button" value = "Delete Entire Field Map" class="button" id = "remvcl"/>
  </td></tr></table>';
    echo '</td></tr></table></form>';
    echo '</div></div>';
    echo '</div>';
}

// Get all Formidable forms available
function wpfx_getforms($show_id = false) {
    global $wpdb;

    $array = array();
    $result = $wpdb->get_results(
            'SELECT `id`, `form_key`, `name`, `created_at` FROM `' . $wpdb->prefix . 'frm_forms` WHERE `status` = "published" AND (`parent_form_id` = 0 OR `parent_form_id` IS NULL ) ORDER BY UNIX_TIMESTAMP(`created_at`) DESC', ARRAY_A
    );

    foreach ($result as $row) {
        $array[($show_id ? $row['id'] : $row['form_key'] )] = array(stripslashes($row['name']), $row['created_at'], $row['id']);
    }

    return $array;
}

// Get all custom created layouts
function wpfx_getlayouts() {
    global $wpdb;

    $array = array();
    $result = $wpdb->get_results('SELECT `ID`, `name` FROM `' . $wpdb->prefix . 'fpropdf_layouts` WHERE 1 ORDER BY `created_at` DESC', ARRAY_A);

    foreach ($result as $row) {
        $array[$row['ID'] + 9] = stripslashes($row['name']); // adding 9 not to mess up with our hardcoded layouts
    }

    return $array;
}

function wpfx_readlayout($id) {
    global $wpdb;

    $result = $wpdb->get_row(
            $wpdb->prepare(
                    'SELECT w.*, f.`form_key` as `form` FROM `' . $wpdb->prefix . 'fpropdf_layouts` w, `' . $wpdb->prefix . 'frm_forms` f WHERE w.`ID` = %d AND f.`id` = w.`form`', $id
            ), ARRAY_A
    );
    if (!$result) {
        $result = array();
    }

    $formats = @unserialize($result['formats']);
    if (!is_array($formats)) {
        $formats = array();
    }

    $data = isset($result['data']) ? unserialize($result['data']) : array();

    $vals = array_values($data);
    if (count($vals)) {
        if (!is_array($vals[0])) {
            $_data = array();
            foreach ($data as $k => $v) {
                $_data[] = array($k, $v);
            }

            $data = $_data;
        }
    }

    if (is_array($data)) {
        foreach ($data as $index => $array) {
            if (is_array($array) && $array[0]) {
                if (fpropdf_use_field_keys()) {
                    $data[$index][0] = fpropdf_field_id_to_key($data[$index][0]);
                } else {
                    $data[$index][0] = fpropdf_field_key_to_id($data[$index][0]);
                }
            }
        }
    }

    $vals = array_values($formats);
    if (count($vals)) {
        if (!is_array($vals[0])) {
            $_data = array();
            foreach ($formats as $k => $v) {
                $_data[] = array($k, $v);
            }
            $formats = $_data;
        }
    }

    if (!isset($result['default_format']) || !$result['default_format']) {
        $result['default_format'] = 'pdf';
    }

    return array(
        'name' => isset($result['name']) ? $result['name'] : '',
        'passwd' => isset($result['passwd']) ? stripslashes($result['passwd']) : '',
        'lang' => isset($result['lang']) ? $result['lang'] : '',
        'file' => isset($result['file']) ? $result['file'] : '',
        'visible' => isset($result['visible']) ? $result['visible'] : '',
        'form' => isset($result['form']) ? $result['form'] : '',
        'index' => isset($result['dname']) ? $result['dname'] : '',
        'add_att' => isset($result['add_att']) ? $result['add_att'] : '',
        'add_att_ids' => isset($result['add_att_ids']) ? $result['add_att_ids'] : '',
        'default_format' => isset($result['default_format']) ? $result['default_format'] : '',
        'name_email' => isset($result['name_email']) ? $result['name_email'] : '',
        'restrict_role' => isset($result['restrict_role']) ? $result['restrict_role'] : '',
        'restrict_user' => isset($result['restrict_user']) ? $result['restrict_user'] : '',
        'data' => $data,
        'formats' => $formats,
    );
}

function wpfx_writelayout($name, $file, $visible, $form, $index, $data, $formats, $add_att, $passwd, $lang, $add_att_ids, $default_format, $name_email, $restrict_role, $restrict_user) {
    global $wpdb;

    $form = $wpdb->get_row(
            $wpdb->prepare(
                    'SELECT `id` FROM `' . $wpdb->prefix . 'frm_forms` WHERE `form_key` = %s', $form
            ), ARRAY_A
    );

    if ($form && isset($form['id'])) {
        $form = $form['id'];
    } else {
        $form = 0;
    }

    $columns = $wpdb->get_col(
            $wpdb->prepare(
                    'SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = %s AND `TABLE_NAME` = %s', DB_NAME, $wpdb->prefix . 'fpropdf_layouts'
            )
    );

    if (!in_array('formats', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN formats LONGTEXT CHARACTER SET utf8');
    }
    $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` MODIFY COLUMN formats LONGTEXT CHARACTER SET utf8');
    $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` MODIFY COLUMN data LONGTEXT');

    if (!in_array('passwd', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN passwd VARCHAR(255)');
    }
    if (!in_array('lang', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN lang INT(3) UNSIGNED NOT NULL DEFAULT "0"');
    }
    if (!in_array('default_format', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN default_format VARCHAR(255) NOT NULL DEFAULT "pdf"');
    }
    if (!in_array('add_att_ids', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN add_att_ids VARCHAR(255) NOT NULL DEFAULT "all"');
    }
    if (!in_array('add_att', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN add_att INT(3) UNSIGNED NOT NULL DEFAULT "0"');
    }
    if (!in_array('name_email', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN name_email VARCHAR(255)');
    }
    if (!in_array('restrict_user', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN restrict_user TEXT CHARACTER SET utf8');
    }
    if (!in_array('restrict_role', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN restrict_role TEXT CHARACTER SET utf8');
    }

    set_transient('fpropdf_notification_new_layout', true, 1800);

    $layout = array(
        'name' => $name,
        'file' => $file,
        'data' => serialize($data),
        'visible' => $visible,
        'form' => $form,
        'dname' => $index,
        'created_at' => current_time('mysql'),
        'formats' => serialize($formats),
        'passwd' => $passwd,
        'lang' => $lang,
        'name_email' => $name_email,
        'restrict_user' => $restrict_user,
        'restrict_role' => $restrict_role,
        'default_format' => $default_format,
        'add_att' => $add_att,
        'add_att_ids' => $add_att_ids,
    );
    $res = $wpdb->insert($wpdb->prefix . 'fpropdf_layouts', $layout);

    if ($wpdb->last_error) {
        die('<div class="error" style="margin-left: 0;"><p>Error while saving layout: ' . esc_html($wpdb->last_error) . '</p></div>');
    }

    $id = $wpdb->insert_id;
    wpfx_backup_layout($id);

    if ($id) {
        return $id;
    }

    return $res;
}

function wpfx_backup_layout($id, $with_pdf = false) {

    global $wpdb;

    $folder = FPROPDF_BACKUPS_DIR;

    $data = $wpdb->get_row(
            $wpdb->prepare(
                    'SELECT * FROM `' . $wpdb->prefix . 'fpropdf_layouts` WHERE ID = %d', $id
            ), ARRAY_A
    );

    if (!$data) {
        return;
    }

    $assocData = @unserialize($data['data']);
    if ($assocData) {
        foreach ($assocData as $index => $v) {
            $assocData[$index][0] = fpropdf_field_id_to_key($v[0]);
        }
        $data['data'] = serialize($assocData);
    }

    $formid = $data['form'];
    $formdata = $wpdb->get_row(
            $wpdb->prepare(
                    'SELECT * FROM `' . $wpdb->prefix . 'frm_forms` WHERE `id` = %d', $formid
            ), ARRAY_A
    );
    if (!$formdata) {
        $formdata = array();
    }

    $filedata = array(
        'ts' => time(),
        'data' => $data,
        'salt' => FPROPDF_SALT,
        'form' => $formdata,
    );

    if ($with_pdf && file_exists(FPROPDF_FORMS_DIR . $data['file'])) {

        $pdf = base64_encode(file_get_contents(FPROPDF_FORMS_DIR . $data['file']));
        $filedata['pdf'] = $pdf;

        ob_start();
        FrmXMLController::generate_xml(
                array('forms'),
                array('ids' => array($data['form']))
        );
        $xml = ob_get_clean();
        $filedata['xml'] = base64_encode($xml);

        header_remove('Content-Description');
        header_remove('Content-Disposition');
        header_remove('Content-Type');
        header_remove('Content-Encoding');
        header_remove('Content-Length');
        header_remove('Content-Type');
        header_remove();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
    }

    $name = preg_replace('/[^a-zA-Z0-9]+/', '_', $data['name']);
    $name = preg_replace('/\_+/', '_', $name);
    $filename = $folder . time() . '_' . $name . '_' . $id . '.json';

    @file_put_contents($filename, json_encode($filedata));

    return $filename;
}

function wpfx_updatelayout($id, $name, $file, $visible, $form, $index, $data, $formats, $add_att, $passwd, $lang, $add_att_ids, $default_format, $name_email, $restrict_role, $restrict_user) {
    global $wpdb;

    $columns = $wpdb->get_col(
            $wpdb->prepare(
                    'SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = %s AND `TABLE_NAME` = %s', DB_NAME, $wpdb->prefix . 'fpropdf_layouts'
            )
    );
    if (!in_array('formats', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN formats LONGTEXT CHARACTER SET utf8');
    }
    $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` MODIFY COLUMN formats LONGTEXT CHARACTER SET utf8');
    $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` MODIFY COLUMN data LONGTEXT');

    if (!in_array('passwd', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN passwd VARCHAR(255)');
    }
    if (!in_array('lang', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN lang INT(3) UNSIGNED NOT NULL DEFAULT "0"');
    }
    if (!in_array('default_format', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN default_format VARCHAR(255) NOT NULL DEFAULT "pdf"');
    }
    if (!in_array('add_att_ids', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN add_att_ids VARCHAR(255) NOT NULL DEFAULT "all"');
    }
    if (!in_array('add_att', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN add_att INT(3) UNSIGNED NOT NULL DEFAULT "0"');
    }
    if (!in_array('name_email', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN name_email VARCHAR(255)');
    }
    if (!in_array('restrict_user', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN restrict_user TEXT CHARACTER SET utf8');
    }
    if (!in_array('restrict_role', $columns, true)) {
        $wpdb->query('ALTER TABLE `' . $wpdb->prefix . 'fpropdf_layouts` ADD COLUMN restrict_role TEXT CHARACTER SET utf8');
    }

    wpfx_backup_layout($id);

    $form_id = $wpdb->get_var(
            $wpdb->prepare(
                    'SELECT id FROM `' . $wpdb->prefix . 'frm_forms` WHERE form_key = %s', $form
            )
    );

    $layout = array(
        'name' => $name,
        'file' => $file,
        'data' => serialize($data),
        'visible' => $visible,
        'form' => $form_id ? $form_id : '0',
        'dname' => $index,
        'created_at' => current_time('mysql'),
        'formats' => serialize($formats),
        'passwd' => $passwd,
        'lang' => $lang,
        'name_email' => $name_email,
        'restrict_user' => $restrict_user,
        'restrict_role' => $restrict_role,
        'default_format' => $default_format,
        'add_att' => $add_att,
        'add_att_ids' => $add_att_ids,
    );

    $res = $wpdb->update($wpdb->prefix . 'fpropdf_layouts', $layout, array('ID' => $id));

    if ($wpdb->last_error) {
        die('<div class="error" style="margin-left: 0;"><p>Error while saving layout: ' . esc_html($wpdb->last_error) . '</p></div>');
    }

    if ($res) {
        return $id;
    }

    return $res;
}

// Get all datasets for specified form
function wpfx_getdataset() {
    global $wpdb;

    if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'fpropdf_wpfx_getdataset_nonce')) {
        // Form key can be any string
        $key = isset($_POST['wpfx_form_key']) ? sanitize_text_field(wp_unslash($_POST['wpfx_form_key'])) : '';

        $array = array();

        $fid = $wpdb->get_row(
                $wpdb->prepare(
                        'SELECT  `id` FROM  `' . $wpdb->prefix . 'frm_forms` WHERE  `form_key` =  %s', $key
                ), ARRAY_A
        );
        if (!$fid) {
            $fid = array();
        }
        $fid = isset($fid['id']) ? $fid['id'] : '0';

        $results = $wpdb->get_results(
                $wpdb->prepare(
                        'SELECT `id`, `name`, `item_key`, `created_at`, `updated_at`, `user_id` FROM  `' . $wpdb->prefix . 'frm_items` WHERE  `form_id` = %d ORDER BY UNIX_TIMESTAMP(`created_at`) DESC', $fid
                ), ARRAY_A
        );

        $fields = FrmField::get_all_for_form($fid, '', 'include');

        if (!$results || ( count($results) == 0 )) {
            $array = array(
                array(
                    'id' => -3,
                    'date' => 'You MUST enter form data before creating merge!',
                ),
            );
            echo json_encode($array);
            die();
        }

        $layouts = $wpdb->get_results(
                $wpdb->prepare(
                        'SELECT `data`, `dname` FROM `' . $wpdb->prefix . 'fpropdf_layouts` WHERE `form` = %d', $fid
                ), ARRAY_A
        );

        if (!$layouts) {
            $layouts = array();
        }

        foreach ($layouts as &$layout) {
            $layout['count'] = 0;
            $layout['found'] = false;

            foreach (unserialize($layout['data']) as $values) {
                if ($layout['count'] == $layout['dname']) {
                    $layout['count'] = fpropdf_field_key_to_id($values[0]);
                    $layout['found'] = true;
                    break;
                }
                $layout['count']++;
            }
        }

        foreach ($results as $row) {
            $name = '';
            if (!count($layouts)) {
                $array[] = array(
                    'id' => $row['id'],
                    'date' => 'Add matching layout first — ' . date('m-d-Y', strtotime($row['created_at'])),
                );
                continue;
            }

            $entry = FrmEntry::getOne($row['id'], true);

            foreach ($layouts as $layout) {
                if (!$layout['found']) {
                    $name = '[empty]';
                    continue;
                }

                $description = array();
                if ($entry && !is_array($entry->description)) {
                    $description_data = @unserialize($entry->description);
                    if ($description_data && is_array($description_data)) {
                        $description = $description_data;
                    }
                }
                $referrer = '';
                if (isset($description['referrer']) && @preg_match('/Referer +\d+\:[ \t]+([^\n\t]+)/', $description['referrer'], $m)) {
                    $referrer = $m[1];
                } else {
                    $referrer = isset($description['referrer']) ? $description['referrer'] : '';
                }

                if ($layout['count'] == 'FPROPDF_ITEM_KEY') {
                    $name = $row['item_key'];
                    continue;
                }
                if ($layout['count'] == 'FPROPDF_BROWSER') {
                    $name = isset($description['browser']) ? $description['browser'] : '';
                    continue;
                }
                if ($layout['count'] == 'FPROPDF_IP') {
                    $name = $entry->ip;
                    continue;
                }
                if ($layout['count'] == 'FPROPDF_CREATED_AT') {
                    $name = get_date_from_gmt($row['created_at'], 'Y-m-d H:i:s');
                    continue;
                }
                if ($layout['count'] == 'FPROPDF_UPDATED_AT') {
                    $name = get_date_from_gmt($row['updated_at'], 'Y-m-d H:i:s');
                    continue;
                }
                if ($layout['count'] == 'FPROPDF_REFERRER') {
                    $name = $referrer;
                    continue;
                }
                if ($layout['count'] == 'FPROPDF_USER_ID') {
                    $name = $row['user_id'];
                    continue;
                }
                if ($layout['count'] == 'FPROPDF_DATASET_ID') {
                    $name = $row['id'];
                    continue;
                }

                $found2 = false;
                foreach ($fields as $field) {
                    if ($field->id != $layout['count']) {
                        continue;
                    }
                    $embedded_field_id = ( $entry->form_id != $field->form_id ) ? 'form' . $field->form_id : 0;
                    $atts = array(
                        'type' => $field->type, 'post_id' => $entry->post_id,
                        'show_filename' => true, 'show_icon' => true, 'entry_id' => $entry->id,
                        'embedded_field_id' => $embedded_field_id,
                    );
                    $name = FrmEntriesHelper::prepare_display_value($entry, $field, $atts);

                    if ($name) {
                        $found2 = true;
                    }
                    break;
                }

                if ($found2) {
                    continue;
                }

                $_name = $wpdb->get_row(
                        $wpdb->prepare(
                                'SELECT `meta_value` as value FROM `' . $wpdb->prefix . 'frm_item_metas` WHERE `item_id` = %d AND `field_id` = %d', $row['id'], $layout['count']
                        ), ARRAY_A
                );
                if ($_name) {
                    $name = stripslashes($_name['value']);
                    break;
                }

                if (!$name) {
                    $name = '[empty]';
                }
            }

            if (!$name) {
                $name = 'Add matching field first';
            }

            $array[] = array(
                'id' => $row['id'],
                'date' => $name . ' — ' . date('m-d-Y', strtotime($row['created_at'])),
            );
        }

        echo json_encode($array);
    }
    die();
}

function wpfx_peeklayout() {
    global $wpdb, $currentFile;

    if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'fpropdf_wpfx_peeklayout_nonce')) {

        $wpfx_layout = isset($_POST['wpfx_layout']) ? intval($_POST['wpfx_layout']) : 0;
        $id = $wpfx_layout - 9;

        $layout = wpfx_readlayout($id);

        $file = FPROPDF_FORMS_DIR . $layout['file'];
        if (defined('FPROPDF_IS_DATA_SUBMITTING')) {
            $file = $currentFile;
        }

        $form_key = $layout['form'];

        $layout['file'] = base64_encode($layout['file']);

        ob_start();

        $layout['imagesBase'] = plugins_url('', __FILE__);
        $layout['images'] = array();
        $layout['checkboxes'] = array();

        try {
            if (!file_exists($file)) {
                throw new Exception('PDF file not found');
            }

            $fields_data = "";
            if (is_callable('shell_exec') && is_callable('escapeshellarg') && shell_exec('which pdftk') && (defined('FPROPDF_IS_MASTER') || get_option('fpropdf_enable_local') || get_option('fpropdf_licence') == 'OFFLINE_SITE')) {
                $fields_data = shell_exec('pdftk ' . escapeshellarg($file) . ' dump_data_fields_utf8 2> /dev/null');
            }

            $fields = array();
            if (!preg_match_all('/FieldName: (.*)/', $fields_data, $m)) {
                throw new Exception('PDFTK returned no fields.');
            }

            $fields = $m[1];
            $layout['fields2'] = $fields;

            $data2 = explode('---', $fields_data);
            foreach ($data2 as $_row) {
                $id = false;
                $options = array();
                $_row = explode("\n", $_row);
                foreach ($_row as $_line) {
                    if (preg_match('/FieldName: (.*)$/', $_line, $m)) {
                        $id = $m[1];
                    }
                    if ($id) {
                        if (preg_match('/FieldStateOption: (.*)$/', $_line, $m)) {
                            $options[] = $m[1];
                        }
                    }
                }
                if ($id && count($options)) {
                    $layout['checkboxes'][$id] = json_encode($options);
                }
            }
        } catch (Exception $e) {
            $layout['error2'] = $e->getMessage();
        }

        try {
            if (!file_exists($file)) {
                throw new Exception('PDF file not found');
            }
            $layout['imageFields'] = array();

            global $wpdb;

            $layout['actions'] = array();
            $results = $wpdb->get_row(
                    $wpdb->prepare(
                            'SELECT forms.id FROM `' . $wpdb->prefix . 'frm_forms` forms WHERE forms.form_key = %s', $form_key
                    )
            );
            if ($results && $results->id) {
                $form_actions = FrmFormAction::get_action_for_form(intval($results->id));
                foreach ($form_actions as $a) {
                    if ($a->post_excerpt == 'email') {
                        $layout['actions'][$a->ID] = $a->post_title;
                    }
                }
            }

            $fields = array();
            $results = $wpdb->get_results(
                    $wpdb->prepare(
                            'SELECT fields.* FROM `' . $wpdb->prefix . 'frm_fields` fields INNER JOIN `' . $wpdb->prefix . 'frm_forms` forms ON ( forms.id = fields.form_id AND forms.form_key = %s) ORDER BY fields.field_order ASC', $form_key
                    )
            );

            foreach ($results as $row) {

                $field_options = array();
                if (isset($row->field_options)) {
                    $field_options = @unserialize($row->field_options);
                }
                if (( $row->type == 'file' ) || ( $row->type == 'signature' ) || ( $row->type == 'image' ) || ($row->type == 'url' && isset($field_options['show_image']) && $field_options['show_image'] == '1')) {
                    $_row_id = $row->id;
                    if (fpropdf_use_field_keys()) {
                        $_row_id = fpropdf_field_id_to_key($_row_id);
                    }
                    $layout['imageFields'][] = $_row_id;
                }
                $name = $row->name;
                $name = str_replace('&nbsp;', ' ', $name);
                $name = trim($name);
                if ($name == 'End Section') {
                    continue;
                }
                if ($row->type == 'checkbox') {
                    $checkboxes = array();
                    $_opts = @unserialize($row->options);
                    if ($_opts && is_array($_opts)) {
                        foreach ($_opts as $_opt) {
                            if (is_array($_opt)) {
                                $_opt = $_opt['value'];
                            }
                            $checkboxes[] = $_opt;
                        }
                    }
                }
                if ($row->type == 'divider') {
                    $data = $row->field_options;
                    $data = @unserialize($data);
                    if (!$data['repeat']) {
                        continue;
                    }
                }

                $_row_id = $row->id;
                if (fpropdf_use_field_keys()) {
                    $_row_id = fpropdf_field_id_to_key($_row_id);
                }
                $fields[] = array($_row_id, '[' . $row->id . '] ' . $name, fpropdf_field_id_to_key($row->id));
            }

            if (!count($fields)) {
                throw new Exception('Could not get web form IDs');
            }
            $layout['fields1'] = $fields;
        } catch (Exception $e) {
            $layout['error1'] = $e->getMessage();
        }

        $layout['activated'] = true;
        $layout['previews_activated'] = ($layout['activated'] && get_option('fpropdf_enable_previews'));
        if (!defined('FPROPDF_IS_MASTER')) {
            try {

                if (!file_exists($file) || !is_file($file)) {
                    throw new Exception('PDF file not found');
                }

                $request = wp_remote_post(
                        FPROPDF_SERVER . 'licence/data.php?' . time(),
                        array(
                            'method' => 'POST',
                            'timeout' => 600,
                            'body' => array(
                                'salt' => FPROPDF_SALT,
                                'code' => get_option('fpropdf_licence'),
                                'form' => $layout['form'],
                                'pdf_file' => '@' . realpath($file),
                                'pdf_file_string' => base64_encode(@file_get_contents(realpath($file))),
                            ),
                        )
                );

                if (is_wp_error($request)) {
                    throw new Exception('Server returned no data');
                }

                $data = json_decode(wp_remote_retrieve_body($request));
                if (!$data) {
                    throw new Exception('Server unknown data');
                }
                $keys = explode(' ', 'fields fields2 checkboxes');
                foreach ($keys as $key) {
                    if (isset($data->{$key}) && $data->{$key}) {
                        $layout[$key] = $data->{$key};
                    }
                }
            } catch (Exception $e) {
                $layout['error_server'] = $e->getMessage();
            }
        }

        if (defined('FPROPDF_IS_DATA_SUBMITTING')) {
            if (file_exists($currentFile)) {
                @unlink($currentFile);
            }
        }
        ob_get_clean();
        echo json_encode($layout);
    }
    die();
}

function wpfx_killlayout() {
    global $wpdb;

    if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'fpropdf_wpfx_killlayout_nonce')) {
        $wpfx_layout = isset($_POST['wpfx_layout']) ? intval($_POST['wpfx_layout']) : 0;
        $id = $wpfx_layout - 9;
        wpfx_backup_layout($id);
        $wpdb->delete($wpdb->prefix . 'fpropdf_layouts', array('ID' => $id));
        echo 1;
    }
    die();
}

function wpfx_duplayout() {
    global $wpdb;

    if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'fpropdf_wpfx_duplayout_nonce')) {
        $wpfx_layout = isset($_POST['wpfx_layout']) ? intval($_POST['wpfx_layout']) : 0;
        $id = $wpfx_layout - 9;
        $layout = wpfx_readlayout($id);
        extract($layout);
        $name .= ' (copy)';
        wpfx_writelayout($name, $file, $visible, $form, $index, $data, $formats, $add_att, $passwd, $lang, $add_att_ids, $default_format, $name_email, $restrict_role, $restrict_user);
    }
    die();
}

// Enqueue admin styles and scripts
function wpfx_init() {

    if (!isset($_GET['page']) || $_GET['page'] != 'fpdf') {
        return;
    }

    wp_register_script('wpfx-script', plugins_url('/res/script.js', __FILE__), array(), FPROPDF_VERSION, false);
    wp_register_style('wpfx-style', plugins_url('/res/style.css', __FILE__), array(), FPROPDF_VERSION);

    wp_enqueue_style('wpfx-style');
    wp_enqueue_script('wpfx-script');
}

// Add menu button
function wpfx_menu() {
    global $wpfx_url;
    if (get_option('fpropdf_field_map_allowed') == 'Yes') {
        $role = 'edit_pages';
    } else {
        $role = 'administrator';
    }
    add_menu_page('Formidable PRO2PDF', 'Formidable PRO2PDF', $role, 'fpdf', 'wpfx_admin', $wpfx_url . '/res/icon.png');
}

function wpfx_fpropdf_remove_pdf() {

    if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'fpropdf_wpfx_remove_pdf_nonce')) {
        $file = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : false;
        if ($file) {
            $file = base64_decode($file);
            // Check if filename does not contain slashes
            if (!preg_match('/\//', $file)) {
                if (file_exists(FPROPDF_FORMS_DIR . $file) && is_file(FPROPDF_FORMS_DIR . $file)) {
                    @unlink(FPROPDF_FORMS_DIR . $file);
                }
            } else {
                die('Wrong filename');
            }
        }
    }
    die();
}

// Add admin init action
add_action('admin_init', 'wpfx_init');

// Register menu
add_action('admin_menu', 'wpfx_menu');

// Register AJAX requests
add_action('wp_ajax_wpfx_get_dataset', 'wpfx_getdataset');
add_action('wp_ajax_wpfx_get_layout', 'wpfx_peeklayout');
add_action('wp_ajax_wpfx_del_layout', 'wpfx_killlayout');
add_action('wp_ajax_wpfx_dup_layout', 'wpfx_duplayout');
add_action('wp_ajax_fpropdf_remove_pdf', 'wpfx_fpropdf_remove_pdf');

// Generate PDF
add_action('wp_ajax_wpfx_generate', 'wpfx_generate_pdf');
add_action('wp_ajax_nopriv_wpfx_generate', 'wpfx_generate_pdf');

function wpfx_generate_pdf() {
    if (isset($_GET['redirect_to_secure'])) {
        $get_redirect_to_secure = sanitize_text_field(wp_unslash($_GET['redirect_to_secure']));
        if ($get_redirect_to_secure) {
            if (current_user_can('manage_options')) {
                $params = $_GET;
                unset($params['redirect_to_secure']);
                $params['key'] = fpropdf_dataset_key($params['dataset'], $params['form'], $params['layout']);
                if (isset($params['form2']) && $params['form2']) {
                    $params['key2'] = fpropdf_dataset_key($params['dataset2'], $params['form2'], $params['layout2']);
                }
                wp_redirect(admin_url('admin-ajax.php') . '?' . http_build_query($params));
                exit;
            }
        }
    }
    $_POST['wpfx_submit_nonce'] = wp_create_nonce('fpropdf_wpfx_submit');
    require_once __DIR__ . '/download.php';
    exit;
}

// Generate Previews
add_action('wp_ajax_wpfx_preview_pdf', 'wpfx_preview_pdf');
add_action('wp_ajax_nopriv_wpfx_preview_pdf', 'wpfx_preview_pdf');

function wpfx_preview_pdf() {
    if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'fpropdf_ajax_preview_nonce')) {
        $get_tb_iframe = isset($_GET['TB_iframe']) && $_GET['TB_iframe'] == 'true' ? true : false;
        if ($get_tb_iframe) {
            unset($_GET['TB_iframe']);
            $src = '?' . http_build_query($_GET);
            echo '<img src="' . esc_url($src) . '" />';
            exit;
        }

        require_once __DIR__ . '/preview.php';
    }
    exit;
}

add_action('frm_after_create_entry', 'cache_entry', 20, 2);

function cache_entry($entry_id, $form_id) {
    global $wpdb;

    $form = FrmForm::getOne($form_id);
    if (!$form) {
        return;
    }

    if (isset($form->options['no_save']) && $form->options['no_save'] == '1') {
        $layout = $wpdb->get_row(
                $wpdb->prepare(
                        'SELECT * FROM `' . $wpdb->prefix . 'fpropdf_layouts` WHERE form = %d', $form_id
                ), ARRAY_A
        );
        if (!$layout) {
            return false;
        }

        $layout_id = $layout['ID'];
        $layout = wpfx_readlayout($layout_id);

        global $currentLayout;
        $currentLayout = $layout;

        $unicode = isset($layout['lang']) && $layout['lang'] == '1' ? true : false;
        $pdf = FPROPDF_FORMS_DIR . $layout['file'];

        $wpfx_fdf = new FDFMaker();
        $filename = wpfx_download($wpfx_fdf->makeFDF(wpfx_extract(3, $entry_id, $layout['data']), $pdf, $unicode));

        $signatures = '';
        global $fpropdfSignatures;
        if (is_array($fpropdfSignatures) && !empty($fpropdfSignatures)) {
            $signatures = serialize($fpropdfSignatures);
        }

        $tmp = array(
            'form_id' => $form_id,
            'layout_id' => $layout_id,
            'entry_id' => $entry_id,
            'path' => $filename,
            'signatures' => $signatures,
        );

        $wpdb->insert($wpdb->prefix . 'fpropdf_tmp', $tmp);
    }
}

// Email Notifications
add_filter('frm_notification_attachment', 'fpropdf_add_my_attachment', 10, 3);

function fpropdf_add_my_attachment($attachments, $form, $args) {

    global $wpdb, $fpropdf_global, $fpropdfSignatures;

    if (!defined('FPROPDF_IS_SENDING_EMAIL')) {
        define('FPROPDF_IS_SENDING_EMAIL', true);
    }

    $form_id = $form->id;
    $form_key = $form->form_key;
    $layouts = $wpdb->get_results(
            $wpdb->prepare(
                    'SELECT * FROM `' . $wpdb->prefix . 'fpropdf_layouts` WHERE form = %d AND add_att = 1', $form_id
            ), ARRAY_A
    );
    if (!$layouts || !count($layouts)) {
        return $attachments;
    }

    if (!$layouts) {
        $layouts = array();
    }

    foreach ($layouts as $layout) {
        $fpropdfSignatures = array();
        if (isset($layout['add_att_ids'])) {
            $ids = explode(',', $layout['add_att_ids']);
            $found = false;
            foreach ($ids as $id) {
                if (( $id == 'all' ) || ( $id == $args['email_key'] )) {
                    $found = true;
                }
            }
            if (!$found) {
                continue;
            }
        }

        $layout = $layout['ID'];
        $dataset = $args['entry']->id;

        global $FPROPDF_NO_HEADERS, $FPROPDF_CONTENT, $FPROPDF_FILENAME, $FPROPDF_GEN_ERROR;
        $FPROPDF_NO_HEADERS = true;
        $FPROPDF_CONTENT = false;
        $FPROPDF_FILENAME = false;
        $FPROPDF_GEN_ERROR = false;

        $__POST = $_POST;
        $__GET = $_GET;
        $__REQUEST = $_REQUEST;

        $_GET['form'] = $form_key;
        $_REQUEST['form'] = $form_key;

        $_GET['layout'] = $layout + 9;
        $_REQUEST['layout'] = $layout + 9;

        $_GET['dataset'] = $dataset;
        $_REQUEST['dataset'] = $dataset;

        ob_start();
        $_POST['wpfx_submit_nonce'] = wp_create_nonce('fpropdf_wpfx_submit');
        require __DIR__ . '/download.php';
        ob_get_clean();

        $data = $FPROPDF_CONTENT;
        $filename = $FPROPDF_FILENAME;

        $_POST = $__POST;
        $_GET = $__GET;
        $_REQUEST = $__REQUEST;

        if ($FPROPDF_GEN_ERROR) {
            $filename = 'error.txt';
            if ($FPROPDF_GEN_ERROR) {
                $data = $FPROPDF_GEN_ERROR;
            }
        }

        if ($filename) {
            $tmp = __DIR__ . '/fields/' . $filename;
            file_put_contents($tmp, $data);
            $attachments[] = $tmp;
            $fpropdf_global->addAttachmentToRemove($tmp);
        }
    }

    return $attachments;
}

add_filter('frm_importing_xml', 'importing_fields_meta_fix', 5, 2);

function importing_fields_meta_fix($imported, $xml) {
    global $wpdb;

    if (!isset($xml->view) && !isset($xml->item)) {
        return $imported;
    }

    if (isset($xml->item)) {
        foreach ($xml->item as $item) {
            $item_key = (string) $item->item_key;
            $form_id = (int) $item->form_id;

            $item_id = $wpdb->get_var(
                    $wpdb->prepare(
                            'SELECT id FROM `' . $wpdb->prefix . 'frm_items` WHERE item_key = %s', $item_key
                    )
            );
            if ($item_id) {
                $item->id = $item_id;
            }
            foreach ($item->item_meta as $meta) {
                $field_id = (int) $meta->field_id;
                $field_key = $wpdb->get_var(
                        $wpdb->prepare(
                                'SELECT field_key FROM `' . $wpdb->prefix . 'fpropdf_fields` WHERE field_id = %d AND form_id = %d', $field_id, $form_id
                        )
                );

                if ($field_key) {
                    $field = FrmField::getOne($field_key);
                    if ($field && isset($field->id)) {
                        $meta->field_id = $field->id;
                    }
                }
            }
        }
    }
    return $imported;
}

add_action('frm_notification', 'fpropdf_remove_my_attachment', 10, 3);

function fpropdf_remove_my_attachment() {
    global $fpropdf_global;

    $attachments = $fpropdf_global->getAttachmentsToRemove();

    if (!empty($attachments)) {
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                @unlink($attachment);
            }
        }
    }

    $fpropdf_global->flush();
}

// Shortcode
require_once __DIR__ . '/formidable-shortcode.php';
