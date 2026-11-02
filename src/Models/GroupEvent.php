<?php

namespace Zca\Models;

/**
 * Group event emitted by the listener (`group_event`).
 */
class GroupEvent
{
    /**
     * @param string $type one of GroupEventType constants
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $type,
        public string $act,
        public array $data,
        public string $threadId,
        public bool $isSelf,
    ) {}

    /**
     * Port of `initializeGroupEvent`
     *
     * @param array<string, mixed> $data
     */
    public static function initialize(string $uid, array $data, string $type, string $act): self
    {
        $threadId = (string) (array_key_exists("group_id", $data) ? $data["group_id"] : ($data["groupId"] ?? ""));

        if ($type == GroupEventType::JOIN_REQUEST) {
            return new self($type, $act, $data, $threadId, false);
        } elseif (
            $type == GroupEventType::NEW_PIN_TOPIC ||
            $type == GroupEventType::UNPIN_TOPIC ||
            $type == GroupEventType::UPDATE_PIN_TOPIC
        ) {
            return new self($type, $act, $data, $threadId, ($data["actorId"] ?? null) == $uid);
        } elseif ($type == GroupEventType::REORDER_PIN_TOPIC) {
            return new self($type, $act, $data, $threadId, ($data["actorId"] ?? null) == $uid);
        } elseif ($type == GroupEventType::UPDATE_BOARD || $type == GroupEventType::REMOVE_BOARD) {
            return new self($type, $act, $data, $threadId, ($data["sourceId"] ?? null) == $uid);
        } elseif ($type == GroupEventType::ACCEPT_REMIND || $type == GroupEventType::REJECT_REMIND) {
            $isSelf = false;
            foreach ($data["updateMembers"] ?? [] as $memberId) {
                if ($memberId == $uid) $isSelf = true;
            }

            return new self($type, $act, $data, $threadId, $isSelf);
        } elseif ($type == GroupEventType::REMIND_TOPIC) {
            return new self($type, $act, $data, $threadId, ($data["creatorId"] ?? null) == $uid);
        } else {
            $isSelf = false;
            foreach ($data["updateMembers"] ?? [] as $member) {
                if (is_array($member) && ($member["id"] ?? null) == $uid) $isSelf = true;
            }

            return new self($type, $act, $data, $threadId, $isSelf || ($data["sourceId"] ?? null) == $uid);
        }
    }
}
