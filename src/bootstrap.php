<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

$settingsPath = __DIR__ . '/../storage/app_settings.json';
if (is_file($settingsPath)) {
    $savedSettings = json_decode((string) file_get_contents($settingsPath), true);
    if (is_array($savedSettings)) {
        $config = array_replace_recursive($config, $savedSettings);
    }
}

date_default_timezone_set($config['timezone']);
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'OilApp\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
