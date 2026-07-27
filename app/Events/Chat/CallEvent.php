<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $conversationId;
    public $callerId;
    public $callerName;
    public $type; // 'video' or 'voice'
    public $action; // 'start', 'accept', 'reject', 'end'
    public $roomId;

    public function __construct($userId, $conversationId, $callerId, $callerName, $type, $action, $roomId)
    {
        $this->userId = $userId;
        $this->conversationId = $conversationId;
        $this->callerId = $callerId;
        $this->callerName = $callerName;
        $this->type = $type;
        $this->action = $action;
        $this->roomId = $roomId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('App.Models.User.' . $this->userId);
    }

    public function broadcastAs()
    {
        return 'call.event';
    }

    public function broadcastWith()
    {
        return [
            'conversation_id' => $this->conversationId,
            'caller_id' => $this->callerId,
            'caller_name' => $this->callerName,
            'type' => $this->type,
            'action' => $this->action,
            'room_id' => $this->roomId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
