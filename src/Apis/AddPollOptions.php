<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/addPollOptions.ts
 *
 * Response: array{options: array<PollOptions>}
 */
class AddPollOptions extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/poll/option/add");
    }

    /**
     * Add new option to poll
     *
     * @param array{pollId: int, options: array<array{voted: bool, content: string}>, votedOptionIds: int[]} $payload
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $payload): mixed
    {
        $params = [
            "poll_id" => $payload["pollId"],
            "new_options" => Utils::json($payload["options"]),
            "voted_option_ids" => $payload["votedOptionIds"],
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
