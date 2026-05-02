<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PushNotificationCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_user_id',
        'specialty_id',
        'title',
        'body',
        'total_doctors',
        'sent_count',
        'failed_count',
    ];

    protected $casts = [
        'total_doctors' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }
}
