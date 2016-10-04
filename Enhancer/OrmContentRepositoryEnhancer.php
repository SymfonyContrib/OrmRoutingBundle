<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\Enhancer;

use Symfony\Cmf\Component\Routing\ContentRepositoryInterface;
use Symfony\Cmf\Component\Routing\Enhancer\RouteEnhancerInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 */
class OrmContentRepositoryEnhancer implements RouteEnhancerInterface
{
    const ROUTE_OBJECT = '_route_object';
    const ROUTE_NAME   = 'name';

    /**
     * @var ContentRepositoryInterface
     */
    private $contentRepository;

    /**
     * @param ContentRepositoryInterface $contentRepository repository to search for the content
     */
    public function __construct(ContentRepositoryInterface $contentRepository) {
        $this->contentRepository = $contentRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function enhance(array $defaults, Request $request)
    {
        if (!isset($defaults['id']) && isset($defaults[self::ROUTE_OBJECT]) && $name = $defaults[self::ROUTE_OBJECT]->getName()) {
            $route = $defaults[self::ROUTE_OBJECT];

            $defaults['_content']    = $this->contentRepository->findById($name);
            $defaults['_content_id'] = $name;
            $defaults['id']          = $this->contentRepository->getModelId($name);
        }

        return $defaults;
    }
}
