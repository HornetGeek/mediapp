<?php

namespace App\Listeners;

use App\Events\SendNotificationEvent;
use App\Models\Notification;
use App\Services\FirebaseNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendFcmNotificationListener
{
    protected $service;

    public function __construct(FirebaseNotificationService $service)
    {
        $this->service = $service;
    }

    /**
     * Handle the event.
     */
    public function handle(SendNotificationEvent $event): void
{
    \Log::info('SendFcmNotificationListener started', [
        'notifiable_id' => $event->notifiable->id ?? null,
        'has_token' => !empty($event->notifiable->fcm_token),
    ]);

    $event->notifiable->notifications()->create([
        'title' => $event->title,
        'body' => $event->body,
        'is_read' => false,
        'target_type' => $event->target_type
    ]);

    if ($event->notifiable->fcm_token) {
        $result = $this->service->sendNotification(
            $event->notifiable->fcm_token,
            $event->title,
            $event->body,
            $event->data
        );

        \Log::info('FCM send result', [
            'result' => $result
        ]);
    } else {
        \Log::warning('Doctor has no FCM token');
    }
}
}
