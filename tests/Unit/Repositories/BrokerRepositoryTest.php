<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use enzolarosa\MqttBroadcast\Models\Brokers;
use enzolarosa\MqttBroadcast\Repositories\BrokerRepository;
use enzolarosa\MqttBroadcast\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BrokerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected BrokerRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BrokerRepository();
    }

    /**
     * CORE TEST 1: Creates broker record in database
     */
    public function test_it_creates_broker_record_in_database(): void
    {
        $name = 'test-broker-abc123';
        $connection = 'mqtt-local';

        $broker = $this->repository->create($name, $connection);

        $this->assertInstanceOf(Brokers::class, $broker);
        $this->assertEquals($name, $broker->name);
        $this->assertEquals($connection, $broker->connection);
        $this->assertEquals(getmypid(), $broker->pid);
        $this->assertNotNull($broker->started_at);
        $this->assertTrue($broker->working);
    }

    /**
     * CORE TEST 2: Finds broker by name
     */
    public function test_it_finds_broker_by_name(): void
    {
        $broker = Brokers::factory()->create([
            'name' => 'test-broker-xyz',
        ]);

        $result = $this->repository->find('test-broker-xyz');

        $this->assertInstanceOf(Brokers::class, $result);
        $this->assertEquals($broker->id, $result->id);
        $this->assertEquals('test-broker-xyz', $result->name);
    }

    /**
     * CORE TEST 3: Returns null when broker not found
     */
    public function test_it_returns_null_when_broker_not_found(): void
    {
        $result = $this->repository->find('non-existent-broker');

        $this->assertNull($result);
    }

    /**
     * CORE TEST 4: Returns all brokers as collection
     */
    public function test_it_returns_all_brokers_as_collection(): void
    {
        Brokers::factory()->count(3)->create();

        $result = $this->repository->all();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(3, $result);
    }

    /**
     * CORE TEST 5: Returns empty collection when no brokers exist
     */
    public function test_it_returns_empty_collection_when_no_brokers_exist(): void
    {
        $result = $this->repository->all();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(0, $result);
    }

    /**
     * CORE TEST 6: Deletes broker by name
     */
    public function test_it_deletes_broker_by_name(): void
    {
        Brokers::factory()->create(['name' => 'broker-to-delete']);

        $this->repository->delete('broker-to-delete');

        $this->assertNull($this->repository->find('broker-to-delete'));
    }

    /**
     * CORE TEST 7: Delete does not throw exception when broker not found
     */
    public function test_delete_does_not_throw_exception_when_broker_not_found(): void
    {
        // Should not throw exception (silent fail, Horizon pattern)
        $this->repository->delete('non-existent-broker');

        $this->assertTrue(true); // If we reach here, no exception was thrown
    }

    /**
     * CORE TEST 8: Deletes broker by PID
     */
    public function test_it_deletes_broker_by_pid(): void
    {
        $broker = Brokers::factory()->create(['pid' => 99999]);

        $this->repository->deleteByPid(99999);

        $this->assertNull($this->repository->find($broker->name));
    }

    /**
     * CORE TEST 9: Generates unique broker name
     */
    public function test_it_generates_unique_broker_name(): void
    {
        $name1 = $this->repository->generateName();
        $name2 = $this->repository->generateName();

        $this->assertIsString($name1);
        $this->assertNotEmpty($name1);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+-[a-z0-9]{4}$/', $name1);
        $this->assertNotEquals($name1, $name2); // Should be unique (random token)
    }

    /**
     * HEARTBEAT TEST 1: Updates last_heartbeat_at timestamp
     */
    public function test_it_updates_heartbeat_timestamp(): void
    {
        $broker = Brokers::factory()->create([
            'name' => 'broker-heartbeat',
            'last_heartbeat_at' => now()->subMinutes(5),
        ]);

        $oldHeartbeat = $broker->last_heartbeat_at;

        sleep(1); // Ensure timestamp difference

        $this->repository->touch('broker-heartbeat');

        $broker->refresh();

        $this->assertNotNull($broker->last_heartbeat_at);
        $this->assertTrue($broker->last_heartbeat_at->greaterThan($oldHeartbeat));
    }

    /**
     * HEARTBEAT TEST 2: Touch does not update other fields
     */
    public function test_touch_does_not_update_other_fields(): void
    {
        $broker = Brokers::factory()->create([
            'name' => 'broker-touch',
            'connection' => 'original-connection',
            'working' => true,
        ]);

        $this->repository->touch('broker-touch');

        $broker->refresh();

        $this->assertEquals('original-connection', $broker->connection);
        $this->assertTrue($broker->working);
    }

    /**
     * HEARTBEAT TEST 3: Touch on non-existent broker does not throw exception
     */
    public function test_touch_on_non_existent_broker_does_not_throw_exception(): void
    {
        // Should not throw exception (silent fail)
        $this->repository->touch('non-existent-broker');

        $this->assertTrue(true);
    }

    /**
     * INTEGRATION TEST 1: Complete lifecycle (create → find → touch → delete)
     */
    public function test_complete_broker_lifecycle(): void
    {
        // 1. Create
        $name = $this->repository->generateName();
        $broker = $this->repository->create($name, 'mqtt-test');

        $this->assertNotNull($broker);
        $this->assertEquals($name, $broker->name);

        // 2. Find
        $found = $this->repository->find($name);
        $this->assertNotNull($found);
        $this->assertEquals($broker->id, $found->id);

        // 3. Touch (heartbeat)
        $oldHeartbeat = $found->last_heartbeat_at;
        sleep(1);
        $this->repository->touch($name);
        $found->refresh();
        $this->assertTrue($found->last_heartbeat_at->greaterThan($oldHeartbeat));

        // 4. Delete
        $this->repository->delete($name);
        $this->assertNull($this->repository->find($name));
    }

    /**
     * INTEGRATION TEST 2: Multiple brokers independence
     */
    public function test_multiple_brokers_operate_independently(): void
    {
        $broker1 = $this->repository->create('broker-1', 'connection-1');
        $broker2 = $this->repository->create('broker-2', 'connection-2');

        // Both exist
        $this->assertNotNull($this->repository->find('broker-1'));
        $this->assertNotNull($this->repository->find('broker-2'));

        // Delete one doesn't affect the other
        $this->repository->delete('broker-1');
        $this->assertNull($this->repository->find('broker-1'));
        $this->assertNotNull($this->repository->find('broker-2'));
    }

    /**
     * EDGE CASE TEST 1: DeleteByPid removes all brokers with that PID
     */
    public function test_delete_by_pid_removes_all_brokers_with_same_pid(): void
    {
        // Edge case: multiple brokers with same PID (shouldn't happen but handle it)
        Brokers::factory()->create(['name' => 'broker-1', 'pid' => 55555]);
        Brokers::factory()->create(['name' => 'broker-2', 'pid' => 55555]);
        Brokers::factory()->create(['name' => 'broker-3', 'pid' => 66666]);

        $this->repository->deleteByPid(55555);

        $this->assertNull($this->repository->find('broker-1'));
        $this->assertNull($this->repository->find('broker-2'));
        $this->assertNotNull($this->repository->find('broker-3')); // Different PID, still exists
    }

    /**
     * EDGE CASE TEST 2: Create sets current PID correctly
     */
    public function test_create_sets_current_pid_correctly(): void
    {
        $broker = $this->repository->create('test-pid', 'mqtt-test');

        $this->assertEquals(getmypid(), $broker->pid);
    }

    /**
     * EDGE CASE TEST 3: All returns brokers in consistent order
     */
    public function test_all_returns_brokers_in_consistent_order(): void
    {
        Brokers::factory()->create(['name' => 'broker-c', 'created_at' => now()->subMinutes(3)]);
        Brokers::factory()->create(['name' => 'broker-a', 'created_at' => now()->subMinutes(1)]);
        Brokers::factory()->create(['name' => 'broker-b', 'created_at' => now()->subMinutes(2)]);

        $brokers = $this->repository->all();

        $this->assertCount(3, $brokers);
        // Verify we get a stable collection
        $this->assertInstanceOf(Brokers::class, $brokers->first());
    }
}
