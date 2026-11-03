<?php

namespace Zca\Models;

/**
 * Port of enum `SyncEventType` (src/models/SyncEvent.ts, zca-js PR #269)
 */
final class SyncEventType
{
    public const USER_CONFIRM = "user_confirm";
    public const SYNCMSG_INFO = "syncmsg_info";
    public const TRANSFER_ERROR = "transfer_error";
    public const UNKNOWN = "unknown";
}
