<?php

namespace Wa72\JsonRpcBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wa72_json_rpc');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('functions')
                    ->useAttributeAsKey('function')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('service')->end()
                            ->scalarNode('method')->end()
                            ->arrayNode('serialization_context')
                                ->children()
                                    ->arrayNode('groups')
                                        ->beforeNormalization()
                                            ->ifTrue(function ($v) { return is_string($v); })
                                            ->then(function ($v) { return array($v); })
                                        ->end()
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->scalarNode('version')->end()
                                    ->booleanNode('max_depth_checks')->setDeprecated(
                                        'wa72/json-rpc-bundle', '0.8.0',
                                        'The "%node%" option is deprecated. Use "enable_max_depth" instead.'
                                    )->end()
                                    ->booleanNode('enable_max_depth')->end()
                                ->end()
                            ->end()
                            ->arrayNode('jms_serialization_context')
                                ->setDeprecated(
                                    'wa72/json-rpc-bundle', '0.8.0',
                                    'The "%node%" option is deprecated. Use "serialization_context" instead.'
                                )
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
