<?php
/**
 * The Turbine Micro Framework. An advanced derivate of Proton Micro Framework
 *
 * @author Marco Bunge <marco_bunge@web.de>
 * @author Alex Bilbie <hello@alexbilbie.com>
 * @copyright Marco Bunge <marco_bunge@web.de>
 *
 * @license MIT
 */

namespace Hawkbit;

use League\Container\Container;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerInterface;
use League\Container\ReflectionContainer;
use League\Event\Emitter;
use League\Event\EmitterInterface;
use League\Event\EmitterTrait;
use League\Event\ListenerAcceptorInterface;
use League\Route\Http\Exception\NotFoundException;
use League\Route\RouteCollection;
use League\Route\RouteCollectionInterface;
use League\Route\RouteCollectionMapTrait;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Hawkbit\Application\ApplicationEvent;
use Hawkbit\Application\MiddlewareRunner;
use Whoops\Handler\Handler;
use Whoops\Handler\HandlerInterface;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\XmlResponseHandler;
use Whoops\Run;
use Zend\Config\Config;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

/**
 * Hawkbit Application Class.
 */
class Application implements ApplicationInterface, ContainerAwareInterface, ListenerAcceptorInterface,
    RouteCollectionInterface, TerminableInterface, \ArrayAccess
{
    use EmitterTrait;
    use RouteCollectionMapTrait;

    /**
     * @var \League\Route\RouteCollection
     */
    protected $router;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var array
     */
    protected $loggers = [];

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var \Zend\Diactoros\Response\EmitterInterface
     */
    protected $responseEmitter;

    /**
     * Set while handle exception.
     * @var bool
     */
    private $error = false;

    /**
     * Flag which is forcing response termination after
     * handling request / response lifecycle
     *
     * Response always destroyed before throwing exceptions!
     *
     * @var bool
     */
    private $terminate = true;

    /**
     * Flag which forces response emitting and ignores already sended output
     *
     * @var bool
     */
    private $forceResponseEmitting = false;

    /**
     * Get content type of current request or response
     *
     * @var string
     */
    private $contentType = 'text/html';

    /**
     * @var callable[]
     */
    private $middlewares = [];

    /** @var ApplicationEvent */
    private $applicationEvent;

    /**
     * New Application.
     *
     * @param bool|array $configuration Enable debug mode
     */
    public function __construct($configuration = [])
    {
        if (is_bool($configuration)) {
            $this->setConfig(self::KEY_ERROR, $configuration);
        } elseif (
            is_array($configuration) ||
            ($configuration instanceof \ArrayAccess ||
                $configuration instanceof \Traversable)
        ) {
            $this->setConfig($configuration);
        }
        $this->init();
    }

    protected function init()
    {
        // configure request content type
        $this->setContentType(ServerRequestFactory::getHeader('content-type', ServerRequestFactory::fromGlobals()->getHeaders(), $this->getContentType()));
    }

    /*******************************************
     *
     *               CONFIG
     *
     */

    /**
     * Set a config item. Add recursive if key is traversable.
     *
     * @param string|array|\Traversable $key
     * @param mixed $value
     *
     * @return $this
     */
    public function setConfig($key, $value = null)
    {
        $configurator = $this->getConfigurator();
        if(!is_scalar($key)){
            $configuratorClass = get_class($configurator);
            $configurator->merge(new $configuratorClass($key, true));
        }else{
            $configurator[$key] = $value;
        }

        return $this;
    }

    /**
     * Get a config key's value
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getConfig($key = null, $default = null)
    {

        $configurator = $this->getConfigurator();
        if (null === $key) {
            return $configurator;
        }

        return $configurator->get($key, $default);
    }

    /**
     * Check if key exists
     *
     * @param $key
     *
     * @return bool
     */
    public function hasConfig($key)
    {
        $configurator = $this->getConfigurator();
        return isset($configurator[$key]);
    }


    /*******************************************
     *
     *           Middleware
     *
     */

    /**
     * Add a middleware
     *
     * @param $middleware
     */
    public function addMiddleware(callable $middleware)
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * @return callable[]
     */
    public function getMiddlewares()
    {
        return $this->middlewares;
    }

    /*******************************************
     *
     *           GETTER / SETTER
     *
     */

    /**
     * Get configuration container
     *
     * @return \Hawkbit\Configuration
     *
     */
    public function getConfigurator()
    {
        if (!$this->getContainer()->has(Configuration::class)) {
            $this->getContainer()->share(Configuration::class, (new Configuration([], true)));
        }

        return $this->getContainer()->get(Configuration::class);
    }

    /**
     * Set a container.
     *
     * @param \League\Container\ContainerInterface $container
     * @return $this
     */
    public function setContainer(ContainerInterface $container)
    {
        $application = $this;
        $container->share(ApplicationInterface::class, $application);
        $container->share(\Interop\Container\ContainerInterface::class, $container);

        $this->container = $container;

        return $this;
    }

    /**
     * Get the container.
     *
     * @return \League\Container\Container|\League\Container\ContainerInterface
     */
    public function getContainer()
    {
        if (!isset($this->container)) {
            $this->setContainer(new Container);
        }

        return $this->container;
    }

    /**
     * Get the Emitter.
     *
     * @return \League\Event\EmitterInterface
     */
    public function getEmitter()
    {
        if (!$this->getContainer()->has(EmitterInterface::class)) {
            $this->getContainer()->share(EmitterInterface::class, new Emitter());
        }

        /** @var EmitterInterface $validateContract */
        $validateContract = $this->validateContract($this->getContainer()->get(EmitterInterface::class), EmitterInterface::class);
        return $validateContract;
    }

    /**
     * @return \Whoops\Run
     */
    public function getErrorHandler()
    {
        if (!$this->getContainer()->has(Run::class)) {
            $errorHandler = new Run();
            $errorHandler->pushHandler($this->getErrorResponseHandler());
            $errorHandler->pushHandler(function (\Exception $exception) {

                // log all errors
                $this->getLogger()->error($exception->getMessage());

                // emit runtime error event
                $applicationEvent = $this->getApplicationEvent();
                $applicationEvent->setName(static::EVENT_RUNTIME_ERROR);
                $this->emit($applicationEvent, $exception);

                return Handler::DONE;
            });
            $errorHandler->register();
            $this->getContainer()->share(Run::class, $errorHandler);
        }

        /** @var Run $contract */
        $contract = $this->validateContract($this->getContainer()->get(Run::class), Run::class);
        return $contract;
    }

    /**
     * Get the error response handler
     *
     * @return \Whoops\Handler\HandlerInterface
     */
    public function getErrorResponseHandler()
    {
        if (!$this->getContainer()->has(HandlerInterface::class)) {
            if ($this->isCli() || false === $this->getConfig(self::KEY_ERROR, static::DEFAULT_ERROR)) {
                $class = PlainTextHandler::class;
            } elseif ($this->isSoapRequest() || $this->isXmlRequest()) {
                $class = XmlResponseHandler::class;
            } elseif ($this->isAjaxRequest() || $this->isJsonRequest()) {
                $class = JsonResponseHandler::class;
            } else {
                $class = PrettyPageHandler::class;
            }
            $this->getContainer()->add(HandlerInterface::class, $class);
        }

        /** @var HandlerInterface $contract */
        $contract = $this->validateContract($this->getContainer()->get(HandlerInterface::class), HandlerInterface::class);
        return $contract;
    }

    /**
     * Return the event emitter.
     *
     * @return \League\Event\Emitter|\League\Event\EmitterInterface
     */
    public function getEventEmitter()
    {
        return $this->getEmitter();
    }

    /**
     * Return a logger
     *
     * @param string $name
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger($name = 'default')
    {
        if (isset($this->loggers[$name])) {
            return $this->loggers[$name];
        }

        if (!$this->getContainer()->has(LoggerInterface::class)) {
            $this->getContainer()->add(LoggerInterface::class, Logger::class);
        }

        /** @var Logger $logger */
        $logger = $this->getContainer()->get(LoggerInterface::class, [$name]);

        // by default silence all loggers.
        $logger->pushHandler(new NullHandler());

        $this->loggers[$name] = $logger;

        /** @var LoggerInterface $contract */
        $contract = $this->validateContract($this->loggers[$name], LoggerInterface::class);
        return $contract;
    }

    /**
     * Get a list of logger names
     *
     * @return string[]
     */
    public function getLoggerChannels()
    {
        return array_keys($this->loggers);
    }

    /**
     * Return the router.
     *
     * @return \League\Route\RouteCollection
     */
    public function getRouter()
    {
        if (!isset($this->router)) {
            $container = clone $this->getContainer();
            $container->delegate(new ReflectionContainer);
            $this->router = (new RouteCollection($container));
        }

        return $this->validateContract($this->router, RouteCollection::class);
    }

    /**
     * Get the request
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    public function getRequest()
    {
        if (!$this->getContainer()->has(ServerRequestInterface::class)) {
            $this->getContainer()->share(ServerRequestInterface::class, ServerRequestFactory::fromGlobals()->withHeader('content-type', $this->getContentType()));
        }

        /** @var ServerRequestInterface $request */
        $request = $this->validateContract($this->getContainer()->get(ServerRequestInterface::class),
            ServerRequestInterface::class);
        return $request;
    }

    /**
     * Get the response
     *
     * @param string $content
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getResponse($content = '', $contentType = null)
    {
        //transform content by content type
        if ($this->isAjaxRequest() || $this->isJsonRequest()) {
            if ($content instanceof Response\JsonResponse) {
                $content = json_decode($content->getBody());
            } elseif (!is_array($content)) {
                $content = [$content];
            }
        } else {
            if (is_array($content)) {
                $content = implode('', $content);
            } elseif ($content instanceof ResponseInterface) {
                $content = $content->getBody()->__toString();
            }
        }

        if (!$this->getContainer()->has(ResponseInterface::class)) {
            if ($this->isCli()) {
                $class = Response\TextResponse::class;
            } elseif ($this->isJsonRequest()) {
                $class = Response\JsonResponse::class;
            } else {
                $class = Response\HtmlResponse::class;
            }
            $this->getContainer()->add(ResponseInterface::class, $class);
        }

        /** @var ResponseInterface $response */
        $response = $this->validateContract($this->getContainer()->get(ResponseInterface::class, [$content]),
            ResponseInterface::class);

        //inject request content type
        $request = $this->getRequest();

        $contentTypeKey = 'content-type';
        foreach ($request->getHeader($contentTypeKey) as $contentType) {
            $response = $response->withHeader($contentTypeKey, $contentType);
        }

        return $response;
    }


    /**
     * Get response emitter
     *
     * @return \Zend\Diactoros\Response\EmitterInterface
     */
    public function getResponseEmitter()
    {
        if (!$this->getContainer()->has(Response\EmitterInterface::class)) {
            $this->getContainer()->share(Response\EmitterInterface::class, new Response\SapiEmitter());
        }

        return $this->validateContract($this->getContainer()->get(Response\EmitterInterface::class),
            Response\EmitterInterface::class);
    }

    /*******************************************
     *
     *               STATUS
     *
     */

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param string $contentType
     * @return Application
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
        return $this;
    }

    /**
     * Check if request is a ajax request
     *
     * @return bool
     */
    public function isAjaxRequest()
    {
        return
            false !== strpos(
                strtolower(ServerRequestFactory::getHeader('x-requested-with', $this->getRequest()->getHeaders(), '')),
                'xmlhttprequest'
            ) && $this->isHttpRequest();
    }

    /**
     * Check if request is a ajax request
     *
     * @return bool
     */
    public function isJsonRequest()
    {
        return
            false !== strpos(
                $this->getContentType(),
                'json'
            ) && $this->isHttpRequest();
    }

    /**
     * Check if request is a ajax request
     *
     * @return bool
     */
    public function isSoapRequest()
    {
        return
            false !== strpos(
                $this->getContentType(),
                'soap'
            ) && $this->isHttpRequest();
    }

    /**
     * Check if request is a ajax request
     *
     * @return bool
     */
    public function isXmlRequest()
    {
        return
            false !== strpos(
                $this->getContentType(),
                'xml'
            ) && $this->isHttpRequest();
    }

    /**
     * Check server environment for cli
     *
     * @return bool
     */
    public function isCli()
    {
        return php_sapi_name() === 'cli';
    }

    /**
     * Check if an error has been occurred
     *
     * @return boolean
     */
    public function isError()
    {
        return $this->error;
    }

    /**
     * Check server environment for http
     *
     * @return bool
     */
    public function isHttpRequest()
    {
        return !$this->isCli();
    }

    /**
     * Check that terminate the response request
     * lifecycle is enabled
     *
     * @return boolean
     */
    public function canTerminate()
    {
        return $this->terminate;
    }

    /**
     * Set terminating flag
     *
     * @param boolean $terminate
     * @return $this
     */
    public function setCanTerminate($terminate)
    {
        $this->terminate = $terminate;
        return $this;
    }

    /**
     * @return boolean
     */
    public function canForceResponseEmitting()
    {
        return $this->forceResponseEmitting;
    }

    /**
     * @param boolean $forceResponseEmitting
     * @return $this
     */
    public function setForceResponseEmitting($forceResponseEmitting)
    {
        $this->forceResponseEmitting = $forceResponseEmitting;
        return $this;
    }

    /*******************************************
     *
     *               ROUTER
     *
     */

    /**
     * Add a route to the map.
     *
     * @param $method
     * @param $route
     * @param $action
     *
     * @return \League\Route\Route
     */
    public function map($method, $route, $action)
    {
        return $this->getRouter()->map($method, $route, $this->bindClosureToInstance($action));
    }

    /**
     * Add a group of routes to the collection. Binds $this to app instance
     *
     * @param $prefix
     * @param callable $group
     *
     * @return \League\Route\RouteGroup
     */
    public function group($prefix, callable $group)
    {
        return $this->getRouter()->group($prefix, $this->bindClosureToInstance($group));
    }

    /*******************************************
     *
     *                Events
     *
     */

    /**
     * Subscribe to an event.
     *
     * @param string $event
     * @param callable $listener
     * @param int $priority
     *
     * @deprecated
     */
    public function subscribe($event, $listener, $priority = ListenerAcceptorInterface::P_NORMAL)
    {
        $this->addListener($event, $listener, $priority);
    }

    /*******************************************
     *
     *                IoC
     *
     */

    /**
     * Array Access get.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->getContainer()->get($key);
    }

    /**
     * Array Access set.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->getContainer()->share($key, $value);
    }

    /**
     * Removing services are not support by
     * `league/container` 2.0 and greater
     *
     * @param string $key
     *
     * @return void
     */
    public function offsetUnset($key)
    {
    }

    /**
     * Array Access isset.
     *
     * @param string $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->getContainer()->has($key);
    }

    /**
     * Register a new service provider
     *
     * @param $serviceProvider
     */
    public function register($serviceProvider)
    {
        $this->getContainer()->addServiceProvider($serviceProvider);
    }

    /*******************************************
     *
     *         CONTRACTS
     *
     */

    /**
     * throw a exception
     *
     * @param \Throwable|\Exception $exception
     *
     * @throws \Throwable|\Exception
     */
    public function throwException($exception)
    {
        $this->shutdown();
        throw $exception;
    }

    /**
     * Validates that class is instance of contract
     *
     * @param $class
     * @param $contract
     *
     * @return string|object
     *
     * @throws \InvalidArgumentException|\LogicException
     */
    public function validateContract($class, $contract)
    {
        $validateObject = function ($object) {
            //does need trigger when calling *_exists with object
            $condition = is_string($object) ? class_exists($object) || interface_exists($object) : is_object($object);
            if (false === $condition) {
                $this->throwException(new \InvalidArgumentException('Class not exists ' . $object));
            }
        };

        $convertClassToString = function ($object) {
            if (is_object($object)) {
                $object = get_class($object);
            }

            return is_string($object) ? $object : false;
        };

        $validateObject($class);
        $validateObject($contract);

        if (!($class instanceof $contract)) {

            if (is_object($class)) {
                $class = get_class($class);
            }
            $this->throwException(new \LogicException($convertClassToString($class) . ' needs to be an instance of ' . $convertClassToString($contract)));
        }

        return $class;
    }

    /*******************************************
     *
     *         LIFECYCLE INVOCATION
     *
     */

    /**
     * Convert request into response. If an error occurs, Hawkbit tries
     * to handle error as response.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param bool $catch
     *
     * @return ResponseInterface
     *
     * @throws \Throwable
     */
    public function handle(
        ServerRequestInterface $request,
        ResponseInterface $response = null,
        $catch = self::DEFAULT_ERROR_CATCH
    )
    {
        // Passes the request to the container
        if (!$this->getContainer()->has(ServerRequestInterface::class)) {
            $this->getContainer()->share(ServerRequestInterface::class, $request);
        }

        //inject request content type
        $this->setContentType(ServerRequestFactory::getHeader('content-type', $this->getRequest()->getHeaders(), ''));

        if ($response === null) {
            $response = $this->getResponse();
        }

        $applicationEvent = $this->getApplicationEvent();
        $applicationEvent->setRequest($request);
        $applicationEvent->setResponse($response);

        // init middleware runner
        $middlewareRunner = new MiddlewareRunner($this->getMiddlewares());
        // add request handler middleware
        $middlewareRunner->addMiddleware(function (ServerRequestInterface $request, $response, $next) {
            return $next($this->handleRequest($request), $response);
        });


        // fetch response
        $response = $middlewareRunner->run($request, $response,
            function ($request, $response) {
                return $this->handleResponse($request, $response);
            },
            function ($exception, $request, $response) use ($catch) {
                $notFoundException = $exception instanceof NotFoundException;
                $response = $this->handleError($exception, $request, $response, $catch)
                    ->withStatus($notFoundException ? 404 : 500);

                return $response;
            }
        );

        $this->setContentType(ServerRequestFactory::getHeader('content-type', $this->getResponse()->getHeaders(), ''));

        return $response;
    }

    /**
     * Convert request into response.
     *
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     */
    public function handleRequest(ServerRequestInterface $request)
    {
        $applicationEvent = $this->getApplicationEvent();
        $applicationEvent->setName(self::EVENT_REQUEST_RECEIVED);
        $this->emit($applicationEvent, $request);

        return $applicationEvent->getRequest();
    }

    /**
     * Handle Response
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function handleResponse(ServerRequestInterface $request, ResponseInterface $response)
    {
        $applicationEvent = $this->getApplicationEvent();
        $applicationEvent->setName(self::EVENT_RESPONSE_CREATED);
        $applicationEvent->setResponse($this->getRouter()->dispatch(
            $request,
            $response
        ));

        $this->emit($applicationEvent, $request, $response);

        return $applicationEvent->getResponse();
    }

    /**
     * Handle error and return response of error message or throw
     * error if error.catch is disabled.
     *
     * @param \Throwable|\Exception $exception
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param bool $catch
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws string
     */
    public function handleError(
        $exception,
        ServerRequestInterface $request,
        ResponseInterface $response,
        $catch = self::DEFAULT_ERROR_CATCH
    )
    {
        // notify app that an error occurs
        $this->error = true;

        $exception = $this->decorateException($exception);
        $errorHandler = $this->getErrorHandler();

        // if delivered value of $catch, then configured value, then default value
        $catch = self::DEFAULT_ERROR_CATCH !== $catch ? $catch : $this->getConfig(self::KEY_ERROR_CATCH, $catch);

        $showError = $this->getConfig(self::KEY_ERROR, static::DEFAULT_ERROR);
        if (
            false === $catch
            && true === $showError
        ) {
            $this->throwException($exception);
        }

        $message = $this->determineErrorMessage($exception, $errorHandler);

        return $this->determineErrorResponse($exception, $message, $response, $request);
    }

    /**
     * Handle response / request lifecycle
     *
     * When $callable is a valid callable, callable will executed before emit response
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request A Request instance
     * @param \Psr\Http\Message\ResponseInterface $response A response instance
     *
     * @return $this
     *
     */
    public function run(ServerRequestInterface $request = null, ResponseInterface $response = null)
    {
        if ($request === null) {
            $request = $this->getRequest();
        }
        $response = $this->handle($request, $response);

        $this->emitResponse($request, $response);

        if ($this->canTerminate()) {
            $this->terminate($request, $response);
        }

        $this->shutdown($response);

        return $this;
    }

    /**
     * Emit a response
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @throws \Exception
     */
    public function emitResponse(ServerRequestInterface $request, ResponseInterface $response)
    {
        try {
            $this->getResponseEmitter()->emit($response);
        } catch (\Exception $e) {
            if ($this->canForceResponseEmitting()) {
                // flush buffers
                $maxBufferLevel = ob_get_level();

                while (ob_get_level() > $maxBufferLevel) {
                    ob_end_flush();
                }

                // print response
                echo $response->getBody();
            } else {
                throw $e;
            }
        }
        $applicationEvent = $this->getApplicationEvent();
        $applicationEvent->setName(self::EVENT_RESPONSE_SENT);
        $applicationEvent->setRequest($request);
        $applicationEvent->setResponse($response);
        $this->emit($applicationEvent);
    }

    /**
     * Terminates a request/response cycle.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $applicationEvent = $this->getApplicationEvent();
        $applicationEvent->setRequest($request);
        $applicationEvent->setResponse($response);
        $applicationEvent->setName(self::EVENT_LIFECYCLE_COMPLETE);
        $this->emit($applicationEvent);

    }

    /**
     * Finish request. Collect garbage and terminate output buffering
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return void
     */
    public function shutdown($response = null)
    {

        $this->collectGarbage();
        $applicationEvent = $this->getApplicationEvent();
        $applicationEvent->setResponse($response);
        $applicationEvent->setName(self::EVENT_SHUTDOWN);
        $this->emit($applicationEvent, $this->terminateOutputBuffering(1, $response));
    }

    /**
     * Finish request. Collect garbage and terminate output buffering
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @deprecated
     * @return void
     */
    public function finishRequest($response = null)
    {
        $this->shutdown($response);
    }

    /**
     * Close response stream and terminate output buffering
     *
     * @param int $level
     * @param null|\Psr\Http\Message\ResponseInterface $response
     * @return array
     */
    public function terminateOutputBuffering($level = 0, $response = null)
    {

        // close response stream berfore terminating output buffer
        // and only if response is an instance of
        // \Psr\Http\ResponseInterface
        if ($response instanceof ResponseInterface) {
            $body = $response->getBody();
            if ($body->isReadable()) {
                $body->close();
            }
        }

        // Command line output buffering is disabled in cli by default
        if ($this->isCli()) {
            return [];
        }

        // $level needs to be a numeric value
        if (!is_numeric($level)) {
            $level = 0;
        }

        // force type casting to an integer value
        if (!is_int($level)) {
            $level = (int)$level;
        }

        // avoid infinite loop on clearing
        // output buffer by set level to 0
        // if $level is smaller
        if (-1 > $level) {
            $level = 0;
        }

        // terminate all output buffers until $level is 0 or desired level
        // collect all contents and return
        $content = [];
        while (ob_get_level() > $level) {
            $content[] = ob_get_clean();
        }
        return $content;
    }

    /**
     * Perform garbage collection
     */
    public function collectGarbage()
    {
        // try to enable garbage collection
        if (!gc_enabled()) {
            @gc_enable();
        }

        // collect garbage only if garbage
        // collection is enabled
        if (gc_enabled()) {
            gc_collect_cycles();
        }
    }

    /**
     * Perform garbage collection
     * @deprecated
     */
    public function cleanUp()
    {
        return $this->collectGarbage();
    }

    /**
     * Convert any type into an exception
     *
     * @param $error
     * @return \Exception|string
     */
    private function decorateException($error)
    {

        if (is_callable($error)) {
            $error = $this->getContainer()->call($error, [$this->getRequest(), $this->getResponse()]);
        }

        if (is_object($error) && !($error instanceof \Exception)) {
            $error = method_exists($error,
                '__toString') ? $error->__toString() : 'Error with object ' . get_class($error);
        }

        if (is_resource($error)) {
            $error = 'Error with resource type ' . get_resource_type($error);
        }

        if (is_array($error)) {
            $error = implode("\n", $error);
        }

        if (!($error instanceof \Exception)) {
            $error = new \Exception(is_scalar($error) ? $error : 'Error with ' . gettype($error));
        }

        return $error;
    }

    /**
     * Determine error message by error configuration
     *
     * @param \Throwable $exception
     * @param \Whoops\Run $errorHandler
     *
     * @return string
     *
     */
    private function determineErrorMessage($exception, $errorHandler)
    {
        // quit if error occured
        $shouldQuit = $this->error;

        // if request type ist not strict e.g. xml or json consider error config
        if (!$this->isXmlRequest() && !$this->isJsonRequest()) {
            $shouldQuit = $shouldQuit && $this->getConfig(self::KEY_ERROR, static::DEFAULT_ERROR);
        }

        $errorHandler->allowQuit($shouldQuit);

        $method = $errorHandler::EXCEPTION_HANDLER;
        ob_start();
        $errorHandler->$method($exception);
        $message = ob_get_clean();

        return $message;
    }

    /**
     * Determines response for error. Emits `lifecycle.error` event.
     *
     * @param \Throwable $exception
     * @param string $message
     * @param ResponseInterface $response
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function determineErrorResponse(
        $exception,
        $message,
        ResponseInterface $response,
        ServerRequestInterface $request
    )
    {
        $applicationEvent = $this->getApplicationEvent();
        $applicationEvent->setName(self::EVENT_LIFECYCLE_ERROR);
        $applicationEvent->setRequest($request);
        $applicationEvent->setResponse($response);
        $applicationEvent->setErrorResponse($this->getResponse());

        $this->emit($applicationEvent, $exception);

        $errorResponse = $applicationEvent->getErrorResponse();

        if (!$errorResponse->getBody()->isWritable()) {
            return $errorResponse;
        }

        $content = $errorResponse->getBody()->__toString();
        if (empty($content)) {
            $errorResponse->getBody()->write($message);
        }

        return $errorResponse;
    }

    /**
     * @return ApplicationEvent
     */
    public function getApplicationEvent()
    {
        if (null === $this->applicationEvent) {
            $this->applicationEvent = new ApplicationEvent($this);
        }
        return $this->applicationEvent;
    }

    /**
     * Validate that config key is scalar
     *
     * @param $key
     * @throws \Throwable
     */
    private function validateConfigKey($key)
    {
        if (!is_scalar($key)) {
            $this->throwException(new \InvalidArgumentException('Key needs to be a valid scalar!'));
        }
    }

    /**
     * Bind any closure to application instance
     *
     * @param $closure
     *
     * @return mixed
     */
    private function bindClosureToInstance($closure)
    {
        if ($closure instanceof \Closure) {
            \Closure::bind($closure, $this, get_class($this));
        }

        return $closure;
    }
}
