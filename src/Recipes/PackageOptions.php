<?php

namespace Ufo\Packages\Recipes;

class PackageOptions
{
    public function __construct(
        public readonly ?string $minVersion = null,
        public readonly bool $prerelease = false,
        public readonly ?string $github = null,
    ) {}
}
