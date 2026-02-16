<?php

namespace UfoTech\Recipes;

class VersionTags
{
    protected array $versions = [];
    public function __construct(array $tags)
    {
        foreach ($tags as $tag) {
            $semVer = explode('.', $tag['name']);
            if (count($semVer) < 3) continue;
            $ver = $semVer[0] . '.' . $semVer[1];
            $this->versions[$ver] ??= $ver;
        }
    }

    /**
     * @return array
     */
    public function getVersions(): array
    {
        return $this->versions;
    }
}
