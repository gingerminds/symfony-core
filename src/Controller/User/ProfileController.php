<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\User;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Form\User\ProfileType;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The account of the logged in user.
 */
class ProfileController extends AbstractController
{
    /**
     * @param class-string<ProfileType> $formType
     */
    public function __construct(
        protected readonly ManagerRegistry $doctrine,
        protected readonly TranslatorInterface $translator,
        protected readonly string $formType = ProfileType::class,
    ) {
    }

    #[IsGranted('IS_AUTHENTICATED')]
    public function __invoke(Request $request, #[CurrentUser] UserInterface $user): Response
    {
        $form = $this->createForm($this->formType, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $repository = $this->doctrine->getRepository($user::class);

            if ($repository instanceof RepositoryInterface) {
                $repository->save($user);
            }

            $this->addFlash('success', $this->translator->trans('flash.profile_updated', [], 'GingermindsCore'));

            return $this->redirectToRoute('gingerminds_core_profile', status: Response::HTTP_SEE_OTHER);
        }

        // AbstractController::render() answers 422 for a submitted invalid form.
        return $this->render('@GingermindsCore/pages/profile/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
}
