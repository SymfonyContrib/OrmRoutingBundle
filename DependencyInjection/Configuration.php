<?php

namespace SymfonyContrib\Bundle\OrmRoutingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * This class contains the configuration information for the bundle.
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Returns the config tree builder.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $root        = $treeBuilder->root('orm_routing');

        $this->addChainSection($root);
        $this->addDynamicSection($root);
        $this->addAutoSection($root);

        return $treeBuilder;
    }

    private function addChainSection(ArrayNodeDefinition $root)
    {
        $root->children()
            ->arrayNode('chain')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('routers_by_id')
                        ->defaultValue(array('router.default' => 100))
                        ->useAttributeAsKey('id')
                        ->prototype('scalar')->end()
                    ->end()
                    ->booleanNode('replace_symfony_router')->defaultTrue()->end()
                ->end()
            ->end()
        ->end();
    }

    private function addDynamicSection(ArrayNodeDefinition $root)
    {
        $root->children()
            ->arrayNode('dynamic')
                ->addDefaultsIfNotSet()
                ->canBeEnabled()
                ->children()
                    ->scalarNode('route_collection_limit')
                        ->defaultValue(0)
                    ->end()
                    ->scalarNode('generic_controller')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('default_controller')
                        ->defaultNull()
                    ->end()
                    ->arrayNode('controllers_by_type')
                        ->useAttributeAsKey('type')
                        ->prototype('scalar')->end()
                    ->end()
                    ->arrayNode('controllers_by_class')
                        ->useAttributeAsKey('class')
                        ->prototype('scalar')->end()
                    ->end()
                    ->arrayNode('templates_by_class')
                        ->useAttributeAsKey('class')
                        ->prototype('scalar')->end()
                    ->end()
                    ->arrayNode('orm')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('manager_name')->defaultNull()->end()
                        ->end()
                    ->end()
                    ->scalarNode('uri_filter_regexp')->defaultValue('')->end()
                    ->scalarNode('route_provider_service_id')->end()
                    ->arrayNode('route_filters_by_id')
                        ->canBeUnset()
                        ->defaultValue(array())
                        ->useAttributeAsKey('id')
                        ->prototype('scalar')->end()
                    ->end()
                    ->scalarNode('content_repository_service_id')->end()
                    ->arrayNode('locales')
                        ->prototype('scalar')->end()
                    ->end()
                    ->integerNode('limit_candidates')->defaultValue(20)->end()
                    ->booleanNode('match_implicit_locale')->defaultValue(true)->end()
                    ->booleanNode('auto_locale_pattern')->defaultValue(false)->end()
                    ->scalarNode('url_generator')
                        ->defaultValue('orm_routing.generator')
                        ->info('URL generator service ID')
                    ->end()
                ->end()
            ->end()
        ->end();
    }

    private function addAutoSection(ArrayNodeDefinition $root)
    {
        $root->children()
            ->arrayNode('auto')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('adapter')
                        ->info('Use a specific adapter, overrides any implicit selection')
                    ->end()
                    ->booleanNode('auto_mapping')
                        ->defaultTrue()
                    ->end()
                    ->arrayNode('mapping')
                        ->children()
                            ->arrayNode('resources')
                                ->prototype('array')
                                    ->beforeNormalization()
                                        ->ifString()
                                        ->then(function ($v) {
                                            return ['path' => $v];
                                        })
                                    ->end()
                                    ->children()
                                        ->scalarNode('path')
                                            ->isRequired()
                                        ->end()
                                        ->scalarNode('type')
                                            ->defaultNull()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();
    }
}
