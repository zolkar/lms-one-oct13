<?php
define('CLI_SCRIPT', true);
require_once('config.php');

echo "Table structure for local_aicc_export_sessions:\n";
$columns = $DB->get_columns('local_aicc_export_sessions');
foreach ($columns as $name => $column) {
    echo "$name: {$column->type} " . ($column->not_null ? "(NOT NULL)" : "(NULL)") . "\n";
}
