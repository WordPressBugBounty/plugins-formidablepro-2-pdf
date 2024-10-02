<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('fpropdf_custom_capitalize')) {

    function fpropdf_custom_capitalize($m) {
        $s = $m[1];
        $s = function_exists('mb_strtoupper') ? mb_strtoupper($s) : strtoupper($s);
        return $s;
    }

}

if (!function_exists('fpropdf_is_number')) {

    function fpropdf_is_number($m) {
        if ($m === '' || ($m && preg_match('/[^0-9\.\,\-\s]+/', $m))) {
            return false;
        }
        return true;
    }

}

if (!function_exists('fpropdf_if_shortcode')) {

    function fpropdf_if_shortcode($string, $type = '', $val, $key, $this_field_key, $debug = false) {

        /* Prepare local/global shortcodes */
        $updated_string = '';
        if (false !== strpos($string, '[if')) {
            preg_match_all('/\[if(.*?)\](((?R)|.*?)+)\[\/if (.*?)\]/is', $string, $matches);
            if (isset($matches[0]) && !empty($matches[0])) {
                foreach ($matches[0] as $shortcode_key => $shortcode_value) {
                    $atts = shortcode_parse_atts($shortcode_value);
                    if (isset($atts[1])) {
                        $value_to_compare = $atts[1];
                        if ($value_to_compare == $key . $type || $value_to_compare == $this_field_key . $type) {
                            $updated_string .= $shortcode_value;
                        }
                    }
                }
            }
        }

        /* Check if local/global shortcodes exists */
        if ($type) {
            if (!$updated_string) {
                return $val;
            } else {
                $string = $updated_string;
            }
        } else {
            if (!$updated_string) {
                return $string;
            }
        }

        /* Working with local/global shortcodes */
        if (false !== strpos($string, '[if')) {
            preg_match_all('/\[if(.*?)\](((?R)|.*?)+)\[\/if (.*?)\]/is', $string, $matches);
            if (isset($matches[0]) && !empty($matches[0])) {
                foreach ($matches[0] as $shortcode_key => $shortcode_value) {

                    /* Replace nested shortcodes */
                    preg_match_all('/\[if(.*?)\](((?R)|.*?)+)\[\/if (.*?)\]/is', $shortcode_value, $sub_matches);
                    if (isset($sub_matches[2]) && !empty($sub_matches[2])) {
                        foreach ($sub_matches[2] as $sub_string) {
                            if (false !== strpos($sub_string, '[if')) {
                                $string = str_replace($sub_string, fpropdf_if_shortcode($sub_string, $type, $val, $key, $this_field_key), $string);
                            }
                        }
                    }

                    /* Replace if conditions */
                    preg_match_all('@\[(if\s+[^<>&/\[\]\x00-\x20=]++)@', $string, $value_matches);
                    if (isset($value_matches[1]) && !empty($value_matches[1])) {
                        $pattern = get_shortcode_regex($value_matches[1]);
                        preg_match_all("/$pattern/", $string, $shortcodes);
                        foreach ($shortcodes[0] as $shortcode_key => $sub_shortcode_value) {
                            $shortcode = array();
                            $shortcode[1] = $shortcodes[1][$shortcode_key];
                            $shortcode[2] = $shortcodes[2][$shortcode_key];
                            $shortcode[3] = $shortcodes[3][$shortcode_key];
                            $shortcode[4] = $shortcodes[4][$shortcode_key];
                            $shortcode[5] = $shortcodes[5][$shortcode_key];
                            $shortcode[6] = $shortcodes[6][$shortcode_key];

                            $atts = shortcode_parse_atts($shortcode[3]);

                            $value_to_compare = trim(str_replace('if', '', $shortcode[2]));

                            if ($value_to_compare == $key . $type || $value_to_compare == $this_field_key . $type) {

                                $value_to_compare = $val;

                                if ($type) {
                                    $shortcode[5] = str_replace('[' . str_replace(':', '', $type) . ']', $val, $shortcode[5]);
                                }

                                if (isset($atts['equals']) && $value_to_compare === $atts['equals']) {
                                    $string = str_replace($sub_shortcode_value, $shortcode['5'], $string);
                                } elseif (isset($atts['not_equal']) && $value_to_compare !== $atts['not_equal']) {
                                    $string = str_replace($sub_shortcode_value, $shortcode['5'], $string);
                                } elseif (isset($atts['like']) && stripos($value_to_compare, $atts['like']) !== false) {
                                    $string = str_replace($sub_shortcode_value, $shortcode['5'], $string);
                                } elseif (isset($atts['not_like']) && stripos($value_to_compare, $atts['not_like']) === false) {
                                    $string = str_replace($sub_shortcode_value, $shortcode['5'], $string);
                                } elseif (isset($atts['greater_than']) && $value_to_compare > $atts['greater_than']) {
                                    $string = str_replace($sub_shortcode_value, $shortcode['5'], $string);
                                } elseif (isset($atts['less_than']) && $value_to_compare < $atts['less_than']) {
                                    $string = str_replace($sub_shortcode_value, $shortcode['5'], $string);
                                } else {
                                    $string = str_replace($sub_shortcode_value, '', $string);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $string;
    }

}

if (!function_exists('fpropdf_format_field')) {

    function fpropdf_format_field($v, $_format, $level = 0) {

        if ($level > 1) {
            return $v;
        }

        global $currentLayout;

        $key = $_format[0];
        $format = $_format[1];

        if ($format != 'repeatable' && $format != 'repeatable2') {
            $fieldId = false;
            foreach ($currentLayout['data'] as $__v) {
                if ($__v[1] == $key) {
                    $fieldId = $__v[0];
                    global $wpdb;
                    $row2 = $wpdb->get_row(
                            $wpdb->prepare(
                                    'SELECT * FROM `' . $wpdb->prefix . 'frm_fields` WHERE `id` = %d', intval(fpropdf_field_key_to_id($fieldId))
                            ), ARRAY_A
                    );
                    if ($row2) {
                        $field_options = @unserialize($row2['field_options']);
                        if ($row2['type'] === 'select' && array_key_exists('multiple', $field_options) && $field_options['multiple'] === '1') {
                            $_tmp = @unserialize($v);
                            if ($_tmp && is_array($_tmp) && count($_tmp)) {
                                $v = implode(', ', $_tmp);
                            }
                        }
                    }
                }
            }
        }

        if ($format == 'html') {
            $v = strip_tags($v . '');
            $format = 'removeEmptyLines';
        }

        $additional = apply_filters('fpropdf_additional_formatting', array());
        foreach ($additional as $title => $callback) {
            if (!is_callable($callback) && !function_exists($callback)) {
                continue;
            }
            if ($format != $title) {
                continue;
            }
            $v = call_user_func($callback, $v);
        }

        switch ($format) {
            case 'number_f1':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 2, '.', ',');
                } else {
                    $v = '';
                }
                break;
            case 'number_f2':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 2, ',', '.');
                } else {
                    $v = '';
                }
                break;
            case 'number_f3':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 2, '.', ' ');
                } else {
                    $v = '';
                }
                break;
            case 'number_f4':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 2, ',', ' ');
                } else {
                    $v = '';
                }
                break;
            case 'number_f5':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 0, '.', ',');
                } else {
                    $v = '';
                }
                break;
            case 'number_f6':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 0, '.', '.');
                } else {
                    $v = '';
                }
                break;
            case 'number_f7':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 0, '.', ' ');
                } else {
                    $v = '';
                }
                break;
            case 'number_f8':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 2, ',', '');
                } else {
                    $v = '';
                }
                break;
            case 'number_f9':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 2, ', ', '. ');
                } else {
                    $v = '';
                }
                break;
            case 'number_f10':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 2, '. ', ', ');
                } else {
                    $v = '';
                }
                break;
            case 'number_f11':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 0, '.', '');
                } else {
                    $v = '';
                }
                break;
            case 'number_f12':
                if (fpropdf_is_number($v)) {
                    $v = number_format((float) $v, 2, '.', '');
                } else {
                    $v = '';
                }
                break;
            case 'number_f13':
                $v = intval($v);
                break;

            case 'removeEmptyLines':
                $v = $v . '';
                $v = explode("\n", $v);
                foreach ($v as $_k => $_v) {
                    $_v = str_replace("\r", '', $_v);
                    $_v = preg_replace('/(^\s+)|(\s+$)/us', '', $_v);
                    if (strlen($_v)) {
                        $v[$_k] = $_v;
                    } else {
                        unset($v[$_k]);
                    }
                }
                $v = array_values($v);
                $v = implode("\n", $v);
                break;

            case 'label':
                $fieldId = false;
                foreach ($currentLayout['data'] as $__v) {
                    if ($__v[1] == $key) {

                        $fieldId = $__v[0];
                        global $wpdb;

                        $row2 = $wpdb->get_row(
                                $wpdb->prepare(
                                        'SELECT * FROM `' . $wpdb->prefix . 'frm_fields` WHERE `id` = %d', intval(fpropdf_field_key_to_id($fieldId))
                                ), ARRAY_A
                        );

                        if ($row2) {
                            if (in_array($row2['type'], explode(' ', 'select checkbox radio'), true)) {
                                $opts = @unserialize($row2['options']);
                                foreach ($opts as $_v) {
                                    if (!is_array($v)) {
                                        if (isset($_v['value']) && $_v['value'] == $v) {
                                            $v = $_v['label'];
                                            break;
                                        }
                                    } else {
                                        foreach ($v as $v_k => $v_v) {
                                            if (isset($_v['value']) && $_v['value'] == $v_v) {
                                                $v[$v_k] = $_v['label'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if (is_array($v)) {
                    $_opts = @json_decode($_format[3]);
                    if ($_opts && is_array($_opts) && count($_opts)) {
                        foreach ($v as $_k => $_v) {
                            if (!in_array($_v, $_opts)) {
                                unset($v[$_k]);
                            }
                        }
                    }
                } else {
                    $v = str_replace("\r", '', $v);
                }

                break;

            case 'signature':
                global $fpropdfSignatures;
                if (!$fpropdfSignatures) {
                    $fpropdfSignatures = array();
                }

                $v = @unserialize($v);
                if (isset($v['typed']) && !isset($v['output']) && is_serialized($v['typed'])) {
                    $v['output'] = $v['typed'];
                }

                if (isset($v['content']) && isset($v['format']) && $v['format'] == 'drawn') {
                    $uploads = wp_upload_dir();
                    $target_path = $uploads['basedir'] . '/';
                    $target_path .= apply_filters('frm_sig_upload_folder', 'formidable/signatures');
                    $target_path = untrailingslashit($target_path);
                    $file = $target_path . '/' . $v['content'];

                    if (file_exists($file)) {
                        $v = base64_encode(file_get_contents($file));
                    }
                } elseif (isset($v['content']) && isset($v['format']) && $v['format'] == 'typed') {
                    $v['typed'] = $v['content'];
                }

                $v = apply_filters('fpro2pdf_signature', $v, $key, isset($_POST['wpfx_dataset']) ? intval($_POST['wpfx_dataset']) : 0);

                if (is_array($v)) {
                    $options = array();
                    $options = apply_filters('fpro2pdf_sig_output_options', $options, $key);
                    $v['options'] = $options;
                }

                $v = @serialize($v);

                $fpropdfSignatures[] = array(
                    'data' => $v,
                    'alignment' => $_format[4],
                    'rotation' => isset($_format[7]) ? $_format[7] : '0',
                    'field' => $key,
                );

                $v = '';
                break;

            case 'curDate':
                $v = date('m/d/y');
                break;

            case 'curDate2':
                $v = date('d/m/Y');
                break;

            case 'curDate3':
                $v = date('m/d/Y');
                break;

            case 'curDate4':
                $v = date('Y/m/d');
                break;

            case 'curDate5':
                $v = date('d-m-Y');
                break;

            case 'curDate6':
                $v = date('d.m.Y');
                break;

            case 'curDate7':
                $v = date_i18n('j. F Y', time());
                break;

            case 'curDate8':
                $v = date('d/m/y');
                break;

            case 'repeatable':
            case 'repeatable2':
                $v = @unserialize($v);
                $vals = array();
                try {
                    if (!$v || !is_array($v)) {
                        throw new Exception('Not an array');
                    }

                    foreach ($v as $id) {
                        $string = $_format[2];

                        global $wpdb;
                        $rows = $wpdb->get_results(
                                $wpdb->prepare(
                                        'SELECT * FROM `' . $wpdb->prefix . 'frm_item_metas` WHERE `item_id` = %d', intval($id)
                                ), ARRAY_A
                        );
                        if (!$rows) {
                            $rows = array();
                        }
                        foreach ($rows as $row) {
                            $key = $row['field_id'];
                            $this_field_key = 'unknown_field_key';
                            $val = $row['meta_value'];
                            $val_label = $val;
                            $address_labels = array();
                            $credit_card_labels = array();

                            $_tmp = @unserialize($val_label);
                            if ($_tmp && is_array($_tmp) && count($_tmp)) {
                                $val_label = implode(', ', $_tmp);
                            }

                            $row2 = $wpdb->get_row(
                                    $wpdb->prepare(
                                            'SELECT * FROM `' . $wpdb->prefix . 'frm_fields` WHERE `id` = %d', intval($key)
                                    ), ARRAY_A
                            );

                            if ($row2) {
                                $this_field_key = $row2['field_key'];
                                if ($row2['type'] === 'select' || $row2['type'] === 'radio') {
                                    $opts = @unserialize($row2['options']);
                                    foreach ($opts as $v) {
                                        if ($v['value'] == $val) {
                                            $val_label = $v['label'];
                                            $_tmp = @unserialize($val_label);
                                            if ($_tmp && is_array($_tmp) && count($_tmp)) {
                                                $val_label = $_tmp[0];
                                            }
                                        }
                                    }
                                } elseif ($row2['type'] === 'checkbox') {
                                    $opts = @unserialize($row2['options']);
                                    $_tmp = @unserialize($val);
                                    $labels = array();
                                    foreach ($opts as $v) {
                                        if (is_array($_tmp)) {
                                            if (in_array($v['value'], $_tmp)) {
                                                $labels[] = $v['label'];
                                            }
                                        } else {
                                            if ($v['value'] === $val) {
                                                $labels[] = $v['label'];
                                            }
                                        }
                                    }
                                    if (is_array($labels)) {
                                        foreach ($labels as $label_key => $label_value) {
                                            /* Apply if conditions for label */
                                            $formatted_label = fpropdf_if_shortcode($string, ':label', $label_value, $key, $this_field_key);
                                            if ($formatted_label) {
                                                $labels[$label_key] = $formatted_label;
                                            } else {
                                                unset($labels[$label_key]);
                                            }
                                        }
                                        $val_label = implode(', ', $labels);
                                    }
                                } elseif ($row2['type'] === 'address') {
                                    $_tmp = @unserialize($val);
                                    if (is_array($_tmp)) {
                                        $address_labels = $_tmp;
                                        $val = implode(' ', $address_labels);
                                    }
                                } elseif ($row2['type'] === 'credit_card') {
                                    $_tmp = @unserialize($val);
                                    if (is_array($_tmp)) {
                                        $credit_card_labels = $_tmp;
                                        $val = implode(' ', $credit_card_labels);
                                    }
                                }

                                $field_options = @unserialize($row2['field_options']);
                                if ($row2['type'] === 'select' && array_key_exists('multiple', $field_options) && $field_options['multiple'] === '1') {
                                    $_tmp = @unserialize($val);
                                    if ($_tmp && is_array($_tmp) && count($_tmp)) {
                                        /* Apply if conditions for values */
                                        foreach ($_tmp as $_tmp_key => $_tmp_value) {
                                            $formatted_tmp = fpropdf_if_shortcode($string, ':value', $_tmp_value, $key, $this_field_key);
                                            if ($formatted_tmp) {
                                                $_tmp[$_tmp_key] = $formatted_tmp;
                                            } else {
                                                unset($_tmp[$_tmp_key]);
                                            }
                                        }

                                        $val = implode(', ', $_tmp);
                                    } elseif ($val) {
                                        $val = fpropdf_if_shortcode($string, ':value', $val, $key, $this_field_key);
                                    }
                                }

                                if ($row2['type'] === 'checkbox') {
                                    $_tmp = @unserialize($val);
                                    if ($_tmp && is_array($_tmp) && count($_tmp)) {
                                        /* Apply if conditions for values */
                                        foreach ($_tmp as $_tmp_key => $_tmp_value) {
                                            $formatted_tmp = fpropdf_if_shortcode($string, ':value', $_tmp_value, $key, $this_field_key, true);
                                            if ($formatted_tmp) {
                                                $_tmp[$_tmp_key] = $formatted_tmp;
                                            } else {
                                                unset($_tmp[$_tmp_key]);
                                            }
                                        }
                                        $val = implode(', ', $_tmp);
                                    } elseif ($val) {
                                        $val = fpropdf_if_shortcode($string, ':value', $val, $key, $this_field_key);
                                    }
                                }
                            }

                            /* Apply global if conditions */
                            $string = fpropdf_if_shortcode($string, '', $val, $key, $this_field_key);

                            $string = str_replace(':Show label for checkbox/select/radio]', ':label]', $string);

                            $string = str_replace('[' . $key . ']', $val, $string);
                            $string = str_replace('[' . $key . ':label]', $val_label, $string);
                            $string = str_replace('[' . $this_field_key . ']', $val, $string);
                            $string = str_replace('[' . $this_field_key . ':label]', $val_label, $string);

                            /* Render Address Field Vales */
                            foreach ($address_labels as $address_key => $address_label) {
                                $string = str_replace('[' . $key . ':' . $address_key . ']', $address_label, $string);
                            }

                            /* Render Creadit Card Values */
                            foreach ($credit_card_labels as $credit_card_key => $credit_card_label) {
                                $string = str_replace('[' . $key . ':' . $credit_card_key . ']', $credit_card_label, $string);
                            }

                            $_formats = array(
                                'dd' => 'd',
                                'mm' => 'm',
                                'yyyy' => 'Y',
                                'yy' => 'y',
                            );
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                                foreach ($_formats as $format1 => $date1) {
                                    foreach ($_formats as $format2 => $date2) {
                                        foreach ($_formats as $format3 => $date3) {
                                            $f = $format1 . '/' . $format2 . '/' . $format3;
                                            $datef = $date1 . '/' . $date2 . '/' . $date3;
                                            $string = str_ireplace('[' . $key . ':' . $f . ']', date($datef, strtotime($val)), $string);
                                            $string = str_ireplace('[' . $this_field_key . ':' . $f . ']', date($datef, strtotime($val)), $string);
                                        }
                                    }
                                }
                            }

                            $_formats = array(
                                'none' => '(no formatting)',
                                'tel' => 'Telephone',
                                'address' => 'Address',
                                'credit_card' => 'Credit Card',
                                'date' => 'Date MM/DD/YY',
                                'date2' => 'Date DD/MM/YYYY',
                                'date3' => 'Date MM/DD/YYYY',
                                'date4' => 'Date YYYY/MM/DD',
                                'date5' => 'Date DD-MM-YYYY',
                                'date6' => 'Date DD.MM.YYYY',
                                'date7' => 'Date DD. month year',
                                'date8' => 'Date DD/MM/YY',
                                'capitalize' => 'Capitalize',
                                'capitalizeAll' => 'CAPITALIZE ALL',
                                'returnToComma' => 'Carriage return to comma',
                                'returnToCarriage' => 'Comma to carriage return',
                                'removeEmptyLines' => 'Remove empty lines in text',
                                'html' => 'Remove HTML tags',
                                'number_f1' => 'Number 1,000.00',
                                'number_f2' => 'Number 1.000,00',
                                'number_f3' => 'Number 1 000.00',
                                'number_f4' => 'Number 1 000,00',
                                'number_f5' => 'Number 1,000',
                                'number_f6' => 'Number 1.000',
                                'number_f7' => 'Number 1 000',
                                'number_f8' => 'Number 1000,00',
                                'number_f9' => 'Number 1. 000, 00',
                                'number_f10' => 'Number 1, 000. 00',
                                'number_f11' => 'Number 1000',
                                'number_f12' => 'Number 1000.00',
                                'number_f13' => 'Number Intval',
                                'lowercase' => 'Lowercase',
                            );

                            foreach ($_formats as $format_key => $format_string) {
                                $val_formatted = fpropdf_format_field($val, array($key, $format_key), $level + 1);
                                $string = str_ireplace('[' . $key . ':' . $format_string . ']', $val_formatted, $string);
                                $string = str_ireplace('[' . $this_field_key . ':' . $format_string . ']', $val_formatted, $string);
                            }

                            $_formats = array(
                                'curDate' => 'Current Date MM/DD/YY',
                                'curDate2' => 'Current Date DD/MM/YYYY',
                                'curDate3' => 'Current Date MM/DD/YYYY',
                                'curDate4' => 'Current Date YYYY/MM/DD',
                                'curDate5' => 'Current Date DD-MM-YYYY',
                                'curDate6' => 'Current Date DD.MM.YYYY',
                                'curDate7' => 'Current Date DD. month year',
                                'curDate8' => 'Current Date DD/MM/YY',
                            );

                            foreach ($_formats as $format_key => $format_string) {
                                $val_formatted = fpropdf_format_field('', array($key, $format_key), $level + 1);
                                $string = str_ireplace('[' . $format_string . ']', $val_formatted, $string);
                            }
                        }

                        /* Remove unsued if conditions */
                        if (false !== strpos($string, '[if')) {
                            preg_match_all('/\[if(.*?)\](((?R)|.*?)+)\[\/if (.*?)\]/is', $string, $matches);
                            if (isset($matches[0]) && !empty($matches[0])) {
                                foreach ($matches[0] as $shortcode_key => $shortcode_value) {
                                    $string = str_replace($shortcode_value, '', $string);
                                }
                            }
                        }

                        $vals[] = $string;
                    }
                } catch (Exception $e) {
                    $e->getMessage();
                }

                foreach ($vals as $_k => $_v) {
                    $_v = preg_replace('/\[[^\]]+?\]/', '', $_v);
                    $_v = preg_replace('/\[[^\]\:]+\:[^\]]+\]/', '', $_v);
                    $_v = str_replace("\r", '', $_v);
                    $vals[$_k] = $_v;
                }

                if ($format == 'repeatable2') {

                    $_opts = @json_decode($_format[3]);
                    if ($_opts && is_array($_opts) && count($_opts)) {
                        foreach ($vals as $val_key => $vvals) {
                            if (in_array($vvals, $_opts)) {
                                continue;
                            }
                            $vals[$val_key] = '';
                            $vvals = explode(', ', $vvals);
                            foreach ($vvals as $__k => $__v) {
                                if (in_array($__v, $_opts)) {
                                    $vals[$val_key] = $__v;
                                }
                            }
                        }
                    }

                    global $separateRepeatable;
                    global $currentKey;
                    if (!$separateRepeatable) {
                        $separateRepeatable = array();
                    }
                    $separateRepeatable[$currentKey] = $vals;
                    $v = '';
                } else {
                    $v = implode('', $vals);
                }
                break;

            case 'tel':
                if (trim($v) !== '') {
                    $v2 = preg_replace('/[^0-9]+/', '', $v);
                    $v2 = intval($v2);
                    $v2 = sprintf('%010d', $v2);
                    if (preg_match('/(\d{3})(\d{3})(\d{4})$/', $v2, $matches)) {
                        $v = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                    }
                }
                break;

            case 'address':
                $v = @unserialize($v);
                $string = $_format[5];

                if (is_array($v)) {
                    foreach ($v as $key => $val) {
                        $string = str_replace('[' . $key . ']', $val, $string);
                    }
                }
                /* for remove empty [] shorcode and line feed issue */
                $string_array = preg_split("/[\n ]+/", $string);
                $text_inside = array();
                foreach ($string_array as $key => $val) {
                    $length = strlen($val);
                    for ($i = 0; $i < $length; $i++) {
                        if ($val[$i] == '[') {
                            $t1 = '';
                            $i++;
                            while ($val[$i] != ']') {
                                $t1 .= $val[$i];
                                $i++;
                            }
                            if ($val != '') {
                                unset($string_array[$key]);
                            }
                        }
                    }
                }
                $string = implode(' ', $string_array);
                $v = $string;
                break;

            case 'credit_card':
                $v = @unserialize($v);
                $string = $_format[6];

                if (is_array($v)) {
                    foreach ($v as $key => $val) {
                        $string = str_replace('[' . $key . ']', $val, $string);
                    }
                }

                $v = $string;
                break;

            case 'date':
                if (preg_match('/^(\d{4})\-(\d{2})\-(\d{2})/', $v, $m)) {
                    $v = $m[2] . '/' . $m[3] . '/' . substr($m[1], 2, 4);
                }
                break;

            case 'date2':
                if (preg_match('/^(\d{4})\-(\d{2})\-(\d{2})/', $v, $m)) {
                    $v = $m[3] . '/' . $m[2] . '/' . $m[1];
                }
                break;

            case 'date3':
                if (preg_match('/^(\d{4})\-(\d{2})\-(\d{2})/', $v, $m)) {
                    $v = $m[2] . '/' . $m[3] . '/' . $m[1];
                }
                break;

            case 'date4':
                if (preg_match('/^(\d{4})\-(\d{2})\-(\d{2})/', $v, $m)) {
                    $v = $m[1] . '/' . $m[2] . '/' . $m[3];
                }
                break;

            case 'date5':
                if (preg_match('/^(\d{4})\-(\d{2})\-(\d{2})/', $v, $m)) {
                    $v = $m[3] . '-' . $m[2] . '-' . $m[1];
                }
                break;

            case 'date6':
                if (preg_match('/^(\d{4})\-(\d{2})\-(\d{2})/', $v, $m)) {
                    $v = $m[3] . '.' . $m[2] . '.' . $m[1];
                }
                break;

            case 'date7':
                if (preg_match('/^(\d{4})\-(\d{2})\-(\d{2})/', $v, $m)) {
                    $v = date_i18n('j. F Y', strtotime($m[1] . '-' . $m[2] . '-' . $m[3]));
                }
                break;

            case 'date8':
                if (preg_match('/^(\d{4})\-(\d{2})\-(\d{2})/', $v, $m)) {
                    $v = $m[3] . '/' . $m[2] . '/' . substr($m[1], 2, 4);
                }
                break;

            case 'returnToComma':
                $v = str_replace("\r", '', $v);
                $v = str_replace("\n", ', ', $v);
                $v = preg_replace('/ +/', ' ', $v);
                $v = preg_replace('/\, +$/', '', $v);
                break;

            case 'returnToCarriage':
                $v = str_replace('', "\r", $v);
                $v = str_replace(', ', "\n", $v);
                $v = preg_replace('/ +/', ' ', $v);
                $v = preg_replace('/\, +$/', '', $v);
                break;

            case 'capitalize':
                $v = preg_replace_callback('/(^[a-z]| [a-z])/u', 'fpropdf_custom_capitalize', strtolower($v));
                break;

            case 'capitalizeAll':
                $v = function_exists('mb_strtoupper') ? mb_strtoupper($v) : strtoupper($v);
                break;

            case 'lowercase':
                $v = function_exists('mb_strtolower') ? mb_strtolower($v) : strtolower($v);
                break;

            default:
                if (is_array($v)) {
                    $_opts = @json_decode($_format[3]);
                    if ($_opts && is_array($_opts) && count($_opts)) {
                        $_opts2 = str_replace('&amp;', '&', $_opts);

                        //3.04 Special Characters as ü fix for Checkbox due incorrect encoding
                        $_opts3 = array();
                        foreach ($_opts2 as $_opts2_k => $_opts2_v) {
                            if ($_opts2_v && is_string($_opts2_v)) {
                                $_opts3[] = html_entity_decode($_opts2_v);
                            }
                        }

                        foreach ($v as $_k => $_v) {
                            if (!in_array($_v, $_opts) && !in_array($_v, $_opts2) && !in_array($_v, $_opts3)) {
                                unset($v[$_k]);
                            }
                        }
                    }
                } else {
                    $v = str_replace("\r", '', $v);
                }
                break;
        }

        if (!is_array($v)) {
            if (preg_match('/^FPROPDF\_IMAGE\:(.*)$/', $v, $m)) {
                global $fpropdfSignatures;
                if (!$fpropdfSignatures) {
                    $fpropdfSignatures = array();
                }

                $fpropdfSignatures[] = array(
                    'data' => serialize(base64_encode(file_get_contents(ABSPATH . $m[1]))),
                    'field' => $key,
                    'alignment' => $_format[4],
                    'rotation' => isset($_format[7]) ? $_format[7] : '0',
                    'is_file' => basename($m[1]),
                );

                $v = '';
            }

            if (preg_match('/^FPROPDF\_IMAGE\_FIELD\:([^\:]+)\:([^\:]+)$/', $v, $m)) {
                global $fpropdfSignatures;
                if (!$fpropdfSignatures) {
                    $fpropdfSignatures = array();
                }

                $fpropdfSignatures[] = array(
                    'data' => serialize($m[2]),
                    'field' => $key,
                    'alignment' => $_format[4],
                    'rotation' => isset($_format[7]) ? $_format[7] : '0',
                    'is_file' => $m[1],
                );

                $v = '';
            }
        }
        return $v;
    }

}
