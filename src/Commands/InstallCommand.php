<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'mqtt-broadcast:install')]
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt-broadcast:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install all of the MQTT Broadcast resources';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Installing MQTT Broadcast resources.');

        collect([
            'mqtt-broadcast-config',
            'mqtt-broadcast-provider',
            'mqtt-broadcast-assets',
        ])->each(function ($tag) {
            $this->call('vendor:publish', ['--tag' => $tag, '--force' => true]);
        });

        $this->registerMqttBroadcastServiceProvider();

        $this->components->info('MQTT Broadcast scaffolding installed successfully.');
        $this->newLine();
        $this->components->info('Next steps:');
        $this->components->bulletList([
            'Configure your MQTT broker in config/mqtt-broadcast.php',
            'Update the gate in app/Providers/MqttBroadcastServiceProvider.php',
            'Run: php artisan migrate',
            'Start supervisor: php artisan mqtt-broadcast',
        ]);

        return self::SUCCESS;
    }

    /**
     * Register the MQTT Broadcast service provider in the application configuration file.
     */
    protected function registerMqttBroadcastServiceProvider(): void
    {
        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());
        $appConfig = file_get_contents(config_path('app.php'));

        $providerClass = "{$namespace}\\Providers\\MqttBroadcastServiceProvider::class";

        // Check if already registered
        if (Str::contains($appConfig, $providerClass)) {
            return;
        }

        // Try modern Laravel bootstrap/providers.php first
        if (file_exists(base_path('bootstrap/providers.php'))) {
            $this->registerInBootstrapProviders($providerClass);

            return;
        }

        // Fallback to config/app.php for older Laravel versions
        $this->registerInConfigApp($appConfig, $providerClass);
    }

    /**
     * Register the service provider in bootstrap/providers.php (Laravel 11+).
     */
    protected function registerInBootstrapProviders(string $providerClass): void
    {
        $filesystem = new Filesystem;
        $providersPath = base_path('bootstrap/providers.php');
        $contents = $filesystem->get($providersPath);

        // Add to the return array
        if (! Str::contains($contents, $providerClass)) {
            $contents = Str::replaceLast(
                '];',
                "    {$providerClass},\n];",
                $contents
            );

            $filesystem->put($providersPath, $contents);
        }
    }

    /**
     * Register the service provider in config/app.php (Laravel 10 and below).
     */
    protected function registerInConfigApp(string $appConfig, string $providerClass): void
    {
        // Find the providers array
        if (! Str::contains($appConfig, "'providers' => ServiceProvider::defaultProviders()->merge([")) {
            // Older format without defaultProviders()
            $appConfig = str_replace(
                "App\\Providers\\RouteServiceProvider::class,".PHP_EOL,
                "App\\Providers\\RouteServiceProvider::class,".PHP_EOL."        {$providerClass},".PHP_EOL,
                $appConfig
            );
        } else {
            // Newer format with defaultProviders()
            $appConfig = str_replace(
                "App\\Providers\\RouteServiceProvider::class,".PHP_EOL,
                "App\\Providers\\RouteServiceProvider::class,".PHP_EOL."        {$providerClass},".PHP_EOL,
                $appConfig
            );
        }

        file_put_contents(config_path('app.php'), $appConfig);
    }
}
