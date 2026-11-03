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
     * @var string
     */
    public $type;
    /**
     * @var mixed
     */
    public $data;
    /**
     * @var string
     */
    public $act;
    /**
     * @var string
     */
    public $act_type;
    /**
     * @param string $type one of SyncEventType constants
     * @param mixed $data
     */
    public function __construct(string $type, $data, string $act, string $act_type)
    {
        $this->type = $type;
        $this->data = $data;
        $this->act = $act;
        $this->act_type = $act_type;
    }

    /**
     * Port of `initializeSyncEvent`
     * @param mixed $data
     */
    public static function initialize($data, string $act, string $act_type): self
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
