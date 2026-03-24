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

    public $notifiable;
    public $title;
    public $body;
    public $data;
    public $target_type;

    /**
     * Create a new event instance.
     */
    public function __construct($notifiable, $title, $body, $target_type, $data = [])
    {
        $this->notifiable = $notifiable;
        $this->title = $title;
        $this->body = $body;
        $this->target_type = $target_type;
        $this->data = $data;
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
