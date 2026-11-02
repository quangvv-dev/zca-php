<?php

namespace Zca;

use Zca\Apis\Login;
use Zca\Apis\LoginQR;
use Zca\Cookie\CookieJar;
use Zca\Errors\ZaloApiError;
use Zca\Models\LoginQRCallbackEventType;

/**
 * Entry point, port of the `Zalo` class of zca-js.
 *
 * Credentials: [
 *   "imei" => string,
 *   "cookie" => array (list of cookies | ["url" => string, "cookies" => list] | tough-cookie jar JSON),
 *   "userAgent" => string,
 *   "language" => string (optional, default "vi"),
 * ]
 */
class Zalo
{
    /**
     * @var array<string, mixed>
     */
    private $options = [];
    /**
     * @var bool
     */
    private $enableEncryptParam = true;

    /**
     * @param array<string, mixed> $options see Context for the available options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
        if (!array_key_exists("imageMetadataGetter", $this->options)) {
            $this->options["imageMetadataGetter"] = [Utils::class, "defaultImageMetadataGetter"];
        }
    }

    /**
     * @param array<int|string, mixed> $cookie
     */
    private function parseCookies(array $cookie): CookieJar
    {
        $arrayIsListFunction = function (array $array): bool {
            if (function_exists('array_is_list')) {
                return array_is_list($array);
            }
            if ($array === []) {
                return true;
            }
            $current_key = 0;
            foreach ($array as $key => $noop) {
                if ($key !== $current_key) {
                    return false;
                }
                ++$current_key;
            }
            return true;
        };
        $cookieArr = $arrayIsListFunction($cookie) ? $cookie : ($cookie["cookies"] ?? []);

        foreach ($cookieArr as $i => $e) {
            if (isset($e["domain"]) && is_string($e["domain"]) && strncmp($e["domain"], ".", strlen(".")) === 0) $cookieArr[$i]["domain"] = (string) substr($e["domain"], 1);
        }

        $jar = new CookieJar();
        foreach ($cookieArr as $each) {
            try {
                $domain = $each["domain"] ?? null ?: "chat.zalo.me";
                $url = "https://" . (strncmp($domain, ".", strlen(".")) === 0 ? (string) substr($domain, 1) : $domain);
                $jar->setCookieFromJSON($each, $url);
            } catch (\Throwable $error) {
                Logger::of(["logging" => $this->options["logging"] ?? null])->error("Failed to set cookie:", $error);
            }
        }

        return $jar;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function validateParams(array $credentials): void
    {
        if (empty($credentials["imei"]) || empty($credentials["cookie"]) || empty($credentials["userAgent"])) {
            throw new ZaloApiError("Missing required params");
        }
    }

    private function createContext(): Context
    {
        $ctx = Context::create($this->options["apiType"] ?? null, $this->options["apiVersion"] ?? null);
        $ctx->options = array_merge($ctx->options, $this->options);

        return $ctx;
    }

    /**
     * Login with cookie
     *
     * @param array{imei: string, cookie: array, userAgent: string, language?: string} $credentials
     */
    public function login(array $credentials): API
    {
        $ctx = $this->createContext();

        return $this->loginCookie($ctx, $credentials);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function loginCookie(Context $ctx, array $credentials): API
    {
        Update::checkUpdate($ctx);

        $this->validateParams($credentials);

        $ctx->imei = $credentials["imei"];
        $ctx->cookie = $this->parseCookies($credentials["cookie"]);
        $ctx->userAgent = $credentials["userAgent"];
        $ctx->language = $credentials["language"] ?? null ?: "vi";

        $loginData = Login::login($ctx, $this->enableEncryptParam);
        $serverInfo = Login::getServerInfo($ctx, $this->enableEncryptParam);

        $loginInfo = $loginData["data"] ?? null;

        if (!$loginData || !$loginInfo || !$serverInfo) throw new ZaloApiError("Đăng nhập thất bại");

        $ctx->secretKey = $loginInfo["zpw_enk"];
        $ctx->uid = (string) $loginInfo["uid"];

        // Zalo currently responds with setttings instead of settings
        // they might fix this in the future, so we should have a fallback just in case
        $ctx->settings = $serverInfo["setttings"] ?? $serverInfo["settings"] ?? null;

        $ctx->extraVer = $serverInfo["extra_ver"] ?? null;
        $ctx->loginInfo = $loginInfo;
        $ctx->zpwServiceMap = $loginInfo["zpw_service_map_v3"];

        if (!$ctx->isSession()) throw new ZaloApiError("Khởi tạo ngữ cảnh thất bại.");

        Logger::of($ctx)->info("Logged in as", $loginInfo["uid"]);

        return new API($ctx, $loginInfo["zpw_service_map_v3"], $loginInfo["zpw_ws"]);
    }

    /**
     * Login with QR code
     *
     * @param array{userAgent?: string, language?: string, qrPath?: string}|null $options
     * @param (callable(array): mixed)|null $callback receives ["type" => LoginQRCallbackEventType::*, "data" => ..., "actions" => ...]
     */
    public function loginQR(?array $options = null, ?callable $callback = null): API
    {
        if (!$options) $options = [];
        if (empty($options["userAgent"]))
            $options["userAgent"] = "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0";
        if (empty($options["language"])) $options["language"] = "vi";

        $ctx = $this->createContext();

        $loginQRResult = LoginQR::loginQR($ctx, $options, $callback);
        if (!$loginQRResult) throw new ZaloApiError("Unable to login with QRCode");

        $imei = Utils::generateZaloUUID($options["userAgent"]);

        if ($callback) {
            // Thanks to @YanCastle for this great suggestion!
            $callback([
                "type" => LoginQRCallbackEventType::GotLoginInfo,
                "data" => [
                    "cookie" => $loginQRResult["cookies"],
                    "imei" => $imei,
                    "userAgent" => $options["userAgent"],
                ],
                "actions" => null,
            ]);
        }

        return $this->loginCookie($ctx, [
            "cookie" => $loginQRResult["cookies"],
            "imei" => $imei,
            "userAgent" => $options["userAgent"],
            "language" => $options["language"],
        ]);
    }
}
