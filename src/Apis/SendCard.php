<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/sendCard.ts
 *
 * Response: array{msgId: int}
 */
class SendCard extends BaseApi
{
    /** @var array<int, string> */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/message/forward"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/group/forward"),
        ];
    }

    /**
     * Send a card to a User - Group
     *
     * @param array{userId: string, phoneNumber?: string, ttl?: int} $options
     *   userId Unique ID for Card,
     *   phoneNumber Optional phone number for sending card to a User,
     *   ttl Time to live in milliseconds (default: 0)
     * @param string $threadId ID of the conversation
     * @param int $type Message type (User or GroupMessage)
     *
     * @throws ZaloApiError
     *
     *@return mixed
     */
    public function __invoke(array $options, string $threadId, int $type = ThreadType::User)
    {
        $data = $this->api->getQR($options["userId"]);
        $QRCodeURL = $data[$options["userId"]] ?? null;
        $clientId = (string) Utils::now();

        $params = [
            "ttl" => $options["ttl"] ?? 0,
            "msgType" => 6,
            "clientId" => $clientId,
            "msgInfo" => [
                "contactUid" => $options["userId"],
                "qrCodeUrl" => $QRCodeURL,
            ],
        ];
        // JSON.stringify drops undefined values
        if ($QRCodeURL === null) unset($params["msgInfo"]["qrCodeUrl"]);

        if (($options["phoneNumber"] ?? null) !== null && $options["phoneNumber"] !== "") {
            $params["msgInfo"]["phone"] = $options["phoneNumber"];
        }

        if ($type == ThreadType::Group) {
            $params["visibility"] = 0;
            $params["grid"] = $threadId;
        } else {
            $params["toId"] = $threadId;
            $params["imei"] = $this->ctx->imei;
        }

        $msgInfoStringified = Utils::json($params["msgInfo"]);
        $params["msgInfo"] = $msgInfoStringified;

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->serviceURL[$type], [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }
}
