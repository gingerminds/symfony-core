<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker\Extension;

use Gingerminds\CoreBundle\Maker\ResourceName;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Extends the make:gm:* makers from another bundle (autoconfigured, tag
 * `gingerminds_core.maker_extension`): extra options, changes to the core
 * skeletons and extra generated files, e.g. `--translated` of
 * gingerminds/symfony-multisite.
 */
interface ResourceMakerExtensionInterface
{
    public const string TAG = 'gingerminds_core.maker_extension';

    /**
     * Adds the extension options to a maker (`$commandName`: make:gm:resource, make:gm:entity...).
     */
    public function configureCommand(string $commandName, Command $command): void;

    /**
     * Whether the extension takes part in this run (typically: its option is set).
     */
    public function isEnabled(InputInterface $input): bool;

    /**
     * Called before a core skeleton is rendered (`$template->name`: Entity.tpl.php,
     * FormType.tpl.php, twig/_form.tpl.php...): add use statements or variables,
     * or replace the skeleton path.
     */
    public function configureTemplate(SkeletonTemplate $template, ResourceName $resource): void;

    /**
     * Generates the extension files once the core ones are generated (before they are written).
     *
     * @return list<string> next steps displayed after the success message
     */
    public function generate(ResourceMakerContext $context): array;
}
