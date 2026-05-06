<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $operatorId;

    public function __construct($order, $operatorId)
    {
        $this->order = $order;
        $this->operatorId = $operatorId;
    }

    public function broadcastOn()
    {
        // Canal público siguiendo el patrón de RequestPosQROrder
        return new Channel('user.' . $this->operatorId);
    }

    public function broadcastAs()
    {
        return 'OrderPaid';
    }

    public function broadcastWith()
    {
        return [
            'order' => $this->order,
            'message' => 'Pago completado'
        ];
    }
}
