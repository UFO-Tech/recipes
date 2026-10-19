<?php

namespace Ufo\Packages\Recipes;

use function array_diff;
use function array_is_list;
use function array_keys;
use function implode;
use function is_array;
use function is_scalar;
use function is_string;
use function preg_match;
use function str_ends_with;
use function str_starts_with;

/**
 * Catches mistakes Flex would silently ignore: unknown keys, misplaced sections,
 * malformed bundle classes and env names, copy-from-recipe without files.
 */
class ManifestValidator
{
    /** Keys handled by symfony/flex (configurators + Flex itself). */
    public const array KNOWN_KEYS = [
        'bundles', 'copy-from-recipe', 'copy-from-package', 'env', 'dotenv', 'container',
        'makefile', 'composer-scripts', 'composer-commands', 'gitignore', 'dockerfile',
        'docker-compose', 'add-lines', 'post-install-output', 'aliases', 'conflict',
    ];

    protected const string FQCN = '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/';
    protected const string ENV_NAME = '/^[A-Z][A-Z0-9_]*$/';

    /**
     * @param array<string, mixed> $files recipe files, see RecipeSource::files()
     * @return string[] errors, empty when the manifest is fine
     */
    public function validate(array $manifest, array $files = []): array
    {
        $errors = [];
        if ($manifest === [] || array_is_list($manifest)) {
            return ['manifest must be a non-empty JSON object'];
        }

        $unknown = array_diff(array_keys($manifest), static::KNOWN_KEYS);
        if ($unknown) {
            $errors[] = 'unknown keys: ' . implode(', ', $unknown) . ' (allowed: ' . implode(', ', static::KNOWN_KEYS) . ')';
        }
        if (isset($manifest['manifests']) || isset($manifest['manifest'])) {
            $errors[] = 'config must contain the manifest itself, without "manifests"/"manifest" wrappers — the generator adds them';
        }

        foreach ($manifest['bundles'] ?? [] as $class => $envs) {
            if (!preg_match(static::FQCN, $class)) {
                $errors[] = "bundles: '$class' is not a fully qualified class name";
            }
            if (!is_array($envs) || !array_is_list($envs) || $envs === []) {
                $errors[] = "bundles: '$class' must map to a list of environments, e.g. [\"all\"]";
            }
        }

        foreach ($manifest['env'] ?? [] as $name => $value) {
            // Flex treats "#1", "#2" keys as comments
            if (!str_starts_with((string)$name, '#') && !preg_match(static::ENV_NAME, (string)$name)) {
                $errors[] = "env: '$name' is not a valid variable name";
            }
            if (!is_scalar($value)) {
                $errors[] = "env: '$name' must be a scalar";
            }
        }

        foreach (['copy-from-package', 'copy-from-recipe'] as $key) {
            if (!isset($manifest[$key])) continue;
            if (!is_array($manifest[$key])) {
                $errors[] = "$key must be an object {source: target}";
                continue;
            }
            foreach ($manifest[$key] as $from => $to) {
                if (!is_string($to)) {
                    $errors[] = "$key: target for '$from' must be a string";
                }
            }
        }

        foreach ($manifest['copy-from-recipe'] ?? [] as $from => $to) {
            if (!$this->hasFile($files, (string)$from)) {
                $errors[] = "copy-from-recipe: '$from' not found in files/";
            }
        }
        if ($files !== [] && empty($manifest['copy-from-recipe'])) {
            $errors[] = 'files/ is not empty but copy-from-recipe is missing — Flex will not copy them';
        }

        if (isset($manifest['post-install-output']) && !array_is_list((array)$manifest['post-install-output'])) {
            $errors[] = 'post-install-output must be a list of strings';
        }

        return $errors;
    }

    protected function hasFile(array $files, string $from): bool
    {
        foreach (array_keys($files) as $path) {
            if ($path === $from || (str_ends_with($from, '/') && str_starts_with($path, $from))) {
                return true;
            }
        }
        return false;
    }
}
