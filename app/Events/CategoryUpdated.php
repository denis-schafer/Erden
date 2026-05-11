<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CategoryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $categoryId;

    public function __construct($categoryId)
    {
        $this->categoryId = $categoryId;
    }

    public function broadcastOn()
    {
        return new Channel('categories');
    }

    public function broadcastAs()
    {
        return 'CategoryUpdated';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->categoryId,
            'updated_at' => now(),
        ];
    }
}
