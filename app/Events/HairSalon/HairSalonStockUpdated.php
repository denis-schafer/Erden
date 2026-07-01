<?php

namespace App\Events\HairSalon;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HairSalonStockUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $movement;

    public function __construct($movement)
    {
        $this->movement = $movement;
    }

    public function broadcastOn()
    {
        return ['hairsalon'];
    }

    public function broadcastAs()
    {
        return 'HairSalonStockUpdated';
    }

    public function broadcastWith()
    {
        $m = $this->movement;
        return [
            'product_id' => is_object($m) ? $m->product_id : ($m['product_id'] ?? null),
            'type' => is_object($m) ? $m->type : ($m['type'] ?? null),
        ];
    }
}
