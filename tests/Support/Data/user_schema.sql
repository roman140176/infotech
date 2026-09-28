-- Таблица пользователей в формате стандартной миграции Yii2 (m130524_201442_init + verification_token).
-- Нужна только для локального SQLite: на стенде хелпер работает с его реальной схемой.
DROP TABLE IF EXISTS "user";

CREATE TABLE "user" (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    username             VARCHAR(255) NOT NULL UNIQUE,
    auth_key             VARCHAR(32)  NOT NULL,
    password_hash        VARCHAR(255) NOT NULL,
    password_reset_token VARCHAR(255) UNIQUE,
    verification_token   VARCHAR(255) DEFAULT NULL,
    email                VARCHAR(255) NOT NULL UNIQUE,
    status               SMALLINT     NOT NULL DEFAULT 10,
    created_at           INTEGER      NOT NULL,
    updated_at           INTEGER      NOT NULL
);
