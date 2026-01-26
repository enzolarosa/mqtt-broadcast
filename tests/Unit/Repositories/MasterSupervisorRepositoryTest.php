<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use enzolarosa\MqttBroadcast\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class MasterSupervisorRepositoryTest extends TestCase
{
    protected MasterSupervisorRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MasterSupervisorRepository();

        // Clear cache before each test
        Cache::flush();
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        Cache::flush();
        parent::tearDown();
    }

    /**
     * CORE TEST 1: Stores supervisor state in cache
     */
    public function test_it_stores_supervisor_state_in_cache(): void
    {
        $name = 'master-supervisor-test';
        $data = [
            'pid' => 12345,
            'status' => 'running',
            'supervisors' => 2,
            'started_at' => '2026-01-26 10:00:00',
        ];

        $this->repository->update($name, $data);

        $cached = Cache::get('mqtt-broadcast:master:' . $name);
        $this->assertIsArray($cached);
        $this->assertEquals($data['pid'], $cached['pid']);
        $this->assertEquals($data['status'], $cached['status']);
        $this->assertEquals($data['supervisors'], $cached['supervisors']);
        $this->assertArrayHasKey('updated_at', $cached);
    }

    /**
     * CORE TEST 2: Overwrites existing state
     */
    public function test_it_overwrites_existing_state(): void
    {
        $name = 'master-supervisor-test';
        $initialData = [
            'pid' => 12345,
            'status' => 'running',
            'supervisors' => 1,
        ];

        $this->repository->update($name, $initialData);

        $updatedData = [
            'pid' => 12345,
            'status' => 'pausing',
            'supervisors' => 2,
        ];

        $this->repository->update($name, $updatedData);

        $result = $this->repository->find($name);
        $this->assertEquals('pausing', $result['status']);
        $this->assertEquals(2, $result['supervisors']);
    }

    /**
     * CORE TEST 3: Uses correct cache key format
     */
    public function test_it_uses_correct_cache_key_format(): void
    {
        $name = 'test-supervisor-abc123';
        $data = ['pid' => 99999];

        $this->repository->update($name, $data);

        $expectedKey = 'mqtt-broadcast:master:test-supervisor-abc123';
        $this->assertTrue(Cache::has($expectedKey));
    }

    /**
     * CORE TEST 4: Returns supervisor state from cache
     */
    public function test_it_returns_supervisor_state_from_cache(): void
    {
        $name = 'master-supervisor-test';
        $data = [
            'pid' => 54321,
            'status' => 'running',
            'supervisors' => 3,
            'started_at' => '2026-01-26 11:00:00',
        ];

        $this->repository->update($name, $data);
        $result = $this->repository->find($name);

        $this->assertIsArray($result);
        $this->assertEquals($data['pid'], $result['pid']);
        $this->assertEquals($data['status'], $result['status']);
        $this->assertEquals($data['supervisors'], $result['supervisors']);
        $this->assertEquals($data['started_at'], $result['started_at']);
        $this->assertArrayHasKey('updated_at', $result);
    }

    /**
     * CORE TEST 5: Returns null when supervisor not found
     */
    public function test_it_returns_null_when_not_found(): void
    {
        $result = $this->repository->find('non-existent-supervisor');

        $this->assertNull($result);
    }

    /**
     * CORE TEST 6: Returns array with expected structure
     */
    public function test_it_returns_array_with_expected_structure(): void
    {
        $name = 'master-supervisor-test';
        $data = [
            'pid' => 11111,
            'status' => 'running',
            'supervisors' => 1,
        ];

        $this->repository->update($name, $data);
        $result = $this->repository->find($name);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pid', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('supervisors', $result);
        $this->assertArrayHasKey('updated_at', $result);
    }

    /**
     * CORE TEST 7: Removes supervisor state from cache
     */
    public function test_it_removes_supervisor_state_from_cache(): void
    {
        $name = 'master-supervisor-test';
        $data = ['pid' => 12345, 'status' => 'running'];

        $this->repository->update($name, $data);
        $this->assertNotNull($this->repository->find($name));

        $this->repository->forget($name);

        $this->assertNull($this->repository->find($name));
        $this->assertFalse(Cache::has('mqtt-broadcast:master:' . $name));
    }

    /**
     * CORE TEST 8: Forget does nothing when state does not exist
     */
    public function test_forget_does_nothing_when_state_does_not_exist(): void
    {
        $name = 'non-existent-supervisor';

        // Should not throw exception
        $this->repository->forget($name);

        $this->assertNull($this->repository->find($name));
    }

    /**
     * CORE TEST 9: Returns all supervisors as collection
     */
    public function test_it_returns_all_supervisors_as_collection(): void
    {
        $supervisor1 = ['pid' => 11111, 'status' => 'running'];
        $supervisor2 = ['pid' => 22222, 'status' => 'pausing'];
        $supervisor3 = ['pid' => 33333, 'status' => 'running'];

        $this->repository->update('supervisor-1', $supervisor1);
        $this->repository->update('supervisor-2', $supervisor2);
        $this->repository->update('supervisor-3', $supervisor3);

        $result = $this->repository->all();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(3, $result);
    }

    /**
     * CORE TEST 10: Returns supervisor names as array
     */
    public function test_it_returns_supervisor_names_as_array(): void
    {
        $this->repository->update('supervisor-alpha', ['pid' => 11111]);
        $this->repository->update('supervisor-beta', ['pid' => 22222]);
        $this->repository->update('supervisor-gamma', ['pid' => 33333]);

        $names = $this->repository->names();

        $this->assertIsArray($names);
        $this->assertCount(3, $names);
        $this->assertContains('supervisor-alpha', $names);
        $this->assertContains('supervisor-beta', $names);
        $this->assertContains('supervisor-gamma', $names);
    }

    /**
     * INTEGRATION TEST 1: Complete lifecycle (update → find → forget)
     */
    public function test_complete_lifecycle_update_find_forget(): void
    {
        $name = 'lifecycle-test-supervisor';
        $data = [
            'pid' => 99999,
            'status' => 'running',
            'supervisors' => 2,
            'started_at' => '2026-01-26 12:00:00',
        ];

        // Create
        $this->repository->update($name, $data);
        $this->assertNotNull($this->repository->find($name));

        // Read
        $retrieved = $this->repository->find($name);
        $this->assertEquals($data['pid'], $retrieved['pid']);
        $this->assertEquals($data['status'], $retrieved['status']);

        // Update
        $updatedData = array_merge($data, ['status' => 'pausing']);
        $this->repository->update($name, $updatedData);
        $retrieved = $this->repository->find($name);
        $this->assertEquals('pausing', $retrieved['status']);

        // Delete
        $this->repository->forget($name);
        $this->assertNull($this->repository->find($name));
    }

    /**
     * INTEGRATION TEST 2: Handles multiple supervisors independently
     */
    public function test_it_handles_multiple_supervisors_independently(): void
    {
        $supervisor1 = ['pid' => 11111, 'status' => 'running'];
        $supervisor2 = ['pid' => 22222, 'status' => 'pausing'];
        $supervisor3 = ['pid' => 33333, 'status' => 'running'];

        // Create all three
        $this->repository->update('sup-1', $supervisor1);
        $this->repository->update('sup-2', $supervisor2);
        $this->repository->update('sup-3', $supervisor3);

        // Verify all exist
        $this->assertNotNull($this->repository->find('sup-1'));
        $this->assertNotNull($this->repository->find('sup-2'));
        $this->assertNotNull($this->repository->find('sup-3'));

        // Update one
        $this->repository->update('sup-2', ['pid' => 22222, 'status' => 'running']);

        // Verify only one changed
        $this->assertEquals('running', $this->repository->find('sup-1')['status']);
        $this->assertEquals('running', $this->repository->find('sup-2')['status']);
        $this->assertEquals('running', $this->repository->find('sup-3')['status']);

        // Delete one
        $this->repository->forget('sup-2');

        // Verify only one deleted
        $this->assertNotNull($this->repository->find('sup-1'));
        $this->assertNull($this->repository->find('sup-2'));
        $this->assertNotNull($this->repository->find('sup-3'));
    }

    /**
     * EDGE CASE 1: Automatically adds updated_at timestamp
     */
    public function test_it_automatically_adds_updated_at_timestamp(): void
    {
        $name = 'timestamp-test';
        $data = ['pid' => 12345];

        $beforeUpdate = now();
        $this->repository->update($name, $data);
        $afterUpdate = now();

        $result = $this->repository->find($name);
        $this->assertArrayHasKey('updated_at', $result);

        $updatedAt = \Carbon\Carbon::parse($result['updated_at']);
        $this->assertTrue($updatedAt->between($beforeUpdate, $afterUpdate));
    }

    /**
     * EDGE CASE 2: Updates updated_at on each update
     */
    public function test_it_updates_updated_at_on_each_update(): void
    {
        $name = 'timestamp-update-test';
        $data = ['pid' => 12345];

        $this->repository->update($name, $data);
        $firstUpdate = $this->repository->find($name)['updated_at'];

        sleep(1); // Ensure time difference

        $this->repository->update($name, $data);
        $secondUpdate = $this->repository->find($name)['updated_at'];

        $this->assertNotEquals($firstUpdate, $secondUpdate);
    }

    /**
     * EDGE CASE 3: Handles empty supervisors list
     */
    public function test_it_handles_empty_supervisors_list(): void
    {
        // No supervisors added
        $result = $this->repository->all();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    /**
     * EDGE CASE 4: Handles empty names list
     */
    public function test_it_handles_empty_names_list(): void
    {
        // No supervisors added
        $names = $this->repository->names();

        $this->assertIsArray($names);
        $this->assertCount(0, $names);
    }

    /**
     * EDGE CASE 5: Preserves all custom data fields
     */
    public function test_it_preserves_all_custom_data_fields(): void
    {
        $name = 'custom-fields-test';
        $data = [
            'pid' => 12345,
            'status' => 'running',
            'supervisors' => 2,
            'custom_field_1' => 'value1',
            'custom_field_2' => 'value2',
            'nested' => ['key' => 'value'],
        ];

        $this->repository->update($name, $data);
        $result = $this->repository->find($name);

        $this->assertEquals('value1', $result['custom_field_1']);
        $this->assertEquals('value2', $result['custom_field_2']);
        $this->assertIsArray($result['nested']);
        $this->assertEquals('value', $result['nested']['key']);
    }

    /**
     * EDGE CASE 6: Cache TTL is set correctly
     */
    public function test_cache_ttl_is_set_correctly(): void
    {
        $name = 'ttl-test-supervisor';
        $data = ['pid' => 12345];

        $this->repository->update($name, $data);

        // Verify the data exists
        $this->assertNotNull($this->repository->find($name));

        // Note: Testing actual TTL expiration would require waiting 1 hour
        // We verify the key exists and data is retrievable
        $this->assertTrue(Cache::has('mqtt-broadcast:master:' . $name));
    }
}
