<?php

namespace Wa72\JsonRpcBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Wa72\JsonRpcBundle\DependencyInjection\Compiler\JsonRpcExposablePass;

class Wa72JsonRpcBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new JsonRpcExposablePass());
    }
}
