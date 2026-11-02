<?php

namespace Zca\Models;

/**
 * Port of enum `ReviewPendingMemberRequestStatus` (src/apis/reviewPendingMemberRequest.ts)
 */
final class ReviewPendingMemberRequestStatus
{
    public const SUCCESS = 0;
    public const NOT_IN_PENDING_LIST = 170;
    public const ALREADY_IN_GROUP = 178;
    public const INSUFFICIENT_PERMISSION = 166;
}
