<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * `POST /api/login {"email": ..., "password": ...}` as a Symfony authenticator.
 *
 * Not `json_login`: its contract differs from the API one (400 on a missing field
 * instead of 422, untrimmed identifier, non-JSON bodies left to the controller).
 * Everything else is native: the user comes from the firewall provider, the password
 * is checked (and rehashed) by the security listeners, `login_throttling` and the
 * `user_checker` of the firewall apply, `LoginSuccessEvent` / `LoginFailureEvent` are
 * dispatched. Responses are delegated to the success / failure handlers.
 */
final class ApiLoginAuthenticator extends AbstractAuthenticator
{
    public const string ROUTE = 'gingerminds_core_api_login';

    public function __construct(
        private readonly AuthenticationSuccessHandlerInterface $successHandler,
        private readonly AuthenticationFailureHandlerInterface $failureHandler,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && self::ROUTE === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            $payload = [];
        }

        $email = \is_string($payload['email'] ?? null) ? trim($payload['email']) : '';
        $password = \is_string($payload['password'] ?? null) ? $payload['password'] : '';

        if ('' === $email || '' === $password) {
            throw new MissingApiCredentialsException();
        }

        return new Passport(new UserBadge($email), new PasswordCredentials($password));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }
}
