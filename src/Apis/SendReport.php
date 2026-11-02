<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ReportReason;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/sendReport.ts
 *
 * Response: array{reportId: string}
 */
class SendReport extends BaseApi
{
    /** @var array<int, string> */
    private array $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/report/abuse-v2"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/reportabuse"),
        ];
    }

    /**
     * Send report to Zalo
     *
     * @param array{reason: int, content?: string} $options Report options (reason: ReportReason::*, content required when reason is ReportReason::Other)
     * @param string $threadId The threadID to report
     * @param int $type Thread type, default direct
     *
     * @throws ZaloApiError
     *
     */
    public function __invoke(array $options, string $threadId, int $type = ThreadType::User): mixed
    {
        $params =
            $type == ThreadType::User
                ? [
                    "idTo" => $threadId,
                    "objId" => "person.profile",
                    "reason" => (string) $options["reason"],
                    "content" => $options["reason"] == ReportReason::Other ? ($options["content"] ?? null) : null,
                ]
                : [
                    "uidTo" => $threadId,
                    "type" => 14,
                    "reason" => $options["reason"],
                    "content" => $options["reason"] == ReportReason::Other ? ($options["content"] ?? null) : "",
                    "imei" => $this->ctx->imei,
                ];

        Utils::removeUndefinedKeys($params);

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
