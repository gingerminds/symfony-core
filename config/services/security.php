<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepository;
use Gingerminds\CoreBundle\Repository\User\UserRepository;
use Gingerminds\CoreBundle\Security\Api\ApiAuthenticationFailureHandler;
use Gingerminds\CoreBundle\Security\Api\ApiRateLimitListener;
use Gingerminds\CoreBundle\Security\Api\ApiTokenHandler;
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

/*
 * Voters, user provider, API token handler, login listeners, API rate limit.
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
        // After the firewall (8, the key needs the API token user), before the API
        // response cache (5, a cached response is limited too).
        ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => 7])
        // After the API response cache stored the response (-10): headers are per client.
        ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse', 'priority' => -20]);
};
