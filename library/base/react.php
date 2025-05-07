<?php

use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;

function get_react_http(
    ?LoopInterface $loop,
    string         $url,
    string         $type,
    array          $headers = [],
    mixed          $arguments = null
): PromiseInterface
{
    $browser = new Browser($loop);

    $method = strtolower($type);
    $body = $arguments !== null
        ? json_encode($arguments)
        : "";

    return $browser->$method($url, $headers, $body)->then(
        function (ResponseInterface $response) {
            try {
                return $response->getBody()->getContents();
            } catch (Throwable $e) {
                return null;
            }
        },
        function (Throwable $e) {
            if (class_exists("BigManageError")) {
                BigManageError::debug(2);
                BigManageError::debug($e->getMessage());
                BigManageError::debug($e->getTraceAsString());
            }
            return null;
        }
    );
}

function get_curl_multi_with_react(
    LoopInterface $loop,
    string        $url,
    string        $method,
    array         $headers,
    mixed         $body,
    callable      $onComplete
): void
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $mh = curl_multi_init();
    curl_multi_add_handle($mh, $ch);

    $running = 1;
    curl_multi_exec($mh, $running);  // Kick off the request

    // Create a timer to periodically check the status of the multi-handle
    $loop->addPeriodicTimer(0.01, function (TimerInterface $timer)
    use (&$running, $mh, $ch, $onComplete, $loop) {
        // Non-blocking check for cURL activity (allows event loop to continue)
        curl_multi_select($mh, 0.1); // Wait up to 0.1 seconds for activity

        do {
            // Perform any outstanding cURL operations
            $status = curl_multi_exec($mh, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        if ($running === 0) {
            // All requests have completed

            // Get the response data
            $response = curl_multi_getcontent($ch);
            $info = curl_getinfo($ch);
            $error = curl_error($ch);

            // Cleanup
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            curl_multi_close($mh);

            // Stop the timer and return the response
            $loop->cancelTimer($timer);

            // Callback with the results
            $onComplete(
                $response,
                $info,
                $error
            );
        }
    });
}
