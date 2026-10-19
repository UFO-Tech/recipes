<?php

namespace Ufo\Packages\Recipes;

use function json_encode;
use function sha1;

class RecipeBuilder
{
    /**
     * Wraps a manifest into the Flex recipe format. "ref" is a hash of the content,
     * so it changes exactly when the recipe changes and `composer recipes` sees updates.
     */
    public function build(string $package, array $manifest, array $files = []): array
    {
        $recipe = [
            'manifest' => $manifest,
            'files' => $files,
        ];
        $recipe['ref'] = sha1(json_encode($recipe, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return ['manifests' => [$package => $recipe]];
    }
}
