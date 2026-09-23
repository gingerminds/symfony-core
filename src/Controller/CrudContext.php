<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class CrudContext
{
    public function __construct(
        public Environment $twig,
        public FormFactoryInterface $formFactory,
        public UrlGeneratorInterface $urlGenerator,
        public AuthorizationCheckerInterface $authorizationChecker,
        public CsrfTokenManagerInterface $csrfTokenManager,
        public TranslatorInterface $translator,
        public ResourceRegistry $resources,
        public ManagerRegistry $doctrine,
    ) {
    }
}
