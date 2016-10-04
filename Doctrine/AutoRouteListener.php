<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\Doctrine;

use Doctrine\Common\Persistence\Event\ManagerEventArgs;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Symfony\Cmf\Component\RoutingAuto\AutoRouteManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Cmf\Component\RoutingAuto\UriContextCollection;
use Symfony\Cmf\Component\RoutingAuto\Mapping\Exception\ClassNotMappedException;

/**
 * Doctrine ORM listener for maintaining automatic routes.
 */
class AutoRouteListener
{
    protected $postFlushDone = false;

    protected $container;

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

    protected function getMetadataFactory()
    {
        return $this->container->get('orm_routing.auto.metadata.factory');
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em  = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        $arm = $this->getAutoRouteManager();

        $scheduledInserts = $uow->getScheduledEntityInsertions();
        $scheduledUpdates = $uow->getScheduledEntityUpdates();
        $updates          = array_merge($scheduledInserts, $scheduledUpdates);

        $autoRoute = null;
        foreach ($updates as $entity) {
            if ($this->isAutoRouteable($entity)) {
//                $locale = $uow->getCurrentLocale($document);

                $uriContextCollection = new UriContextCollection($entity);
                $arm->buildUriContextCollection($uriContextCollection);

                // refactor this.
                foreach ($uriContextCollection->getUriContexts() as $uriContext) {
                    $autoRoute = $uriContext->getAutoRoute();
                    $em->persist($autoRoute);
                    $uow->computeChangeSets();
                }

                // reset locale to the original locale
//                if (null !== $locale) {
//                    $em->findTranslation(get_class($document), $uow->getDocumentId($document), $locale);
//                }
            }
        }

        $removes = $uow->getScheduledEntityDeletions();
        foreach ($removes as $entity) {
            if ($this->isAutoRouteable($entity)) {
                $referrers = $em->getReferrers($entity);
                $referrers = $referrers->filter(function ($referrer) {
                    if ($referrer instanceof AutoRoute) {
                        return true;
                    }

                    return false;
                });
                foreach ($referrers as $autoRoute) {
                    $uow->scheduleForDelete($autoRoute);
                }
            }
        }
    }

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

    private function isAutoRouteable($entity)
    {
        try {
            return (bool)$this->getMetadataFactory()->getMetadataForClass(get_class($entity));
        } catch (ClassNotMappedException $e) {
            return false;
        }
    }
}
