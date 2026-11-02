<?php

namespace Zca\Models;

use Zca\Utils;

/**
 * Friend event emitted by the listener (`friend_event`).
 */
class FriendEvent
{
    /**
     * @param int $type one of FriendEventType constants
     * @param string|array<string, mixed> $data
     */
    public function __construct(
        public int $type,
        public string|array $data,
        public string $threadId,
        public bool $isSelf,
    ) {}

    /**
     * Port of `initializeFriendEvent`
     *
     * @param string|int|array<string, mixed> $data
     */
    public static function initialize(string $uid, string|int|array $data, int $type): self
    {
        if (
            $type == FriendEventType::ADD ||
            $type == FriendEventType::REMOVE ||
            $type == FriendEventType::BLOCK ||
            $type == FriendEventType::UNBLOCK ||
            $type == FriendEventType::BLOCK_CALL ||
            $type == FriendEventType::UNBLOCK_CALL
        ) {
            return new self($type, is_array($data) ? $data : (string) $data, is_array($data) ? "" : (string) $data, !in_array($type, [FriendEventType::ADD, FriendEventType::REMOVE], true));
        } elseif ($type == FriendEventType::REJECT_REQUEST || $type == FriendEventType::UNDO_REQUEST) {
            return new self($type, $data, (string) ($data["toUid"] ?? ""), ($data["fromUid"] ?? null) == $uid);
        } elseif ($type == FriendEventType::REQUEST) {
            return new self($type, $data, (string) ($data["toUid"] ?? ""), ($data["fromUid"] ?? null) == $uid);
        } elseif ($type == FriendEventType::SEEN_FRIEND_REQUEST) {
            return new self($type, $data, $uid, true);
        } elseif ($type == FriendEventType::PIN_CREATE) {
            return new self($type, $data, (string) ($data["conversationId"] ?? ""), ($data["actorId"] ?? null) == $uid);
        } elseif ($type == FriendEventType::PIN_UNPIN) {
            return new self($type, $data, (string) ($data["conversationId"] ?? ""), ($data["actorId"] ?? null) == $uid);
        } else {
            return new self(FriendEventType::UNKNOWN, Utils::json($data), "", false);
        }
    }
}
