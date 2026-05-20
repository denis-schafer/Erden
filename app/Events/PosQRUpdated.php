<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class PosQRUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $targetUserId;
    public $orderId;
    public $username;
    public $total;

    public function __construct($targetUserId, $orderId, $username, $total)
    {
        $this->targetUserId = $targetUserId;
        $this->orderId = $orderId;
        $this->username = $username;
        $this->total = $total;
    }

    public function broadcastOn()
    {
        return new Channel('users');
    }

    public function broadcastAs()
    {
        return 'PosQRUpdated';
    }

    public function broadcastWith()
    {
        return [
            'target_user_id' => $this->targetUserId,
            'order_id' => $this->orderId,
            'username' => $this->username,
            'total' => $this->total,
        ];
    }
}