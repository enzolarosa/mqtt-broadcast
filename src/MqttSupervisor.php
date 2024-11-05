<?php

namespace enzolarosa\MqttBroadcast;

use enzolarosa\MqttBroadcast\Contracts\Pausable;
use enzolarosa\MqttBroadcast\Contracts\Restartable;
use enzolarosa\MqttBroadcast\Contracts\Terminable;
use Illuminate\Support\Str;

class MqttSupervisor implements Pausable, Restartable, Terminable
{
    use ListensForSignals;

    /**
     * The environment that was used to provision this master supervisor.
     *
     * @var string|null
     */
    public $environment;

    /**
     * The name of the master supervisor.
     *
     * @var string
     */
    public $name;

    /**
     * All of the supervisors managed.
     *
     * @var \Illuminate\Support\Collection
     */
    public $supervisors;

    /**
     * Indicates if the master supervisor process is working.
     *
     * @var bool
     */
    public $working = true;

    /**
     * The output handler.
     *
     * @var \Closure|null
     */
    public $output;

    /**
     * The callback to use to resolve master supervisor names.
     *
     * @var \Closure|null
     */
    public static $nameResolver;

    public function __construct(?string $environment = null)
    {
        $this->environment = $environment;

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
     * @param  \Closure  $callback
     * @return void
     */
    public static function determineNameUsing(Closure $callback)
    {
        static::$nameResolver = $callback;
    }

    /**
     * Get the name of the command queue for the master supervisor.
     *
     * @return string
     */
    public static function commandQueue()
    {
        return 'master:'.static::name();
    }

    /**
     * Get the name of the command queue for the given master supervisor.
     *
     * @param  string|null  $name
     * @return string
     */
    public static function commandQueueFor($name = null)
    {
        return $name ? 'master:'.$name : static::commandQueue();
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
     * @param  \Closure  $callback
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
