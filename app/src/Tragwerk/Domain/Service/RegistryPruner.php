<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Service;

use RuntimeException;
use Tragwerk\Domain\Entity\Registry;

use function array_merge;
use function array_slice;
use function array_unique;
use function base64_encode;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function rsort;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use function urlencode;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_URL;
use const SORT_STRING;

/**
 * Prunes old image tags from Docker Hub or OCI v2 registries.
 *
 * Keeps the newest $keepTags tags (sorted alphabetically descending — works because
 * the tag format embeds a timestamp: {appSlug}-{branchSlug}-{YmdHis}-{sha}).
 * Unsupported registries (AWS ECR, Azure ACR, Quay.io) are silently skipped.
 */
final readonly class RegistryPruner
{
    private const string DOCKER_HUB_HOST = 'docker.io';

    /**
     * Deletes ALL tags whose name starts with any of $prefixes.
     *
     * Used when a project is deleted to wipe its registry images entirely.
     *
     * @param list<string> $prefixes e.g. ['myapp-main-', 'myapp-dev-']
     *
     * @return list<string> tags that were deleted
     */
    public function pruneAll(Registry $registry, array $prefixes): array
    {
        if ($registry->url === self::DOCKER_HUB_HOST) {
            return $this->deleteMatchingDockerHub($registry, $prefixes, keepCount: 0);
        }

        return $this->deleteMatchingOci($registry, $prefixes, keepCount: 0);
    }

    /**
     * Deletes tags whose prefix does NOT appear in $activePrefixes.
     *
     * Used for dead-branch cleanup: pass all currently active prefixes so only
     * images for deleted branches are removed.
     *
     * @param list<string> $activePrefixes e.g. ['myapp-main-', 'myapp-dev-']
     *
     * @return list<string> tags that were deleted
     */
    public function pruneOrphaned(Registry $registry, array $activePrefixes): array
    {
        if ($registry->url === self::DOCKER_HUB_HOST) {
            return $this->deleteOrphanedDockerHub($registry, $activePrefixes);
        }

        return $this->deleteOrphanedOci($registry, $activePrefixes);
    }

    /** @return list<string> tags that were deleted */
    public function prune(Registry $registry, string $appSlug, string $branchSlug): array
    {
        $prefix = $appSlug . '-' . $branchSlug . '-';

        if ($registry->url === self::DOCKER_HUB_HOST) {
            return $this->pruneDockerHub($registry, $prefix);
        }

        return $this->pruneOci($registry, $prefix);
    }

    /** @return list<string> */
    private function pruneDockerHub(Registry $registry, string $prefix): array
    {
        $token = $this->dockerHubToken($registry);

        [$namespace, $repo] = $this->splitRepository($registry->repository);

        $url  = 'https://hub.docker.com/v2/repositories/' . $namespace . '/' . $repo
            . '/tags/?page_size=100&ordering=-last_updated';
        $body = $this->httpGet($url, ['Authorization: Bearer ' . $token]);

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, true);

        if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results'])) {
            return [];
        }

        $matching      = [];
        $matchingCache = [];
        foreach ($data['results'] as $tag) {
            if (! is_array($tag)) {
                continue;
            }

            $name = is_string($tag['name'] ?? null) ? $tag['name'] : '';
            if ($name === '' || ! str_starts_with($name, $prefix)) {
                continue;
            }

            if (str_ends_with($name, '-cache')) {
                $matchingCache[] = $name;
            } else {
                $matching[] = $name;
            }
        }

        rsort($matching, SORT_STRING);
        rsort($matchingCache, SORT_STRING);
        $toDelete = array_merge(
            array_slice($matching, $registry->keepTags),
            array_slice($matchingCache, $registry->keepTags),
        );
        $deleted  = [];

        foreach ($toDelete as $tag) {
            $deleteUrl = 'https://hub.docker.com/v2/repositories/' . $namespace . '/' . $repo . '/tags/' . $tag . '/';
            $status    = $this->httpDelete($deleteUrl, ['Authorization: Bearer ' . $token]);

            if ($status < 200 || $status >= 300) {
                continue;
            }

            $deleted[] = $tag;
        }

        return $deleted;
    }

    /** @return list<string> */
    private function pruneOci(Registry $registry, string $prefix): array
    {
        [$token, $authType] = $this->ociToken($registry);
        $repo               = $registry->repository;
        $base               = 'https://' . $registry->url . '/v2/' . $repo;
        $auth               = 'Authorization: ' . $authType . ' ' . $token;

        $body = $this->httpGet($base . '/tags/list', [$auth]);

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, true);

        if (! is_array($data) || ! isset($data['tags']) || ! is_array($data['tags'])) {
            return [];
        }

        $matching      = [];
        $matchingCache = [];
        foreach ($data['tags'] as $tag) {
            if (! is_string($tag) || ! str_starts_with($tag, $prefix)) {
                continue;
            }

            if (str_ends_with($tag, '-cache')) {
                $matchingCache[] = $tag;
            } else {
                $matching[] = $tag;
            }
        }

        rsort($matching, SORT_STRING);
        rsort($matchingCache, SORT_STRING);
        $toDelete = array_merge(
            array_slice($matching, $registry->keepTags),
            array_slice($matchingCache, $registry->keepTags),
        );
        $deleted  = [];

        foreach ($toDelete as $tag) {
            $digest = $this->ociDigest($base . '/manifests/' . $tag, [$auth]);

            if ($digest === '') {
                continue;
            }

            $status = $this->httpDelete($base . '/manifests/' . $digest, [$auth]);

            if ($status < 200 || $status >= 300) {
                continue;
            }

            $deleted[] = $tag;
        }

        return $deleted;
    }

    private function dockerHubToken(Registry $registry): string
    {
        $payload = (string) json_encode(['username' => $registry->username, 'password' => $registry->password]);
        $ch      = curl_init();

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize curl');
        }

        curl_setopt($ch, CURLOPT_URL, 'https://hub.docker.com/v2/users/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $body   = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($status !== 200) {
            throw new RuntimeException('Docker Hub login failed (HTTP ' . $status . ')');
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, true);

        if (! is_array($data) || ! is_string($data['token'] ?? null)) {
            throw new RuntimeException('Docker Hub login response missing token');
        }

        return $data['token'];
    }

    /** @return array{0: string, 1: 'Basic'|'Bearer'} token + auth scheme */
    private function ociToken(Registry $registry): array
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize curl');
        }

        $credentials = base64_encode($registry->username . ':' . $registry->password);
        $basicAuth   = 'Authorization: Basic ' . $credentials;

        curl_setopt($ch, CURLOPT_URL, 'https://' . $registry->url . '/v2/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [$basicAuth]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($status === 200) {
            return [$credentials, 'Basic'];
        }

        $repo     = $registry->repository;
        $scope    = urlencode('repository:' . $repo . ':pull,delete');
        $tokenUrl = 'https://' . $registry->url . '/token?service=' . $registry->url . '&scope=' . $scope;
        $body     = $this->httpGet($tokenUrl, [$basicAuth]);

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, true);

        if (is_array($data) && is_string($data['token'] ?? null)) {
            return [$data['token'], 'Bearer'];
        }

        if (is_array($data) && is_string($data['access_token'] ?? null)) {
            return [$data['access_token'], 'Bearer'];
        }

        throw new RuntimeException('Could not obtain OCI registry token from ' . $registry->url);
    }

    /**
     * @param non-empty-string $url
     * @param list<string>     $headers
     */
    private function ociDigest(string $url, array $headers): string
    {
        $ch = curl_init();

        if ($ch === false) {
            return '';
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_HEADER, true);
        $acceptHeader = 'Accept: application/vnd.docker.distribution.manifest.v2+json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, [$acceptHeader]));

        $response = (string) curl_exec($ch);

        foreach (explode("\r\n", $response) as $line) {
            if (str_starts_with(strtolower($line), 'docker-content-digest:')) {
                $pos = strpos($line, ':');

                return $pos !== false ? trim(substr($line, $pos + 1)) : '';
            }
        }

        return '';
    }

    /**
     * @param non-empty-string $url
     * @param list<string>     $headers
     */
    private function httpGet(string $url, array $headers): string
    {
        $ch = curl_init();

        if ($ch === false) {
            return '';
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return (string) curl_exec($ch);
    }

    /**
     * @param non-empty-string $url
     * @param list<string>     $headers
     */
    private function httpDelete(string $url, array $headers): int
    {
        $ch = curl_init();

        if ($ch === false) {
            return 0;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch);

        return (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }

    /**
     * @param list<string> $prefixes
     *
     * @return list<string>
     */
    private function deleteMatchingDockerHub(Registry $registry, array $prefixes, int $keepCount): array
    {
        $token = $this->dockerHubToken($registry);

        [$namespace, $repo] = $this->splitRepository($registry->repository);

        $url  = 'https://hub.docker.com/v2/repositories/' . $namespace . '/' . $repo
            . '/tags/?page_size=100&ordering=-last_updated';
        $body = $this->httpGet($url, ['Authorization: Bearer ' . $token]);

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, true);

        if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results'])) {
            return [];
        }

        $byPrefix = [];
        foreach ($data['results'] as $tag) {
            if (! is_array($tag)) {
                continue;
            }

            $name = is_string($tag['name'] ?? null) ? $tag['name'] : '';
            if ($name === '') {
                continue;
            }

            foreach ($prefixes as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $byPrefix[$prefix][] = $name;
                    break;
                }
            }
        }

        $toDelete = [];
        foreach ($byPrefix as $tags) {
            rsort($tags, SORT_STRING);
            foreach (array_slice($tags, $keepCount) as $tag) {
                $toDelete[] = $tag;
            }
        }

        $deleted = [];
        foreach (array_unique($toDelete) as $tag) {
            $deleteUrl = 'https://hub.docker.com/v2/repositories/' . $namespace . '/' . $repo . '/tags/' . $tag . '/';
            $status    = $this->httpDelete($deleteUrl, ['Authorization: Bearer ' . $token]);

            if ($status < 200 || $status >= 300) {
                continue;
            }

            $deleted[] = $tag;
        }

        return $deleted;
    }

    /**
     * @param list<string> $prefixes
     *
     * @return list<string>
     */
    private function deleteMatchingOci(Registry $registry, array $prefixes, int $keepCount): array
    {
        [$token, $authType] = $this->ociToken($registry);
        $repo               = $registry->repository;
        $base               = 'https://' . $registry->url . '/v2/' . $repo;
        $auth               = 'Authorization: ' . $authType . ' ' . $token;

        $body = $this->httpGet($base . '/tags/list', [$auth]);

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, true);

        if (! is_array($data) || ! isset($data['tags']) || ! is_array($data['tags'])) {
            return [];
        }

        $byPrefix = [];
        foreach ($data['tags'] as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            foreach ($prefixes as $prefix) {
                if (str_starts_with($tag, $prefix)) {
                    $byPrefix[$prefix][] = $tag;
                    break;
                }
            }
        }

        $toDelete = [];
        foreach ($byPrefix as $tags) {
            rsort($tags, SORT_STRING);
            foreach (array_slice($tags, $keepCount) as $tag) {
                $toDelete[] = $tag;
            }
        }

        $deleted = [];
        foreach (array_unique($toDelete) as $tag) {
            $digest = $this->ociDigest($base . '/manifests/' . $tag, [$auth]);

            if ($digest === '') {
                continue;
            }

            $status = $this->httpDelete($base . '/manifests/' . $digest, [$auth]);

            if ($status < 200 || $status >= 300) {
                continue;
            }

            $deleted[] = $tag;
        }

        return $deleted;
    }

    /**
     * @param list<string> $activePrefixes
     *
     * @return list<string>
     */
    private function deleteOrphanedDockerHub(Registry $registry, array $activePrefixes): array
    {
        $token = $this->dockerHubToken($registry);

        [$namespace, $repo] = $this->splitRepository($registry->repository);

        $url  = 'https://hub.docker.com/v2/repositories/' . $namespace . '/' . $repo
            . '/tags/?page_size=100&ordering=-last_updated';
        $body = $this->httpGet($url, ['Authorization: Bearer ' . $token]);

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, true);

        if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results'])) {
            return [];
        }

        $toDelete = [];
        foreach ($data['results'] as $tag) {
            if (! is_array($tag)) {
                continue;
            }

            $name = is_string($tag['name'] ?? null) ? $tag['name'] : '';
            if ($name === '') {
                continue;
            }

            $isActive = false;
            foreach ($activePrefixes as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $isActive = true;
                    break;
                }
            }

            if ($isActive) {
                continue;
            }

            $toDelete[] = $name;
        }

        $deleted = [];
        foreach ($toDelete as $tag) {
            $deleteUrl = 'https://hub.docker.com/v2/repositories/' . $namespace . '/' . $repo . '/tags/' . $tag . '/';
            $status    = $this->httpDelete($deleteUrl, ['Authorization: Bearer ' . $token]);

            if ($status < 200 || $status >= 300) {
                continue;
            }

            $deleted[] = $tag;
        }

        return $deleted;
    }

    /**
     * @param list<string> $activePrefixes
     *
     * @return list<string>
     */
    private function deleteOrphanedOci(Registry $registry, array $activePrefixes): array
    {
        [$token, $authType] = $this->ociToken($registry);
        $repo               = $registry->repository;
        $base               = 'https://' . $registry->url . '/v2/' . $repo;
        $auth               = 'Authorization: ' . $authType . ' ' . $token;

        $body = $this->httpGet($base . '/tags/list', [$auth]);

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, true);

        if (! is_array($data) || ! isset($data['tags']) || ! is_array($data['tags'])) {
            return [];
        }

        $toDelete = [];
        foreach ($data['tags'] as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $isActive = false;
            foreach ($activePrefixes as $prefix) {
                if (str_starts_with($tag, $prefix)) {
                    $isActive = true;
                    break;
                }
            }

            if ($isActive) {
                continue;
            }

            $toDelete[] = $tag;
        }

        $deleted = [];
        foreach ($toDelete as $tag) {
            $digest = $this->ociDigest($base . '/manifests/' . $tag, [$auth]);

            if ($digest === '') {
                continue;
            }

            $status = $this->httpDelete($base . '/manifests/' . $digest, [$auth]);

            if ($status < 200 || $status >= 300) {
                continue;
            }

            $deleted[] = $tag;
        }

        return $deleted;
    }

    /** @return array{0: string, 1: string} */
    private function splitRepository(string $repository): array
    {
        $parts = explode('/', $repository, 2);

        return [$parts[0], $parts[1] ?? $parts[0]];
    }
}
