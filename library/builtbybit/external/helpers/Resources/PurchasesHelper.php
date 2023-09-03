<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** A helper class for purchase-related API endpoints. */
class PurchasesHelper {
    /** @var APIWrapper The current wrapper instance in use. */
    private $wrapper;

    /**
	 * Construct a new purchases helper from a wrapper instance.
	 *
	 * @param APIWrapper The current wrapper instance in use.
	 */
    function __construct(APIWrapper $wrapper) {
        $this->wrapper = $wrapper;
    }

    /**
	 * List a single page of resource purchases.
	 *
     * @param int The identifier of the resource.
     * @param array An optional associated array of sort options.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function list(int $resource_id, array $sort = []): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/purchases", $resource_id), $sort);
    }

    /**
	 * Fetch a resource purchase.
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the purchase.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function fetch(int $resource_id, int $purchase_id): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/purchases/%d", $resource_id, $purchase_id));
    }
}