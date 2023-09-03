<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** A helper class for download-related API endpoints. */
class DownloadsHelper {
    /** @var APIWrapper The current wrapper instance in use. */
    private $wrapper;

    /**
	 * Construct a new downloads helper from a wrapper instance.
	 *
	 * @param APIWrapper The current wrapper instance in use.
	 */
    function __construct(APIWrapper $wrapper) {
        $this->wrapper = $wrapper;
    }

    /**
	 * List a single page of resource downloads.
	 *
     * @param int The identifier of the resource.
     * @param array An optional associated array of sort options.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function list(int $resource_id, array $sort = []): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/downloads", $resource_id), $sort);
    }

    /**
	 * List a single page of resource downloads by member.
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the member.
     * @param array An optional associated array of sort options.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function listByMember(int $resource_id, int $member_id, array $sort = []): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/downloads/members/%d", $resource_id, $member_id), $sort);
    }

    /**
	 * List a single page of resource downloads by version.
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the version.
     * @param array An optional associated array of sort options.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function listByVersion(int $resource_id, int $version_id, array $sort = []): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/downloads/versions/%d", $resource_id, $version_id), $sort);
    }
}