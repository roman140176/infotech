<?php

declare(strict_types=1);

namespace Tests\Support\Page\Acceptance;

/**
 * Страница восстановления пароля /site/restore-password (модель RestorePasswordForm).
 */
final class RestorePasswordPage
{
    public const PATH = '/site/restore-password';
    public const TITLE = 'Восстановление пароля';

    public const HEADING = '.authorization__heading';
    public const FORM = '#reset-form';
    public const EMAIL = '#restorepasswordform-email';
    public const CAPTCHA_INPUT = '#restorepasswordform-verifycode';
    public const CAPTCHA_IMAGE = '#restorepasswordform-verifycode-image';
    public const SUBMIT = '#reset-form button[name=reset-password-button]';
}
