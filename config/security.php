<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all security-related configuration for the application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Authentication & Authorization
    |--------------------------------------------------------------------------
    */
    'auth' => [
        // Password requirements
        'password_min_length' => env('PASSWORD_MIN_LENGTH', 8),
        'password_require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'password_require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'password_require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
        'password_require_symbols' => env('PASSWORD_REQUIRE_SYMBOLS', true),
        'password_history_count' => env('PASSWORD_HISTORY_COUNT', 5), // Prevent reusing last N passwords

        // Account lockout
        'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),
        'lockout_duration' => env('LOCKOUT_DURATION', 15), // Minutes
        'account_lockout_threshold' => env('ACCOUNT_LOCKOUT_THRESHOLD', 10), // Permanent lock after N failed attempts

        // Session security
        'session_lifetime' => env('SESSION_LIFETIME', 120), // Minutes
        'session_timeout_on_inactivity' => env('SESSION_TIMEOUT_INACTIVE', 30), // Minutes
        'concurrent_sessions' => env('ALLOW_CONCURRENT_SESSIONS', false),
        'session_fingerprinting' => env('SESSION_FINGERPRINTING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        // API rate limits (requests per minute)
        'api_rate_limit' => env('API_RATE_LIMIT', 60),
        'api_rate_limit_authenticated' => env('API_RATE_LIMIT_AUTH', 100),
        'api_rate_limit_premium' => env('API_RATE_LIMIT_PREMIUM', 1000),

        // Specific endpoint limits
        'login_rate_limit' => env('LOGIN_RATE_LIMIT', 5),
        'register_rate_limit' => env('REGISTER_RATE_LIMIT', 3),
        'password_reset_rate_limit' => env('PASSWORD_RESET_RATE_LIMIT', 3),
        'transaction_rate_limit' => env('TRANSACTION_RATE_LIMIT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Security
    |--------------------------------------------------------------------------
    */
    'transactions' => [
        // Transaction limits
        'max_transfer_amount' => env('MAX_TRANSFER_AMOUNT', 999999.99),
        'min_transfer_amount' => env('MIN_TRANSFER_AMOUNT', 0.01),
        'daily_transfer_limit' => env('DAILY_TRANSFER_LIMIT', 10000),
        'monthly_transfer_limit' => env('MONTHLY_TRANSFER_LIMIT', 100000),

        // Transaction velocity checks
        'max_transactions_per_minute' => env('MAX_TRANSACTIONS_PER_MINUTE', 3),
        'max_transactions_per_hour' => env('MAX_TRANSACTIONS_PER_HOUR', 30),
        'max_transactions_per_day' => env('MAX_TRANSACTIONS_PER_DAY', 100),

        // Fraud detection
        'suspicious_amount_threshold' => env('SUSPICIOUS_AMOUNT_THRESHOLD', 5000),
        'large_transaction_threshold' => env('LARGE_TRANSACTION_THRESHOLD', 1000),
        'require_additional_verification' => env('REQUIRE_ADDITIONAL_VERIFICATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Security
    |--------------------------------------------------------------------------
    */
    'ip_security' => [
        // IP whitelist (always allowed)
        'whitelisted_ips' => env('WHITELISTED_IPS') ? explode(',', env('WHITELISTED_IPS')) : [],

        // IP blacklist (always blocked)
        'blacklisted_ips' => env('BLACKLISTED_IPS') ? explode(',', env('BLACKLISTED_IPS')) : [],

        // Geo-blocking
        'geo_blocking_enabled' => env('GEO_BLOCKING_ENABLED', false),
        'blocked_countries' => env('BLOCKED_COUNTRIES') ? explode(',', env('BLOCKED_COUNTRIES')) : [],
        'allowed_countries' => env('ALLOWED_COUNTRIES') ? explode(',', env('ALLOWED_COUNTRIES')) : [],
    ],

    /*
    |--------------------------------------------------------------------------
    | CAPTCHA Settings
    |--------------------------------------------------------------------------
    */
    'captcha' => [
        'enabled' => env('CAPTCHA_ENABLED', true),
        'provider' => env('CAPTCHA_PROVIDER', 'recaptcha'), // recaptcha, hcaptcha, cloudflare
        'threshold_score' => env('CAPTCHA_THRESHOLD_SCORE', 0.5),
        'required_after_failed_attempts' => env('CAPTCHA_AFTER_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security
    |--------------------------------------------------------------------------
    */
    'content_security' => [
        // CSP settings
        'csp_enabled' => env('CSP_ENABLED', true),
        'csp_report_only' => env('CSP_REPORT_ONLY', false),
        'csp_report_uri' => env('CSP_REPORT_URI', '/api/security/csp-report'),

        // XSS protection
        'xss_protection' => env('XSS_PROTECTION', true),
        'auto_escape_output' => env('AUTO_ESCAPE_OUTPUT', true),

        // File upload security
        'max_upload_size' => env('MAX_UPLOAD_SIZE', 10485760), // 10MB in bytes
        'allowed_file_extensions' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
        'scan_uploads_for_malware' => env('SCAN_UPLOADS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Security
    |--------------------------------------------------------------------------
    */
    'api' => [
        // API authentication
        'require_api_key' => env('REQUIRE_API_KEY', true),
        'api_key_rotation_days' => env('API_KEY_ROTATION_DAYS', 90),
        'require_api_signature' => env('REQUIRE_API_SIGNATURE', false),

        // API versioning
        'current_version' => env('API_VERSION', 'v1'),
        'supported_versions' => ['v1'],
        'deprecation_notice_days' => env('API_DEPRECATION_NOTICE_DAYS', 30),

        // Request validation
        'max_request_size' => env('MAX_REQUEST_SIZE', 2097152), // 2MB
        'require_user_agent' => env('REQUIRE_USER_AGENT', true),
        'block_suspicious_user_agents' => env('BLOCK_SUSPICIOUS_UA', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Security
    |--------------------------------------------------------------------------
    */
    'email' => [
        // Disposable email blocking
        'block_disposable_emails' => env('BLOCK_DISPOSABLE_EMAILS', true),
        'disposable_email_domains' => [
            'tempmail.com', 'throwaway.email', 'guerrillamail.com',
            'mailinator.com', '10minutemail.com', 'temp-mail.org',
            'yopmail.com', 'trashmail.com', 'getairmail.com',
            'dispostable.com', 'mailnesia.com', 'tempr.email',
        ],

        // Blocked email domains
        'blocked_email_domains' => env('BLOCKED_EMAIL_DOMAINS') ? explode(',', env('BLOCKED_EMAIL_DOMAINS')) : [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit & Logging
    |--------------------------------------------------------------------------
    */
    'audit' => [
        // Audit logging
        'enable_audit_logging' => env('ENABLE_AUDIT_LOGGING', true),
        'log_all_requests' => env('LOG_ALL_REQUESTS', false),
        'log_sensitive_operations' => env('LOG_SENSITIVE_OPERATIONS', true),
        'audit_log_retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 90),

        // Security event logging
        'log_failed_logins' => env('LOG_FAILED_LOGINS', true),
        'log_suspicious_activity' => env('LOG_SUSPICIOUS_ACTIVITY', true),
        'log_security_events' => env('LOG_SECURITY_EVENTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Monitoring & Alerts
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        // Alert settings
        'alert_on_suspicious_activity' => env('ALERT_SUSPICIOUS_ACTIVITY', true),
        'alert_on_multiple_failed_logins' => env('ALERT_FAILED_LOGINS', true),
        'alert_failed_login_threshold' => env('ALERT_FAILED_LOGIN_THRESHOLD', 10),
        'alert_webhook_url' => env('SECURITY_ALERT_WEBHOOK'),
        'alert_email_addresses' => env('SECURITY_ALERT_EMAILS') ? explode(',', env('SECURITY_ALERT_EMAILS')) : [],

        // SIEM integration
        'siem_enabled' => env('SIEM_ENABLED', false),
        'siem_endpoint' => env('SIEM_ENDPOINT'),
        'siem_api_key' => env('SIEM_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption & Hashing
    |--------------------------------------------------------------------------
    */
    'encryption' => [
        // Data encryption
        'encrypt_sensitive_data' => env('ENCRYPT_SENSITIVE_DATA', true),
        'encryption_algorithm' => env('ENCRYPTION_ALGORITHM', 'AES-256-CBC'),

        // Hashing
        'bcrypt_rounds' => env('BCRYPT_ROUNDS', 12),
        'argon_memory' => env('ARGON_MEMORY', 1024),
        'argon_threads' => env('ARGON_THREADS', 2),
        'argon_time' => env('ARGON_TIME', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Settings
    |--------------------------------------------------------------------------
    */
    'cors' => [
        'enabled' => env('CORS_ENABLED', true),
        'allowed_origins' => env('CORS_ALLOWED_ORIGINS') ? explode(',', env('CORS_ALLOWED_ORIGINS')) : ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-API-Key', 'X-API-Signature'],
        'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-API-Version'],
        'max_age' => env('CORS_MAX_AGE', 86400),
        'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration Settings
    |--------------------------------------------------------------------------
    */
    'registration' => [
        'enabled' => env('REGISTRATIONS_ENABLED', true),
        'require_email_verification' => env('REQUIRE_EMAIL_VERIFICATION', true),
        'email_verification_timeout' => env('EMAIL_VERIFICATION_TIMEOUT', 60), // Minutes
        'max_registrations_per_ip_per_day' => env('MAX_REGISTRATIONS_PER_IP', 3),
        'require_invitation_code' => env('REQUIRE_INVITATION_CODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deprecated Endpoints
    |--------------------------------------------------------------------------
    */
    'deprecated_endpoints' => [
        // 'api/old/endpoint' => [
        //     'replacement' => 'api/v1/new/endpoint',
        //     'removal_date' => '2024-12-31',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Response Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'remove_x_powered_by' => env('REMOVE_X_POWERED_BY', true),
        'x_frame_options' => env('X_FRAME_OPTIONS', 'DENY'),
        'x_content_type_options' => env('X_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'x_xss_protection' => env('X_XSS_PROTECTION', '1; mode=block'),
        'referrer_policy' => env('REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('PERMISSIONS_POLICY', 'geolocation=(), microphone=(), camera=()'),
        'strict_transport_security' => env('STRICT_TRANSPORT_SECURITY', 'max-age=31536000; includeSubDomains'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode
    |--------------------------------------------------------------------------
    */
    'maintenance' => [
        'enabled' => env('MAINTENANCE_MODE', false),
        'message' => env('MAINTENANCE_MESSAGE', 'The application is currently under maintenance.'),
        'allowed_ips' => env('MAINTENANCE_ALLOWED_IPS') ? explode(',', env('MAINTENANCE_ALLOWED_IPS')) : [],
        'secret_token' => env('MAINTENANCE_SECRET'),
    ],
];