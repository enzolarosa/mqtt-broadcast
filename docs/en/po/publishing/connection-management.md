# Connection Management

## What It Does

Connection management ensures that every MQTT connection is properly configured and validated before any message is sent or received. It acts as a safety net: if a broker's hostname, port, authentication credentials, or protocol settings are wrong, the system catches the error immediately rather than failing silently at runtime.

The system supports multiple named broker connections, each with their own settings, while providing sensible defaults that minimize configuration effort.

## User Journey

1. An administrator configures one or more broker connections in the package configuration file, providing at minimum a hostname and port.
2. When the system starts (either the supervisor process or a queued publish job), it validates the connection configuration.
3. If validation passes, the system creates an MQTT client with the correct settings and connects to the broker.
4. If validation fails, the system immediately reports the exact problem — which connection has which invalid setting — so the administrator can fix it.

## Business Rules

- Every connection must have a valid hostname (non-empty string) and port (between 1 and 65535).
- Quality of Service must be 0 (at most once), 1 (at least once), or 2 (exactly once).
- Connection timeout and keep-alive interval must be positive numbers.
- When authentication is enabled, both username and password are required — the system will not attempt to connect with partial credentials.
- Connection-specific settings override global defaults. If a connection does not specify a value, the global default is used.
- Setting a connection value to null means "use the global default", not "disable this setting".

## Edge Cases

- **Missing connection**: if code references a connection name that doesn't exist in configuration, the system throws an error identifying the missing connection by name.
- **Partial authentication**: enabling auth without providing both username and password is caught at validation time, not at connection time.
- **Self-signed certificates**: allowed by default for development convenience. Can be disabled per-connection for production environments that require certificate validation.
- **Client ID collision**: publisher jobs use a unique random identifier for each message to avoid conflicting with the long-running subscriber process. The subscriber can use either a fixed identifier (for persistent sessions) or an auto-generated one.
- **Config changes without restart**: since configuration is read at job dispatch time (for publishers) and at supervisor startup (for subscribers), config changes take effect on the next publish job or supervisor restart — not immediately for active subscriber connections.

## Permissions & Access

Connection management is internal infrastructure — it has no user-facing interface and no dedicated access controls. Configuration is managed through the package's configuration file and environment variables. Any code that publishes or subscribes to MQTT implicitly uses the connection management layer.

Dashboard users can see the connection status of each broker (connected, idle, reconnecting, disconnected) but cannot modify connection settings through the dashboard.
