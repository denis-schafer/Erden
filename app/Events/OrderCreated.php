<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcast
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
        return 'OrderCreated';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->order['id'] ?? null,
            'total' => $this->order['total'] ?? 0,
            'operator_id' => $this->order['operator_id'] ?? null,
            'detail' => $this->order['detail'] ?? null,
            'paid' => $this->order['paid'] ?? false,
            'status_id' => $this->order['status_id'] ?? 1,
            'created_at' => $this->order['created_at'] ?? now(),
        ];
    }
}