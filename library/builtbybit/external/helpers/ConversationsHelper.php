<?php
// Copyright (c) 2021 Harry [Majored] [hello@majored.pw]
// MIT License (https://github.com/Majored/php-bbb-api-wrapper/blob/main/LICENSE)

/** A helper class for conversation-related API endpoints. */
class ConversationsHelper {
    /** @var APIWrapper The current wrapper instance in use. */
    private $wrapper;

    /**
	 * Construct a new conversations helper from a wrapper instance.
	 *
	 * @param APIWrapper The current wrapper instance in use.
	 */
    function __construct(APIWrapper $wrapper) {
        $this->wrapper = $wrapper;
    }

    /**
	 * List a single page of unread conversations.
	 *
     * @param array An optional associated array of sort options.
	 * @return APIResponse The parsed API response.
	 */
    function listUnread(array $sort = []): APIResponse {
        return $this->wrapper->get("conversations", $sort);
    }

    /**
	 * Start a new conversation.
	 *
     * @param string The title of the conversation.
     * @param string The text content of the first conversation message.
     * @param int[] An array of member identifiers.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function start(string $title, string $message, array $recipients): APIResponse {
        $body = ["title" => $title, "message" => $message, "recipient_ids" => $recipients];
        return $this->wrapper->post("conversations", $body);
    }

    /**
	 * List a single page of replies to an unread conversation.
	 *
     * @param int The identifier of the unread conversation.
     * @param array An optional associated array of sort options.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function listReplies(int $conversation_id, array $sort = []): APIResponse {
        return $this->wrapper->get(sprintf("conversations/%d/replies", $conversation_id), $sort);
    }

    /**
	 * Reply to an unread conversation.
	 *
     * @param int The identifier of the unread conversation.
     * @param string The text content of the reply message.
     * 
	 * @return APIResponse The parsed API response.
	 */
    function reply(int $conversation_id, string $message): APIResponse {
        return $this->wrapper->post(sprintf("conversations/%d/replies", $conversation_id), ["message" => $message]);
    }
}