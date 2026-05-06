<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserSettingsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function broadcastOn()
    {
        return ['users'];
    }

    public function broadcastAs()
    {
        return 'UserSettingsUpdated';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->user['id'] ?? 0,
            'enable_print' => $this->user['enable_print'] ?? false,
            'printer_ip' => $this->user['printer_ip'] ?? '',
            'printer_port' => $this->user['printer_port'] ?? 9100,
            'printer_type' => $this->user['printer_type'] ?? 'raw',
            'printer_width' => $this->user['printer_width'] ?? '80mm',
            'mercadopago_qr_enabled' => $this->user['mercadopago_qr_enabled'] ?? false,
        ];
    }
}