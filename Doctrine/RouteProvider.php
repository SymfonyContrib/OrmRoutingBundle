<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\Doctrine;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Symfony\Cmf\Component\Routing\Candidates\CandidatesInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Cmf\Component\Routing\RouteProviderInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Provider loading routes from Doctrine.
 *
 * This is <strong>NOT</strong> not a doctrine repository but just the route
 * provider for the NestedMatcher. (you could of course implement this
 * interface in a repository class, if you need that)
 */
class RouteProvider implements RouteProviderInterface
{
    /**
     * If this is null, the manager registry will return the default manager.
     *
     * @var string|null Name of object manager to use
     */
    protected $managerName;

    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    /**
     * Class name of the object class to find, null for PHPCR-ODM as it can
     * determine the class on its own.
     *
     * @var string|null
     */
    protected $className;

    /**
     * Limit to apply when calling getRoutesByNames() with null.
     *
     * @var int|null
     */
    protected $routeCollectionLimit;

    /**
     * @var CandidatesInterface
     */
    private $candidatesStrategy;

    public function __construct(ManagerRegistry $managerRegistry, CandidatesInterface $candidatesStrategy, $className)
    {
        $this->managerRegistry    = $managerRegistry;
        $this->className          = $className;
        $this->candidatesStrategy = $candidatesStrategy;
    }

    /**
     * Set the object manager name to use for this loader. If not set, the
     * default manager as decided by the manager registry will be used.
     *
     * @param string|null $managerName
     */
    public function setManagerName($managerName)
    {
        $this->managerName = $managerName;
    }

    /**
     * Set the limit to apply when calling getAllRoutes().
     *
     * Setting the limit to null means no limit is applied.
     *
     * @param int|null $routeCollectionLimit
     */
    public function setRouteCollectionLimit($routeCollectionLimit = null)
    {
        $this->routeCollectionLimit = $routeCollectionLimit;
    }

    /**
     * Get the object manager named $managerName from the registry.
     *
     * @return ObjectManager
     */
    protected function getObjectManager()
    {
        return $this->managerRegistry->getManager($this->managerName);
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollectionForRequest(Request $request)
    {
        $collection = new RouteCollection();

        $candidates = $this->candidatesStrategy->getCandidates($request);
        if (empty($candidates)) {
            return $collection;
        }
        $routes = $this->getRouteRepository()->findByStaticPrefix($candidates, array('position' => 'ASC'));
        /** @var $route Route */
        foreach ($routes as $route) {
            $collection->add($route->getName(), $route);
        }

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteByName($name)
    {
        if (!$this->candidatesStrategy->isCandidate($name)) {
            throw new RouteNotFoundException(sprintf('Route "%s" is not handled by this route provider', $name));
        }

        $route = $this->getRouteRepository()->findOneBy(array('name' => $name));
        if (!$route) {
            throw new RouteNotFoundException("No route found for name '$name'");
        }

        return $route;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutesByNames($names = null)
    {
        if (null === $names) {
            if (0 === $this->routeCollectionLimit) {
                return array();
            }

            try {
                return $this->getRouteRepository()->findBy(array(), null, $this->routeCollectionLimit ?: null);
            } catch (TableNotFoundException $e) {
                return array();
            }
        }

        $routes = array();
        foreach ($names as $name) {
            // TODO: if we do findByName with multivalue, we need to filter with isCandidate afterwards
            try {
                $routes[] = $this->getRouteByName($name);
            } catch (RouteNotFoundException $e) {
                // not found
            }
        }

        return $routes;
    }

    /**
     * @return ObjectRepository
     */
    protected function getRouteRepository()
    {
        return $this->getObjectManager()->getRepository($this->className);
    }
}
