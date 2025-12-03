<?php

namespace App\Notifications;

use App\Models\Transfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransferReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(public Transfer $transfer) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $t = $this->transfer->loadMissing(['sourceSubshop','destinationSubshop']);
        return [
            'type' => 'transfer_received',
            'transfer_id' => $t->id,
            'status' => $t->status,
            'source' => $t->sourceSubshop->name ?? null,
            'destination' => $t->destinationSubshop->name ?? null,
            'message' => 'Transfer #'.$t->id.' '.$t->status.' at '.($t->destinationSubshop->name ?? '-')
        ];
    }
}
