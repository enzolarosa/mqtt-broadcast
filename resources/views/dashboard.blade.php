<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MQTT Broadcast Dashboard</title>

    {{-- App Configuration --}}
    <script>
        window.mqttBroadcast = {
            basePath: '{{ config('mqtt-broadcast.path', 'mqtt-broadcast') }}',
            apiUrl: '/{{ config('mqtt-broadcast.path', 'mqtt-broadcast') }}/api',
            loggingEnabled: {{ config('mqtt-broadcast.logs.enable', false) ? 'true' : 'false' }},
            refreshInterval: 5000, // 5 seconds
        };
    </script>

    {{-- Dashboard assets --}}
    {!! enzolarosa\MqttBroadcast\MqttBroadcast::css() !!}
    {!! enzolarosa\MqttBroadcast\MqttBroadcast::js() !!}
</head>
<body>
    {{-- React app mount point --}}
    <div id="mqtt-dashboard-root"></div>

    {{-- Fallback for when JavaScript is disabled --}}
    <noscript>
        <div style="padding: 2rem; text-align: center; background: #fee; color: #c00;">
            <h1>JavaScript Required</h1>
            <p>MQTT Broadcast Dashboard requires JavaScript to be enabled.</p>
        </div>
    </noscript>
</body>
</html>
