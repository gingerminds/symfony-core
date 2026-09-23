<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * @property CrudContext $context
 */
trait ControllerTrait
{
    /**
     * @param array<string, mixed> $parameters
     */
    protected function render(string $template, array $parameters = [], ?Response $response = null): Response
    {
        $response ??= new Response();

        return $response->setContent($this->context->twig->render($template, $parameters));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function redirectToRoute(string $route, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->context->urlGenerator->generate($route, $parameters), Response::HTTP_SEE_OTHER);
    }

    protected function denyAccessUnlessGranted(string $attribute, mixed $subject = null): void
    {
        if (!$this->context->authorizationChecker->isGranted($attribute, $subject)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes([$attribute]);
            $exception->setSubject($subject);

            throw $exception;
        }
    }

    protected function isGranted(string $attribute, mixed $subject = null): bool
    {
        return $this->context->authorizationChecker->isGranted($attribute, $subject);
    }

    protected function isCsrfTokenValid(string $id, ?string $token): bool
    {
        return $this->context->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }

    protected function addFlash(Request $request, string $type, string $message): void
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $key, array $parameters = [], string $domain = 'GingermindsCore'): string
    {
        return $this->context->translator->trans($key, $parameters, $domain);
    }
}
