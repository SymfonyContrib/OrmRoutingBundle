<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\Doctrine;

use Symfony\Cmf\Component\Routing\ContentRepositoryInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Abstract content repository for ORM.
 *
 * This repository follows the pattern of FQN:id. That is, the full model class
 * name, then a colon, then the id. For example "Acme\Content:12".
 *
 * This will only work with single column ids.
 */
class ContentRepository implements ContentRepositoryInterface
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
     * @param ManagerRegistry $managerRegistry
     * @param string          $className
     */
    public function __construct(ManagerRegistry $managerRegistry, $className = null)
    {
        $this->managerRegistry = $managerRegistry;
        $this->className       = $className;
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
     * Determine target class and id for this content.
     *
     * @param mixed $identifier as produced by getContentId
     *
     * @return array with model first element, id second
     */
    public function getModelAndId($identifier)
    {
        return explode(':', $identifier, 2);
    }

    public function getModelId($identifier)
    {
        return explode(':', $identifier, 2)[1];
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id The ID contains both model name and id, separated by a colon.
     */
    public function findById($id)
    {
        list($model, $modelId) = $this->getModelAndId($id);

        return $this->getObjectManager()->getRepository($model)->find($modelId);
    }

    /**
     * {@inheritdoc}
     */
    public function getContentId($content)
    {
        if (!is_object($content)) {
            return;
        }

        try {
            $class = get_class($content);
            $meta  = $this->getObjectManager()->getClassMetadata($class);
            $ids   = $meta->getIdentifierValues($content);
            if (1 !== count($ids)) {
                throw new \Exception(sprintf('Class "%s" must use only one identifier', $class));
            }

            return implode(':', array($class, reset($ids)));
        } catch (\Exception $e) {
            return;
        }
    }
}
