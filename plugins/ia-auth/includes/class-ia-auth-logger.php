<?php
if (!defined('ABSPATH')) { exit; }

class IA_Auth_Logger {

    private $opt_key = 'ia_auth_log_ring';

    public function info($message, array $context = []) {
        $this->write('INFO', $message, $context);
    }

    // Added because IA Auth calls $this->log->warn(...)
    public function warn($message, array $context = []) {
        $this->write('WARN', $message, $context);
    }

    public function error($message, array $context = []) {
        $this->write('ERROR', $message, $context);
    }

    private function write($level, $message, array $context) {
        $row = [
            'ts' => gmdate('c'),
            'level' => $level,
            'message' => (string)$message,
            'context' => $context,
        ];

        $ring = get_option($this->opt_key, []);
        if (!is_array($ring)) $ring = [];
        $ring[] = $row;

        if (count($ring) > 200) {
            $ring = array_slice($ring, -200);
        }

        update_option($this->opt_key, $ring, false);
    }

    public function get_ring() {
        $ring = get_option($this->opt_key, []);
        if (!is_array($ring)) $ring = [];
        return array_reverse($ring);
    }
}
