<?php

namespace App\Events\HairSalon;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HairSalonAppointmentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointment;
    public $action;

    public function __construct($appointment, string $action = 'updated')
    {
        $this->appointment = $appointment;
        $this->action = $action;
    }

    public function broadcastOn()
    {
        return ['hairsalon'];
    }

    public function broadcastAs()
    {
        return 'HairSalonAppointmentUpdated';
    }

    public function broadcastWith()
    {
        $appt = $this->appointment;
        return [
            'id' => is_object($appt) ? $appt->id : ($appt['id'] ?? null),
            'action' => $this->action,
        ];
    }
}
