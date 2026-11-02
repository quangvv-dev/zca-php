<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\MuteAction;
use Zca\Models\MuteDuration;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/setMute.ts
 *
 * Response: ""
 */
class SetMute extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/setmute");
    }

    /**
     * Set mute
     *
     * @param array{duration?: int|string, action?: int}|null $params - Mute parameters (duration: Mute duration in seconds or predefined durations (MuteDuration::*), action: MuteAction::*). null = {}
     * @param string $threadID - ID of the thread to mute
     * @param int $type - Type of thread (User or Group)
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(?array $params, string $threadID, int $type = ThreadType::User)
    {
        $params = $params ?? [];
        $duration = $params["duration"] ?? MuteDuration::FOREVER;
        $action = $params["action"] ?? MuteAction::MUTE;

        if ($action === MuteAction::UNMUTE) {
            $muteDuration = -1;
        } elseif ($duration === MuteDuration::FOREVER) {
            $muteDuration = -1;
        } elseif ($duration === MuteDuration::UNTIL_8AM) {
            $now = new \DateTime();
            $next8AM = clone $now;
            $next8AM->setTime(8, 0, 0, 0);

            if ((int) $now->format("G") >= 8) {
                $next8AM->modify("+1 day");
            }

            $muteDuration = (int) floor(((int) $next8AM->format("Uv") - (int) $now->format("Uv")) / 1000);
        } else {
            $muteDuration = $duration;
        }

        $requestParams = [
            "toid" => $threadID,
            "duration" => $muteDuration,
            "action" => $action,
            "startTime" => (int) floor(Utils::now() / 1000),
            "muteType" => $type === ThreadType::User ? 1 : 2,
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($requestParams));
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
