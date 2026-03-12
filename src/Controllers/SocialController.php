<?php

class SocialController
{
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        private readonly UserRepository    $userRepo,
        private readonly FriendRepository  $friendRepo,
        private readonly WorkoutRepository $workoutRepo
    ) {}

    /**
     * GET /users/search?query=...
     */
    public function search(array $params, array $user): void
    {
        $query = trim($_GET['query'] ?? '');

        if (mb_strlen($query) < 2) {
            Response::error('Search query must be at least 2 characters', 400);
        }

        $users = $this->userRepo->search($query, $user['id']);

        Response::json(['users' => $users]);
    }

    /**
     * POST /friends/add
     * Body: { "user_id": 42 }
     */
    public function addFriend(array $params, array $user): void
    {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $friendId = (int) ($body['user_id'] ?? 0);

        if (!$friendId) {
            Response::error('user_id is required', 400);
        }

        if ($friendId === (int) $user['id']) {
            Response::error('You cannot add yourself as a friend', 400);
        }

        if (!$this->userRepo->findById($friendId)) {
            Response::error('User not found', 404);
        }

        $status = $this->friendRepo->add((int) $user['id'], $friendId);

        $messages = [
            'pending'  => 'Запрос на дружбу отправлен!',
            'accepted' => 'Вы уже друзья.',
            'rejected' => 'Запрос ранее был отклонён.',
        ];

        Response::json([
            'status'  => $status,
            'message' => $messages[$status] ?? 'Готово.',
        ]);
    }

    /**
     * GET /profile/{id}
     */
    public function profile(array $params, array $user): void
    {
        $profileId   = (int) $params['id'];
        $profileUser = $this->userRepo->findById($profileId);

        if (!$profileUser) {
            Response::error('User not found', 404);
        }

        $monthlyVolume  = $this->workoutRepo->getMonthlyVolume($profileId);
        $stats          = $this->workoutRepo->getStats($profileId);
        $friends        = $this->friendRepo->getByUser($profileId);
        $recentWorkouts = $this->workoutRepo->getFeed($profileId, 5, 0);

        Response::json([
            'user'            => $profileUser,
            'stats'           => $stats,
            'monthly_volume'  => $monthlyVolume,   // [{day, km}, ...] for chart
            'friends'         => $friends,
            'recent_workouts' => $recentWorkouts,
        ]);
    }

    /**
     * POST /profile/avatar
     * Multipart: file field "avatar"
     */
    public function uploadAvatar(array $params, array $user): void
    {
        if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
            Response::error('No file uploaded', 400);
        }

        $file = $_FILES['avatar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload error (code ' . $file['error'] . ')', 400);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_IMAGE_EXTENSIONS)) {
            Response::error('Only images are allowed: ' . implode(', ', self::ALLOWED_IMAGE_EXTENSIONS), 400);
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!str_starts_with($mime, 'image/')) {
            Response::error('Uploaded file is not an image', 400);
        }

        $dir = __DIR__ . '/../../uploads/avatars/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'user_' . $user['id'] . '_' . uniqid('', true) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . $filename);

        $path = '/uploads/avatars/' . $filename;
        $this->userRepo->updateAvatar((int) $user['id'], $path);

        Response::json(['avatar_path' => $path]);
    }
}
