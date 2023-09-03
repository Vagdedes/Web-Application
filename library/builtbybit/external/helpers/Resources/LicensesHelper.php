<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** A helper class for license-related API endpoints. */
class LicensesHelper {
    /** @var APIWrapper The current wrapper instance in use. */
    private $wrapper;

    /**
	 * Construct a new licenses helper from a wrapper instance.
	 *
	 * @param APIWrapper The current wrapper instance in use.
	 */
    function __construct(APIWrapper $wrapper) {
        $this->wrapper = $wrapper;
    }

    /**
	 * List a single page of resource licenses.
	 *
     * @param int The identifier of the resource.
     * @param array An optional associated array of sort options.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function list(int $resource_id, array $sort = []): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/licenses", $resource_id), $sort);
    }

    /**
	 * Fetch a resource license.
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the license.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function fetch(int $resource_id, int $license_id): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/licenses/%d", $resource_id, $license_id));
    }

    /**
	 * Fetch a resource license by member.
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the member.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function fetchByMember(int $resource_id, int $member_id): APIResponse {
        // TODO: Add query parameters for nonce/timestamp.
        return $this->wrapper->get(sprintf("resources/%d/licenses/member/%d", $resource_id, $member_id));
    }

    /**
	 * Modify a permanent license (and convert to permanent if currently temporary).
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the license.
     * @param bool Whether or not the license should be active, or null if unchanged.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function modifyPermanent(int $resource_id, int $license_id, bool $active): APIResponse {
        $body = [
            "permanent" => false,
            "active" => $active
        ];

        return $this->wrapper->patch(sprintf("resources/%d/licenses/%d", $resource_id, $license_id), $body);
    }

    /**
	 * Modify a temporary license (and convert to temporary if currently permanent).
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the license.
     * @param int The start date of the license as a UNIX timestamp, or null if unchanged.
     * @param int The end date of the license as a UNIX timestamp, or null if unchanged.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function modifyTemporary(int $resource_id, int $license_id, int $start_date, int $end_date): APIResponse {
        $body = [
            "permanent" => false,
            "start_date" => $start_date,
            "end_date" => $end_date,
        ];

        return $this->wrapper->patch(sprintf("resources/%d/licenses/%d", $resource_id, $license_id), $body);
    }
}