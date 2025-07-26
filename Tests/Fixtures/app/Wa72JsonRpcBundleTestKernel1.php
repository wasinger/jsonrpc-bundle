<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * Kernel for testing with JMS Serializer Bundle
 */
class Wa72JsonRpcBundleTestKernel1 extends Kernel
{
    public function registerBundles(): iterable
    {
        return array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Wa72\JsonRpcBundle\Wa72JsonRpcBundle(),
            new JMS\SerializerBundle\JMSSerializerBundle(),
        );
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config/config1.yml');
    }

    /**
     * @return string
     */
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/Wa72JsonRpcBundle/cache';
    }

    /**
     * @return string
     */
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/Wa72JsonRpcBundle/logs';
    }
}
