<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitApi
{
    /**
     * Rate limit configurations for different endpoint types
     */
    protected array $limits = [
        'auth' => ['attempts' => 5, 'decay' => 300], // 5 attempts per 5 minutes
        'transaction' => ['attempts' => 30, 'decay' => 60], // 30 transactions per minute
        'read' => ['attempts' => 100, 'decay' => 60], // 100 reads per minute
        'write' => ['attempts' => 20, 'decay' => 60], // 20 writes per minute
        'default' => ['attempts' => 60, 'decay' => 60], // 60 requests per minute
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $type = 'default'): Response
    {
        $key = $this->resolveRequestKey($request, $type);
        $maxAttempts = $this->limits[$type]['attempts'] ?? $this->limits['default']['attempts'];
        $decayMinutes = ($this->limits[$type]['decay'] ?? $this->limits['default']['decay']) / 60;

        // Check if user is whitelisted
        if ($this->isWhitelisted($request)) {
            return $next($request);
        }

        // Apply stricter limits for suspicious IPs
        if ($this->isSuspicious($request)) {
            $maxAttempts = max(1, intval($maxAttempts / 3));
        }

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($request, $key, $maxAttempts);
        }

        RateLimiter::hit($key, $this->limits[$type]['decay'] ?? $this->limits['default']['decay']);

        $response = $next($request);

        // Add rate limit headers to response
        return $this->addHeaders(
            $response,
            $maxAttempts,
            RateLimiter::remaining($key, $maxAttempts),
            RateLimiter::availableIn($key)
        );
    }

    /**
     * Resolve the request key for rate limiting
     */
    protected function resolveRequestKey(Request $request, string $type): string
    {
        $user = $request->user();

        // Use different keys for authenticated vs unauthenticated users
        if ($user) {
            return sprintf(
                'rate_limit:%s:%s:%s:%s',
                $type,
                'user',
                $user->id,
                $request->route()->getName() ?? $request->path()
            );
        }

        return sprintf(
            'rate_limit:%s:%s:%s:%s',
            $type,
            'ip',
            $request->ip(),
            $request->route()->getName() ?? $request->path()
        );
    }

    /**
     * Check if the request IP is whitelisted
     */
    protected function isWhitelisted(Request $request): bool
    {
        $whitelistedIps = config('security.whitelisted_ips', []);

        // Also whitelist authenticated admin users
        $user = $request->user();
        if ($user && $user->is_admin ?? false) {
            return true;
        }

        return in_array($request->ip(), $whitelistedIps);
    }

    /**
     * Check if the request appears suspicious
     */
    protected function isSuspicious(Request $request): bool
    {
        // Check for suspicious user agents
        $userAgent = $request->userAgent();
        $suspiciousAgents = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
            'python', 'java', 'ruby', 'perl', 'php'
        ];

        foreach ($suspiciousAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                return true;
            }
        }

        // Check for missing or suspicious headers
        if (!$request->hasHeader('Accept') ||
            !$request->hasHeader('Accept-Language') ||
            !$request->hasHeader('User-Agent')) {
            return true;
        }

        // Check for known bad IPs from cache
        $badIpKey = 'bad_ip:' . $request->ip();
        if (cache()->has($badIpKey)) {
            return true;
        }

        // Check for rapid endpoint scanning
        $scanKey = 'scan_detect:' . $request->ip();
        $endpoints = cache()->get($scanKey, []);
        $currentEndpoint = $request->path();

        if (!in_array($currentEndpoint, $endpoints)) {
            $endpoints[] = $currentEndpoint;
            cache()->put($scanKey, $endpoints, now()->addMinutes(5));
        }

        // If hitting many different endpoints rapidly, likely scanning
        if (count($endpoints) > 10) {
            cache()->put($badIpKey, true, now()->addHours(1));
            return true;
        }

        return false;
    }

    /**
     * Build the rate limit response
     */
    protected function buildResponse(Request $request, string $key, int $maxAttempts): Response
    {
        $retryAfter = RateLimiter::availableIn($key);

        // Log the rate limit violation
        \Log::channel('security')->warning('Rate limit exceeded', [
            'ip' => $request->ip(),
            'user_id' => $request->user()->id ?? null,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'retry_after' => $retryAfter,
        ]);

        // Mark IP as potentially suspicious
        if (RateLimiter::attempts($key) > $maxAttempts * 2) {
            $badIpKey = 'bad_ip:' . $request->ip();
            cache()->put($badIpKey, true, now()->addHours(24));
        }

        $message = 'Too many requests. Please retry after ' . $retryAfter . ' seconds.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'retry_after' => $retryAfter,
            ], 429);
        }

        abort(429, $message);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, ?int $retryAfter = null): Response
    {
        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];

        if ($retryAfter !== null && $remainingAttempts === 0) {
            $headers['X-RateLimit-Reset'] = now()->addSeconds($retryAfter)->timestamp;
            $headers['Retry-After'] = $retryAfter;
        }

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
