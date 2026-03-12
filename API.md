# Sport Akipress Run — API Reference

Base URL: `http://your-domain.com/sportapp`

Все ответы возвращаются в формате **JSON** (`Content-Type: application/json`).

---

## Авторизация

Все эндпоинты, кроме `/login` и `/register`, требуют заголовок:

```
Authorization: Bearer <token>
```

Токен выдаётся при логине/регистрации и хранится в таблице `users.api_token`.

---

## 1. Auth

### `POST /register` — Регистрация

**Body (JSON):**
```json
{
  "name": "Айбек Усупов",
  "email": "aibek@example.com",
  "password": "secret123"
}
```

**Ответ 201:**
```json
{
  "token": "a3f8c2d1e4b9...",
  "user": {
    "id": 1,
    "name": "Айбек Усупов",
    "email": "aibek@example.com"
  }
}
```

**Ошибки:**

| Код | Причина |
|-----|---------|
| 400 | Пустые поля / невалидный email / пароль < 6 символов |
| 409 | Email уже зарегистрирован |

---

### `POST /login` — Вход

**Body (JSON):**
```json
{
  "email": "aibek@example.com",
  "password": "secret123"
}
```

**Ответ 200:**
```json
{
  "token": "a3f8c2d1e4b9...",
  "user": {
    "id": 1,
    "name": "Айбек Усупов",
    "email": "aibek@example.com",
    "avatar_path": "/uploads/avatars/user_1_abc123.jpg"
  }
}
```

**Ошибки:**

| Код | Причина |
|-----|---------|
| 400 | Пустые поля |
| 401 | Неверный email или пароль |

---

## 2. Workouts — Запись тренировок

### `POST /workouts/upload` — Загрузить тренировку

Два варианта отправки:

#### Вариант A — чистый JSON (без фото карты)

```
Content-Type: application/json
Authorization: Bearer <token>
```

**Body:**
```json
{
  "type": "run",
  "start_time": "2024-03-04 08:00:00",
  "duration": 3600,
  "distance": 10.5,
  "avg_pace": 5.71,
  "avg_heart_rate": 145,
  "telemetry": [
    {
      "timestamp": 1709538000,
      "lat": 42.874621,
      "lon": 74.589843,
      "altitude": 800.5,
      "heart_rate": 140,
      "accel_x": 0.12,
      "accel_y": -0.05,
      "accel_z": 9.81
    },
    {
      "timestamp": 1709538001,
      "lat": 42.874710,
      "lon": 74.589950,
      "altitude": 801.0,
      "heart_rate": 142,
      "accel_x": 0.15,
      "accel_y": -0.03,
      "accel_z": 9.79
    }
  ]
}
```

#### Вариант B — multipart/form-data (с фото карты)

```
Content-Type: multipart/form-data
```

Поля:
- `data` — JSON-строка с мета-данными и телеметрией (те же поля что выше)
- `map_image` — файл изображения (jpg/png/webp/gif)

---

**Поля мета-данных:**

| Поле | Тип | Обяз. | Описание |
|------|-----|:-----:|---------|
| `type` | string | — | `run`, `walk`, `cycle`, `hike`, `ski` (default: `run`) |
| `start_time` | string | ✓ | Формат `YYYY-MM-DD HH:MM:SS` |
| `duration` | int | ✓ | Длительность в **секундах** |
| `distance` | float | ✓ | Дистанция в **км** |
| `avg_pace` | float | — | Средний темп (мин/км) |
| `avg_heart_rate` | int | — | Средний пульс (уд/мин) |
| `telemetry` | array | — | Массив точек (описание ниже) |

**Поля одной точки телеметрии:**

| Поле | Тип | Описание |
|------|-----|---------|
| `timestamp` | int | Unix timestamp (секунды) |
| `lat` | float | Широта |
| `lon` | float | Долгота |
| `altitude` | float | Высота над уровнем моря (м) |
| `heart_rate` | int | Пульс (уд/мин) |
| `accel_x` | float | Акселерометр X (м/с²) |
| `accel_y` | float | Акселерометр Y (м/с²) |
| `accel_z` | float | Акселерометр Z (м/с²) |

**Ответ 201:**
```json
{
  "workout_id": 42,
  "telemetry_saved": 3600,
  "message": "Тренировка успешно сохранена!"
}
```

**Ошибки:**

| Код | Причина |
|-----|---------|
| 400 | Нет обязательных полей / неверный тип тренировки / неверный формат файла |
| 401 | Неверный токен |
| 500 | Ошибка записи в БД (транзакция откатывается) |

> **Производительность:** телеметрия вставляется пакетами по 500 строк за один INSERT.

---

## 3. Workouts — Чтение

### `GET /workouts/feed` — Лента тренировок

Возвращает свои тренировки + тренировки друзей со статусом `accepted`, новые первые.

**Query params:**

| Параметр | Default | Max | Описание |
|----------|---------|-----|---------|
| `limit` | 20 | 50 | Кол-во записей |
| `offset` | 0 | — | Смещение для пагинации |

**Пример:** `GET /workouts/feed?limit=10&offset=20`

**Ответ 200:**
```json
{
  "workouts": [
    {
      "id": 42,
      "user_id": 1,
      "user_name": "Айбек Усупов",
      "avatar_path": "/uploads/avatars/user_1_abc.jpg",
      "type": "run",
      "start_time": "2024-03-04 08:00:00",
      "duration": 3600,
      "distance": "10.50",
      "avg_pace": "5.71",
      "avg_heart_rate": 145,
      "map_image_path": null,
      "created_at": "2024-03-04 09:01:00"
    }
  ]
}
```

---

### `GET /workout/{id}` — Детали тренировки

Возвращает сводку и **все точки телеметрии** — используется для отрисовки графиков пульса/высоты и маршрута на карте.

**Пример:** `GET /workout/42`

**Ответ 200:**
```json
{
  "workout": {
    "id": 42,
    "user_id": 1,
    "user_name": "Айбек Усупов",
    "type": "run",
    "start_time": "2024-03-04 08:00:00",
    "duration": 3600,
    "distance": "10.50",
    "avg_pace": "5.71",
    "avg_heart_rate": 145,
    "map_image_path": null
  },
  "telemetry": [
    {
      "id": 1,
      "timestamp": 1709538000,
      "lat": "42.8746210",
      "lon": "74.5898430",
      "altitude": "800.50",
      "heart_rate": 140,
      "accel_x": 0.12,
      "accel_y": -0.05,
      "accel_z": 9.81
    }
  ]
}
```

**Ошибки:**

| Код | Причина |
|-----|---------|
| 404 | Тренировка не найдена |

---

## 4. Social

### `GET /users/search?query=...` — Поиск пользователей

Поиск по имени и email. Минимальная длина запроса — 2 символа. Максимум 20 результатов.

**Пример:** `GET /users/search?query=Айбек`

**Ответ 200:**
```json
{
  "users": [
    {
      "id": 5,
      "name": "Айбек Усупов",
      "email": "aibek@example.com",
      "avatar_path": null
    }
  ]
}
```

---

### `POST /friends/add` — Добавить друга / подписаться

**Body (JSON):**
```json
{
  "user_id": 5
}
```

**Ответ 200:**
```json
{
  "status": "pending",
  "message": "Запрос на дружбу отправлен!"
}
```

Возможные значения `status`:

| Значение | Смысл |
|----------|-------|
| `pending` | Запрос отправлен, ждёт подтверждения |
| `accepted` | Уже в друзьях |
| `rejected` | Запрос ранее был отклонён |

**Ошибки:**

| Код | Причина |
|-----|---------|
| 400 | Нет `user_id` / попытка добавить себя |
| 404 | Пользователь не найден |

---

### `GET /profile/{id}` — Профиль пользователя

**Пример:** `GET /profile/1`

**Ответ 200:**
```json
{
  "user": {
    "id": 1,
    "name": "Айбек Усупов",
    "email": "aibek@example.com",
    "avatar_path": null
  },
  "stats": {
    "total_workouts": 24,
    "total_km": "245.30",
    "total_seconds": 86400
  },
  "monthly_volume": [
    { "day": "2024-02-03", "km": "5.20" },
    { "day": "2024-02-05", "km": "10.50" },
    { "day": "2024-02-07", "km": "8.00" }
  ],
  "friends": [
    {
      "id": 3,
      "name": "Жылдыз Мамытова",
      "avatar_path": null,
      "status": "accepted",
      "created_at": "2024-02-01 12:00:00"
    }
  ],
  "recent_workouts": []
}
```

Поле `monthly_volume` — данные за последние **30 дней**, агрегированные по дням (`GROUP BY DATE`).
Используй для построения bar-chart объёма тренировок.

---

### `POST /profile/avatar` — Загрузить аватар

```
Content-Type: multipart/form-data
Authorization: Bearer <token>
```

Поле: `avatar` — файл изображения (jpg / jpeg / png / gif / webp).

**Ответ 200:**
```json
{
  "avatar_path": "/uploads/avatars/user_1_abc123.jpg"
}
```

---

## 5. Content

### `GET /news` — Лента новостей

**Query params:** `limit` (default: 20, max: 50), `offset` (default: 0)

**Ответ 200:**
```json
{
  "news": [
    {
      "id": 1,
      "title": "Бишкек проводит марафон «Ала-Тоо Run 2024»",
      "content": "В столице Кыргызстана пройдёт ежегодный марафон...",
      "image_url": null,
      "created_at": "2024-03-04 10:00:00"
    }
  ],
  "message": "Последние новости спорта Кыргызстана"
}
```

---

## Общие коды ошибок

| Код | Значение |
|-----|---------|
| 400 | Bad Request — неверные входные данные |
| 401 | Unauthorized — токен отсутствует или неверный |
| 404 | Not Found — ресурс не существует |
| 409 | Conflict — например, email уже занят |
| 500 | Server Error — внутренняя ошибка сервера |

Формат ошибки **всегда**:
```json
{
  "error": "Описание ошибки"
}
```

---

## Примеры запросов (curl)

```bash
# Регистрация
curl -X POST http://localhost/sportapp/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Айбек","email":"test@mail.com","password":"pass123"}'

# Логин
curl -X POST http://localhost/sportapp/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@mail.com","password":"pass123"}'

# Загрузить тренировку (JSON)
curl -X POST http://localhost/sportapp/workouts/upload \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "run",
    "start_time": "2024-03-04 08:00:00",
    "duration": 1800,
    "distance": 5.0,
    "telemetry": [
      {"timestamp":1709538000,"lat":42.87,"lon":74.59,"altitude":800,"heart_rate":140}
    ]
  }'

# Лента тренировок
curl "http://localhost/sportapp/workouts/feed?limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Детали тренировки
curl http://localhost/sportapp/workout/42 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Поиск пользователей
curl "http://localhost/sportapp/users/search?query=Айбек" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Добавить друга
curl -X POST http://localhost/sportapp/friends/add \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 5}'

# Профиль пользователя
curl http://localhost/sportapp/profile/1 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Загрузить аватар
curl -X POST http://localhost/sportapp/profile/avatar \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "avatar=@/path/to/photo.jpg"

# Новости
curl http://localhost/sportapp/news \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Установка и запуск

### 1. База данных

```bash
"Скачать и подключить php 
https://www.google.com/search?q=rfr+crfxfnm+%D0%B7%D1%80%D0%B7&rlz=1C1YTUH_ruKG1032KG1068&oq=rfr+crfxfnm+%D0%B7%D1%80%D0%B7&gs_lcrp=EgZjaHJvbWUyBggAEEUYOTIJCAEQABgNGIAEMgkIAhAAGA0YgAQyCQgDEAAYDRiABDIJCAQQABgNGIAEMgkIBRAAGA0YgAQyCQgGEAAYDRiABDIICAcQABgWGB4yCAgIEAAYFhgeMggICRAAGBYYHtIBCDM1MDZqMGoxqAIAsAIA&sourceid=chrome&ie=UTF-8#fpstate=ive&vld=cid:803cef94,vid:MyRWEOSO5lY,st:0"
"Запустить в xampp MySql, Apache"
"открыть http://localhost/phpmyadmin"
"содай sportapp таблицу"
"импортировать sql/schema.sql"
"запустить сервер на локалке
php -S localhost:8000"
```

### 2. Конфигурация

Отредактируй [config/config.php](config/config.php):

```php
'db' => [
    'host'     => 'localhost',
    'dbname'   => 'sportapp',
    'user'     => 'root',
    'password' => 'your_password',
],
```

### 3. Веб-сервер

**Apache** — файл `.htaccess` уже создан. Убедись что включён `mod_rewrite` и `AllowOverride All`.

**Nginx:**
```nginx
location /sportapp/ {
    try_files $uri $uri/ /sportapp/index.php?$query_string;
}
```

### 4. Права на папку uploads

```bash
# Linux/Mac
chmod -R 755 uploads/

# Windows: папки uploads/avatars/ и uploads/maps/ уже созданы
```

