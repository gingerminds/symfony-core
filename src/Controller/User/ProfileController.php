<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\User;

use Gingerminds\CoreBundle\Controller\ControllerTrait;
use Gingerminds\CoreBundle\Controller\CrudContext;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Form\User\ProfileType;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ProfileController
{
    use ControllerTrait;

    /**
     * @param class-string<ProfileType> $formType
     */
    public function __construct(
        protected readonly CrudContext $context,
        protected readonly Security $security,
        protected readonly string $formType = ProfileType::class,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof UserInterface) {
            throw new AccessDeniedException();
        }

        $form = $this->context->formFactory->create($this->formType, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $repository = $this->context->doctrine->getRepository($user::class);

            if ($repository instanceof RepositoryInterface) {
                $repository->save($user, $form);
            }

            $this->addFlash($request, 'success', $this->trans('flash.profile_updated'));

            return $this->redirectToRoute('gingerminds_core_profile');
        }

        return $this->render('@GingermindsCore/pages/profile/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
