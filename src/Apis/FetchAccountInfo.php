<?php

namespace Zca\Apis;

/**
 * Port of src/apis/fetchAccountInfo.ts
 *
 * Response: array{profile: User}
 */
class FetchAccountInfo extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/me-v2");
    }

    /**
     * @return mixed
     */
    public function __invoke()
    {
        $response = $this->utils->request($this->serviceURL, [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
