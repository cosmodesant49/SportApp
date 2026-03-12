<?php

class WorkoutController
{
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const ALLOWED_WORKOUT_TYPES    = ['run', 'walk', 'cycle', 'hike', 'ski'];

    public function __construct(
        private readonly WorkoutRepository $workoutRepo,
        private readonly PDO               $pdo
    ) {}

    /**
     * POST /workouts/upload
     *
     * Accepts JSON body:
     * {
     *   "type": "run",
     *   "start_time": "2024-03-04 08:00:00",
     *   "duration": 3600,        // seconds
     *   "distance": 10.5,        // km
     *   "avg_pace": 5.7,         // min/km  (optional)
     *   "avg_heart_rate": 145,   // bpm     (optional)
     *   "telemetry": [           // optional, array of per-second data points
     *     { "timestamp": 1709538000, "lat": 42.87, "lon": 74.59,
     *       "altitude": 800, "heart_rate": 140,
     *       "accel_x": 0.1, "accel_y": 0.2, "accel_z": 9.8 },
     *     ...
     *   ]
     * }
     * Optionally multipart/form-data with file field "map_image".
     */
    public function upload(array $params, array $user): void
    {
        $body = $this->parseBody();

        foreach (['start_time', 'duration', 'distance'] as $field) {
            if (empty($body[$field]) && $body[$field] !== 0) {
                Response::error("Field '$field' is required", 400);
            }
        }

        if (isset($body['type']) && !in_array($body['type'], self::ALLOWED_WORKOUT_TYPES)) {
            Response::error('Invalid workout type. Allowed: ' . implode(', ', self::ALLOWED_WORKOUT_TYPES), 400);
        }

        // Handle optional map image upload (multipart)
        $body['map_image_path'] = null;
        if (!empty($_FILES['map_image']) && $_FILES['map_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $body['map_image_path'] = $this->saveImage($_FILES['map_image'], 'maps');
        }

        $telemetry = [];
        if (!empty($body['telemetry']) && is_array($body['telemetry'])) {
            $telemetry = $body['telemetry'];
            unset($body['telemetry']);
        }

        $this->pdo->beginTransaction();
        try {
            $workoutId = $this->workoutRepo->create($user['id'], $body);

            if (!empty($telemetry)) {
                $this->workoutRepo->insertTelemetryBatch($workoutId, $telemetry);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            Response::error('Failed to save workout: ' . $e->getMessage(), 500);
        }

        Response::json([
            'workout_id'      => $workoutId,
            'telemetry_saved' => count($telemetry),
            'message'         => 'Тренировка успешно сохранена!',
        ], 201);
    }

    /**
     * GET /workouts/feed?limit=20&offset=0
     */
    public function feed(array $params, array $user): void
    {
        $limit  = min((int) ($_GET['limit'] ?? 20), 50);
        $offset = max((int) ($_GET['offset'] ?? 0), 0);

        $workouts = $this->workoutRepo->getFeed($user['id'], $limit, $offset);

        Response::json(['workouts' => $workouts]);
    }

    /**
     * GET /workout/{id}
     */
    public function getOne(array $params, array $user): void
    {
        $id      = (int) $params['id'];
        $workout = $this->workoutRepo->findById($id);

        if (!$workout) {
            Response::error('Workout not found', 404);
        }

        $telemetry = $this->workoutRepo->getTelemetry($id);

        Response::json([
            'workout'   => $workout,
            'telemetry' => $telemetry,
        ]);
    }

    private function saveImage(array $file, string $subfolder): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload error (code ' . $file['error'] . ')', 400);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_IMAGE_EXTENSIONS)) {
            Response::error('Only images are allowed: ' . implode(', ', self::ALLOWED_IMAGE_EXTENSIONS), 400);
        }

        // Verify it's actually an image
        $mime = mime_content_type($file['tmp_name']);
        if (!str_starts_with($mime, 'image/')) {
            Response::error('Uploaded file is not an image', 400);
        }

        $dir = __DIR__ . "/../../uploads/$subfolder/";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = uniqid('', true) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . $filename);

        return "/uploads/$subfolder/$filename";
    }

    /** Parse body from JSON or multipart/form-data */
    private function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }

        // multipart: JSON meta in 'data' field, file in 'map_image'
        if (!empty($_POST['data'])) {
            return json_decode($_POST['data'], true) ?? [];
        }

        return $_POST;
    }
}
