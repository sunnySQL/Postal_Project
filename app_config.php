<?php
/**
 * Application configuration.
 * Copy config.local.php.example → config.local.php for local development overrides.
 */
$app_config = [
    'debug' => false,
];

$config_local = __DIR__ . '/config.local.php';
if (file_exists($config_local)) {
    $local = require $config_local;
    if (is_array($local)) {
        $app_config = array_merge($app_config, $local);
    }
}

if (!empty($app_config['debug'])) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

error_reporting(E_ALL);
