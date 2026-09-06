<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudioContentTest extends TestCase
{
    private string $contentPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contentPath = sys_get_temp_dir() . '/supadata-content-' . Str::uuid()->toString();
        config(['studio.content_path' => $this->contentPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentPath);
        parent::tearDown();
    }

    public function test_sql_content_and_folders_persist_through_native_contracts(): void
    {
        $auth = $this->withBasicAuth('studio', 'password');
        $folder = $auth->postJson('/api/platform/projects/default/content/folders', ['name' => 'Reports'])
            ->assertCreated()
            ->json();

        $snippetId = (string) Str::uuid();
        $auth->putJson('/api/platform/projects/default/content', [
            'id' => $snippetId,
            'type' => 'sql',
            'name' => 'Revenue report',
            'description' => 'Monthly revenue',
            'favorite' => false,
            'content' => [
                'sql' => 'select 42;',
                'content_id' => (string) Str::uuid(),
                'schema_version' => '1.0',
            ],
            'visibility' => 'user',
            'folder_id' => $folder['id'],
        ])->assertOk()->assertJsonPath('id', $snippetId);

        $auth->getJson('/api/platform/projects/default/content')
            ->assertOk()
            ->assertJsonPath('data.0.id', $snippetId)
            ->assertJsonPath('data.0.name', 'Revenue report');

        $auth->getJson('/api/platform/projects/default/content/item/' . $snippetId)
            ->assertOk()
            ->assertJsonPath('id', $snippetId)
            ->assertJsonPath('content.sql', 'select 42;');

        $auth->getJson('/api/platform/projects/default/content/folders')
            ->assertOk()
            ->assertJsonPath('data.folders.0.id', $folder['id'])
            ->assertJsonPath('data.contents.0.id', $snippetId);

        $auth->deleteJson('/api/platform/projects/default/content?ids=' . $snippetId)
            ->assertOk()
            ->assertJsonPath('0.id', $snippetId);

        $auth->getJson('/api/platform/projects/default/content/item/' . $snippetId)
            ->assertNotFound();
    }
}
