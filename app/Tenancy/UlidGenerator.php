<?php

namespace App\Tenancy;

use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;

class UlidGenerator implements UniqueIdentifierGenerator
{
    public static function generate($resource): string
    {
        return (string) Str::ulid();
    }
}
