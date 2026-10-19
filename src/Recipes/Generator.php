<?php

namespace Ufo\Packages\Recipes;

use Ufo\Packages\Recipes\Source\PackageNotFound;
use Ufo\Packages\Recipes\Source\VersionSource;

use function array_keys;
use function array_map;
use function basename;
use function glob;
use function in_array;
use function json_encode;
use function str_replace;

/**
 * Builds index.json and recipes/*.json in memory; nothing touches the disk here.
 */
class Generator
{
    public function __construct(
        protected GeneratorConfig $config,
        protected VersionSource $versions,
        protected ?RecipeSource $source = null,
        protected ManifestValidator $validator = new ManifestValidator(),
        protected RecipeBuilder $builder = new RecipeBuilder(),
    ) {
        $this->source ??= new RecipeSource($config->configsDir);
    }

    public function generate(): GenerationResult
    {
        $result = new GenerationResult();
        $index = [];

        foreach ($this->config->bundles as $package => $options) {
            try {
                $minors = VersionTags::minors(
                    $this->versions->versions($package, $options),
                    $options->prerelease,
                    $options->minVersion,
                );
            } catch (PackageNotFound $e) {
                $result->errors[] = "$package: " . $e->getMessage();
                continue;
            }

            if ($minors === []) {
                // an empty list would make Flex report a conflict, so the package is left out
                $result->warnings[] = "$package: no stable releases yet, skipped";
                continue;
            }
            if (!$this->source->exists($package)) {
                $result->errors[] = "$package: has releases but no config in " . $this->source->dir($package);
                continue;
            }

            $files = $this->source->files($package);
            $validated = [];
            $packageOk = true;
            foreach ($minors as $minor) {
                try {
                    $path = $this->source->manifestPath($package, $minor);
                    $manifest = $this->source->readManifest($path);
                } catch (\RuntimeException $e) {
                    $result->errors[] = "$package: " . $e->getMessage();
                    $packageOk = false;
                    break;
                }
                if (!isset($validated[$path])) {
                    $validated[$path] = true;
                    foreach ($this->validator->validate($manifest, $files) as $error) {
                        $result->errors[] = "$package (" . basename($path) . "): $error";
                        $packageOk = false;
                    }
                }
                $result->files[$this->recipePath($package, $minor)] = $this->encode(
                    $this->builder->build($package, $manifest, $files)
                );
            }
            if ($packageOk) {
                $index[$package] = $minors;
            }
        }

        $this->warnOrphans($result);

        $result->files[$this->config->indexFile] = $this->encode([
            'recipes' => $index,
            'branch' => $this->config->branch,
            'is_contrib' => true,
            '_links' => [
                'repository' => 'github.com/' . $this->config->repository,
                'origin_template' => '{package}:{version}@github.com/' . $this->config->repository . ':' . $this->config->branch,
                'recipe_template' => 'https://api.github.com/repos/' . $this->config->repository . '/contents/'
                    . basename($this->config->recipesDir) . '/{package_dotted}.{version}.json',
            ],
        ]);

        foreach (glob($this->config->recipesDir . '/*.json') ?: [] as $existing) {
            if (!isset($result->files[$existing])) {
                $result->stale[] = $existing;
            }
        }

        return $result;
    }

    protected function recipePath(string $package, string $minor): string
    {
        return $this->config->recipesDir . '/' . str_replace('/', '.', $package) . '.' . $minor . '.json';
    }

    protected function warnOrphans(GenerationResult $result): void
    {
        $bundles = array_map(fn(string $p) => str_replace('/', '.', $p), array_keys($this->config->bundles));
        foreach ($this->source->configured() as $dotted) {
            if (!in_array($dotted, $bundles, true)) {
                $result->warnings[] = "$dotted: config exists but the package is not marked true in packages.php";
            }
        }
    }

    protected function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }
}
