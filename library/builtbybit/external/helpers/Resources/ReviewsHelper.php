<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** A helper class for review-related API endpoints. */
class ReviewsHelper {
    /** @var APIWrapper The current wrapper instance in use. */
    private $wrapper;

    /**
	 * Construct a new reviews helper from a wrapper instance.
	 *
	 * @param APIWrapper The current wrapper instance in use.
	 */
    function __construct(APIWrapper $wrapper) {
        $this->wrapper = $wrapper;
    }

    /**
	 * List a single page of resource reviews.
	 *
     * @param int The identifier of the resource.
     * @param array An optional associated array of sort options.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function list(int $resource_id, array $sort = []): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/reviews", $resource_id), $sort);
    }

    /**
	 * Fetch a resource review by member.
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the member.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function fetchByMember(int $resource_id, int $member_id): APIResponse {
        return $this->wrapper->get(sprintf("resources/%d/reviews/members/%d", $resource_id, $member_id));
    }

    /**
	 * Respond to a resource review.
	 *
     * @param int The identifier of the resource.
     * @param int The identifier of the review.
     * @param string The text content of the response message.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function respond(int $resource_id, int $review_id, string $response): APIResponse {
        $body = ["response" => $response];
        return $this->wrapper->patch(sprintf("resources/%d/reviews/%d", $resource_id, $review_id), $body);
    }
}