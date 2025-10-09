<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Validator;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    /**
     * The maximum login attempts allowed
     */
    protected const MAX_ATTEMPTS = 5;

    /**
     * The lockout time in minutes
     */
    protected const LOCKOUT_TIME = 15;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if IP is not blacklisted
        $blacklistedIps = config('security.blacklisted_ips', []);
        if (in_array($this->ip(), $blacklistedIps)) {
            return false;
        }

        // Check rate limiting for this IP
        $key = $this->throttleKey();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return false;
        }

        return true;
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Normalize email to lowercase and trim
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }

        // Trim password (but don't modify content)
        if ($this->has('password')) {
            $this->merge([
                'password' => trim($this->password),
            ]);
        }

        // Generate request fingerprint for tracking
        $this->merge([
            'fingerprint' => $this->generateFingerprint(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Check for suspicious email patterns
                    $suspiciousPatterns = [
                        '/[<>\'\"\\\\]/', // Special characters that shouldn't be in emails
                        '/\s/', // Whitespace
                    ];

                    foreach ($suspiciousPatterns as $pattern) {
                        if (preg_match($pattern, $value)) {
                            $fail('Invalid email format detected.');
                        }
                    }

                    // Check if email domain is not in blocklist
                    $domain = substr(strrchr($value, "@"), 1);
                    $blockedDomains = config('security.blocked_email_domains', []);
                    if (in_array($domain, $blockedDomains)) {
                        $fail('Email domain is not allowed.');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:128', // Prevent DoS with extremely long passwords
                function ($attribute, $value, $fail) {
                    // Check for null bytes or other dangerous characters
                    if (strpos($value, "\0") !== false) {
                        $fail('Password contains invalid characters.');
                    }
                },
            ],
            'remember' => [
                'sometimes',
                'boolean',
            ],
            'device_name' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-_.]+$/', // Only safe characters
            ],
            'captcha' => [
                // Required after 3 failed attempts
                $this->requiresCaptcha() ? 'required' : 'sometimes',
                'string',
                function ($attribute, $value, $fail) {
                    if ($this->requiresCaptcha() && !$this->validateCaptcha($value)) {
                        $fail('Invalid CAPTCHA verification.');
                    }
                },
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$validator->errors()->any()) {
                // Increment rate limiter
                RateLimiter::hit($this->throttleKey(), self::LOCKOUT_TIME * 60);

                // Log login attempt for audit
                $this->logLoginAttempt();

                // Check for credential stuffing patterns
                $this->checkCredentialStuffing($validator);
            }
        });
    }

    /**
     * Check if CAPTCHA is required
     */
    protected function requiresCaptcha(): bool
    {
        $attempts = RateLimiter::attempts($this->throttleKey());
        return $attempts >= 3;
    }

    /**
     * Validate CAPTCHA response
     */
    protected function validateCaptcha(string $response): bool
    {
        // Implement actual CAPTCHA validation (e.g., Google reCAPTCHA)
        // This is a placeholder
        if (!config('security.captcha_enabled', false)) {
            return true;
        }

        // Verify with CAPTCHA service
        $secret = config('services.recaptcha.secret');
        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verifyUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $secret,
            'response' => $response,
            'remoteip' => $this->ip(),
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $result = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($result, true);
        return $responseData['success'] ?? false;
    }

    /**
     * Check for credential stuffing attack patterns
     */
    protected function checkCredentialStuffing(Validator $validator): void
    {
        // Check if same email tried with different passwords recently
        $recentKey = 'login_email:' . md5($this->email);
        $recentAttempts = cache()->get($recentKey, []);

        $passwordHash = hash('sha256', $this->password);
        if (!in_array($passwordHash, $recentAttempts)) {
            $recentAttempts[] = $passwordHash;
            cache()->put($recentKey, $recentAttempts, now()->addMinutes(5));
        }

        // If more than 3 different passwords tried in 5 minutes, likely credential stuffing
        if (count($recentAttempts) > 3) {
            $validator->errors()->add('email', 'Suspicious activity detected. Please try again later.');
        }

        // Check for rapid attempts from same IP with different emails
        $ipKey = 'login_ip:' . md5($this->ip());
        $ipEmails = cache()->get($ipKey, []);

        if (!in_array($this->email, $ipEmails)) {
            $ipEmails[] = $this->email;
            cache()->put($ipKey, $ipEmails, now()->addMinutes(5));
        }

        // If same IP trying multiple emails, potential attack
        if (count($ipEmails) > 5) {
            $validator->errors()->add('email', 'Too many login attempts from your IP. Please try again later.');
        }
    }

    /**
     * Log login attempt for security audit
     */
    protected function logLoginAttempt(): void
    {
        \Log::channel('security')->info('Login attempt', [
            'email' => $this->email,
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'fingerprint' => $this->fingerprint ?? null,
            'timestamp' => now()->toIso8601String(),
            'requires_captcha' => $this->requiresCaptcha(),
        ]);
    }

    /**
     * Generate request fingerprint
     */
    protected function generateFingerprint(): string
    {
        return hash('sha256', implode('|', [
            $this->ip(),
            $this->userAgent(),
            $this->header('Accept-Language'),
            $this->header('Accept-Encoding'),
        ]));
    }

    /**
     * Get the rate limiting throttle key
     */
    protected function throttleKey(): string
    {
        return Str::lower($this->email ?? '') . '|' . $this->ip();
    }

    /**
     * Get custom messages for validator errors
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email address is too long.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.max' => 'Password is too long.',
            'captcha.required' => 'Please complete the CAPTCHA verification.',
            'device_name.regex' => 'Device name contains invalid characters.',
        ];
    }

    /**
     * Handle failed authorization
     */
    protected function failedAuthorization()
    {
        $remainingAttempts = self::MAX_ATTEMPTS - RateLimiter::attempts($this->throttleKey());

        if ($remainingAttempts <= 0) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                sprintf('Too many login attempts. Please try again in %d minutes.', self::LOCKOUT_TIME)
            );
        }

        throw new \Illuminate\Auth\Access\AuthorizationException(
            'Access denied. Your IP may be blocked or you have exceeded the rate limit.'
        );
    }
}
