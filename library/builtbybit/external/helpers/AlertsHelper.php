<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** A helper class for alert-related API endpoints. */
class AlertsHelper {
    /** @var APIWrapper The current wrapper instance in use. */
    private $wrapper;

    /**
	 * Construct a new alerts helper from a wrapper instance.
	 *
	 * @param APIWrapper The current wrapper instance in use.
	 */
    function __construct(APIWrapper $wrapper) {
        $this->wrapper = $wrapper;
    }

    /**
	 * List a single page of unread alerts.
	 *
     * @param array An optional associated array of sort options.
	 * @return APIResponse The parsed API response.
	 */
    function listUnread(array $sort = []): APIResponse {
        return $this->wrapper->get("alerts", $sort);
    }

    /**
	 * Mark all unread alerts as read.
	 *
	 * @return APIResponse The parsed API response.
	 */
    function markAsRead(): APIResponse {
        return $this->wrapper->patch("alerts", ["read" => true]);
    }
}