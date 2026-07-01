<?php

namespace App\Events\HairSalon;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HairSalonClientUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $client;
    public $action;

    public function __construct($client, string $action = 'updated')
    {
        $this->client = $client;
        $this->action = $action;
    }

    public function broadcastOn()
    {
        return ['hairsalon'];
    }

    public function broadcastAs()
    {
        return 'HairSalonClientUpdated';
    }

    public function broadcastWith()
    {
        $client = $this->client;
        return [
            'id' => is_object($client) ? $client->id : ($client['id'] ?? null),
            'action' => $this->action,
        ];
    }
}
