<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTelemetryTest extends TestCase
{
    use RefreshDatabase;

    private function auth()
    {
        return $this->withBasicAuth('studio', 'password');
    }

    public function test_telemetry_requires_studio_authentication(): void
    {
        $this->postJson('/api/telemetry', ['message' => 'unauthorized'])
            ->assertUnauthorized();
    }

    public function test_telemetry_is_persisted_and_sensitive_values_are_redacted(): void
    {
        $response = $this->auth()->postJson('/api/telemetry', [
            'severity' => 'error',
            'source' => 'browser',
            'event_type' => 'unhandled_exception',
            'project_id' => 'alpha',
            'message' => 'request failed with Bearer secret-token',
            'context' => [
                'api_key' => 'secret-api-key',
                'nested' => ['password' => 'secret-password'],
                'safe' => 'kept',
            ],
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('accepted', true)
            ->assertJsonStructure(['id']);

        $this->auth()->getJson('/api/platform/telemetry?project_id=alpha')
            ->assertOk()
            ->assertJsonPath('data.0.project_id', 'alpha')
            ->assertJsonPath('data.0.context.api_key', '[REDACTED]')
            ->assertJsonPath('data.0.context.nested.password', '[REDACTED]')
            ->assertJsonPath('data.0.context.safe', 'kept')
            ->assertJsonPath('data.0.message', 'request failed with Bearer [REDACTED]');
    }

    public function test_telemetry_payloads_are_bounded_by_validation(): void
    {
        $this->auth()->postJson('/api/telemetry', [
            'message' => str_repeat('x', 8193),
        ])->assertUnprocessable();
    }
}
