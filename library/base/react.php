<?php

use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

function get_react_http(
    ?LoopInterface $loop,
    string         $url,
    string         $type,
    array          $headers = [],
    mixed          $arguments = null
): PromiseInterface
{
    if ($loop === null) {
        $loop = Loop::get();
    }
    $browser = new Browser($loop);

    $method = strtolower($type);
    $body = $arguments !== null
        ? json_encode($arguments)
        : null;

    return $browser->$method($url, $headers, $body)->then(
        function (ResponseInterface $response) {
            return (string)$response->getBody();
        },
        function (Exception $e) {
            return null;
        }
    );

}
