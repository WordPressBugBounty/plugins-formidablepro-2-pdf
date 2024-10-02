<?php

if (!defined('ABSPATH')) {
    exit;
}

class Fpropdf_Global {

    protected $attachments;

    public function __construct() {
        $this->flush();
    }

    /*
     * Add attachment to remove
     * @param $attachment - Filepath to attachment
     */

    public function addAttachmentToRemove($attachment) {
        $this->attachments[] = $attachment;
    }

    /*
     * Get attachments to remove
     * @return array()
     */

    public function getAttachmentsToRemove() {
        return $this->attachments;
    }

    /*
     * Flush all settings
     */

    public function flush() {
        $this->attachments = array();
    }

}
