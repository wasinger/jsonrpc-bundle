<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class Wa72JsonRpcBundleTestKernel extends Kernel
{
    public function registerBundles()
    {
        return array(
            new JMS\SerializerBundle\JMSSerializerBundle(),
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Wa72\JsonRpcBundle\Wa72JsonRpcBundle()
        );
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config/config.yml');
    }

    /**
     * @return string
     */
    public function getCacheDir()
    {
        return sys_get_temp_dir().'/Wa72JsonRpcBundle/cache';
    }

    /**
     * @return string
     */
    public function getLogDir()
    {
        return sys_get_temp_dir().'/Wa72JsonRpcBundle/logs';
    }
}
