<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** A helper class for version-related API endpoints. */
class VersionsHelper {
    /** @var APIWrapper The current wrapper instance in use. */
    private $wrapper;

    /**
	 * Construct a new versions helper from a wrapper instance.
	 *
	 * @param APIWrapper The current wrapper instance in use.
	 */
    function __construct(APIWrapper $wrapper) {
        $this->wrapper = $wrapper;
    }

    /**
	 * List a single page of resource versions.
	 *
     * @param int The identifier of the resource.
     * @param array An optional associated array of sort options.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function list(int $resource_id, array $sort = []): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/versions", $resource_id), $sort);
    }

    /**
	 * Fetch a resource version.
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the version.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function fetch(int $resource_id, int $version_id): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/versions/%d", $resource_id, $version_id));
    }

    /**
	 * Delete a resource version.
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the version.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function delete(int $resource_id, int $version_id): APIResponse {
        return $this->wrapper->delete(sprintf("resources/%d/versions/%d", $resource_id, $version_id));
    }

    /**
	 * Fetch the latest resource version.
	 *
     * @param int The identifier of the resource.
	 * @return APIResponse The parsed API response.
	 */
    function latest(int $resource_id): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/versions/latest", $resource_id));
    }
}