# Hawkbit Changelog

## 2.0

### Notice

__Migrate Hawkbit from PhpThinkTank to component collection.__

### Added

 - Add `\Hawkbit\Configuration` (extending `\Zend\Config\Config`) as default configuration storage
 - Add PSR7 middleware implementation `\Hawkbit\Application\MiddlewareRunner` for advanced control of application lifecycle
 
### Altered
 
 - Change Hawkbit test namespace to Hawkbit\Tests
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

 - Add `\Hawkbit\Application\ConfiguratorInterface`

### Altered

 - `Application::getConfigurator` is now bound to `\Hawkbit\Application\ConfiguratorInterface` contract

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
 - add `\Hawkbit\Application::group()` for creating route groups, see [documentation](http://route.thephpleague.com/route-groups/)

### Deprecated

 - `\Hawkbit\Application::subscribe()`

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
 - rename `Hawkbit\Psr7\TerminableInterface` to `Hawkbit\TerminableInterface`
 - rename debug config option to error
 - change configuration engine from `array` to instance of `\ArrayAccess`
 - Signature changes of `Hawkbit\Application::handle`, `Hawkbit\Application::run`, `Hawkbit\Application::__construct`, `Hawkbit\Application::handleErrors` 

### Removed

 - `Hawkbit\Psr7\HttpKernelInterface` replaced by `Hawkbit\ApplicationInterface`
 
## 1.0.0 (2016-03-04)

### Added

 - `Hawkbit\Psr7\HttpKernelInterface` and `Hawkbit\Psr7\TerminableInterface` port of symfony HttpKernelInterface for PSR-7 compatibility
 - Add `zend/diactoros` for PSR-7 http support
 - provide compatibility with adapter `Hawkbit\Symfony\HttpKernelAdapter` for StackPHP and other Symfony HttpKernelInterface implementations
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