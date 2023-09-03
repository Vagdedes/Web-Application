<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** Represents a parsed response from BuiltByBit's API. */
class APIResponse {
    /** @var string A string representing whether this response was successful or not. */
    private $result;

    /** @var mixed The data of this response if present. */
    private $data;

    /** @var mixed The error of this response if present. */
    private $error;


    /**
	 * Constructs a new response from the result, and data or error.
     * 
     * @param string The result, either "success" or "error".
     * @param mixed The data if present.
     * @param mixed The error if present.
	 */
    function __construct(string $result, $data, $error) {
        $this->result = $result;
        $this->data = $data;
        $this->error = $error;
    }
    
    /**
	 * Returns whether or not this response was successful.
     * 
     * @return bool Whether or not this response was successful.
	 */
    function isSuccess(): bool {
        return $this->result === "success";
    }

    /**
	 * Returns the data of this response.
     * 
     * @return mixed The response data, or null if it was errored.
	 */
    function getData() {
        return $this->data;
    }
    
    /**
	 * Returns whether or not this response was errored.
     * 
     * @return bool Whether or not this response was errored.
	 */
    function isError(): bool {
        return $this->result === "error";
    }

    /**
	 * Returns the error of this response.
     * 
     * @return mixed The response error, or null if it was successful.
	 */
    function getError() {
        return $this->error;
    }

    /**
	 * Parse a JSON response and construct a new APIResponse from it.
     * 
     * @param string The raw JSON response.
     * @return APIResponse The newly construct response instance.
	 */
    static function from_json(string $json): Self {
        $data = json_decode($json, true);

        if (!array_key_exists("data", $data)) {
            $data["data"] = NULL;
        }
        if (!array_key_exists("error", $data)) {
            $data["error"] = NULL;
        }

        return new APIResponse($data["result"], $data["data"], $data["error"]);
    }
}