<?php

namespace App\Events\HairSalon;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HairSalonServiceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $service;
    public $action;

    public function __construct($service, string $action = 'updated')
    {
        $this->service = $service;
        $this->action = $action;
    }

    public function broadcastOn()
    {
        return ['hairsalon'];
    }

    public function broadcastAs()
    {
        return 'HairSalonServiceUpdated';
    }

    public function broadcastWith()
    {
        $svc = $this->service;
        return [
            'id' => is_object($svc) ? $svc->id : ($svc['id'] ?? null),
            'action' => $this->action,
        ];
    }
}
