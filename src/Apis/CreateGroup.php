<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/createGroup.ts
 *
 * Response: array{groupType: int, sucessMembers: string[], groupId: string, errorMembers: string[], error_data: array<string, mixed>}
 */
class CreateGroup extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/create/v2");
    }

    /**
     * Create a new group
     *
     * @param array{name?: string, members: string[], avatarSource?: string|array, avatarPath?: string} $options Group options
     *   - name: Group name
     *   - members: List of member IDs to add to the group
     *   - avatarSource: Avatar source, can be a file path or an Attachment object
     *   - avatarPath: Path to the avatar image file (@deprecated Use `avatarSource` instead)
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $options): mixed
    {
        if (count($options["members"]) == 0) throw new ZaloApiError("Group must have at least one member");

        $params = [
            "clientId" => Utils::now(),
            "gname" => (string) Utils::now(),
            "gdesc" => null,
            "members" => $options["members"],
            "membersTypes" => array_map(fn () => -1, $options["members"]),
            "nameChanged" => 0,
            "createLink" => 1,
            "clientLang" => $this->ctx->language,
            "imei" => $this->ctx->imei,
            "zsource" => 601,
        ];

        if (isset($options["name"]) && mb_strlen($options["name"], "UTF-8") > 0) {
            $params["gname"] = $options["name"];
            $params["nameChanged"] = 1;
        }

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "POST",
        ]);

        $data = $this->utils->resolve($response);
        $options["avatarSource"] = ($options["avatarSource"] ?? null) ?: ($options["avatarPath"] ?? null);
        if ($options["avatarSource"]) {
            try {
                $this->api->changeGroupAvatar($options["avatarSource"], $data["groupId"]);
            } catch (\Throwable $e) {
                $this->utils->logger->error($e);
            }
        }

        return $data;
    }
}
