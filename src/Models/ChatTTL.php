<?php

namespace Zca\Models;

/**
 * Port of enum `ChatTTL` (src/apis/updateAutoDeleteChat.ts)
 */
final class ChatTTL
{
    public const NO_DELETE = 0;
    public const ONE_DAY = 86400000;
    public const SEVEN_DAYS = 604800000;
    public const FOURTEEN_DAYS = 1209600000;
}
