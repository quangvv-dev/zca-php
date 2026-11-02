<?php

namespace Zca\Models;

/**
 * Port of enum `GroupEventType` (src/models/GroupEvent.ts)
 */
final class GroupEventType
{
    public const JOIN_REQUEST = "join_request";
    public const JOIN = "join";
    public const LEAVE = "leave";
    public const REMOVE_MEMBER = "remove_member";
    public const BLOCK_MEMBER = "block_member";
    public const UPDATE_SETTING = "update_setting";
    public const UPDATE = "update";
    public const NEW_LINK = "new_link";
    public const ADD_ADMIN = "add_admin";
    public const REMOVE_ADMIN = "remove_admin";
    public const NEW_PIN_TOPIC = "new_pin_topic";
    public const UPDATE_PIN_TOPIC = "update_pin_topic";
    public const REORDER_PIN_TOPIC = "reorder_pin_topic";
    public const UPDATE_BOARD = "update_board";
    public const REMOVE_BOARD = "remove_board";
    public const UPDATE_TOPIC = "update_topic";
    public const UNPIN_TOPIC = "unpin_topic";
    public const REMOVE_TOPIC = "remove_topic";
    public const ACCEPT_REMIND = "accept_remind";
    public const REJECT_REMIND = "reject_remind";
    public const REMIND_TOPIC = "remind_topic";
    public const UPDATE_AVATAR = "update_avatar";
    public const UNKNOWN = "unknown";
}
