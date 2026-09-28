<?php

/**
 * Заглушка API пользователей для запуска примера без реального бэкенда.
 * Поднимается автоматически расширением RunProcess (см. tests/Api.suite.yml):
 *   php -S 127.0.0.1:8089 tests/Support/MockApi/router.php
 *
 * Контракт повторяет yii\rest\ActiveController: 201 + Location при создании,
 * 422 со списком [{field, message}] при ошибках валидации.
 */

declare(strict_types=1);

const API_TOKEN = 'test-api-token';
const ROLES = ['manager', 'admin', 'viewer'];

header('Content-Type: application/json; charset=UTF-8');

function respond(int $status, mixed $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/api/v1/users') {
    respond(404, ['name' => 'Not Found', 'message' => 'Страница не найдена.', 'status' => 404]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['name' => 'Method Not Allowed', 'message' => 'Метод не поддерживается.', 'status' => 405]);
}
if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer ' . API_TOKEN) {
    respond(401, ['name' => 'Unauthorized', 'message' => 'Your request was made with invalid credentials.', 'status' => 401]);
}

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    respond(400, ['name' => 'Bad Request', 'message' => 'Invalid JSON data in request body', 'status' => 400]);
}

// пользователи живут, пока жив процесс сервера; файл в tests/_output (чистится codecept clean)
$storage = dirname(__DIR__, 2) . '/_output/mock-api-users-' . getmypid() . '.json';
$users = is_file($storage) ? json_decode((string)file_get_contents($storage), true) : [];

$email = trim((string)($data['email'] ?? ''));
$errors = [];

if ($email === '') {
    $errors[] = ['field' => 'email', 'message' => 'Необходимо заполнить «Email».'];
} elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = ['field' => 'email', 'message' => 'Значение «Email» не является правильным email адресом.'];
} elseif (in_array(mb_strtolower($email), array_map(static fn (array $u) => mb_strtolower($u['email']), $users), true)) {
    $errors[] = ['field' => 'email', 'message' => 'Значение «' . $email . '» для «Email» уже занято.'];
}
if (mb_strlen((string)($data['password'] ?? '')) < 8) {
    $errors[] = ['field' => 'password', 'message' => 'Значение «Пароль» должно содержать минимум 8 символов.'];
}
foreach (['first_name' => 'Имя', 'last_name' => 'Фамилия'] as $field => $label) {
    if (trim((string)($data[$field] ?? '')) === '') {
        $errors[] = ['field' => $field, 'message' => "Необходимо заполнить «{$label}»."];
    }
}
if (!in_array($data['role'] ?? null, ROLES, true)) {
    $errors[] = ['field' => 'role', 'message' => 'Значение «Роль» неверно.'];
}

if ($errors !== []) {
    respond(422, $errors);
}

$user = [
    'id' => count($users) + 1,
    'email' => $email,
    'first_name' => trim($data['first_name']),
    'last_name' => trim($data['last_name']),
    'role' => $data['role'],
    'status' => 10,
    'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
];
$users[] = $user;
file_put_contents($storage, json_encode($users));

header('Location: http://' . $_SERVER['HTTP_HOST'] . '/api/v1/users/' . $user['id']);
respond(201, $user);
