<?php

class UserRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, avatar_path FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $name, string $email, string $passwordHash, string $apiToken): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, api_token) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $passwordHash, $apiToken]);
        return (int) $this->pdo->lastInsertId();
    }

    public function search(string $query, int $currentUserId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, avatar_path
             FROM users
             WHERE (name LIKE ? OR email LIKE ?) AND id != ?
             LIMIT 20'
        );
        $like = '%' . $query . '%';
        $stmt->execute([$like, $like, $currentUserId]);
        return $stmt->fetchAll();
    }

    public function updateAvatar(int $id, string $path): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET avatar_path = ? WHERE id = ?');
        $stmt->execute([$path, $id]);
    }
}
