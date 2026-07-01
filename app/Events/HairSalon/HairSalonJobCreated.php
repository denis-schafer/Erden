<?php

namespace App\Events\HairSalon;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HairSalonJobCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $job;

    public function __construct($job)
    {
        $this->job = $job;
    }

    public function broadcastOn()
    {
        return ['hairsalon'];
    }

    public function broadcastAs()
    {
        return 'HairSalonJobCreated';
    }

    public function broadcastWith()
    {
        $job = $this->job;
        return [
            'id' => is_object($job) ? $job->id : ($job['id'] ?? null),
            'total' => is_object($job) ? $job->total : ($job['total'] ?? 0),
        ];
    }
}
