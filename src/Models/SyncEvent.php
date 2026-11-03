<?php

namespace Zca\Models;

/**
 * Mobile message sync event emitted by the listener (`sync_event`), see `$api->pullMobile()`.
 *
 * data by type:
 *  - SyncEventType::USER_CONFIRM: array{user_action: int, public_key: string, pc_name: string}
 *  - SyncEventType::SYNCMSG_INFO: array{uid: int, url: string, time: int, device_type: int, device_name: string, client_version: int,
 *        file_name: string, checksum_code: string, file_size: int, client_time: int, from_seq_id: int, public_key: string,
 *        encrypted_key: string, trigger_reason: int, is_full_transfer: int, db_info: string|array}
 *  - SyncEventType::TRANSFER_ERROR: array{public_key: string, pc_name: string, can_retry: int, error_msg: string, error_code: int}
 */
class SyncEvent
{
    /**
     * @param string $type one of SyncEventType constants
     */
    public function __construct(
        public string $type,
        public mixed $data,
        public string $act,
        public string $act_type,
    ) {}

    /**
     * Port of `initializeSyncEvent`
     */
    public static function initialize(mixed $data, string $act, string $act_type): self
    {
        if ($act === "user_confirm") {
            return new self(SyncEventType::USER_CONFIRM, $data, $act, $act_type);
        } elseif ($act === "syncmsg_info") {
            return new self(SyncEventType::SYNCMSG_INFO, $data, $act, $act_type);
        } elseif ($act === "transfer_error") {
            return new self(SyncEventType::TRANSFER_ERROR, $data, $act, $act_type);
        } else {
            return new self(SyncEventType::UNKNOWN, $data, $act, $act_type);
        }
    }
}
