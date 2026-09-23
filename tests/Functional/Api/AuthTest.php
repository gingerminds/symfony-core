<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Api;

use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;
use Gingerminds\CoreBundle\Tests\Functional\Fixtures;

final class AuthTest extends ApiTestCase
{
    public function testLoginIssuesATokenUsableOnTheApi(): void
    {
        $this->fixtures->user('jane@example.com', ['view users']);

        $data = $this->api('POST', '/api/login', json: ['email' => 'jane@example.com', 'password' => Fixtures::PASSWORD]);

        $this->assertStatus(200);
        self::assertSame('Bearer', $data['token_type']);
        self::assertStringStartsWith('gm_', $data['token']);

        $this->api('GET', '/api/users', $data['token']);
        $this->assertStatus(200);
    }

    public function testLoginIsCaseInsensitiveOnEmail(): void
    {
        $this->fixtures->user('case@example.com');

        $this->api('POST', '/api/login', json: ['email' => 'CASE@example.com', 'password' => Fixtures::PASSWORD]);

        $this->assertStatus(200);
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        $this->fixtures->user('john@example.com');

        $data = $this->api('POST', '/api/login', json: ['email' => 'john@example.com', 'password' => 'wrong-password']);

        $this->assertStatus(401);
        self::assertArrayNotHasKey('token', $data);
    }

    public function testMessagesAreTranslated(): void
    {
        $this->fixtures->user('i18n@example.com');

        $data = $this->api('POST', '/api/login', json: ['email' => 'i18n@example.com', 'password' => 'wrong-password'], locale: 'fr-FR,fr;q=0.9');
        self::assertSame('Identifiants invalides.', $data['message']);

        $data = $this->api('POST', '/api/login', json: ['email' => 'i18n@example.com', 'password' => 'wrong-password'], locale: 'en');
        self::assertSame('Invalid credentials.', $data['message']);

        $data = $this->api('GET', '/api/users', 'gm_invalid', locale: 'en');
        $this->assertStatus(401);
        self::assertSame('Invalid or expired API token.', $data['message']);
        self::assertStringContainsString('Invalid or expired API token.', (string) $this->client->getResponse()->headers->get('WWW-Authenticate'));
    }

    public function testLoginRequiresBothFields(): void
    {
        $this->api('POST', '/api/login', json: ['email' => 'john@example.com']);

        $this->assertStatus(422);
    }

    public function testApiRequiresAToken(): void
    {
        $this->api('GET', '/api/users');
        $this->assertStatus(401);

        $this->api('GET', '/api/users', 'gm_invalid');
        $this->assertStatus(401);
    }

    public function testLogoutRevokesTheCurrentToken(): void
    {
        $user = $this->fixtures->user('logout@example.com', ['view users']);
        $current = $this->fixtures->token($user);
        $other = $this->fixtures->token($user);

        $this->api('POST', '/api/logout', $current);
        $this->assertStatus(200);

        $this->api('GET', '/api/users', $current);
        $this->assertStatus(401);

        $this->api('GET', '/api/users', $other);
        $this->assertStatus(200);
    }

    public function testLogoutCanRevokeEveryToken(): void
    {
        $user = $this->fixtures->user('revoke@example.com', ['view users']);
        $current = $this->fixtures->token($user);
        $other = $this->fixtures->token($user);

        $this->api('POST', '/api/logout', $current, ['revoke_all' => true]);
        $this->assertStatus(200);

        $this->api('GET', '/api/users', $other);
        $this->assertStatus(401);
    }
}
