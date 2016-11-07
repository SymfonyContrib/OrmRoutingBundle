<?php


namespace SymfonyContrib\Bundle\OrmRoutingBundle\Context;

use Symfony\Cmf\Component\RoutingAuto\Model\AutoRouteInterface;
use Symfony\Cmf\Component\RoutingAuto\UriContextCollection as CmfUriContextCollection;

class UriContextCollection extends CmfUriContextCollection
{
    /**
     * Check if any of the UriContexts in the stack contain
     * the given auto route.
     *
     * @param AutoRouteInterface $autoRoute
     *
     * @return bool
     */
    public function containsAutoRoute(AutoRouteInterface $autoRoute)
    {
        foreach ($this->uriContexts as $uriContext) {
            if ($autoRoute->getId() === $uriContext->getAutoRoute()->getId()) {
                return true;
            }
        }

        return false;
    }
}
