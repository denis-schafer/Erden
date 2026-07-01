<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModulesReordered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $orders;

    public function __construct($userId, $orders)
    {
        $this->userId = $userId;
        $this->orders = $orders;
    }

    public function broadcastOn()
    {
        return new Channel('users');
    }

    public function broadcastAs()
    {
        return 'ModulesReordered';
    }

    public function broadcastWith()
    {
        return [
            'userId' => $this->userId,
            'orders' => $this->orders,
        ];
    }
}
