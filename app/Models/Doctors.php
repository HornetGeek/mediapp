<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Doctors extends Authenticatable
{
    use HasApiTokens, HasFactory;



    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'address_1',
        'address_2',
        'specialty_id',
        'status',
        'from_date',
        'to_date',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'date' => 'date',
    ];


    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }
    public function availableTimes()
    {
        return $this->hasMany(DoctorAvailability::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function favoredByReps()
    {
        return $this->belongsToMany(Representative::class, 'doctor_representative_favorite', 'doctors_id', 'representative_id')
            ->withPivot('is_fav');
    }

    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }
    public function blocks()
    {
        return $this->hasMany(DoctorBlock::class, 'doctors_id');
    }






    // Search of Doctors 
    public function scopeFilter($query, $filters)
    {
        if (!empty($filters['name'])) {
            $name = trim($filters['name']);
            $query->where('name', 'like', "%{$name}%");
        }

        if (!empty($filters['speciality'])) {
            $query->where('speciality', 'like', '%' . $filters['speciality'] . '%');
        }

        if (!empty($filters['location'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('address_1', 'like', '%' . $filters['location'] . '%')
                    ->orWhere('address_2', 'like', '%' . $filters['location'] . '%');
            });
        }

        if (!empty($filters['specialty_id'])) {
            $query->where('specialty_id', $filters['specialty_id']);
        }

        return $query;
    }

    public function scopeFavoriteFilter($query, $searchTerm)
    {
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('address_1', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('specialty', function ($specialtyQuery) use ($searchTerm) {
                        $specialtyQuery->where('name', 'like', '%' . $searchTerm . '%');
                    });
            });
        }
    }

    // // Accessor: عند قراءة الاسم يتم تحويله لأحرف كبيرة
    // public function getNameAttribute($value)
    // {
    //     return strtoupper($value);
    // }

    // // Mutator: عند تخزين الاسم يتم تحويله لأحرف صغيرة
    // public function setNameAttribute($value)
    // {
    //     $this->attributes['name'] = strtolower($value);
    // }

    // Laravel 9 and above is using this method 
    // use Illuminate\Database\Eloquent\Casts\Attribute;
    // public function name(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn($value) => strtoupper($value),
    //         set: fn($value) => strtolower($value),
    //     );
    // }
}
