<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** A helper class for resource-related API endpoints. */
class ResourcesHelper {
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
	 * List a single page of resources.
	 *
     * @param array An optional associated array of sort options.
	 * @return APIResponse The parsed API response.
	 */
    function list(array $sort = []): APIResponse {
        return $this->wrapper->get("resources", $sort);
    }

    /**
	 * List a single page of resources you own.
	 *
     * @param array An optional associated array of sort options.
	 * @return APIResponse The parsed API response.
	 */
    function listOwned(array $sort = []): APIResponse {
        return $this->wrapper->get("resources/owned", $sort);
    }

    /**
	 * List a single page of resources you collaborate on.
	 *
     * @param array An optional associated array of sort options.
	 * @return APIResponse The parsed API response.
	 */
    function listCollaborated(array $sort = []): APIResponse {
        return $this->wrapper->get("resources/collaborated", $sort);
    }

    /**
	 * Fetch information about a resource.
	 *
     * @param int The identifier of the resource.
	 * @return APIResponse The parsed API response.
	 */
    function fetch(int $resource_id): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d", $resource_id));
    }

    /** 
     * Construct and return a licenses helper instance.
	 *
     * @return LicensesHelper The constructed licenses helper.
	 */
    function licenses(): LicensesHelper {
        return new LicensesHelper($this->wrapper);
    }

    /** 
     * Construct and return a purchases helper instance.
	 *
     * @return PurchasesHelper The constructed purchases helper.
	 */
    function purchases(): PurchasesHelper {
        return new PurchasesHelper($this->wrapper);
    }

    /** 
     * Construct and return a downloads helper instance.
	 *
     * @return DownloadsHelper The constructed downloads helper.
	 */
    function downloads(): DownloadsHelper {
        return new DownloadsHelper($this->wrapper);
    }

    /** 
     * Construct and return a versions helper instance.
	 *
     * @return VersionsHelper The constructed versions helper.
	 */
    function versions(): VersionsHelper {
        return new VersionsHelper($this->wrapper);
    }

    /** 
     * Construct and return an updates helper instance.
	 *
     * @return UpdatesHelper The constructed updates helper.
	 */
    function updates(): UpdatesHelper {
        return new UpdatesHelper($this->wrapper);
    }

    /** 
     * Construct and return a reviews helper instance.
	 *
     * @return ReviewsHelper The constructed reviews helper.
	 */
    function reviews(): ReviewsHelper {
        return new ReviewsHelper($this->wrapper);
    }
}