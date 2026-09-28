<?php

declare(strict_types=1);

namespace Tests\Support\Page\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * Страница авторизации /login (Yii2 ActiveForm, модель LoginForm).
 */
final class LoginPage
{
    // язык фиксируем явно, чтобы тексты ошибок не зависели от локали браузера
    public const URL = '/login?language=ru_RU';
    public const PATH = '/login';

    public const FORM = '#login-form';
    public const EMAIL = '#loginform-email';
    public const PASSWORD = '#loginform-password';
    public const SUBMIT = '#login-form button[type=submit]';
    public const FORGOT_PASSWORD_LINK = '#login-form a[href="/site/restore-password"]';

    // контейнеры полей: Yii вешает на них .has-error, текст ошибки — в .help-block-error
    public const EMAIL_FIELD = '.field-loginform-email';
    public const PASSWORD_FIELD = '.field-loginform-password';

    public const MSG_EMAIL_REQUIRED = 'Необходимо заполнить «Электронная почта».';
    public const MSG_PASSWORD_REQUIRED = 'Необходимо заполнить «Пароль».';
    public const MSG_EMAIL_INVALID = 'Некорректный email';
    public const MSG_WRONG_CREDENTIALS = 'Некорректный email / пароль';

    // первый заход может уйти на JS-проверку антибота — даём ей время
    private const PAGE_TIMEOUT = 30;
    private const UI_TIMEOUT = 10;

    public function __construct(private readonly AcceptanceTester $I)
    {
    }

    public function open(): self
    {
        $this->I->amOnPage(self::URL);
        $this->I->waitForElementVisible(self::FORM, self::PAGE_TIMEOUT);

        return $this;
    }

    public function fillCredentials(string $email, string $password): self
    {
        $this->I->fillField(self::EMAIL, $email);
        $this->I->fillField(self::PASSWORD, $password);

        return $this;
    }

    public function submit(): self
    {
        $this->I->click(self::SUBMIT);

        return $this;
    }

    public function loginAs(string $email, string $password): self
    {
        return $this->fillCredentials($email, $password)->submit();
    }

    /**
     * Ждёт текст ошибки под полем: клиентская валидация появляется без перезагрузки,
     * серверная — после ответа на POST, поэтому ожидание, а не мгновенная проверка.
     */
    public function seeFieldError(string $field, string $message): self
    {
        $this->I->waitForText($message, self::UI_TIMEOUT, "{$field} .help-block-error");
        $this->I->seeElement("{$field}.has-error");

        return $this;
    }

    public function dontSeeFieldError(string $field): self
    {
        $this->I->dontSeeElement("{$field}.has-error");

        return $this;
    }

    public function seeStillOnLoginPage(): self
    {
        $this->I->seeCurrentUrlMatches('~^' . preg_quote(self::PATH, '~') . '(\?|$)~');
        $this->I->seeElement(self::FORM);

        return $this;
    }
}
