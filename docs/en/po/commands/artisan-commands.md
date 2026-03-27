# Artisan Commands

## What It Does

MQTT Broadcast provides a set of command-line tools for managing the MQTT messaging system. These commands handle the full lifecycle: installing the package, starting the message supervisor, stopping it gracefully, and testing broker connectivity. They are run from the terminal and designed for DevOps, system administrators, or developers managing the application infrastructure.

## User Journey

### First-Time Setup

1. The administrator runs the install command: `php artisan mqtt-broadcast:install`.
2. The system publishes the configuration file, a service provider stub, and frontend dashboard assets into the application.
3. The system automatically registers the service provider (supports both Laravel 10 and 11+ project structures).
4. The administrator receives a checklist of next steps: configure the broker, update access permissions, run database migrations, and start the supervisor.

### Starting the Supervisor

1. The administrator runs `php artisan mqtt-broadcast`.
2. The system checks that no other supervisor is already running on this machine — if one exists, it warns and refuses to start (preventing duplicate instances).
3. The system detects the current environment (production, staging, local) and loads the broker connections configured for that environment.
4. All broker configurations are validated before any connection is established. If any configuration is invalid, all errors are shown together and the supervisor does not start.
5. The supervisor starts, displays the number of active broker connections, and begins monitoring messages.
6. The process runs in the foreground and can be stopped with Ctrl+C for a graceful shutdown.

### Stopping the Supervisor

1. The administrator runs `php artisan mqtt-broadcast:terminate`.
2. Optionally, a specific broker name can be provided to stop only that connection: `php artisan mqtt-broadcast:terminate broker-name`.
3. The system identifies all running processes on the current machine and sends them a graceful shutdown signal.
4. Database records and cache entries are cleaned up automatically.
5. If a process has already stopped, the system recognizes this and reports it without error.

### Testing Connectivity

1. The administrator runs `php artisan mqtt-broadcast:test {broker} {topic} {message}`.
2. The system sends a single message synchronously (bypassing the queue) to verify the broker is reachable and properly configured.
3. Success or failure is displayed immediately.

## Business Rules

- **One supervisor per machine**: the system prevents starting a second supervisor instance on the same machine. The first instance must be terminated before a new one can start.
- **Environment-aware startup**: the supervisor only connects to brokers configured for the current environment. A production server will not accidentally connect to development brokers (and vice versa).
- **All-or-nothing validation**: if any broker configuration is invalid, the entire supervisor refuses to start. This prevents partial operation where some brokers work but others silently fail.
- **Graceful shutdown preserves message integrity**: termination sends a graceful signal, allowing in-flight messages to complete processing before the connection is closed.
- **Best-effort cleanup**: the terminate command always succeeds from the user's perspective. Even if a process is already dead or unreachable, the system cleans up its database and cache records.
- **Install is idempotent**: running the install command multiple times is safe — it forces republishing of assets and skips service provider registration if already present.
- **Environment priority**: the environment is determined in order: explicit CLI flag > configuration file setting > application environment variable. This allows targeted overrides without changing configuration.

## Edge Cases

- **Supervisor already running**: attempting to start a second supervisor shows a warning and exits. No damage is done.
- **No brokers configured for the environment**: the supervisor shows an error message pointing to the config file and exits.
- **Process already dead when terminating**: the system detects this (via ESRCH error code) and treats it as a successful termination, cleaning up stale records.
- **System permission error on terminate**: if the current user doesn't have permission to signal a process, the error is reported but the command continues cleaning up other processes.
- **Install on Laravel 10 vs 11+**: the installer detects the Laravel version automatically and registers the service provider in the correct location (`bootstrap/providers.php` for 11+, `config/app.php` for 10 and below).
- **Test command with unreachable broker**: the synchronous publish fails immediately with a descriptive error, confirming the connectivity issue.

## Permissions & Access

- All commands require **terminal/CLI access** to the server — they cannot be triggered from the web interface.
- The `mqtt-broadcast:install` command requires **write access** to the application's config, providers, and public directories.
- The `mqtt-broadcast:terminate` command requires **process signaling permissions** (same user as the running supervisor, or root).
- There are no role-based or gate-based restrictions on Artisan commands — access control is managed at the server/SSH level.
