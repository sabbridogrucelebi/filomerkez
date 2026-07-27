<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserReceivedMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $conversationId;

    public function __construct(int $userId, int $conversationId)
    {
        $this->userId = $userId;
        $this->conversationId = $conversationId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('App.Models.User.' . $this->userId);
    }

    public function broadcastAs()
    {
        return 'message.received';
    }

    public function broadcastWith()
    {
        return [
            'conversation_id' => $this->conversationId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
