<?php

namespace Zca\Errors;

class ZaloApiMissingImageMetadataGetter extends ZaloApiError
{
    public function __construct()
    {
        parent::__construct("Missing `imageMetadataGetter`. Please provide it in the Zalo object options.");
    }

    public function getName(): string
    {
        return "ZaloApiMissingImageMetadataGetter";
    }
}
