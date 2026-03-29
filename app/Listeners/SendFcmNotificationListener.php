<?php

namespace App\Listeners;

use App\Events\SendNotificationEvent;
use App\Models\Notification;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Carbon;

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
        $dedupeKey = $this->resolveDedupeKey($event);
        $windowMinutes = (int) ($event->dedupe_window_minutes ?? SendNotificationEvent::DEFAULT_DEDUPE_WINDOW_MINUTES);
        $windowMinutes = $windowMinutes > 0 ? $windowMinutes : SendNotificationEvent::DEFAULT_DEDUPE_WINDOW_MINUTES;

        \Log::info('SendFcmNotificationListener started', [
            'notifiable_id' => $event->notifiable->id ?? null,
            'has_token' => !empty($event->notifiable->fcm_token),
            'dedupe_key' => $dedupeKey,
            'dedupe_window_minutes' => $windowMinutes,
        ]);

        $isDuplicate = Notification::query()
            ->where('notifiable_id', $event->notifiable->id)
            ->where('notifiable_type', get_class($event->notifiable))
            ->where('dedupe_key', $dedupeKey)
            ->where('created_at', '>=', Carbon::now()->subMinutes($windowMinutes))
            ->exists();

        if ($isDuplicate) {
            \Log::info('Duplicate notification skipped', [
                'notifiable_id' => $event->notifiable->id ?? null,
                'notifiable_type' => get_class($event->notifiable),
                'dedupe_key' => $dedupeKey,
            ]);
            return;
        }

        $event->notifiable->notifications()->create([
            'title' => $event->title,
            'body' => $event->body,
            'is_read' => false,
            'target_type' => $event->target_type,
            'dedupe_key' => $dedupeKey,
        ]);

        if ($event->notifiable->fcm_token) {
            $result = $this->service->sendNotification(
                $event->notifiable->fcm_token,
                $event->title,
                $event->body,
                $event->data
            );

            \Log::info('FCM send result', [
                'result' => $result,
            ]);
        } else {
            \Log::warning('Notifiable has no FCM token', [
                'notifiable_id' => $event->notifiable->id ?? null,
                'notifiable_type' => get_class($event->notifiable),
            ]);
        }
    }

    private function resolveDedupeKey(SendNotificationEvent $event): string
    {
        if (!empty($event->dedupe_key)) {
            return $event->dedupe_key;
        }

        return hash('sha256', implode('|', [
            get_class($event->notifiable),
            (string) $event->notifiable->id,
            (string) $event->title,
            (string) $event->body,
            (string) $event->target_type,
        ]));
    }
}
