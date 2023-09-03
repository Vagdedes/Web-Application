<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** A helper class for member-related API endpoints. */
class MembersHelper {
    /** @var APIWrapper The current wrapper instance in use. */
    private $wrapper;

    /**
	 * Construct a new members helper from a wrapper instance.
	 *
	 * @param APIWrapper The current wrapper instance in use.
	 */
    function __construct(APIWrapper $wrapper) {
        $this->wrapper = $wrapper;
    }

    /**
	 * Fetch a member by their identifier.
	 *
     * @param int The identifier of the member.
	 * @return APIResponse The parsed API response.
	 */
    function fetch(int $member_id): APIResponse {
        return $this->wrapper->get(sprintf("members/%d", $member_id));
    }

    /**
	 * Fetch a member by their username.
	 *
     * @param string The username of the member.
	 * @return APIResponse The parsed API response.
	 */
    function fetchByName(string $username): APIResponse {
        return $this->wrapper->get(sprintf("members/usernames/%s", $username));
    }

    /**
	 * Fetch a member by their Discord identifier.
	 *
     * @param string The identifier of the Discord account.
	 * @return APIResponse The parsed API response.
	 */
    function fetchByDiscord(int $discordId): APIResponse {
        return $this->wrapper->get(sprintf("members/discords/%d", $discordId));
    }

    /**
	 * Fetch information about yourself.
	 *
	 * @return APIResponse The parsed API response.
	 */
    function fetchSelf(): APIResponse {
        return $this->wrapper->get("members/self");
    }

    /**
	 * Modify information about yourself.
	 *
     * @param string The text content of a new custom title, or null if unchanged.
     * @param string The text content of a new about me, or null if unchanged.
     * @param string The text content of a signature, or null if unchanged.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function modifySelf(string $custom_title, string $about_me, string $signature): APIResponse {
        $body = ["custom_tile" => $custom_title, "about_me" => $about_me, "signature" => $signature];
        return $this->wrapper->patch("members/self", $body);
    }

    /**
	 * List recent member bans.
	 *
	 * @return APIResponse The parsed API response.
	 */
    function listRecentBans(): APIResponse {
        return $this->wrapper->get("members/bans");
    }

    /**
	 * List a single page of profile posts on your own profile.
	 *
     * @param array An optional associated array of sort options.
	 * @return APIResponse The parsed API response.
	 */
    function listProfilePosts(array $sort = []): APIResponse {
        return $this->wrapper->get("members/self/profile-posts", $sort);
    }

    /**
	 * Fetch a profile post on your own profile.
	 *
     * @param int The identifier of the profile post.
	 * @return APIResponse The parsed API response.
	 */
    function fetchProfilePost(int $profile_post_id): APIResponse {
        return $this->wrapper->get(sprintf("members/self/profile-posts/%d", $profile_post_id));
    }

    /**
	 * Modify a profile post on your own profile.
	 *
     * @param int The identifier of the profile post.
     * @param string The new text content of the profile post.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function modifyProfilePost(int $profile_post_id, string $message): APIResponse {
        $body =  ["message" => $message];
        return $this->wrapper->patch(sprintf("members/self/profile-posts/%d", $profile_post_id), $body);
    }

    /**
	 * Delete a profile post on your own profile.
	 *
     * @param int The identifier of the profile post.
	 * @return APIResponse The parsed API response.
	 */
    function deleteProfilePost(int $profile_post_id): APIResponse {
        return $this->wrapper->delete(sprintf("members/self/profile-posts/%d", $profile_post_id));
    }
}