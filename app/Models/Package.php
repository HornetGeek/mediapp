<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    public const PLAN_QUARTERLY = 'quarterly';
    public const PLAN_SEMI_ANNUAL = 'semi_annual';
    public const PLAN_ANNUAL = 'annual';
    public const PLAN_CUSTOM_DAYS = 'custom_days';

    public const PLAN_TO_MONTHS = [
        self::PLAN_QUARTERLY => 3,
        self::PLAN_SEMI_ANNUAL => 6,
        self::PLAN_ANNUAL => 12,
    ];

    public const PLAN_TO_DAYS = [
        self::PLAN_QUARTERLY => 90,
        self::PLAN_SEMI_ANNUAL => 180,
        self::PLAN_ANNUAL => 365,
    ];

    protected $fillable = [
        'name',
        'price',
        'duration',
        'plan_type',
        'billing_months',
        'description',
    ];

    protected $casts = [
        'billing_months' => 'integer',
    ];

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    public static function isStandardPlan(?string $planType): bool
    {
        return in_array($planType, array_keys(self::PLAN_TO_MONTHS), true);
    }

    public function resolvePlanType(): string
    {
        if (is_string($this->plan_type) && array_key_exists($this->plan_type, self::PLAN_TO_MONTHS + [self::PLAN_CUSTOM_DAYS => null])) {
            return $this->plan_type;
        }

        return match ((int) $this->duration) {
            90 => self::PLAN_QUARTERLY,
            180 => self::PLAN_SEMI_ANNUAL,
            365 => self::PLAN_ANNUAL,
            default => self::PLAN_CUSTOM_DAYS,
        };
    }

    public function resolveBillingMonths(): ?int
    {
        if ($this->billing_months !== null) {
            return (int) $this->billing_months;
        }

        return self::PLAN_TO_MONTHS[$this->resolvePlanType()] ?? null;
    }

    public function getPlanLabelAttribute(): string
    {
        return match ($this->resolvePlanType()) {
            self::PLAN_QUARTERLY => 'Quarterly',
            self::PLAN_SEMI_ANNUAL => 'Semi-Annual',
            self::PLAN_ANNUAL => 'Annual',
            default => 'Custom Days',
        };
    }
}
