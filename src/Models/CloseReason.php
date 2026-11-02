<?php

namespace Zca\Models;

/**
 * Port of enum `CloseReason` (src/apis/listen.ts)
 */
final class CloseReason
{
    public const ManualClosure = 1000;
    public const AbnormalClosure = 1006;
    public const DuplicateConnection = 3000;
    public const KickConnection = 3003;
}
