<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/pullMobile.ts (zca-js PR #269)
 *
 * Asks the phone to sync its messages to this client. The response does not contain any meaningful fields
 * (empty encrypted data); the progress is sent through the listener as `sync_event` events.
 */
class PullMobile extends BaseApi
{
    /**
     * @var string
     */
    private $baseURL;

    protected function init(): void
    {
        $this->baseURL = $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/message/pull_mobile_msg");
    }

    /**
     * Pull mobile messages from the server
     *
     * @param array{public_key: string, from_seq_id?: int, is_retry?: int, min_seq_id?: int, temp_key?: string} $params Pull mobile message parameters
     *        (`public_key`: base64 RSA public key, see Utils::generateRSAKeyPair())
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $params)
    {
        $Params = [
            "pc_name" => "Web", // This will not be changed
            "public_key" => $params["public_key"] ?? null,
            "from_seq_id" => $params["from_seq_id"] ?? 0,
            "is_retry" => $params["is_retry"] ?? 0,
            "min_seq_id" => $params["min_seq_id"] ?? 0,
            "temp_key" => $params["temp_key"] ?? "",
            "imei" => $this->ctx->imei,
        ];
        if ($Params["public_key"] === null) unset($Params["public_key"]);

        $encryptedParams = $this->utils->encodeAES(Utils::json($Params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $serviceURL = $this->utils->makeURL(
            $this->baseURL,
            [
                "zpw_ver" => $this->ctx->API_VERSION,
                "zpw_type" => $this->ctx->API_TYPE,
                "params" => $encryptedParams,
                "nretry" => 0,
            ],
            false
        );

        $response = $this->utils->request($serviceURL, [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
