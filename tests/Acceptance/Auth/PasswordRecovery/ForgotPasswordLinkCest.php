<?php

declare(strict_types=1);

namespace Tests\Acceptance\Auth\PasswordRecovery;

use Codeception\Attribute\Group;
use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Acceptance\LoginPage;
use Tests\Support\Page\Acceptance\RestorePasswordPage;

/**
 * Переход по ссылке «Забыли пароль» со страницы входа.
 * Саму отправку формы восстановления не автоматизируем: она закрыта капчей.
 */
#[Group('auth'), Group('password-recovery')]
final class ForgotPasswordLinkCest
{
    public function _before(LoginPage $loginPage): void
    {
        $loginPage->open();
    }

    public function linkLeadsToRestorePasswordPage(AcceptanceTester $I): void
    {
        $I->seeLink('Забыли пароль', RestorePasswordPage::PATH);

        $I->click('Забыли пароль', LoginPage::FORM);

        $I->waitForElementVisible(RestorePasswordPage::FORM, 30);
        $I->seeCurrentUrlEquals(RestorePasswordPage::PATH);
        $I->seeInTitle(RestorePasswordPage::TITLE);
        $I->see(RestorePasswordPage::TITLE, RestorePasswordPage::HEADING);
    }

    public function restorePasswordFormIsReady(AcceptanceTester $I): void
    {
        $I->click('Забыли пароль', LoginPage::FORM);
        $I->waitForElementVisible(RestorePasswordPage::FORM, 30);

        $I->seeElement(RestorePasswordPage::EMAIL);
        $I->seeElement(RestorePasswordPage::CAPTCHA_INPUT);
        $I->seeElement(RestorePasswordPage::CAPTCHA_IMAGE);
        $I->see('Восстановить', RestorePasswordPage::SUBMIT);

        // картинка капчи реально загрузилась, а не битая (грузится асинхронно — ждём)
        $I->waitForJS(
            sprintf(
                'const img = document.querySelector(%s); return img !== null && img.complete && img.naturalWidth > 0;',
                json_encode(RestorePasswordPage::CAPTCHA_IMAGE)
            ),
            10
        );

        // старая страница входа не осталась в DOM
        $I->dontSeeElement(LoginPage::FORM);
    }
}
