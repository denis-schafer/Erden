<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotaConfigUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function broadcastOn()
    {
        return ['quota'];
    }

    public function broadcastAs()
    {
        return 'QuotaConfigUpdated';
    }

    public function broadcastWith()
    {
        $c = $this->config;
        return [
            'name' => is_object($c) ? $c->name : ($c['name'] ?? ''),
            'value' => is_object($c) ? $c->value : ($c['value'] ?? ''),
        ];
    }
}
