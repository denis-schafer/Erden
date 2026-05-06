<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PosQRUpdated implements ShouldBroadcast
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