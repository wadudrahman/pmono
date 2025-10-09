<?php

namespace App\Events;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transaction;
    public $senderNewBalance;
    public $receiverNewBalance;

    /**
     * Create a new event instance.
     */
    public function __construct(Transaction $transaction, $senderNewBalance, $receiverNewBalance)
    {
        $this->transaction = $transaction;
        $this->senderNewBalance = $senderNewBalance;
        $this->receiverNewBalance = $receiverNewBalance;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->transaction->sender_id),
            new PrivateChannel('user.' . $this->transaction->receiver_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'transaction' => [
                'id' => $this->transaction->id,
                'reference_number' => $this->transaction->reference_number,
                'amount' => $this->transaction->amount,
                'commission_fee' => $this->transaction->commission_fee,
                'total_deducted' => $this->transaction->total_deducted,
                'sender' => [
                    'id' => $this->transaction->sender->id,
                    'name' => $this->transaction->sender->name,
                    'email' => $this->transaction->sender->email,
                ],
                'receiver' => [
                    'id' => $this->transaction->receiver->id,
                    'name' => $this->transaction->receiver->name,
                    'email' => $this->transaction->receiver->email,
                ],
                'status' => $this->transaction->status,
                'description' => $this->transaction->description,
                'created_at' => $this->transaction->created_at,
                'processed_at' => $this->transaction->processed_at,
            ],
            'balances' => [
                'sender' => $this->senderNewBalance,
                'receiver' => $this->receiverNewBalance,
            ],
        ];
    }
}
