<?php
namespace Wa72\JsonRpcBundle\Tests;

class Testservice {
    public function hello($name) {
        return 'Hello ' . $name . '!';
    }
}