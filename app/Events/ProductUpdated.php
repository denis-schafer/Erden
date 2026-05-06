<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $product;

    public function __construct($product)
    {
        $this->product = $product;
    }

    public function broadcastOn()
    {
        return new Channel('products');
    }

    public function broadcastAs()
    {
        return 'ProductUpdated';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->product['id'] ?? null,
            'name' => $this->product['name'] ?? '',
            'enable' => $this->product['enable'] ?? true,
            'stock' => $this->product['stock'] ?? null,
            'updated_at' => $this->product['updated_at'] ?? now(),
        ];
    }
}