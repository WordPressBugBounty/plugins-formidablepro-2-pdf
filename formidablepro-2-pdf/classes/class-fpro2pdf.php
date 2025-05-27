<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FPRO2PDF')) {

    class FPRO2PDF {

        protected static $instance = null;
        protected $attachments = array();
        protected $tmps = array();

        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function is_attachment($attachment) {
            $this->attachments[] = $attachment;
            return $attachment;
        }

        public function flush_attachments() {
            if (!empty($this->attachments)) {
                foreach ($this->attachments as $attachment) {
                    if (file_exists($attachment)) {
                        @unlink($attachment);
                    }
                }
            }
            $this->attachments = array();
        }

        public function is_tmp($tmp) {
            $this->tmps[] = $tmp;
            return $tmp;
        }

        public function flush_tmps() {
            if (!empty($this->tmps)) {
                foreach ($this->tmps as $file) {
                    if (file_exists($file)) {
                        if (is_file($file)) {
                            @unlink($file);
                        } elseif (is_dir($file)) {
                            $this->delete_dir($file);
                        }
                    }
                }
            }
            $this->tmps = array();
        }

        public function delete_dir($dir) {
            if (!is_dir($dir)) {
                return;
            }
            $dir = trailingslashit($dir);
            $files = glob($dir . '*', GLOB_MARK);
            foreach ($files as $file) {
                if (file_exists($file) && is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($dir);
        }
    }

}