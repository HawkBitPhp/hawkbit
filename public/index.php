<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__.'/../vendor/autoload.php';

$app = new Hawkbit\Application();

$app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
    $response->getBody()->write('<h1>Hello, World!</h1>');

    return $response;
});

$app->run();