<?php
namespace Wa72\JsonRpcBundle\Tests;

class Testservice {
    public function hello($name)
    {
        return 'Hello ' . $name . '!';
    }

    public function hi($name)
    {
        return 'Hi ' . $name . '!';
    }

    public function parametertest($arg1, $arg2, $arg_array)
    {
        if (!is_array($arg_array)) throw new \InvalidArgumentException('arg_array must be an array!');
        return $arg1 . $arg2;
    }
}