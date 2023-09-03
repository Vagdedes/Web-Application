<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** A helper class for thread-related API endpoints. */
class ThreadsHelper {
    /** @var APIWrapper The current wrapper instance in use. */
    private $wrapper;

    /**
	 * Construct a new threads helper from a wrapper instance.
	 *
	 * @param APIWrapper The current wrapper instance in use.
	 */
    function __construct(APIWrapper $wrapper) {
        $this->wrapper = $wrapper;
    }

    /**
	 * List a single page of threads you own or collaborate on.
	 *
     * @param array An optional associated array of sort options.
	 * @return APIResponse The parsed API response.
	 */
    function list(array $sort = []): APIResponse {
        return $this->wrapper->get("threads", $sort);
    }

    /**
	 * Fetch a thread you own or collaborate on.
	 *
     * @param int The identifier of the thread.
	 * @return APIResponse The parsed API response.
	 */
    function fetch(int $thread_id): APIResponse {
        return $this->wrapper->get(sprintf("threads/%d", $thread_id));
    }

    /**
	 * List a single page of replies to a thread you own or collaborate on.
     * 
	 * @param int The identifier of the thread.
     * @param array An optional associated array of sort options.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function listReplies(int $thread_id, array $sort = []): APIResponse {
        return $this->wrapper->get(sprintf("threads/%d/replies", $thread_id), $sort);
    }

    /**
	 * Reply to a thread you own or collaborate on.
	 *
     * @param int The identifier of the thread.
     * @param string The text content of the reply message.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function reply(int $thread_id, string $message): APIResponse {
        return $this->wrapper->post(sprintf("threads/%d/replies", $thread_id), ["message" => $message]);
    }
}