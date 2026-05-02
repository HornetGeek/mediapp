<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'image_url',
        'video_url',
        'media_type',
        'display_type',
        'is_skippable',
        'acknowledged_at',
        'is_read',
        'target_type',
        'dedupe_key',
        'dedupe_fingerprint',
        'notifiable_id',
        'notifiable_type',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_skippable' => 'boolean',
        'acknowledged_at' => 'datetime',
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }
}
