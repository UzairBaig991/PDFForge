<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
define('APP_ROOT', $config['root']);

if (is_file(APP_ROOT . '/vendor/autoload.php')) {
    require APP_ROOT . '/vendor/autoload.php';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('pdfforge_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once APP_ROOT . '/app/Helpers/functions.php';
require_once APP_ROOT . '/app/Services/PdfEngine.php';
require_once APP_ROOT . '/app/Services/PdfToJpgService.php';
require_once APP_ROOT . '/app/Services/PdfTextService.php';
require_once APP_ROOT . '/app/Services/PdfToolsService.php';
if (empty($config['debug'])) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
ini_set('log_errors', '1');
