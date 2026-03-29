<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'body', 'is_read', 'target_type', 'dedupe_key', 'notifiable_id', 'notifiable_type'];

    public function notifiable()
    {
        return $this->morphTo();
    }
}
