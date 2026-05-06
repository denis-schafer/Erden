<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function broadcastOn()
    {
        return new Channel('orders');
    }

    public function broadcastAs()
    {
        return 'OrderUpdated';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->order['id'] ?? null,
            'total' => $this->order['total'] ?? 0,
            'status_id' => $this->order['status_id'] ?? 1,
            'paid' => $this->order['paid'] ?? false,
            'updated_at' => $this->order['updated_at'] ?? now(),
        ];
    }
}