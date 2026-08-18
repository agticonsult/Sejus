<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AuditService;
use Symfony\Component\HttpFoundation\Response;

class AuditAccessLog
{
    public function __construct(
        protected AuditService $audit
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $resourceName = null): Response
    {
        $response = $next($request);

        $this->recordAuditLog($request, $response, $resourceName);

        return $response;
    }

    /**
     * Record immutable audit entry.
     */
    protected function recordAuditLog(Request $request, Response $response, ?string $resourceName): void
    {
        $userId = Auth::id();
        $route = $request->route();
        $routeName = $route ? $route->getName() : $request->path();
        $method = $request->method();

        // Extract prontuario identifier from route parameter or body
        $prontuarioParam = $request->route('prontuario')
            ?? $request->route('id')
            ?? $request->input('prontuario_id');

        $prontuarioId = null;
        if (is_object($prontuarioParam)) {
            $prontuarioId = $prontuarioParam->id ?? null;
        } elseif (is_numeric($prontuarioParam)) {
            $prontuarioId = (int) $prontuarioParam;
        }

        // Determine action label
        $action = match ($method) {
            'GET' => $resourceName ? "VIEW_{$resourceName}" : "READ_{$routeName}",
            'POST' => $resourceName ? "CREATE_{$resourceName}" : "STORE_{$routeName}",
            'PUT', 'PATCH' => $resourceName ? "UPDATE_{$resourceName}" : "UPDATE_{$routeName}",
            'DELETE' => $resourceName ? "DELETE_{$resourceName}" : "DESTROY_{$routeName}",
            default => "ACCESS_{$method}_{$routeName}",
        };

        $sanitizedInput = $request->except(['password', 'password_confirmation', '_token', 'token']);

        $details = [
            'route' => $routeName,
            'method' => $method,
            'uri' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'resource' => $resourceName,
            'payload' => $sanitizedInput,
        ];

        $this->audit->log(
            $prontuarioId,
            strtoupper($action),
            $details,
            $userId,
            $request->ip(),
            $request->userAgent()
        );
    }
}
