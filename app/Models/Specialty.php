<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class Specialty extends Model
{
    use HasFactory;
    

    protected $fillable = [
        'name'
    ];

    public function doctors()
    {
        return $this->hasMany(Doctors::class);
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }
}
