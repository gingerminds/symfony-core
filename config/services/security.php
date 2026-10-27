<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepository;
use Gingerminds\CoreBundle\Repository\User\UserRepository;
use Gingerminds\CoreBundle\Security\Api\ApiAuthenticationFailureHandler;
use Gingerminds\CoreBundle\Security\Api\ApiLoginAuthenticator;
use Gingerminds\CoreBundle\Security\Api\ApiLoginFailureHandler;
use Gingerminds\CoreBundle\Security\Api\ApiLoginSuccessHandler;
use Gingerminds\CoreBundle\Security\Api\ApiRateLimitListener;
use Gingerminds\CoreBundle\Security\Api\ApiTokenHandler;
use Gingerminds\CoreBundle\Security\Api\ApiTokenRevocationListener;
use Gingerminds\CoreBundle\Security\Authentication\AuthorizedDomainListener;
use Gingerminds\CoreBundle\Security\UserProvider;
use Gingerminds\CoreBundle\Security\Voter\Permission\PermissionVoter;
use Gingerminds\CoreBundle\Security\Voter\PermissionNameVoter;
use Gingerminds\CoreBundle\Security\Voter\Role\RoleVoter;
use Gingerminds\CoreBundle\Security\Voter\SuperAdminVoter;
use Gingerminds\CoreBundle\Security\Voter\User\ContributorVoter;
use Gingerminds\CoreBundle\Security\Voter\User\UserVoter;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\RateLimiter\DefaultLoginRateLimiter;

/*
 * Voters, user provider, API login authenticator / token handler / logout, login listeners, API rate limit.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_core.security.voter.super_admin', SuperAdminVoter::class)
        ->tag('security.voter', ['priority' => 300]);
    $services->set('gingerminds_core.security.voter.permission_name', PermissionNameVoter::class)
        ->tag('security.voter');

    foreach (
        [
            'user' => UserVoter::class,
            'contributor' => ContributorVoter::class,
            'role' => RoleVoter::class,
            'permission' => PermissionVoter::class,
        ] as $name => $class
    ) {
        $services->set('gingerminds_core.security.voter.' . $name, $class)
            ->tag('security.voter');
    }

    $services->set('gingerminds_core.security.user_provider', UserProvider::class)
        ->args([service(UserRepository::class)]);
    $services->alias(UserProvider::class, 'gingerminds_core.security.user_provider');

    $services->set('gingerminds_core.security.api_failure_handler', ApiAuthenticationFailureHandler::class)
        ->args([service('translator')]);
    $services->alias(ApiAuthenticationFailureHandler::class, 'gingerminds_core.security.api_failure_handler');

    $services->set('gingerminds_core.security.api_token_handler', ApiTokenHandler::class)
        ->args([service(ApiTokenRepository::class), service('clock')]);
    $services->alias(ApiTokenHandler::class, 'gingerminds_core.security.api_token_handler');

    // API login (`custom_authenticators` + `login_throttling.limiter` of the login firewall).
    $services->set('gingerminds_core.security.api_login_success_handler', ApiLoginSuccessHandler::class)
        ->args([
            service(ApiTokenRepository::class),
            tagged_iterator('gingerminds_core.login_response_enricher'),
            param('gingerminds_core.api.token_ttl'),
            service('clock'),
        ]);
    $services->alias(ApiLoginSuccessHandler::class, 'gingerminds_core.security.api_login_success_handler');

    $services->set('gingerminds_core.security.api_login_failure_handler', ApiLoginFailureHandler::class)
        ->args([service('translator')]);
    $services->alias(ApiLoginFailureHandler::class, 'gingerminds_core.security.api_login_failure_handler');

    $services->set('gingerminds_core.security.api_login_authenticator', ApiLoginAuthenticator::class)
        ->args([
            service('gingerminds_core.security.api_login_success_handler'),
            service('gingerminds_core.security.api_login_failure_handler'),
        ]);
    $services->alias(ApiLoginAuthenticator::class, 'gingerminds_core.security.api_login_authenticator');

    // Symfony's own login_throttling limiter (username + IP, and a 5x wider one per IP),
    // fed by the limiters prepended from gingerminds_core.api.login_throttling.
    $services->set('gingerminds_core.security.api_login_rate_limiter', DefaultLoginRateLimiter::class)
        ->args([
            service('limiter.gingerminds_core_api_login_global'),
            service('limiter.gingerminds_core_api_login'),
            param('kernel.secret'),
        ]);

    // API logout: revokes the bearer token on the LogoutEvent of Security::logout().
    $services->set('gingerminds_core.security.api_token_revocation_listener', ApiTokenRevocationListener::class)
        ->args([service(ApiTokenRepository::class), service('security.access_token_extractor.header')])
        ->tag('kernel.event_listener', ['event' => LogoutEvent::class]);

    $services->set('gingerminds_core.security.authorized_domain_listener', AuthorizedDomainListener::class)
        ->args([service('request_stack'), param('gingerminds_core.security.authorized_domains')])
        ->tag('kernel.event_listener', ['event' => CheckPassportEvent::class]);

    $services->set('gingerminds_core.security.api_rate_limit_listener', ApiRateLimitListener::class)
        ->args([
            tagged_locator('rate_limiter', 'name'),
            service('api_platform.metadata.resource.metadata_collection_factory'),
            service('security.helper'),
            service('translator'),
            param('gingerminds_core.api.prefix'),
            param('gingerminds_core.api.rate_limit.enabled'),
        ])
        // The API login is answered by the firewall (8): limited just before it.
        ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequestBeforeFirewall', 'priority' => 9])
        // After the firewall (8, the key needs the API token user), before the API
        // response cache (5, a cached response is limited too).
        ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => 7])
        // After the API response cache stored the response (-10): headers are per client.
        ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse', 'priority' => -20]);
};
