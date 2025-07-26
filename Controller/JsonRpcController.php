<?php

namespace Wa72\JsonRpcBundle\Controller;

use JMS\Serializer\SerializationContext as JMS_SerializationContext;
use JMS\Serializer\SerializerInterface as JMS_SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
class JsonRpcController
{
    private ContainerInterface $container;
    
    const PARSE_ERROR = -32700;
    const INVALID_REQUEST = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS = -32602;
    const INTERNAL_ERROR = -32603;

    /**
     * Functions that are allowed to be called
     */
    private array $functions = [];

    /**
     * Array of names of fully exposed services (all methods of this services are allowed to be called)
     */
    private array $services = [];


    private JMS_SerializerInterface|SerializerInterface $serializer;


    private JMS_SerializationContext|array $serializationContext = [];

    /**
     * @param ContainerInterface $container
     * @param array $config Associative array for configuration, expects at least a key "functions"
     * @throws \InvalidArgumentException
     */
    public function __construct(ContainerInterface $container, array $config)
    {
        if (isset($config['functions'])) {
            if (!is_array($config['functions'])) throw new \InvalidArgumentException('Configuration parameter "functions" must be array');
            $this->functions = $config['functions'];
        }
        $this->container = $container;
        if ($this->container->has('jms_serializer')) {
            $this->serializer = $this->container->get('jms_serializer');
        } elseif ($this->container->has('wa72_jsonrpc.serializer')) {
            $this->serializer = $this->container->get('wa72_jsonrpc.serializer');
        } else {
            throw new \InvalidArgumentException('No serializer service found in container. Please install jms/serializer-bundle or symfony/serializer.');
        }
    }

    /**
     * @param Request $httprequest
     * @return Response
     */
    public function execute(Request $httprequest): Response
    {
        $json = $httprequest->getContent();
        $request = json_decode($json, true);
        $requestId = ($request['id'] ?? null);

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
        $params = ($request['params'] ?? []);

        if (is_callable([$service, $method])) {
            $r = new \ReflectionMethod($service, $method);
            $rps = $r->getParameters();

            if (is_array($params)) {
                if (!(count($params) >= $r->getNumberOfRequiredParameters()
                    && count($params) <= $r->getNumberOfParameters())
                ) {
                    return $this->getErrorResponse(self::INVALID_PARAMS, $requestId,
                        sprintf('Number of given parameters (%d) does not match the number of expected parameters (%d required, %d total)',
                            count($params), $r->getNumberOfRequiredParameters(), $r->getNumberOfParameters()));
                }

            }
            if ($this->isAssoc($params)) {
                $newparams = [];
                foreach ($rps as $i => $rp) {
                    $name = $rp->name;
                    if (!isset($params[$rp->name]) && !$rp->isOptional()) {
                        return $this->getErrorResponse(self::INVALID_PARAMS, $requestId,
                            sprintf('Parameter %s is missing', $name));
                    }
                    if (isset($params[$rp->name])) {
                        $newparams[] = $params[$rp->name];
                    } else {
                        $newparams[] = null;
                    }
                }
                $params = $newparams;
            }

            // correctly deserialize object parameters
            foreach ($params as $index => $param) {
                // if the json_decode'd param value is an array but an object is expected as method parameter,
                // re-encode the array value to json and correctly decode it using the serializer.
                //
                // TODO: since PHP 8, the method type hints can include union types, so we need to handle those as well.
                if (is_array($param) && !$rps[$index]->isArray() && $rps[$index]->getClass() != null) {
                    $class = $rps[$index]->getClass()->getName();
                    $params[$index] = $this->deserialize(json_encode($param), $class);
                }
            }

            try {
                $result = call_user_func_array([$service, $method], $params);
            } catch (\Exception $e) {
                return $this->getErrorResponse(self::INTERNAL_ERROR, $requestId, $this->convertExceptionToErrorData($e));
            }
            $response = ['jsonrpc' => '2.0'];
            $response['result'] = $result;
            $response['id'] = $requestId;
            $response = $this->serialize($response, $request['method']);
            return JsonResponse::fromJsonString($response);
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
        if (isset($this->functions[$alias]) && !$overwrite) {
            throw new \InvalidArgumentException('JsonRpcController: The function "' . $alias . '" already exists.');
        }
        $this->functions[$alias] = [
            'service' => $service,
            'method' => $method
        ];
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

    protected function convertExceptionToErrorData(\Exception $e): string
    {
        return $e->getMessage();
    }

    protected function getError($code): array
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

    protected function getErrorResponse($code, $id, $data = null): JsonResponse
    {
        $response = array('jsonrpc' => '2.0');
        $response['error'] = $this->getError($code);

        if ($data != null) {
            $response['error']['data'] = $data;
        }

        $response['id'] = $id;

        return new JsonResponse($response);
    }

    /**
     * Serialize the return value of a method call to JSON.
     */
    protected function serialize(mixed $data, string $rpc_method): string
    {
        return $this->serializer->serialize($data, 'json', $this->getSerializationContext($rpc_method));
    }

    /**
     * Deserialize parameter values coming with the RPC request to the expected type.
     */
    protected function deserialize(string $json, string $class): mixed
    {
        return $this->serializer->deserialize($json, $class, 'json');
    }

    /**
     * Set SerializationContext
     *
     */
    public function setSerializationContext(array|JMS_SerializationContext $context): void
    {
        if ($this->serializer instanceof JMS_SerializerInterface && !($context instanceof JMS_SerializationContext)) {
            throw new \InvalidArgumentException('If jms_serializer is used, the SerializationContext must be an instance of JMS_SerializationContext');
        }
        if ($this->serializer instanceof SerializerInterface && !is_array($context)) {
            throw new \InvalidArgumentException('If symfony/serializer is used, the SerializationContext must be an array');
        }
        $this->serializationContext = $context;
    }

    /**
     * Get SerializationContext for a given rpc_method.
     *
     * The context will be created from the configuration array for this method if available,
     * otherwise the default serialization context (set by $this->setSerializationContext()) will be used.
     */
    protected function getSerializationContext(string $rpc_method): JMS_SerializationContext|array
    {
        $functionConfig = $this->functions[$rpc_method] ?? [];
        if ($this->serializer instanceof JMS_SerializerInterface) {
            // legacy support for jms_serialization_context
            if (isset($functionConfig['jms_serialization_context'])) {
                $functionConfig['serialization_context'] = $functionConfig['jms_serialization_context'];
            }
            if (isset($functionConfig['serialization_context'])) {
                $context = JMS_SerializationContext::create();
                if (isset($functionConfig['serialization_context']['groups'])) {
                    $context->setGroups($functionConfig['jms_serialization_context']['groups']);
                }
                if (isset($functionConfig['serialization_context']['version'])) {
                    $context->setVersion($functionConfig['jms_serialization_context']['version']);
                }
                if (!empty($functionConfig['serialization_context']['max_depth_checks']) || !empty($functionConfig['serialization_context']['enable_max_depth'])) {
                    $context->enableMaxDepthChecks();
                }
            } else {
                if ($this->serializationContext instanceof JMS_SerializationContext) {
                    $context = $this->serializationContext;
                } else {
                    $context = JMS_SerializationContext::create();
                }
            }
        } elseif ($this->serializer instanceof SerializerInterface) {
            $context = $functionConfig['serialization_context'] ?? $this->serializationContext;
            if (!empty($context['max_depth_checks'])) { // legacy support for max_depth_checks
                $context['enable_max_depth'] = true;
            }
        } else {
            throw new \LogicException('No serializer service found in container. Please install jms/serializer-bundle or symfony/serializer.');
        }

        return $context;
    }
    
    /**
     * Finds whether a variable is an associative array
     *
     * @param $var
     * @return bool
     */
    protected function isAssoc($var)
    {
        return array_keys($var) !== range(0, count($var) - 1);
    }
}
