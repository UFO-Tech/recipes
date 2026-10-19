<?php

namespace Ufo\Packages\Recipes;

class GenerationResult
{
    /**
     * @var array<string, string> absolute path => content
     */
    public array $files = [];

    /**
     * @var string[] generated recipes that no longer belong to the index
     */
    public array $stale = [];

    /**
     * @var string[]
     */
    public array $errors = [];

    /**
     * @var string[]
     */
    public array $warnings = [];
}
