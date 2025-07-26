<?php
namespace Wa72\JsonRpcBundle\Tests\Fixtures;

use JMS\Serializer\Annotation\Type;

class Testparameter {

    /**
     * @Type("string")
     */
    private string $a;

    /**
     * @Type("string")
     */
    protected string $b;

    /**
     * @Type("string")
     */
    public string $c;


    public function __construct(string $a)
    {
        $this->a = $a;
    }


    public function getA(): string
    {
        return $this->a;
    }


    public function getB(): string
    {
        return $this->b;
    }


    public function setB(string $b)
    {
        $this->b = $b;
    }


    public function getC(): string
    {
        return $this->c;
    }


    public function setC(string $c)
    {
        $this->c = $c;
    }

}