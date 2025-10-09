<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'amount',
        'commission_fee',
        'total_deducted',
        'type',
        'status',
        'reference_number',
        'description',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_fee' => 'decimal:2',
        'total_deducted' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    const COMMISSION_RATE = 0.015;

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public static function generateReferenceNumber(): string
    {
        do {
            $reference = 'TXN' . date('Ymd') . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (self::where('reference_number', $reference)->exists());

        return $reference;
    }

    public static function calculateCommission($amount): float
    {
        return round($amount * self::COMMISSION_RATE, 2);
    }

    public static function calculateTotalDeduction($amount): float
    {
        return $amount + self::calculateCommission($amount);
    }
}
