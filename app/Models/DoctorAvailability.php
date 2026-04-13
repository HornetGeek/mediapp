<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctors_id',
        'date',
        'start_time',
        'end_time',
        'ends_next_day',
        'max_reps_per_range',
        'status',
    ];

    protected $casts = [
        'ends_next_day' => 'boolean',
        'max_reps_per_range' => 'integer',
    ];
    
    public function doctor()
    {
        return $this->belongsTo(Doctors::class, 'doctors_id');
    }
}
