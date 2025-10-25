<?php
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Query String: " . $_SERVER['QUERY_STRING'] . "\n";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "Path Info: " . (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : 'Not set') . "\n";
echo "GET parameters: " . print_r($_GET, true) . "\n";
?>
