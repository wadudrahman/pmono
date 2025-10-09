<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Fields that should not be sanitized
     */
    protected array $except = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize input data
        $this->sanitizeInput($request);

        // Check for XSS attempts
        if ($this->detectXssAttempt($request)) {
            return $this->handleXssAttempt($request);
        }

        // Check for SQL injection attempts
        if ($this->detectSqlInjection($request)) {
            return $this->handleSqlInjection($request);
        }

        // Check for path traversal attempts
        if ($this->detectPathTraversal($request)) {
            return $this->handlePathTraversal($request);
        }

        // Check for command injection attempts
        if ($this->detectCommandInjection($request)) {
            return $this->handleCommandInjection($request);
        }

        return $next($request);
    }

    /**
     * Sanitize all input data
     */
    protected function sanitizeInput(Request $request): void
    {
        $input = $request->all();
        $sanitized = $this->sanitizeArray($input);

        // Replace request input with sanitized data
        $request->replace($sanitized);

        // Also sanitize query parameters
        if ($request->query()) {
            $queryParams = $this->sanitizeArray($request->query());
            foreach ($queryParams as $key => $value) {
                $request->query->set($key, $value);
            }
        }
    }

    /**
     * Recursively sanitize array data
     */
    protected function sanitizeArray(array $data, string $parentKey = ''): array
    {
        foreach ($data as $key => $value) {
            $fullKey = $parentKey ? "{$parentKey}.{$key}" : $key;

            // Skip exempt fields
            if ($this->isExempt($fullKey)) {
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value, $fullKey);
            } elseif (is_string($value)) {
                $data[$key] = $this->sanitizeString($value, $fullKey);
            } elseif (is_numeric($value)) {
                $data[$key] = $this->sanitizeNumeric($value);
            }
        }

        return $data;
    }

    /**
     * Sanitize string input
     */
    protected function sanitizeString(string $value, string $key = ''): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Trim whitespace
        $value = trim($value);

        // Remove invisible characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        // Convert special HTML characters
        if (!$this->isHtmlField($key)) {
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        }

        // Remove JavaScript event handlers
        $value = preg_replace('/on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $value);

        // Remove script tags
        $value = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $value);

        // Remove style tags with expression
        $value = preg_replace('/<style[^>]*>.*?expression\s*\([^)]*\).*?<\/style>/si', '', $value);

        // Remove javascript: protocol
        $value = preg_replace('/javascript:/i', '', $value);

        // Remove data: URIs that might contain scripts
        $value = preg_replace('/data:(?!image\/(gif|png|jpeg|jpg|svg\+xml|webp);base64)/i', '', $value);

        return $value;
    }

    /**
     * Sanitize numeric input
     */
    protected function sanitizeNumeric($value)
    {
        if (is_int($value)) {
            return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        }

        return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Check if field is exempt from sanitization
     */
    protected function isExempt(string $key): bool
    {
        foreach ($this->except as $exempt) {
            if ($key === $exempt || str_ends_with($key, '.' . $exempt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if field should preserve HTML
     */
    protected function isHtmlField(string $key): bool
    {
        $htmlFields = ['description', 'content', 'body', 'message'];

        foreach ($htmlFields as $field) {
            if (str_contains($key, $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect XSS attempts
     */
    protected function detectXssAttempt(Request $request): bool
    {
        $input = json_encode($request->all());

        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/si',
            '/<iframe[^>]*>.*?<\/iframe>/si',
            '/<embed[^>]*>/i',
            '/<object[^>]*>.*?<\/object>/si',
            '/javascript:/i',
            '/vbscript:/i',
            '/on\w+\s*=\s*["\']?[^"\']*["\']?/i',
            '/expression\s*\([^)]*\)/i',
            '/<img[^>]+src[\\s]*=[\\s]*["\']javascript:/i',
            '/document\.(cookie|write|location)/i',
            '/window\.(location|open)/i',
            '/eval\s*\(/i',
            '/setTimeout\s*\(/i',
            '/setInterval\s*\(/i',
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect SQL injection attempts
     */
    protected function detectSqlInjection(Request $request): bool
    {
        $input = strtolower(json_encode($request->all()));

        $sqlPatterns = [
            '/\bunion\s+(all\s+)?select\b/i',
            '/\bselect\s+.*\s+from\s+.*\s+(where|having|group by|order by)\b/i',
            '/\binsert\s+into\s+.*\s+values\s*\(/i',
            '/\bupdate\s+.*\s+set\s+/i',
            '/\bdelete\s+from\s+/i',
            '/\bdrop\s+(database|table|column|index)\b/i',
            '/\bexec(ute)?\s*\(/i',
            '/\bscript\s*>/i',
            '/\bcreate\s+(database|table|index|view|procedure|function)\b/i',
            '/\balter\s+(database|table|column)\b/i',
            '/\b(sleep|benchmark|load_file|into outfile|into dumpfile)\s*\(/i',
            '/\bwaitfor\s+delay\b/i',
            '/\bconvert\s*\(.*using\s+/i',
            '/\b0x[0-9a-f]+\b/i', // Hex encoding
            '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i', // Common SQL injection characters
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                // Check if it's not a legitimate use (like in a code editor)
                if (!$this->isLegitimateCodeInput($request)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect path traversal attempts
     */
    protected function detectPathTraversal(Request $request): bool
    {
        $input = json_encode($request->all());

        $pathPatterns = [
            '/\.\.[\/\\\\]/', // ../ or ..\
            '/\.\.[\/\\\\]{2,}/', // Multiple traversals
            '/%2e%2e[%2f%5c]/i', // URL encoded
            '/%252e%252e/i', // Double URL encoded
            '/\.\.;/', // Semicolon bypass
            '/\.\.%00/', // Null byte injection
            '/\.\.%01/', // Alternative null bytes
        ];

        foreach ($pathPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        // Check for absolute paths
        if (preg_match('/^([a-z]:)?[\/\\\\]/i', $input)) {
            return true;
        }

        return false;
    }

    /**
     * Detect command injection attempts
     */
    protected function detectCommandInjection(Request $request): bool
    {
        $input = json_encode($request->all());

        $commandPatterns = [
            '/[;|`&$]/', // Command chaining characters
            '/\$\(.*\)/', // Command substitution
            '/`.*`/', // Backtick execution
            '/\bping\s+-[nc]\b/i',
            '/\b(nc|netcat|telnet|bash|sh|cmd|powershell)\b/i',
            '/\b(wget|curl|fetch|lwp-request)\b/i',
            '/\|{2}/', // OR operator
            '/&{2}/', // AND operator
            '/>\s*\/dev\/null/i',
            '/2>&1/i',
        ];

        foreach ($commandPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                // Check if it's not legitimate input
                if (!$this->isLegitimateCommandInput($request)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if code input is legitimate (e.g., code editor)
     */
    protected function isLegitimateCodeInput(Request $request): bool
    {
        // Check if the route is for code editing or similar
        $legitimateRoutes = ['api/code/*', 'admin/editor/*'];

        foreach ($legitimateRoutes as $route) {
            if ($request->is($route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if command input is legitimate
     */
    protected function isLegitimateCommandInput(Request $request): bool
    {
        // Similar check for legitimate command input
        return $this->isLegitimateCodeInput($request);
    }

    /**
     * Handle XSS attempt
     */
    protected function handleXssAttempt(Request $request): Response
    {
        $this->logSecurityEvent('xss_attempt', $request);

        return response()->json([
            'message' => 'Invalid input detected. Request has been blocked.',
            'error_code' => 'SECURITY_XSS_DETECTED',
        ], 403);
    }

    /**
     * Handle SQL injection attempt
     */
    protected function handleSqlInjection(Request $request): Response
    {
        $this->logSecurityEvent('sql_injection_attempt', $request);

        return response()->json([
            'message' => 'Invalid input detected. Request has been blocked.',
            'error_code' => 'SECURITY_SQL_INJECTION_DETECTED',
        ], 403);
    }

    /**
     * Handle path traversal attempt
     */
    protected function handlePathTraversal(Request $request): Response
    {
        $this->logSecurityEvent('path_traversal_attempt', $request);

        return response()->json([
            'message' => 'Invalid path detected. Request has been blocked.',
            'error_code' => 'SECURITY_PATH_TRAVERSAL_DETECTED',
        ], 403);
    }

    /**
     * Handle command injection attempt
     */
    protected function handleCommandInjection(Request $request): Response
    {
        $this->logSecurityEvent('command_injection_attempt', $request);

        return response()->json([
            'message' => 'Invalid input detected. Request has been blocked.',
            'error_code' => 'SECURITY_COMMAND_INJECTION_DETECTED',
        ], 403);
    }

    /**
     * Log security event
     */
    protected function logSecurityEvent(string $type, Request $request): void
    {
        app(\App\Services\AuditLogService::class)->logSecurityEvent(
            $type,
            'Security threat detected and blocked',
            [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'input' => $request->all(),
            ]
        );

        // Block IP after multiple attempts
        $attemptKey = 'security_attempt:' . $request->ip();
        $attempts = cache()->increment($attemptKey, 1, now()->addHour());

        if ($attempts >= 3) {
            cache()->put('blocked_ip:' . $request->ip(), true, now()->addDay());
        }
    }
}