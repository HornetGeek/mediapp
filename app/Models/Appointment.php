<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctors_id',
        'representative_id',
        'company_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'appointment_code',
        'cancelled_by',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s', //
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctors::class, 'doctors_id');
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class, 'representative_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($appointment) {
            $appointment->appointment_code = (string) Str::uuid();
        });
    }

    public function scopeFilter($query, $filters)
    {

        if (!empty($filters['name'])) {
            $name = $filters['name'];

            $query->where(function ($q) use ($name) {
                $q->whereHas('representative', function ($q2) use ($name) {
                    $q2->where('name', 'like', "%{$name}%");
                })
                    ->orWhereHas('company', function ($q3) use ($name) {
                        $q3->where('name', 'like', "%{$name}%");
                    });
            });
        }

        // if (!empty($filters['month'])) {
        //     $query->whereMonth('date', $filters['month']);
        // }

        // if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
        //     $query->whereBetween('date', [$filters['from_date'], $filters['to_date']]);
        // }
    }

    public function scopeAdvancedFilter($query, $filters)
    {
        // filter by doctor name
        if (!empty($filters['doctor_name'])) {
            $query->whereHas('doctor', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['doctor_name'] . '%');
            });
        }

        // filter by company name
        if (!empty($filters['company_name'])) {
            $query->whereHas('company', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['company_name'] . '%');
            });
        }

        // filter date between (from and to date)
        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->whereBetween('date', [$filters['from_date'], $filters['to_date']]);
        }

        return $query;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
