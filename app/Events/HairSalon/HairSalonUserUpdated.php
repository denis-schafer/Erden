<?php

namespace App\Events\HairSalon;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HairSalonUserUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $action;

    public function __construct($user, string $action = 'updated')
    {
        $this->user = $user;
        $this->action = $action;
    }

    public function broadcastOn()
    {
        return ['hairsalon'];
    }

    public function broadcastAs()
    {
        return 'HairSalonUserUpdated';
    }

    public function broadcastWith()
    {
        $u = $this->user;
        return [
            'id' => is_object($u) ? $u->id : ($u['id'] ?? null),
            'action' => $this->action,
        ];
    }
}
