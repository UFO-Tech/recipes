<?php

use Ufo\Packages\Recipes\JsonDumper;
use Ufo\Packages\Recipes\TagRepository;
use Ufo\Packages\UfoPackage;

require_once __DIR__ . '/../vendor/autoload.php';

const RECIPES_DIR = 'recipes';
const CONFIGS_DIR = 'configs';

$repo = new TagRepository();
$dumper = new JsonDumper();
foreach (UfoPackage::allBundles() as $bundle) {
    $repo->addBundleRepositories($bundle);
}
$versions = $repo->parse();

$dumper->dumpJson('index.json', [
    'recipes' => $versions,
    'branch' => 'main',
    'is_contrib' => true,
    '_links' => [
        'repository' => 'github.com/UFO-Tech/recipes',
        'origin_template' => '{package}:{version}@github.com/UFO-Tech/recipes:main',
        'recipe_template' => 'https://api.github.com/repos/UFO-Tech/recipes/contents/'.RECIPES_DIR.'/{package_dotted}.{version}.json',
    ],
]);

foreach (UfoPackage::allBundles() as $bundle) {
    foreach ($versions[$bundle] as $version) {
        $dotBundle = str_replace('/', '.', $bundle);
        $dumper->copy(
            __DIR__ . '/../' . CONFIGS_DIR . '/' . $dotBundle . '.json',
            RECIPES_DIR . '/' . $dotBundle . '.' . $version . '.json'
        );
    }

}


