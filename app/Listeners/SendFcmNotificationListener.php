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
        if ($dedupeKey === null) {
            \Log::error('Send notification skipped: missing dedupe key', [
                'notifiable_id' => $event->notifiable->id ?? null,
                'notifiable_type' => $notifiableType,
                'title' => $event->title ?? null,
                'target_type' => $event->target_type ?? null,
            ]);

            return;
        }

        $now = Carbon::now();
        $dedupeFingerprint = $this->buildDedupeFingerprint(
            $notifiableType,
            (int) $event->notifiable->id,
            $dedupeKey
        );

        \Log::info('SendFcmNotificationListener started', [
            'notifiable_id' => $event->notifiable->id ?? null,
            'has_token' => !empty($event->notifiable->fcm_token),
            'notifiable_type' => $notifiableType,
            'dedupe_key' => $dedupeKey,
            'dedupe_fingerprint' => $dedupeFingerprint,
            'dedupe_window_minutes' => $event->dedupe_window_minutes ?? null,
        ]);

        $inserted = 0;
        try {
            $inserted = Notification::query()->insertOrIgnore([
                'title' => $event->title,
                'body' => $event->body,
                'is_read' => false,
                'target_type' => $event->target_type,
                'dedupe_key' => $dedupeKey,
                'dedupe_fingerprint' => $dedupeFingerprint,
                'notifiable_id' => $event->notifiable->id,
                'notifiable_type' => $notifiableType,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $e) {
            \Log::error('Failed while persisting deduplicated notification', [
                'notifiable_id' => $event->notifiable->id ?? null,
                'notifiable_type' => $notifiableType,
                'dedupe_key' => $dedupeKey,
                'dedupe_fingerprint' => $dedupeFingerprint,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $shouldLogBookedDebug = $this->shouldLogBookedDebug($dedupeKey);
        $resolvedNotification = null;
        if ($shouldLogBookedDebug) {
            $resolvedNotification = Notification::query()
                ->where('dedupe_fingerprint', $dedupeFingerprint)
                ->first(['id', 'created_at']);
        }

        if ($inserted === 0) {
            if ($shouldLogBookedDebug) {
                \Log::info('Booked notification dedupe debug', [
                    'notifiable_id' => $event->notifiable->id ?? null,
                    'notifiable_type' => $notifiableType,
                    'dedupe_key' => $dedupeKey,
                    'dedupe_fingerprint' => $dedupeFingerprint,
                    'inserted' => 0,
                    'notification_id' => $resolvedNotification?->id,
                    'created_at' => $resolvedNotification?->created_at?->toDateTimeString(),
                ]);
            }

            \Log::info('Duplicate notification skipped', [
                'notifiable_id' => $event->notifiable->id ?? null,
                'notifiable_type' => $notifiableType,
                'dedupe_key' => $dedupeKey,
                'dedupe_fingerprint' => $dedupeFingerprint,
            ]);
            return;
        }

        if ($shouldLogBookedDebug) {
            \Log::info('Booked notification dedupe debug', [
                'notifiable_id' => $event->notifiable->id ?? null,
                'notifiable_type' => $notifiableType,
                'dedupe_key' => $dedupeKey,
                'dedupe_fingerprint' => $dedupeFingerprint,
                'inserted' => 1,
                'notification_id' => $resolvedNotification?->id,
                'created_at' => $resolvedNotification?->created_at?->toDateTimeString(),
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

    private function resolveDedupeKey(SendNotificationEvent $event): ?string
    {
        $dedupeKey = trim((string) ($event->dedupe_key ?? ''));

        if ($dedupeKey !== '') {
            return $dedupeKey;
        }

        return null;
    }

    private function buildDedupeFingerprint(
        string $notifiableType,
        int $notifiableId,
        string $dedupeKey
    ): string {
        return hash('sha256', implode('|', [
            $notifiableType,
            (string) $notifiableId,
            $dedupeKey,
        ]));
    }

    private function shouldLogBookedDebug(string $dedupeKey): bool
    {
        if (!config('notifications.debug', false)) {
            return false;
        }

        return preg_match('/^appointment:\d+:booked:/', $dedupeKey) === 1;
    }
}
