<?php

class NewsRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function getAll(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM news ORDER BY created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
}
