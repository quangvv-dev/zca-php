<?php

namespace Zca\Apis;

/**
 * Port of src/apis/getAllGroups.ts
 *
 * Response: array{version: string, gridVerMap: array<string, string>}
 */
class GetAllGroups extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group_poll"][0]}/api/group/getlg/v4");
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
