<?php

namespace Wa72\JsonRpcBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('wa72_json_rpc');

        $rootNode
            ->children()
                ->arrayNode('functions')
                    ->useAttributeAsKey('function')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('service')->end()
                            ->scalarNode('method')->end()
                            ->arrayNode('jms_serialization_context')
                                ->children()
                                    ->arrayNode('groups')
                                        ->beforeNormalization()
                                            ->ifTrue(function ($v) { return is_string($v); })
                                            ->then(function ($v) { return array($v); })
                                        ->end()
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->scalarNode('version')->end()
                                    ->booleanNode('max_depth_checks')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
