<?php

class AuthController
{
    public function __construct(private readonly UserRepository $repo) {}

    public function login(array $params): void
    {
        $body     = $this->parseJson();
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            Response::error('Email and password are required', 400);
        }

        $user = $this->repo->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('Invalid email or password', 401);
        }

        Response::json([
            'token' => $user['api_token'],
            'user'  => [
                'id'          => $user['id'],
                'name'        => $user['name'],
                'email'       => $user['email'],
                'avatar_path' => $user['avatar_path'],
            ],
        ]);
    }

    public function register(array $params): void
    {
        $body     = $this->parseJson();
        $name     = trim($body['name'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$name || !$email || !$password) {
            Response::error('Name, email and password are required', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email format', 400);
        }

        if (mb_strlen($password) < 6) {
            Response::error('Password must be at least 6 characters', 400);
        }

        if ($this->repo->findByEmail($email)) {
            Response::error('This email is already registered', 409);
        }

        $hash  = password_hash($password, PASSWORD_BCRYPT);
        $token = bin2hex(random_bytes(32));
        $id    = $this->repo->create($name, $email, $hash, $token);

        Response::json([
            'token' => $token,
            'user'  => [
                'id'    => $id,
                'name'  => $name,
                'email' => $email,
            ],
        ], 201);
    }

    private function parseJson(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}
