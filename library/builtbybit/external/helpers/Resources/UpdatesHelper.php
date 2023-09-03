<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** A helper class for update-related API endpoints. */
class UpdatesHelper {
    /** @var APIWrapper The current wrapper instance in use. */
    private $wrapper;

    /**
	 * Construct a new updates helper from a wrapper instance.
	 *
	 * @param APIWrapper The current wrapper instance in use.
	 */
    function __construct(APIWrapper $wrapper) {
        $this->wrapper = $wrapper;
    }

    /**
	 * List a single page of resource updates.
	 *
     * @param int The identifier of the resource.
     * @param array An optional associated array of sort options.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function list(int $resource_id, array $sort = []): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/updates", $resource_id), $sort);
    }

    /**
	 * Fetch a resource update.
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the update.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function fetch(int $resource_id, int $update_id): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/updates/%d", $resource_id, $update_id));
    }

    /**
	 * Delete a resource update.
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the update.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function delete(int $resource_id, int $update_id): APIResponse {
        return $this->wrapper->delete(sprintf("resources/%d/updates/%d", $resource_id, $update_id));
    }

    /**
	 * Fetch the latest resource update.
	 *
     * @param int The identifier of the resource.
	 * @return APIResponse The parsed API response.
	 */
    function latest(int $resource_id): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/updates/latest", $resource_id));
    }
}