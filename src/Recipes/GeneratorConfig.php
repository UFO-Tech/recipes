<?php

namespace Ufo\Packages\Recipes;

use InvalidArgumentException;

use function str_contains;

class GeneratorConfig
{
    /**
     * @param array<string, PackageOptions> $bundles full package name => options
     */
    public function __construct(
        public readonly string $vendor,
        public readonly string $repository,
        public readonly string $branch,
        public readonly string $githubOrg,
        public readonly string $configsDir,
        public readonly string $recipesDir,
        public readonly string $indexFile,
        public readonly array $bundles,
    ) {}

    /**
     * Relative paths in the generator config are resolved against $rootDir.
     */
    public static function load(string $generatorFile, string $packagesFile, string $rootDir): static
    {
        $config = require $generatorFile;
        foreach (['vendor', 'repository', 'branch', 'configs_dir', 'recipes_dir', 'index_file'] as $key) {
            if (empty($config[$key])) {
                throw new InvalidArgumentException("Missing '$key' in $generatorFile");
            }
        }
        $vendor = $config['vendor'];
        $options = $config['packages'] ?? [];

        $bundles = [];
        foreach (require $packagesFile as $name => $needRecipe) {
            if (!$needRecipe) continue;
            $full = static::fullName($name, $vendor);
            $opt = $options[$name] ?? $options[$full] ?? [];
            $bundles[$full] = new PackageOptions(
                minVersion: $opt['min_version'] ?? null,
                prerelease: $opt['prerelease'] ?? false,
                github: $opt['github'] ?? null,
            );
        }

        return new static(
            vendor: $vendor,
            repository: $config['repository'],
            branch: $config['branch'],
            githubOrg: $config['github_org'] ?? '',
            configsDir: $rootDir . '/' . $config['configs_dir'],
            recipesDir: $rootDir . '/' . $config['recipes_dir'],
            indexFile: $rootDir . '/' . $config['index_file'],
            bundles: $bundles,
        );
    }

    public static function fullName(string $name, string $vendor): string
    {
        return str_contains($name, '/') ? $name : $vendor . '/' . $name;
    }
}
