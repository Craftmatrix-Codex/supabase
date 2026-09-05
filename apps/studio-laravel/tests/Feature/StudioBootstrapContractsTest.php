<?php

namespace Tests\Feature;

use Tests\TestCase;

class StudioBootstrapContractsTest extends TestCase
{
    private function auth()
    {
        return $this->withBasicAuth('studio', 'password');
    }

    public function test_native_bootstrap_endpoints_return_compatible_shapes(): void
    {
        $this->auth()->getJson('/api/platform/projects/default')->assertOk()->assertJsonStructure([
            'id', 'name', 'status', 'connectionString', 'restUrl',
        ]);
        $this->auth()->getJson('/api/platform/profile')->assertOk()->assertJsonStructure([
            'id', 'primary_email', 'organizations',
        ]);
        $this->auth()->getJson('/api/platform/organizations')->assertOk()->assertJsonIsArray();
        $this->auth()->getJson('/api/get-deployment-commit')->assertOk()->assertJsonStructure([
            'commitSha', 'commitTime',
        ]);
        $this->auth()->getJson('/api/enabled-features-overrides')->assertOk()->assertJson(['disabled_features' => []]);
        $this->auth()->getJson('/api/platform/deployment-mode')->assertOk()->assertJson(['is_cli_mode' => false]);
        $this->auth()->getJson('/api/platform/projects/default/databases')->assertOk()->assertJsonIsArray();
        $this->auth()->getJson('/api/v1/projects/default/api-keys?reveal=false')->assertOk()->assertJsonIsArray();
        $this->auth()->getJson('/api/platform/integrations/github/authorization')
            ->assertOk()
            ->assertContent('null');
        $this->auth()->getJson('/api/ai/sql/check-api-key')->assertOk()->assertJson(['hasKey' => false]);
        $this->auth()->getJson('/api/platform/projects/default/config/secrets/update-status')
            ->assertOk()->assertJson(['update_status' => null]);
        $this->auth()->getJson('/api/platform/projects/default/config/postgrest')
            ->assertOk()
            ->assertJsonStructure(['db_anon_role', 'db_extra_search_path', 'db_schema', 'jwt_secret', 'max_rows', 'role_claim_key'])
            ->assertJsonPath('db_schema', 'public');
        $this->auth()->get('/api/platform/projects/default/api/rest')
            ->assertOk()->assertJson([]);
        $this->auth()->head('/api/platform/projects/default/api/rest')->assertOk();
        $this->auth()->get('/api/platform/projects/default/analytics/endpoints/logs.all')
            ->assertOk()->assertJson(['result' => []]);
    }
}
