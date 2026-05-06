<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MpCodeReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $code;
    public $companyId;

    public function __construct($code, $companyId)
    {
        $this->code = $code;
        $this->companyId = $companyId;
    }

    public function broadcastOn()
    {
        return new Channel('users');
    }

    public function broadcastAs()
    {
        return 'MpCodeReceived';
    }

    public function broadcastWith()
    {
        return [
            'code' => $this->code,
            'company_id' => $this->companyId,
        ];
    }
}