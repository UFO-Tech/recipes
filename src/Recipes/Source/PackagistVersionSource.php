<?php

namespace Ufo\Packages\Recipes\Source;

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Ufo\Packages\Recipes\PackageOptions;

use function array_column;

/**
 * Composer v2 metadata: all tagged releases in one request, no GitHub rate limit.
 */
class PackagistVersionSource implements VersionSource
{
    public const string URL = 'https://repo.packagist.org/p2/%s.json';

    public function __construct(protected HttpClientInterface $client) {}

    public function versions(string $package, PackageOptions $options): array
    {
        $response = $this->client->request('GET', sprintf(static::URL, $package));
        $status = $response->getStatusCode();
        if ($status === 404) {
            throw new PackageNotFound("$package is not on Packagist");
        }
        if ($status !== 200) {
            throw new RuntimeException("Packagist returned HTTP $status for $package");
        }

        return array_column($response->toArray()['packages'][$package] ?? [], 'version');
    }
}
