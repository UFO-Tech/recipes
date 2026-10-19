<?php

namespace Ufo\Packages\Tests\Recipes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ufo\Packages\Recipes\ManifestValidator;

class ManifestValidatorTest extends TestCase
{
    public function testValidManifest(): void
    {
        $errors = (new ManifestValidator())->validate([
            'bundles' => ['Ufo\\JsonRpcBundle\\UfoJsonRpcBundle' => ['all']],
            'copy-from-package' => ['install/' => '%CONFIG_DIR%/'],
            'env' => ['#1' => 'comment', 'UFO_API_TOKEN' => '%generate(secret)%'],
            'post-install-output' => ['  * ready'],
        ]);

        $this->assertSame([], $errors);
    }

    public static function invalidManifests(): iterable
    {
        yield 'empty' => [[], 'non-empty'];
        yield 'wrapped in manifests' => [['manifests' => ['a/b' => []]], 'wrappers'];
        yield 'unknown key' => [['bundles' => ['A\\B' => ['all']], 'bundle' => []], 'unknown keys: bundle'];
        yield 'files/ref inside manifest' => [['bundles' => ['A\\B' => ['all']], 'ref' => 'x'], 'unknown keys: ref'];
        yield 'not a FQCN' => [['bundles' => ['AcmeBundle' => ['all']]], 'fully qualified'];
        yield 'envs not a list' => [['bundles' => ['A\\B' => 'all']], 'list of environments'];
        yield 'bad env name' => [['env' => ['app-token' => 'x']], 'valid variable name'];
        yield 'env not scalar' => [['env' => ['TOKEN' => ['x']]], 'must be a scalar'];
        yield 'copy target not string' => [['copy-from-package' => ['install/' => 1]], 'must be a string'];
        yield 'copy-from-recipe without files' => [['copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], "not found in files/"];
    }

    #[DataProvider('invalidManifests')]
    public function testInvalidManifest(array $manifest, string $expected): void
    {
        $errors = (new ManifestValidator())->validate($manifest);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString($expected, implode("\n", $errors));
    }

    public function testFilesRequireCopyFromRecipe(): void
    {
        $files = ['config/packages/a.yaml' => ['contents' => ['a: 1'], 'executable' => false]];
        $validator = new ManifestValidator();

        $this->assertStringContainsString(
            'copy-from-recipe is missing',
            implode("\n", $validator->validate(['bundles' => ['A\\B' => ['all']]], $files))
        );
        $this->assertSame([], $validator->validate(['copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $files));
    }
}
