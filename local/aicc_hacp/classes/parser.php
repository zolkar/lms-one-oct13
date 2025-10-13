<?php

namespace local_aicc_hacp;

defined('MOODLE_INTERNAL') || die();

class parser {
    public static function parse_aicc(string $raw): array {
        $data = [];
        $current_section = '';

        $lines = preg_split("/\r\n|\n|\r/", $raw);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, ';') === 0) {
                continue;
            }

            if (preg_match('/^\[(.*)\]$/', $line, $matches)) {
                $current_section = $matches[1];
                if (!isset($data[$current_section])) {
                    $data[$current_section] = [];
                }
            } else if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                if ($current_section) {
                    if (isset($data[$current_section][$key])) {
                        if (!is_array($data[$current_section][$key])) {
                            $data[$current_section][$key] = [$data[$current_section][$key]];
                        }
                        $data[$current_section][$key][] = $value;
                    } else {
                        $data[$current_section][$key] = $value;
                    }
                }
            }
        }

        return $data;
    }
}
