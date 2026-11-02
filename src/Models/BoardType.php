<?php

namespace Zca\Models;

/**
 * Port of enum `BoardType` (src/models/Board.ts)
 */
final class BoardType
{
    public const Note = 1;
    public const PinnedMessage = 2;
    public const Poll = 3;
}
