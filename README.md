# Автотесты формы авторизации CRM Stella

Тестовое задание AQA (PHP): автотесты на Codeception 5 для https://stellantis.autocrm.ru/.

| Пункт задания | Где |
|---|---|
| 1. Функциональные автотесты формы входа | [`tests/Acceptance/Auth`](tests/Acceptance/Auth) |
| 2. Хелпер, вставляющий тестового пользователя в БД | [`tests/Support/Helper/TestUserHelper.php`](tests/Support/Helper/TestUserHelper.php) и его самопроверка в [`tests/Db`](tests/Db) |
| 3. Пример API-запроса на создание пользователя | [`tests/Api/Users/CreateUserCest.php`](tests/Api/Users/CreateUserCest.php), ниже раздел «API» |
| 4. Какие сценарии ещё проверить | [`SCENARIOS.md`](SCENARIOS.md) |

## Запуск

Нужны PHP >=8.3 (расширения `curl`, `dom`, `mbstring`, `pdo_sqlite`), Composer и Docker.

```bash
composer install
docker compose up -d      # Selenium + Chrome на :4444
composer test             # все сьюты
```

По отдельности:

```bash
composer test:ui          # UI-тесты формы входа на живом стенде
composer test:db          # хелпер TestUserHelper на локальном SQLite
composer test:api         # API создания пользователя на локальной заглушке
composer test:known-issues   # воспроизведение найденного дефекта (падает, пока не исправят)

vendor/bin/codecept run Acceptance -g password-recovery   # одна группа
vendor/bin/codecept run Acceptance --debug                # подробный лог шагов
```

Прогон можно смотреть вживую в браузере: http://localhost:7900 (noVNC, пароль `secret`).
При падении скриншот и HTML страницы сохраняются в `tests/_output/`.

> Перед сайтом стоит WAF с JS-проверкой (ServicePipe). Поэтому UI-тесты идут через настоящий браузер (WebDriver),
> PhpBrowser до формы не доберётся. Запросы через VPN или прокси WAF может отбрасывать — запускайте с прямым подключением.

## Структура

```
tests/
├── Acceptance/                       UI, живой стенд, WebDriver
│   └── Auth/
│       ├── Login/
│       │   ├── InvalidCredentialsCest.php   невалидные логин/пароль, формат email
│       │   ├── EmptyFieldsCest.php          пустые поля и поля из пробелов
│       │   ├── PasswordMaskingCest.php      маскировка пароля (+ найденный дефект)
│       │   └── SuccessfulLoginCest.php      вход пользователем из хелпера (только --env stage)
│       └── PasswordRecovery/
│           └── ForgotPasswordLinkCest.php   переход по «Забыли пароль»
├── Api/Users/CreateUserCest.php      POST /api/v1/users
├── Db/Auth/TestUserHelperCest.php    самопроверка хелпера
└── Support/
    ├── Page/Acceptance/              Page Object: селекторы и тексты в одном месте
    ├── Helper/TestUserHelper.php     тестовый пользователь в БД
    ├── MockApi/router.php            заглушка API для запуска примера
    └── Data/user_schema.sql          схема таблицы user (Yii2) для SQLite
```

Тесты разложены по разделам системы (`Auth/Login`, `Auth/PasswordRecovery`, `Api/Users`), у каждого класса есть группы
(`auth`, `login`, `password-recovery`, `api`), чтобы гонять раздел целиком. Селекторы и тексты сообщений живут только в Page Object:
если вёрстка поменяется, правка будет в одном месте.

Про устройство тестов:

- **Данные.** Используются только несуществующие адреса в зарезервированном домене `example.com`, чтобы не задеть реальные учётки и не заблокировать их.
- **Ожидания.** Явные: `waitForElementVisible`, `waitForText`, `waitForJS`. Фиксированных `sleep` нет, неявное ожидание WebDriver не используется.
- **Изоляция.** Браузер перезапускается на каждый тест (`restart: true`), cookies чистятся. Тесты не зависят друг от друга и от порядка запуска.
- **Язык интерфейса.** Зафиксирован через `?language=ru_RU`, поэтому тексты ошибок не зависят от локали машины.

## Хелпер тестового пользователя

`TestUserHelper` — модуль Codeception поверх штатного модуля `Db`. Перед каждым тестом с группой `needs-test-user`
он вставляет пользователя в таблицу `user`, а после теста модуль `Db` удаляет эту запись.

```php
#[Group(TestUserHelper::GROUP)]            // 'needs-test-user'
public function userCanLogIn(AcceptanceTester $I, LoginPage $loginPage): void
{
    $user = $I->grabTestUser();             // ['id' => …, 'email' => …, 'password' => …]
    $loginPage->open()->loginAs($user['email'], $user['password']);
    …
}

// или вручную, с нужными полями:
$I->haveTestUser(['status' => 0, 'password' => 'Blocked_123']);
```

- Пароль хэшируется bcrypt'ом (`$2y$`) — тот же формат, что у `Yii::$app->security->generatePasswordHash()`, поэтому приложение на Yii2 примет такого пользователя.
- **Реальная БД не нужна.** По умолчанию `Db` смотрит в локальный SQLite-файл (`tests/_output/stella_test.sqlite`), схема грузится из `tests/Support/Data/user_schema.sql`.
- **На стенде с доступом к базе** достаточно переключить окружение, код тестов не меняется:

```bash
STAGE_URL=https://stage.example STAGE_DB_DSN='mysql:host=…;dbname=…' STAGE_DB_USER=… STAGE_DB_PASSWORD=… \
  vendor/bin/codecept run Acceptance --env stage
```

В этом окружении дополнительно запускается `SuccessfulLoginCest` — вход под пользователем, которого хелпер только что создал.

## API

Пример запроса на создание пользователя:

```http
POST /api/v1/users HTTP/1.1
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json

{"email": "ivan.testov@example.test", "password": "Str0ng_Passw0rd", "first_name": "Иван", "last_name": "Тестов", "role": "manager"}
```

```bash
curl -X POST http://127.0.0.1:8089/api/v1/users \
  -H 'Authorization: Bearer test-api-token' -H 'Content-Type: application/json' \
  -d '{"email":"ivan.testov@example.test","password":"Str0ng_Passw0rd","first_name":"Иван","last_name":"Тестов","role":"manager"}'
```

Ожидаемый ответ — `201 Created` с заголовком `Location: …/api/v1/users/{id}` и телом без пароля:

```json
{"id": 1, "email": "ivan.testov@example.test", "first_name": "Иван", "last_name": "Тестов", "role": "manager", "status": 10, "created_at": "2026-09-28T12:00:00Z"}
```

Ошибки валидации — `422` в формате `yii\rest\ActiveController`: `[{"field": "email", "message": "…"}]`.

`CreateUserCest` проверяет:

- успешное создание: схему ответа, `Location` и что пароль не утекает в ответ;
- дубль email, в том числе в другом регистре;
- невалидные поля (датапровайдер);
- запросы без токена и с чужим токеном.

Публичного API у стенда нет, поэтому сьют сам поднимает заглушку [`tests/Support/MockApi/router.php`](tests/Support/MockApi/router.php)
(расширение `RunProcess`). Против настоящего API: `API_URL=https://…/api/v1 API_TOKEN=… vendor/bin/codecept run Api --env real`.
