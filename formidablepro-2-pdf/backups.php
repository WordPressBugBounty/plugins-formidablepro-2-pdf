<?php
if (!defined('ABSPATH')) {
    exit;
}

function fpropdf_backups_sort($a, $b) {
    if ($a['ts'] > $b['ts']) {
        return -1;
    }
    return 1;
}

function fpropdf_frm_match_xml_form($edit_query, $form) {
    if (isset($edit_query['created_at'])) {
        $edit_query['created_at'] = date('Y-m-d H:i:s', strtotime('now'));
    }
    return $edit_query;
}

function fpropdf_restore_backup($filename, $force_id = false, $duplicate = false) {
    if (!file_exists($filename)) {
        throw new Exception("File wasn't uploaded or couldn't be found.");
    }

    $currentFileData = json_decode(file_get_contents($filename), true);

    if (!$currentFileData) {
        throw new Exception("File contains some invalid data. The plugin wasn't able to read it.");
    }

    if (isset($currentFileData['xml']) && $currentFileData['xml']) {
        $tmp = tempnam(PROPDF_TEMP_DIR, 'fproPdfXml');

        try {
            if (!file_exists($tmp)) {
                throw new Exception('Tmp folder ' . PROPDF_TEMP_DIR . ' not exists or not writable');
            }
        } catch (Exception $e) {
            echo '<div class="error" style="margin-left: 0;"><p>' . esc_html($e->getMessage()) . '</p></div>';
            die();
        }

        file_put_contents($tmp, base64_decode($currentFileData['xml']));
        $form_fields = array();

        if ($duplicate) {
            add_filter('frm_match_xml_form', 'fpropdf_frm_match_xml_form', 10, 2);
        }
        $result = FrmXMLHelper::import_xml($tmp);
        $import = $result;
        if ($duplicate) {
            remove_filter('frm_match_xml_form', 'fpropdf_frm_match_xml_form', 10);
        }

        global $wpdb;
        $dom = new DOMDocument();
        $success = $dom->loadXML(file_get_contents($tmp));

        try {
            if (!$success) {
                throw new Exception('There was an error when reading this XML file');
            } elseif (!function_exists('simplexml_import_dom')) {
                throw new Exception('Your server is missing the simplexml_import_dom function');
            }
        } catch (Exception $e) {
            echo '<div class="error" style="margin-left: 0;"><p>' . esc_html($e->getMessage()) . '</p></div>';
            die();
        }

        $xml = simplexml_import_dom($dom);

        $form_id = (string) $xml->form->id;
        $new_form = false;

        if ($duplicate) {
            if (isset($import['forms'][$form_id])) {
                $form_id = $import['forms'][$form_id];
                $new_form = FrmForm::getOne($form_id);
                if ($new_form) {
                    foreach ($xml->form as $xml_form) {
                        $xml_form_id = (string) $xml_form->id;
                        if (isset($import['forms'][$xml_form_id])) {
                            $xml_form_id = $import['forms'][$xml_form_id];

                            foreach ($xml_form->field as $item) {
                                $field_options = FrmAppHelper::maybe_json_decode((string) $item->field_options);

                                if (isset($field_options['form_select']) && $field_options['form_select']) {
                                    foreach ($xml->form as $sub_form_key => $sub_form) {
                                        if ((string) $sub_form->id == $field_options['form_select']) {
                                            foreach ($sub_form->field as $sub_field_key => $sub_field) {
                                                //Get new fields for current form
                                                $fields = FrmField::getAll(array('fi.form_id' => (int) $xml_form_id), 'id ASC');
                                                foreach ($fields as $key => $field) {
                                                    if (strpos($field->field_key, (string) $sub_field->field_key) === 0) {
                                                        $form_fields[] = array(
                                                            'old_id' => (int) $sub_field->id,
                                                            'old_key' => (string) $sub_field->field_key,
                                                            'new_id' => $field->id,
                                                            'new_key' => $field->field_key,
                                                        );
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }

                                $fields = FrmField::getAll(array('fi.form_id' => (int) $xml_form_id), 'id ASC');
                                foreach ($fields as $key => $field) {
                                    if (strpos($field->field_key, (string) $item->field_key) === 0) {
                                        $form_fields[] = array(
                                            'old_id' => (int) $item->id,
                                            'old_key' => (string) $item->field_key,
                                            'new_id' => $field->id,
                                            'new_key' => $field->field_key,
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        foreach ($xml->form->field as $item) {
            $field = array(
                'field_id' => (int) $item->id,
                'field_key' => (string) $item->field_key,
                'form_id' => $form_id,
            );

            $search = array_search($field['field_key'], array_column($form_fields, 'old_key'));
            if ($search !== false) {
                $field['field_id'] = $form_fields[$search]['new_id'];
                $field['field_key'] = $form_fields[$search]['new_key'];
            }

            $result = $wpdb->get_var(
                    $wpdb->prepare(
                            'SELECT field_key FROM `' . $wpdb->prefix . 'fpropdf_fields` WHERE field_id = %s AND form_id = %d', $field['field_id'], $field['form_id']
                    )
            );

            if (!$result) {
                $wpdb->insert($wpdb->prefix . 'fpropdf_fields', $field, array('%d', '%s', '%s'));
            }
        }

        FrmXMLHelper::parse_message($result, $message, $errors);
        if ($errors) {
            throw new Exception('There were some errors when importing Formidable Form. ' . print_r($errors, true));
        }
    }

    $map = $currentFileData['data'];
    extract($map);

    global $wpdb;
    $form = $currentFileData['form']['form_key'];
    $exists = $wpdb->get_var(
            $wpdb->prepare(
                    'SELECT COUNT(*) AS c FROM `' . $wpdb->prefix . 'fpropdf_layouts` WHERE ID = %d', $ID
            )
    );

    if ($duplicate) {
        $exists = 0;
    }

    $index = $dname;
    $data = @unserialize($data);
    $formats = @unserialize($formats);

    if ($currentFileData['salt'] != FPROPDF_SALT) {
        $row = $wpdb->get_row(
                $wpdb->prepare(
                        'SELECT * FROM `' . $wpdb->prefix . 'fpropdf_layouts` WHERE name = %s', $name
                ), ARRAY_A
        );
        if ($row && !$duplicate) {
            $exists = true;
            $ID = $row['ID'];
        }
    }

    $lang = isset($lang) ? $lang : 0;

    if ($exists) {
        wpfx_updatelayout($ID, $name, $file, $visible, $form, $index, $data, $formats, $add_att, $passwd, $lang, $add_att_ids, $default_format, $name_email, $restrict_role, $restrict_user);
    } else {

        if ($duplicate && $new_form) {

            $unique_id = time();

            $str_search = array();
            $str_replace = array();

            $form = $new_form->form_key;
            $add_att_ids = '';

            while (file_exists(FPROPDF_FORMS_DIR . '/' . $unique_id . '_' . $file)) {
                ++$unique_id;
            }
            $file = $unique_id . '_' . $file;

            foreach ($data as $key => $field) {
                $search = array_search($field['0'], array_column($form_fields, 'old_key'));
                if ($search !== false) {
                    $data[$key]['0'] = $form_fields[$search]['new_key'];
                }
            }

            foreach ($form_fields as $form_field) {
                $str_search[] = '[' . $form_field['old_id'] . ']';
                $str_search[] = '[' . $form_field['old_id'] . ':label]';
                $str_search[] = '[' . $form_field['old_key'] . ']';
                $str_search[] = '[' . $form_field['old_key'] . ':label]';

                $str_replace[] = '[' . $form_field['new_id'] . ']';
                $str_replace[] = '[' . $form_field['new_id'] . ':label]';
                $str_replace[] = '[' . $form_field['new_key'] . ']';
                $str_replace[] = '[' . $form_field['new_key'] . ':label]';
            }

            foreach ($formats as $key => $format) {
                if (isset($format['2']) && $formats[$key]['2']) {
                    $formats[$key]['2'] = str_replace($str_search, $str_replace, $format['2']);
                }
            }

            if ($name_email) {
                $name_email = str_replace($str_search, $str_replace, $name_email);
            }

            $name = $unique_id . '_' . $name;
        }

        $r = wpfx_writelayout($name, $file, $visible, $form, $index, $data, $formats, $add_att, $passwd, $lang, $add_att_ids, $default_format, $name_email, $restrict_role, $restrict_user);

        if ($duplicate && $new_form) {

            $update = array();

            $update['name'] = $unique_id . '_' . $new_form->name;

            $form_options = FrmAppHelper::maybe_json_decode((string) $xml->form->options);
            if (isset($new_form->options['success_msg'])) {
                $success_msg = $new_form->options['success_msg'];

                $old_form_key = (string) $xml->form->form_key;
                $old_form_id = (string) $xml->form->id;
                $success_msg = preg_replace('/form=("|\'|)(' . $old_form_key . ')("|\'|\s)/i', 'form=${1}' . $new_form->form_key . '${3}', $success_msg);
                $success_msg = preg_replace('/form=("|\'|)(' . $old_form_id . ')("|\'|\s)/i', 'form=${1}' . $new_form->id . '${3}', $success_msg);

                if (isset($ID) && $ID && $r) {
                    $old_layout_id = (int) $ID + 9;
                    $new_layout_id = (int) $r + 9;
                    $success_msg = preg_replace('/layout=("|\'|)(' . $old_layout_id . ')("|\'|\s)/i', 'layout=${1}' . $new_layout_id . '${3}', $success_msg);
                }

                $success_msg = str_replace($str_search, $str_replace, $success_msg);
                $update['options']['success_msg'] = $success_msg;
            }

            $update['options']['custom_style'] = 1;

            FrmForm::update($new_form->id, $update);
        }
    }

    if (isset($currentFileData['pdf']) && $currentFileData['pdf']) {
        file_put_contents(FPROPDF_FORMS_DIR . '/' . $file, base64_decode($currentFileData['pdf']));
    }

    if ($force_id) {
        $wpdb->update($wpdb->prefix . 'fpropdf_layouts', array('ID' => $force_id), array('name' => $name));
    }

    $exists = $wpdb->get_var(
            $wpdb->prepare(
                    'SELECT COUNT(*) AS c FROM `' . $wpdb->prefix . 'fpropdf_layouts` WHERE ID = %d', $ID
            )
    );
}

function fpropdf_backups_page() {
    $files = array();

    $handle = opendir(FPROPDF_BACKUPS_DIR);
    if ($handle) {
        while (false !== ($entry = readdir($handle))) {
            if (!preg_match('/\.json$/', $entry)) {
                continue;
            }

            $data = json_decode(file_get_contents(FPROPDF_BACKUPS_DIR . $entry), true);

            $files[] = array(
                'name' => $entry,
                'ts' => $data['ts'],
                'data' => $data,
            );
        }
        closedir($handle);
    }

    usort($files, 'fpropdf_backups_sort');

    if (isset($_GET['restore'])) {
        if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'fpropdf_backup_nonce')) {
            $get_restore = sanitize_text_field(wp_unslash($_GET['restore']));
            if ($get_restore) {
                foreach ($files as $currentFile) {
                    if ($get_restore == $currentFile['name']) {
                        try {
                            fpropdf_restore_backup(FPROPDF_BACKUPS_DIR . $currentFile['name']);
                        } catch (Exception $e) {
                            die($e->getMessage());
                        }
                        set_transient('fpropdf_notification_restored', true, 1800);
                        echo '<script>window.location.href = "?page=fpdf&tab=backups";</script>';
                        exit;
                    }
                }
            }
        }
    }

    if (isset($_GET['delete'])) {
        if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'fpropdf_backup_nonce')) {
            $get_delete = sanitize_text_field(wp_unslash($_GET['delete']));
            if ($get_delete) {
                foreach ($files as $currentFile) {
                    if ($get_delete == $currentFile['name']) {
                        if (file_exists(FPROPDF_BACKUPS_DIR . $currentFile['name'])) {
                            unlink(FPROPDF_BACKUPS_DIR . $currentFile['name']);
                        }
                        set_transient('fpropdf_notification_deleted', true, 1800);
                        echo '<script>window.location.href = "?page=fpdf&tab=backups";</script>';
                        exit;
                    }
                }
            }
        }
    }

    if (get_transient('fpropdf_notification_restored')) {
        echo '<div class="updated" style="margin-left: 0;"><p>Field map has been restored. You can now edit it in <a href="?page=fpdf">field map designer</a>.</p></div>';
        delete_transient('fpropdf_notification_restored');
    }

    if (get_transient('fpropdf_notification_deleted')) {
        echo '<div class="updated" style="margin-left: 0;"><p>Backup has been deleted.</p></div>';
        delete_transient('fpropdf_notification_deleted');
    }

    if (!count($files)) {
        echo '<div class="error" style="margin-left: 0;"><p>You don\'t have any backups yet. <br /> Backups will be automatically generated after you save or create a field map.</p></div>';
        return;
    }
    ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Form</th>
                <th>Field Map</th>
                <th>Filename</th>
                <th>Number of fields</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>

            <?php
            foreach ($files as $file) {
                if (!isset($file['data']['form']['name'])) {
                    $file['data']['form']['name'] = '';
                }
                ?>
                <tr>
                    <td><?php echo date(get_option('date_format'), $file['data']['ts']); ?></td>
                    <td><?php echo date('H:i:s', $file['data']['ts']); ?></td>
                    <td><?php echo esc_html($file['data']['form']['name']); ?></td>
                    <td><?php echo esc_html($file['data']['data']['name']); ?></td>
                    <td><?php echo esc_html($file['data']['data']['file']); ?></td>
                    <td><?php echo @count(@unserialize($file['data']['data']['data'])); ?></td>
                    <td>
                        <p>
                            <a href="<?php echo esc_url('?page=fpdf&tab=backups&restore=' . $file['name'] . '&_wpnonce=' . wp_create_nonce('fpropdf_backup_nonce')); ?>" class="button button-primary" onclick="return confirm('Are you sure you want to restore this backup (<?php echo date(get_option('date_format') . ' H:i:s', $file['data']['ts']); ?>)?');">Restore</a>
                            <a href="<?php echo esc_url('?page=fpdf&tab=backups&delete=' . $file['name'] . '&_wpnonce=' . wp_create_nonce('fpropdf_backup_nonce')); ?>" class="button" onclick="return confirm('Are you sure you want to delete this backup (<?php echo date(get_option('date_format') . ' H:i:s', $file['data']['ts']); ?>)?');">Delete</a>
                        </p>
                        <p>
                            <a href="<?php echo esc_url('/wp-content/uploads/fpropdf-backups/' . $file['name']); ?>" class="button" download="<?php echo esc_attr($file['data']['form']['name'] . ' - ' . $file['data']['data']['name'] . ' - ' . date('Y-m-d H-i-s', $file['data']['ts']) . '.json'); ?>">Download</a>
                        </p>
                    </td>
                </tr>

                <?php
            }
            ?>

        </tbody>
    </table>

    <?php
}
