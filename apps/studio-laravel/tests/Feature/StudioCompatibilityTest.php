<?php

namespace Tests\Feature;

use Tests\TestCase;

class StudioCompatibilityTest extends TestCase
{
    private function auth()
    {
        return $this->withBasicAuth('studio', 'password');
    }

    public function test_pg_meta_query_returns_a_successful_json_result(): void
    {
        $response = $this->auth()->postJson('/api/platform/pg-meta/default/query', [
            'query' => 'select 1',
        ]);

        $response->assertOk()->assertJsonIsArray();
    }

    public function test_pg_meta_entity_type_query_returns_native_data_row(): void
    {
        $this->auth()->postJson('/api/platform/pg-meta/default/query?key=entity-types-public-0', ['query' => 'select 1'])
            ->assertOk()
            ->assertJsonPath('0.data.entities', [])
            ->assertJsonPath('0.data.count', 0);
    }

    public function test_project_detail_uses_native_ref_identifier(): void
    {
        $this->auth()->getJson('/api/platform/projects/default')
            ->assertOk()
            ->assertJsonPath('ref', 'default');
    }

    public function test_lints_accept_the_native_get_request(): void
    {
        $this->auth()->getJson('/api/platform/projects/default/run-lints')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_content_and_content_count_return_empty_state_contracts(): void
    {
        $this->auth()->getJson('/api/platform/projects/default/content')
            ->assertOk()
            ->assertExactJson(['data' => [], 'cursor' => null]);

        $this->auth()->getJson('/api/platform/projects/default/content/count')
            ->assertOk()
            ->assertExactJson(['shared' => 0, 'favorites' => 0, 'private' => 0]);
    }

    public function test_management_surfaces_return_successful_empty_arrays(): void
    {
        foreach ([
            '/api/platform/storage/default/buckets',
            '/api/platform/storage/default/vector-buckets',
            '/api/v1/projects/default/functions',
            '/api/platform/projects/default/analytics/log-drains',
        ] as $uri) {
            $this->auth()->getJson($uri)->assertOk()->assertExactJson([]);
        }
    }

    public function test_settings_returns_the_native_studio_shape_without_secrets_by_default(): void
    {
        $this->auth()->getJson('/api/platform/projects/default/settings')
            ->assertOk()
            ->assertJsonStructure([
                'app_config' => ['db_schema', 'endpoint', 'storage_endpoint', 'protocol'],
                'name', 'ref', 'region', 'status', 'service_api_keys',
            ]);
    }

    public function test_utc_time_endpoint_returns_native_json(): void
    {
        $this->auth()->getJson('/api/get-utc-time')
            ->assertOk()
            ->assertJsonStructure(['utcTime']);
    }

    public function test_unknown_api_paths_are_json_not_the_spa_shell(): void
    {
        $this->auth()->getJson('/api/not-implemented')
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Studio API endpoint not implemented');
    }
}
