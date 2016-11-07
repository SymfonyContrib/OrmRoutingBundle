<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\Doctrine;

use Doctrine\Common\Persistence\Event\ManagerEventArgs;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Symfony\Cmf\Component\RoutingAuto\Mapping\MetadataFactory;
use Symfony\Cmf\Component\RoutingAuto\UriContext;
use SymfonyContrib\Bundle\OrmRoutingBundle\Manager\AutoRouteManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use SymfonyContrib\Bundle\OrmRoutingBundle\Context\UriContextCollection;
use Symfony\Cmf\Component\RoutingAuto\Mapping\Exception\ClassNotMappedException;

/**
 * Doctrine ORM listener for maintaining automatic routes.
 */
class AutoRouteListener
{
    /** @var  bool */
    protected $postFlushDone = false;

    /** @var  ContainerInterface */
    protected $container;

    /**
     * AutoRouteListener constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return AutoRouteManager
     */
    protected function getAutoRouteManager()
    {
        // lazy load the auto_route_manager service to prevent a circular
        // reference to the entity manager.
        return $this->container->get('orm_routing.auto.auto_route_manager');
    }

    /**
     * @return MetadataFactory
     */
    protected function getMetadataFactory()
    {
        return $this->container->get('orm_routing.auto.metadata.factory');
    }

    /**
     * @return ContentRepository
     */
    protected function getContentRepository()
    {
        return $this->container->get('orm_routing.orm_content_repository');
    }

    /**
     * @return RouteProvider
     */
    protected function getRouteProvider()
    {
        return $this->container->get('orm_routing.route_provider');
    }

    /**
     * @param OnFlushEventArgs $args
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $em          = $args->getEntityManager();
        $uow         = $em->getUnitOfWork();
        $manager     = $this->getAutoRouteManager();
        $contentRepo = $this->getContentRepository();
        $provider    = $this->getRouteProvider();

        $scheduledInserts = $uow->getScheduledEntityInsertions();
        $scheduledUpdates = $uow->getScheduledEntityUpdates();
        $updates          = array_merge($scheduledInserts, $scheduledUpdates);

        $autoRoute = null;
        foreach ($updates as $entity) {
            if ($this->isAutoRouteEnabled($entity)) {
//                $locale = $uow->getCurrentLocale($document);

                $uriContextCollection = new UriContextCollection($entity);
                $manager->buildUriContextCollection($uriContextCollection);

                // refactor this.
                /** @var UriContext $uriContext */
                foreach ($uriContextCollection->getUriContexts() as $uriContext) {
                    $autoRoute = $uriContext->getAutoRoute();
                    $em->persist($autoRoute);
                }

                // reset locale to the original locale
//                if (null !== $locale) {
//                    $em->findTranslation(get_class($document), $uow->getDocumentId($document), $locale);
//                }
            }
        }

        $removes = $uow->getScheduledEntityDeletions();
        foreach ($removes as $entity) {
            if ($this->isAutoRouteEnabled($entity)) {
                $name  = $contentRepo->getContentId($entity);
                $route = $provider->getRouteByName($name);

                if ($route) {
                    $em->remove($route);
                }
            }
        }

        $manager->handleDefunctRoutes();

        $uow->computeChangeSets();
    }

    /**
     * @param ManagerEventArgs $args
     */
    public function endFlush(ManagerEventArgs $args)
    {
        $em  = $args->getObjectManager();
        $arm = $this->getAutoRouteManager();
        $arm->handleDefunctRoutes();

        if (!$this->postFlushDone) {
            $this->postFlushDone = true;
            $em->flush();
        }

        $this->postFlushDone = false;
    }

    /**
     * @param $entity
     *
     * @return bool
     */
    private function isAutoRouteEnabled($entity)
    {
        try {
            return (bool)$this->getMetadataFactory()->getMetadataForClass(get_class($entity));
        } catch (ClassNotMappedException $e) {
            return false;
        }
    }
}
