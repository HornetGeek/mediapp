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
        ?int $dedupeWindowMinutes = null
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
