<?php

namespace Ufo\Packages\Recipes\Source;

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Ufo\Packages\Recipes\PackageOptions;

use function array_column;
use function count;
use function explode;

/**
 * Fallback for packages that are not published on Packagist.
 * Set GITHUB_TOKEN to lift the 60 requests/hour anonymous limit.
 */
class GitHubVersionSource implements VersionSource
{
    public const string URL = 'https://api.github.com/repos/%s/tags';
    protected const int PER_PAGE = 100;

    public function __construct(
        protected HttpClientInterface $client,
        protected string $org,
        protected ?string $token = null,
    ) {}

    public function versions(string $package, PackageOptions $options): array
    {
        $repo = $options->github ?? $this->org . '/' . explode('/', $package)[1];
        $headers = ['Accept' => 'application/vnd.github+json'];
        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        $versions = [];
        $page = 1;
        do {
            $response = $this->client->request('GET', sprintf(static::URL, $repo), [
                'headers' => $headers,
                'query' => ['per_page' => static::PER_PAGE, 'page' => $page++],
            ]);
            $status = $response->getStatusCode();
            if ($status === 404) {
                throw new PackageNotFound("GitHub repository $repo not found");
            }
            if ($status === 403 || $status === 429) {
                throw new RuntimeException("GitHub rate limit hit for $repo — set GITHUB_TOKEN");
            }
            if ($status !== 200) {
                throw new RuntimeException("GitHub returned HTTP $status for $repo");
            }
            $tags = $response->toArray();
            $versions = [...$versions, ...array_column($tags, 'name')];
        } while (count($tags) === static::PER_PAGE);

        return $versions;
    }
}
