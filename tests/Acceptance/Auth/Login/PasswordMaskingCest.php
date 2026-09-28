<?php

declare(strict_types=1);

namespace Tests\Acceptance\Auth\Login;

use Codeception\Attribute\Group;
use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Acceptance\LoginPage;

/**
 * Маскировка пароля: символы не отображаются ни при вводе, ни после ответа сервера.
 */
#[Group('auth'), Group('login')]
final class PasswordMaskingCest
{
    private const PASSWORD = 'VisibleSecret_42';

    public function _before(LoginPage $loginPage): void
    {
        $loginPage->open();
    }

    public function passwordFieldIsMaskedWhileTyping(AcceptanceTester $I): void
    {
        $I->seeElement(LoginPage::PASSWORD, ['type' => 'password']);

        $I->fillField(LoginPage::PASSWORD, self::PASSWORD);

        // значение в поле есть, но браузер рисует его точками: тип не сменился на text
        $I->seeInField(LoginPage::PASSWORD, self::PASSWORD);
        $I->seeElement(LoginPage::PASSWORD, ['type' => 'password']);
        $I->dontSee(self::PASSWORD);
    }

    public function passwordStaysMaskedAfterFailedLogin(AcceptanceTester $I, LoginPage $loginPage): void
    {
        $loginPage->loginAs('aqa.nonexistent.user@example.com', self::PASSWORD);
        $loginPage->seeFieldError(LoginPage::PASSWORD_FIELD, LoginPage::MSG_WRONG_CREDENTIALS);

        // после серверной перерисовки формы поле по-прежнему password
        $I->seeElement(LoginPage::PASSWORD, ['type' => 'password']);
        $I->dontSee(self::PASSWORD);
    }

    /**
     * Известный дефект: после неудачного входа сервер возвращает введённый пароль
     * в HTML (value="..." у поля пароля). Пароль виден в исходном коде страницы,
     * попадает в кэш браузера/прокси и в сохранённые копии страницы.
     *
     * По умолчанию не запускается (composer test:ui пропускает known-issues),
     * воспроизвести: vendor/bin/codecept run Acceptance -g known-issues
     */
    #[Group('known-issues'), Group('security')]
    public function passwordIsNotEchoedBackInPageSource(AcceptanceTester $I, LoginPage $loginPage): void
    {
        $loginPage->loginAs('aqa.nonexistent.user@example.com', self::PASSWORD);
        $loginPage->seeFieldError(LoginPage::PASSWORD_FIELD, LoginPage::MSG_WRONG_CREDENTIALS);

        // именно HTML-атрибут из ответа сервера, а не текущее значение поля (property)
        $serverRenderedValue = $I->executeJS(
            'return document.querySelector(arguments[0]).getAttribute("value");',
            [LoginPage::PASSWORD]
        );
        $I->assertEmpty($serverRenderedValue, 'Пароль не должен возвращаться сервером в HTML после неудачного входа');
    }
}
