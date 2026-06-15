<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotaRenderedUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $paymentId;
    public $quotaIds;
    public $rendered;
    public $companyDb;

    public function __construct($paymentId, $quotaIds, $rendered, $companyDb)
    {
        $this->paymentId = $paymentId;
        $this->quotaIds = $quotaIds;
        $this->rendered = $rendered;
        $this->companyDb = $companyDb;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('quota.' . $this->companyDb);
    }

    public function broadcastAs()
    {
        return 'QuotaRenderedUpdated';
    }

    public function broadcastWith()
    {
        return [
            'payment_id' => $this->paymentId,
            'quota_ids' => $this->quotaIds,
            'rendered' => $this->rendered,
        ];
    }
}
