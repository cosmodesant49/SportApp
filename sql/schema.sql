-- Sport Akipress Run — Database Schema
-- MySQL 8.x / utf8mb4

CREATE DATABASE IF NOT EXISTS sportapp
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE sportapp;

-- ─── Users ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT          NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    api_token     VARCHAR(64)  NOT NULL,
    avatar_path   VARCHAR(255)          DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email     (email),
    UNIQUE KEY uq_api_token (api_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Friends / Subscriptions ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS friends (
    id         INT                              NOT NULL AUTO_INCREMENT,
    user_id    INT                              NOT NULL,
    friend_id  INT                              NOT NULL,
    status     ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP                        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_friendship (user_id, friend_id),
    KEY        idx_friend_id (friend_id),
    CONSTRAINT fk_friends_user   FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_friends_friend FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Workouts (Summary) ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS workouts (
    id              INT            NOT NULL AUTO_INCREMENT,
    user_id         INT            NOT NULL,
    type            VARCHAR(50)    NOT NULL DEFAULT 'run'   COMMENT 'run | walk | cycle | hike | ski',
    start_time      DATETIME       NOT NULL,
    duration        INT            NOT NULL                 COMMENT 'total seconds',
    distance        DECIMAL(10,3)  NOT NULL                 COMMENT 'km',
    avg_pace        DECIMAL(6,2)            DEFAULT NULL    COMMENT 'min/km',
    avg_heart_rate  SMALLINT UNSIGNED       DEFAULT NULL    COMMENT 'bpm',
    map_image_path  VARCHAR(255)            DEFAULT NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_time (user_id, start_time),
    CONSTRAINT fk_workouts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Workout Telemetry (per-second data) ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS workout_telemetry (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workout_id  INT             NOT NULL,
    timestamp   INT UNSIGNED    NOT NULL             COMMENT 'Unix timestamp (seconds)',
    lat         DECIMAL(10,7)            DEFAULT NULL COMMENT 'latitude',
    lon         DECIMAL(10,7)            DEFAULT NULL COMMENT 'longitude',
    altitude    DECIMAL(8,2)             DEFAULT NULL COMMENT 'meters above sea level',
    heart_rate  SMALLINT UNSIGNED        DEFAULT NULL COMMENT 'bpm',
    accel_x     FLOAT                    DEFAULT NULL COMMENT 'm/s²',
    accel_y     FLOAT                    DEFAULT NULL,
    accel_z     FLOAT                    DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_wt_workout (workout_id),
    KEY idx_wt_time    (workout_id, timestamp),
    CONSTRAINT fk_telemetry_workout FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── News ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS news (
    id         INT          NOT NULL AUTO_INCREMENT,
    title      VARCHAR(255) NOT NULL,
    content    TEXT         NOT NULL,
    image_url  VARCHAR(255)          DEFAULT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_news_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Sample news (Kyrgyzstan sports) ─────────────────────────────────────────
INSERT INTO news (title, content, image_url) VALUES
('Бишкек проводит марафон «Ала-Тоо Run 2024»',
 'В столице Кыргызстана пройдёт ежегодный марафон по центральным улицам города. Участие могут принять все желающие!',
 NULL),
('Кыргызские легкоатлеты готовятся к Азиатским играм',
 'Национальная сборная по лёгкой атлетике провела сборы на высокогорной базе Иссык-Куль.',
 NULL);
