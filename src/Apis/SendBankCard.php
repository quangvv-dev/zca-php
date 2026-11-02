<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/sendBankCard.ts
 *
 * Response: ""
 */
class SendBankCard extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["zimsg"][0]}/api/transfer/card");
    }

    /**
     * Send bank card
     *
     * @param array{binBank: int, numAccBank: string, nameAccBank?: string} $payload The payload containing the bank card information (binBank: BinBankCard::*)
     * @param string $threadId The ID of the thread to send the bank card to
     * @param int $type The type of thread to send the bank card to
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $payload, string $threadId, int $type = ThreadType::User)
    {
        $nameAccBank = isset($payload["nameAccBank"]) ? mb_strtoupper($payload["nameAccBank"], "UTF-8") : null;

        $params = [
            "binBank" => $payload["binBank"],
            "numAccBank" => $payload["numAccBank"],
            "nameAccBank" => ($nameAccBank === null || $nameAccBank === "") ? "---" : $nameAccBank,
            "cliMsgId" => (string) Utils::now(),
            "tsMsg" => Utils::now(),
            "destUid" => $threadId,
            "destType" => $type === ThreadType::Group ? 1 : 0,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->serviceURL, [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }
}
