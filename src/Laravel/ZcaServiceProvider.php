<?php

namespace Zca\Laravel;

use Illuminate\Support\ServiceProvider;
use Zca\Zalo;

class ZcaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . "/../../config/zca.php", "zca");

        $this->app->singleton(Zalo::class, function ($app) {
            $config = $app["config"]["zca"];
            $options = $config["options"] ?? [];
            if (!empty($config["use_laravel_logger"])) {
                $options["logger"] = $app["log"];
            }

            return new Zalo($options);
        });

        $this->app->alias(Zalo::class, "zca");
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . "/../../config/zca.php" => config_path("zca.php"),
            ], "zca-config");
        }
    }
}
