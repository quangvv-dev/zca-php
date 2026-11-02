<?php

return [
    /*
    | Options passed to `new Zca\Zalo($options)`.
    */
    "options" => [
        "selfListen" => env("ZCA_SELF_LISTEN", false),
        "checkUpdate" => env("ZCA_CHECK_UPDATE", false),
        "logging" => env("ZCA_LOGGING", true),
        // "apiType" => 30,
        // "apiVersion" => 685,
        // "proxy" => "http://user:pass@host:port",
        // "uploadTimeout" => 300,
    ],

    /*
    | Send library logs to the Laravel log instead of stdout.
    */
    "use_laravel_logger" => env("ZCA_USE_LARAVEL_LOGGER", true),
];
