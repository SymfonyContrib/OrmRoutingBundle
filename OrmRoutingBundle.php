<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Cmf\Component\Routing\DependencyInjection\Compiler\RegisterRoutersPass;
use Symfony\Cmf\Component\Routing\DependencyInjection\Compiler\RegisterRouteEnhancersPass;
use SymfonyContrib\Bundle\OrmRoutingBundle\DependencyInjection\Compiler\AdapterPass;
use SymfonyContrib\Bundle\OrmRoutingBundle\DependencyInjection\Compiler\ServicePass;
use SymfonyContrib\Bundle\OrmRoutingBundle\DependencyInjection\Compiler\SetRouterPass;

/**
 * Bundle class.
 */
class OrmRoutingBundle extends Bundle
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new RegisterRoutersPass('orm_routing.router'));
        $container->addCompilerPass(new RegisterRouteEnhancersPass('orm_routing.dynamic_router'));
        $container->addCompilerPass(new SetRouterPass());

        $container->addCompilerPass($this->buildBaseCompilerPass());

        $container->addCompilerPass(new ServicePass());
        $container->addCompilerPass(new AdapterPass());

    }

    /**
     * Builds the compiler pass for the symfony core routing component. The
     * compiler pass factory method uses the SymfonyFileLocator which does
     * magic with the namespace and thus does not work here.
     *
     * @return CompilerPassInterface
     */
    private function buildBaseCompilerPass()
    {
        $arguments = [[realpath(__DIR__.'/Resources/config/doctrine_base')], '.orm.yml'];
        $locator   = new Definition('Doctrine\Common\Persistence\Mapping\Driver\DefaultFileLocator', $arguments);
        $driver    = new Definition(YamlDriver::class, [$locator]);

        return new DoctrineOrmMappingsPass(
            $driver,
            ['Symfony\Component\Routing'],
            []
        );
    }
}
