<?php

namespace Zca\Apis;

/**
 * Port of src/apis/fetchAccountInfo.ts
 *
 * Response: array{profile: User}
 */
class FetchAccountInfo extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/me-v2");
    }

    public function __invoke(): mixed
    {
        $response = $this->utils->request($this->serviceURL, [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
