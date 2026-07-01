<?php

namespace App\Events\HairSalon;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HairSalonProductUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $product;
    public $action;

    public function __construct($product, string $action = 'updated')
    {
        $this->product = $product;
        $this->action = $action;
    }

    public function broadcastOn()
    {
        return ['hairsalon'];
    }

    public function broadcastAs()
    {
        return 'HairSalonProductUpdated';
    }

    public function broadcastWith()
    {
        $product = $this->product;
        return [
            'id' => is_object($product) ? $product->id : ($product['id'] ?? null),
            'action' => $this->action,
        ];
    }
}
