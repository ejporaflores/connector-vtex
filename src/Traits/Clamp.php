<?php

namespace Vega\Connector\Vtex\Traits;

trait Clamp
{
    /**
     * @param callable $callable
     * @param array $args
     * @param int $time
     * @return mixed
     */
    protected function clamp(callable $callable, array $args, $time = 13000)
    {
        // convert float seconds to integer microseconds
        $start = microtime(true) * 1000000;
        $return = call_user_func_array($callable, $args);
        $end = microtime(true) * 1000000;
        $diff = floor($end - $start);

        $sleep = $time - $diff;
        if ($sleep > 0) {
            usleep($sleep);
        }

        return $return;
    }
}
