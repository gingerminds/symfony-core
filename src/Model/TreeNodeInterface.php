<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * A node of an admin tree (AbstractTreeCrudController, TreeRepositoryTrait): a `parent`
 * association of its own class and an integer `position` within its level.
 */
interface TreeNodeInterface
{
    public function getId(): int|string|null;

    public function getParent(): ?self;

    public function getPosition(): int;

    public function setPosition(int $position): void;
}
