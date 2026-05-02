<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class NotificationBroadcast extends Model
{
    use HasFactory;

    protected $fillable = [
        'super_admin_id',
        'title',
        'body',
        'image_path',
        'video_path',
        'media_type',
        'delivery_type',
        'display_type',
        'is_skippable',
        'target_type',
        'target_specialty_ids',
        'status',
        'recipient_count',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'target_specialty_ids' => 'array',
        'sent_at' => 'datetime',
        'recipient_count' => 'integer',
        'is_skippable' => 'boolean',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }

    public function getVideoUrlAttribute(): ?string
    {
        return $this->video_path
            ? Storage::disk('public')->url($this->video_path)
            : null;
    }

    public function targetSpecialties()
    {
        $ids = $this->target_specialty_ids ?? [];
        if ($this->target_type !== 'specialties' || empty($ids)) {
            return Specialty::whereRaw('1 = 0');
        }

        return Specialty::whereIn('id', $ids);
    }
}
