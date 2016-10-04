<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Loader;

/**
 *
 */
class OrmRoutingExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config/service'));

        if ($this->isConfigEnabled($container, $config['dynamic'])) {
            $this->setupDynamicRouter($config['dynamic'], $container, $loader);
        }

        $this->setupChainRouter($config, $container, $loader);

        $loader->load('validator.yml');

        $loader->load('auto.yml');
        $loader->load('token_provider.yml');
        $loader->load('defunct_route_handler.yml');
        $loader->load('conflict_resolver.yml');
        $loader->load('doctrine.yml');

        $this->mapAutoRouteConfig($container, $config['auto']);
    }

    private function setupChainRouter(array $config, ContainerBuilder $container, LoaderInterface $loader)
    {
        $loader->load('chain_router.yml');

        $container->setParameter('orm_routing.replace_symfony_router', $config['chain']['replace_symfony_router']);

        // add the routers defined in the configuration mapping
        $router = $container->getDefinition('orm_routing.router');
        foreach ($config['chain']['routers_by_id'] as $id => $priority) {
            $router->addMethodCall('add', [new Reference($id), trim($priority)]);
        }
    }

    /**
     * Set up the DynamicRouter - only to be called if enabled is set to true.
     *
     * @param array            $config    the compiled configuration for the dynamic router
     * @param ContainerBuilder $container the container builder
     * @param LoaderInterface  $loader    the configuration loader
     */
    private function setupDynamicRouter(array $config, ContainerBuilder $container, LoaderInterface $loader)
    {
        $loader->load('dynamic_router.yml');

        $defaultController = $config['default_controller'];
        if (null === $defaultController) {
            $defaultController = $config['generic_controller'];
        }
        $container->setParameter('orm_routing.default_controller', $defaultController);

        $locales = $config['locales'];
        if (0 === count($locales) && $config['auto_locale_pattern']) {
            throw new InvalidConfigurationException('It makes no sense to activate auto_locale_pattern when no locales are configured.');
        }

        $this->configureParameters($container, $config, [
            'generic_controller'     => 'generic_controller',
            'controllers_by_type'    => 'controllers_by_type',
            'controllers_by_class'   => 'controllers_by_class',
            'templates_by_class'     => 'templates_by_class',
            'uri_filter_regexp'      => 'uri_filter_regexp',
            'route_collection_limit' => 'route_collection_limit',
            'limit_candidates'       => 'dynamic.limit_candidates',
            'locales'                => 'dynamic.locales',
            'auto_locale_pattern'    => 'dynamic.auto_locale_pattern',
        ]);

        $this->loadProvider($config['orm'], $loader, $container, $config['match_implicit_locale']);

        if (isset($config['route_provider_service_id'])) {
            $container->setAlias('orm_routing.route_provider', $config['route_provider_service_id']);
        }

        if (isset($config['content_repository_service_id'])) {
            $container->setAlias('orm_routing.content_repository', $config['content_repository_service_id']);
        }

        // content repository is optional
        $generator = $container->getDefinition('orm_routing.generator');
        $generator->addMethodCall('setContentRepository', [new Reference('orm_routing.content_repository')]);
        $container->getDefinition('orm_routing.enhancer.content_repository')
                  ->addTag('dynamic_router_route_enhancer', ['priority' => 100]);

        $dynamic = $container->getDefinition('orm_routing.dynamic_router');

        // if any mappings are defined, set the respective route enhancer
        if (count($config['controllers_by_type']) > 0) {
            $container->getDefinition('orm_routing.enhancer.controllers_by_type')
                      ->addTag('dynamic_router_route_enhancer', ['priority' => 60]);
        }

        if (count($config['controllers_by_class']) > 0) {
            $container->getDefinition('orm_routing.enhancer.controllers_by_class')
                      ->addTag('dynamic_router_route_enhancer', ['priority' => 50]);
        }

        if (count($config['templates_by_class']) > 0) {
            $container->getDefinition('orm_routing.enhancer.templates_by_class')
                      ->addTag('dynamic_router_route_enhancer', ['priority' => 40]);

            /*
             * The CoreBundle prepends the controller from ContentBundle if the
             * ContentBundle is present in the project.
             * If you are sure you do not need a generic controller, set the field
             * to false to disable this check explicitly. But you would need
             * something else like the default_controller to set the controller,
             * as no controller will be set here.
             */
            if (null === $config['generic_controller']) {
                throw new InvalidConfigurationException('If you want to configure templates_by_class, you need to configure the generic_controller option.');
            }

            // if the content class defines the template, we also need to make sure we use the generic controller for those routes
            $controllerForTemplates = [];
            foreach ($config['templates_by_class'] as $key => $value) {
                $controllerForTemplates[$key] = $config['generic_controller'];
            }

            $definition = $container->getDefinition('orm_routing.enhancer.controller_for_templates_by_class');
            $definition->replaceArgument(2, $controllerForTemplates);

            $container->getDefinition('orm_routing.enhancer.controller_for_templates_by_class')
                      ->addTag('dynamic_router_route_enhancer', ['priority' => 30]);
        }

        if (null !== $config['generic_controller'] && $defaultController !== $config['generic_controller']) {
            $container->getDefinition('orm_routing.enhancer.explicit_template')
                      ->addTag('dynamic_router_route_enhancer', ['priority' => 10]);
        }

        if (null !== $defaultController) {
            $container->getDefinition('orm_routing.enhancer.default_controller')
                      ->addTag('dynamic_router_route_enhancer', ['priority' => -100]);
        }

        if (count($config['route_filters_by_id']) > 0) {
            $matcher = $container->getDefinition('orm_routing.nested_matcher');

            foreach ($config['route_filters_by_id'] as $id => $priority) {
                $matcher->addMethodCall('addRouteFilter', [new Reference($id), $priority]);
            }
        }

        $dynamic->replaceArgument(2, new Reference($config['url_generator']));
    }

    private function loadProvider($config, LoaderInterface $loader, ContainerBuilder $container, $matchImplicitLocale)
    {
        $loader->load('provider.yml');

        $container->setParameter('orm_routing.dynamic.orm.manager_name', $config['manager_name']);

        if (!$matchImplicitLocale) {
            // remove the locales argument from the candidates
            $container->getDefinition('orm_routing.orm_candidates')->setArguments([]);
        }
    }

    /**
     * @param ContainerBuilder $container          The container builder
     * @param array            $config             The config array
     * @param array            $settingToParameter An array with setting to parameter mappings (key = setting, value = parameter name without alias prefix)
     */
    private function configureParameters(ContainerBuilder $container, array $config, array $settingToParameter)
    {
        foreach ($settingToParameter as $setting => $parameter) {
            $container->setParameter('orm_routing.'.$parameter, $config[$setting]);
        }
    }

    private function mapAutoRouteConfig(ContainerBuilder $container, array $config)
    {
        // auto mapping
        $resources = [];
        if ($config['auto_mapping']) {
            $bundles   = $container->getParameter('kernel.bundles');
            $resources = $this->findMappingFiles($bundles);
        }
        // add configured mapping file resources
        if (isset($config['mapping']['resources'])) {
            foreach ($config['mapping']['resources'] as $resource) {
                $resources[] = $resource;
            }
        }
        $container->setParameter('orm_routing.auto.metadata.loader.resources', $resources);
        $adapterName = isset($config['adapter']) ? $config['adapter'] : 'doctrine_orm';
        if (empty($adapterName)) {
            throw new InvalidConfigurationException(sprintf(
                'No adapter has been configured, you either need to enable a persistence layer or '.
                'explicitly specify an adapter using the "adapter" configuration key.'
            ));
        }
        $container->setParameter('orm_routing.auto.adapter_name', $adapterName);
    }

    protected function findMappingFiles($bundles)
    {
        $resources = [];
        foreach ($bundles as $bundle) {
            $refl       = new \ReflectionClass($bundle);
            $bundlePath = dirname($refl->getFileName());
            $path = $bundlePath.'/Resources/config/orm_routing.auto.yml';
            if (file_exists($path)) {
                $resources[] = ['path' => $path, 'type' => null];
            }
        }

        return $resources;
    }
}
