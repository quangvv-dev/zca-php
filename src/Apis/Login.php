<?php

namespace Zca\Apis;

use Zca\Context;
use Zca\Errors\ZaloApiError;
use Zca\Logger;
use Zca\ParamsEncryptor;
use Zca\Utils;

/**
 * Port of src/apis/login.ts
 */
final class Login
{
    /**
     * @return array<string, mixed>|null
     */
    public static function login(Context $ctx, bool $encryptParams): ?array
    {
        $encryptedParams = self::getEncryptParam($ctx, $encryptParams, "getlogininfo");

        try {
            $response = Utils::request(
                $ctx,
                Utils::makeURL($ctx, "https://wpa.chat.zalo.me/api/login/getLoginInfo", [
                    ...$encryptedParams["params"],
                    "nretry" => 0,
                ]),
            );
            if (!Utils::isOk($response)) throw new ZaloApiError("Failed to fetch login info: " . $response->getReasonPhrase());
            $data = Utils::responseJson($response);

            if ($encryptedParams["enk"]) {
                $decryptedData = Utils::decryptResp($encryptedParams["enk"], (string) ($data["data"] ?? "undefined"));

                return $decryptedData != null && !is_string($decryptedData) ? $decryptedData : null;
            }

            return null;
        } catch (\Throwable $error) {
            Logger::of($ctx)->error("Login failed:", $error);
            throw $error;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getServerInfo(Context $ctx, bool $encryptParams): array
    {
        $encryptedParams = self::getEncryptParam($ctx, $encryptParams, "getserverinfo");
        if (empty($encryptedParams["params"]["signkey"]) || !is_string($encryptedParams["params"]["signkey"]))
            throw new ZaloApiError("Missing signkey");

        $response = Utils::request(
            $ctx,
            Utils::makeURL(
                $ctx,
                "https://wpa.chat.zalo.me/api/login/getServerInfo",
                [
                    "imei" => $ctx->imei,
                    "type" => $ctx->API_TYPE,
                    "client_version" => $ctx->API_VERSION,
                    "computer_name" => "Web",
                    "signkey" => $encryptedParams["params"]["signkey"],
                ],
                false,
            ),
        );
        if (!Utils::isOk($response)) throw new ZaloApiError("Failed to fetch server info: " . $response->getReasonPhrase());
        $data = Utils::responseJson($response);

        if (($data["data"] ?? null) == null) throw new ZaloApiError("Failed to fetch server info: " . ($data["error_message"] ?? ""));

        return $data["data"];
    }

    /**
     * @return array{params: array<string, mixed>, enk: string|null}
     */
    private static function getEncryptParam(Context $ctx, bool $encryptParams, string $type): array
    {
        $params = [];
        $data = [
            "computer_name" => "Web",
            "imei" => $ctx->imei,
            "language" => $ctx->language,
            "ts" => Utils::now(),
        ];
        $encryptedData = self::encryptParam($ctx, $data, $encryptParams);

        if ($encryptedData == null) {
            $params = array_merge($params, $data);
        } else {
            $params = array_merge($params, $encryptedData["encrypted_params"]);
            $params["params"] = $encryptedData["encrypted_data"];
        }

        $params["type"] = $ctx->API_TYPE;
        $params["client_version"] = $ctx->API_VERSION;
        $params["signkey"] =
            $type == "getserverinfo"
                ? Utils::getSignKey($type, [
                    "imei" => $ctx->imei,
                    "type" => $ctx->API_TYPE,
                    "client_version" => $ctx->API_VERSION,
                    "computer_name" => "Web",
                ])
                : Utils::getSignKey($type, $params);

        return [
            "params" => $params,
            "enk" => $encryptedData ? $encryptedData["enk"] : null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{encrypted_data: string|null, encrypted_params: array<string, string>, enk: string}|null
     */
    private static function encryptParam(Context $ctx, array $data, bool $encryptParams): ?array
    {
        if ($encryptParams) {
            $encryptor = new ParamsEncryptor($ctx->API_TYPE, $data["imei"], Utils::now());
            try {
                $stringifiedData = Utils::json($data);
                $encryptedKey = $encryptor->getEncryptKey();
                $encodedData = ParamsEncryptor::encodeAES($encryptedKey, $stringifiedData, "base64", false);
                $params = $encryptor->getParams();

                return $params
                    ? [
                        "encrypted_data" => $encodedData,
                        "encrypted_params" => $params,
                        "enk" => $encryptedKey,
                    ]
                    : null;
            } catch (\Throwable $error) {
                throw new ZaloApiError("Failed to encrypt params: " . $error->getMessage());
            }
        }

        return null;
    }
}
