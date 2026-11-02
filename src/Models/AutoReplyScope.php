<?php

namespace Zca\Models;

/**
 * Port of enum `AutoReplyScope` (src/models/AutoReply.ts)
 */
final class AutoReplyScope
{
    public const Everyone = 0;
    public const Stranger = 1;
    public const SpecificFriends = 2;
    public const FriendsExcept = 3;
}
