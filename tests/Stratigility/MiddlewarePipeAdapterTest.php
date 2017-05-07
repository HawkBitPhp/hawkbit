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

namespace Hawkbit\Tests\Stratigility;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Hawkbit\Application;
use Hawkbit\Stratigility\MiddlewarePipeAdapter;
use Zend\Diactoros\ServerRequestFactory;

class MiddlewareAdapterTest extends \PHPUnit_Framework_TestCase
{


    public function testFunctionalPiping()
    {
        $handleRoute = false;
        $handleMiddleware = false;
        $application = new Application();
        $testCase = $this;
        $application->get('/', function ($request, ResponseInterface $response) use(&$handleRoute, $testCase) {
            $testCase->assertInstanceOf(ServerRequestInterface::class, $request);
            $testCase->assertInstanceOf(ResponseInterface::class, $response);
            $response->getBody()->write('Hello World');
            $handleRoute = true;
            return $response;
        });
        $middleware = new MiddlewarePipeAdapter($application);

        $middleware->pipe('/', function ($request, ResponseInterface $response, $next) use(&$handleMiddleware) {
            $this->assertInstanceOf(ServerRequestInterface::class, $request);
            $this->assertInstanceOf(ResponseInterface::class, $response);
            $this->assertTrue(is_callable($next));

            $response->getBody()->write('<h1>');
            /** @var ResponseInterface $response */
            $response = $next($request, $response);
            $response->getBody()->write('</h1>');

            $handleMiddleware = true;

            return $response;
        });

        $response = $middleware(ServerRequestFactory::fromGlobals(), $application->getResponse());

        $this->assertEquals('<h1>Hello World</h1>', $response->getBody()->__toString());
        $this->assertTrue($handleMiddleware);
        $this->assertTrue($handleRoute);

    }

    public function testNotFoundException()
    {
        $application = new Application();
        $application->setConfig($application::KEY_ERROR_CATCH, false);
        $middleware = new MiddlewarePipeAdapter($application);

        $response = $middleware->__invoke(ServerRequestFactory::fromGlobals(), $middleware->getApplication()->getResponse());
        $this->assertEquals(404, $response->getStatusCode());
    }
}
