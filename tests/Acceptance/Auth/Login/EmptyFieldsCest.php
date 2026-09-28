<?php

declare(strict_types=1);

namespace Tests\Acceptance\Auth\Login;

use Codeception\Attribute\DataProvider;
use Codeception\Attribute\Group;
use Codeception\Example;
use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Acceptance\LoginPage;

/**
 * Нажатие «Войти» с незаполненными полями.
 * Валидация клиентская (yiiActiveForm): запрос на сервер не уходит, страница не перезагружается.
 */
#[Group('auth'), Group('login')]
final class EmptyFieldsCest
{
    public function _before(LoginPage $loginPage): void
    {
        $loginPage->open();
    }

    public function bothFieldsEmpty(AcceptanceTester $I, LoginPage $loginPage): void
    {
        $csrfBefore = $I->grabValueFrom('input[name=_csrf]');

        $loginPage->submit();

        $loginPage
            ->seeFieldError(LoginPage::EMAIL_FIELD, LoginPage::MSG_EMAIL_REQUIRED)
            ->seeFieldError(LoginPage::PASSWORD_FIELD, LoginPage::MSG_PASSWORD_REQUIRED)
            ->seeStillOnLoginPage();

        // тот же CSRF-токен — значит, форма не отправлялась и страница не перерисовывалась
        $I->seeInField('input[name=_csrf]', $csrfBefore);
        $I->seeElement(LoginPage::EMAIL, ['aria-invalid' => 'true']);
        $I->seeElement(LoginPage::PASSWORD, ['aria-invalid' => 'true']);
    }

    #[DataProvider('partiallyFilledProvider')]
    public function oneFieldEmpty(LoginPage $loginPage, Example $example): void
    {
        $loginPage->loginAs($example['email'], $example['password']);

        $loginPage
            ->seeFieldError($example['errorField'], $example['message'])
            ->dontSeeFieldError($example['validField'])
            ->seeStillOnLoginPage();
    }

    protected function partiallyFilledProvider(): array
    {
        return [
            'пустой пароль' => [
                'email' => 'aqa.nonexistent.user@example.com',
                'password' => '',
                'errorField' => LoginPage::PASSWORD_FIELD,
                'validField' => LoginPage::EMAIL_FIELD,
                'message' => LoginPage::MSG_PASSWORD_REQUIRED,
            ],
            'пустой email' => [
                'email' => '',
                'password' => 'AnyPassword123',
                'errorField' => LoginPage::EMAIL_FIELD,
                'validField' => LoginPage::PASSWORD_FIELD,
                'message' => LoginPage::MSG_EMAIL_REQUIRED,
            ],
            // required-валидатор Yii обрезает пробелы — такое поле считается пустым
            'email из одних пробелов' => [
                'email' => '   ',
                'password' => 'AnyPassword123',
                'errorField' => LoginPage::EMAIL_FIELD,
                'validField' => LoginPage::PASSWORD_FIELD,
                'message' => LoginPage::MSG_EMAIL_REQUIRED,
            ],
        ];
    }
}
