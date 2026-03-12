<?php
declare(strict_types=1);

// ─── Autoloader ──────────────────────────────────────────────────────────────
spl_autoload_register(static function (string $class): void {
    $dirs = [
        __DIR__ . '/config/',
        __DIR__ . '/src/Core/',
        __DIR__ . '/src/Middleware/',
        __DIR__ . '/src/Controllers/',
        __DIR__ . '/src/Repositories/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ─── CORS + Content-Type ──────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────
$pdo    = Database::getConnection();
$router = new Router();

// ─── Auth (public) ────────────────────────────────────────────────────────────
$router->add('POST', '/login', static function (array $p) use ($pdo): void {
    (new AuthController(new UserRepository($pdo)))->login($p);
});

$router->add('POST', '/register', static function (array $p) use ($pdo): void {
    (new AuthController(new UserRepository($pdo)))->register($p);
});

// ─── Workouts ────────────────────────────────────────────────────────────────
$router->add('POST', '/workouts/upload', static function (array $p) use ($pdo): void {
    $user = AuthMiddleware::handle($pdo);
    (new WorkoutController(new WorkoutRepository($pdo), $pdo))->upload($p, $user);
});

$router->add('GET', '/workouts/feed', static function (array $p) use ($pdo): void {
    $user = AuthMiddleware::handle($pdo);
    (new WorkoutController(new WorkoutRepository($pdo), $pdo))->feed($p, $user);
});

$router->add('GET', '/workout/{id}', static function (array $p) use ($pdo): void {
    $user = AuthMiddleware::handle($pdo);
    (new WorkoutController(new WorkoutRepository($pdo), $pdo))->getOne($p, $user);
});

// ─── Social ───────────────────────────────────────────────────────────────────
$router->add('GET', '/users/search', static function (array $p) use ($pdo): void {
    $user = AuthMiddleware::handle($pdo);
    (new SocialController(new UserRepository($pdo), new FriendRepository($pdo), new WorkoutRepository($pdo)))->search($p, $user);
});

$router->add('POST', '/friends/add', static function (array $p) use ($pdo): void {
    $user = AuthMiddleware::handle($pdo);
    (new SocialController(new UserRepository($pdo), new FriendRepository($pdo), new WorkoutRepository($pdo)))->addFriend($p, $user);
});

$router->add('GET', '/profile/{id}', static function (array $p) use ($pdo): void {
    $user = AuthMiddleware::handle($pdo);
    (new SocialController(new UserRepository($pdo), new FriendRepository($pdo), new WorkoutRepository($pdo)))->profile($p, $user);
});

$router->add('POST', '/profile/avatar', static function (array $p) use ($pdo): void {
    $user = AuthMiddleware::handle($pdo);
    (new SocialController(new UserRepository($pdo), new FriendRepository($pdo), new WorkoutRepository($pdo)))->uploadAvatar($p, $user);
});

// ─── News ─────────────────────────────────────────────────────────────────────
$router->add('GET', '/news', static function (array $p) use ($pdo): void {
    $user = AuthMiddleware::handle($pdo);
    (new NewsController(new NewsRepository($pdo)))->index($p, $user);
});

// ─── Dispatch ─────────────────────────────────────────────────────────────────
$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);
