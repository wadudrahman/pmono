<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if registrations are enabled
        if (!config('security.registrations_enabled', true)) {
            return false;
        }

        // Check IP-based rate limiting for registration
        $registrationKey = 'register_ip:' . $this->ip();
        $recentRegistrations = cache()->get($registrationKey, 0);

        // Max 3 registrations per IP per day
        if ($recentRegistrations >= 3) {
            return false;
        }

        return true;
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Sanitize and normalize inputs
        $this->merge([
            'name' => trim(strip_tags($this->name ?? '')),
            'email' => strtolower(trim($this->email ?? '')),
            'phone' => preg_replace('/[^0-9+]/', '', $this->phone ?? ''),
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
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z\s\-\'\.]+$/', // Only letters, spaces, hyphens, apostrophes, dots
                function ($attribute, $value, $fail) {
                    // Check for suspicious patterns in name
                    $suspiciousPatterns = [
                        '/\d{4,}/', // 4 or more consecutive digits
                        '/<script/i',
                        '/javascript:/i',
                        '/on\w+\s*=/i',
                    ];

                    foreach ($suspiciousPatterns as $pattern) {
                        if (preg_match($pattern, $value)) {
                            $fail('Name contains invalid characters or patterns.');
                        }
                    }

                    // Check for SQL keywords
                    $sqlKeywords = ['select', 'insert', 'update', 'delete', 'drop', 'union', 'exec', 'script'];
                    $lowerValue = strtolower($value);
                    foreach ($sqlKeywords as $keyword) {
                        if (strpos($lowerValue, $keyword) !== false) {
                            $fail('Name contains invalid keywords.');
                        }
                    }
                },
            ],
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                'unique:users,email',
                function ($attribute, $value, $fail) {
                    // Check for disposable email addresses
                    $domain = substr(strrchr($value, "@"), 1);
                    $disposableDomains = config('security.disposable_email_domains', [
                        'tempmail.com', 'throwaway.email', 'guerrillamail.com',
                        'mailinator.com', '10minutemail.com', 'temp-mail.org'
                    ]);

                    if (in_array($domain, $disposableDomains)) {
                        $fail('Disposable email addresses are not allowed.');
                    }

                    // Check for suspicious email patterns
                    if (preg_match('/[<>\'\"\\\\]/', $value)) {
                        $fail('Email contains invalid characters.');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(3), // Check against known data breaches
                'max:128',
                function ($attribute, $value, $fail) {
                    // Check for common weak patterns
                    $weakPatterns = [
                        '/^(.)\1+$/', // All same character
                        '/^(012|123|234|345|456|567|678|789|890)+$/', // Sequential numbers
                        '/^(abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)+$/i', // Sequential letters
                    ];

                    foreach ($weakPatterns as $pattern) {
                        if (preg_match($pattern, $value)) {
                            $fail('Password is too weak. Please choose a stronger password.');
                        }
                    }

                    // Check password doesn't contain username or email
                    if ($this->name && stripos($value, $this->name) !== false) {
                        $fail('Password should not contain your name.');
                    }

                    if ($this->email) {
                        $emailPart = strstr($this->email, '@', true);
                        if (stripos($value, $emailPart) !== false) {
                            $fail('Password should not contain parts of your email.');
                        }
                    }

                    // Check for null bytes
                    if (strpos($value, "\0") !== false) {
                        $fail('Password contains invalid characters.');
                    }
                },
            ],
            'phone' => [
                'sometimes',
                'string',
                'regex:/^\+?[1-9]\d{1,14}$/', // E.164 format
                'unique:users,phone',
            ],
            'terms_accepted' => [
                'required',
                'accepted',
            ],
            'captcha' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!$this->validateCaptcha($value)) {
                        $fail('Invalid CAPTCHA verification.');
                    }
                },
            ],
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$validator->errors()->any()) {
                // Check for honeypot field (anti-bot)
                if ($this->filled('website') || $this->filled('url') || $this->filled('company')) {
                    $validator->errors()->add('email', 'Suspicious activity detected.');
                }

                // Check registration velocity
                $this->checkRegistrationVelocity($validator);

                // Log registration attempt
                $this->logRegistrationAttempt();
            }
        });
    }

    /**
     * Check registration velocity for fraud detection
     */
    protected function checkRegistrationVelocity(Validator $validator): void
    {
        // Check if same IP registered recently
        $ipKey = 'register_ip:' . $this->ip();
        $lastRegistration = cache()->get($ipKey . '_time');

        if ($lastRegistration && now()->diffInMinutes($lastRegistration) < 5) {
            $validator->errors()->add('email', 'Please wait before registering another account.');
        }

        // Check email domain velocity
        $domain = substr(strrchr($this->email, "@"), 1);
        $domainKey = 'register_domain:' . $domain;
        $domainCount = cache()->get($domainKey, 0);

        if ($domainCount >= 10) {
            $validator->errors()->add('email', 'Too many registrations from this email domain.');
        }

        // Check name similarity (potential fake accounts)
        $nameHash = soundex($this->name);
        $nameKey = 'register_name:' . $nameHash;
        $similarNames = cache()->get($nameKey, 0);

        if ($similarNames >= 3) {
            $validator->errors()->add('name', 'Similar name already registered recently.');
        }
    }

    /**
     * Validate CAPTCHA response
     */
    protected function validateCaptcha(string $response): bool
    {
        if (!config('security.captcha_enabled', true)) {
            return true;
        }

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

        // For reCAPTCHA v3, also check the score
        if (isset($responseData['score']) && $responseData['score'] < 0.5) {
            return false;
        }

        return $responseData['success'] ?? false;
    }

    /**
     * Log registration attempt
     */
    protected function logRegistrationAttempt(): void
    {
        \Log::channel('security')->info('Registration attempt', [
            'email' => $this->email,
            'name' => $this->name,
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // Update rate limiting counters
        $ipKey = 'register_ip:' . $this->ip();
        cache()->increment($ipKey, 1);
        cache()->put($ipKey . '_time', now(), now()->addDay());

        $domain = substr(strrchr($this->email, "@"), 1);
        $domainKey = 'register_domain:' . $domain;
        cache()->increment($domainKey, 1, now()->addHours(24));

        $nameHash = soundex($this->name);
        $nameKey = 'register_name:' . $nameHash;
        cache()->increment($nameKey, 1, now()->addHours(24));
    }

    /**
     * Get custom messages for validator errors
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please provide your full name.',
            'name.regex' => 'Name can only contain letters, spaces, hyphens, and apostrophes.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.mixed' => 'Password must contain both uppercase and lowercase letters.',
            'password.numbers' => 'Password must contain at least one number.',
            'password.symbols' => 'Password must contain at least one special character.',
            'password.uncompromised' => 'This password has been compromised in a data breach. Please choose a different password.',
            'phone.regex' => 'Please provide a valid phone number.',
            'phone.unique' => 'This phone number is already registered.',
            'terms_accepted.accepted' => 'You must accept the terms and conditions.',
            'captcha.required' => 'Please complete the CAPTCHA verification.',
        ];
    }

    /**
     * Handle failed authorization
     */
    protected function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'Registration is currently disabled or you have exceeded the registration limit. Please try again later.'
        );
    }
}