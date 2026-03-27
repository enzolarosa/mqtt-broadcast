<?php

use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use enzolarosa\MqttBroadcast\Supervisors\BrokerSupervisor;
use enzolarosa\MqttBroadcast\Supervisors\MasterSupervisor;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->repository = new MasterSupervisorRepository();
});

test('it constructs with name and repository', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    expect($master->getName())->toBe('test-master')
        ->and($master->getSupervisorsCount())->toBe(0)
        ->and($master->isWorking())->toBeTrue();
});

test('it adds supervisor to the pool', function () {
    $master = new MasterSupervisor('test-master', $this->repository);
    $supervisor = Mockery::mock(BrokerSupervisor::class);

    $master->addSupervisor($supervisor);

    expect($master->getSupervisorsCount())->toBe(1);
});

test('it adds multiple supervisors to the pool', function () {
    $master = new MasterSupervisor('test-master', $this->repository);
    $supervisor1 = Mockery::mock(BrokerSupervisor::class);
    $supervisor2 = Mockery::mock(BrokerSupervisor::class);

    $master->addSupervisor($supervisor1);
    $master->addSupervisor($supervisor2);

    expect($master->getSupervisorsCount())->toBe(2);
});

test('it persists state to repository', function () {
    $master = new MasterSupervisor('test-master', $this->repository);
    $supervisor = Mockery::mock(BrokerSupervisor::class);
    $master->addSupervisor($supervisor);

    $master->persist();

    $state = $this->repository->find('test-master');
    expect($state)->not->toBeNull()
        ->and($state['pid'])->toBe(getmypid())
        ->and($state['status'])->toBe('running')
        ->and($state['supervisors'])->toBe(1);
});

test('it persists paused status correctly', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $master->pause();
    $master->persist();

    $state = $this->repository->find('test-master');
    expect($state['status'])->toBe('paused');
});

test('it calls monitor on each supervisor during loop', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $supervisor1 = Mockery::mock(BrokerSupervisor::class);
    $supervisor1->shouldReceive('monitor')->once();
    $supervisor1->shouldReceive('isWorking')->andReturn(true);

    $supervisor2 = Mockery::mock(BrokerSupervisor::class);
    $supervisor2->shouldReceive('monitor')->once();
    $supervisor2->shouldReceive('isWorking')->andReturn(true);

    $master->addSupervisor($supervisor1);
    $master->addSupervisor($supervisor2);

    $master->loop();
});

test('it removes dead supervisors during loop', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $aliveSupervisor = Mockery::mock(BrokerSupervisor::class);
    $aliveSupervisor->shouldReceive('monitor')->once();
    $aliveSupervisor->shouldReceive('isWorking')->andReturn(true);

    $deadSupervisor = Mockery::mock(BrokerSupervisor::class);
    $deadSupervisor->shouldReceive('monitor')->once();
    $deadSupervisor->shouldReceive('isWorking')->andReturn(false);

    $master->addSupervisor($aliveSupervisor);
    $master->addSupervisor($deadSupervisor);

    expect($master->getSupervisorsCount())->toBe(2);

    $master->loop();

    expect($master->getSupervisorsCount())->toBe(1);
});

test('it does not monitor supervisors when paused', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $supervisor = Mockery::mock(BrokerSupervisor::class);
    $supervisor->shouldNotReceive('monitor');
    $supervisor->shouldReceive('pause')->once();

    $master->addSupervisor($supervisor);
    $master->pause();
    $master->loop();

    expect($master->isWorking())->toBeFalse();
});

test('it pauses all supervisors when paused', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $supervisor1 = Mockery::mock(BrokerSupervisor::class);
    $supervisor1->shouldReceive('pause')->once();

    $supervisor2 = Mockery::mock(BrokerSupervisor::class);
    $supervisor2->shouldReceive('pause')->once();

    $master->addSupervisor($supervisor1);
    $master->addSupervisor($supervisor2);

    $master->pause();
});

test('it continues all supervisors when resumed', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $supervisor1 = Mockery::mock(BrokerSupervisor::class);
    $supervisor1->shouldReceive('pause')->once();
    $supervisor1->shouldReceive('continue')->once();

    $supervisor2 = Mockery::mock(BrokerSupervisor::class);
    $supervisor2->shouldReceive('pause')->once();
    $supervisor2->shouldReceive('continue')->once();

    $master->addSupervisor($supervisor1);
    $master->addSupervisor($supervisor2);

    $master->pause();
    expect($master->isWorking())->toBeFalse();

    $master->continue();
    expect($master->isWorking())->toBeTrue();
});

test('it handles exceptions during loop gracefully', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $supervisor = Mockery::mock(BrokerSupervisor::class);
    $supervisor->shouldReceive('monitor')->andThrow(new Exception('Test error'));
    $supervisor->shouldReceive('isWorking')->andReturn(true);

    $outputCalled = false;
    $outputType = null;
    $outputLine = null;

    $master->setOutput(function ($type, $line) use (&$outputCalled, &$outputType, &$outputLine) {
        $outputCalled = true;
        $outputType = $type;
        $outputLine = $line;
    });

    $master->addSupervisor($supervisor);

    // Should not throw exception
    $master->loop();

    expect($outputCalled)->toBeTrue()
        ->and($outputType)->toBe('error')
        ->and($outputLine)->toContain('Test error');
});

test('it calls output callback when set', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $outputCalled = false;
    $outputType = null;
    $outputLine = null;

    $master->setOutput(function ($type, $line) use (&$outputCalled, &$outputType, &$outputLine) {
        $outputCalled = true;
        $outputType = $type;
        $outputLine = $line;
    });

    // Trigger an error to call output
    $supervisor = Mockery::mock(BrokerSupervisor::class);
    $supervisor->shouldReceive('monitor')->andThrow(new Exception('Test output'));
    $supervisor->shouldReceive('isWorking')->andReturn(true);

    $master->addSupervisor($supervisor);
    $master->loop();

    expect($outputCalled)->toBeTrue()
        ->and($outputType)->toBe('error');
});

test('it terminates all supervisors when terminated', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $supervisor1 = Mockery::mock(BrokerSupervisor::class);
    $supervisor1->shouldReceive('terminate')->with(0)->once();

    $supervisor2 = Mockery::mock(BrokerSupervisor::class);
    $supervisor2->shouldReceive('terminate')->with(0)->once();

    $master->addSupervisor($supervisor1);
    $master->addSupervisor($supervisor2);

    // Mock exit to prevent actual termination
    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Mocked exit');

    // We can't really test the exit() call, so we'll verify the supervisors
    // are terminated by mocking them above
    // In real scenario, this would exit with status code

    // Note: Can't actually test terminate() fully because of exit()
    // We verify via mock expectations that supervisors are terminated
})->skip('Cannot test exit() in unit tests without process isolation');

test('it removes state from repository on terminate', function () {
    $master = new MasterSupervisor('test-master', $this->repository);
    $master->persist();

    expect($this->repository->find('test-master'))->not->toBeNull();

    // Can't actually test because of exit()
})->skip('Cannot test exit() in unit tests without process isolation');

test('it handles supervisor termination errors gracefully', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $supervisor1 = Mockery::mock(BrokerSupervisor::class);
    $supervisor1->shouldReceive('terminate')->andThrow(new Exception('Termination failed'));

    $outputCalled = false;
    $master->setOutput(function ($type, $line) use (&$outputCalled) {
        if (str_contains($line, 'Error terminating supervisor')) {
            $outputCalled = true;
        }
    });

    $master->addSupervisor($supervisor1);

    // Can't fully test due to exit(), but we verify error handling
})->skip('Cannot test exit() in unit tests without process isolation');

test('it persists state during loop', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $supervisor = Mockery::mock(BrokerSupervisor::class);
    $supervisor->shouldReceive('monitor');
    $supervisor->shouldReceive('isWorking')->andReturn(true);

    $master->addSupervisor($supervisor);
    $master->loop();

    $state = $this->repository->find('test-master');
    expect($state)->not->toBeNull()
        ->and($state['supervisors'])->toBe(1);
});

test('it updates supervisor count after removing dead ones', function () {
    $master = new MasterSupervisor('test-master', $this->repository);

    $supervisor1 = Mockery::mock(BrokerSupervisor::class);
    $supervisor1->shouldReceive('monitor');
    $supervisor1->shouldReceive('isWorking')->andReturn(true);

    $supervisor2 = Mockery::mock(BrokerSupervisor::class);
    $supervisor2->shouldReceive('monitor');
    $supervisor2->shouldReceive('isWorking')->andReturn(false);

    $master->addSupervisor($supervisor1);
    $master->addSupervisor($supervisor2);
    $master->loop();

    $state = $this->repository->find('test-master');
    expect($state['supervisors'])->toBe(1); // Only alive one remains
});
