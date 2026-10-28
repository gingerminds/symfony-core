<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Controller;

use Gingerminds\CoreBundle\Controller\AbstractTreeCrudController;
use Gingerminds\CoreBundle\Resource\AsCrudController;
use Gingerminds\CoreBundle\Tests\Application\Entity\Section;
use Gingerminds\CoreBundle\Tests\Application\Form\SectionType;

/**
 * Tree resource (TreeTest), `/admin/sections`.
 */
#[AsCrudController(resource: 'section', entity: Section::class, form: SectionType::class)]
final class SectionController extends AbstractTreeCrudController
{
}
