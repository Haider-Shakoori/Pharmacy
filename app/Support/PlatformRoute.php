<?php

namespace App\Support;

class PlatformRoute
{
    public static function name(string $suffix): string
    {
        $current = request()->route()?->getName();

        return is_string($current) && str_starts_with($current, 'legacy.platform.')
            ? 'legacy.platform.'.$suffix
            : 'platform.'.$suffix;
    }

    public static function url(string $suffix, mixed $parameters = [], bool $absolute = true): string
    {
        return route(self::name($suffix), $parameters, $absolute);
    }

    public static function is(string $pattern): bool
    {
        return request()->routeIs('platform.'.$pattern)
            || request()->routeIs('legacy.platform.'.$pattern);
    }
}
