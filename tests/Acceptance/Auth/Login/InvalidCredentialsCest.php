<?php

declare(strict_types=1);

namespace Tests\Acceptance\Auth\Login;

use Codeception\Attribute\DataProvider;
use Codeception\Attribute\Group;
use Codeception\Example;
use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Acceptance\LoginPage;

/**
 * Вход с невалидными логином/паролем.
 * Используются только заведомо несуществующие адреса в домене example.com (RFC 2606),
 * чтобы не задеть реальные учётки и не спровоцировать их блокировку.
 */
#[Group('auth'), Group('login')]
final class InvalidCredentialsCest
{
    private const UNKNOWN_EMAIL = 'aqa.nonexistent.user@example.com';

    public function _before(LoginPage $loginPage): void
    {
        $loginPage->open();
    }

    #[DataProvider('rejectedByServerProvider')]
    public function serverRejectsUnknownCredentials(AcceptanceTester $I, LoginPage $loginPage, Example $example): void
    {
        $loginPage->loginAs($example['email'], $example['password']);

        // ошибка общая и висит на поле пароля — не раскрывает, что именно неверно
        $loginPage
            ->seeFieldError(LoginPage::PASSWORD_FIELD, LoginPage::MSG_WRONG_CREDENTIALS)
            ->dontSeeFieldError(LoginPage::EMAIL_FIELD)
            ->seeStillOnLoginPage();

        // введённый email сохраняется, чтобы пользователю не набирать его заново
        $I->seeInField(LoginPage::EMAIL, $example['email']);
    }

    #[DataProvider('rejectedByClientProvider')]
    public function clientValidationRejectsMalformedEmail(AcceptanceTester $I, LoginPage $loginPage, Example $example): void
    {
        $loginPage->loginAs($example['email'], 'AnyPassword123');

        $loginPage
            ->seeFieldError(LoginPage::EMAIL_FIELD, LoginPage::MSG_EMAIL_INVALID)
            ->seeStillOnLoginPage();
        $I->dontSee(LoginPage::MSG_WRONG_CREDENTIALS);
    }

    protected function rejectedByServerProvider(): array
    {
        return [
            'несуществующий пользователь' => ['email' => self::UNKNOWN_EMAIL, 'password' => 'WrongPassword123'],
            'email в верхнем регистре' => ['email' => strtoupper(self::UNKNOWN_EMAIL), 'password' => 'WrongPassword123'],
            'SQL-инъекция в пароле' => ['email' => self::UNKNOWN_EMAIL, 'password' => "' OR '1'='1' -- "],
        ];
    }

    protected function rejectedByClientProvider(): array
    {
        return [
            'нет символа @' => ['email' => 'aqa.nonexistent.user.example.com'],
            'нет домена' => ['email' => 'aqa.nonexistent.user@'],
            'кириллица в адресе' => ['email' => 'тест@example.com'],
            'SQL-инъекция в логине' => ['email' => "admin' OR '1'='1"],
        ];
    }
}
