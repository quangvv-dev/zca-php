<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/votePoll.ts
 *
 * Response: array{options: array (PollOptions[])}
 */
class VotePoll extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/poll/vote");
    }

    /**
     * Vote on a poll
     *
     * @param int $pollId The ID of the poll to vote on
     * @param int|int[] $optionId The ID(s) of the option to vote on
     *
     * @throws ZaloApiError
     */
    public function __invoke(int $pollId, int|array $optionId): mixed
    {
        if (!is_array($optionId)) $optionId = [$optionId];

        $params = [
            "poll_id" => $pollId,
            "option_ids" => $optionId, // unvote = empty array
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
