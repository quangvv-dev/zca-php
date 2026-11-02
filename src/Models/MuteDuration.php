<?php

namespace Zca\Models;

/**
 * Port of enum `MuteDuration` (src/apis/setMute.ts)
 */
final class MuteDuration
{
    public const ONE_HOUR = 3600;
    public const FOUR_HOURS = 14400;
    public const FOREVER = -1;
    public const UNTIL_8AM = "until8AM";
}
