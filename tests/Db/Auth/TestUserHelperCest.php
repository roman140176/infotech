<?php

declare(strict_types=1);

namespace Tests\Db\Auth;

use Codeception\Attribute\Group;
use Tests\Support\DbTester;
use Tests\Support\Helper\TestUserHelper;

/**
 * Проверяет сам хелпер: пользователь появляется в таблице до теста,
 * пароль хэширован совместимо с Yii2, после теста запись удаляется.
 */
#[Group('helpers')]
final class TestUserHelperCest
{
    #[Group(TestUserHelper::GROUP)]
    public function userIsInsertedBeforeTest(DbTester $I): void
    {
        $user = $I->grabTestUser();

        $I->seeInDatabase('user', ['id' => $user['id'], 'email' => $user['email'], 'status' => 10]);

        $hash = $I->grabFromDatabase('user', 'password_hash', ['id' => $user['id']]);
        $I->assertStringStartsWith('$2y$', $hash, 'Yii2 Security::validatePassword() ожидает bcrypt с префиксом $2y$');
        $I->assertTrue(password_verify($user['password'], $hash));
        $I->dontSeeInDatabase('user', ['password_hash' => $user['password']]);

        // в таблице только пользователь этого теста — от соседних тестов ничего не осталось
        $I->seeNumRecords(1, 'user');
    }

    public function fieldsCanBeOverridden(DbTester $I): void
    {
        $user = $I->haveTestUser([
            'email' => 'aqa.blocked@example.test',
            'password' => 'Blocked_123',
            'status' => 0,
        ]);

        $I->seeInDatabase('user', ['id' => $user['id'], 'email' => 'aqa.blocked@example.test', 'status' => 0]);
        $I->assertSame('Blocked_123', $user['password']);
        $I->seeNumRecords(1, 'user');
    }

    public function userIsNotCreatedWithoutGroup(DbTester $I): void
    {
        $I->seeNumRecords(0, 'user');
    }
}
