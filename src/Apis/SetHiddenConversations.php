<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/setHiddenConversations.ts
 *
 * Response: ""
 */
class SetHiddenConversations extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["conversation"][0]}/api/hiddenconvers/add-remove");
    }

    /**
     * Set hidden conversations
     *
     * @param bool $hidden - Hide or unhide conversations
     * @param string|string[] $threadId Thread ID(s)
     * @param int $type Thread type (User/Group)
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(bool $hidden, $threadId, int $type = ThreadType::User)
    {
        $threadId = is_array($threadId) ? $threadId : [$threadId];
        if (count($threadId) === 0) throw new ZaloApiError("threadId is required");

        $is_group = $type === ThreadType::Group ? 1 : 0;
        $params = [
            ($hidden ? "add_threads" : "del_threads") => Utils::json(
                array_map(function ($id) use ($is_group) {
                    return [
                        "thread_id" => $id,
                        "is_group" => $is_group,
                    ];
                }, $threadId)
            ),
            ($hidden ? "del_threads" : "add_threads") => "[]",
            "imei" => $this->ctx->imei,
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
