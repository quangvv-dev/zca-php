<?php

namespace Zca\Apis;

use Zca\Context;
use Zca\Cookie\CookieJar;
use Zca\Errors\ZaloApiError;
use Zca\Errors\ZaloApiLoginQRAborted;
use Zca\Errors\ZaloApiLoginQRDeclined;
use Zca\Logger;
use Zca\Models\LoginQRCallbackEventType;
use Zca\Utils;

/**
 * Port of src/apis/loginQR.ts
 *
 * PHP is synchronous, so the `retry` / `abort` actions passed to the callback record a decision
 * which is applied as soon as the callback returns:
 *  - `retry()` restarts the whole QR login flow
 *  - `abort()` throws ZaloApiLoginQRAborted
 *
 * When the QR code expires (or is declined) and the callback does not call any action,
 * zca-js would wait forever; here ZaloApiLoginQRAborted (or ZaloApiLoginQRDeclined) is thrown instead.
 *
 * Callback event shape: ["type" => LoginQRCallbackEventType::*, "data" => array|null, "actions" => array<string, callable>|null]
 */
final class LoginQR
{
    public const QR_TIMEOUT = 100; // seconds

    private const SEC_CH_UA = '"Chromium";v="130", "Google Chrome";v="130", "Not?A_Brand";v="99"';
    private const ACCEPT_LANGUAGE = "vi-VN,vi;q=0.9,fr-FR;q=0.8,fr;q=0.7,en-US;q=0.6,en;q=0.5";

    private static function loadLoginPage(Context $ctx): ?string
    {
        $response = Utils::request($ctx, "https://id.zalo.me/account?continue=https%3A%2F%2Fchat.zalo.me%2F", [
            "headers" => [
                "accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
                "accept-language" => self::ACCEPT_LANGUAGE,
                "cache-control" => "max-age=0",
                "priority" => "u=0, i",
                "sec-ch-ua" => self::SEC_CH_UA,
                "sec-ch-ua-mobile" => "?0",
                "sec-ch-ua-platform" => '"Windows"',
                "sec-fetch-dest" => "document",
                "sec-fetch-mode" => "navigate",
                "sec-fetch-site" => "same-site",
                "sec-fetch-user" => "?1",
                "upgrade-insecure-requests" => "1",
                "Referer" => "https://chat.zalo.me/",
                "Referrer-Policy" => "strict-origin-when-cross-origin",
            ],
            "method" => "GET",
        ]);

        $html = (string) $response->getBody();

        if (preg_match('/https:\/\/stc-zlogin\.zdn\.vn\/main-([\d.]+)\.js/', $html, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function xhrHeaders(string $referer, bool $form = true): array
    {
        $headers = [
            "accept" => "*/*",
            "accept-language" => self::ACCEPT_LANGUAGE,
            "content-type" => "application/x-www-form-urlencoded",
            "priority" => "u=1, i",
            "sec-ch-ua" => self::SEC_CH_UA,
            "sec-ch-ua-mobile" => "?0",
            "sec-ch-ua-platform" => '"Windows"',
            "sec-fetch-dest" => "empty",
            "sec-fetch-mode" => "cors",
            "sec-fetch-site" => "same-origin",
            "Referer" => $referer,
            "Referrer-Policy" => "strict-origin-when-cross-origin",
        ];
        if (!$form) unset($headers["content-type"]);

        return $headers;
    }

    /**
     * @return mixed
     */
    private static function postJson(Context $ctx, string $url, array $form, string $referer, ?float $timeout = null)
    {
        $options = [
            "headers" => self::xhrHeaders($referer),
            "body" => $form,
            "method" => "POST",
        ];
        if ($timeout !== null) $options["timeout"] = $timeout;

        return Utils::responseJson(Utils::request($ctx, $url, $options));
    }

    /**
     * @return mixed
     */
    private static function getLoginInfo(Context $ctx, string $version)
    {
        try {
            return self::postJson($ctx, "https://id.zalo.me/account/logininfo", [
                "continue" => "https://zalo.me/pc",
                "v" => $version,
            ], "https://id.zalo.me/account?continue=https%3A%2F%2Fzalo.me%2Fpc");
        } catch (\Throwable $e) {
            Logger::of($ctx)->error($e);

            return null;
        }
    }

    /**
     * @return mixed
     */
    private static function verifyClient(Context $ctx, string $version)
    {
        try {
            return self::postJson($ctx, "https://id.zalo.me/account/verify-client", [
                "type" => "device",
                "continue" => "https://zalo.me/pc",
                "v" => $version,
            ], "https://id.zalo.me/account?continue=https%3A%2F%2Fzalo.me%2Fpc");
        } catch (\Throwable $e) {
            Logger::of($ctx)->error($e);

            return null;
        }
    }

    /**
     * @return array{data: array{code: string, image: string, options: array, token: string}|null, error_code: int, error_message: string}|null
     */
    private static function generate(Context $ctx, string $version): ?array
    {
        try {
            return self::postJson($ctx, "https://id.zalo.me/account/authen/qr/generate", [
                "continue" => "https://zalo.me/pc",
                "v" => $version,
            ], "https://id.zalo.me/account?continue=https%3A%2F%2Fzalo.me%2Fpc");
        } catch (\Throwable $e) {
            Logger::of($ctx)->error($e);

            return null;
        }
    }

    private static function saveQRCodeToFile(string $filepath, string $imageData): void
    {
        file_put_contents($filepath, base64_decode($imageData));
    }

    /**
     * Long polling until the QR code is scanned. Returns null on failure, "expired" when the QR code expired.
     * @return mixed[]|string|null
     */
    private static function waitingScan(Context $ctx, string $version, string $code, float $deadline)
    {
        while (true) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) return "expired";

            try {
                $data = self::postJson($ctx, "https://id.zalo.me/account/authen/qr/waiting-scan", [
                    "code" => $code,
                    "continue" => "https://chat.zalo.me/",
                    "v" => $version,
                ], "https://id.zalo.me/account?continue=https%3A%2F%2Fchat.zalo.me%2F", $remaining);
            } catch (\Throwable $e) {
                if (microtime(true) >= $deadline) return "expired";
                Logger::of($ctx)->error($e);

                return null;
            }

            if (($data["error_code"] ?? null) == 8) continue;

            return $data;
        }
    }

    /**
     * Long polling until the login is confirmed. Returns null on failure, "expired" when the QR code expired.
     * @return mixed[]|string|null
     */
    private static function waitingConfirm(Context $ctx, string $version, string $code, float $deadline)
    {
        Logger::of($ctx)->info("Please confirm on your phone");

        while (true) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) return "expired";

            try {
                $data = self::postJson($ctx, "https://id.zalo.me/account/authen/qr/waiting-confirm", [
                    "code" => $code,
                    "gToken" => "",
                    "gAction" => "CONFIRM_QR",
                    "continue" => "https://chat.zalo.me/",
                    "v" => $version,
                ], "https://id.zalo.me/account?continue=https%3A%2F%2Fchat.zalo.me%2F", $remaining);
            } catch (\Throwable $e) {
                if (microtime(true) >= $deadline) return "expired";
                Logger::of($ctx)->error($e);

                return null;
            }

            if (($data["error_code"] ?? null) == 8) continue;

            return $data;
        }
    }

    /**
     * @return mixed
     */
    private static function checkSession(Context $ctx)
    {
        try {
            return Utils::request(
                $ctx,
                "https://id.zalo.me/account/checksession?continue=https%3A%2F%2Fchat.zalo.me%2Findex.html",
                [
                    "headers" => [
                        "accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
                        "accept-language" => self::ACCEPT_LANGUAGE,
                        "priority" => "u=0, i",
                        "sec-ch-ua" => self::SEC_CH_UA,
                        "sec-ch-ua-mobile" => "?0",
                        "sec-ch-ua-platform" => '"Windows"',
                        "sec-fetch-dest" => "document",
                        "sec-fetch-mode" => "navigate",
                        "sec-fetch-site" => "same-origin",
                        "upgrade-insecure-requests" => "1",
                        "Referer" => "https://id.zalo.me/account?continue=https%3A%2F%2Fchat.zalo.me%2F",
                        "Referrer-Policy" => "strict-origin-when-cross-origin",
                    ],
                    "redirect" => "manual",
                    "method" => "GET",
                ]
            );
        } catch (\Throwable $e) {
            Logger::of($ctx)->error($e);

            return null;
        }
    }

    private static function getUserInfo(Context $ctx): ?array
    {
        try {
            $headers = self::xhrHeaders("https://chat.zalo.me/", false);
            $headers["sec-fetch-site"] = "same-site";

            return Utils::responseJson(Utils::request($ctx, "https://jr.chat.zalo.me/jr/userinfo", [
                "headers" => $headers,
                "method" => "GET",
            ]));
        } catch (\Throwable $e) {
            Logger::of($ctx)->error($e);

            return null;
        }
    }

    /**
     * @param array{userAgent: string, language?: string, qrPath?: string|null} $options
     * @param (callable(array): mixed)|null $callback
     * @return array{cookies: array<int, array<string, mixed>>, userInfo: array{name: string, avatar: string}}|null
     */
    public static function loginQR(Context $ctx, array $options, ?callable $callback = null): ?array
    {
        $ctx->cookie = new CookieJar();
        $ctx->userAgent = $options["userAgent"];

        $decision = null;
        $actions = [
            "retry" => function () use (&$decision) {
                $decision = "retry";
            },
            "abort" => function () use (&$decision) {
                $decision = "abort";
            },
        ];
        $applyDecision = function () use (&$decision, $ctx, $options, $callback) {
            if ($decision === "retry") return ["result" => self::loginQR($ctx, $options, $callback)];
            if ($decision === "abort") throw new ZaloApiLoginQRAborted();

            return null;
        };

        $loginVersion = self::loadLoginPage($ctx);
        if (!$loginVersion) throw new ZaloApiError("Cannot get API login version");

        Logger::of($ctx)->info("Got login version:", $loginVersion);

        self::getLoginInfo($ctx, $loginVersion);
        self::verifyClient($ctx, $loginVersion);
        $qrGenResult = self::generate($ctx, $loginVersion);
        if (!$qrGenResult || empty($qrGenResult["data"]))
            throw new ZaloApiError("Unable to generate QRCode\nResponse: " . json_encode($qrGenResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $qrData = $qrGenResult["data"];
        $image = preg_replace('/^data:image\/png;base64,/', "", $qrData["image"]);

        if ($callback) {
            $callback([
                "type" => LoginQRCallbackEventType::QRCodeGenerated,
                "data" => array_merge($qrData, ["image" => $image]),
                "actions" => array_merge(["saveToFile" => function (?string $qrPath = null) use ($options, $image, $ctx) {
                    $qrPath = $qrPath ?? $options["qrPath"] ?? "qr.png";
                    self::saveQRCodeToFile($qrPath, $image);
                    Logger::of($ctx)->info("Scan the QR code at", "'{$qrPath}'", "to proceed with login");
                }], $actions),
            ]);
            if ($r = $applyDecision()) return $r["result"];
        } else {
            $qrPath = $options["qrPath"] ?? "qr.png";

            self::saveQRCodeToFile($qrPath, $image);
            Logger::of($ctx)->info("Scan the QR code at", "'{$qrPath}'", "to proceed with login");
        }

        $deadline = microtime(true) + self::QR_TIMEOUT;

        $onExpired = function () use ($ctx, $callback, $actions, $applyDecision, $options) {
            Logger::of($ctx)->info("QR expired!");

            if ($callback) {
                $callback([
                    "type" => LoginQRCallbackEventType::QRCodeExpired,
                    "data" => null,
                    "actions" => $actions,
                ]);
                if ($r = $applyDecision()) return $r["result"];

                throw new ZaloApiLoginQRAborted("QR code expired");
            }

            return self::loginQR($ctx, $options, $callback);
        };

        $scanResult = self::waitingScan($ctx, $loginVersion, $qrData["code"], $deadline);
        if ($scanResult === "expired") return $onExpired();
        if (!$scanResult || empty($scanResult["data"])) throw new ZaloApiError("Cannot get scan result");

        if ($callback) {
            $callback([
                "type" => LoginQRCallbackEventType::QRCodeScanned,
                "data" => $scanResult["data"],
                "actions" => $actions,
            ]);
            if ($r = $applyDecision()) return $r["result"];
        }

        $confirmResult = self::waitingConfirm($ctx, $loginVersion, $qrData["code"], $deadline);
        if ($confirmResult === "expired") return $onExpired();
        if (!$confirmResult) throw new ZaloApiError("Cannot get confirm result");

        if ($confirmResult["error_code"] == -13) {
            if ($callback) {
                $callback([
                    "type" => LoginQRCallbackEventType::QRCodeDeclined,
                    "data" => [
                        "code" => $qrData["code"],
                    ],
                    "actions" => $actions,
                ]);
                if ($r = $applyDecision()) return $r["result"];

                throw new ZaloApiLoginQRDeclined();
            } else {
                Logger::of($ctx)->error("QRCode login declined");
                throw new ZaloApiLoginQRDeclined();
            }
        } elseif ($confirmResult["error_code"] != 0) {
            throw new ZaloApiError("An error has occurred.\nResponse: " . json_encode($confirmResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $checkSessionResult = self::checkSession($ctx);
        if (!$checkSessionResult) throw new ZaloApiError("Cannot get session, login failed");

        Logger::of($ctx)->info("Successfully logged into the account", $scanResult["data"]["display_name"]);

        $userInfo = self::getUserInfo($ctx);
        if (!$userInfo || empty($userInfo["data"])) throw new ZaloApiError("Can't get account info");
        if (!$userInfo["data"]["logged"]) throw new ZaloApiError("Can't login");

        return [
            "cookies" => $ctx->cookie->toJSON()["cookies"],
            "userInfo" => $userInfo["data"]["info"],
        ];
    }
}
