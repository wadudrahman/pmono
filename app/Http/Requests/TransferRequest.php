<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user account is active
        $user = auth()->user();
        return $user && ($user->is_active ?? true) && !$user->blocked_at && !$user->locked_at;
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Sanitize amount to prevent string manipulation
        if ($this->has('amount')) {
            $this->merge([
                'amount' => filter_var($this->amount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            ]);
        }

        // Sanitize description to prevent XSS
        if ($this->has('description')) {
            $this->merge([
                'description' => strip_tags(trim($this->description)),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = auth()->user();
        $maxTransferAmount = config('security.max_transfer_amount', 999999.99);
        $dailyLimit = config('security.daily_transfer_limit', 10000);

        return [
            'receiver_id' => [
                'required',
                'integer',
                'min:1',
                'exists:users,id',
                'different:' . $user->id,
                function ($attribute, $value, $fail) {
                    // Check if receiver account is active
                    $receiver = User::find($value);
                    if ($receiver && (!$receiver->is_active ?? false)) {
                        $fail('The recipient account is not active.');
                    }
                    // Check if receiver is not blocked
                    if ($receiver && $receiver->blocked_at !== null) {
                        $fail('The recipient account is blocked.');
                    }
                },
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:' . $maxTransferAmount,
                'regex:/^\d+(\.\d{1,2})?$/', // Only 2 decimal places
                function ($attribute, $value, $fail) use ($user, $dailyLimit) {
                    // Check sufficient balance including commission
                    $totalDeduction = Transaction::calculateTotalDeduction($value);
                    if ($user->balance < $totalDeduction) {
                        $fail(sprintf(
                            'Insufficient balance. Required: $%.2f (including commission), Available: $%.2f',
                            $totalDeduction,
                            $user->balance
                        ));
                    }

                    // Check daily transfer limit
                    $todayTotal = Transaction::where('sender_id', $user->id)
                        ->whereDate('created_at', today())
                        ->where('status', 'completed')
                        ->sum('amount');

                    if (($todayTotal + $value) > $dailyLimit) {
                        $fail(sprintf(
                            'Daily transfer limit exceeded. Limit: $%.2f, Already transferred: $%.2f',
                            $dailyLimit,
                            $todayTotal
                        ));
                    }
                },
            ],
            'description' => [
                'nullable',
                'string',
                'min:1',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-_.,:;!?()]+$/', // Only alphanumeric and basic punctuation
                function ($attribute, $value, $fail) {
                    // Check for potential SQL injection patterns
                    $suspiciousPatterns = [
                        '/(\bunion\b|\bselect\b|\binsert\b|\bupdate\b|\bdelete\b|\bdrop\b)/i',
                        '/<script[^>]*>.*?<\/script>/is',
                        '/javascript:/i',
                        '/on\w+\s*=/i', // Event handlers
                    ];

                    foreach ($suspiciousPatterns as $pattern) {
                        if (preg_match($pattern, $value)) {
                            $fail('Description contains invalid characters or patterns.');
                        }
                    }
                },
            ],
            // Add idempotency key for preventing duplicate transactions
            'idempotency_key' => [
                'sometimes',
                'string',
                'uuid',
                'unique:transactions,idempotency_key',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Additional security checks after basic validation
            if (!$validator->errors()->any()) {
                // Check for suspicious activity patterns
                $this->checkForSuspiciousActivity($validator);

                // Verify request signature if configured
                $this->verifyRequestSignature($validator);
            }
        });
    }

    /**
     * Check for suspicious transaction patterns
     */
    protected function checkForSuspiciousActivity(Validator $validator): void
    {
        $user = auth()->user();

        // Check for rapid-fire transactions (potential bot/attack)
        $recentTransactions = Transaction::where('sender_id', $user->id)
            ->where('created_at', '>', now()->subMinutes(1))
            ->count();

        if ($recentTransactions >= 3) {
            $validator->errors()->add('amount', 'Too many transactions in a short period. Please wait before trying again.');
        }

        // Check for unusual transaction pattern (same amount to same receiver)
        $duplicatePattern = Transaction::where('sender_id', $user->id)
            ->where('receiver_id', $this->receiver_id)
            ->where('amount', $this->amount)
            ->where('created_at', '>', now()->subMinutes(5))
            ->exists();

        if ($duplicatePattern) {
            $validator->errors()->add('amount', 'Duplicate transaction detected. Please verify your request.');
        }
    }

    /**
     * Verify request signature for API calls
     */
    protected function verifyRequestSignature(Validator $validator): void
    {
        if (!config('security.require_api_signature', false)) {
            return;
        }

        $signature = $this->header('X-API-Signature');
        if (!$signature) {
            $validator->errors()->add('signature', 'API signature is required.');
            return;
        }

        // Verify HMAC signature
        $payload = json_encode($this->only(['receiver_id', 'amount', 'description']));
        $expectedSignature = hash_hmac('sha256', $payload, $this->user()->api_secret ?? '');

        if (!hash_equals($expectedSignature, $signature)) {
            $validator->errors()->add('signature', 'Invalid API signature.');
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'receiver_id.required' => 'Please specify who you want to send money to.',
            'receiver_id.exists' => 'The recipient user does not exist.',
            'receiver_id.different' => 'You cannot send money to yourself.',
            'receiver_id.min' => 'Invalid recipient ID.',
            'amount.required' => 'Please specify the amount to transfer.',
            'amount.numeric' => 'Amount must be a valid number.',
            'amount.min' => 'Minimum transfer amount is $0.01.',
            'amount.max' => 'Maximum transfer amount exceeded.',
            'amount.regex' => 'Amount can only have up to 2 decimal places.',
            'description.max' => 'Description cannot exceed 255 characters.',
            'description.regex' => 'Description contains invalid characters.',
            'idempotency_key.uuid' => 'Invalid idempotency key format.',
            'idempotency_key.unique' => 'This transaction has already been processed.',
        ];
    }

    /**
     * Handle failed authorization.
     */
    protected function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'Your account is not authorized to perform transfers. Please verify your email or contact support.'
        );
    }
}
