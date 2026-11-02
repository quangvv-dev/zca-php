<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateGroupSettings.ts
 *
 * Response: ""
 */
class UpdateGroupSettings extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/setting/update");
    }

    /**
     * Update group settings
     *
     * @param array{blockName?: bool, signAdminMsg?: bool, setTopicOnly?: bool, enableMsgHistory?: bool, joinAppr?: bool, lockCreatePost?: bool, lockCreatePoll?: bool, lockSendMsg?: bool, lockViewMember?: bool} $options Options
     *   - blockName: Disallow group members to change the group name and avatar
     *   - signAdminMsg: Highlight messages from owner/admins
     *   - setTopicOnly: Don't pin messages, notes, and polls to the top of a conversation
     *   - enableMsgHistory: Allow new members to read most recent messages
     *   - joinAppr: Membership approval
     *   - lockCreatePost: Disallow group members to create notes & reminders
     *   - lockCreatePoll: Disallow group members to create polls
     *   - lockSendMsg: Disallow group members to send messages
     *   - lockViewMember: Disallow group members to view full member list (community only)
     * @param string $groupId Group ID
     *
     * @note Zalo might throw an error with code 166 if you don't have enough permissions to change the settings.
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $options, string $groupId)
    {
        $params = [
            "blockName" => !empty($options["blockName"]) ? 1 : 0,
            "signAdminMsg" => !empty($options["signAdminMsg"]) ? 1 : 0,

            // addMemberOnly: 0, // very tricky, any idea?

            "setTopicOnly" => !empty($options["setTopicOnly"]) ? 1 : 0,
            "enableMsgHistory" => !empty($options["enableMsgHistory"]) ? 1 : 0,
            "joinAppr" => !empty($options["joinAppr"]) ? 1 : 0,
            "lockCreatePost" => !empty($options["lockCreatePost"]) ? 1 : 0,
            "lockCreatePoll" => !empty($options["lockCreatePoll"]) ? 1 : 0,
            "lockSendMsg" => !empty($options["lockSendMsg"]) ? 1 : 0,
            "lockViewMember" => !empty($options["lockViewMember"]) ? 1 : 0,

            // default values for not implemented options
            "bannFeature" => 0,
            "dirtyMedia" => 0,
            "banDuration" => 0,
            "blocked_members" => [],

            "grid" => $groupId,
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
