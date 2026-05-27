<?php

namespace Mygento\Deployer\Model;

use GuzzleHttp\Client;

class GithubApi
{
    /** @return string[] */
    public function findRelease(string $repo): array
    {
        $client = new Client([
            'http_errors' => true,
        ]);
        $apiUrl = "https://api.github.com/repos/{$repo}/releases/latest";

        $response = $client->get($apiUrl);

        $releaseData = json_decode((string) $response->getBody(), true);

        $assets = $releaseData['assets'] ?? [];

        return array_map(fn (array $al): string => $al['browser_download_url'], $assets);
    }

    public function download(string $url): void
    {
        $filename = basename(
            parse_url($url, PHP_URL_PATH)
        );
        $client = new Client([
            'http_errors' => true,
        ]);
        $client->request(
            'GET',
            $url,
            [
                'sink' => $filename,
            ]
        );
    }
}
