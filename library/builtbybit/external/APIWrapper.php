<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

require __DIR__ . "/APIToken.php";
require __DIR__ . "/APIResponse.php";
require __DIR__ . "/Throttler.php";

require __DIR__ . "/helpers/AlertsHelper.php";
require __DIR__ . "/helpers/ConversationsHelper.php";
require __DIR__ . "/helpers/MembersHelper.php";
require __DIR__ . "/helpers/ThreadsHelper.php";

require __DIR__ . "/helpers/Resources/ResourcesHelper.php";
require __DIR__ . "/helpers/Resources/LicensesHelper.php";
require __DIR__ . "/helpers/Resources/PurchasesHelper.php";
require __DIR__ . "/helpers/Resources/DownloadsHelper.php";
require __DIR__ . "/helpers/Resources/VersionsHelper.php";
require __DIR__ . "/helpers/Resources/UpdatesHelper.php";
require __DIR__ . "/helpers/Resources/ReviewsHelper.php";

/** The primary class for interactions with BuiltByBit's API. */
class APIWrapper
{
    /** @var string The base URL of BuiltByBit's API that we prepend to endpoints. */
    const BASE_URL = "https://api.builtbybit.com/v1";

    /** @var string The complete header line for request bodies (JSON). */
    const CONTENT_TYPE_HEADER = "Content-Type: application/json";

    /** @var string The the number of entities returned per page for paginated endpoints. */
    const PER_PAGE = 20;


    /** @var CurlHandle The current CURL instance being used within this wrapper. */
    private $http;

    /** @var Throttler The current throttler instance being used within this wrapper. */
    private $throttler;

    /** @var APIToken The pre-constructed API token passed to this wrapper during initilisation. */
    private $token;


    /**
     * Initialises this wrapper with a provided API token, and runs a health check if requested.
     *
     * @param APIToken The pre-constructed API token.
     * @param bool Whether or not to run a health check.
     * @return APIResponse The parsed response of the request to `health`.
     */
    function initialise(APIToken $token, bool $health): APIResponse
    {
        $this->token = $token;
        $this->http = curl_init();
        $this->throttler = new Throttler();

        curl_setopt($this->http, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->http, CURLOPT_HEADER, 1);

        if ($health) {
            return $this->health();
        } else {
            return ["result" => "success"];
        }
    }

    /**
     * Schedules a GET request to a specific endpoint and stalls if we've previously hit a rate limit.
     *
     * @param string The path of the endpoint.
     * @param array An optional associated array of sort options.
     * @return APIResponse The parsed response.
     */
    function get(string $endpoint, array $sort = []): APIResponse
    {
        $this->stallUntilCanMakeRequest(RequestType::READ);

        $url = sprintf("%s/%s?%s", APIWrapper::BASE_URL, $endpoint, http_build_query($sort));

        curl_setopt($this->http, CURLOPT_HTTPGET, true);
        curl_setopt($this->http, CURLOPT_URL, $url);
        curl_setopt($this->http, CURLOPT_HTTPHEADER, array($this->token->asHeader()));

        if ($body = $this->handleResponse(RequestType::READ)) {
            return APIResponse::from_json($body);
        } else {
            return $this->get($endpoint, $sort);
        }
    }

    /**
     * Schedules a PATCH request to a specific endpoint and stalls if we've previously hit a rate limit.
     *
     * @param string The path of the endpoint.
     * @param mixed The body of the request which will be serialised into JSON.
     * @return APIResponse The parsed response.
     */
    function patch(string $endpoint, mixed $body): APIResponse
    {
        $this->stallUntilCanMakeRequest(RequestType::WRITE);

        curl_setopt($this->http, CURLOPT_HTTPGET, true);
        curl_setopt($this->http, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($this->http, CURLOPT_URL, sprintf("%s/%s", APIWrapper::BASE_URL, $endpoint));
        curl_setopt($this->http, CURLOPT_HTTPHEADER, [$this->token->asHeader(), APIWrapper::CONTENT_TYPE_HEADER]);
        curl_setopt($this->http, CURLOPT_POSTFIELDS, json_encode($body));

        if ($body = $this->handleResponse(RequestType::WRITE)) {
            return APIResponse::from_json($body);
        } else {
            return $this->patch($endpoint, $body);
        }
    }

    /**
     * Schedules a POST request to a specific endpoint and stalls if we've previously hit a rate limit.
     *
     * @param string The path of the endpoint.
     * @param mixed The body of the request which will be serialised into JSON.
     * @return APIResponse The parsed response.
     */
    function post(string $endpoint, mixed $body): APIResponse
    {
        $this->stallUntilCanMakeRequest(RequestType::WRITE);

        curl_setopt($this->http, CURLOPT_HTTPGET, true);
        curl_setopt($this->http, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->http, CURLOPT_URL, sprintf("%s/%s", APIWrapper::BASE_URL, $endpoint));
        curl_setopt($this->http, CURLOPT_HTTPHEADER, [$this->token->asHeader(), APIWrapper::CONTENT_TYPE_HEADER]);
        curl_setopt($this->http, CURLOPT_POSTFIELDS, json_encode($body));

        if ($body = $this->handleResponse(RequestType::WRITE)) {
            return APIResponse::from_json($body);
        } else {
            return $this->post($endpoint, $body);
        }
    }

    /**
     * Schedules a DELETE request to a specific endpoint and stalls if we've previously hit a rate limit.
     *
     * @param string The path of the endpoint.
     * @return APIResponse The parsed response.
     */
    function delete(string $endpoint): APIResponse
    {
        $this->stallUntilCanMakeRequest(RequestType::WRITE);

        curl_setopt($this->http, CURLOPT_HTTPGET, true);
        curl_setopt($this->http, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($this->http, CURLOPT_URL, sprintf("%s/%s", APIWrapper::BASE_URL, $endpoint));
        curl_setopt($this->http, CURLOPT_HTTPHEADER, array($this->token->asHeader()));

        if ($body = $this->handleResponse(RequestType::WRITE)) {
            return APIResponse::from_json($body);
        } else {
            return $this->delete($endpoint);
        }
    }

    /**
     * Handles a CURL response and sets/resets local rate limiting metadata.
     *
     * @param int The type of request which the response originated from (RequestType).
     * @return string The raw JSON response or null if a rate limit was hit.
     */
    private function handleResponse(int $type): ?string
    {
        list($header, $body) = explode("\r\n\r\n", curl_exec($this->http), 2);
        $status = curl_getinfo($this->http, CURLINFO_HTTP_CODE);
        $header = APIWrapper::parseHeaders(explode("\r\n", $header));
        $timeByVagdedes = 1000;

        if ($status === 429 && $type === RequestType::READ) {
            //$this->throttler->setRead(intval($header["Retry-After"]));
            $this->throttler->setRead($timeByVagdedes);
            return null;
        } else if ($status === 429 && $type === RequestType::WRITE) {
            //$this->throttler->setWrite(intval($header["Retry-After"]));
            $this->throttler->setWrite($timeByVagdedes);
            return null;
        }

        if ($type = RequestType::READ) {
            $this->throttler->resetRead();
        } else if ($type = RequestType::WRITE) {
            $this->throttler->resetWrite();
        }

        return $body;
    }

    /**
     * Converts raw header lines into an associated array of key/value pairs.
     *
     * @param array An array of raw header lines.
     * @return string An associated array of header key/value pairs.
     */
    private static function parseHeaders(array $headers): array
    {
        $new = [];

        foreach ($headers as $header) {
            $split = explode(":", $header, 2);
            if (count($split) === 2) {
                $new[$split[0]] = $split[1];
            }
        }

        return $new;
    }

    /**
     * Sleep until we no longer need to stall a request.
     *
     * @param int The type of request which the response originated from (RequestType).
     */
    private function stallUntilCanMakeRequest(int $type)
    {
        while ($stall_for = $this->throttler->stallFor($type)) {
            usleep($stall_for * 1000);
        }
    }

    /**
     * Schedule an empty request which we expect to always succeed under nominal conditions.
     *
     * @return APIResponse The parsed response.
     */
    function health(): APIResponse
    {
        return $this->get("health");
    }

    /**
     * Schedule an empty request and measure how long the API took to respond.
     *
     * This duration may not be representative of the raw request latency due to the fact that requests may be stalled
     * locally within this wrapper to ensure compliance with rate limiting rules. Whilst this is a trade-off, it can
     * be argued that the returned duration will be more representative of the true latencies experienced.
     *
     * @return APIResponse The parsed response with the data field overriden by the response time in milliseconds.
     */
    function ping(): APIResponse
    {
        $start = microtime(true);
        $res = $this->health();
        $end = microtime(true);

        if ($res->getData()) {
            return new APIResponse("success", ($end - $start) * 1000, null);
        } else {
            return $res;
        }
    }

    /**
     * Construct and return an alerts helper instance.
     *
     * @return AlertsHelper The constructed alerts helper.
     */
    function alerts(): AlertsHelper
    {
        return new AlertsHelper($this);
    }

    /**
     * Construct and return a conversations helper instance.
     *
     * @return ConversationsHelper The constructed conversations helper.
     */
    function conversations(): ConversationsHelper
    {
        return new ConversationsHelper($this);
    }

    /**
     * Construct and return a members helper instance.
     *
     * @return MembersHelper The constructed members helper.
     */
    function members(): MembersHelper
    {
        return new MembersHelper($this);
    }

    /**
     * Construct and return a threads helper instance.
     *
     * @return ThreadsHelper The constructed threads helper.
     */
    function threads(): ThreadsHelper
    {
        return new ThreadsHelper($this);
    }

    /**
     * Construct and return a resources helper instance.
     *
     * @return ResourcesHelper The constructed resources helper.
     */
    function resources(): ResourcesHelper
    {
        return new ResourcesHelper($this);
    }
}