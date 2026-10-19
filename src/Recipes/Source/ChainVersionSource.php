<?php

namespace Ufo\Packages\Recipes\Source;

use Ufo\Packages\Recipes\PackageOptions;

/**
 * Asks sources in order; the next one is tried only when the package is not found.
 */
class ChainVersionSource implements VersionSource
{
    /**
     * @var VersionSource[]
     */
    protected array $sources;

    public function __construct(VersionSource ...$sources)
    {
        $this->sources = $sources;
    }

    public function versions(string $package, PackageOptions $options): array
    {
        $misses = [];
        foreach ($this->sources as $source) {
            try {
                return $source->versions($package, $options);
            } catch (PackageNotFound $e) {
                $misses[] = $e->getMessage();
            }
        }
        throw new PackageNotFound(implode('; ', $misses));
    }
}
