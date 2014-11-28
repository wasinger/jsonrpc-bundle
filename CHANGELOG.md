### v0.4.4 2014-11-28 ###
-   The test service is no longer registered automatically. If you want to use it,
    you must define it in the config.yml of your application, see updated [Resources/doc/index.rst](Resources/doc/index.rst).

### v0.4.2 2014-11-28 ###
-   fixed bug introduced in v0.2.0: named parameters given in associative arrays were always passed to the method in
    the order they were given, not by their names. Thanks to @teet.

### v0.4.1 2014-11-19 ###
-   raised required symfony version to 2.3
-   Added mandatory LICENSE and index.rst files
-   only metadata, no code changes

### v0.4.0 2014-10-23 ###
-   All public methods of services tagged with 'wa72_jsonrpc.exposable' can be called via JSON-RPC. The method name
    to be used in the RPC call is "service:method", i.e. the name of the service and the method separated by colon.