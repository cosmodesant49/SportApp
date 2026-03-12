<?php

class NewsController
{
    public function __construct(private readonly NewsRepository $repo) {}

    public function index(array $params, array $user): void
    {
        $limit  = min((int) ($_GET['limit'] ?? 20), 50);
        $offset = max((int) ($_GET['offset'] ?? 0), 0);

        $news = $this->repo->getAll($limit, $offset);

        Response::json([
            'news'    => $news,
            'message' => 'Последние новости спорта Кыргызстана',
        ]);
    }
}
