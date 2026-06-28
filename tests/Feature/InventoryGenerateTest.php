<?php

/**
 * Feature Test: Migration Inventory Endpoint
 *
 * This test requires a full Laravel application bootstrap.
 * Run only after installing Laravel framework:
 *   composer require laravel/framework
 *
 * Test: POST /api/projects/{project}/inventory/generate
 *
 * Setup:
 * - Refresh database
 * - Create a project with source_config containing valid connection params
 * - Mock the ConnectorFactory to return a stubbed connector
 *
 * Assertions:
 * - 200 OK with valid inventory JSON
 * - 422 when project has no source_config
 * - 422 when connection fails
 */

// namespace Tests\Feature;
//
// use Tests\TestCase;
// use App\Models\MigrationProject;
// use App\Services\ConnectorFactory;
// use SCHF\SDK\Connector\ConnectorInterface;
//
// class InventoryGenerateTest extends TestCase
// {
//     public function test_generates_inventory_successfully(): void
//     {
//         $connector = $this->createMock(ConnectorInterface::class);
//         $connector->method('getSchema')->willReturn([]);
//         $connector->method('getDriverName')->willReturn('firebird');
//
//         $factory = $this->createMock(ConnectorFactory::class);
//         $factory->method('make')->willReturn($connector);
//
//         $this->app->instance(ConnectorFactory::class, $factory);
//
//         $project = MigrationProject::factory()->create([
//             'source_type' => 'firebird',
//             'source_config' => ['host' => 'localhost', 'dbname' => 'test'],
//         ]);
//
//         $response = $this->postJson("/api/projects/{$project->id}/inventory/generate");
//
//         $response->assertOk();
//         $response->assertJsonStructure([
//             'driver', 'generated_at', 'summary' => ['total_tables'],
//         ]);
//     }
//
//     public function test_returns_422_when_no_source_config(): void
//     {
//         $project = MigrationProject::factory()->create([
//             'source_type' => 'firebird',
//             'source_config' => null,
//         ]);
//
//         $response = $this->postJson("/api/projects/{$project->id}/inventory/generate");
//
//         $response->assertStatus(422);
//         $response->assertJson(['error' => 'Project has no source configuration']);
//     }
// }
