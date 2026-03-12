<?php

class FriendRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function add(int $userId, int $friendId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT status FROM friends WHERE user_id = ? AND friend_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $friendId]);
        $existing = $stmt->fetch();

        if ($existing) {
            return $existing['status'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, "pending")'
        );
        $stmt->execute([$userId, $friendId]);
        return 'pending';
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.name, u.avatar_path, f.status, f.created_at
             FROM friends f
             JOIN users u ON f.friend_id = u.id
             WHERE f.user_id = ?
             ORDER BY f.created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
