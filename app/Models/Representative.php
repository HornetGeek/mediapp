<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Representative extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_id',
        'company_catalog_id',
        'requested_company_name',
        'password',
        'status',
        'registration_status',
        'daily_visits_limit',
        'fcm_token',
    ];
    protected $hidden = [
        'password',
    ];



    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function companyCatalog()
    {
        return $this->belongsTo(RepCompanyCatalog::class, 'company_catalog_id');
    }

    public function isRegistrationActive(): bool
    {
        return ($this->registration_status ?? 'active') === 'active';
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function areas()
    {
        return $this->belongsToMany(Area::class, 'area_representative', 'representative_id', 'area_id');
    }

    public function lines()
    {
        return $this->belongsToMany(Line::class, 'line_representative', 'representative_id', 'line_id');
    }

    public function favoriteDoctors()
    {
        return $this->belongsToMany(Doctors::class, 'doctor_representative_favorite', 'representative_id', 'doctors_id');
    }

    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }




    public function scopeFilter($query, $filters)
    {

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }
    }
}
