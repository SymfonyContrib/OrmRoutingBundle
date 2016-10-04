<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\Adapter;

use Doctrine\ORM\EntityManager;
use Doctrine\Common\Util\ClassUtils;
use SymfonyContrib\Bundle\OrmRoutingBundle\Entity\Route;
use Symfony\Cmf\Component\RoutingAuto\Model\AutoRouteInterface;
use Symfony\Cmf\Component\RoutingAuto\UriContext;
use Symfony\Cmf\Component\RoutingAuto\AdapterInterface;

/**
 * Doctrine ORM adapter.
 */
class DoctrineOrmAdapter implements AdapterInterface
{
    const TAG_NO_MULTILANG = 'no-multilang';

    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocales($contentEntity)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function translateObject($contentEntity, $locale)
    {
        throw new \BadMethodCallException('Translation not supported with Doctrine ORM adapter');
    }

    /**
     * {@inheritdoc}
     */
    public function generateAutoRouteTag(UriContext $uriContext)
    {
        return self::TAG_NO_MULTILANG;
    }

    /**
     * {@inheritdoc}
     */
    public function migrateAutoRouteChildren(AutoRouteInterface $srcAutoRoute, AutoRouteInterface $destAutoRoute)
    {
        throw new \RuntimeException('TODO');
    }

    /**
     * {@inheritdoc}
     */
    public function removeAutoRoute(AutoRouteInterface $autoRoute)
    {
        $this->entityManager->remove($autoRoute);
    }

    /**
     * {@inheritdoc}
     */
    public function createAutoRoute(UriContext $uriContext, $contentEntity, $autoRouteTag)
    {
        $name  = get_class($contentEntity).':'.$contentEntity->getId();
        $route = new Route();
        $route->setName($name);
        $route->setContent($contentEntity);
        $route->setStaticPrefix($uriContext->getUri());
        $route->setAutoRouteTag($autoRouteTag);
        $route->setType(AutoRouteInterface::TYPE_PRIMARY);

        foreach ($uriContext->getDefaults() as $key => $value) {
            $route->setDefault($key, $value);
        }

        $this->entityManager->persist($route);

        return $route;
    }

    public function createOrUpdateAutoRoute(UriContext $uriContext, $contentEntity, $autoRouteTag)
    {
        $name  = get_class($contentEntity).':'.$contentEntity->getId();

        if (!$route = $this->findRouteForName($name)) {
            $route = new Route();
            $route->setName($name);
        }

        $route->setContent($contentEntity);
        $route->setStaticPrefix($uriContext->getUri());
//        $route->setAutoRouteTag($autoRouteTag);
        $route->setType(Route::TYPE_AUTO);

        foreach ($uriContext->getDefaults() as $key => $value) {
            $route->setDefault($key, $value);
        }

        $this->entityManager->persist($route);

        return $route;
    }

    /**
     * {@inheritdoc}
     */
    public function createRedirectRoute(AutoRouteInterface $referringAutoRoute, AutoRouteInterface $newRoute)
    {
        $referringAutoRoute->setRedirectTarget($newRoute);
        $referringAutoRoute->setType(AutoRouteInterface::TYPE_REDIRECT);
    }

    /**
     * {@inheritdoc}
     */
    public function getRealClassName($className)
    {
        return ClassUtils::getRealClass($className);
    }

    /**
     * {@inheritdoc}
     */
    public function compareAutoRouteContent(AutoRouteInterface $autoRoute, $contentEntity)
    {
        if ($autoRoute->getContent() === $contentEntity) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getReferringAutoRoutes($contentEntity)
    {
        throw new \RuntimeException('TODO');
    }

    /**
     * {@inheritdoc}
     */
    public function findRouteForUri($uri, UriContext $uriContext)
    {
        return $this->entityManager
            ->getRepository(Route::class)
            ->findOneBy(['staticPrefix' => $uri]);
    }

    public function findRouteForName($name)
    {
        return $this->entityManager
            ->getRepository(Route::class)
            ->findOneBy(['name' => $name]);
    }
}
