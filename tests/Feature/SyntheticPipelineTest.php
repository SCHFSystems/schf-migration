<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SCHF\SDK\Bundle\Validator as BundleValidator;
use Tests\TestCase;

class SyntheticPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_responds(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'system' => 'SCHF Migration',
            ]);
    }

    public function test_synthetic_pipeline_runs_end_to_end(): void
    {
        $project = $this->postJson('/api/projects', [
            'name' => 'Synthetic Pipeline',
            'source_type' => 'synthetic',
            'source_config' => [
                'scenario' => 'clean',
                'organization' => ['external_id' => 'ORG-SYN', 'name' => 'Synthetic Organization'],
            ],
        ])->assertCreated()->json();

        $projectId = $project['id'];

        $this->postJson("/api/projects/{$projectId}/inventory/generate")
            ->assertOk()
            ->assertJsonPath('summary.total_tables', 5);

        $this->getJson("/api/projects/{$projectId}/inventory")
            ->assertOk()
            ->assertJsonPath('summary.total_rows', 18);

        $this->postJson("/api/projects/{$projectId}/normalization/run")
            ->assertOk()
            ->assertJsonPath('summary.total_suppliers', 3)
            ->assertJsonPath('summary.total_payables', 5)
            ->assertJsonPath('summary.total_accounts', 2)
            ->assertJsonPath('summary.total_expenses', 5);

        $this->getJson("/api/projects/{$projectId}/normalization")
            ->assertOk()
            ->assertJsonPath('summary.total_categories', 3);

        $this->postJson("/api/projects/{$projectId}/quality/run")
            ->assertOk()
            ->assertJsonPath('status', 'passed');

        $this->getJson("/api/projects/{$projectId}/quality")
            ->assertOk()
            ->assertJsonPath('summary.total_errors', 0);

        $this->postJson("/api/projects/{$projectId}/preview/generate")
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('ready_for_bundle', true);

        $this->getJson("/api/projects/{$projectId}/preview/result")
            ->assertOk()
            ->assertJsonPath('status', 'ready');

        $bundlePreview = $this->getJson("/api/projects/{$projectId}/bundle/preview")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source.type', 'synthetic')
            ->json();

        $this->assertContains('payments.json', array_column($bundlePreview['files'], 'path'));
        $paymentPreview = array_values(array_filter(
            $bundlePreview['files'],
            fn (array $file): bool => $file['path'] === 'payments.json'
        ))[0];
        $this->assertSame(5, $paymentPreview['records']);

        $export = $this->postJson("/api/projects/{$projectId}/bundle/export")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertStringEndsWith('.schf', $export['bundle_path']);
        $this->assertFileExists($export['bundle_path']);

        $validator = new BundleValidator();
        $validation = $validator->validate($export['bundle_path']);
        $this->assertTrue($validation['valid'], implode(', ', $validation['errors']));
        $this->assertSame(5, $validator->getManifest()?->getFileByPath('payments.json')['records']);

        @unlink($export['bundle_path']);
    }

    public function test_invalid_project_returns_not_found(): void
    {
        $this->getJson('/api/projects/999999/inventory')
            ->assertNotFound();
    }

    public function test_synthetic_only_mode_blocks_real_connectors(): void
    {
        $this->postJson('/api/projects', [
            'name' => 'Blocked Real Connector',
            'source_type' => 'firebird',
            'source_config' => ['host' => 'example.invalid'],
        ])->assertStatus(422)
            ->assertJsonPath('error', 'Real connectors are disabled in synthetic-only mode');
    }

    public function test_e2e_clean_scenario_includes_users(): void
    {
        $project = $this->postJson('/api/projects', [
            'name' => 'E2E Clean Pipeline',
            'source_type' => 'synthetic',
            'source_config' => [
                'scenario' => 'e2e-clean',
                'organization' => ['external_id' => 'ORG-E2E-S11', 'name' => 'Sprint 11 E2E Org'],
            ],
        ])->assertCreated()->json();

        $projectId = $project['id'];

        $this->postJson("/api/projects/{$projectId}/inventory/generate")
            ->assertOk()
            ->assertJsonPath('summary.total_tables', 6);

        $this->getJson("/api/projects/{$projectId}/inventory")
            ->assertOk()
            ->assertJsonPath('summary.total_rows', 12);

        $this->postJson("/api/projects/{$projectId}/normalization/run")
            ->assertOk()
            ->assertJsonPath('summary.total_suppliers', 2)
            ->assertJsonPath('summary.total_payables', 3)
            ->assertJsonPath('summary.total_accounts', 2)
            ->assertJsonPath('summary.total_expenses', 2)
            ->assertJsonPath('summary.total_users', 1);

        $this->getJson("/api/projects/{$projectId}/normalization")
            ->assertOk()
            ->assertJsonPath('summary.total_categories', 2);

        $this->postJson("/api/projects/{$projectId}/quality/run")
            ->assertOk()
            ->assertJsonPath('status', 'passed');

        $this->getJson("/api/projects/{$projectId}/quality")
            ->assertOk()
            ->assertJsonPath('summary.total_errors', 0);

        $this->postJson("/api/projects/{$projectId}/preview/generate")
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('ready_for_bundle', true);

        $bundlePreview = $this->getJson("/api/projects/{$projectId}/bundle/preview")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source.type', 'synthetic')
            ->json();

        $usersPreview = array_values(array_filter(
            $bundlePreview['files'],
            fn (array $file): bool => $file['path'] === 'users.json'
        ));
        $this->assertCount(1, $usersPreview, 'users.json must be present in bundle');
        $this->assertSame(1, $usersPreview[0]['records']);

        $paymentPreview = array_values(array_filter(
            $bundlePreview['files'],
            fn (array $file): bool => $file['path'] === 'payments.json'
        ));
        $this->assertCount(1, $paymentPreview);
        $this->assertSame(3, $paymentPreview[0]['records']);

        $export = $this->postJson("/api/projects/{$projectId}/bundle/export")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertStringEndsWith('.schf', $export['bundle_path']);
        $this->assertFileExists($export['bundle_path']);

        $validator = new BundleValidator();
        $validation = $validator->validate($export['bundle_path']);
        $this->assertTrue($validation['valid'], implode(', ', $validation['errors']));
        $this->assertSame(3, $validator->getManifest()?->getFileByPath('payments.json')['records']);
        $this->assertSame(1, $validator->getManifest()?->getFileByPath('users.json')['records']);

        @unlink($export['bundle_path']);
    }
}
