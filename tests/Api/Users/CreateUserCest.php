<?php

declare(strict_types=1);

namespace Tests\Api\Users;

use Codeception\Attribute\DataProvider;
use Codeception\Attribute\Group;
use Codeception\Example;
use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * POST /api/v1/users — создание пользователя.
 *
 * Сам запрос:
 *   POST /api/v1/users
 *   Authorization: Bearer <token>
 *   Content-Type: application/json
 *   {"email": "...", "password": "...", "first_name": "...", "last_name": "...", "role": "manager"}
 */
#[Group('api'), Group('users')]
final class CreateUserCest
{
    private string $token;

    public function _before(ApiTester $I): void
    {
        $this->token = getenv('API_TOKEN') ?: 'test-api-token';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
    }

    public function createUser(ApiTester $I): void
    {
        $payload = $this->validPayload();

        $I->amBearerAuthenticated($this->token);
        $I->sendPost('/users', $payload);

        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonType([
            'id' => 'integer:>0',
            'email' => 'string:email',
            'first_name' => 'string',
            'last_name' => 'string',
            'role' => 'string',
            'status' => 'integer',
            'created_at' => 'string:date',
        ]);
        $I->seeResponseContainsJson([
            'email' => $payload['email'],
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'role' => $payload['role'],
        ]);

        // пароль и его хэш наружу не отдаются
        $I->dontSeeResponseJsonMatchesJsonPath('$.password');
        $I->dontSeeResponseJsonMatchesJsonPath('$.password_hash');

        [$id] = $I->grabDataFromResponseByJsonPath('$.id');
        $I->seeHttpHeader('Location');
        $I->assertStringEndsWith("/users/{$id}", $I->grabHttpHeader('Location'));
    }

    public function rejectDuplicateEmail(ApiTester $I): void
    {
        $payload = $this->validPayload();
        $I->amBearerAuthenticated($this->token);

        $I->sendPost('/users', $payload);
        $I->seeResponseCodeIs(HttpCode::CREATED);

        // тот же адрес в другом регистре — тоже дубль
        $I->sendPost('/users', ['email' => strtoupper($payload['email'])] + $payload);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
        $I->seeResponseContainsJson([['field' => 'email']]);
    }

    #[DataProvider('invalidPayloadProvider')]
    public function rejectInvalidPayload(ApiTester $I, Example $example): void
    {
        $payload = array_merge($this->validPayload(), $example['override']);

        $I->amBearerAuthenticated($this->token);
        $I->sendPost('/users', $payload);

        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
        $I->seeResponseMatchesJsonType(['field' => 'string', 'message' => 'string'], '$[*]');
        $I->seeResponseContainsJson([['field' => $example['field']]]);
    }

    public function rejectRequestWithoutToken(ApiTester $I): void
    {
        $I->sendPost('/users', $this->validPayload());

        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function rejectRequestWithInvalidToken(ApiTester $I): void
    {
        $I->amBearerAuthenticated('invalid-token');
        $I->sendPost('/users', $this->validPayload());

        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    protected function invalidPayloadProvider(): array
    {
        return [
            'без email' => ['override' => ['email' => ''], 'field' => 'email'],
            'некорректный email' => ['override' => ['email' => 'not-an-email'], 'field' => 'email'],
            'короткий пароль' => ['override' => ['password' => 'short'], 'field' => 'password'],
            'без имени' => ['override' => ['first_name' => '  '], 'field' => 'first_name'],
            'неизвестная роль' => ['override' => ['role' => 'superuser'], 'field' => 'role'],
        ];
    }

    /**
     * @return array{email: string, password: string, first_name: string, last_name: string, role: string}
     */
    private function validPayload(): array
    {
        return [
            'email' => 'aqa.' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => 'Aqa_' . bin2hex(random_bytes(6)),
            'first_name' => 'Иван',
            'last_name' => 'Тестов',
            'role' => 'manager',
        ];
    }
}
