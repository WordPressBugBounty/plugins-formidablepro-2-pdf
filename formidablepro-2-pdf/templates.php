<?php
if (!defined('ABSPATH')) {
    exit;
}

function fpropdf_generate_export_file() {

    if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'fpropdf_export_file')) {
        $layout = isset($_REQUEST['fieldmap']) ? intval($_REQUEST['fieldmap']) : '';
        $file = wpfx_backup_layout($layout, $with_pdf = true);
        if ($file) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
        }
    }
    exit;
}

add_action('wp_ajax_fpropdf_export_file', 'fpropdf_generate_export_file');

function fpropdf_templates_page() {

    function fpropdf_parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
        $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }

    function fpropdf_file_upload_max_size() {
        static $max_size = -1;

        if ($max_size < 0) {
            $max_size = fpropdf_parse_size(ini_get('post_max_size'));
            $upload_max = fpropdf_parse_size(ini_get('upload_max_filesize'));
            if ($upload_max > 0 && $upload_max < $max_size) {
                $max_size = $upload_max;
            }
        }
        return $max_size;
    }
    ?>

    <div id="poststuff" class="metabox-holder">
        <div id="post-body">
            <div id="post-body-content">
                <div class="updated inline">
                    <p>MUST READ! <a href="http://www.formidablepro2pdf.com/first-time-template-use/" target="_blank">How To: First-Time Template Use</a></p>
                </div>

                <div class="postbox ">
                    <h3 class="hndle"><span>Import</span></h3>
                    <div class="inside">
                        <?php
                        if (isset($_POST['fpropdf_action']) && $_POST['fpropdf_action'] == 'import_file') {
                            if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'fpropdf_import_file')) {
                                try {
                                    $duplicate = isset($_POST['fpropdf_duplicate']) && intval($_POST['fpropdf_duplicate']) ? true : false;
                                    if (isset($_FILES['fpropdf_import_file']['name']) && isset($_FILES['fpropdf_import_file']['tmp_name'])) {
                                        $file_name = sanitize_text_field(wp_unslash($_FILES['fpropdf_import_file']['name']));
                                        $tmp_name = $_FILES['fpropdf_import_file']['tmp_name']; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Reason: https://github.com/WordPress/WordPress-Coding-Standards/issues/1720
                                        $filetype = wp_check_filetype($file_name, array('json' => 'application/json'));
                                        if ($filetype ['type'] == 'application/json') {
                                            fpropdf_restore_backup($tmp_name, false, $duplicate);
                                        } else {
                                            throw new Exception("File wasn't uploaded or couldn't be found.");
                                        }
                                    }
                                    echo '<div class="updated" style="margin-left: 0;"><p>Field map has been restored. You can now edit it in <a href="?page=fpdf">field map designer</a>.</p></div>';
                                } catch (Exception $e) {
                                    echo '<div class="updated" style="margin-left: 0;"><p>There was an error: ' . $e->getMessage() . '</p></div>';
                                }
                            }
                        }
                        ?>

                        <p class="howto">Upload your previously created Formidable PRO2PDF file to import a field map into this site. <br><strong>Note: If your imported field map ID matches an item on your site, that item will be updated. You cannot undo this action.</strong></p>
                        <br>
                        <form enctype="multipart/form-data" method="post">
                            <input type="hidden" name="fpropdf_action" value="import_file">
                            <p><label>Choose a Formidable PRO2PDF file (Maximum size: <?php echo sprintf('%d', fpropdf_file_upload_max_size() / 1024.0 / 1024.0); ?> MB)</label>
                                <input type="file" name="fpropdf_import_file">
                            </p>
                            <p>
                                <label>
                                    <input type="radio" name="fpropdf_duplicate" value="0" checked="checked">
                                    Overwrite Web Form/PDF/Field Map
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="radio" name="fpropdf_duplicate" value="1">
                                    Recreate Web Form/PDF/Field Map
                                </label>
                            </p>
                            <p class="submit">
                                <?php wp_nonce_field('fpropdf_import_file', '_wpnonce'); ?>
                                <input type="submit" value="Upload file and import" class="button-primary">
                            </p>
                        </form>
                    </div>
                </div>
                <div class="postbox">
                    <h3 class="hndle"><span>Export</span></h3>
                    <div class="inside with_frm_style">
                        <form target="_blank" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" id="frm_export_xml">
                            <input type="hidden" name="action" value="fpropdf_export_file">
                            <table class="form-table">
                                <tbody><tr class="form-field">
                                        <th scope="row"><label for="format">Export field map</label></th>
                                        <td>
                                            <select name="fieldmap">
                                                <?php
                                                global $wpdb;
                                                $rows = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'fpropdf_layouts ORDER BY name ASC');
                                                foreach ($rows as $row) {
                                                    echo '<option value="' . esc_attr($row->ID) . '">' . esc_html($row->name) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <?php wp_nonce_field('fpropdf_export_file', '_wpnonce'); ?>
                            <p class="submit">
                                <input type="submit" value="Export Field Map" class="button-primary">
                            </p>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
}
