<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Form\Extension\SizeTypeExtension;
use Gingerminds\CoreBundle\Form\Permission\PermissionType;
use Gingerminds\CoreBundle\Form\Role\RoleType;
use Gingerminds\CoreBundle\Form\User\ContributorType;
use Gingerminds\CoreBundle\Form\User\ProfileType;
use Gingerminds\CoreBundle\Form\User\UserType;
use Gingerminds\CoreBundle\Repository\User\ContributorRepository;

/*
 * Form types and extensions.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_core.form.type.user', UserType::class)
        ->args([service('gingerminds_core.resource_registry'), service(ContributorRepository::class), service('translator')])
        ->tag('form.type');
    $services->set('gingerminds_core.form.type.profile', ProfileType::class)
        ->args([service('gingerminds_core.resource_registry'), service(ContributorRepository::class), service('translator')])
        ->tag('form.type');

    foreach (
        [
            'contributor' => ContributorType::class,
            'role' => RoleType::class,
            'permission' => PermissionType::class,
        ] as $name => $class
    ) {
        $services->set('gingerminds_core.form.type.' . $name, $class)
            ->args([service('gingerminds_core.resource_registry')])
            ->tag('form.type');
    }

    $services->set('gingerminds_core.form.extension.size', SizeTypeExtension::class)
        ->tag('form.type_extension');
};
