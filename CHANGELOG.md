### v0.4.1 2014-11-19 ###
-   raised required symfony version to 2.3
-   Added mandatory LICENSE and index.rst files
-   only metadata, no code changes

### v0.4.0 2014-10-23 ###
-   All public methods of services tagged with 'wa72_jsonrpc.exposable' can be called via JSON-RPC. The method name
    to be used in the RPC call is "service:method", i.e. the name of the service and the method separated by colon.