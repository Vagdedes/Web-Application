<?php

use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;

function get_react_http(
    LoopInterface $loop,
    string        $url,
    string        $type,
    array         $headers = [],
    mixed         $body = null
): PromiseInterface
{
    $browser = new Browser($loop);
    $method = strtolower($type);

    if (is_array($body)
        && array_key_exists('Content-Type', $headers)
        && str_contains($headers['Content-Type'], 'multipart/form-data')) {
        $boundary = uniqid('boundary_');
        $bodyContent = '';

        foreach ($body as $key => $value) {
            if ($value instanceof CURLFile) {
                $filePath = $value->getFilename();
                $fileName = basename($filePath);
                $fileContent = file_get_contents($filePath);

                $bodyContent .= "--$boundary\r\n";
                $bodyContent .= "Content-Disposition: form-data; name=\"$key\"; filename=\"$fileName\"\r\n";
                $bodyContent .= "Content-Type: application/octet-stream\r\n\r\n";
                $bodyContent .= $fileContent . "\r\n";
            } elseif (is_array($value)) {
                foreach ($value as $file) {
                    if (file_exists($file)) {
                        $bodyContent .= "--$boundary\r\n";
                        $bodyContent .= "Content-Disposition: form-data; name=\"$key\"; filename=\"" . basename($file) . "\"\r\n";
                        $bodyContent .= "Content-Type: application/octet-stream\r\n\r\n";
                        $bodyContent .= file_get_contents($file) . "\r\n";
                    }
                }
            } else {
                $bodyContent .= "--$boundary\r\n";
                $bodyContent .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n";
                $bodyContent .= $value . "\r\n";
            }
        }

        $bodyContent .= "--$boundary--\r\n";
        $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $bodyContent);
        rewind($stream);

        $body = $stream;
    }
    return $browser->$method($url, $headers, $body)->then(
        function (ResponseInterface $response) {
            return $response->getBody()->getContents();
        },
        function (Throwable $e) {
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
