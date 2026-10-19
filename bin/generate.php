<?php

use Symfony\Component\HttpClient\HttpClient;
use Ufo\Packages\Recipes\Generator;
use Ufo\Packages\Recipes\GeneratorConfig;
use Ufo\Packages\Recipes\JsonDumper;
use Ufo\Packages\Recipes\Source\ChainVersionSource;
use Ufo\Packages\Recipes\Source\GitHubVersionSource;
use Ufo\Packages\Recipes\Source\PackagistVersionSource;

require_once __DIR__ . '/../vendor/autoload.php';

// Usage: php bin/generate.php [--check]
//   --check  nothing is written; exit 1 if index.json/recipes are out of date
$check = in_array('--check', $argv, true);
$root = dirname(__DIR__);

$config = GeneratorConfig::load($root . '/configs/generator.php', $root . '/configs/packages.php', $root);
$client = HttpClient::create([
    'headers' => ['User-Agent' => 'ufo-tech-recipes-generator (+https://github.com/' . $config->repository . ')'],
    'timeout' => 30,
]);
$versions = new ChainVersionSource(
    new PackagistVersionSource($client),
    new GitHubVersionSource($client, $config->githubOrg, getenv('GITHUB_TOKEN') ?: null),
);

$result = (new Generator($config, $versions))->generate();

$relative = fn(string $path) => substr($path, strlen($root) + 1);
foreach ($result->warnings as $warning) {
    fwrite(STDERR, "warning: $warning\n");
}
if ($result->errors) {
    foreach ($result->errors as $error) {
        fwrite(STDERR, "error: $error\n");
    }
    fwrite(STDERR, "Nothing was written.\n");
    exit(1);
}

$dumper = new JsonDumper();
$diff = $check ? $dumper->diff($result) : $dumper->apply($result);
foreach ($diff['changed'] as $path) {
    echo ($check ? 'outdated: ' : 'written:  ') . $relative($path) . "\n";
}
foreach ($diff['removed'] as $path) {
    echo ($check ? 'stale:    ' : 'removed:  ') . $relative($path) . "\n";
}

$dirty = $diff['changed'] || $diff['removed'];
if (!$dirty) {
    echo "Up to date.\n";
}
exit($check && $dirty ? 1 : 0);
