<?php

namespace Ufo\Packages\Recipes;

use Symfony\Component\Filesystem\Filesystem;

use function file_get_contents;
use function is_file;

class JsonDumper extends Filesystem
{
    /**
     * @return array{changed: string[], removed: string[]} paths that differ from the disk
     */
    public function diff(GenerationResult $result): array
    {
        $changed = [];
        foreach ($result->files as $path => $content) {
            if (!is_file($path) || file_get_contents($path) !== $content) {
                $changed[] = $path;
            }
        }
        return ['changed' => $changed, 'removed' => $result->stale];
    }

    public function apply(GenerationResult $result): array
    {
        $diff = $this->diff($result);
        foreach ($diff['changed'] as $path) {
            $this->dumpFile($path, $result->files[$path]);
        }
        $this->remove($diff['removed']);

        return $diff;
    }
}
