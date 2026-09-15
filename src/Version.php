<?php

declare(strict_types=1);

namespace Cpx;

class Version
{
    public const VERSION = '1.00.00';

    public static function resolve(string $version = self::VERSION): string
    {
        return str_starts_with($version, '@') ? 'dev' : $version;
    }
}
