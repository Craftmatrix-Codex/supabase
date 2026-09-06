<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StudioController
{
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function utcTime(): JsonResponse
    {
        return response()->json(['utcTime' => now('UTC')->toIso8601String()]);
    }

    public function apiNotFound(): JsonResponse
    {
        return response()->json([
            'error' => ['message' => 'Studio API endpoint not implemented'],
        ], 404);
    }

    public function apiRest(string $project): JsonResponse
    {
        $this->projectRecord($project);
        return response()->json([]);
    }

    public function aiSqlCheckApiKey(): JsonResponse
    {
        return response()->json(['hasKey' => false]);
    }

    public function jwtSecretUpdateStatus(string $project): JsonResponse
    {
        $this->assertProject($project);

        return response()->json(['update_status' => null]);
    }

    public function postgrestConfig(string $project): JsonResponse
    {
        $this->assertProject($project);

        return response()->json([
            'db_anon_role' => 'anon',
            'db_extra_search_path' => env('PGRST_DB_EXTRA_SEARCH_PATH', 'public'),
            'db_schema' => 'public',
            'jwt_secret' => '',
            'max_rows' => (int) env('PGRST_DB_MAX_ROWS', 1000),
            'role_claim_key' => '.role',
        ]);
    }

    public function analytics(string $project, string $name): JsonResponse
    {
        $this->assertProject($project);
        abort_unless($name !== '', 404);

        return response()->json(['result' => []]);
    }

    public function organizations(): JsonResponse
    {
        return response()->json([]);
    }

    public function project(string $project): JsonResponse
    {
        $record = $this->projectRecord($project);
        $restUrl = $this->projectRestUrl($record);
        unset($record['scope']);

        return response()->json(array_merge($record, [
            'connectionString' => '',
            'restUrl' => $restUrl,
        ]));
    }

    public function profile(): JsonResponse
    {
        $project = $this->projectRecord('default');
        $publicProject = $project;
        unset($publicProject['scope']);

        return response()->json([
            'id' => 1,
            'primary_email' => 'johndoe@supabase.io',
            'username' => 'johndoe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'organizations' => [[
                'id' => 1,
                'name' => env('DEFAULT_ORGANIZATION_NAME', 'Default Organization'),
                'slug' => 'default-org-slug',
                'billing_email' => 'billing@supabase.co',
                'projects' => [array_merge($publicProject, ['connectionString' => ''])],
            ]],
        ]);
    }

    public function deploymentCommit(): JsonResponse
    {
        return response()->json([
            'commitSha' => env('VERCEL_GIT_COMMIT_SHA', 'development'),
            'commitTime' => 'unknown',
        ]);
    }

    public function cliReleaseVersion(): JsonResponse
    {
        return response()->json([
            'current' => env('CURRENT_CLI_VERSION') ? 'v' . env('CURRENT_CLI_VERSION') : null,
        ]);
    }

    public function featureOverrides(): JsonResponse
    {
        return response()->json(['disabled_features' => []]);
    }

    public function deploymentMode(): JsonResponse
    {
        return response()->json(['is_cli_mode' => false]);
    }

    public function databases(string $project): JsonResponse
    {
        $record = $this->projectRecord($project);
        $database = $record['scope']['database'] ?? [];

        return response()->json([[
            'cloud_provider' => 'localhost',
            'connectionString' => '',
            'connection_string_read_only' => '',
            'db_host' => config('studio.db_host'),
            'db_name' => $database['name'] ?? 'postgres',
            'db_port' => config('studio.db_port'),
            'db_user' => $database['role'] ?? 'postgres',
            'identifier' => $project,
            'inserted_at' => '',
            'region' => 'local',
            'restUrl' => $this->projectRestUrl($record),
            'size' => '',
            'status' => 'ACTIVE_HEALTHY',
        ]]);
    }

    public function apiKeys(string $project): JsonResponse
    {
        $this->assertProject($project);

        return response()->json([]);
    }

    public function githubAuthorization(): Response
    {
        return new Response('null', 200, ['Content-Type' => 'application/json']);
    }

    public function projects(): JsonResponse
    {
        return response()->json(array_map(fn (array $project): array => $this->publicProject($project), $this->projectRecords()));
    }

    public function currentProject(): JsonResponse
    {
        $project = collect($this->projectRecords())->firstWhere('current', true);

        return response()->json(['project' => is_array($project) ? $this->publicProject($project) : null]);
    }

    public function runLints(string $project): JsonResponse
    {
        $this->projectRecord($project);

        return response()->json([]);
    }

    public function pgMetaQuery(Request $request, string $project): JsonResponse
    {
        $this->assertProject($project);
        $query = $request->input('query');
        abort_unless(is_string($query) && trim($query) !== '', 422, 'query is required');

        $key = $request->query('key');
        if (is_string($key) && str_starts_with($key, 'entity-types-')) {
            return response()->json([
                ['data' => ['entities' => [], 'count' => 0]],
            ]);
        }

        // Other metadata queries currently use an empty result set as the
        // self-hosted no-schema state.
        return response()->json([]);
    }

    public function content(string $project): JsonResponse
    {
        $this->assertProject($project);

        return response()->json(['data' => [], 'cursor' => null]);
    }

    public function contentCount(string $project): JsonResponse
    {
        $this->assertProject($project);

        return response()->json(['shared' => 0, 'favorites' => 0, 'private' => 0]);
    }

    public function contentFolders(string $project): JsonResponse
    {
        $this->assertProject($project);

        return response()->json([
            'data' => ['folders' => [], 'contents' => []],
            'cursor' => null,
        ]);
    }

    public function emptyArray(string $project): JsonResponse
    {
        $this->assertProject($project);

        return response()->json([]);
    }

    public function storageBuckets(Request $request, string $project): JsonResponse
    {
        $record = $this->projectRecord($project);
        $buckets = $this->projectBucketRecords($record);
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $buckets = array_values(array_filter($buckets, function (array $bucket) use ($search): bool {
                return str_contains(strtolower($bucket['id']), strtolower($search))
                    || str_contains(strtolower($bucket['name']), strtolower($search));
            }));
        }

        $sortColumn = $request->query('sortColumn', 'created_at');
        $sortColumn = is_string($sortColumn) && in_array($sortColumn, ['id', 'name', 'updated_at', 'created_at'], true)
            ? $sortColumn
            : 'created_at';
        $sortOrder = $request->query('sortOrder', 'desc');
        $sortOrder = is_string($sortOrder) && in_array($sortOrder, ['asc', 'desc'], true)
            ? $sortOrder
            : 'desc';

        usort($buckets, function (array $left, array $right) use ($sortColumn, $sortOrder): int {
            $comparison = strcmp((string) $left[$sortColumn], (string) $right[$sortColumn]);
            if ($comparison === 0) {
                $comparison = strcmp($left['id'], $right['id']);
            }

            return $sortOrder === 'asc' ? $comparison : -$comparison;
        });

        $limit = $this->boundedQueryInteger($request->query('limit'), 100, 1, 1000);
        $offset = $this->boundedQueryInteger($request->query('offset'), 0, 0, PHP_INT_MAX);

        return response()->json(array_values(array_slice($buckets, $offset, $limit)));
    }

    /** @return array<int, array<string, mixed>> */
    private function projectBucketRecords(array $record): array
    {
        $storage = $record['scope']['storage'] ?? [];
        $configured = is_array($storage) && is_array($storage['buckets'] ?? null)
            ? $storage['buckets']
            : (is_array($record['buckets'] ?? null) ? $record['buckets'] : []);

        if ($configured === []
            && in_array($record['status'] ?? null, ['ready', 'provisioned', 'ACTIVE_HEALTHY'], true)
            && is_array($storage)
            && is_string($storage['bucket'] ?? null)
            && $storage['bucket'] !== '') {
            $timestamp = is_string($record['inserted_at'] ?? null) && $record['inserted_at'] !== ''
                ? $record['inserted_at']
                : now()->toIso8601String();
            $configured[] = [
                'id' => $storage['bucket'],
                'name' => $storage['bucket'],
                'owner' => '',
                'public' => false,
                'type' => 'STANDARD',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        $result = [];
        foreach ($configured as $bucket) {
            if (! is_array($bucket) || ! is_string($bucket['id'] ?? null) || $bucket['id'] === '') {
                continue;
            }

            $createdAt = is_string($bucket['created_at'] ?? null) && $bucket['created_at'] !== ''
                ? $bucket['created_at']
                : (string) ($record['inserted_at'] ?? now()->toIso8601String());
            $updatedAt = is_string($bucket['updated_at'] ?? null) && $bucket['updated_at'] !== ''
                ? $bucket['updated_at']
                : $createdAt;
            $normalized = [
                'id' => $bucket['id'],
                'name' => is_string($bucket['name'] ?? null) && $bucket['name'] !== '' ? $bucket['name'] : $bucket['id'],
                'owner' => is_string($bucket['owner'] ?? null) ? $bucket['owner'] : '',
                'public' => (bool) ($bucket['public'] ?? false),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
            if (is_array($bucket['allowed_mime_types'] ?? null)) {
                $normalized['allowed_mime_types'] = array_values(array_filter($bucket['allowed_mime_types'], 'is_string'));
            }
            if (is_numeric($bucket['file_size_limit'] ?? null) && (int) $bucket['file_size_limit'] >= 0) {
                $normalized['file_size_limit'] = (int) $bucket['file_size_limit'];
            }
            if (is_string($bucket['type'] ?? null) && $bucket['type'] !== '') {
                $normalized['type'] = $bucket['type'];
            }
            $result[] = $normalized;
        }

        return $result;
    }

    private function boundedQueryInteger(mixed $value, int $default, int $minimum, int $maximum): int
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }
        if (! is_string($value) && ! is_int($value)) {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false) {
            return $default;
        }

        return min($maximum, max($minimum, (int) $parsed));
    }

    public function settings(string $project): JsonResponse
    {
        $record = $this->projectRecord($project);
        $database = $record['scope']['database'] ?? [];
        $endpoint = $this->projectEndpointParts($record);

        return response()->json([
            'app_config' => [
                'db_schema' => 'public',
                'endpoint' => $endpoint['host'],
                'storage_endpoint' => $endpoint['host'],
                'protocol' => $endpoint['protocol'],
            ],
            'cloud_provider' => 'local',
            'db_dns_name' => '-',
            'db_host' => config('database.connections.pgsql.host', 'localhost'),
            'db_ip_addr_config' => 'legacy',
            'db_name' => $database['name'] ?? config('database.connections.pgsql.database', 'postgres'),
            'db_port' => (int) config('database.connections.pgsql.port', 5432),
            'db_user' => $database['role'] ?? config('database.connections.pgsql.username', 'postgres'),
            'inserted_at' => $record['inserted_at'] ?? now()->toIso8601String(),
            'jwt_secret' => '',
            'name' => $record['name'],
            'ref' => $project,
            'region' => 'local',
            'service_api_keys' => [
                ['api_key' => '', 'name' => 'anon key', 'tags' => 'anon'],
                ['api_key' => '', 'name' => 'service_role key', 'tags' => 'service_role'],
            ],
            'ssl_enforced' => false,
            'status' => 'ACTIVE_HEALTHY',
        ]);
    }

    public function client(Request $request): Response
    {
        $shell = config('studio.client_shell');
        abort_unless(is_string($shell) && is_file($shell), 503, 'Studio client bundle is unavailable');

        return response(file_get_contents($shell), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    private function projectRecord(string $project): array
    {
        $record = collect($this->projectRecords())->firstWhere('ref', $project);
        abort_unless(is_array($record), 404);

        return $record;
    }

    private function assertProject(string $project): void
    {
        $this->projectRecord($project);
    }

    private function publicProject(array $project): array
    {
        unset($project['scope']);
        return $project;
    }

    private function projectRecords(): array
    {
        $configured = array_values(array_filter(config('studio.projects', []), 'is_array'));
        $path = config('studio.registry_path');
        if (! is_string($path) || ! is_file($path)) {
            return $configured;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ! is_array($decoded['projects'] ?? null)) {
            return $configured;
        }

        $currentId = is_string($decoded['currentProjectId'] ?? null) ? $decoded['currentProjectId'] : null;
        $records = [];
        foreach ($decoded['projects'] as $project) {
            if (! is_array($project) || ! is_string($project['id'] ?? null)) {
                continue;
            }
            $id = $project['id'];
            $records[] = [
                'id' => $id,
                'ref' => $id,
                'name' => is_string($project['name'] ?? null) ? $project['name'] : $id,
                'organization_id' => 1,
                'cloud_provider' => 'localhost',
                'status' => $this->nativeProjectStatus($project['status'] ?? null),
                'region' => 'local',
                'inserted_at' => $project['createdAt'] ?? '',
                'current' => $currentId !== null ? $id === $currentId : (bool) ($project['current'] ?? false),
                'scope' => is_array($project['scope'] ?? null) ? $project['scope'] : [],
            ];
        }

        $known = array_column($records, 'ref');
        foreach ($configured as $fallback) {
            if (! in_array($fallback['ref'] ?? null, $known, true)) {
                $fallback['current'] = $currentId !== null
                    ? ($fallback['ref'] ?? null) === $currentId
                    : (bool) ($fallback['current'] ?? false);
                $records[] = $fallback;
            }
        }

        return $records;
    }

    private function nativeProjectStatus(mixed $status): string
    {
        if (! is_string($status) || $status === '') {
            return 'UNKNOWN';
        }

        return match ($status) {
            'ready', 'provisioned' => 'ACTIVE_HEALTHY',
            'registered', 'provisioning', 'starting' => 'COMING_UP',
            'failed' => 'INIT_FAILED',
            default => $status,
        };
    }

    private function projectRestUrl(array $record): string
    {
        return rtrim($this->projectEndpoint($record), '/') . '/rest/v1';
    }

    private function projectEndpoint(array $record): string
    {
        $publicUrl = $record['scope']['publicUrl'] ?? null;
        return is_string($publicUrl) && $publicUrl !== ''
            ? $publicUrl
            : (string) config('studio.project_endpoint', 'http://localhost');
    }

    private function projectEndpointParts(array $record): array
    {
        $endpoint = $this->projectEndpoint($record);
        $parsed = parse_url($endpoint);

        if (is_array($parsed) && is_string($parsed['host'] ?? null)) {
            $host = $parsed['host'];
            if (isset($parsed['port'])) {
                $host .= ':' . $parsed['port'];
            }

            return [
                'host' => $host,
                'protocol' => is_string($parsed['scheme'] ?? null) ? $parsed['scheme'] : 'http',
            ];
        }

        return [
            'host' => preg_replace('#^https?://#', '', rtrim($endpoint, '/')),
            'protocol' => 'http',
        ];
    }
}
