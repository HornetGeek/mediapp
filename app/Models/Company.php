<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Company extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'name',
        'package_id',
        'phone',
        'email',
        'password',
        'visits_per_day',
        'num_of_reps',
        'subscription_start',
        'subscription_end',
        'status',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }


    public function representatives()
    {
        return $this->hasMany(Representative::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function lines()
    {
        return $this->hasMany(Line::class);
    }

    public function areas()
    {
        return $this->hasMany(Area::class);
    }
    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }
}
