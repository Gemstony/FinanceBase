<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpiryAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $expiredBatches;
    protected $expiringSoonBatches;
    protected $subshop;

    /**
     * Create a new notification instance.
     */
    public function __construct($expiredBatches, $expiringSoonBatches, $subshop)
    {
        $this->expiredBatches = $expiredBatches;
        $this->expiringSoonBatches = $expiringSoonBatches;
        $this->subshop = $subshop;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = "Expiry Alert - {$this->subshop->name}";
        $expiredCount = $this->expiredBatches->count();
        $expiringCount = $this->expiringSoonBatches->count();

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name}!");

        if ($expiredCount > 0) {
            $mail->line("🚨 **{$expiredCount} batch(es) have EXPIRED** in {$this->subshop->name}")
                ->line('These items should be removed from inventory immediately:');
        }

        if ($expiringCount > 0) {
            $mail->line("⚠️ **{$expiringCount} batch(es) are expiring soon** (within 30 days)")
                ->line('Please plan to sell or use these items:');
        }

        $mail->action('View Inventory', url("/admin/inventory/items?subshop_id={$this->subshop->id}"))
            ->line('Regular monitoring helps maintain inventory quality.')
            ->salutation('Best regards, DukaBase System');

        return $mail;
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Inventory Expiry Alert',
            'message' => $this->getAlertMessage(),
            'subshop_id' => $this->subshop->id,
            'subshop_name' => $this->subshop->name,
            'expired_count' => $this->expiredBatches->count(),
            'expiring_count' => $this->expiringSoonBatches->count(),
            'type' => 'expiry_alert',
            'data' => [
                'expired_batches' => $this->expiredBatches->map(function($batch) {
                    return [
                        'id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'item_name' => $batch->item->name,
                        'quantity' => $batch->quantity,
                        'expire_date' => $batch->expire_date->format('Y-m-d'),
                        'days_expired' => $batch->expire_date->diffInDays(now())
                    ];
                }),
                'expiring_batches' => $this->expiringSoonBatches->map(function($batch) {
                    return [
                        'id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'item_name' => $batch->item->name,
                        'quantity' => $batch->quantity,
                        'expire_date' => $batch->expire_date->format('Y-m-d'),
                        'days_remaining' => now()->diffInDays($batch->expire_date)
                    ];
                })
            ]
        ];
    }

    /**
     * Get the alert message for the notification.
     */
    private function getAlertMessage()
    {
        $expiredCount = $this->expiredBatches->count();
        $expiringCount = $this->expiringSoonBatches->count();

        $message = "Inventory alert for {$this->subshop->name}: ";

        if ($expiredCount > 0 && $expiringCount > 0) {
            $message .= "{$expiredCount} expired, {$expiringCount} expiring soon.";
        } elseif ($expiredCount > 0) {
            $message .= "{$expiredCount} batch(es) have expired.";
        } elseif ($expiringCount > 0) {
            $message .= "{$expiringCount} batch(es) expiring within 30 days.";
        }

        return $message;
    }
}
