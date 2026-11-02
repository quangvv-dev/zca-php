<?php

namespace Zca\Models;

/**
 * Port of enum `ReportReason` (src/apis/sendReport.ts)
 */
final class ReportReason
{
    public const Sensitive = 1;
    public const Annoy = 2;
    public const Fraud = 3;
    public const Other = 0;
}
