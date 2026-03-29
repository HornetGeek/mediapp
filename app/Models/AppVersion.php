<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    use HasFactory;

    public const APP_COMPANY = 'company';
    public const APP_DOCTOR = 'doctor';

    public const PLATFORM_BOTH = 'both';
    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_IOS = 'ios';

    public const SUPPORTED_APP_TYPES = [
        self::APP_COMPANY,
        self::APP_DOCTOR,
    ];

    public const SUPPORTED_PLATFORMS = [
        self::PLATFORM_BOTH,
        self::PLATFORM_ANDROID,
        self::PLATFORM_IOS,
    ];

    public $timestamps = false;
    protected $fillable = ['app_type', 'platform', 'version', 'is_forced'];

    protected $casts = [
        'is_forced' => 'boolean',
    ];
}
