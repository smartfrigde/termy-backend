<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Broadcasting\PrivateChannel;

class SyncNots implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private $userId;
    private $message;

    public function __construct($userId, $message)
    {
        $this->userId = $userId;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('sync.user.{$this->userId}');
    }

    public function broadcastAs()
    {
        return 'sync.nots';
    }

    public function broadcastWith()
    {
        Log::info('📤 broadcastWith SyncNots', [
            'user_id' => $this->userId,
            'message' => $this->message,
        ]);

        return [
            'user_id' => $this->userId,
            'message' => $this->message,
        ];
    }
}
