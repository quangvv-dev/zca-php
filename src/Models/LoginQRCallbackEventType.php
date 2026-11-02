<?php

namespace Zca\Models;

/**
 * Port of enum `LoginQRCallbackEventType` (src/apis/loginQR.ts)
 */
final class LoginQRCallbackEventType
{
    public const QRCodeGenerated = 0;
    public const QRCodeExpired = 1;
    public const QRCodeScanned = 2;
    public const QRCodeDeclined = 3;
    public const GotLoginInfo = 4;
}
