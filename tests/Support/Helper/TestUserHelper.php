<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Lib\Interfaces\DependsOnModule;
use Codeception\Module;
use Codeception\Module\Db;
use Codeception\TestInterface;

/**
 * Создаёт тестового пользователя прямо в таблице пользователей перед тестом авторизации.
 *
 * Тесту достаточно группы "needs-test-user" — пользователь будет вставлен в _before(),
 * данные для входа берутся через $I->grabTestUser(). Вставка идёт через haveInDatabase(),
 * поэтому модуль Db сам удалит запись после теста и тесты не оставляют мусора.
 *
 * Хэш пароля совместим с Yii2: Security::validatePassword() проверяет bcrypt ($2y$) любой стоимости.
 */
final class TestUserHelper extends Module implements DependsOnModule
{
    public const GROUP = 'needs-test-user';

    private const STATUS_ACTIVE = 10;

    protected array $config = [
        'table' => 'user',
        // в тестах дешёвый хэш, чтобы не тратить время на bcrypt с cost 13
        'passwordCost' => 4,
    ];

    private Db $db;

    /** @var array{id: int, email: string, password: string}|null */
    private ?array $user = null;

    public function _depends(): array
    {
        return [Db::class => 'TestUserHelper пишет пользователя через модуль Db: включите Db в сьюте и укажите хелперу "depends: Db"'];
    }

    public function _inject(Db $db): void
    {
        $this->db = $db;
    }

    public function _before(TestInterface $test): void
    {
        $this->user = null;

        if (in_array(self::GROUP, $test->getMetadata()->getGroups(), true)) {
            $this->haveTestUser();
        }
    }

    /**
     * Вставляет пользователя, поля можно переопределить: haveTestUser(['status' => 0]).
     * Пароль передаётся открытым текстом в ключе 'password' — в базу пишется только хэш.
     *
     * @param array<string, mixed> $overrides
     * @return array{id: int, email: string, password: string}
     */
    public function haveTestUser(array $overrides = []): array
    {
        $password = (string)($overrides['password'] ?? 'Aqa_' . bin2hex(random_bytes(6)));
        unset($overrides['password']);

        $suffix = bin2hex(random_bytes(4));
        $now = time();

        $row = array_merge([
            'username' => "aqa_{$suffix}",
            'email' => "aqa.{$suffix}@example.test",
            'auth_key' => bin2hex(random_bytes(16)),
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->config['passwordCost']]),
            'status' => self::STATUS_ACTIVE,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        $id = $this->db->haveInDatabase($this->config['table'], $row);
        $this->debugSection('TestUser', "#{$id} {$row['email']}");

        return $this->user = ['id' => $id, 'email' => (string)$row['email'], 'password' => $password];
    }

    /**
     * @return array{id: int, email: string, password: string}
     */
    public function grabTestUser(): array
    {
        if ($this->user === null) {
            $this->fail('Тестовый пользователь не создан: добавьте тесту группу "' . self::GROUP . '" или вызовите haveTestUser()');
        }

        return $this->user;
    }
}
