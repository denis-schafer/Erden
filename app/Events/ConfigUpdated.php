<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConfigUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function broadcastOn()
    {
        return ['configs'];
    }

    public function broadcastAs()
    {
        return 'ConfigUpdated';
    }

    public function broadcastWith()
    {
        return [
            'name' => $this->config['name'] ?? '',
            'value' => $this->config['value'] ?? '',
        ];
    }
}