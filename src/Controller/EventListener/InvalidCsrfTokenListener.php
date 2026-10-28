<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\EventListener;

use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An invalid CSRF token on an admin resource route (`#[IsCsrfTokenValid]` of
 * AbstractCrudController::delete() or of a subclass' action) keeps the user on the admin: a
 * `flash.invalid_csrf` flash and a redirection to the page he came from (a referer of the same
 * host), or else to the resource list.
 *
 * The token check throws an InvalidCsrfTokenException, an AuthenticationException that the
 * firewall's ExceptionListener (`kernel.exception`, priority 1) would turn into a redirection to
 * the login page, logging nobody out but losing the page. This listener runs before it (priority
 * 2) and only for the HTML (or Turbo Stream) requests of a resource route (`_resource` default):
 * the API and JSON requests keep the firewall's handling.
 */
final readonly class InvalidCsrfTokenListener
{
    public const int PRIORITY = 2;

    private const array FORMATS = ['html', 'turbo_stream'];

    public function __construct(
        private ResourceRegistry $resources,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $resource = $request->attributes->get('_resource');

        if (
            !$event->getThrowable() instanceof InvalidCsrfTokenException
            || !\is_string($resource)
            || !$this->resources->has($resource)
            || !\in_array($request->getPreferredFormat(), self::FORMATS, true)
            || !$request->hasSession()
        ) {
            return;
        }

        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('danger', $this->translator->trans('flash.invalid_csrf', [], 'GingermindsCore'));
        }

        $event->setResponse(new RedirectResponse($this->getTargetUrl($request, $resource), Response::HTTP_SEE_OTHER));
    }

    private function getTargetUrl(Request $request, string $resource): string
    {
        $referer = $request->headers->get('referer');

        if (\is_string($referer) && str_starts_with($referer, $request->getSchemeAndHttpHost() . '/')) {
            return $referer;
        }

        $parameters = $request->attributes->get('_route_params', []);
        $parameters = \is_array($parameters) ? $parameters : [];
        unset($parameters['id'], $parameters['_resource']);

        return $this->urlGenerator->generate($this->resources->get($resource)->route('index'), $parameters);
    }
}
