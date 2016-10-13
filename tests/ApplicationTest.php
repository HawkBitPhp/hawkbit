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

namespace Hawkbit\Tests;

use League\Container\Container;
use League\Event\Emitter;
use League\Event\Event;
use League\Route\Http\Exception\NotFoundException;
use League\Route\RouteCollection;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Tests\Fixtures\ServerRequest;
use Hawkbit\Application;
use Hawkbit\Application\ApplicationEvent;
use Hawkbit\Tests\TestAsset\SharedTestController;
use Hawkbit\Tests\TestAsset\TestController;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiStreamEmitter;
use Zend\Diactoros\ServerRequestFactory;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Test configuration mutation and accessing
     */
    public function testConfiguration()
    {
        $app = new Application(['foo' => 'bar']);
        $this->assertInstanceOf(\ArrayAccess::class, $app->getConfig());
        $this->assertTrue($app->hasConfig('foo'));
        $this->assertEquals('bar', $app->getConfig('foo'));
        $app->setConfig(['baz' => 'far']);
        $this->assertEquals('far', $app->getConfig('baz'));
        $app->setConfig('bar', 'foo');
        $config = $app->getConfig('bar');
        $this->assertEquals('foo', $config);
    }

    /**
     * Test accessing services with declared getter methods
     */
    public function testServiceAccessor()
    {
        $app = new Application();
        $this->assertTrue($app->getContainer() instanceof Container);
        $this->assertTrue($app->getRouter() instanceof RouteCollection);
        $this->assertTrue($app->getEventEmitter() instanceof Emitter);

        $logger = $app->getLogger();
        $this->assertTrue($logger instanceof Logger);
        $this->assertEquals($logger, $app->getLogger('default'));
    }

    /**
     * Test accessing service like array
     */
    public function testArrayAccessContainer()
    {
        $app = new Application();
        $class = new \stdClass();
        $app['foo'] = $class;

        $this->assertSame($class, $app['foo']);
        $this->assertInstanceOf(\stdClass::class, $app['foo']);
        $this->assertTrue(isset($app['foo']));
    }

    /**
     * Test add event listener
     */
    public function testAddListener()
    {
        $app = new Application(true);

        $app->addListener('request.received', function (ApplicationEvent $event) {
            $this->assertInstanceOf(ServerRequestInterface::class, $event->getRequest());
        });

        $this->assertTrue($app->getEmitter()->hasListeners('request.received'));

        $app->get('/', function () {
        });

        $foo = null;
        $app->addListener('response.created', function ($event) use (&$foo) {
            $foo = 'bar';
        });

        $request = ServerRequestFactory::fromGlobals();
        $response = $app->handle($request);

        $this->assertEquals('bar', $foo);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test response termination
     */
    public function testTerminate()
    {
        $app = new Application();

        $app->addListener('response.sent', function (ApplicationEvent $event) {
            $this->assertInstanceOf('League\Event\Event', $event);
            $this->assertInstanceOf(ServerRequestInterface::class, $event->getRequest());
            $this->assertInstanceOf(ResponseInterface::class, $event->getResponse());
        });

        $request = ServerRequestFactory::fromGlobals();
        $response = $app->handle($request);

        $app->terminate($request, $response);
    }

    /**
     * Test handling all available http methods successful
     */
    public function testHandleHttpMethods()
    {
        $app = new Application();

        $action = function ($request, ResponseInterface $response) {
            $response->getBody()->write('<h1>It works!</h1>');
            return $response;
        };

        $request = ServerRequestFactory::fromGlobals();

        $handlingApp = clone $app;
        $handlingApp->get('/', $action);
        $this->assertEquals('<h1>It works!</h1>', $handlingApp->handle($request->withMethod('GET'), null, false)->getBody());

        $handlingApp = clone $app;
        $handlingApp->post('/', $action);
        $this->assertEquals('<h1>It works!</h1>', $handlingApp->handle($request->withMethod('POST'), null, false)->getBody());

        $handlingApp = clone $app;
        $handlingApp->put('/', $action);
        $this->assertEquals('<h1>It works!</h1>', $handlingApp->handle($request->withMethod('PUT'), null, false)->getBody());

        $handlingApp = clone $app;
        $handlingApp->delete('/', $action);
        $this->assertEquals('<h1>It works!</h1>', $handlingApp->handle($request->withMethod('DELETE'), null, false)->getBody());

        $handlingApp = clone $app;
        $handlingApp->patch('/', $action);
        $this->assertEquals('<h1>It works!</h1>', $handlingApp->handle($request->withMethod('PATCH'), null, false)->getBody());

        $handlingApp = clone $app;
        $handlingApp->head('/', $action);
        $this->assertEquals('<h1>It works!</h1>', $handlingApp->handle($request->withMethod('HEAD'), null, false)->getBody());
    }

    /**
     * Test handle controller action
     */
    public function testHandleControllerAction()
    {
        $app = new Application();

        $action = [TestController::class, 'getIndex'];

        $app->get('/', $action);

        $request = ServerRequestFactory::fromGlobals();

        $response = $app->handle($request->withMethod('GET'), null, true);

        $content = $response->getBody()->__toString();
        $this->assertEquals('getIndex', $content);
    }

    /**
     * test handle auto wiring of controller action
     */
    public function testHandleAutoWiringControllerAction()
    {
        $app = new Application();
        $action = SharedTestController::class . '::getIndex';

        $app->get('/', $action);

        $request = ServerRequestFactory::fromGlobals();

        $response = $app->handle($request->withMethod('GET'), null, false);

        $content = $response->getBody();
        $this->assertEquals($app->getConfig('customValueFromController'), $content);
    }

    /**
     *
     */
    public function testHandleWithOtherException()
    {
        $app = new Application();

        $request = ServerRequestFactory::fromGlobals();

        $app->addListener($app::EVENT_REQUEST_RECEIVED, function ($event) {
            throw new \Exception('A test exception');
        });

        $response = $app->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
    }

    /**
     *
     */
    public function testExceptionHandling()
    {
        $app = new Application();
        $app->setConfig('error', false);

        $request = ServerRequestFactory::fromGlobals();

        $app->addListener($app::EVENT_RUNTIME_ERROR, function ($event, $exception) use ($app) {
            $this->assertInstanceOf(\Exception::class, $exception);
        });

        $app->addListener($app::EVENT_LIFECYCLE_ERROR, function (ApplicationEvent $event, $exception) use ($app) {
            $event->getErrorResponse()->getBody()->write('Fail');
        });

        $response = $app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
        $toString = $response->getBody()->__toString();
        $this->assertEquals('Fail', $toString);
    }

    /**
     *
     */
    public function testNotFoundException()
    {
        $app = new Application();
        $request = ServerRequestFactory::fromGlobals();
        $response = $app->handle($request, null, false);

        $this->assertEquals(404, $response->getStatusCode());
    }


    /**
     *
     */
    public function testCustomEvents()
    {
        $app = new Application();

        $time = null;
        $app->addListener('custom.event', function ($event, $args) use (&$time) {
            $time = $args;
        });

        $app->getEventEmitter()->emit('custom.event', time());
        $this->assertTrue($time !== null);
    }

    /**
     *
     */
    public function testSetResponseEmitter()
    {
        $app = new Application();

        $app->get('/', function ($request, ResponseInterface $response) {
            $response->getBody()->write('<h1>It works!</h1>');
            return $response;
        });

        $app->getContainer()->add(EmitterInterface::class, new SapiStreamEmitter());

        $app->addListener($app::EVENT_REQUEST_RECEIVED, function (ApplicationEvent $event) {
            $this->assertInstanceOf(ApplicationEvent::class, $event);
            $this->assertInstanceOf(ServerRequestInterface::class, $event->getRequest());
        });

        $requestResponseCallback = function (ApplicationEvent $event) {
            $this->assertInstanceOf(ApplicationEvent::class, $event);
            $this->assertInstanceOf(ServerRequestInterface::class, $event->getRequest());
            $this->assertInstanceOf(ResponseInterface::class, $event->getResponse());
        };

        $app->addListener($app::EVENT_RESPONSE_CREATED, $requestResponseCallback);
        $app->addListener($app::EVENT_RESPONSE_SENT, $requestResponseCallback);
        $app->addListener($app::EVENT_LIFECYCLE_COMPLETE, $requestResponseCallback);

        ob_start();
        $app->run(ServerRequestFactory::fromGlobals());
        ob_end_clean();
    }

    /**
     *
     */
    public function testRun()
    {
        $app = new Application();

        $app->get('/', function ($request, ResponseInterface $response) {
            $response->getBody()->write('<h1>It works!</h1>');
            return $response;
        });

        $app->addListener('request.received', function (ApplicationEvent $event) {
            $this->assertInstanceOf(ApplicationEvent::class, $event);
            $this->assertInstanceOf(ServerRequestInterface::class, $event->getRequest());
        });
        $app->addListener('response.sent', function (ApplicationEvent $event) {
            $this->assertInstanceOf(ApplicationEvent::class, $event);
            $this->assertInstanceOf(ServerRequestInterface::class, $event->getRequest());
            $this->assertInstanceOf(ResponseInterface::class, $event->getResponse());
        });

        ob_start();
        $app->run();
        ob_get_clean();
    }

    /**
     *
     */
    public function testEnvironment()
    {
        $app = new Application();
        $this->assertFalse($app->isHttpRequest());
        $this->assertFalse($app->isAjaxRequest());
        $this->assertTrue($app->isCli());
    }

    /**
     *
     */
    public function testContainerHasNotClass()
    {
        $app = new Application();

        $this->assertFalse($app->getContainer()->has(\SplMaxHeap::class), 'Should not assert true, when class exists but is not part of container');
    }

    /**
     *
     */
    public function testContentTypeDelegation()
    {
        $app = new Application();
        $app->get('/', function ($request, ResponseInterface $response) {
            $response->getBody()->write('<h1>It works!</h1>');
            return $response;
        });

        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $request = ServerRequestFactory::fromGlobals();

        $response = $app->handle($request);

        $this->assertEquals($request->getHeader('content-type'), $response->getHeader('content-type'));
        $this->assertEquals(['application/json'], $response->getHeader('content-type'));
    }

    public function testGetLoggers()
    {
        $app = new Application();
        $app->getLogger()->info('Hello there');
        $app->getLogger('another')->info('Hallo!');

        $this->assertTrue(in_array('default', $app->getLoggerChannels()));
        $this->assertTrue(in_array('another', $app->getLoggerChannels()));
        $this->assertFalse(in_array('nope', $app->getLoggerChannels()));

    }

}
