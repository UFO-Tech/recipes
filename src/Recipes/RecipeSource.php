<?php

namespace Ufo\Packages\Recipes;

use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function array_map;
use function base64_encode;
use function explode;
use function file_get_contents;
use function glob;
use function is_dir;
use function is_file;
use function json_decode;
use function ksort;
use function mb_check_encoding;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Reads recipe sources from configs/recipes/<vendor>.<name>/:
 *   X.Y.json | X.json | default.json — manifest, the most specific one wins
 *   files/                           — embedded into the recipe "files" (for copy-from-recipe);
 *                                      a file starting with #! is marked executable
 */
class RecipeSource
{
    public const string DEFAULT = 'default';
    public const string FILES_DIR = 'files';

    public function __construct(protected string $configsDir) {}

    public function dir(string $package): string
    {
        return $this->configsDir . '/' . str_replace('/', '.', $package);
    }

    public function exists(string $package): bool
    {
        return is_dir($this->dir($package));
    }

    /**
     * @return string[] dotted names of all configured packages
     */
    public function configured(): array
    {
        return array_map('basename', glob($this->configsDir . '/*', GLOB_ONLYDIR) ?: []);
    }

    /**
     * Path of the manifest file that applies to the given X.Y minor.
     */
    public function manifestPath(string $package, string $minor): string
    {
        $major = explode('.', $minor)[0];
        foreach ([$minor, $major, static::DEFAULT] as $name) {
            $path = $this->dir($package) . '/' . $name . '.json';
            if (is_file($path)) {
                return $path;
            }
        }
        throw new RuntimeException("No recipe config for $package $minor in " . $this->dir($package));
    }

    public function readManifest(string $path): array
    {
        try {
            $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid JSON in $path: " . $e->getMessage(), previous: $e);
        }
        if (!is_array($manifest)) {
            throw new RuntimeException("$path must contain a JSON object");
        }
        return $manifest;
    }

    /**
     * @return array<string, array{contents: string[]|string, executable: bool}>
     */
    public function files(string $package): array
    {
        $root = $this->dir($package) . '/' . static::FILES_DIR;
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $contents = file_get_contents($file->getPathname());
            $files[$relative] = [
                // Flex joins line arrays with "\n" and base64-decodes strings
                'contents' => mb_check_encoding($contents, 'UTF-8') ? explode("\n", $contents) : base64_encode($contents),
                // file modes are unreliable on mounted volumes, so a shebang decides
                'executable' => str_starts_with($contents, '#!'),
            ];
        }
        ksort($files);

        return $files;
    }
}
