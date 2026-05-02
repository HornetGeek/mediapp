<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendNotificationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const DEFAULT_DEDUPE_WINDOW_MINUTES = 10;

    public $notifiable;
    public $title;
    public $body;
    public $data;
    public $target_type;
    public $dedupe_key;
    // Deprecated for runtime dedupe logic; kept for constructor backward compatibility.
    public $dedupe_window_minutes;
    public $image_url;
    public ?string $video_url = null;
    public string $media_type = 'none';
    public string $delivery_type = 'both';
    public string $display_type = 'list';
    public bool $is_skippable = true;

    /**
     * Create a new event instance.
     */
    public function __construct(
        $notifiable,
        $title,
        $body,
        $targetTypeOrData = null,
        array $data = [],
        ?string $dedupeKey = null,
        ?int $dedupeWindowMinutes = null,
        ?string $imageUrl = null
    )
    {
        $this->notifiable = $notifiable;
        $this->title = $title;
        $this->body = $body;

        if (is_array($targetTypeOrData)) {
            $this->target_type = null;
            $this->data = $targetTypeOrData;
        } else {
            $this->target_type = $targetTypeOrData;
            $this->data = $data;
        }

        $this->dedupe_key = $dedupeKey;
        $window = $dedupeWindowMinutes ?? self::DEFAULT_DEDUPE_WINDOW_MINUTES;
        $this->dedupe_window_minutes = $window > 0 ? $window : self::DEFAULT_DEDUPE_WINDOW_MINUTES;
        $this->image_url = $imageUrl !== null && $imageUrl !== '' ? $imageUrl : null;

        $payload = $this->data ?? [];

        $videoUrl = $payload['video_url'] ?? null;
        $this->video_url = is_string($videoUrl) && $videoUrl !== '' ? $videoUrl : null;

        $imageFromData = $payload['image_url'] ?? null;
        if ($this->image_url === null && is_string($imageFromData) && $imageFromData !== '') {
            $this->image_url = $imageFromData;
        }

        $mediaType = $payload['media_type'] ?? null;
        $this->media_type = in_array($mediaType, ['none', 'image', 'video'], true)
            ? $mediaType
            : ($this->video_url ? 'video' : ($this->image_url ? 'image' : 'none'));

        $deliveryType = $payload['delivery_type'] ?? null;
        $this->delivery_type = in_array($deliveryType, ['both', 'push_only', 'in_app_only'], true)
            ? $deliveryType
            : 'both';

        $displayType = $payload['display_type'] ?? null;
        $this->display_type = in_array($displayType, ['list', 'modal'], true) ? $displayType : 'list';
        if ($this->delivery_type === 'push_only') {
            $this->display_type = 'list';
        }

        $skippable = $payload['is_skippable'] ?? null;
        if (is_bool($skippable)) {
            $this->is_skippable = $skippable;
        } elseif (is_string($skippable) || is_int($skippable)) {
            $this->is_skippable = filter_var($skippable, FILTER_VALIDATE_BOOLEAN);
        } else {
            $this->is_skippable = true;
        }
        if ($this->display_type !== 'modal') {
            $this->is_skippable = true;
        }
    }

    // /**
    //  * Get the channels the event should broadcast on.
    //  *
    //  * @return array<int, \Illuminate\Broadcasting\Channel>
    //  */
    // public function broadcastOn(): array
    // {
    //     return [
    //         new PrivateChannel('channel-name'),
    //     ];
    // }
}
