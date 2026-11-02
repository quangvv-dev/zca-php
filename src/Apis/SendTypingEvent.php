<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\DestType;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/sendTypingEvent.ts
 *
 * Response: array{status: int}
 */
class SendTypingEvent extends BaseApi
{
    /** @var array<int, string> */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["chat"][0]}/api/message/typing"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/typing"),
        ];
    }

    /**
     * Send typing event
     *
     * @param string $threadId The ID of the User or Group to send the typing event to
     * @param int $type The type of thread (User or Group)
     * @param int $destType The destination type (User or Page), for User threads only, defaults to User
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $threadId, int $type = ThreadType::User, int $destType = DestType::User)
    {
        if (!$threadId) throw new ZaloApiError("Missing threadId");

        $params = array_merge([($type === ThreadType::User ? "toid" : "grid") => $threadId], $type === ThreadType::User ? ["destType" => $destType] : [], ["imei" => $this->ctx->imei]);

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
