<?php

namespace Ufo\Packages\Recipes;

use function array_keys;
use function preg_match;
use function str_starts_with;
use function usort;
use function version_compare;

class VersionTags
{
    /**
     * Collapses release versions/tags to unique X.Y minors sorted ascending —
     * Flex walks the index in reverse and takes the first minor <= installed version.
     *
     * @param string[] $versions e.g. ['1.2.3', 'v1.3.0', '5.2', '2.0.0-beta1']
     * @return string[]
     */
    public static function minors(array $versions, bool $prerelease = false, ?string $minVersion = null): array
    {
        $minors = [];
        foreach ($versions as $version) {
            if (!preg_match('/^v?(\d+)\.(\d+)(?:\.\d+){0,2}(?<suffix>.*)$/i', $version, $m)) continue;
            $isPrerelease = $m['suffix'] !== '' && !str_starts_with($m['suffix'], '+');
            if ($isPrerelease && !$prerelease) continue;
            $minor = $m[1] . '.' . $m[2];
            if ($minVersion !== null && version_compare($minor, $minVersion, '<')) continue;
            $minors[$minor] = true;
        }
        $minors = array_keys($minors);
        usort($minors, 'version_compare');

        return $minors;
    }
}
