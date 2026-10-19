<?php

namespace Ufo\Packages\Recipes\Source;

use Ufo\Packages\Recipes\PackageOptions;

interface VersionSource
{
    /**
     * @return string[] raw release versions/tags
     * @throws PackageNotFound
     */
    public function versions(string $package, PackageOptions $options): array;
}
