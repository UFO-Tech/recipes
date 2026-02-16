<?php

namespace Ufo\Packages\Recipes;

use Symfony\Component\Filesystem\Filesystem;

class JsonDumper extends Filesystem
{
    public function dumpJson(string $filename, array $data): void
    {
        $this->dumpFile($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

}
