<?php

namespace Zca;

use React\EventLoop\Loop;
use React\Promise\PromiseInterface;

/**
 * Minimal blocking `await` for PHP 7 (no fibers): runs the ReactPHP event loop until the promise settles.
 *
 * Must not be called from inside an event loop callback (nested loops are not re-entrant);
 * listener events dispatched by `Listener::run()` are outside of the loop stack and can use it.
 */
final class Async
{
    /**
     * @return mixed
     */
    public static function await(PromiseInterface $promise)
    {
        $wait = true;
        $resolved = null;
        $rejected = null;

        $promise->then(
            function ($value) use (&$resolved, &$wait) {
                $resolved = $value;
                $wait = false;
                Loop::stop();
            },
            function ($error) use (&$rejected, &$wait) {
                $rejected = $error;
                $wait = false;
                Loop::stop();
            }
        );

        while ($wait) {
            Loop::run();
        }

        if ($rejected !== null) {
            throw $rejected instanceof \Throwable ? $rejected : new \RuntimeException((string) $rejected);
        }

        return $resolved;
    }
}
