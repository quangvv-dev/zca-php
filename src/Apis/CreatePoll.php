<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/createPoll.ts
 *
 * Response: PollDetail
 */
class CreatePoll extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/poll/create");
    }

    /**
     * Create a poll in a group.
     *
     * @param array{question: string, options: string[], expiredTime?: int, allowMultiChoices?: bool, allowAddNewOption?: bool, hideVotePreview?: bool, isAnonymous?: bool} $options Poll options
     *   - question: Question for the poll.
     *   - options: List of options for the poll.
     *   - expiredTime: Poll expiration time in milliseconds (0 = no expiration).
     *   - allowMultiChoices: Allows multiple choices in the poll.
     *   - allowAddNewOption: Allows members to add new options to the poll.
     *   - hideVotePreview: Hides voting results until the user has voted.
     *   - isAnonymous: Hides poll voters (anonymous poll).
     * @param string $groupId Group ID to create poll from
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $options, string $groupId)
    {
        $params = [
            "group_id" => $groupId,
            "question" => $options["question"],
            "options" => $options["options"],
            "expired_time" => $options["expiredTime"] ?? 0,
            "pinAct" => false,
            "allow_multi_choices" => !empty($options["allowMultiChoices"]),
            "allow_add_new_option" => !empty($options["allowAddNewOption"]),
            "is_hide_vote_preview" => !empty($options["hideVotePreview"]),
            "is_anonymous" => !empty($options["isAnonymous"]),
            "poll_type" => 0,
            "src" => 1,
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
