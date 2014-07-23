<?php
namespace Wa72\JsonRpcBundle\Tests;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wa72\JsonRpcBundle\Controller\JsonRpcController;

require __DIR__ . '/Fixtures/app/Wa72JsonRpcBundleTestKernel.php';

class JsonRpcControllerTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var \Wa72JsonRpcBundleTestKernel
     */
    private $kernel;

    /**
     * @var \Wa72\JsonRpcBundle\Controller\JsonRpcController
     */
    private $controller;

    public function setUp()
    {
        $config = array(
            'functions' => array(
                'testhello' => array(
                    'service' => 'wa72_jsonrpc.testservice',
                    'method' => 'hello'
                )
            )
        );
        $this->kernel = new \Wa72JsonRpcBundleTestKernel('test', false);
        $this->kernel->boot();
        $this->controller = new JsonRpcController($this->kernel->getContainer(), $config);
    }

    public function testHello()
    {
        $requestdata = array(
            'jsonrpc' => '2.0',
            'id' => 'test',
            'method' => 'testhello',
            'params' => array('name' => 'Joe')
        );
        $response = $this->makeRequest($requestdata);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test', $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayNotHasKey('error', $response);
        $this->assertEquals('Hello Joe!', $response['result']);

        // Test: missing parameter
        $requestdata = array(
            'jsonrpc' => '2.0',
            'id' => 'test',
            'method' => 'testhello'
        );
        $response = $this->makeRequest($requestdata);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayNotHasKey('result', $response);
        $this->assertEquals(-32602, $response['error']['code']);
    }

    public function testAddMethod()
    {
        $requestdata = array(
            'jsonrpc' => '2.0',
            'id' => 'test',
            'method' => 'testhi',
            'params' => array('name' => 'Tom')
        );
        // this request will fail because there is no such method "testhi"
        $response = $this->makeRequest($requestdata);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayNotHasKey('result', $response);
        $this->assertEquals(-32601, $response['error']['code']);

        // add the method definition for "testhi"
        $this->controller->addMethod('testhi', 'wa72_jsonrpc.testservice', 'hi');

        // now the request should succeed
        $response = $this->makeRequest($requestdata);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayNotHasKey('error', $response);
        $this->assertEquals('Hi Tom!', $response['result']);
    }

    private function makeRequest($requestdata)
    {
        return json_decode($this->controller->execute(
            new Request(array(), array(), array(), array(), array(), array(), json_encode($requestdata))
        )->getContent(), true);
    }

    /**
     * Shuts the kernel down if it was used in the test.
     */
    protected function tearDown()
    {
        if (null !== $this->kernel) {
            $this->kernel->shutdown();
        }
    }

}
