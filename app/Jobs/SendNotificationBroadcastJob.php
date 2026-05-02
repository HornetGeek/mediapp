<?php

namespace App\Jobs;

use App\Events\SendNotificationEvent;
use App\Models\Doctors;
use App\Models\NotificationBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(public int $broadcastId) {}

    public function handle(): void
    {
        $broadcast = NotificationBroadcast::find($this->broadcastId);
        if (!$broadcast) {
            Log::error('SendNotificationBroadcastJob: broadcast not found', [
                'broadcast_id' => $this->broadcastId,
            ]);
            return;
        }

        $broadcast->update(['status' => 'sending']);

        try {
            $query = Doctors::query();

            if ($broadcast->target_type === 'specialties') {
                $specialtyIds = array_filter((array) ($broadcast->target_specialty_ids ?? []));
                if (empty($specialtyIds)) {
                    $broadcast->update([
                        'status' => 'failed',
                        'error' => 'Broadcast targeted "specialties" but had no specialty ids.',
                    ]);
                    return;
                }
                $query->whereIn('specialty_id', $specialtyIds);
            }

            $count = 0;
            $imageUrl = $broadcast->image_url;
            $dedupeKey = 'broadcast:' . $broadcast->id;

            $query->orderBy('id')->chunkById(200, function ($doctors) use ($broadcast, $dedupeKey, $imageUrl, &$count) {
                foreach ($doctors as $doctor) {
                    event(new SendNotificationEvent(
                        $doctor,
                        $broadcast->title,
                        $broadcast->body,
                        'doctors',
                        [],
                        $dedupeKey,
                        null,
                        $imageUrl
                    ));
                    $count++;
                }
            });

            $broadcast->update([
                'status' => 'sent',
                'recipient_count' => $count,
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('SendNotificationBroadcastJob failed', [
                'broadcast_id' => $broadcast->id,
                'error' => $e->getMessage(),
            ]);
            $broadcast->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
