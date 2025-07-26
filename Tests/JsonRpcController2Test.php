<?php

use Symfony\Component\HttpFoundation\Request;
use Wa72\JsonRpcBundle\Controller\JsonRpcController;
use Wa72\JsonRpcBundle\Tests\Fixtures\Testparameter;

require __DIR__ . '/Fixtures/app/Wa72JsonRpcBundleTestKernel2.php';

/**
 * Test for JsonRpcController with Symfony Serializer
 */
class JsonRpcController2Test extends \PHPUnit\Framework\TestCase {

    private \Wa72JsonRpcBundleTestKernel2 $kernel;

    private JsonRpcController $controller;

    public function setUp(): void
    {
        $this->kernel = new \Wa72JsonRpcBundleTestKernel2('test', true);
        $this->kernel->boot();
        $container = $this->kernel->getContainer();
        $this->controller = $container->get('wa72_jsonrpc.jsonrpccontroller');
    }

    public function testHello()
    {
        $requestdata = array(
            'jsonrpc' => '2.0',
            'id' => 'test',
            'method' => 'testhello',
            'params' => array('name' => 'Joe')
        );
        $response = $this->makeRequest($this->controller, $requestdata);
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
        $response = $this->makeRequest($this->controller, $requestdata);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayNotHasKey('result', $response);
        $this->assertEquals(-32602, $response['error']['code']);
    }

    public function testService()
    {
        $controller = $this->kernel->getContainer()->get('wa72_jsonrpc.jsonrpccontroller');
        $requestdata = array(
            'jsonrpc' => '2.0',
            'id' => 'testservice',
            'method' => 'wa72_jsonrpc.testservice:hello',
            'params' => array('name' => 'Max')
        );

        $response = $this->makeRequest($controller, $requestdata);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('testservice', $response['id']);
        $this->assertArrayNotHasKey('error', $response);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('Hello Max!', $response['result']);

        // Test: non-existing service should return "Method not found" error
        $requestdata = array(
            'jsonrpc' => '2.0',
            'id' => 'testservice',
            'method' => 'someservice:somemethod',
            'params' => array('name' => 'Max')
        );

        $response = $this->makeRequest($controller, $requestdata);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('testservice', $response['id']);
        $this->assertArrayNotHasKey('result', $response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
    }

    public function testParameters()
    {
        // params as associative array in right order
        $controller = $this->kernel->getContainer()->get('wa72_jsonrpc.jsonrpccontroller');
        $requestdata = array(
            'jsonrpc' => '2.0',
            'id' => 'parametertest',
            'method' => 'wa72_jsonrpc.testservice:parametertest',
            'params' => array('arg1' => 'abc', 'arg2' => 'def', 'arg_array' => array())
        );

        $response = $this->makeRequest($controller, $requestdata);
        $this->assertArrayNotHasKey('error', $response);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('abcdef', $response['result']);

        // params as simple array in right order
        $controller = $this->kernel->getContainer()->get('wa72_jsonrpc.jsonrpccontroller');
        $requestdata = array(
            'jsonrpc' => '2.0',
            'id' => 'parametertest',
            'method' => 'wa72_jsonrpc.testservice:parametertest',
            'params' => array('abc', 'def', array())
        );

        $response = $this->makeRequest($controller, $requestdata);
        $this->assertArrayNotHasKey('error', $response);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('abcdef', $response['result']);

        // params as associative array in mixed order
        $controller = $this->kernel->getContainer()->get('wa72_jsonrpc.jsonrpccontroller');
        $requestdata = array(
            'jsonrpc' => '2.0',
            'id' => 'parametertest',
            'method' => 'wa72_jsonrpc.testservice:parametertest',
            'params' => array('arg_array' => array(), 'arg2' => 'def', 'arg1' => 'abc')
        );

        $response = $this->makeRequest($controller, $requestdata);
        $this->assertArrayNotHasKey('error', $response);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('abcdef', $response['result']);

        // params with objects
        $controller = $this->kernel->getContainer()->get('wa72_jsonrpc.jsonrpccontroller');
        $arg3 = new Testparameter('abc');
        $arg3->setB('def');
        $arg3->setC('ghi');
        $requestdata = array(
            'jsonrpc' => '2.0',
            'id' => 'testParameterTypes',
            'method' => 'wa72_jsonrpc.testservice:testParameterTypes',
            'params' => array('arg1' => array(), 'arg2' => new \stdClass(), 'arg3' => $arg3)
        );

        $response = $this->makeRequest($controller, $requestdata);
        $this->assertArrayNotHasKey('error', $response);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('abcdefghi', $response['result']);
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
        $response = $this->makeRequest($this->controller, $requestdata);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayNotHasKey('result', $response);
        $this->assertEquals(-32601, $response['error']['code']);

        // add the method definition for "testhi"
        $this->controller->addMethod('testhi', 'wa72_jsonrpc.testservice', 'hi');

        // now the request should succeed
        $response = $this->makeRequest($this->controller, $requestdata);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayNotHasKey('error', $response);
        $this->assertEquals('Hi Tom!', $response['result']);
    }

    private function makeRequest($controller, $requestdata)
    {
        $container = $this->kernel->getContainer();
        if ($container->has('jms_serializer')) {
            $serializer = $container->get('jms_serializer');
        } elseif ($container->has('wa72_jsonrpc.serializer')) {
            $serializer = $container->get('wa72_jsonrpc.serializer');
        } else {
            $this->fail('No serializer service found in container.');
        }
        return json_decode($controller->execute(
            new Request([], [], [], [], [], [], $serializer->serialize($requestdata, 'json'))
        )->getContent(), true);
    }

    /**
     * Shuts the kernel down if it was used in the test.
     */
    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

}
