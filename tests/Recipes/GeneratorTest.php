<?php

namespace Ufo\Packages\Tests\Recipes;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Ufo\Packages\Recipes\Generator;
use Ufo\Packages\Recipes\GeneratorConfig;
use Ufo\Packages\Recipes\PackageOptions;
use Ufo\Packages\Recipes\RecipeSource;
use Ufo\Packages\Recipes\Source\ChainVersionSource;
use Ufo\Packages\Recipes\Source\GitHubVersionSource;
use Ufo\Packages\Recipes\Source\PackagistVersionSource;

class GeneratorTest extends TestCase
{
    protected const string FIXTURES = __DIR__ . '/../Fixtures/configs/recipes';

    protected function config(array $bundles, string $recipesDir = '/out/recipes'): GeneratorConfig
    {
        return new GeneratorConfig(
            vendor: 'acme',
            repository: 'Acme/recipes',
            branch: 'main',
            githubOrg: 'Acme',
            configsDir: static::FIXTURES,
            recipesDir: $recipesDir,
            indexFile: '/out/index.json',
            bundles: $bundles,
        );
    }

    /**
     * @param array<string, string[]|null> $packagist package => versions, null = 404
     */
    protected function packagist(array $packagist): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url) use ($packagist): MockResponse {
            foreach ($packagist as $package => $versions) {
                if ($url === sprintf(PackagistVersionSource::URL, $package)) {
                    return $versions === null
                        ? new MockResponse('', ['http_code' => 404])
                        : new MockResponse(json_encode(['packages' => [$package => array_map(fn($v) => ['version' => $v], $versions)]]));
                }
            }
            return new MockResponse('', ['http_code' => 404]);
        });
    }

    public function testBuildsIndexAndRecipesWithOverrides(): void
    {
        $generator = new Generator(
            $this->config(['acme/overrides' => new PackageOptions()]),
            new PackagistVersionSource($this->packagist(['acme/overrides' => ['2.1.3', '1.0.0', '2.0.0', '2.1.0', '3.0.0-beta']])),
        );

        $result = $generator->generate();

        $this->assertSame([], $result->errors);
        $index = json_decode($result->files['/out/index.json'], true);
        $this->assertSame(['acme/overrides' => ['1.0', '2.0', '2.1']], $index['recipes']);
        $this->assertSame('{package}:{version}@github.com/Acme/recipes:main', $index['_links']['origin_template']);

        $level = fn(string $minor) => json_decode($result->files["/out/recipes/acme.overrides.$minor.json"], true)
            ['manifests']['acme/overrides']['manifest']['env']['LEVEL'];
        $this->assertSame('default', $level('1.0'));
        $this->assertSame('major', $level('2.0'));
        $this->assertSame('minor', $level('2.1'));
    }

    public function testRefIsContentHash(): void
    {
        $generator = new Generator(
            $this->config(['acme/overrides' => new PackageOptions()]),
            new PackagistVersionSource($this->packagist(['acme/overrides' => ['1.0.0', '1.1.0', '2.0.0']])),
        );
        $result = $generator->generate();
        $ref = fn(string $minor) => json_decode($result->files["/out/recipes/acme.overrides.$minor.json"], true)
            ['manifests']['acme/overrides']['ref'];

        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $ref('1.0'));
        $this->assertSame($ref('1.0'), $ref('1.1'), 'same content - same ref');
        $this->assertNotSame($ref('1.0'), $ref('2.0'));
    }

    public function testEmbedsFiles(): void
    {
        $generator = new Generator(
            $this->config(['acme/with-files' => new PackageOptions()]),
            new PackagistVersionSource($this->packagist(['acme/with-files' => ['1.0.0']])),
        );
        $result = $generator->generate();

        $this->assertSame([], $result->errors);
        $recipe = json_decode($result->files['/out/recipes/acme.with-files.1.0.json'], true)['manifests']['acme/with-files'];
        $this->assertSame(
            ['contents' => ['acme:', '    enabled: true', ''], 'executable' => false],
            $recipe['files']['config/packages/acme.yaml']
        );
    }

    public function testPackageWithoutReleasesIsSkipped(): void
    {
        $result = (new Generator(
            $this->config(['acme/overrides' => new PackageOptions()]),
            new PackagistVersionSource($this->packagist(['acme/overrides' => []])),
        ))->generate();

        $this->assertSame([], $result->errors);
        $this->assertSame([], json_decode($result->files['/out/index.json'], true)['recipes']);
        $this->assertStringContainsString('no stable releases', implode("\n", $result->warnings));
    }

    public function testReleasesWithoutConfigIsAnError(): void
    {
        $result = (new Generator(
            $this->config(['acme/missing' => new PackageOptions()]),
            new PackagistVersionSource($this->packagist(['acme/missing' => ['1.0.0']])),
        ))->generate();

        $this->assertStringContainsString('no config', implode("\n", $result->errors));
    }

    public function testWarnsAboutOrphanConfigs(): void
    {
        $result = (new Generator(
            $this->config(['acme/overrides' => new PackageOptions()]),
            new PackagistVersionSource($this->packagist(['acme/overrides' => ['1.0.0']])),
        ))->generate();

        $this->assertStringContainsString('acme.orphan: config exists', implode("\n", $result->warnings));
    }

    public function testMarksStaleRecipes(): void
    {
        $dir = sys_get_temp_dir() . '/recipes-test-' . uniqid();
        mkdir($dir);
        touch("$dir/acme.overrides.0.9.json");
        touch("$dir/acme.overrides.1.0.json");

        $result = (new Generator(
            $this->config(['acme/overrides' => new PackageOptions()], $dir),
            new PackagistVersionSource($this->packagist(['acme/overrides' => ['1.0.0']])),
        ))->generate();

        $this->assertSame(["$dir/acme.overrides.0.9.json"], $result->stale);
        array_map('unlink', glob("$dir/*"));
        rmdir($dir);
    }

    public function testFallsBackToPaginatedGitHubTags(): void
    {
        $requests = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = $url;
            if (str_starts_with($url, 'https://repo.packagist.org/')) {
                return new MockResponse('', ['http_code' => 404]);
            }
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
            $this->assertSame(['Authorization: Bearer secret'], $options['normalized_headers']['authorization']);
            $tags = ($query['page'] ?? '1') === '1'
                ? array_map(fn($i) => ['name' => "1.$i.0"], range(0, 99))
                : [['name' => '2.0.0']];
            return new MockResponse(json_encode($tags));
        });
        $versions = new ChainVersionSource(
            new PackagistVersionSource($client),
            new GitHubVersionSource($client, 'Acme', 'secret'),
        );

        $found = $versions->versions('acme/overrides', new PackageOptions());

        $this->assertCount(101, $found);
        $this->assertContains('2.0.0', $found);
        $this->assertCount(3, $requests);
        $this->assertStringStartsWith('https://api.github.com/repos/Acme/overrides/tags', $requests[1]);
    }

    public function testRecipeSourceFallbackChain(): void
    {
        $source = new RecipeSource(static::FIXTURES);

        $this->assertStringEndsWith('/2.1.json', $source->manifestPath('acme/overrides', '2.1'));
        $this->assertStringEndsWith('/2.json', $source->manifestPath('acme/overrides', '2.5'));
        $this->assertStringEndsWith('/default.json', $source->manifestPath('acme/overrides', '3.0'));
    }
}
