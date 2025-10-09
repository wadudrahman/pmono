<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Transaction;

class AuditLogService
{
    /**
     * Log levels for different event types
     */
    protected array $eventLevels = [
        'login' => 'info',
        'logout' => 'info',
        'login_failed' => 'warning',
        'transaction_created' => 'info',
        'transaction_failed' => 'error',
        'suspicious_activity' => 'warning',
        'security_breach' => 'critical',
        'data_export' => 'notice',
        'permission_denied' => 'warning',
        'account_locked' => 'warning',
        'password_changed' => 'notice',
        'api_key_created' => 'notice',
        'api_key_revoked' => 'notice',
    ];

    /**
     * Log an audit event
     */
    public function log(
        string $eventType,
        ?User $user = null,
        array $data = [],
        ?Request $request = null
    ): void {
        $request = $request ?? request();
        $level = $this->eventLevels[$eventType] ?? 'info';

        $logData = [
            'event_type' => $eventType,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_method' => $request?->method(),
            'request_url' => $request?->fullUrl(),
            'session_id' => session()->getId(),
            'timestamp' => now()->toIso8601String(),
            'data' => $this->sanitizeData($data),
        ];

        // Add geolocation if available
        if ($request && $geoData = $this->getGeolocation($request->ip())) {
            $logData['geolocation'] = $geoData;
        }

        // Add request fingerprint
        if ($request) {
            $logData['fingerprint'] = $this->generateFingerprint($request);
        }

        // Log to appropriate channel based on event type
        if ($this->isCriticalEvent($eventType)) {
            $this->logCriticalEvent($logData);
        }

        // Store in database for persistent audit trail
        $this->storeAuditLog($logData);

        // Log to file
        Log::channel('audit')->log($level, $eventType, $logData);

        // Send alerts for critical events
        if ($this->shouldAlert($eventType)) {
            $this->sendSecurityAlert($eventType, $logData);
        }
    }

    /**
     * Log transaction-specific audit event
     */
    public function logTransaction(
        Transaction $transaction,
        string $action,
        array $additionalData = []
    ): void {
        $data = array_merge([
            'transaction_id' => $transaction->id,
            'reference_number' => $transaction->reference_number,
            'sender_id' => $transaction->sender_id,
            'receiver_id' => $transaction->receiver_id,
            'amount' => $transaction->amount,
            'commission_fee' => $transaction->commission_fee,
            'status' => $transaction->status,
            'action' => $action,
        ], $additionalData);

        $eventType = $action === 'failed' ? 'transaction_failed' : 'transaction_created';

        $this->log($eventType, auth()->user(), $data);
    }

    /**
     * Log security-related event
     */
    public function logSecurityEvent(
        string $type,
        string $description,
        array $context = []
    ): void {
        $data = array_merge([
            'security_event_type' => $type,
            'description' => $description,
            'severity' => $this->getSecuritySeverity($type),
        ], $context);

        $this->log('security_breach', auth()->user(), $data);

        // Immediate notification for high severity events
        if ($this->getSecuritySeverity($type) === 'critical') {
            $this->triggerSecurityResponse($type, $data);
        }
    }

    /**
     * Store audit log in database
     */
    protected function storeAuditLog(array $data): void
    {
        try {
            DB::table('audit_logs')->insert([
                'event_type' => $data['event_type'],
                'user_id' => $data['user_id'],
                'ip_address' => $data['ip_address'],
                'user_agent' => $data['user_agent'],
                'request_method' => $data['request_method'],
                'request_url' => $data['request_url'],
                'session_id' => $data['session_id'],
                'fingerprint' => $data['fingerprint'] ?? null,
                'geolocation' => json_encode($data['geolocation'] ?? null),
                'data' => json_encode($data['data']),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Fallback to file logging if database fails
            Log::channel('audit')->error('Failed to store audit log in database', [
                'error' => $e->getMessage(),
                'audit_data' => $data,
            ]);
        }
    }

    /**
     * Sanitize sensitive data before logging
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'credit_card',
            'cvv',
            'ssn',
            'api_key',
            'api_secret',
            'token',
            'secret',
        ];

        foreach ($data as $key => $value) {
            // Mask sensitive fields
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $data[$key] = '***REDACTED***';
                    continue 2;
                }
            }

            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            }

            // Mask credit card numbers
            if (is_string($value) && preg_match('/\d{13,19}/', $value)) {
                $data[$key] = preg_replace('/\d(?=\d{4})/', '*', $value);
            }

            // Mask email addresses partially
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $parts = explode('@', $value);
                $data[$key] = substr($parts[0], 0, 2) . '***@' . $parts[1];
            }
        }

        return $data;
    }

    /**
     * Generate request fingerprint
     */
    protected function generateFingerprint(Request $request): string
    {
        $components = [
            $request->ip(),
            $request->userAgent(),
            $request->header('Accept'),
            $request->header('Accept-Language'),
            $request->header('Accept-Encoding'),
            $request->secure() ? 'https' : 'http',
        ];

        return hash('sha256', implode('|', array_filter($components)));
    }

    /**
     * Get geolocation data for IP
     */
    protected function getGeolocation(string $ip): ?array
    {
        // Skip for local IPs
        if (in_array($ip, ['127.0.0.1', '::1']) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false) {
            return null;
        }

        // Cache geolocation data
        $cacheKey = 'geo:' . $ip;
        return cache()->remember($cacheKey, now()->addDay(), function () use ($ip) {
            try {
                // Use a geolocation service (example with ipapi.co)
                $response = file_get_contents("https://ipapi.co/{$ip}/json/");
                $data = json_decode($response, true);

                if ($data && !isset($data['error'])) {
                    return [
                        'country' => $data['country_name'] ?? null,
                        'region' => $data['region'] ?? null,
                        'city' => $data['city'] ?? null,
                        'postal' => $data['postal'] ?? null,
                        'latitude' => $data['latitude'] ?? null,
                        'longitude' => $data['longitude'] ?? null,
                        'timezone' => $data['timezone'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                Log::error('Geolocation lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
            }

            return null;
        });
    }

    /**
     * Check if event is critical
     */
    protected function isCriticalEvent(string $eventType): bool
    {
        $criticalEvents = [
            'security_breach',
            'unauthorized_access',
            'data_breach',
            'privilege_escalation',
            'mass_assignment',
            'sql_injection_attempt',
            'xss_attempt',
        ];

        return in_array($eventType, $criticalEvents);
    }

    /**
     * Log critical event with immediate notification
     */
    protected function logCriticalEvent(array $data): void
    {
        // Log to separate critical events file
        Log::channel('critical')->critical('Critical Security Event', $data);

        // Store in high-priority queue for immediate processing
        dispatch(new \App\Jobs\ProcessCriticalSecurityEvent($data))->onQueue('critical');

        // Increment security incident counter
        cache()->increment('security_incidents_' . now()->format('Y-m-d'));
    }

    /**
     * Check if event should trigger alerts
     */
    protected function shouldAlert(string $eventType): bool
    {
        $alertEvents = [
            'security_breach',
            'login_failed' => fn() => $this->checkFailedLoginThreshold(),
            'transaction_failed' => fn() => $this->checkTransactionFailureRate(),
            'suspicious_activity',
            'account_locked',
        ];

        if (isset($alertEvents[$eventType])) {
            return is_callable($alertEvents[$eventType])
                ? $alertEvents[$eventType]()
                : true;
        }

        return false;
    }

    /**
     * Check failed login threshold
     */
    protected function checkFailedLoginThreshold(): bool
    {
        $count = cache()->get('failed_logins_' . now()->format('Y-m-d-H'), 0);
        return $count > 50; // Alert if more than 50 failed logins in an hour
    }

    /**
     * Check transaction failure rate
     */
    protected function checkTransactionFailureRate(): bool
    {
        $failures = cache()->get('transaction_failures_' . now()->format('Y-m-d-H'), 0);
        $total = cache()->get('transaction_total_' . now()->format('Y-m-d-H'), 1);

        $failureRate = ($failures / $total) * 100;
        return $failureRate > 10; // Alert if failure rate exceeds 10%
    }

    /**
     * Send security alert
     */
    protected function sendSecurityAlert(string $eventType, array $data): void
    {
        // Send email notification
        $admins = User::where('is_admin', true)->get();
        foreach ($admins as $admin) {
            \Mail::to($admin->email)->queue(new \App\Mail\SecurityAlert($eventType, $data));
        }

        // Send to security monitoring service
        if ($webhookUrl = config('security.alert_webhook_url')) {
            \Http::post($webhookUrl, [
                'event_type' => $eventType,
                'severity' => $this->eventLevels[$eventType] ?? 'info',
                'data' => $data,
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        // Log to external SIEM if configured
        if (config('security.siem_enabled')) {
            $this->sendToSiem($eventType, $data);
        }
    }

    /**
     * Get security severity level
     */
    protected function getSecuritySeverity(string $type): string
    {
        $severityMap = [
            'sql_injection' => 'critical',
            'xss_attack' => 'high',
            'csrf_attack' => 'high',
            'brute_force' => 'high',
            'privilege_escalation' => 'critical',
            'unauthorized_access' => 'high',
            'suspicious_activity' => 'medium',
            'rate_limit_exceeded' => 'low',
        ];

        return $severityMap[$type] ?? 'medium';
    }

    /**
     * Trigger automated security response
     */
    protected function triggerSecurityResponse(string $type, array $data): void
    {
        switch ($type) {
            case 'sql_injection':
            case 'xss_attack':
                // Block IP immediately
                $this->blockIpAddress($data['ip_address'] ?? request()->ip());
                break;

            case 'brute_force':
                // Temporarily lock affected account
                if ($userId = $data['user_id'] ?? null) {
                    $this->lockUserAccount($userId);
                }
                break;

            case 'privilege_escalation':
                // Revoke all sessions for the user
                if ($userId = $data['user_id'] ?? null) {
                    $this->revokeUserSessions($userId);
                }
                break;
        }

        // Create incident ticket
        $this->createSecurityIncident($type, $data);
    }

    /**
     * Block IP address
     */
    protected function blockIpAddress(string $ip): void
    {
        cache()->forever('blocked_ip:' . $ip, [
            'blocked_at' => now(),
            'reason' => 'Automated security response',
        ]);

        Log::critical('IP address blocked', ['ip' => $ip]);
    }

    /**
     * Lock user account
     */
    protected function lockUserAccount(int $userId): void
    {
        User::where('id', $userId)->update([
            'locked_at' => now(),
            'lock_reason' => 'Security incident - automated response',
        ]);

        Log::warning('User account locked', ['user_id' => $userId]);
    }

    /**
     * Revoke user sessions
     */
    protected function revokeUserSessions(int $userId): void
    {
        DB::table('sessions')
            ->where('user_id', $userId)
            ->delete();

        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $userId)
            ->delete();

        Log::warning('User sessions revoked', ['user_id' => $userId]);
    }

    /**
     * Create security incident record
     */
    protected function createSecurityIncident(string $type, array $data): void
    {
        DB::table('security_incidents')->insert([
            'type' => $type,
            'severity' => $this->getSecuritySeverity($type),
            'data' => json_encode($data),
            'status' => 'open',
            'created_at' => now(),
        ]);
    }

    /**
     * Send to SIEM system
     */
    protected function sendToSiem(string $eventType, array $data): void
    {
        // Implementation would depend on SIEM system (Splunk, ELK, etc.)
        // This is a placeholder for SIEM integration
    }
}