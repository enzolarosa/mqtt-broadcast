<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    /**
     * Handle the incoming request.
     *
     * Authorization strategy (following Laravel Horizon pattern):
     * 1. In 'local' environment: Always allow access
     * 2. In other environments: Check 'viewMqttBroadcast' gate
     *
     * Users can customize authorization by defining the gate in their
     * App\Providers\MqttBroadcastServiceProvider:
     *
     * Gate::define('viewMqttBroadcast', function ($user) {
     *     return in_array($user->email, [
     *         'admin@example.com',
     *     ]);
     * });
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Always allow access in local environment
        if (app()->environment('local')) {
            return $next($request);
        }

        // Check if user is authorized via gate
        if (Gate::allows('viewMqttBroadcast', [$request->user()])) {
            return $next($request);
        }

        // Unauthorized
        return response('Forbidden', 403);
    }
}
