<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AdminAuthRepository;
use App\Repositories\StorefrontProductRepository;
use App\Repositories\StorefrontVisibilityRepository;
use App\Services\AdminAuthService;
use App\Services\BusinessHoursService;
use App\Services\StorefrontCatalogService;
use App\Services\StorefrontVisibilityService;
use App\Services\WhatsAppCheckoutService;
use App\Validation\Validator;
use Dotenv\Dotenv;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Dependencies are missing. Run composer install.');
}
require $autoload;

Dotenv::createImmutable($root)->safeLoad();
$appConfig = require $root . '/config/app.php';
$databaseConfig = require $root . '/config/database.php';
$businessConfig = require $root . '/config/business.php';
$whatsappConfig = require $root . '/config/whatsapp.php';
$logger = new Logger($root . '/storage/logs');
(new ErrorHandler($logger, $appConfig['debug']))->register();
if (!in_array($appConfig['environment'], ['local', 'testing', 'production'], true)) {
    throw new RuntimeException('APP_ENV must be local, testing, or production.');
}
date_default_timezone_set($appConfig['timezone']);
$httpsActive = str_starts_with(strtolower($appConfig['url']), 'https://') || (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off');
$session = new Session();
$session->start(['name' => $appConfig['session']['name'], 'cookie_httponly' => true, 'cookie_secure' => $appConfig['session']['secure'] || $httpsActive, 'cookie_samesite' => 'Lax', 'cookie_path' => '/', 'use_strict_mode' => true, 'use_only_cookies' => true]);
$csrf = new Csrf($session);
$database = new Database($databaseConfig);
$adminAuth = new AdminAuthService(new AdminAuthRepository($database), $session);
$storefrontVisibility = new StorefrontVisibilityService(new StorefrontVisibilityRepository($database));
$catalog = new StorefrontCatalogService(new StorefrontProductRepository($database), require $root . '/config/storefront.php');
$businessHours = new BusinessHoursService($businessConfig);
$whatsappCheckout = new WhatsAppCheckoutService($businessHours, $catalog, $whatsappConfig, $logger);

return [
    'config' => $appConfig,
    'catalog' => $catalog,
    'adminAuth' => $adminAuth,
    'storefrontVisibility' => $storefrontVisibility,
    'businessHours' => $businessHours,
    'whatsappCheckout' => $whatsappCheckout,
    'request' => Request::capture(),
    'router' => new Router(),
    'view' => new View($root . '/resources/views'),
    'session' => $session,
    'csrf' => $csrf,
    'validator' => new Validator(),
    'database' => $database,
    'logger' => $logger,
];
