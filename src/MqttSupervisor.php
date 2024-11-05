<?php

namespace enzolarosa\MqttBroadcast;

use Carbon\CarbonImmutable;
use Closure;
use enzolarosa\MqttBroadcast\Contracts\MqttSupervisorRepository;
use enzolarosa\MqttBroadcast\Contracts\Pausable;
use enzolarosa\MqttBroadcast\Contracts\Restartable;
use enzolarosa\MqttBroadcast\Contracts\Terminable;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Str;
use Throwable;

class MqttSupervisor implements Pausable, Restartable, Terminable
{
    use ListensForSignals;

    /**
     * The name of the master supervisor.
     */
    public string $name;

    /**
     * All of the supervisors managed.
     */
    public \Illuminate\Support\Collection $supervisors;

    /**
     * Indicates if the master supervisor process is working.
     */
    public bool $working = true;

    /**
     * The output handler.
     *
     * @var Closure|null
     */
    public $output;

    /**
     * The callback to use to resolve master supervisor names.
     *
     * @var Closure|null
     */
    public static $nameResolver;

    public function __construct()
    {
        $this->name = static::name();
        $this->supervisors = collect();

        $this->output = function () {
            //
        };
    }

    /**
     * Get the name for this master supervisor.
     *
     * @return string
     */
    public static function name()
    {
        static $token;

        if (! $token) {
            $token = Str::random(4);
        }

        return static::basename().'-'.$token;
    }

    /**
     * Get the basename for the machine's master supervisors.
     *
     * @return string
     */
    public static function basename()
    {
        return static::$nameResolver
            ? call_user_func(static::$nameResolver)
            : Str::slug(gethostname());
    }

    /**
     * Use the given callback to resolve master supervisor names.
     *
     * @return void
     */
    public static function determineNameUsing(Closure $callback)
    {
        static::$nameResolver = $callback;
    }

    /**
     * Terminate all current supervisors and start fresh ones.
     *
     * @return void
     */
    public function restart()
    {
        $this->working = true;

        $this->supervisors->each->terminateWithStatus(1);
    }

    /**
     * Pause the supervisors.
     *
     * @return void
     */
    public function pause()
    {
        $this->working = false;

        $this->supervisors->each->pause();
    }

    /**
     * Instruct the supervisors to continue working.
     *
     * @return void
     */
    public function continue()
    {
        $this->working = true;

        $this->supervisors->each->continue();
    }

    public function terminate($status = 0)
    {
        $this->working = false;

        $longest = config('mqtt-broadcast.longest_wait_time', 60);

        $this->supervisors->each->terminate();

        // We will go ahead and remove this master supervisor's record from storage so
        // another master supervisor could get started in its place without waiting
        // for it to really finish terminating all of its underlying supervisors.
        app(MqttSupervisorRepository::class)
            ->forget($this->name);

        $startedTerminating = CarbonImmutable::now();

        // Here we will wait until all the child supervisors finish terminating and
        // then exit the process. We will keep track of a timeout value so that the
        // process does not get stuck in an infinite loop here waiting for these.
        while (count($this->supervisors->filter->isRunning())) {
            if (CarbonImmutable::now()->subSeconds($longest)
                ->gte($startedTerminating)) {
                break;
            }

            sleep(1);
        }

        $this->exit($status);
    }

    public function monitor(Closure $callback)
    {
        $this->ensureNoOtherMasterSupervisors();

        $this->listenForSignals();

        $this->persist();

        while (true) {
            sleep(1);

            $this->loop($callback);
        }
    }

    public function persist()
    {
        app(MqttSupervisorRepository::class)->update($this);
    }

    public function loop(Closure $callback)
    {
        try {
            $this->processPendingSignals();

            call_user_func($callback);

            if ($this->working) {
                $this->monitorSupervisors();
            }

            $this->persist();

        } catch (Throwable $e) {
            app(ExceptionHandler::class)->report($e);
        }
    }

    /**
     * Ensure that this is the only master supervisor running for this machine.
     *
     * @return void
     *
     * @throws Exception
     */
    public function ensureNoOtherMasterSupervisors()
    {
        if (app(MqttSupervisorRepository::class)->find($this->name) !== null) {
            throw new Exception('A master supervisor is already running on this machine.');
        }
    }

    /**
     * Get the process ID for this supervisor.
     *
     * @return int
     */
    public function pid()
    {
        return getmypid();
    }

    /**
     * Get the current memory usage (in megabytes).
     *
     * @return float
     */
    public function memoryUsage()
    {
        return memory_get_usage() / 1024 / 1024;
    }

    /**
     * Set the output handler.
     *
     * @return $this
     */
    public function handleOutputUsing(Closure $callback)
    {
        $this->output = $callback;

        return $this;
    }

    /**
     * Handle the given output.
     *
     * @param  string  $type
     * @param  string  $line
     * @return void
     */
    public function output($type, $line)
    {
        call_user_func($this->output, $type, $line);
    }

    /**
     * "Monitor" all the supervisors.
     *
     * @return void
     */
    protected function monitorSupervisors()
    {
        $this->supervisors->each->monitor();

        $this->supervisors = $this->supervisors->reject->dead;
    }

    /**
     * Shutdown the supervisor.
     *
     * @param  int  $status
     * @return void
     */
    protected function exit($status = 0)
    {
        $this->exitProcess($status);
    }

    /**
     * Exit the PHP process.
     *
     * @param  int  $status
     * @return void
     */
    protected function exitProcess($status = 0)
    {
        exit((int) $status);
    }
}
