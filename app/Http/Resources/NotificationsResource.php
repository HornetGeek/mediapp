<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'display_type' => $this->display_type ?? 'list',
            'is_skippable' => (bool) ($this->is_skippable ?? true),
            'media_type' => $this->media_type ?? 'none',
            'image_url' => $this->image_url,
            'video_url' => $this->video_url,
            'is_read' => (int) ($this->is_read ?? false),
            'acknowledged_at' => $this->acknowledged_at?->toDateTimeString(),
          	'target_type' => $this->target_type ?? null
        ];
    }
}
