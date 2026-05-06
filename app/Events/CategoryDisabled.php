<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CategoryDisabled implements ShouldBroadcast
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
        return 'CategoryDisabled';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->categoryId,
            'disabled_at' => now(),
        ];
    }
}