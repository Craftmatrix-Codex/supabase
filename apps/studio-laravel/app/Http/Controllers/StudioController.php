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
            'db_schema' => ['public'],
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
        $this->assertProject($project);
        $record = collect(config('studio.projects', []))->firstWhere('ref', $project);

        return response()->json(array_merge($record, [
            'connectionString' => '',
            'restUrl' => config('studio.rest_url'),
        ]));
    }

    public function profile(): JsonResponse
    {
        $project = $this->projectRecord('default');

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
                'projects' => [array_merge($project, ['connectionString' => ''])],
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
        $this->assertProject($project);

        return response()->json([[
            'cloud_provider' => 'localhost',
            'connectionString' => '',
            'connection_string_read_only' => '',
            'db_host' => config('studio.db_host'),
            'db_name' => 'postgres',
            'db_port' => config('studio.db_port'),
            'db_user' => 'postgres',
            'identifier' => $project,
            'inserted_at' => '',
            'region' => 'local',
            'restUrl' => config('studio.rest_url'),
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
        return response()->json(['projects' => config('studio.projects', [])]);
    }

    public function currentProject(): JsonResponse
    {
        $project = collect(config('studio.projects', []))->firstWhere('current', true);

        return response()->json(['project' => $project]);
    }

    public function runLints(string $project): JsonResponse
    {
        abort_unless(collect(config('studio.projects', []))->contains('ref', $project), 404);

        return response()->json([]);
    }

    public function pgMetaQuery(Request $request, string $project): JsonResponse
    {
        $this->assertProject($project);
        $query = $request->input('query');
        abort_unless(is_string($query) && trim($query) !== '', 422, 'query is required');

        // The database-backed metadata adapter is intentionally isolated behind
        // this contract. Empty metadata is a valid self-hosted initial state.
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

    public function emptyArray(string $project): JsonResponse
    {
        $this->assertProject($project);

        return response()->json([]);
    }

    public function settings(string $project): JsonResponse
    {
        $this->assertProject($project);

        return response()->json([
            'app_config' => [
                'db_schema' => 'public',
                'endpoint' => config('studio.project_endpoint', 'http://localhost'),
                'storage_endpoint' => config('studio.project_endpoint', 'http://localhost'),
                'protocol' => 'http',
            ],
            'cloud_provider' => 'local',
            'db_dns_name' => '-',
            'db_host' => config('database.connections.pgsql.host', 'localhost'),
            'db_ip_addr_config' => 'legacy',
            'db_name' => config('database.connections.pgsql.database', 'postgres'),
            'db_port' => (int) config('database.connections.pgsql.port', 5432),
            'db_user' => config('database.connections.pgsql.username', 'postgres'),
            'inserted_at' => now()->toIso8601String(),
            'jwt_secret' => '',
            'name' => config('studio.projects.0.name', 'Default Project'),
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
        $record = collect(config('studio.projects', []))->firstWhere('ref', $project);
        abort_unless(is_array($record), 404);

        return $record;
    }

    private function assertProject(string $project): void
    {
        abort_unless(collect(config('studio.projects', []))->contains('ref', $project), 404);
    }
}
