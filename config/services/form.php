<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Form\Autocomplete\ResourceAutocompleter;
use Gingerminds\CoreBundle\Form\Extension\DisplayTimezoneTypeExtension;
use Gingerminds\CoreBundle\Form\Extension\InputGroupTypeExtension;
use Gingerminds\CoreBundle\Form\Extension\SizeTypeExtension;
use Gingerminds\CoreBundle\Form\Permission\PermissionType;
use Gingerminds\CoreBundle\Form\Role\RoleType;
use Gingerminds\CoreBundle\Form\Type\ContributorSelectorType;
use Gingerminds\CoreBundle\Form\Type\ResourceAutocompleteType;
use Gingerminds\CoreBundle\Form\User\ContributorType;
use Gingerminds\CoreBundle\Form\User\ProfileType;
use Gingerminds\CoreBundle\Form\User\UserType;

/*
 * Form types and extensions.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_core.form.type.user', UserType::class)
        ->args([service('gingerminds_core.resource_registry'), service('gingerminds_core.repository.contributor'), service('translator')])
        ->tag('form.type');
    $services->set('gingerminds_core.form.type.profile', ProfileType::class)
        ->args([service('gingerminds_core.resource_registry'), service('gingerminds_core.repository.contributor'), service('translator')])
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

    // One child per resource, aliased by its name (ResourceRegistryPass).
    $services->set('gingerminds_core.form.autocompleter', ResourceAutocompleter::class)
        ->abstract()
        ->args([abstract_arg('resource name'), service('gingerminds_core.resource_registry'), service('gingerminds_core.api.filter_parameters')]);

    $services->set('gingerminds_core.form.type.resource_autocomplete', ResourceAutocompleteType::class)
        ->args([service('gingerminds_core.resource_registry'), service('router')])
        ->tag('form.type');
    $services->set('gingerminds_core.form.type.contributor_selector', ContributorSelectorType::class)
        ->args([service('gingerminds_core.repository.contributor'), service('translator'), service('router')])
        ->tag('form.type');

    $services->set('gingerminds_core.form.extension.size', SizeTypeExtension::class)
        ->tag('form.type_extension');
    $services->set('gingerminds_core.form.extension.input_group', InputGroupTypeExtension::class)
        ->tag('form.type_extension');
    $services->set('gingerminds_core.form.extension.display_timezone', DisplayTimezoneTypeExtension::class)
        ->args([service('gingerminds_core.display_timezone')])
        ->tag('form.type_extension');
};
