<?php

namespace Zca\Models;

/**
 * Port of enum `MuteAction` (src/apis/setMute.ts)
 */
final class MuteAction
{
    public const MUTE = 1;
    public const UNMUTE = 3; // khi tat thi duration = -1
}
