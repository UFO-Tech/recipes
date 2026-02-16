<?php

namespace Ufo\Packages\Recipes;

use Symfony\Component\HttpClient\CurlHttpClient;

class TagRepository
{
    protected array $bundleRepositories = [];

    protected CurlHttpClient $client;

    /**
     * @var VersionTags[]
     */
    protected array $versions = [];

    public function __construct()
    {
        $this->client = new CurlHttpClient();
    }

    /**
     * @param string $bundleRepositories
     * @return $this
     */
    public function addBundleRepositories(string $bundleRepositories): static
    {
        $this->bundleRepositories[] = $bundleRepositories;
        return $this;
    }

    protected function getTagsFromRepository(string $repo)
    {
        $apiUrl = "https://api.github.com/repos/$repo/tags";

        $request = $this->client->request(
            'GET',
            $apiUrl,
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'My-User-Agent',
                ],
            ]
        );

        $response = $request->getContent();

        $ver = new VersionTags(json_decode($response, true));
        $this->versions[$repo] = $ver->getVersions();
    }

    public function parse(): array
    {
        foreach ($this->bundleRepositories as $repository) {
            $this->getTagsFromRepository($repository);
        }
        return $this->getVersions();
    }

    /**
     * @return array
     */
    public function getVersions(): array
    {
        return $this->versions;
    }
}
