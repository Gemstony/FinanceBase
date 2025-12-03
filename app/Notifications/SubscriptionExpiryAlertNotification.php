<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiryAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $expiringSubscriptions;
    protected $shop;

    /**
     * Create a new notification instance.
     */
    public function __construct($expiringSubscriptions, $shop)
    {
        $this->expiringSubscriptions = $expiringSubscriptions;
        $this->shop = $shop;
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
        $subject = "Subscription Expiry Alert - {$this->shop->name}";
        $expiringCount = $this->expiringSubscriptions->count();

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name}!")
            ->line("⚠️ **{$expiringCount} subscription(s) are expiring soon** for {$this->shop->name}")
            ->line('Your plan subscription(s) will expire within the next 10 days. Please renew to avoid service interruption:');

        foreach ($this->expiringSubscriptions as $subscription) {
            $daysRemaining = now()->diffInDays($subscription->end_date, false);
            $mail->line("• **{$subscription->plan->name}** - Expires in {$daysRemaining} days ({$subscription->end_date->format('M j, Y')})");
        }

        $mail->action('Manage Subscription', url("/shops/{$this->shop->id}/configure#plan-management"))
            ->line('Regular subscription management helps ensure uninterrupted service.')
            ->salutation('Best regards, DukaBase System');

        return $mail;
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Subscription Expiry Alert',
            'message' => $this->getAlertMessage(),
            'shop_id' => $this->shop->id,
            'shop_name' => $this->shop->name,
            'expiring_count' => $this->expiringSubscriptions->count(),
            'type' => 'subscription_expiry_alert',
            'data' => [
                'expiring_subscriptions' => $this->expiringSubscriptions->map(function($subscription) {
                    return [
                        'id' => $subscription->id,
                        'plan_name' => $subscription->plan->name,
                        'plan_price' => $subscription->plan->price,
                        'plan_currency' => $subscription->plan->currency,
                        'billing_cycle' => $subscription->plan->billing_cycle,
                        'end_date' => $subscription->end_date->format('Y-m-d'),
                        'days_remaining' => now()->diffInDays($subscription->end_date, false)
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
        $expiringCount = $this->expiringSubscriptions->count();

        $message = "Subscription alert for {$this->shop->name}: ";

        if ($expiringCount === 1) {
            $subscription = $this->expiringSubscriptions->first();
            $daysRemaining = now()->diffInDays($subscription->end_date, false);
            $message .= "{$subscription->plan->name} expires in {$daysRemaining} days.";
        } else {
            $message .= "{$expiringCount} subscription(s) expiring within 10 days.";
        }

        return $message;
    }
}
