<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Api;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Entity\Security\ApiToken;
use Gingerminds\CoreBundle\Tests\Application\Security\LoginEventSpy;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;
use Gingerminds\CoreBundle\Tests\Functional\Fixtures;

/**
 * API login (ApiLoginAuthenticator on the `api_login` firewall), bearer tokens
 * (`access_token` on the `api` firewall) and logout (LogoutEvent).
 */
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

    public function testApiIsOpenByDefault(): void
    {
        $this->api('GET', '/api/products');
        $this->assertStatus(200);

        foreach (['/api/users', '/api/contributors', '/api/roles', '/api/permissions'] as $uri) {
            $this->api('GET', $uri);
            $this->assertStatus(401);
        }
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

    public function testLoginResponseIsEnrichedAndDispatchesTheNativeEvent(): void
    {
        $this->fixtures->user('event@example.com');

        $data = $this->api('POST', '/api/login', json: ['email' => '  event@example.com ', 'password' => Fixtures::PASSWORD]);

        $this->assertStatus(200);
        self::assertSame(['token', 'token_type', 'expires_at', 'email'], array_keys($data));
        self::assertNull($data['expires_at']);
        // TestLoginResponseEnricher (LoginResponseEnricherInterface).
        self::assertSame('event@example.com', $data['email']);
        // The API rate limit still applies to the login, answered by the firewall.
        self::assertResponseHeaderSame('X-RateLimit-Limit', '3');
        self::assertResponseHeaderSame('X-RateLimit-Remaining', '2');
        self::assertSame(['api_login:event@example.com'], self::getContainer()->get(LoginEventSpy::class)->logins);
    }

    public function testUnknownUserGetsTheInvalidCredentialsError(): void
    {
        $data = $this->api('POST', '/api/login', json: ['email' => 'nobody@example.com', 'password' => Fixtures::PASSWORD], locale: 'en');

        $this->assertStatus(401);
        self::assertSame(['message' => 'Invalid credentials.'], $data);
        self::assertResponseNotHasHeader('WWW-Authenticate');
        self::assertSame([], self::getContainer()->get(LoginEventSpy::class)->logins);
    }

    public function testLoginRejectsAnEmptyOrMalformedPayload(): void
    {
        $data = $this->api('POST', '/api/login', json: ['email' => 'john@example.com', 'password' => ''], locale: 'en');
        $this->assertStatus(422);
        self::assertSame(['message' => 'The "email" and "password" fields are required.'], $data);

        $this->client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT_LANGUAGE' => 'en'], content: '{not json');
        $this->assertStatus(422);
    }

    public function testLoginIsThrottled(): void
    {
        // gingerminds_core.api.login_throttling.max_attempts: 2 in the test application;
        // the limiter state lives in the kernel (in-memory storage).
        $this->client->disableReboot();
        $this->fixtures->user('throttled@example.com');

        foreach (range(1, 2) as $ignored) {
            $this->api('POST', '/api/login', json: ['email' => 'throttled@example.com', 'password' => 'wrong-password']);
            $this->assertStatus(401);
        }

        // Even the right password is refused once throttled, whatever the email case.
        $data = $this->api('POST', '/api/login', json: ['email' => 'THROTTLED@example.com', 'password' => Fixtures::PASSWORD], locale: 'en');

        $this->assertStatus(429);
        self::assertSame(['message' => 'Too many login attempts, please try again in a few minutes.'], $data);
        self::assertArrayNotHasKey('token', $data);
    }

    public function testSuccessfulLoginResetsTheThrottling(): void
    {
        $this->client->disableReboot();
        $this->fixtures->user('reset@example.com');

        $this->api('POST', '/api/login', json: ['email' => 'reset@example.com', 'password' => 'wrong-password']);
        $this->assertStatus(401);
        $this->api('POST', '/api/login', json: ['email' => 'reset@example.com', 'password' => Fixtures::PASSWORD]);
        $this->assertStatus(200);
        $this->api('POST', '/api/login', json: ['email' => 'reset@example.com', 'password' => 'wrong-password']);
        $this->assertStatus(401);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $user = $this->fixtures->user('expired@example.com', ['view users']);
        $plain = ApiToken::generatePlainToken();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new ApiToken($user, 'test', $plain, new \DateTimeImmutable('-1 minute')));
        $entityManager->flush();

        $data = $this->api('GET', '/api/users', $plain, locale: 'en');

        $this->assertStatus(401);
        self::assertSame('Invalid or expired API token.', $data['message']);
    }

    public function testLogoutRequiresAValidToken(): void
    {
        $data = $this->api('POST', '/api/logout', locale: 'en');
        $this->assertStatus(401);
        self::assertSame(['message' => 'Authentication required.'], $data);

        $data = $this->api('POST', '/api/logout', 'gm_invalid', locale: 'en');
        $this->assertStatus(401);
        self::assertSame(['message' => 'Invalid or expired API token.'], $data);

        $this->api('GET', '/api/logout', $this->fixtures->token($this->fixtures->user('get@example.com')));
        $this->assertStatus(405);
    }

    public function testLogoutDispatchesTheNativeEvent(): void
    {
        $token = $this->fixtures->token($this->fixtures->user('bye@example.com'));

        $data = $this->api('POST', '/api/logout', $token, locale: 'en');

        $this->assertStatus(200);
        self::assertSame(['message' => 'Logged out.'], $data);
        self::assertResponseHeaderSame('X-RateLimit-Limit', '3');
        self::assertSame(['bye@example.com'], self::getContainer()->get(LoginEventSpy::class)->logouts);
    }
}
