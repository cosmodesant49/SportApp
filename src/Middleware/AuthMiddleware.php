<?php

class AuthMiddleware
{
    public static function handle(PDO $pdo): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : self::parseHeaders();

        $auth = '';
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $auth = $value;
                break;
            }
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
            Response::error('Unauthorized: missing or malformed token', 401);
        }

        $token = trim($m[1]);

        $stmt = $pdo->prepare(
            'SELECT id, name, email, avatar_path FROM users WHERE api_token = ? LIMIT 1'
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('Unauthorized: invalid token', 401);
        }

        return $user;
    }

    private static function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
}
