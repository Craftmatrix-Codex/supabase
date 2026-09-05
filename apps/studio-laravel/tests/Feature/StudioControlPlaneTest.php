<?php

namespace Tests\Feature;

use Tests\TestCase;

class StudioControlPlaneTest extends TestCase
{
    public function test_health_endpoint_is_public_and_json(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_project_list_requires_studio_basic_authentication(): void
    {
        $this->getJson('/api/platform/projects')->assertUnauthorized();

        $this->withBasicAuth('studio', 'password')
            ->getJson('/api/platform/projects')
            ->assertOk()
            ->assertJsonPath('projects.0.id', 1)
            ->assertJsonPath('projects.0.ref', 'default')
            ->assertJsonPath('projects.0.name', 'Default Project');
    }

    public function test_current_project_is_durable_in_the_http_contract(): void
    {
        $this->withBasicAuth('studio', 'password')
            ->getJson('/api/platform/projects/current')
            ->assertOk()
            ->assertJsonPath('project.id', 1)
            ->assertJsonPath('project.ref', 'default');
    }

    public function test_react_fallback_is_not_a_blade_view(): void
    {
        $response = $this->withBasicAuth('studio', 'password')->get('/project/default');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertSee('<title>Supabase</title>', false);
        $response->assertSee('studio-shell-loader', false);
        $response->assertDontSee('Laravel', false);
    }
}
