<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorBlock extends Model
{
    use HasFactory;

    protected $fillable = ['doctors_id', 'blockable_id', 'blockable_type'];

    public function blockable()
    {
        return $this->morphTo();
    }


    public function scopeFilter($query, $filters)
    {
        if (!empty($filters['name'])) {
            $name = $filters['name'];

            $query->whereHasMorph('blockable', ['App\Models\Representative', 'App\Models\Company'],
                function ($q, $type) use ($name) {
                    $q->where('name', 'like', "%{$name}%");
                }
            );
        }
    }
}
