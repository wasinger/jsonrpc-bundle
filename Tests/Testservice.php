<?php
namespace Wa72\JsonRpcBundle\Tests;

use Wa72\JsonRpcBundle\Tests\Fixtures\Testparameter;

class Testservice {
    public function hello($name)
    {
        return 'Hello ' . $name . '!';
    }

    public function hi($name)
    {
        return 'Hi ' . $name . '!';
    }

    public function parametertest($arg1, $arg2, array $arg_array)
    {
        if (!is_array($arg_array)) throw new \InvalidArgumentException('arg_array must be an array!');
        return $arg1 . $arg2;
    }

    public function testParameterTypes(array $arg1, \stdClass $arg2, Testparameter $arg3)
    {
        if (!is_array($arg1)) throw new \InvalidArgumentException('arg1 must be an array!');
        if (!is_object($arg2)) throw new \InvalidArgumentException('arg2 must be an object!');
        if (!($arg3 instanceof Testparameter)) throw new \InvalidArgumentException('arg2 must be an object!');
        return $arg3->getA() . $arg3->getB() . $arg3->getC();
    }
}