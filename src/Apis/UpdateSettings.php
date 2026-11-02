<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateSettings.ts
 *
 * Response: ""
 */
class UpdateSettings extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("https://wpa.chat.zalo.me/api/setting/update");
    }

    /**
     * Set account settings
     *
     * @param string $type The type of setting to update (UpdateSettingsType::*)
     * @param int $value
     *
     * ViewBirthday
     * * 0: hide
     * * 1: show full day/month/year
     * * 2: show day/month
     *
     * ShowOnlineStatus
     * * 0: hide
     * * 1: show
     *
     * DisplaySeenStatus
     * * 0: hide
     * * 1: show
     *
     * ReceiveMessage
     * * 1: everyone
     * * 2: only friends
     *
     * AcceptCall
     * * 2: only friends
     * * 3: everyone
     * * 4: friends and person who contacted
     *
     * AddFriendViaPhone
     * * 0: disable
     * * 1: enable
     *
     * AddFriendViaQR
     * * 0: disable
     * * 1: enable
     *
     * AddFriendViaGroup
     * * 0: disable
     * * 1: enable
     *
     * AddFriendViaContact
     * * 0: disable
     * * 1: enable
     *
     * DisplayOnRecommendFriend
     * * 0: disable
     * * 1: enable
     *
     * ArchivedChat
     * * 0: disable
     * * 1: enable
     *
     * QuickMessage
     * * 0: disable
     * * 1: enable
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $type, int $value)
    {
        $params = [
            $type => $value,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
