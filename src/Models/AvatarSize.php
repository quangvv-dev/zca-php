<?php

namespace Zca\Models;

/**
 * Port of enum `AvatarSize` (src/models/Enum.ts)
 */
final class AvatarSize
{
    public const Small = 120;
    /** @experimental Use only if you know what you're doing. */
    public const Medium = 180;
    public const Large = 240;
    /** @experimental Use only if you know what you're doing. */
    public const ExtraLarge = 360;
}
