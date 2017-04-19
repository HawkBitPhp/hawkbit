# Hawkbit\Application

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Total Downloads][ico-downloads]][link-downloads]
[![Coverage Status][ico-coveralls]][link-coveralls]

Hawkbit\Application micro framework is a high customizable, event driven and compatible with 
[PSR-7](https://github.com/php-fig/http-message), 
[StackPHP](http://stackphp.com/) and 
[Zend Stratigility](https://github.com/zendframework/zend-stratigility).

Hawkbit\Application uses latest versions of [League\Route](https://github.com/thephpleague/route) for routing, 
[League\Container](https://github.com/thephpleague/container) for dependency injection, 
[League\Event](https://github.com/thephpleague/event) for event dispatching,
[Zend Config](https://docs.zendframework.com/zend-config/) for configuration.

Hawkbit\Application is an advanced derivate of [Proton](https://github.com/alexbilbie/Proton) and part of Hawkbit\Application Component collection by Marco Bunge. Hawkbit\Application 1.x is also known as Blast Hawkbit\Application.

### Quick start

Please see [public/](public/) for example usage and read documentation.

### Integrations

Hawkbit\Application delivers also optional packages:

 - Database: [`hawkbit/database`](https://github.com/HawkBitPhp/hawkbit-database)
 - Doctrine ORM Wrapper: [`hawkbit/doctrine`](https://github.com/HawkBitPhp/hawkbit-doctrine)
 - Plates View Engine: [`hawkbit/presentation`](https://github.com/HawkBitPhp/hawkbit-presentation)

## Install

### Using Composer

Hawkbit\Application is available on [Packagist](https://packagist.org/packages/hawkbit/hawkbit) and can be installed using [Composer](https://getcomposer.org/). This can be done by running the following command or by updating your `composer.json` file.

```bash
composer require hawkbit/hawkbit
```

composer.json

```javascript
{
    "require": {
        "hawkbit/hawkbit": "~2.0"
    }
}
```

Be sure to also include your Composer autoload file in your project:

```php
<?php

require __DIR__ . '/vendor/autoload.php';
```

### Downloading .zip file

This project is also available for download as a `.zip` file on GitHub. Visit the [releases page](https://github.com/hawkbit/hawkbit/releases), select the version you want, and click the "Source code (zip)" download button.

### Requirements

The following versions of PHP are supported by this version.

* PHP 5.5
* PHP 5.6
* PHP 7.0
* PHP 7.1
* HHVM

## Setup

Create a new app

```php
<?php

require __DIR__.'/../vendor/autoload.php';

$app = new \Hawkbit\Application();
```

Create a new app with configuration

```php
<?php

$config = [
    'key' => 'value'
];
$app = new \Hawkbit\Application($config);
```

Add routes

```php
<?php

/** @var Hawkbit\Application $app */
$app->get('/', function ($request, $response) {
    $response->getBody()->write('<h1>It works!</h1>');
    return $response;
});

$app->get('/hello/{name}', function ($request, $response, $args) {
    $response->getBody()->write(
        sprintf('<h1>Hello, %s!</h1>', $args['name'])
    );
    return $response;
});
```

Run application

```php
<?php

$app->run();
```

See also our example at `/public/index.php`.

## Configuration

Add additional configuration to application
 
Hawkbit\Application Configuration is managed by [zend-config](https://docs.zendframework.com/zend-config/).

```php
<?php

//add many values
$app->setConfig([
    'database' => [
        'default' => 'mysql://root:root@localhost/acmedb',
    ],
    'services' => [
        'Acme\Services\ViewProvider',
    ]
]);

//add a single value
$app->setConfig('baseurl', 'localhost/');

$app->getConfig()->baseurl = 'localhost/';
$app->getConfig()['baseurl'] = 'localhost/';
```

Access configuration

```php
<?php

//access all configuration
$app->getConfig();

//get configuration item
$default = $app->getConfig('database')->default; // returns 'mysql://root:root@localhost/acmedb
$default = $app->getConfig()->database->default; // returns 'mysql://root:root@localhost/acmedb
$default = $app->getConfig('database')['default']; // returns 'mysql://root:root@localhost/acmedb
$default = $app->getConfig()['database']['default']; // returns 'mysql://root:root@localhost/acmedb
```

## Middlewares

Hawkbit\Application middlewares allows advanced control of lifecycle execution.

```php
<?php

$app->addMiddleware(new Acme\SomeMiddleware);
```

Hawkbit\Application uses it's own runner `Hawkbit\Application\MiddelwareRunner`

## Routing

Hawkbit\Application uses routing integration of `league/route` and allows access to route collection methods directly.

Basic usage with anonymous functions:

```php
<?php
// index.php

$app->get('/', function ($request, $response) {
    $response->getBody()->write('<h1>It works!</h1>');
    return $response;
});

$app->get('/hello/{name}', function ($request, $response, $args) {
    $response->getBody()->write(
        sprintf('<h1>Hello, %s!</h1>', $args['name'])
    );
    return $response;
});

$app->run();
```

#### Access app from anonymous function

Hawkbit\Application allows to access itself from anonymous function through closure binding.

```php
<?php

$app->get('/hello/{name}', function ($request, $response, $args) {
    
    // access Hawkbit\Application
    $app = $this;
    
    $response->getBody()->write(
        sprintf('<h1>Hello, %s!</h1>', $args['name'])
    );
    return $response;
});

```

Basic usage with controllers:

```php
<?php

require __DIR__.'/../vendor/autoload.php';

$app = new Hawkbit\Application();

$app->get('/', 'HomeController::index'); // calls index method on HomeController class

$app->run();
```

```php
<?php

// HomeController.php

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class HomeController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $response->getBody()->write('<h1>It works!</h1>');
        return $response;
    }
}
```

Automatic constructor injection of controllers:

```php
<?php

// index.php

require __DIR__.'/../vendor/autoload.php';

$app = new Hawkbit\Application();

$app->getContainer()->add('CustomService', new CustomService);
$app->get('/', 'HomeController::index'); // calls index method on HomeController class

$app->run();
```

*Please use boot method in Service Providers for correct injection of services into controller!*

```php
<?php

// HomeController.php

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class HomeController
{
    /**
     * @var CustomService
     */
    private $service;

    /**
     * @param CustomService $application
     */
    public function __construct(CustomService $service = null)
    {
        $this->service = $service;
    }

    /**
     * @return CustomService
     */
    public function getService()
    {
        return $this->service;
    }
    
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        //do somehing with service
        $service = $this->getService();
        return $response;
    }
}
```

For more information about routes [read this guide](http://route.thephpleague.com/)

### Route groups

Hawkbit\Application add support for route groups. 

```php
<?php

$app->group('/admin', function (\League\Route\RouteGroup $route) {

    //access app container (or any other method!)
    $app = $this;
    
    $route->map('GET', '/acme/route1', 'AcmeController::actionOne');
    $route->map('GET', '/acme/route2', 'AcmeController::actionTwo');
    $route->map('GET', '/acme/route3', 'AcmeController::actionThree');
});
```

#### Available vars

- `$route` - `\League\Route\RouteGroup`
- `$this` - `\Hawkbit\Application`

## Middleware integrations

### StackPHP

Basic usage with StackPHP (using `Stack\Builder` and `Stack\Run`):

```php
<?php

// index.php
require __DIR__.'/../vendor/autoload.php';

$app = new Hawkbit\Application();

$app->get('/', function ($request, $response) {
    $response->setContent('<h1>Hello World</h1>');
    return $response;
});

$httpKernel = new Hawkbit\Application\Symfony\HttpKernelAdapter($app);

$stack = (new \Stack\Builder())
    ->push('Some/MiddleWare') // This will execute first
    ->push('Some/MiddleWare') // This will execute second
    ->push('Some/MiddleWare'); // This will execute third

$app = $stack->resolve($httpKernel);
\Stack\run($httpKernel); // The app will run after all the middlewares have run
```

### Zend Stratigility

Basic usage with Stratigility (using `Zend\Stratigility\MiddlewarePipe`):

```php
<?php

use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequestFactory;
use Hawkbit\Application;
use Hawkbit\Application\Stratigility\MiddlewarePipeAdapter;

$application = new Application();
$application->get('/', function($request, ResponseInterface $response){
    $response->getBody()->write('Hello World');
});
$middleware = new MiddlewarePipeAdapter($application);

//wrap html heading
$middleware->pipe('/', function($request, ResponseInterface $response, $next){
    $response->getBody()->write('<h1>');

    /** @var ResponseInterface $response */
    $response = $next($request, $response);

    $response->getBody()->write('</h1>');
});

/** @var ResponseInterface $response */
$response = $middleware(ServerRequestFactory::fromGlobals(), $application->getResponse());

echo $response->getBody(); //prints <h1>Hello World</h1>

```

## Error handling

Hawkbit\Application uses <a href="https://github.com/filp/whoops" target="_blank">Whoops</a> error handling framework and determines the error handler by request content type.

Set your own handler:

```php
<?php

$app->getErrorHandler()->push(new Acme\ErrorResponseHandler);
```

By default Hawkbit\Application runs with error options disabled. To enable debugging add

```php
<?php

$app->setConfig('error', true);
```

By default Hawkbit\Application is catching all errors. To disable error catching add

```php
<?php

$app->setConfig('error.catch', false);
```

## Console

The console application inherit all methods from Http Application except routing and PSR-7 handling and capturing. 
In addition to http application, the console application does not support all events (Refer to events for more 
information!)

## Logging

Hawkbit\Application has built in support for Monolog. To access a channel call:

```php
<?php

$app->getLogger('channel name');
```

For more information about channels read this guide - [https://github.com/Seldaek/monolog/blob/master/doc/usage.md#leveraging-channels](https://github.com/Seldaek/monolog/blob/master/doc/usage.md#leveraging-channels).

## Events

You can intercept requests and responses at seven points during the lifecycle. You can manipulate Request, Response and 
ErrorResponse via `Hawkbit\ApplicationEvent`.

### Application event

```php
<?php

/** @var \Hawkbit\Application\ApplicationEvent $event */

// custom params
$event->getParamCollection(); // returns a mutable \ArrayObject

// access application
$event->getApplication();

```

### request.received

```php
<?php

$app->addListener($app::EVENT_REQUEST_RECEIVED, function (\Hawkbit\Application\ApplicationEvent $event) {
    $request = $event->getRequest();
    
    // manipulate $request
    
    $event->setRequest($request);
});
```

This event is fired when a request is received but before it has been processed by the router.

### response.created

*Not available for Console applications!*

```php
<?php

$app->addListener($app::EVENT_RESPONSE_CREATED, function (\Hawkbit\Application\ApplicationEvent $event) {
    $request = $event->getRequest();
    $response = $event->getResponse();
        
    // manipulate request or response
    
    $event->setRequest($request);
    $event->setResponse($response);
});
```

This event is fired when a response has been created but before it has been output.

### response.sent

*Not available for Console applications! Please use `shutdown`*

```php
<?php

$app->addListener($app::EVENT_RESPONSE_SENT, function (\Hawkbit\Application\ApplicationEvent $event) {
    $request = $event->getRequest();
    $response = $event->getResponse();
    
    // manipulate request or response
    
    $event->setRequest($request);
    $event->setResponse($response);
});
```

This event is fired when a response has been output and before the application lifecycle is completed. Not available for Console Applications!

### runtime.error

```php
<?php

$app->addListener($app::EVENT_RUNTIME_ERROR, function (\Hawkbit\Application\ApplicationEvent $event, $exception) use ($app) {
    //process exception
});
```

This event is always fired when an error occurs.

### lifecycle.error

*Not available for Console applications! Please use `runtime.error`*

`$errorResponse` is used as default response

```php
<?php

$app->addListener($app::EVENT_LIFECYCLE_ERROR, function (\Hawkbit\Application\ApplicationEvent $event, \Exception $exception) {
    $errorResponse = $event->getErrorResponse();
 
    //manipulate error response and process exception
        
    $event->setErrorResponse($errorResponse);
});
```

This event is fired only when an error occurs while handling request/response lifecycle. 
This event is fired after runtime.error

### lifecycle.complete

*Not available for Console applications! Please use `shutdown`*

```php
<?php

$app->addListener($app::EVENT_LIFECYCLE_COMPLETE, function (\Hawkbit\Application\ApplicationEvent $event) {
    // access the request using $event->getRequest()
    // access the response using $event->getResponse()
});
```

This event is fired when a response has been output and before the application lifecycle is completed.

### shutdown

```php
<?php

$app->addListener($app::EVENT_SHUTDOWN, function (\Hawkbit\Application\ApplicationEvent $event, $response, $terminatedOutputBuffers = []) {
    // access the response using $event->getResponse()
    // access terminated output buffer contents
    // or force application exit()
});
```

This event is always fired after each operation is completed or failed.

### Custom Events

You can fire custom events using the event emitter directly:

```php
<?php

// addListener
$app->addListener('custom.event', function ($event, $time) {
    return 'the time is '.$time;
});

// or with class addListener
$app->addListener(Acme\Event::class, function (Acme\Event $event, $time) {
    return 'the time is '.$time;
});

// Publish
$app->getEventEmitter()->emit('custom.event', time());
```

## Dependency Injection Container

Hawkbit\Application uses `League/Container` as its dependency injection container.

You can bind singleton objects into the container from the main application object using ArrayAccess:

```php
<?php
/** @var Hawkbit\Application $app */
$app['db'] = function () use($app) {
    $config = $app->getConfig('database');
    $manager = new Illuminate\Database\Capsule\Manager;

    $manager->addConnection([
        'driver'    => 'mysql',
        'host'      => $config['host'],
        'database'  => $config['name'],
        'username'  => $config['user'],
        'password'  => $config['pass'],
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci'
    ], 'default');

    $manager->setAsGlobal();

    return $manager;
};
```

or by container access:

```php
<?php
/** @var Hawkbit\Application $app */
$app->getContainer()->share('db', function () use($app) {
    $config = $app->getConfig('database');
    $manager = new Illuminate\Database\Capsule\Manager;

    $manager->addConnection([
        'driver'    => 'mysql',
        'host'      => $config['db_host'],
        'database'  => $config['db_name'],
        'username'  => $config['db_user'],
        'password'  => $config['db_pass'],
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci'
    ], 'default');

    $manager->setAsGlobal();

    return $manager;
});
```

Multitons can be added using the `add` method on the container:

```php
<?php

//callback
$app->getContainer()->add('foo', function () {
    return new Foo();
});
```

Service providers can be registered using the `register` method on the Hawkbit\Application app or `addServiceProvider` on the container:

```php
<?php

$app->register('\My\Service\Provider');
$app->getContainer()->addServiceProvider('\My\Service\Provider');
```

For more information about service providers check out this page - [http://container.thephpleague.com/service-providers/](http://container.thephpleague.com/service-providers/).

For easy testing down the road it is recommended you embrace constructor injection:

```php
<?php

$app->getContainer()->add('Bar', function () {
        return new Bar();
});

$app->getContainer()->add('Foo', function () use ($app) {
        return new Foo(
            $app->getContainer()->get('Bar')
        );
});
```

### Container

Set your own container needs an instance of `\League\Container\ContainerInterface`

```php
<?php

$app->setContainer($container);
```

Get container

```php
<?php

$app->getContainer();
```

## Services

Hawkbit\Application uses dependency injection container to access services. Following integrations can be exchanged.

### Configurator

Uses in `Application::setConfig()`,`Application::getConfig()` and `Application::hasConfig()`

```php
<?php

$app->getConfigurator();
``` 

```php
<?php

$app->getContainer()->share(\Zend\Config\Config::class, new \Zend\Config\Config([], true));
```

### error handler

```php
<?php

$app->getContainer()->share(\Whoops\Run::class, new \Whoops\Run());
```

```php
<?php

$app->getErrorHandler();
``` 

### error response handler

```php
<?php

$app->getContainer()->share(\Whoops\Handler\HandlerInterface::class, Acme\ErrorResponseHandler::class);
```

```php
<?php

$app->getErrorResponseHandler();
``` 

### psr logger

Get a new logger instance by channel name

```php
<?php

$app->getContainer()->add(\Psr\Log\LoggerInterface::class, \Monolog\Logger::class);
```

```php
<?php

$app->getLogger('channel name');
``` 

Get a list of available logger channels

```php
<?php

$app->getLoggerChannels();
```

### psr server request

```php
<?php

$app->getContainer()->share(\Psr\Http\Message\ServerRequestInterface::class, \Zend\Diactoros\ServerRequestFactory::fromGlobals());
```

```php
<?php

$app->getRequest();
``` 

### psr response

```php
<?php

$app->getContainer()->add(\Psr\Http\Message\ResponseInterface::class, \Zend\Diactoros\Response::class);
```

```php
<?php

$app->getRequest();
``` 

### response emitter

```php
<?php

$app->getContainer()->share(\Zend\Diactoros\Response\EmitterInterface::class, \Zend\Diactoros\Response\SapiEmitter::class);
```

```php
<?php

$app->getResponseEmitter();
``` 


## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email <mjls@web.de> instead of using the issue tracker.

## Credits

- [Marco Bunge](https://github.com/mbunge)
- [Alex Bilbie](https://github.com/alexbilbie) (Proton)
- [All contributors](https://github.com/hawkbit/hawkbit/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/hawkbit/hawkbit.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/HawkBitPhp/hawkbit/master.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/hawkbit/hawkbit.svg?style=flat-square
[ico-coveralls]: https://img.shields.io/coveralls/HawkBitPhp/hawkbit/master.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/hawkbit/hawkbit
[link-travis]: https://travis-ci.org/HawkBitPhp/hawkbit
[link-downloads]: https://packagist.org/packages/hawkbit/hawkbit
[link-author]: https://github.com/mbunge
[link-contributors]: ../../contributors
[link-coveralls]: https://coveralls.io/github/HawkBitPhp/hawkbit
