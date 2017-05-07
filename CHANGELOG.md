# Hawkbit Changelog

## 2.3.2

### Fixed

 - Fix issue #34. Bind closure always to application instance.

### Altered

 - Upgrade league/container to 2.4
 - Upgrade zendframework/zend-diactoros to 1.4
 - Update container implementation to psr 11

## 2.3.1
 
### Fixed

 - Reopen and fix #29, Special Thanks to @BlackScorp for fixing and @designcise for identification

## 2.3.0

### Added

 - Add basic usage example
 
### Altered
 
 - Add official league/route 3.0 support
 
### Fixed

 - Fix #29, #30

## 2.2.0

### Added

 - Add console application
 - Add constructor injection for console commands
 
### Altered
 
 - Move application interface to correct position
 - Refactor configuration initiation into trait and reuse in application and console

## 2.1.3

### Fixed

 - Fix #25 Ajax request is always forcing JSON request and response
 - Fix #26 use league/route at dev master until official middleware release

### Removed

 - Remove HHVM integration due to inconsitencies

## 2.1.2

### Fixed

 - Fix #24 incorrect and buggy error handling

## 2.1.1

### Added

 - Add tests for controller constructor injection

## 2.1.0

### Fixed

 - Validate response contract after executing

### Altered

 - Whoops handlers has been refactored to `\Hawkbit\Application\Services\Whoops\HandlerService`
 - Error und shutdown handling has been refactored to `Hawkbit\Application\Init\InitHaltHooksTrait`
 
### Added

 - Add exception stack `Hawkbit\Application\Application\AbtractApplication::getExceptionStack` of all occured exceptions
 - Provide backwards / onwards compatibility for PHP 7 `\Throwables`


### Altered
 
 - Update [League Router](https://github.com/thephpleague/route/tree/507606b53d3935e7830aa7c48c43337bc2b1b2ba) and use router middleware implementation instead of application middleware. 
 - Update Zend Stratigility to 1.3.1
 - Advanced error capturing and error stack for debugging

### Removed

 - Remove dotted notation from from configuration
 - Remove vagrant machine

## 2.0.1

### Altered

 - Fix documentation
 - Fix typo

## 2.0

### Notice

__Migrate Turbine from PhpThinkTank to hawkbit.__

### Added

 - Add `\Hawkbit\Application\Configuration` (extending `\Zend\Config\Config`) as default configuration storage
 - Add PSR7 middleware implementation `\Hawkbit\Application\Application\MiddlewareRunner` for advanced control of application lifecycle
 
### Altered
 
 - Change Hawkbit\Application test namespace to Hawkbit\Application\Tests
 - Rewrite event behavior for advanced interception of requests, responses and errors
 - Implement dot chaining for nested configuration
 
## 1.1.7

### Altered

 - Fix wrong response determined by content type delegation
 
## 1.1.6

### Added

 - Add vagrant development environment
 - Add shutdown event
 - Add logic to force response emitting if headers already send

### Altered

 - Delegate request content type to response
 - Rename `Application::cleanUp` to `Application::collectGarbage`
 - Rename `Application::finishRequest` to `Application::shutdown`
 - Rename `Application::subscribe` to `Application::addListener`
 - Enhance error handling for different content types
 - Log application errors correctly, logging is silenced by default.
 
### Deprecated

 - `Application::cleanUp`
 - `Application::finishRequest`
 - `Application::subscribe`


## 1.1.5

### Added

 - Add `\Hawkbit\Application\Application\ConfiguratorInterface`

### Altered

 - `Application::getConfigurator` is now bound to `\Hawkbit\Application\Application\ConfiguratorInterface` contract

## 1.1.4

### Fixes

- [\#9](../../issues/9) If class exists and is not part of container, `League\Container\Container::has` returns now false.

## 1.1.3

### Altered

- Accept and process `\ArrayAccess` and `\Traversable` as configuration

## 1.1.2

### Altered

 - Replace applications [route collection methods](https://github.com/thephpleague/route/blob/master/src/RouteCollectionInterface.php) with `\League\Route\RouteCollectionMapTrait`
 - Application implements `\League\Route\RouteCollectionInterface`
 - add `\League\Route\RouteCollectionInterface::map()` 
 - add `\Hawkbit\Application\Application::group()` for creating route groups, see [documentation](http://route.thephpleague.com/route-groups/)

### Deprecated

 - `\Hawkbit\Application\Application::subscribe()`

## 1.1.1

### Altered

 - Upgrade `league/route` from dev-develop to stable 2.x (`~2.0`) release

## 1.1.0

### Added

 - Add `filp/whoops` as default error handler
 - Add `zendframework/zend-stratigility` integration

### Altered

 - add request and response accessors
 - refactor error handling and replace exception decorator with whoops
 - pass and receive all config 
 - remove possibilty to configure events, routes and services by callables
 - rename `Hawkbit\Application\Psr7\TerminableInterface` to `Hawkbit\Application\TerminableInterface`
 - rename debug config option to error
 - change configuration engine from `array` to instance of `\ArrayAccess`
 - Signature changes of `Hawkbit\Application\Application::handle`, `Hawkbit\Application\Application::run`, `Hawkbit\Application\Application::__construct`, `Hawkbit\Application\Application::handleErrors` 

### Removed

 - `Hawkbit\Application\Psr7\HttpKernelInterface` replaced by `Hawkbit\Application\ApplicationInterface`
 
## 1.0.0 (2016-03-04)

### Added

 - `Hawkbit\Application\Psr7\HttpKernelInterface` and `Hawkbit\Application\Psr7\TerminableInterface` port of symfony HttpKernelInterface for PSR-7 compatibility
 - Add `zend/diactoros` for PSR-7 http support
 - provide compatibility with adapter `Hawkbit\Application\Symfony\HttpKernelAdapter` for StackPHP and other Symfony HttpKernelInterface implementations
 - Add `filp/whoops` as default error handler
 - Add `zendframework/zend-stratigility` integration

### Altered

 - upgrade `league/container` to latest version 2 and add interopt compatibility
 - upgrade `league/route` to latest version 2 (currently under development)
 - replace symfony request and response with diactoros request and response
 - enable auto wiring of container configurable and enable by default
 - events, routes and services configurable by callables
 - add request and response accessors
 - refactor error handling and replace exception decorator with whoops
 - enhance configuration handling 
