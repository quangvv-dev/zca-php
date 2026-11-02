<?php

namespace Zca\Models;

/**
 * Port of enum `UpdateSettingsType` (src/apis/updateSettings.ts)
 */
final class UpdateSettingsType
{
    public const ViewBirthday = "view_birthday";
    public const ShowOnlineStatus = "show_online_status";
    public const DisplaySeenStatus = "display_seen_status";
    public const ReceiveMessage = "receive_message";
    public const AcceptCall = "accept_stranger_call";
    public const AddFriendViaPhone = "add_friend_via_phone";
    public const AddFriendViaQR = "add_friend_via_qr";
    public const AddFriendViaGroup = "add_friend_via_group";
    public const AddFriendViaContact = "add_friend_via_contact";
    public const DisplayOnRecommendFriend = "display_on_recommend_friend";
    public const ArchivedChat = "archivedChatStatus";
    public const QuickMessage = "quickMessageStatus";
}
