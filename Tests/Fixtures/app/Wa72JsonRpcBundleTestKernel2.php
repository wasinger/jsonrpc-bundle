<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * Kernel for testing with Symfony Serializer
 * (without JMS Serializer Bundle)
 */
class Wa72JsonRpcBundleTestKernel2 extends Kernel
{
    public function registerBundles(): iterable
    {
        return array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Wa72\JsonRpcBundle\Wa72JsonRpcBundle()
        );
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config/config.yml');
    }


    /**
     * @return string
     */
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/Wa72JsonRpcBundle/cache';
    }

    /**
     * @return string
     */
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/Wa72JsonRpcBundle/logs';
    }
}
