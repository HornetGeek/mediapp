<?php

namespace App\Services;

use App\Models\Doctors;
use App\Models\Notification;
use App\Models\PushNotificationCampaign;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DoctorSpecialtyPushNotificationService
{
    public function __construct(
        private FirebaseNotificationService $firebaseNotificationService
    ) {
    }

    public function send(
        int $senderUserId,
        int $specialtyId,
        string $title,
        string $body,
        ?string $imagePath = null,
        ?string $videoPath = null,
        string $displayType = 'list',
        bool $isSkippable = true,
        string $deliveryType = 'both'
    ): PushNotificationCampaign
    {
        $imageUrl = $imagePath ? Storage::disk('public')->url($imagePath) : null;
        $videoUrl = $videoPath ? Storage::disk('public')->url($videoPath) : null;
        $mediaType = $videoUrl ? 'video' : ($imageUrl ? 'image' : 'none');
        $deliveryType = in_array($deliveryType, ['both', 'push_only', 'in_app_only'], true) ? $deliveryType : 'both';
        $displayType = $displayType === 'modal' ? 'modal' : 'list';
        if ($deliveryType === 'push_only') {
            $displayType = 'list';
        }
        $isSkippable = $displayType === 'modal' ? $isSkippable : true;

        $campaign = PushNotificationCampaign::create([
            'sender_user_id' => $senderUserId,
            'specialty_id' => $specialtyId,
            'title' => $title,
            'body' => $body,
            'image_path' => $imagePath,
            'video_path' => $videoPath,
            'display_type' => $displayType,
            'is_skippable' => $isSkippable,
            'media_type' => $mediaType,
            'delivery_type' => $deliveryType,
            'total_doctors' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
        ]);

        $totalDoctors = 0;
        $sentCount = 0;
        $failedCount = 0;

        Doctors::query()
            ->where('specialty_id', $specialtyId)
            ->orderBy('id')
            ->chunkById(100, function ($doctors) use ($campaign, $specialtyId, $title, $body, $imageUrl, $videoUrl, $mediaType, $displayType, $isSkippable, $deliveryType, &$totalDoctors, &$sentCount, &$failedCount) {
                foreach ($doctors as $doctor) {
                    $totalDoctors++;
                    $dedupeKey = sprintf('admin_specialty_push:%d:doctor:%d', (int) $campaign->id, (int) $doctor->id);

                    if ($deliveryType !== 'push_only') {
                        Notification::create([
                            'title' => $title,
                            'body' => $body,
                            'image_url' => $imageUrl,
                            'video_url' => $videoUrl,
                            'media_type' => $mediaType,
                            'display_type' => $displayType,
                            'is_skippable' => $isSkippable,
                            'is_read' => false,
                            'target_type' => 'doctor',
                            'dedupe_key' => $dedupeKey,
                            'dedupe_fingerprint' => $this->buildDedupeFingerprint(Doctors::class, (int) $doctor->id, $dedupeKey),
                            'notifiable_id' => $doctor->id,
                            'notifiable_type' => Doctors::class,
                        ]);
                    }

                    if ($deliveryType === 'in_app_only' || empty($doctor->fcm_token)) {
                        continue;
                    }

                    try {
                        $result = $this->firebaseNotificationService->sendNotification(
                            $doctor->fcm_token,
                            $title,
                            $body,
                            [
                                'type' => 'admin_push_notification',
                                'target_type' => 'doctor',
                                'specialty_id' => $specialtyId,
                                'campaign_id' => $campaign->id,
                                'delivery_type' => $deliveryType,
                                'display_type' => $displayType,
                                'is_skippable' => $isSkippable ? '1' : '0',
                                'media_type' => $mediaType,
                            ],
                            $mediaType === 'image' ? $imageUrl : null
                        );

                        if ($result === null) {
                            $failedCount++;
                        } else {
                            $sentCount++;
                        }
                    } catch (Throwable $exception) {
                        $failedCount++;
                        Log::error('Admin specialty push notification failed for doctor', [
                            'campaign_id' => $campaign->id,
                            'doctor_id' => $doctor->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $campaign->update([
            'total_doctors' => $totalDoctors,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ]);

        return $campaign->refresh();
    }

    private function buildDedupeFingerprint(string $notifiableType, int $notifiableId, string $dedupeKey): string
    {
        return hash('sha256', implode('|', [
            $notifiableType,
            (string) $notifiableId,
            $dedupeKey,
        ]));
    }
}
