<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestPosQROrder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $orderId;
    public $username;
    public $total;
    public $targetUserId;

    public function __construct($orderId, $username, $total, $targetUserId)
    {
        $this->orderId = $orderId;
        $this->username = $username;
        $this->total = $total;
        $this->targetUserId = $targetUserId;
    }

    public function broadcastOn()
    {
        return new Channel('user.' . $this->targetUserId);
    }
    
    public function broadcastAs()
    {
        return 'RequestPosQROrder';
    }
    
    public function broadcastWith()
    {
        return [
            'order_id' => $this->orderId,
            'username' => $this->username,
            'total' => $this->total,
            'target_user_id' => $this->targetUserId,
        ];
    }
    
    public function shouldBroadcast()
    {
        return true;
    }
}