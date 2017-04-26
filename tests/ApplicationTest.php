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

use Hawkbit\Application;
use League\Container\Container;
use League\Event\Emitter;
use League\Route\RouteCollection;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Hawkbit\Application\HttpApplicationEvent;
use Hawkbit\Tests\TestAsset\SharedTestController;
use Hawkbit\Tests\TestAsset\TestController;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\SapiStreamEmitter;
use Zend\Diactoros\ServerRequestFactory;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    /**
     *
     */
    protected function tearDown()
    {
        restore_error_handler();
        restore_exception_handler();
    }


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

        $app->addListener('request.received', function (HttpApplicationEvent $event) {
            $this->assertInstanceOf(ServerRequestInterface::class, $event->getRequest());
        });

        $this->assertTrue($app->getEmitter()->hasListeners('request.received'));

        $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response, array $args = []) {
            return $response;
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

        $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response, array $args = []) {
            return $response;
        });

        $app->addListener('response.sent', function (HttpApplicationEvent $event) {
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
        $this->assertTrue($app->isError());

        $throwables = $app->getExceptionStack();
        $this->assertTrue(0 < count($throwables));
        $this->assertInstanceOf(\Exception::class, reset($throwables));

    }

    /**
     * This should not throw errors
     */
    public function testHttpErrorHandling()
    {
        $app = new Application();

        $request = ServerRequestFactory::fromGlobals();

        $app->addListener($app::EVENT_SYSTEM_ERROR, function ($event, $exception) use ($app) {
            $this->assertInstanceOf(\Exception::class, $exception);
        });

        $app->addListener($app::EVENT_LIFECYCLE_ERROR, function (HttpApplicationEvent $event, $exception) use ($app) {
            $this->assertInstanceOf(\Exception::class, $exception);
            $event->getErrorResponse()->getBody()->write('Fail');
        });

        $response = $app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
        $toString = $response->getBody()->__toString();
        $this->assertEquals('Fail', $toString);

        $throwables = $app->getExceptionStack();
        $this->assertTrue(0 < count($throwables));
        $this->assertInstanceOf(\Exception::class, reset($throwables));
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
        $throwables = $app->getExceptionStack();
        $this->assertTrue(0 < count($throwables));
        $this->assertInstanceOf(\Exception::class, reset($throwables));
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

        $app->addListener($app::EVENT_REQUEST_RECEIVED, function (HttpApplicationEvent $event) {
            $this->assertInstanceOf(HttpApplicationEvent::class, $event);
            $this->assertInstanceOf(ServerRequestInterface::class, $event->getRequest());
        });

        $requestResponseCallback = function (HttpApplicationEvent $event) {
            $this->assertInstanceOf(HttpApplicationEvent::class, $event);
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

        $app->addListener('request.received', function (HttpApplicationEvent $event) {
            $this->assertInstanceOf(HttpApplicationEvent::class, $event);
            $this->assertInstanceOf(ServerRequestInterface::class, $event->getRequest());
        });
        $app->addListener('response.sent', function (HttpApplicationEvent $event) {
            $this->assertInstanceOf(HttpApplicationEvent::class, $event);
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
        unset($_SERVER['CONTENT_TYPE']);

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

    public function testApplicationMiddlewareOnError()
    {
        $app = new Application();
        $handledOnError = false;
        $app->addMiddleware(function (ServerRequestInterface $request, ResponseInterface $response, callable $next) use (&$handledOnError) {
            $handledOnError = true;
            return $response;
        });

        $app->handle(ServerRequestFactory::fromGlobals());

        $this->assertTrue($handledOnError);

    }

    public function testApplicationMiddleware()
    {
        $app = new Application();
        $handled = false;
        $app->get('/', [TestController::class, 'getIndex']);
        $app->addMiddleware(function (ServerRequestInterface $request, ResponseInterface $response, callable $next) use (&$handled) {
            $handled = true;
            $response->getBody()->write('<');
            /** @var ResponseInterface $response */
            $response = $next($request, $response);
            $response->getBody()->write('>');
            return $response;
        });

        $response = $app->handle(ServerRequestFactory::fromGlobals());

        $this->assertTrue($handled);
        $this->assertEquals('<getIndex>', $response->getBody()->__toString());

    }

    public function testErrorHandler()
    {
        $app = new Application();

        $response = $app->handle(ServerRequestFactory::fromGlobals());
        $app->shutdown($response);

        $this->assertTrue($app->isError());
        $this->assertInstanceOf('\League\Route\Http\Exception\NotFoundException', $app->getLastException());
    }

    public function testAjaxRequestRecogniseHTMLAutomatically()
    {

        $app = new Application();
        $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write('<h1>HELLO</h1>');
            return $response;
        });

        // emulate http request from basic app
        $_SERVER['CONTENT_TYPE'] = 'text/html';

        // emulate xhr request
        $_SERVER['X_REQUESTED_WITH'] = 'xmlhttprequest';

        $serverRequest = ServerRequestFactory::fromGlobals();

        unset($_SERVER['CONTENT_TYPE']);
        unset($_SERVER['X_REQUESTED_WITH']);

        $response = $app->handle($serverRequest);

        $this->assertEquals('<h1>HELLO</h1>', $response->getBody()->__toString());
        $this->assertEquals('text/html', $app->getContentType());
    }

    public function testAjaxRequestRecogniseHTML()
    {

        $app = new Application();
        $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
            return new JsonResponse(['title' => 'HELLO']);
        });

        $serverRequest = ServerRequestFactory::fromGlobals();

        // set xhr header
        $serverRequest = $serverRequest
            ->withHeader('x-requested-with', 'xmlhttprequest')
            // force content type to be json
            ->withHeader('content-type', 'application/json');

        $response = $app->handle($serverRequest);

        $this->assertEquals('{"title":"HELLO"}', $response->getBody()->__toString());
        $this->assertEquals('application/json', $app->getContentType());
    }

    public function testRootRequestWithoutPublicAsDocumentRoot(){
        $app = new Application();
        $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write('<h1>HELLO</h1>');
            return $response;
        });

        //Fake globals
        $_SERVER['PHP_SELF'] = '/someProject/public/index.php';
        $_SERVER['REQUEST_URI'] = '/someProject/public/';

        $response = $app->handle($app->getRequest());
        $this->assertEquals('<h1>HELLO</h1>', $response->getBody()->__toString());
        $this->assertEquals('text/html', $app->getContentType());
    }
    public function testExampleRequestWithoutPublicAsDocumentRoot(){
        $app = new Application();
        $app->get('/test', function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write('<h1>HELLO</h1>');
            return $response;
        });

        //Fake globals
        $_SERVER['PHP_SELF'] = '/someProject/public/index.php';
        $_SERVER['REQUEST_URI'] = '/someProject/public/test';

        $response = $app->handle($app->getRequest());
        $this->assertEquals('<h1>HELLO</h1>', $response->getBody()->__toString());
        $this->assertEquals('text/html', $app->getContentType());
    }
}
