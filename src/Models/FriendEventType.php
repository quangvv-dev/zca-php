<?php

namespace Zca\Models;

/**
 * Port of enum `FriendEventType` (src/models/FriendEvent.ts)
 */
final class FriendEventType
{
    public const ADD = 0;
    public const REMOVE = 1;
    public const REQUEST = 2;
    public const UNDO_REQUEST = 3;
    public const REJECT_REQUEST = 4;
    public const SEEN_FRIEND_REQUEST = 5;
    public const BLOCK = 6;
    public const UNBLOCK = 7;
    public const BLOCK_CALL = 8;
    public const UNBLOCK_CALL = 9;
    public const PIN_UNPIN = 10;
    public const PIN_CREATE = 11;
    public const UNKNOWN = 12;
}
