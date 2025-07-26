<?php
namespace Wa72\JsonRpcBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 *
 */
class JsonRpcExposablePass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('wa72_jsonrpc.jsonrpccontroller');
        $services = $container->findTaggedServiceIds('wa72_jsonrpc.exposable');
        foreach ($services as $service => $attributes) {
            $definition->addMethodCall('addService', [$service]);
        }

        // Add an alias for the serializer service to make it public
        try {
            $serializer = $container->getDefinition('serializer');
        } catch (\Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException $e) {
            $serializer = null;
        }
        if ($serializer) {
            $a = new Alias('serializer', true);
            $container->setAlias('wa72_jsonrpc.serializer', $a);
        }
    }
}