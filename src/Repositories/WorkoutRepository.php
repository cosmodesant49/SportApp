<?php

class WorkoutRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO workouts
                (user_id, type, start_time, duration, distance, avg_pace, avg_heart_rate, map_image_path)
             VALUES
                (:user_id, :type, :start_time, :duration, :distance, :avg_pace, :avg_heart_rate, :map_image_path)'
        );
        $stmt->execute([
            'user_id'        => $userId,
            'type'           => $data['type'] ?? 'run',
            'start_time'     => $data['start_time'],
            'duration'       => (int) $data['duration'],
            'distance'       => (float) $data['distance'],
            'avg_pace'       => isset($data['avg_pace']) ? (float) $data['avg_pace'] : null,
            'avg_heart_rate' => isset($data['avg_heart_rate']) ? (int) $data['avg_heart_rate'] : null,
            'map_image_path' => $data['map_image_path'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }


    public function insertTelemetryBatch(int $workoutId, array $points): void
    {
        if (empty($points)) {
            return;
        }

        $chunks = array_chunk($points, 500);

        foreach ($chunks as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, ?, ?, ?, ?, ?)'));

            $sql = "INSERT INTO workout_telemetry
                        (workout_id, timestamp, lat, lon, altitude, heart_rate, accel_x, accel_y, accel_z)
                    VALUES $placeholders";

            $values = [];
            foreach ($chunk as $p) {
                $values[] = $workoutId;
                $values[] = $p['timestamp'];
                $values[] = isset($p['lat'])      ? (float) $p['lat']      : null;
                $values[] = isset($p['lon'])      ? (float) $p['lon']      : null;
                $values[] = isset($p['altitude']) ? (float) $p['altitude'] : null;
                $values[] = isset($p['heart_rate']) ? (int) $p['heart_rate'] : null;
                $values[] = isset($p['accel_x'])  ? (float) $p['accel_x']  : null;
                $values[] = isset($p['accel_y'])  ? (float) $p['accel_y']  : null;
                $values[] = isset($p['accel_z'])  ? (float) $p['accel_z']  : null;
            }

            $this->pdo->prepare($sql)->execute($values);
        }
    }

    public function getFeed(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT w.*, u.name AS user_name, u.avatar_path,
                    ROUND(w.distance, 2) AS distance
             FROM workouts w
             JOIN users u ON w.user_id = u.id
             WHERE w.user_id = ?
                OR w.user_id IN (
                    SELECT friend_id FROM friends
                    WHERE user_id = ? AND status = "accepted"
                )
             ORDER BY w.start_time DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $userId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT w.*, u.name AS user_name, u.avatar_path,
                    ROUND(w.distance, 2) AS distance
             FROM workouts w
             JOIN users u ON w.user_id = u.id
             WHERE w.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getTelemetry(int $workoutId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, timestamp, lat, lon, altitude, heart_rate, accel_x, accel_y, accel_z
             FROM workout_telemetry
             WHERE workout_id = ?
             ORDER BY timestamp ASC'
        );
        $stmt->execute([$workoutId]);
        return $stmt->fetchAll();
    }

    public function getMonthlyVolume(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE(start_time) AS day, ROUND(SUM(distance), 2) AS km
             FROM workouts
             WHERE user_id = ? AND start_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(start_time)
             ORDER BY day ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getStats(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS total_workouts,
                ROUND(SUM(distance), 2) AS total_km,
                SUM(duration) AS total_seconds
             FROM workouts
             WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
}
