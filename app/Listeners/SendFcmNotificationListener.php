<?php

namespace App\Listeners;

use App\Events\SendNotificationEvent;
use App\Models\Notification;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Carbon;
use Throwable;

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
        if (empty($event->notifiable) || empty($event->notifiable->id)) {
            \Log::error('Send notification skipped: missing notifiable target');
            return;
        }

        $notifiableType = get_class($event->notifiable);
        $dedupeKey = $this->resolveDedupeKey($event);
        $windowMinutes = (int) ($event->dedupe_window_minutes ?? SendNotificationEvent::DEFAULT_DEDUPE_WINDOW_MINUTES);
        $windowMinutes = $windowMinutes > 0 ? $windowMinutes : SendNotificationEvent::DEFAULT_DEDUPE_WINDOW_MINUTES;

        \Log::info('SendFcmNotificationListener started', [
            'notifiable_id' => $event->notifiable->id ?? null,
            'has_token' => !empty($event->notifiable->fcm_token),
            'notifiable_type' => $notifiableType,
            'dedupe_key' => $dedupeKey,
            'dedupe_window_minutes' => $windowMinutes,
        ]);

        $isDuplicate = false;
        try {
            $isDuplicate = Notification::query()
                ->where('notifiable_id', $event->notifiable->id)
                ->where('notifiable_type', $notifiableType)
                ->where('dedupe_key', $dedupeKey)
                ->where('created_at', '>=', Carbon::now()->subMinutes($windowMinutes))
                ->exists();
        } catch (Throwable $e) {
            \Log::error('Failed while checking notification deduplication state', [
                'notifiable_id' => $event->notifiable->id ?? null,
                'notifiable_type' => $notifiableType,
                'dedupe_key' => $dedupeKey,
                'error' => $e->getMessage(),
            ]);
        }

        if ($isDuplicate) {
            \Log::info('Duplicate notification skipped', [
                'notifiable_id' => $event->notifiable->id ?? null,
                'notifiable_type' => $notifiableType,
                'dedupe_key' => $dedupeKey,
            ]);
            return;
        }

        try {
            $event->notifiable->notifications()->create([
                'title' => $event->title,
                'body' => $event->body,
                'is_read' => false,
                'target_type' => $event->target_type,
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (Throwable $e) {
            \Log::error('Failed to persist notification in database', [
                'notifiable_id' => $event->notifiable->id ?? null,
                'notifiable_type' => $notifiableType,
                'title' => $event->title,
                'error' => $e->getMessage(),
            ]);
        }

        if ($event->notifiable->fcm_token) {
            try {
                $result = $this->service->sendNotification(
                    $event->notifiable->fcm_token,
                    $event->title,
                    $event->body,
                    $event->data
                );

                \Log::info('FCM send result', [
                    'result' => $result,
                    'notifiable_id' => $event->notifiable->id ?? null,
                    'notifiable_type' => $notifiableType,
                ]);
            } catch (Throwable $e) {
                \Log::error('Unhandled error while sending FCM notification', [
                    'notifiable_id' => $event->notifiable->id ?? null,
                    'notifiable_type' => $notifiableType,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            \Log::warning('Notifiable has no FCM token', [
                'notifiable_id' => $event->notifiable->id ?? null,
                'notifiable_type' => $notifiableType,
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
