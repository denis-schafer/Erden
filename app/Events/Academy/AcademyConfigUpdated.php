<?php

namespace App\Events\Academy;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AcademyConfigUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function broadcastOn(): array
    {
        return [new Channel('configs')];
    }

    public function broadcastAs(): string
    {
        return 'ConfigUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'name' => is_array($this->config) ? $this->config['name'] : $this->config->name,
            'value' => is_array($this->config) ? $this->config['value'] : $this->config->value,
        ];
    }
}
