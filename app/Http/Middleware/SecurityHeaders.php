<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Security headers to be applied
     */
    protected array $headers = [
        // Prevent XSS attacks
        'X-XSS-Protection' => '1; mode=block',

        // Prevent clickjacking
        'X-Frame-Options' => 'DENY',

        // Prevent MIME type sniffing
        'X-Content-Type-Options' => 'nosniff',

        // Referrer policy for privacy
        'Referrer-Policy' => 'strict-origin-when-cross-origin',

        // Permissions policy (formerly Feature Policy)
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=()',

        // Remove server identification
        'X-Powered-By' => '',
        'Server' => '',
    ];

    /**
     * Content Security Policy directives
     */
    protected array $cspDirectives = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://www.google.com', 'https://www.gstatic.com'],
        'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
        'img-src' => ["'self'", 'data:', 'https:', 'blob:'],
        'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
        'connect-src' => ["'self'", 'wss:', 'https:'],
        'media-src' => ["'self'"],
        'object-src' => ["'none'"],
        'frame-src' => ["'self'", 'https://www.google.com'],
        'frame-ancestors' => ["'none'"],
        'form-action' => ["'self'"],
        'base-uri' => ["'self'"],
        'upgrade-insecure-requests' => [],
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Apply security headers
        foreach ($this->headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        // Build and apply CSP header
        $csp = $this->buildContentSecurityPolicy($request);
        if ($csp) {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // Apply HSTS for HTTPS connections
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Add nonce for inline scripts if needed
        if ($request->attributes->has('csp-nonce')) {
            $this->addNonceToResponse($response, $request->attributes->get('csp-nonce'));
        }

        // Clear sensitive headers that might leak information
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Add security headers for API responses
        if ($request->is('api/*')) {
            $this->addApiSecurityHeaders($response);
        }

        // Add cache control for sensitive pages
        if ($this->shouldPreventCaching($request)) {
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    /**
     * Build Content Security Policy string
     */
    protected function buildContentSecurityPolicy(Request $request): string
    {
        $directives = $this->cspDirectives;

        // Add Vite dev server in development
        if (app()->environment('local', 'development')) {
            $viteUrl = env('VITE_URL', 'http://localhost:19173');
            $directives['script-src'][] = $viteUrl;
            $directives['style-src'][] = $viteUrl;
            $directives['connect-src'][] = $viteUrl;
            $directives['connect-src'][] = 'ws://localhost:19173';
            $directives['img-src'][] = $viteUrl;
        }

        // Add nonce for inline scripts if available
        if ($nonce = $request->attributes->get('csp-nonce')) {
            $directives['script-src'][] = "'nonce-{$nonce}'";
            $directives['style-src'][] = "'nonce-{$nonce}'";
        }

        // Add WebSocket URL for real-time features
        if (config('broadcasting.connections.pusher.key')) {
            $wsHost = config('broadcasting.connections.pusher.options.host', 'ws.pusherapp.com');
            $directives['connect-src'][] = "wss://{$wsHost}";
        }

        // Build CSP string
        $policy = [];
        foreach ($directives as $directive => $sources) {
            if (empty($sources)) {
                $policy[] = $directive;
            } else {
                $policy[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        // Add report-uri if configured
        if ($reportUri = config('security.csp_report_uri')) {
            $policy[] = "report-uri {$reportUri}";
        }

        return implode('; ', $policy);
    }

    /**
     * Add nonce to inline scripts and styles in response
     */
    protected function addNonceToResponse(Response $response, string $nonce): void
    {
        $content = $response->getContent();

        // Add nonce to script tags
        $content = preg_replace(
            '/<script(?![^>]*\snonce=)/',
            '<script nonce="' . $nonce . '"',
            $content
        );

        // Add nonce to style tags
        $content = preg_replace(
            '/<style(?![^>]*\snonce=)/',
            '<style nonce="' . $nonce . '"',
            $content
        );

        $response->setContent($content);
    }

    /**
     * Add additional security headers for API responses
     */
    protected function addApiSecurityHeaders(Response $response): void
    {
        // Prevent API responses from being embedded
        $response->headers->set('X-Frame-Options', 'DENY');

        // Set content type for API responses
        if (!$response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', 'application/json');
        }

        // Add CORS headers if not already set
        if (!$response->headers->has('Access-Control-Allow-Origin')) {
            $allowedOrigins = config('cors.allowed_origins', ['*']);
            $origin = request()->headers->get('Origin');

            if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
        }

        // API versioning header
        $response->headers->set('X-API-Version', config('app.api_version', 'v1'));

        // Add deprecation warnings if applicable
        if ($deprecation = $this->getDeprecationWarning(request())) {
            $response->headers->set('X-API-Deprecation-Warning', $deprecation);
        }
    }

    /**
     * Check if response should prevent caching
     */
    protected function shouldPreventCaching(Request $request): bool
    {
        // Prevent caching for authenticated pages
        if ($request->user()) {
            return true;
        }

        // Prevent caching for sensitive routes
        $sensitivePaths = [
            'api/v1/auth/*',
            'api/v1/transactions/*',
            'api/v1/users/*',
            'admin/*',
        ];

        foreach ($sensitivePaths as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get deprecation warning for old API endpoints
     */
    protected function getDeprecationWarning(Request $request): ?string
    {
        // Check if using deprecated endpoints
        $deprecatedEndpoints = config('security.deprecated_endpoints', []);

        foreach ($deprecatedEndpoints as $endpoint => $info) {
            if ($request->is($endpoint)) {
                return sprintf(
                    'This endpoint is deprecated and will be removed on %s. Please use %s instead.',
                    $info['removal_date'] ?? 'a future date',
                    $info['replacement'] ?? 'the updated API'
                );
            }
        }

        return null;
    }
}