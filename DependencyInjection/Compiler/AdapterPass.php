<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 */
class AdapterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('orm_routing.auto.auto_route_manager')) {
            return;
        }

        $adapter      = $container->getParameter('orm_routing.auto.adapter_name');
        $adapterId    = null;
        $adapterNames = [];
        $ids          = $container->findTaggedServiceIds('orm_routing.auto.adapter');

        foreach ($ids as $id => $attributes) {
            if (!isset($attributes[0]['alias'])) {
                $message = sprintf('No "name" specified for auto route adapter "%s"', $id);
                throw new \InvalidArgumentException($message);
            }

            $alias          = $attributes[0]['alias'];
            $adapterNames[] = $alias;
            if ($adapter === $alias) {
                $adapterId = $id;
                break;
            }
        }

        if (null === $adapterId) {
            throw new \RuntimeException(sprintf(
                'Could not find configured adapter "%s", available adapters: "%s"',
                $adapter,
                implode('", "', $adapterNames)
            ));
        }

        $managerDef = $container->getDefinition('orm_routing.auto.auto_route_manager');
        $container->setAlias('orm_routing.auto.adapter', $adapterId);
    }
}
