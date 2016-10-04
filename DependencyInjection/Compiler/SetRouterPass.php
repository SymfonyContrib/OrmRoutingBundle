<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Changes the Router implementation.
 */
class SetRouterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // Only replace the Symfony router alias if configured.
        if ($container->hasParameter('orm_routing.replace_symfony_router') && true === $container->getParameter('orm_routing.replace_symfony_router')) {
            $container->setAlias('router', 'orm_routing.router');
        }
    }
}
