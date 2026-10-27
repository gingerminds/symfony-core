<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Voter;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Resource\ResourceDefinition;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The voter of every registered resource: `VIEW`, `CREATE`, `EDIT` and `DELETE` on a resource
 * name (`is_granted('CREATE', 'product')`), an entity class or an entity of a resource
 * (`is_granted('EDIT', product)`), the entity class of the resource being the configured one
 * (`resources.*.entity`, possibly overridden by the project).
 *
 * An attribute is granted to a user holding the resource's permission for it
 * (ResourceDefinition::permissionFor(): `view|edit|edit|delete <permission>` by default,
 * `resources.*.permissions` / `#[AsCrudController(permissions: ...)]` to map another one).
 * Rules that only add access (an author editing their own article, a resource readable without
 * token) belong in an additional voter: the default `affirmative` strategy grants as soon as one
 * voter does. A rule that must deny despite the permission goes in an override of
 * voteOnResource() (`gingerminds_core.security.voter.resource` service class).
 *
 * Built-in account rules: a user always views and edits their own account, and never deletes it.
 *
 * @extends Voter<string, mixed>
 */
class ResourceVoter extends Voter
{
    public const string VIEW = 'VIEW';
    public const string CREATE = 'CREATE';
    public const string EDIT = 'EDIT';
    public const string DELETE = 'DELETE';

    public const array ATTRIBUTES = [self::VIEW, self::CREATE, self::EDIT, self::DELETE];

    public function __construct(
        private readonly ResourceRegistry $resources,
    ) {
    }

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, true);
    }

    public function supportsType(string $subjectType): bool
    {
        // A subject type is a loaded class (an object's) or a builtin type: no autoloading.
        return 'string' === $subjectType
            || (class_exists($subjectType, false) && $this->resources->findByEntity($subjectType) instanceof ResourceDefinition);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->supportsAttribute($attribute) && $this->findResource($subject) instanceof ResourceDefinition;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $resource = $this->findResource($subject);

        if (!$user instanceof UserInterface || !$resource instanceof ResourceDefinition) {
            return false;
        }

        return $this->voteOnResource($attribute, $resource, \is_object($subject) ? $subject : null, $user, $vote);
    }

    /**
     * @param string      $attribute one of self::ATTRIBUTES
     * @param object|null $subject   the entity, null for a resource name or an entity class
     */
    protected function voteOnResource(string $attribute, ResourceDefinition $resource, ?object $subject, UserInterface $user, ?Vote $vote = null): bool
    {
        if ($subject instanceof UserInterface && $this->isOwnAccount($user, $subject)) {
            if (\in_array($attribute, [self::VIEW, self::EDIT], true)) {
                $vote?->addReason('A user views and edits their own account.');

                return true;
            }

            if (self::DELETE === $attribute) {
                $vote?->addReason('A user never deletes their own account.');

                return false;
            }
        }

        $permission = $resource->permissionFor($attribute);
        $vote?->addReason(\sprintf('The "%s" permission is required.', $permission));

        return $user->hasPermission($permission);
    }

    private function isOwnAccount(UserInterface $user, UserInterface $subject): bool
    {
        return null !== $user->getId() && $subject->getId() === $user->getId();
    }

    /**
     * The resource of a subject: an entity of a resource, a resource name or an entity class.
     */
    private function findResource(mixed $subject): ?ResourceDefinition
    {
        if (\is_object($subject)) {
            return $this->resources->findByEntity($subject);
        }

        if (!\is_string($subject)) {
            return null;
        }

        if ($this->resources->has($subject)) {
            return $this->resources->get($subject);
        }

        return class_exists($subject) ? $this->resources->findByEntity($subject) : null;
    }
}
