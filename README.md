JsonRpcBundle
=============

JsonRpcBundle is a bundle for Symfony 2.1 and up that allows you to easily create web services using [JSON-RPC 2.0] (http://www.jsonrpc.org/specification).

The bundle contains a controller that is able to expose every service registered in your Symfony service container as a JSON-RPC web service.

Of course, it doesn't simply expose all your services' methods to the public, but only those you have explicitely mentioned in your configuration. And service methods cannot be called by it's original name but only by an alias name to be defined in your configuration.


Installation
------------

1. Add "wa72/jsonrpc-bundle" as requirement to your composer.json

2. Add "new Wa72\JsonRpcBundle\Wa72JsonRpcBundle()" in your AppKernel::registerBundles() function

3. Import the bundle's route in your routing.yml:

```yaml
# app/config/routing.yml
wa72_json_rpc:
    resource: "@Wa72JsonRpcBundle/Resources/config/routing.yml"
    prefix:   /jsonrpc
```

Your JSON-RPC web service will then be available in your project calling the /jsonrpc URL.

Configuration
-------------

Configuration is done under the "wa72_json_rpc" key of your configuration (usually defined in your app/config/config.yml).
To enable a Symfony2 service method to be called as a JSON-RPC web service, add it to the "functions" array of the configuration. 
The key of an entry of the "functions" array is the alias name for the method to be called over RPC and it needs two sub keys:
"service" specifies the name of the service and "method" the name of the method to call. Example:

```yaml
# app/config/config.yml
wa72_json_rpc:
    functions:
        myfunction1:
            service: "mybundle.servicename"
            method: "methodofservice"
        anotherfunction:
            service: "bundlename.foo"
            method: "bar"
```

In this example, "myfunction1" and "anotherfunction" are aliases for service methods that are used as JSON-RPC method names.
A method name "myfunction1" in the JSON-RPC call will then call the method "methodofservice" of service "mybundle.servicename".
