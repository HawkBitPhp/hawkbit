<?php
/**
 * The Hawkbit Micro Framework. An advanced derivate of Proton Micro Framework
 *
 * @author Marco Bunge <marco_bunge@web.de>
 * @author Daniyal Hamid (@Designcise) <hello@designcise.com>
 * @copyright Marco Bunge <marco_bunge@web.de>
 *
 * @license MIT
 */

namespace Hawkbit\Application;

use Hawkbit\Application\Services\WhoopsService;
use Hawkbit\Configuration;
use League\Container\Container;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerInterface;
use League\Event\Emitter;
use League\Event\EmitterInterface;
use League\Event\EmitterTrait;
use League\Event\ListenerAcceptorInterface;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

abstract class AbstractApplication implements ApplicationInterface, ContainerAwareInterface, ListenerAcceptorInterface, \ArrayAccess
{
    use EmitterTrait;

    /**
     * Set while handle exception.
     * @var bool
     */
    protected $error = false;

    /**
     * @var array
     */
    protected $loggers = [];

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ApplicationEvent
     */
    protected $applicationEvent;

    /**
     * @var string
     */
    protected $applicationEventClass = ApplicationEvent::class;

    /**
     * @var \Exception[]|\Throwable[]
     */
    protected $exceptionStack = [];

    /** IOC */

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

    /** Config */

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

    /**
     * @return \Hawkbit\Application\Services\WhoopsService
     */
    public function getErrorHandler()
    {
        /** @var \Hawkbit\Application\Services\WhoopsService $contract */
        $contract = $this->validateContract($this->getContainer()->get(WhoopsService::class), WhoopsService::class);
        return $contract;
    }

    /**
     * @return \Exception[]|\Throwable[]
     */
    public function getExceptionStack()
    {
        return $this->exceptionStack;
    }

    /**
     * @param \Exception|\Throwable $exception
     * @return $this
     */
    public function pushException($exception){
        array_push($this->exceptionStack, $exception);
        return $this;
    }

    /**
     * @return \Exception|\Throwable
     */
    public function getLastException(){
        $exceptionStack = $this->getExceptionStack();
        $exception = end($exceptionStack);
        reset($exception);
        return $exception;
    }

    /** Events */

    /**
     * Return the event emitter.
     *
     * @return \League\Event\Emitter|\League\Event\EmitterInterface
     */
    public function getEventEmitter()
    {
        if (!$this->getContainer()->has(EmitterInterface::class)) {
            $this->getContainer()->share(EmitterInterface::class, new Emitter());
        }

        /** @var EmitterInterface $validateContract */
        $validateContract = $this->validateContract($this->getContainer()->get(EmitterInterface::class), EmitterInterface::class);
        return $validateContract;
    }

    /** Logging */

    /**
     * Return a logger
     *
     * @param string $channel
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger($channel = 'default')
    {
        if (isset($this->loggers[$channel])) {
            return $this->loggers[$channel];
        }

        /** @var Logger $logger */
        $logger = $this->getContainer()->get(LoggerInterface::class, [$channel]);

        $this->loggers[$channel] = $logger;

        /** @var LoggerInterface $contract */
        $contract = $this->validateContract($this->loggers[$channel], LoggerInterface::class);
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

    /**
     * Bind any closure to application instance
     *
     * @param $closure
     * @param $instance
     *
     * @return mixed
     */
    protected function bindClosureToInstance($closure, $instance)
    {
        if ($closure instanceof \Closure) {
            $closure = $closure->bind($closure, $instance, get_class($instance));
        }

        return $closure;
    }

    /**
     * @return ApplicationEvent
     */
    public function getApplicationEvent()
    {
        if (null === $this->applicationEvent) {
            $class = $this->applicationEventClass;
            $this->applicationEvent = new $class($this);
        }
        return $this->applicationEvent;
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
     * Check server environment for http
     *
     * @return bool
     */
    public function isHttpRequest()
    {
        return !$this->isCli();
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
     * Shutdown application lifecycle
     *
     * @return void
     */
    abstract public function shutdown();

    /**
     * Initialize Application
     *
     * @return void
     */
    abstract public function init();
}
