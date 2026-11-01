<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use Wayfairs\Core\Config;
use Wayfairs\Core\Context;
use Wayfairs\Core\Database;
use Wayfairs\Core\Flash;
use Wayfairs\Core\Migrator;
use Wayfairs\Core\Request;
use Wayfairs\Core\Response;
use Wayfairs\Core\Router;
use Wayfairs\Core\Security;
use Wayfairs\Core\View;
use Wayfairs\Services\Installer;
use Wayfairs\Services\AuthService;

$config = Config::instance();
$request = new Request($config);
Context::setRequestId($request->requestId());

$sessionDirectory = WAYFAIRS_ROOT . '/storage/sessions';
if (!is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0700, true);
}
session_save_path($sessionDirectory);
session_name('wayfairs_csrf');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $config->cookiePath(),
    'secure' => (bool) $config->get('session.secure', false),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
$_SESSION['csrf_token'] ??= Security::token(32);

// HEAD must produce the same status and headers as GET with no body.
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') {
    ob_start(static fn (string $buffer): string => '');
}

if (!$config->installed()) {
    if ($request->path() !== '/install') {
        Response::redirect('/install');
    }
    if ($request->method() === 'POST') {
        try {
            (new Installer())->install($request->allPost());
            Flash::put('success', 'Wayfairs is installed. Sign in with the administrator account you created.');
            Response::redirect('/login');
        } catch (Throwable $error) {
            Flash::put('error', $error->getMessage());
            Response::redirect('/install');
        }
    }
    $flash = Flash::consume();
    require WAYFAIRS_ROOT . '/app/Views/install.php';
    exit;
}

$database = Database::fromConfig($config);
try {
    (new Migrator($database))->migrate();
} catch (Throwable $error) {
    error_log('[wayfairs] database update failed: ' . $error->getMessage());
    http_response_code(503);
    echo '<!doctype html><html><body><h1>Wayfairs is updating. Please try again in a moment.</h1></body></html>';
    exit;
}

$auth = new AuthService($database);
$cookieName = (string) $config->get('session.cookie', 'wayfairs_session');
$token = is_string($_COOKIE[$cookieName] ?? null) ? (string) $_COOKIE[$cookieName] : '';
$user = $token !== '' ? $auth->sessionUser($token) : null;
Context::setUser($user);
Context::setSessionToken($token !== '' ? $token : null);

if ($request->method() === 'POST' && !hash_equals(wayfairs_csrf(), (string) $request->post('_token', ''))) {
    http_response_code(419);
    echo '<!doctype html><html><body><h1>Your session expired. Go back, refresh, and try again.</h1></body></html>';
    exit;
}

$router = new Router();
(require WAYFAIRS_ROOT . '/app/routes.php')($router, $database);

$route = $router->match($request);
if ($route === null) {
    Response::notFound();
}
$request->setRouteParameters((array) ($route['parameters'] ?? []));

$access = (string) ($route['access'] ?? 'public');
if ($access === 'auth' && $user === null) {
    Flash::put('error', 'Please sign in to continue.');
    Response::redirect('/login');
}
if ($access === 'admin' && ($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo '<!doctype html><html><body><h1>Administrator access required.</h1></body></html>';
    exit;
}
if ($access === 'guest' && $user !== null) {
    Response::redirect($user['role'] === 'admin' ? '/admin' : '/dashboard');
}

call_user_func($route['handler'], $request, $route['parameters']);
