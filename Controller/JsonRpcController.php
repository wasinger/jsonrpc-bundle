<?php

namespace Wa72\JsonRpcBundle\Controller;

use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAware;

/**
 * Controller for executing JSON-RPC 2.0 requests
 * see http://www.jsonrpc.org/specification
 *
 * Only functions of services registered in the DI container may be called.
 *
 * The constructor expects the DI container and a configuration array where
 * the mapping from the jsonrpc method names to service methods is defined:
 *
 * $config = array(
 *   'functions' => array(
 *      'myfunction1' => array(
 *          'service' => 'mybundle.servicename',
 *          'method' => 'methodofservice'
 *      ),
 *      'anotherfunction' => array(
 *          'service' => 'mybundle.foo',
 *          'method' => 'bar'
 *      )
 *   )
 * );
 *
 * A method name "myfunction1" in the RPC request will then call
 * $this->container->get('mybundle.servicename')->methodofservice()
 *
 * If you want to add a service completely so that all public methods of
 * this service may be called, use the addService($servicename) method.
 * Methods of the services added this way can be called remotely using
 * "servicename:method" as RPC method name.
 *
 * @license MIT
 * @author Christoph Singer
 *
 */
class JsonRpcController extends ContainerAware
{
    const PARSE_ERROR = -32700;
    const INVALID_REQUEST = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS = -32602;
    const INTERNAL_ERROR = -32603;

    /**
     * Functions that are allowed to be called
     *
     * @var array $functions
     */
    private $functions = array();

    /**
     * Array of names of fully exposed services (all methods of this services are allowed to be called)
     *
     * @var array $services
     */
    private $services = array();

    /**
     * @var \JMS\Serializer\SerializationContext
     */
    private $serializationContext;

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param array $config Associative array for configuration, expects at least a key "functions"
     * @throws \InvalidArgumentException
     */
    public function __construct($container, $config)
    {
        if (isset($config['functions'])) {
            if (!is_array($config['functions'])) throw new \InvalidArgumentException('Configuration parameter "functions" must be array');
            $this->functions = $config['functions'];
        }
        $this->setContainer($container);
    }

    /**
     * @param Request $httprequest
     * @return Response
     */
    public function execute(Request $httprequest)
    {
        $json = $httprequest->getContent();
        $request = json_decode($json, true);
        $requestId = (isset($request['id']) ? $request['id'] : null);

        if ($request === null) {
            return $this->getErrorResponse(self::PARSE_ERROR, null);
        } elseif (!(isset($request['jsonrpc']) && isset($request['method']) && $request['jsonrpc'] == '2.0')) {
            return $this->getErrorResponse(self::INVALID_REQUEST, $requestId);
        }

        if (in_array($request['method'], array_keys($this->functions))) {
            $servicename = $this->functions[$request['method']]['service'];
            $method = $this->functions[$request['method']]['method'];
        } else {
            if (count($this->services) && strpos($request['method'], ':') > 0) {
                list($servicename, $method) = explode(':', $request['method']);
                if (!in_array($servicename, $this->services)) {
                    return $this->getErrorResponse(self::METHOD_NOT_FOUND, $requestId);
                }
            } else {
                return $this->getErrorResponse(self::METHOD_NOT_FOUND, $requestId);
            }
        }
        try {
            $service = $this->container->get($servicename);
        } catch (ServiceNotFoundException $e) {
            return $this->getErrorResponse(self::METHOD_NOT_FOUND, $requestId);
        }
        $params = (isset($request['params']) ? $request['params'] : array());

        if (is_callable(array($service, $method))) {
            $r = new \ReflectionMethod($service, $method);

            if (is_array($params)) {
                if (!(count($params) >= $r->getNumberOfRequiredParameters()
                    && count($params) <= $r->getNumberOfParameters())
                ) {
                    return $this->getErrorResponse(self::INVALID_PARAMS, $requestId,
                        sprintf('Number of given parameters (%d) does not match the number of expected parameters (%d required, %d total)',
                            count($params), $r->getNumberOfRequiredParameters(), $r->getNumberOfParameters()));
                }
            } elseif (is_object($params)) {
                $rps = $r->getParameters();
                $newparams = array();
                foreach ($rps as $i => $rp) {
                    /* @var \ReflectionParameter $rp */
                    $name = $rp->name;
                    if (!isset($params->$name) && !$rp->isOptional()) {
                        return $this->getErrorResponse(self::INVALID_PARAMS, $requestId,
                            sprintf('Parameter %s is missing', $name));
                    }
                    if (isset($params->$name)) {
                        $newparams[$i] = $params->$name;
                    } else {
                        $newparams[$i] = null;
                    }
                }
                $params = $newparams;
            }

            try {
                $result = call_user_func_array(array($service, $method), $params);
            } catch (\Exception $e) {
                return $this->getErrorResponse(self::INTERNAL_ERROR, $requestId, $e->getMessage());
            }

            $response = array('jsonrpc' => '2.0');
            $response['result'] = $result;
            $response['id'] = $requestId;

            if ($this->container->has('jms_serializer')) {
                $serializationContext = $this->getSerializationContext($this->functions[$request['method']]);
                $response = $this->container->get('jms_serializer')->serialize($response, 'json', $serializationContext);
            } else {
                $response = json_encode($response);
            }

            return new Response($response, 200, array('Content-Type' => 'application/json'));
        } else {
            return $this->getErrorResponse(self::METHOD_NOT_FOUND, $requestId);
        }
    }

    /**
     * Add a new function that can be called by RPC
     *
     * @param string $alias The function name used in the RPC call
     * @param string $service The service name of the method to call
     * @param string $method The method of $service
     * @param bool $overwrite Whether to overwrite an existing function
     * @throws \InvalidArgumentException
     */
    public function addMethod($alias, $service, $method, $overwrite = false)
    {
        if (!isset($this->functions)) $this->functions = array();
        if (isset($this->functions[$alias]) && !$overwrite) {
            throw new \InvalidArgumentException('JsonRpcController: The function "' . $alias . '" already exists.');
        }
        $this->functions[$alias] = array(
            'service' => $service,
            'method' => $method
        );
    }

    /**
     * Add a new service that is fully exposed by json-rpc
     *
     * @param string $service The id of a service
     */
    public function addService($service)
    {
        $this->services[] = $service;
    }

    /**
     * Remove a method definition
     *
     * @param string $alias
     */
    public function removeMethod($alias)
    {
        if (isset($this->functions[$alias])) {
            unset($this->functions[$alias]);
        }
    }

    protected function getError($code)
    {
        $message = '';
        switch ($code) {
            case self::PARSE_ERROR:
                $message = 'Parse error';
                break;
            case self::INVALID_REQUEST:
                $message = 'Invalid request';
                break;
            case self::METHOD_NOT_FOUND:
                $message = 'Method not found';
                break;
            case self::INVALID_PARAMS:
                $message = 'Invalid params';
                break;
            case self::INTERNAL_ERROR:
                $message = 'Internal error';
                break;
        }

        return array('code' => $code, 'message' => $message);
    }

    protected function getErrorResponse($code, $id, $data = null)
    {
        $response = array('jsonrpc' => '2.0');
        $response['error'] = $this->getError($code);

        if ($data != null) {
            $response['error']['data'] = $data;
        }

        $response['id'] = $id;

        return new Response(json_encode($response), 200, array('Content-Type' => 'application/json'));
    }

    /**
     * Set SerializationContext for using with jms_serializer
     *
     * @param \JMS\Serializer\SerializationContext $context
     */
    public function setSerializationContext($context)
    {
        $this->serializationContext = $context;
    }

    /**
     * Get SerializationContext or creates one if jms_serialization_context option is set
     *
     * @param array $functionConfig
     * @return \JMS\Serializer\SerializationContext
     */
    protected function getSerializationContext(array $functionConfig)
    {
        if (isset($functionConfig['jms_serialization_context'])) {
            $serializationContext = \JMS\Serializer\SerializationContext::create();

            if (isset($functionConfig['jms_serialization_context']['groups'])) {
                $serializationContext->setGroups($functionConfig['jms_serialization_context']['groups']);
            }

            if (isset($functionConfig['jms_serialization_context']['version'])) {
                $serializationContext->setVersion($functionConfig['jms_serialization_context']['version']);
            }

            if (isset($functionConfig['jms_serialization_context']['max_depth_checks'])) {
                $serializationContext->enableMaxDepthChecks($functionConfig['jms_serialization_context']['max_depth_checks']);
            }
        } else {
            $serializationContext = $this->serializationContext;
        }

        return $serializationContext;
    }
}
