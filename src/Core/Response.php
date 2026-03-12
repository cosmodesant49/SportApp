<?php

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(['error' => $message], $status);
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): never
    {
        $body = ['message' => $message];
        if ($data !== null) {
            $body['data'] = $data;
        }
        self::json($body, $status);
    }
}
