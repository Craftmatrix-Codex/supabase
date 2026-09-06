<?php

namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class StudioContentStore
{
    /** @return array{data: array<int, array<string, mixed>>, cursor: string|null} */
    public function listSnippets(string $project, array $filters = []): array
    {
        $state = $this->read($project);
        $snippets = array_values(array_filter($state['snippets'], function (array $snippet) use ($filters): bool {
            $folderId = $filters['folder_id'] ?? null;
            if (array_key_exists('folder_id', $filters) && ($snippet['folder_id'] ?? null) !== $folderId) {
                return false;
            }
            $name = trim((string) ($filters['name'] ?? ''));
            return $name === '' || str_contains(strtolower((string) $snippet['name']), strtolower($name));
        }));

        $requestedSortBy = $filters['sort_by'] ?? 'inserted_at';
        $sortBy = in_array($requestedSortBy, ['name', 'inserted_at'], true)
            ? $requestedSortBy
            : 'inserted_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        usort($snippets, function (array $left, array $right) use ($sortBy, $sortOrder): int {
            $comparison = strcmp((string) ($left[$sortBy] ?? ''), (string) ($right[$sortBy] ?? ''));
            return $sortOrder === 'asc' ? $comparison : -$comparison;
        });

        $cursor = null;
        $requestedCursor = $filters['cursor'] ?? null;
        if (is_string($requestedCursor) && $requestedCursor !== '') {
            $index = array_search($requestedCursor, array_column($snippets, 'id'), true);
            if ($index !== false) {
                $snippets = array_slice($snippets, $index + 1);
            }
        }
        $limit = max(1, min(1000, (int) ($filters['limit'] ?? 100)));
        if (count($snippets) > $limit) {
            $snippets = array_slice($snippets, 0, $limit);
            $lastSnippet = end($snippets);
            $cursor = is_array($lastSnippet) ? (string) ($lastSnippet['id'] ?? '') : null;
        }

        return ['data' => $snippets, 'cursor' => $cursor];
    }

    public function getSnippet(string $project, string $id): array
    {
        foreach ($this->read($project)['snippets'] as $snippet) {
            if (($snippet['id'] ?? null) === $id) {
                return $snippet;
            }
        }
        throw new RuntimeException('content not found');
    }

    public function upsertSnippet(string $project, array $input): array
    {
        $id = is_string($input['id'] ?? null) && $input['id'] !== '' ? $input['id'] : (string) Str::uuid();
        if (! is_string($input['name'] ?? null) || trim($input['name']) === '') {
            throw new InvalidArgumentException('name is required');
        }
        $content = $input['content'] ?? [];
        if (! is_array($content) || ! is_string($content['sql'] ?? null)) {
            throw new InvalidArgumentException('content.sql is required');
        }
        $folderId = $input['folder_id'] ?? null;
        $now = now()->toIso8601String();

        return $this->mutate($project, function (array &$state) use ($id, $input, $content, $folderId, $now): array {
            if ($folderId !== null && ! $this->hasFolder($state, $folderId)) {
                throw new InvalidArgumentException('folder not found');
            }
            $existingIndex = array_search($id, array_column($state['snippets'], 'id'), true);
            $existing = $existingIndex === false ? [] : $state['snippets'][$existingIndex];
            $snippet = [
                'id' => $id,
                'inserted_at' => $existing['inserted_at'] ?? $now,
                'updated_at' => $now,
                'type' => 'sql',
                'name' => trim($input['name']),
                'description' => is_string($input['description'] ?? null) ? $input['description'] : '',
                'favorite' => (bool) ($input['favorite'] ?? false),
                'content' => [
                    'sql' => $content['sql'],
                    'content_id' => is_string($content['content_id'] ?? null) ? $content['content_id'] : (string) Str::uuid(),
                    'schema_version' => '1.0',
                ],
                'visibility' => $this->normalizedVisibility($input['visibility'] ?? 'user'),
                'project_id' => 1,
                'folder_id' => $folderId,
                'owner_id' => 1,
                'owner' => ['id' => 1, 'username' => 'johndoe'],
                'updated_by' => ['id' => 1, 'username' => 'johndoe'],
            ];
            if ($existingIndex === false) {
                $state['snippets'][] = $snippet;
            } else {
                $state['snippets'][$existingIndex] = $snippet;
            }
            return $snippet;
        });
    }

    /** @return array<int, array{id: string}> */
    public function deleteSnippets(string $project, array $ids): array
    {
        return $this->mutate($project, function (array &$state) use ($ids): array {
            $state['snippets'] = array_values(array_filter($state['snippets'], fn (array $snippet): bool => ! in_array($snippet['id'] ?? null, $ids, true)));
            return array_map(fn (string $id): array => ['id' => $id], $ids);
        });
    }

    /** @return array{data: array{folders: array<int, array<string, mixed>>, contents: array<int, array<string, mixed>>}, cursor: string|null} */
    public function listFolderContents(string $project, array $filters = []): array
    {
        $state = $this->read($project);
        $folderId = $filters['folder_id'] ?? null;
        $folders = array_values(array_filter($state['folders'], fn (array $folder): bool => $folderId === null || ($folder['id'] ?? null) === $folderId));
        $snippetFilters = $filters;
        if ($folderId !== null) {
            $snippetFilters['folder_id'] = $folderId;
        } else {
            unset($snippetFilters['folder_id']);
        }
        $snippetResult = $this->listSnippets($project, $snippetFilters);
        return ['data' => ['folders' => $folders, 'contents' => $snippetResult['data']], 'cursor' => $snippetResult['cursor']];
    }

    public function createFolder(string $project, string $name): array
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('name is required');
        }
        return $this->mutate($project, function (array &$state) use ($name): array {
            $folder = ['id' => (string) Str::uuid(), 'name' => trim($name), 'owner_id' => 1, 'parent_id' => null, 'project_id' => 1];
            $state['folders'][] = $folder;
            return $folder;
        });
    }

    private function normalizedVisibility(mixed $visibility): string
    {
        return is_string($visibility) && in_array($visibility, ['user', 'project', 'org', 'public'], true)
            ? $visibility
            : 'user';
    }

    private function hasFolder(array $state, string $id): bool
    {
        return in_array($id, array_column($state['folders'], 'id'), true);
    }

    private function pathFor(string $project): string
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $project)) {
            throw new InvalidArgumentException('invalid project');
        }
        $base = config('studio.content_path');
        if (! is_string($base) || $base === '') {
            $base = dirname((string) config('studio.registry_path')) . '/content';
        }
        if (! is_dir($base) && ! mkdir($base, 0700, true) && ! is_dir($base)) {
            throw new RuntimeException('could not create content directory');
        }
        return rtrim($base, '/') . '/' . $project . '.json';
    }

    private function read(string $project): array
    {
        $path = $this->pathFor($project);
        if (! is_file($path)) {
            return ['folders' => [], 'snippets' => []];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? [
            'folders' => is_array($decoded['folders'] ?? null) ? $decoded['folders'] : [],
            'snippets' => is_array($decoded['snippets'] ?? null) ? $decoded['snippets'] : [],
        ] : ['folders' => [], 'snippets' => []];
    }

    private function mutate(string $project, callable $callback): mixed
    {
        $path = $this->pathFor($project);
        $lock = fopen($path . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('could not lock content store');
        }
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('could not lock content store');
            }
            $state = $this->read($project);
            $result = $callback($state);
            $temporary = $path . '.' . Str::uuid()->toString() . '.tmp';
            file_put_contents($temporary, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
            if (! rename($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException('could not persist content store');
            }
            flock($lock, LOCK_UN);
            return $result;
        } finally {
            fclose($lock);
        }
    }
}
