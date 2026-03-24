<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = ['app_type', 'version', 'is_forced'];
}
