<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Daniel Leech <daniel@dantleech.com>
 */
class ServicePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('orm_routing.auto.service_registry')) {
            return;
        }

        $builderUnitChainFactory = $container->getDefinition(
            'orm_routing.auto.service_registry'
        );

        $types = [
            'token_provider'        => 'registerTokenProvider',
            'defunct_route_handler' => 'registerDefunctRouteHandler',
            'conflict_resolver'     => 'registerConflictResolver',
        ];

        foreach ($types as $type => $registerMethod) {
            $ids = $container->findTaggedServiceIds('orm_routing.auto.'.$type);
            foreach ($ids as $id => $attributes) {
                if (!isset($attributes[0]['alias'])) {
                    throw new \InvalidArgumentException(sprintf(
                        'No "alias" specified for auto route "%s" service: "%s"',
                        str_replace('_', ' ', $type),
                        $id
                    ));
                }

                $builderUnitChainFactory->addMethodCall(
                    $registerMethod,
                    [$attributes[0]['alias'], new Reference($id)]
                );
            }
        }
    }
}
