<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\Controller;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Cmf\Component\Routing\RedirectRouteInterface;

/**
 * Default router that handles redirection route objects.
 */
class RedirectController
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @param RouterInterface $router the router to use to build urls
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Action to redirect based on a RedirectRouteInterface route.
     *
     * @param RedirectRouteInterface $contentDocument
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse the response
     */
    public function redirectAction(RedirectRouteInterface $contentDocument)
    {
        // autoroute
//        $routeTarget = $routeDocument->getRedirectTarget();
//        $url         = $this->router->generate($routeTarget);
//
//        return new RedirectResponse($url, 302);


        $url = $contentDocument->getUri();

        if (empty($url)) {
            $routeTarget = $contentDocument->getRouteTarget();
            if ($routeTarget) {
                $url = $this->router->generate($routeTarget, $contentDocument->getParameters(), UrlGeneratorInterface::ABSOLUTE_URL);
            } else {
                $routeName = $contentDocument->getRouteName();
                $url = $this->router->generate($routeName, $contentDocument->getParameters(), UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        return new RedirectResponse($url, $contentDocument->isPermanent() ? 301 : 302);
    }
}
