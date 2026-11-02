<?php

namespace Zca\Models;

/**
 * Port of enum `ReminderRepeatMode` (src/models/Reminder.ts)
 */
final class ReminderRepeatMode
{
    public const None = 0;
    public const Daily = 1;
    public const Weekly = 2;
    public const Monthly = 3;
}
