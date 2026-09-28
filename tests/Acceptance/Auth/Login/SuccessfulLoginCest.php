<?php

declare(strict_types=1);

namespace Tests\Acceptance\Auth\Login;

use Codeception\Attribute\Env;
use Codeception\Attribute\Group;
use Tests\Support\AcceptanceTester;
use Tests\Support\Helper\TestUserHelper;
use Tests\Support\Page\Acceptance\LoginPage;

/**
 * Вход под пользователем, которого TestUserHelper вставил в БД перед тестом.
 *
 * Работает только на стенде, к БД которого есть доступ: vendor/bin/codecept run Acceptance --env stage.
 * На публичном стенде без доступа к его базе тест не запускается.
 */
#[Env('stage')]
#[Group('auth'), Group('login'), Group(TestUserHelper::GROUP)]
final class SuccessfulLoginCest
{
    public function userCanLogIn(AcceptanceTester $I, LoginPage $loginPage): void
    {
        $user = $I->grabTestUser();

        $loginPage->open()->loginAs($user['email'], $user['password']);

        // ждём либо ухода со страницы входа, либо серверной ошибки —
        // чтобы при падении видеть причину, а не голый таймаут
        $I->waitForJS(
            "return location.pathname !== '/login' || document.querySelector('.field-loginform-password.has-error') !== null;",
            15
        );
        $I->dontSee(LoginPage::MSG_WRONG_CREDENTIALS);
        $I->dontSeeElement(LoginPage::FORM);
        $I->dontSeeInCurrentUrl(LoginPage::PATH);
    }
}
